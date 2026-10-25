<?php
/**
 * Servicio de procesamiento y persistencia de consultas de ofertas académicas.
 *
 * Aplica el principio "guardar primero, enviar después":
 * 1. Extraer consultaId (o generar UUIDv4).
 * 2. Comprobar idempotencia temprana en repositorio.
 * 3. Validar campos obligatorios mínimos.
 * 4. Enriquecer datos con catálogo académico o WordPress.
 * 5. Insertar registro en base de datos.
 * 7. Actualizar estado del correo en la base de datos.
 * 8. Retornar resultado estructurado.
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
if (!class_exists('FLACSO_Inquiry_Context_Service')) {
    require_once __DIR__ . '/class-flacso-inquiry-context-service.php';
}
if (!class_exists('FLACSO_Inquiry_Marketing_Service')) {
    require_once __DIR__ . '/class-flacso-inquiry-marketing-service.php';
}
if (!class_exists('FLACSO_Inquiry_Snapshot')) {
    require_once __DIR__ . '/class-flacso-inquiry-snapshot.php';
}
if (!class_exists('FLACSO_Inquiry_Delivery_Repository')) {
    require_once dirname(__DIR__, 3) . '/includes/database/repositories/class-flacso-inquiry-delivery-repository.php';
}
if (!class_exists('FLACSO_Offer_Campaigns')) {
    require_once __DIR__ . '/class-flacso-offer-campaigns.php';
}

class FLACSO_Offer_Inquiry_Service {

    /**
     * Procesa una consulta de oferta académica.
     *
     * @param array $data    Datos de la consulta (compatibles con snake_case y camelCase).
     * @param array $consent Evidencia de consentimiento comercial generada por el
     *                       servidor (FLACSO_Offer_Campaigns::form_consent()). Nunca
     *                       se toma de $data: llega del navegador.
     * @return array Resultado con ok, consulta_id, duplicate, email, etc.
     */
    public static function submit(array $data, array $consent = []): array {
        // 1. Extraer o generar consultaId
        $consulta_id = trim((string)($data['event_id'] ?? $data['consultaId'] ?? $data['consulta_id'] ?? $data['id'] ?? ''));
        if ($consulta_id === '') {
            $consulta_id = self::generate_uuidv4();
        }

        $repo = new FLACSO_Offer_Inquiry_Repository();

        // 2. Verificación temprana de idempotencia
        try {
            $existing = $repo->find_by_consulta_id($consulta_id);
            if ($existing !== null) {
                return [
                    'ok'                 => true,
                    'consulta_id'        => $consulta_id,
                    'duplicate'          => true,
                    'email'              => $existing['emailStatus'] ?? 'skipped',
                    'offer_status'       => $existing['offerStatus'] ?? null,
                    'cohort_number'      => isset($existing['cohortNumber']) && $existing['cohortNumber'] !== '' ? (int)$existing['cohortNumber'] : null,
                    'offer_abbreviation' => $existing['offerAbbreviation'] ?? null,
                    'code'               => 200,
                ];
            }
        } catch (\Throwable $e) {
            error_log('[FLACSO] Error en verificación temprana de idempotencia (oferta): ' . $e->getMessage());
            return [
                'ok'      => false,
                'code'    => 500,
                'error'   => 'db_error',
                'message' => $e->getMessage(),
            ];
        }

        // 3. Normalizar y validar campos requeridos
        $first_name = trim((string)($data['firstName'] ?? $data['nombre'] ?? ''));
        $last_name  = trim((string)($data['lastName'] ?? $data['apellido'] ?? ''));
        $full_name  = trim((string)($data['fullName'] ?? $data['nombre_apellido'] ?? ''));
        if ($full_name === '' && $first_name !== '') {
            $full_name = trim($first_name . ' ' . $last_name);
        } elseif ($first_name === '' && $full_name !== '') {
            $parts = explode(' ', $full_name);
            $first_name = $parts[0];
        }

        $email = trim((string)($data['email'] ?? $data['correo'] ?? ''));

        $offer_id = isset($data['offerWpId']) && $data['offerWpId'] !== ''
            ? (int)$data['offerWpId']
            : (isset($data['id_pagina']) && $data['id_pagina'] !== ''
                ? (int)$data['id_pagina']
                : (isset($data['offer_id']) && $data['offer_id'] !== ''
                    ? (int)$data['offer_id']
                    : (isset($data['post_id']) && $data['post_id'] !== ''
                        ? (int)$data['post_id']
                        : null)));

        $offer_name = trim((string)($data['offerName'] ?? $data['titulo_posgrado'] ?? $data['offer_name'] ?? $data['programa'] ?? $data['name'] ?? $data['title'] ?? ''));
        $offer_type = isset($data['offerType']) ? (string)$data['offerType'] : (isset($data['tipo_oferta']) ? (string)$data['tipo_oferta'] : null);

        // Validación de campos mínimos obligatorios
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'ok'      => false,
                'code'    => 422,
                'error'   => 'validation_error',
                'message' => 'El correo electrónico proporcionado es obligatorio y debe tener un formato válido.',
            ];
        }

        if ($first_name === '' && $full_name === '') {
            return [
                'ok'      => false,
                'code'    => 422,
                'error'   => 'validation_error',
                'message' => 'El nombre del solicitante es obligatorio.',
            ];
        }

        if (($offer_id === null || $offer_id <= 0) && $offer_name === '') {
            return [
                'ok'      => false,
                'code'    => 422,
                'error'   => 'validation_error',
                'message' => 'Debe especificarse el identificador o nombre de la oferta académica.',
            ];
        }

        // 4. Enriquecimiento / resolución de oferta académica vía FLACSO_Inquiry_Context_Service
        $context = class_exists('FLACSO_Inquiry_Context_Service')
            ? FLACSO_Inquiry_Context_Service::resolve((int)($offer_id ?? 0), $data)
            : [];

        $offer_id = $context['offerWpId'] ?? $offer_id;
        $offer_name = !empty($context['offerName']) ? (string)$context['offerName'] : $offer_name;
        $offer_type = $context['offerType'] ?? $offer_type;
        $offer_abbr = $context['offerAbbreviation'] ?? null;
        $cohort_wp_id = $context['cohortWpId'] ?? null;
        $cohort_number = $context['cohortNumber'] ?? null;
        $cohort_name = $context['cohortName'] ?? null;
        $reg_open_at = $context['registrationOpenAt'] ?? null;
        $reg_close_at = $context['registrationCloseAt'] ?? null;

        $offer_status = !empty($data['offerStatus'])
            ? (string)$data['offerStatus']
            : (!empty($data['offer_status'])
                ? (string)$data['offer_status']
                : ($context['offerStatus'] ?? 'sin_cohorte'));

        $is_open = ($offer_status === 'abierta');

        $program_url = !empty($data['programUrl'])
            ? (string)$data['programUrl']
            : (!empty($data['urlBase'])
                ? (string)$data['urlBase']
                : (!empty($data['url_base'])
                    ? (string)$data['url_base']
                    : (!empty($data['url_programa'])
                        ? (string)$data['url_programa']
                        : null)));

        if ($program_url === null && $offer_id !== null && $offer_id > 0 && function_exists('get_permalink')) {
            $permalink = get_permalink($offer_id);
            if (!empty($permalink)) {
                $program_url = (string)$permalink;
            }
        }

        $carta_url = !empty($data['cartaUrl'])
            ? (string)$data['cartaUrl']
            : (!empty($data['url_carta'])
                ? (string)$data['url_carta']
                : null);

        // La carta de una oferta siempre vive en <url-oferta>/carta.
        // No dependemos de que el formulario la envíe explícitamente.
        if (($carta_url === null || trim($carta_url) === '') && !empty($program_url)) {
            $carta_url = rtrim((string)$program_url, '/') . '/carta';
        }

        $preinscripcion_url = !empty($data['preinscripcionUrl'])
            ? (string)$data['preinscripcionUrl']
            : (!empty($data['url_preinscripcion'])
                ? (string)$data['url_preinscripcion']
                : ($context['preinscripcionUrl'] ?? null));

        $start_value = !empty($data['startDate'])
            ? (string)$data['startDate']
            : (!empty($data['startValue'])
                ? (string)$data['startValue']
                : (!empty($data['fecha_inicio'])
                    ? (string)$data['fecha_inicio']
                    : ($context['startDate'] ?? '')));

        $modality = !empty($data['modality'])
            ? (string)$data['modality']
            : (!empty($data['modalityLabel'])
                ? (string)$data['modalityLabel']
                : (!empty($data['modalidad'])
                    ? (string)$data['modalidad']
                    : ($context['modality'] ?? '')));

        $start_precision = !empty($data['startDatePrecision'])
            ? (string)$data['startDatePrecision']
            : (!empty($data['startPrecision'])
                ? (string)$data['startPrecision']
                : (!empty($data['precision_fecha_inicio'])
                    ? (string)$data['precision_fecha_inicio']
                    : ($context['startDatePrecision'] ?? 'day')));

        $country = isset($data['country']) ? (string)$data['country'] : (isset($data['pais']) ? (string)$data['pais'] : null);
        $profession = isset($data['profession']) ? (string)$data['profession'] : (isset($data['profesion']) ? (string)$data['profesion'] : null);
        $education_level = isset($data['educationLevel']) ? (string)$data['educationLevel'] : (isset($data['nivel_academico']) ? (string)$data['nivel_academico'] : null);
        $reply_to = isset($data['replyToEmail']) ? (string)$data['replyToEmail'] : (isset($data['reply_to']) ? (string)$data['reply_to'] : null);
        if (($reply_to === null || trim($reply_to) === '') && !empty($context['replyToEmail'])) {
            $reply_to = (string)$context['replyToEmail'];
        }
        $source = isset($data['source']) ? (string)$data['source'] : (isset($data['origen']) ? (string)$data['origen'] : 'Web');

        $ip = isset($data['ipAddress'])
            ? (string)$data['ipAddress']
            : (isset($data['ip_usuario'])
                ? (string)$data['ip_usuario']
                : (isset($data['meta']['ip'])
                    ? (string)$data['meta']['ip']
                    : (isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : null)));

        $user_agent = isset($data['userAgent'])
            ? (string)$data['userAgent']
            : (isset($data['user_agent'])
                ? (string)$data['user_agent']
                : (isset($data['meta']['user_agent'])
                    ? (string)$data['meta']['user_agent']
                    : (isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : null)));

        $url_referer = isset($data['urlReferer'])
            ? (string)$data['urlReferer']
            : (isset($data['url_referer'])
                ? (string)$data['url_referer']
                : (isset($data['referrer_url'])
                    ? (string)$data['referrer_url']
                    : null));

        $inquiry_at = isset($data['inquiryAt'])
            ? (string)$data['inquiryAt']
            : (isset($data['fecha_envio'])
                ? (string)$data['fecha_envio']
                : gmdate('c'));

        // El seguimiento comercial nuevo pertenece a Mautic y sólo se habilita
        // con consentimiento explícito. No crear followups locales nuevos.
        $followup_due_at = null;
        $followup_status = 'none';

        // 5. Inserción en Base de Datos (Guardar primero)
        $record = [
            'consultaId'          => $consulta_id,
            'offerWpId'           => $offer_id,
            'offerName'           => $offer_name,
            'offerAbbreviation'   => $offer_abbr,
            'offerType'           => $offer_type,
            'cohortWpId'          => $cohort_wp_id,
            'cohortNumber'        => $cohort_number,
            'cohortName'          => $cohort_name,
            'registrationOpenAt'  => $reg_open_at,
            'registrationCloseAt' => $reg_close_at,
            'firstName'           => $first_name,
            'lastName'            => $last_name,
            'fullName'            => $full_name,
            'email'               => $email,
            'country'             => $country,
            'profession'          => $profession,
            'educationLevel'      => $education_level,
            'source'              => $source,
            'campaignProvider'    => $data['campaignProvider'] ?? $data['campaign_provider'] ?? $data['utm_provider'] ?? null,
            'campaignSource'      => $data['campaignSource'] ?? $data['campaign_source'] ?? $data['utm_source'] ?? null,
            'campaignMedium'      => $data['campaignMedium'] ?? $data['campaign_medium'] ?? $data['utm_medium'] ?? null,
            'campaignName'        => $data['campaignName'] ?? $data['campaign_name'] ?? $data['utm_campaign'] ?? null,
            'campaignExternalId'  => $data['campaignExternalId'] ?? $data['campaign_external_id'] ?? $data['utm_id'] ?? null,
            'campaignContent'     => $data['campaignContent'] ?? $data['campaign_content'] ?? $data['utm_content'] ?? null,
            'campaignTerm'        => $data['campaignTerm'] ?? $data['campaign_term'] ?? $data['utm_term'] ?? null,
            'urlBase'             => $program_url,
            'urlReferer'          => $url_referer,
            'inquiryAt'           => $inquiry_at,
            'ipAddress'           => $ip,
            'userAgent'           => $user_agent,
            'replyToEmail'        => $reply_to,
            'programUrl'          => $program_url,
            'cartaUrl'            => $carta_url,
            'preinscripcionUrl'   => $preinscripcion_url,
            'offerStatus'         => $offer_status,
            'followupDueAt'       => $followup_due_at,
            'followupStatus'      => $followup_status,
            'emailStatus'         => 'pending',
            'emailSender'         => 'mautic_transactional_queue',
            'payload'             => $data,
        ];

        $snapshot_form = array_merge($data, [
            'firstName' => $first_name,
            'lastName' => $last_name,
            'fullName' => $full_name,
            'email' => $email,
            'country' => $country,
            'profession' => $profession,
            'educationLevel' => $education_level,
            'programUrl' => $program_url,
            'cartaUrl' => $carta_url,
            'preinscripcionUrl' => $preinscripcion_url,
            'replyToEmail' => $reply_to,
            'inquiryAt' => $inquiry_at,
            'offerStatus' => $offer_status,
            'startDate' => $start_value,
            'startDatePrecision' => $start_precision,
            'modality' => $modality,
        ]);
        $snapshot_context = array_merge($context, [
            'offerWpId' => $offer_id,
            'offerName' => $offer_name,
            'offerType' => $offer_type,
            'offerAbbreviation' => $offer_abbr,
            'cohortWpId' => $cohort_wp_id,
            'cohortNumber' => $cohort_number,
            'cohortName' => $cohort_name,
            'registrationOpenAt' => $reg_open_at,
            'registrationCloseAt' => $reg_close_at,
            'offerStatus' => $offer_status,
            'preinscripcionUrl' => $preinscripcion_url,
            'replyToEmail' => $reply_to,
            'startDate' => $start_value,
            'startDatePrecision' => $start_precision,
            'modality' => $modality,
        ]);

        try {
            $snapshot = FLACSO_Inquiry_Snapshot::from_offer($snapshot_form, $snapshot_context, $consulta_id);
            $delivery_repo = new FLACSO_Inquiry_Delivery_Repository();
            $insert_result = $delivery_repo->persist_inquiry_with_delivery(
                $repo,
                $record,
                $snapshot,
                'offer',
                '',
                $consent
            );
        } catch (\Throwable $e) {
            error_log('[FLACSO] Error al persistir consulta/snapshot/entrega de oferta: ' . $e->getMessage());
            return [
                'ok' => false,
                'code' => 500,
                'error' => 'db_error',
                'message' => $e->getMessage(),
            ];
        }

        if (!empty($insert_result['duplicate'])) {
            return [
                'ok'                 => true,
                'consulta_id'        => $consulta_id,
                'duplicate'          => true,
                'email'              => $insert_result['state'] ?? 'pending',
                'offer_status'       => $offer_status,
                'cohort_number'      => $cohort_number,
                'offer_abbreviation' => $offer_abbr,
                'delivery_id'        => $insert_result['delivery_id'] ?? null,
                'code'               => 200,
            ];
        }

        $record_id = (string) ($insert_result['id'] ?? '');
        $marketing = ['ok' => true, 'status' => 'skipped', 'reason' => 'consent_required'];
        if (FLACSO_Offer_Campaigns::is_enabled() && FLACSO_Offer_Campaigns::valid_consent($consent)) {
            // El plugin de Mautic hace el contacto y el alta en la campaña de la oferta al procesar la entrega.
            $marketing = ['ok' => true, 'status' => 'skipped', 'reason' => 'offer_campaign'];
        } elseif (class_exists('FLACSO_Inquiry_Marketing_Service')) {
            try {
                $marketing = FLACSO_Inquiry_Marketing_Service::sync_commercial_contact(
                    $snapshot,
                    $consent,
                    $repo,
                    $record_id
                );
            } catch (\Throwable $e) {
                error_log('[FLACSO] Error en sincronización comercial separada: ' . $e->getMessage());
                $marketing = ['ok' => false, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return [
            'ok'                   => true,
            'consulta_id'          => $consulta_id,
            'duplicate'            => false,
            'email'                => 'pending',
            'email_sender'         => 'mautic_transactional_queue',
            'email_engine'         => 'mautic_transactional_queue',
            'delivery_id'          => $insert_result['delivery_id'] ?? null,
            'snapshot_id'          => $insert_result['snapshot_id'] ?? null,
            'mailjet_message_id'   => null,
            'mailjet_message_uuid' => null,
            'offer_status'         => $offer_status,
            'cohort_number'        => $cohort_number,
            'offer_abbreviation'   => $offer_abbr,
            'marketing_sync'       => $marketing,
            'followup_status'      => 'none',
            'followup_due_at'      => null,
            'code'                 => 200,
        ];
    }

    /**
     * Genera un identificador UUIDv4 RFC 4122.
     */
    private static function generate_uuidv4(): string {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
