<?php
/**
 * Servicio de Seguimiento Automático de Consultas - FLACSO Uruguay
 *
 * Orquesta el ciclo periódico de seguimiento en WP-Cron, la reevaluación
 * dinámica de la cohorte contra FLACSO_Academic_Catalog, la compilación
 * de tokens canónicos y el despacho vía Mautic con fallback automático a Mailjet.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

if (!class_exists('FLACSO_DB')) {
    require_once dirname(__DIR__, 3) . '/includes/database/class-flacso-db.php';
}
if (!class_exists('FLACSO_Offer_Inquiry_Repository')) {
    require_once dirname(__DIR__, 3) . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
}
if (!class_exists('FLACSO_Mautic_Client')) {
    require_once dirname(__DIR__, 3) . '/includes/integrations/class-flacso-mautic-client.php';
}
if (!class_exists('FLACSO_Mailjet_Client')) {
    require_once dirname(__DIR__, 3) . '/includes/integrations/class-flacso-mailjet-client.php';
}
if (!class_exists('FLACSO_Inquiry_Marketing_Service')) {
    require_once __DIR__ . '/class-flacso-inquiry-marketing-service.php';
}
if (!class_exists('FLACSO_Academic_Catalog') && file_exists(dirname(__DIR__, 3) . '/modules/oferta-academica/includes/class-academic-catalog.php')) {
    require_once dirname(__DIR__, 3) . '/modules/oferta-academica/includes/class-academic-catalog.php';
}

class FLACSO_Inquiry_Followup_Service {

    /**
     * Nombre del hook para el evento periódico de WP-Cron.
     */
    public const CRON_HOOK = 'flacso_inquiry_followup_cron';

    /**
     * Registra el hook en WP-Cron y programa el evento recurrente si no existe.
     */
    public static function init(): void {
        if (function_exists('add_action')) {
            add_action(self::CRON_HOOK, [self::class, 'run_followup_cycle']);
        }

        if (function_exists('wp_next_scheduled') && !wp_next_scheduled(self::CRON_HOOK)) {
            if (function_exists('wp_schedule_event')) {
                wp_schedule_event(time(), 'hourly', self::CRON_HOOK);
            }
        }
    }

    /**
     * Ejecuta el ciclo de seguimiento programado.
     *
     * @param int                                  $limit      Máximo de consultas a procesar por ciclo.
     * @param FLACSO_Offer_Inquiry_Repository|null $repository Instancia opcional inyectada.
     * @return array Resumen estructurado del ciclo.
     */
    public static function run_followup_cycle(int $limit = 25, ?FLACSO_Offer_Inquiry_Repository $repository = null): array {
        $enabled = function_exists('get_option') ? (bool) get_option('flacso_inquiry_followup_enabled', false) : false;
        if (!$enabled) {
            return [
                'ok'        => true,
                'processed' => 0,
                'status'    => 'disabled',
            ];
        }

        $repo = $repository ?? (class_exists('FLACSO_Offer_Inquiry_Repository') ? new FLACSO_Offer_Inquiry_Repository() : null);
        if (!$repo) {
            return [
                'ok'        => false,
                'processed' => 0,
                'status'    => 'failed',
                'error'     => 'Repositorio no disponible',
            ];
        }

        $claimed = $repo->claim_due_followups($limit);
        if (empty($claimed)) {
            return [
                'ok'        => true,
                'processed' => 0,
                'status'    => 'idle',
                'results'   => [],
            ];
        }

        $results = [];
        foreach ($claimed as $inquiry) {
            try {
                $results[] = self::process_single_followup($inquiry, $repo);
            } catch (\Throwable $e) {
                error_log('[FLACSO Followup] Excepción inesperada procesando consulta ' . ($inquiry['id'] ?? '') . ': ' . $e->getMessage());
                if (!empty($inquiry['id'])) {
                    $repo->update_followup_status((string)$inquiry['id'], 'failed', 'Error inesperado: ' . $e->getMessage());
                }
                $results[] = [
                    'ok'     => false,
                    'id'     => (string)($inquiry['id'] ?? ''),
                    'action' => 'failed',
                    'error'  => $e->getMessage(),
                ];
            }
        }

        return [
            'ok'        => true,
            'processed' => count($results),
            'status'    => 'completed',
            'results'   => $results,
        ];
    }

    /**
     * Procesa el seguimiento individual de una consulta, ejecutando la reevaluación
     * dinámica de la cohorte, la selección de plantilla y el despacho con fallback.
     *
     * @param array                                $inquiry    Datos de la consulta reclamada.
     * @param FLACSO_Offer_Inquiry_Repository|null $repository Repositorio inyectado opcional.
     * @return array Resultado de la operación.
     */
    public static function process_single_followup(array $inquiry, ?FLACSO_Offer_Inquiry_Repository $repository = null): array {
        $repo = $repository ?? (class_exists('FLACSO_Offer_Inquiry_Repository') ? new FLACSO_Offer_Inquiry_Repository() : null);
        $id = trim((string)($inquiry['id'] ?? ''));

        if ($id === '') {
            return [
                'ok'     => false,
                'status' => 'failed',
                'error'  => 'ID de consulta inválido',
            ];
        }

        $email = strtolower(trim((string)($inquiry['emailNormalized'] ?? $inquiry['email_normalized'] ?? $inquiry['email'] ?? '')));
        $offer_wp_id = (int)($inquiry['offerWpId'] ?? $inquiry['offer_wp_id'] ?? 0);
        $inquiry_at = trim((string)($inquiry['inquiryAt'] ?? $inquiry['inquiry_at'] ?? $inquiry['createdAt'] ?? ''));

        // Regla 1 (Deduplicación por consulta posterior más reciente)
        if ($repo !== null && $repo->has_newer_inquiry_for_offer($email, $offer_wp_id, $inquiry_at)) {
            $reason = 'Existe consulta más reciente para esta oferta';
            $repo->update_followup_status($id, 'skipped', $reason);
            return [
                'ok'         => true,
                'status'     => 'skipped',
                'reason'     => $reason,
                'inquiry_id' => $id,
            ];
        }

        // Regla 2 (Oferta activa en WordPress)
        if ($offer_wp_id <= 0) {
            $reason = 'Oferta despublicada o eliminada';
            if ($repo !== null) {
                $repo->update_followup_status($id, 'skipped', $reason);
            }
            return [
                'ok'         => true,
                'status'     => 'skipped',
                'reason'     => $reason,
                'inquiry_id' => $id,
            ];
        }

        if (function_exists('get_post_status')) {
            $post_status = get_post_status($offer_wp_id);
            if (!$post_status || $post_status === 'trash') {
                $reason = 'Oferta despublicada o eliminada';
                if ($repo !== null) {
                    $repo->update_followup_status($id, 'skipped', $reason);
                }
                return [
                    'ok'         => true,
                    'status'     => 'skipped',
                    'reason'     => $reason,
                    'inquiry_id' => $id,
                ];
            }
        }

        // Regla 3 (Reevaluación de cohorte en tiempo real)
        $offer = [];
        if (class_exists('FLACSO_Academic_Catalog') && method_exists('FLACSO_Academic_Catalog', 'get_offer')) {
            try {
                $offer = FLACSO_Academic_Catalog::get_offer($offer_wp_id);
            } catch (\Throwable $t) {
                $offer = [];
            }
        }

        $cohorte = $offer['cohorte_consulta'] ?? $offer['cohorte_vigente'] ?? [];
        $reg_data = $cohorte['preinscripcion'] ?? [];
        $is_open = !empty($reg_data['abierta']);

        $cohort_name = (string)($cohorte['nombre'] ?? '');
        $cohort_number = isset($cohorte['numero']) ? (int)$cohorte['numero'] : null;
        $start_value = (string)($cohorte['fecha_inicio'] ?? '');
        $start_precision = (string)($cohorte['precision_fecha_inicio'] ?? 'dia');
        $modality = (string)($cohorte['modalidad'] ?? '');
        $preinscripcion_url = (string)($reg_data['url'] ?? '');
        $carta_url = (string)($cohorte['url_carta'] ?? $offer['url_carta'] ?? $offer['brochure_url'] ?? $offer['carta_url'] ?? $inquiry['cartaUrl'] ?? '');
        $program_name = (string)($offer['nombre'] ?? $inquiry['offerName'] ?? 'Oferta académica');
        $program_url = (string)($offer['url'] ?? $offer['link'] ?? $inquiry['urlBase'] ?? $inquiry['programUrl'] ?? '');

        $program_payload = [
            'name'                    => $program_name,
            'titulo_posgrado'         => $program_name,
            'cohortName'              => $cohort_name,
            'cohortNumber'            => $cohort_number,
            'startValue'              => $start_value,
            'startPrecision'          => $start_precision,
            'modalityLabel'           => $modality,
            'preinscripcionUrl'       => $preinscripcion_url,
            'cartaUrl'                => $carta_url,
            'urlBase'                 => $program_url,
            'programUrl'              => $program_url,
            'isInscripcionesAbiertas' => $is_open,
            'inscripciones_abiertas'   => $is_open,
        ];

        // Copia de consulta enriquecida con los datos vigentes para tokens canónicos
        $inquiry_payload = $inquiry;
        if ($cohort_name !== '') {
            $inquiry_payload['cohortName'] = $cohort_name;
        }
        if ($cohort_number !== null && $cohort_number > 0) {
            $inquiry_payload['cohortNumber'] = $cohort_number;
        }
        $inquiry_payload['preinscripcionUrl'] = $preinscripcion_url;
        if ($start_value !== '') {
            $inquiry_payload['startValue'] = $start_value;
        }
        if ($modality !== '') {
            $inquiry_payload['modalityLabel'] = $modality;
        }
        if ($carta_url !== '') {
            $inquiry_payload['cartaUrl'] = $carta_url;
        }

        // Regla 4 (Plantilla Mautic)
        if ($is_open) {
            $template_id = function_exists('get_option') ? (int) get_option('flacso_mautic_template_seguimiento_abierta', 0) : 0;
        } else {
            $template_id = function_exists('get_option') ? (int) get_option('flacso_mautic_template_seguimiento_cerrada', 0) : 0;
        }

        // Regla 5 (Compilación de Tokens y Despacho)
        $tokens = [];
        if (class_exists('FLACSO_Inquiry_Marketing_Service') && method_exists('FLACSO_Inquiry_Marketing_Service', 'compile_tokens')) {
            $tokens = FLACSO_Inquiry_Marketing_Service::compile_tokens($inquiry_payload, $program_payload, $is_open);
        }

        $contact_id = (int)($inquiry['mauticContactId'] ?? $inquiry['mautic_contact_id'] ?? 0);
        if ($contact_id <= 0 && class_exists('FLACSO_Inquiry_Marketing_Service') && method_exists('FLACSO_Inquiry_Marketing_Service', 'sync_inquiry')) {
            $sync_res = FLACSO_Inquiry_Marketing_Service::sync_inquiry($id, $inquiry_payload, $repo);
            if (!empty($sync_res['contact_id'])) {
                $contact_id = (int) $sync_res['contact_id'];
            }
        }

        $mautic_sent = false;
        $mautic_error = null;

        if ($contact_id > 0 && $template_id > 0 && class_exists('FLACSO_Mautic_Client') && method_exists('FLACSO_Mautic_Client', 'send_email_to_contact')) {
            $mautic_res = FLACSO_Mautic_Client::send_email_to_contact($template_id, $contact_id, $tokens);
            if (!empty($mautic_res['ok']) && ($mautic_res['status'] ?? '') === 'sent') {
                $mautic_sent = true;
            } else {
                $mautic_error = (string)($mautic_res['error'] ?? 'Error desconocido al enviar plantilla Mautic');
            }
        } else {
            if ($template_id <= 0) {
                $mautic_error = 'Plantilla de Mautic no configurada para seguimiento (' . ($is_open ? 'abierta' : 'cerrada') . ')';
            } elseif ($contact_id <= 0) {
                $mautic_error = 'Contacto no disponible en Mautic';
            }
        }

        if ($mautic_sent) {
            if ($repo !== null) {
                $repo->update_followup_status($id, 'sent', null, gmdate('c'));
            }
            return [
                'ok'         => true,
                'status'     => 'sent',
                'channel'    => 'mautic',
                'inquiry_id' => $id,
            ];
        }

        // Regla 6 (Fallback Automático a Mailjet)
        if (class_exists('FLACSO_Mailjet_Client') && method_exists('FLACSO_Mailjet_Client', 'send_offer_inquiry')) {
            $mailjet_res = FLACSO_Mailjet_Client::send_offer_inquiry($inquiry_payload, $program_payload);
            if (!empty($mailjet_res['ok']) && ($mailjet_res['status'] ?? '') === 'sent') {
                $note = 'Enviado vía Mailjet (Fallback)';
                if ($repo !== null) {
                    $repo->update_followup_status($id, 'sent', $note, gmdate('c'));
                }
                return [
                    'ok'         => true,
                    'status'     => 'sent',
                    'channel'    => 'mailjet',
                    'fallback'   => true,
                    'note'       => $note,
                    'inquiry_id' => $id,
                ];
            }

            $mailjet_error = (string)($mailjet_res['error'] ?? 'Error desconocido al enviar con Mailjet');
            $full_error = $mautic_error ? "Mautic: {$mautic_error} | Mailjet: {$mailjet_error}" : $mailjet_error;
            if ($repo !== null) {
                $repo->update_followup_status($id, 'failed', $full_error);
            }
            return [
                'ok'         => false,
                'status'     => 'failed',
                'error'      => $full_error,
                'inquiry_id' => $id,
            ];
        }

        $error_msg = $mautic_error ?: 'Mautic y Mailjet no disponibles';
        if ($repo !== null) {
            $repo->update_followup_status($id, 'failed', $error_msg);
        }
        return [
            'ok'         => false,
            'status'     => 'failed',
            'error'      => $error_msg,
            'inquiry_id' => $id,
        ];
    }
}
