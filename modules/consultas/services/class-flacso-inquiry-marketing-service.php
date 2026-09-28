<?php
/**
 * Servicio de Marketing de Consultas - FLACSO Uruguay
 *
 * Responsable de la generación de etiquetas canónicas y de la sincronización
 * determinística de prospectos hacia Mautic (Marketing Automation),
 * garantizando cero campos volátiles y persistencia de auditoría en base de datos.
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

class FLACSO_Inquiry_Marketing_Service {

    /**
     * Instancia de repositorio inyectada (opcional para pruebas).
     *
     * @var FLACSO_Offer_Inquiry_Repository|null
     */
    private static ?FLACSO_Offer_Inquiry_Repository $repository = null;

    /**
     * Permite fijar un repositorio para pruebas o inyección de dependencias.
     *
     * @param FLACSO_Offer_Inquiry_Repository|null $repo
     * @return void
     */
    public static function set_repository(?FLACSO_Offer_Inquiry_Repository $repo): void {
        self::$repository = $repo;
    }

    /**
     * Obtiene una instancia del repositorio de consultas de oferta.
     *
     * @return FLACSO_Offer_Inquiry_Repository|null
     */
    protected static function get_repository(): ?FLACSO_Offer_Inquiry_Repository {
        if (self::$repository !== null) {
            return self::$repository;
        }
        if (class_exists('FLACSO_Offer_Inquiry_Repository')) {
            return new FLACSO_Offer_Inquiry_Repository();
        }
        return null;
    }

    /**
     * Genera el conjunto canónico de etiquetas para Mautic según el estado de la oferta y cohorte.
     *
     * Reglas:
     * - Si la abreviación está vacía tras normalizar: emite error_log y retorna [].
     * - Tag base: "interes-{$abbreviation}".
     * - Si $cohort_number > 0 y $offer_status !== 'sin_cohorte':
     *     - Tag de cohorte: "interes-{$abbreviation}-c{$cohort_number}".
     *     - Si $offer_status === 'abierta': "consulta-abierta-{$abbreviation}-c{$cohort_number}".
     *     - Si $offer_status === 'cerrada': "consulta-cerrada-{$abbreviation}-c{$cohort_number}".
     * - Si $offer_status === 'sin_cohorte': retorna únicamente ["interes-{$abbreviation}"].
     *
     * @param string|null $abbreviation Abreviación del programa/oferta (ej: 'davia', 'mg-ed').
     * @param int|null    $cohort_number Número de la cohorte académica.
     * @param string      $offer_status Estado de la oferta ('abierta', 'cerrada', 'sin_cohorte').
     * @return array Lista reindexada de tags únicos.
     */
    public static function generate_tags(?string $abbreviation, ?int $cohort_number, string $offer_status): array {
        $raw = (string)($abbreviation ?? '');

        // Normalización de abreviación
        if (class_exists('FLACSO_Oferta_Academica') && method_exists('FLACSO_Oferta_Academica', 'normalize_abbreviation')) {
            $clean = FLACSO_Oferta_Academica::normalize_abbreviation($raw);
        } elseif (function_exists('sanitize_title')) {
            $clean = sanitize_title(strtolower(trim($raw)));
        } else {
            $str = strtr($raw, [
                'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u',
                'Á'=>'a', 'É'=>'e', 'Í'=>'i', 'Ó'=>'o', 'Ú'=>'u',
                'ñ'=>'n', 'Ñ'=>'n', 'ü'=>'u', 'Ü'=>'u',
            ]);
            $clean = strtolower(trim(preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $str), '-'));
        }

        $clean = trim($clean, '-');

        if ($clean === '') {
            error_log('[FLACSO Inquiry Marketing] Advertencia: la abreviación del programa está vacía o es inválida; no se generaron tags de oferta.');
            return [];
        }

        $tags = ["interes-{$clean}"];
        $status_norm = strtolower(trim($offer_status));

        if ($cohort_number !== null && $cohort_number > 0 && $status_norm !== 'sin_cohorte') {
            $tags[] = "interes-{$clean}-c{$cohort_number}";
            if ($status_norm === 'abierta') {
                $tags[] = "consulta-abierta-{$clean}-c{$cohort_number}";
            } elseif ($status_norm === 'cerrada') {
                $tags[] = "consulta-cerrada-{$clean}-c{$cohort_number}";
            }
        }

        return array_values(array_unique($tags));
    }

    /**
     * Sincroniza un prospecto con Mautic aplicando tags canónicos y campos de perfil estables.
     *
     * @param string|int                           $inquiry_id   ID único de la consulta en offer_inquiries.
     * @param array                                $inquiry_data Datos de la consulta (si está vacío, se busca en repo).
     * @param FLACSO_Offer_Inquiry_Repository|null $repo         Repositorio inyectado opcional.
     * @return array{
     *     ok: bool,
     *     status: 'synced'|'skipped'|'failed',
     *     contact_id?: int|null,
     *     tags?: array,
     *     error?: string,
     *     message?: string
     * }
     */
    public static function sync_inquiry(string|int $inquiry_id, array $inquiry_data = [], ?FLACSO_Offer_Inquiry_Repository $repo = null): array {
        $repository = $repo ?? self::get_repository();
        $inquiry_id_str = trim((string)$inquiry_id);

        // 1. Si Mautic no está configurado o está deshabilitado
        $is_configured = class_exists('FLACSO_Mautic_Client') && FLACSO_Mautic_Client::is_configured();
        if (!$is_configured) {
            if ($repository !== null && $inquiry_id_str !== '') {
                $repository->update_mautic_status($inquiry_id_str, [
                    'mauticSyncStatus' => 'skipped',
                ]);
            }
            return [
                'ok'      => true,
                'status'  => 'skipped',
                'message' => 'Mautic sync disabled or unconfigured',
            ];
        }

        // 2. Si no se suministraron datos, intentar recuperarlos del repositorio por ID
        if (empty($inquiry_data)) {
            if ($repository !== null && $inquiry_id_str !== '') {
                $found = $repository->find_by_id($inquiry_id_str);
                if (is_array($found)) {
                    $inquiry_data = $found;
                }
            }

            if (empty($inquiry_data)) {
                return [
                    'ok'     => false,
                    'status' => 'failed',
                    'error'  => 'Inquiry not found',
                ];
            }
        }

        // 3. Extraer y validar campos de perfil estables (soporta camelCase y snake_case)
        $email_raw = $inquiry_data['email'] ?? $inquiry_data['user_email'] ?? '';
        $email = strtolower(trim((string)$email_raw));
        if ($email === '') {
            return [
                'ok'     => false,
                'status' => 'failed',
                'error'  => 'Email is required',
            ];
        }

        $firstname = trim((string)($inquiry_data['firstname'] ?? $inquiry_data['firstName'] ?? $inquiry_data['first_name'] ?? ''));
        $lastname  = trim((string)($inquiry_data['lastname'] ?? $inquiry_data['lastName'] ?? $inquiry_data['last_name'] ?? ''));
        $country   = trim((string)($inquiry_data['country'] ?? $inquiry_data['pais'] ?? ''));
        $phone     = trim((string)($inquiry_data['phone'] ?? $inquiry_data['telefono'] ?? ''));
        $profession = trim((string)($inquiry_data['profession'] ?? $inquiry_data['profesion'] ?? ''));
        $education_level = trim((string)($inquiry_data['education_level'] ?? $inquiry_data['educationLevel'] ?? $inquiry_data['nivel_educativo'] ?? ''));

        // Cero campos volátiles: NO incluir ultima_oferta ni estado_actual
        $contact_fields = [
            'firstname'       => $firstname,
            'lastname'        => $lastname,
            'email'           => $email,
            'country'         => $country,
            'phone'           => $phone,
            'profession'      => $profession,
            'education_level' => $education_level,
        ];

        // 4. Extraer datos para generación de tags canónicos
        $abbreviation_raw = $inquiry_data['offerAbbreviation'] ?? $inquiry_data['offer_abbreviation'] ?? $inquiry_data['abbreviation'] ?? $inquiry_data['abreviacion'] ?? null;
        $abbreviation = $abbreviation_raw !== null ? (string)$abbreviation_raw : null;

        $cohort_number_raw = $inquiry_data['cohortNumber'] ?? $inquiry_data['cohort_number'] ?? $inquiry_data['cohort'] ?? null;
        $cohort_number = ($cohort_number_raw !== null && $cohort_number_raw !== '') ? (int)$cohort_number_raw : null;

        $offer_status_raw = $inquiry_data['offerStatus'] ?? $inquiry_data['offer_status'] ?? 'sin_cohorte';
        $offer_status = !empty($offer_status_raw) ? (string)$offer_status_raw : 'sin_cohorte';

        $tags = self::generate_tags($abbreviation, $cohort_number, $offer_status);

        // 5. Invocar cliente Mautic
        try {
            $res = FLACSO_Mautic_Client::create_or_update_contact($email, $contact_fields, $tags);
        } catch (\Throwable $e) {
            $res = [
                'ok'         => false,
                'contact_id' => null,
                'error'      => $e->getMessage(),
            ];
        }

        // 6. Procesar respuesta y actualizar repositorio
        if (!empty($res['ok'])) {
            $contact_id = isset($res['contact_id']) ? (int)$res['contact_id'] : null;

            if ($repository !== null && $inquiry_id_str !== '') {
                $repository->update_mautic_status($inquiry_id_str, [
                    'mauticContactId'  => $contact_id,
                    'mauticSyncStatus' => 'synced',
                    'mauticSyncedAt'   => gmdate('c'),
                    'mauticLastError'  => null,
                ]);
            }

            return [
                'ok'         => true,
                'status'     => 'synced',
                'contact_id' => $contact_id,
                'tags'       => $tags,
            ];
        }

        $error_msg = !empty($res['error']) ? (string)$res['error'] : 'Mautic API error';

        if ($repository !== null && $inquiry_id_str !== '') {
            $repository->update_mautic_status($inquiry_id_str, [
                'mauticSyncStatus' => 'failed',
                'mauticLastError'  => $error_msg,
            ]);
        }

        return [
            'ok'     => false,
            'status' => 'failed',
            'error'  => $error_msg,
        ];
    }
}
