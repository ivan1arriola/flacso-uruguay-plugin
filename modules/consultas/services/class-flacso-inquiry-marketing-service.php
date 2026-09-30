<?php
/**
 * Sincronización comercial separada del acuse transaccional.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

if (!class_exists('FLACSO_Offer_Inquiry_Repository')) {
    require_once dirname(__DIR__, 3) . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
}
if (!class_exists('FLACSO_Mautic_Client')) {
    require_once dirname(__DIR__, 3) . '/includes/integrations/class-flacso-mautic-client.php';
}
if (!class_exists('FLACSO_Inquiry_Tag_Factory')) {
    require_once __DIR__ . '/class-flacso-inquiry-tag-factory.php';
}
if (!class_exists('FLACSO_Inquiry_Snapshot')) {
    require_once __DIR__ . '/class-flacso-inquiry-snapshot.php';
}
if (!class_exists('FLACSO_Mautic_Payload_Builder')) {
    require_once __DIR__ . '/class-flacso-mautic-payload-builder.php';
}

final class FLACSO_Inquiry_Marketing_Service {
    private static ?FLACSO_Offer_Inquiry_Repository $repository = null;

    public static function set_repository(?FLACSO_Offer_Inquiry_Repository $repository): void {
        self::$repository = $repository;
    }

    private static function get_repository(): ?FLACSO_Offer_Inquiry_Repository {
        if (self::$repository !== null) {
            return self::$repository;
        }
        return class_exists('FLACSO_Offer_Inquiry_Repository')
            ? new FLACSO_Offer_Inquiry_Repository()
            : null;
    }

    /**
     * Compatibilidad para callers antiguos. La única definición de tags vive
     * en FLACSO_Inquiry_Tag_Factory.
     */
    public static function generate_tags(?string $abbreviation, ?int $cohort_number, string $offer_status): array {
        $code = FLACSO_Inquiry_Tag_Factory::slug((string) ($abbreviation ?? ''));
        if ($code === '') {
            return [];
        }

        $status = strtolower(trim($offer_status));
        $cohort = ($cohort_number !== null && $cohort_number > 0 && $status !== 'sin_cohorte')
            ? $cohort_number
            : null;

        return FLACSO_Inquiry_Tag_Factory::from_snapshot([
            'academic' => [
                'code' => $code,
                'cohortNumber' => $cohort,
            ],
        ]);
    }

    /**
     * Nuevo punto de entrada comercial. Sin consentimiento válido no realiza
     * ninguna llamada a Mautic.
     *
     * El consentimiento se conserva como evidencia en WordPress (payload de la
     * consulta); Mautic recibe perfil estable, tags acumulativos y campaña.
     */
    public static function sync_commercial_contact(
        array $snapshot,
        array $consent,
        ?FLACSO_Offer_Inquiry_Repository $repository = null,
        string $inquiry_id = ''
    ): array {
        FLACSO_Inquiry_Snapshot::assert_snapshot($snapshot);

        if (!self::valid_consent($consent)) {
            self::update_repository($repository, $inquiry_id, [
                'mauticSyncStatus' => 'skipped',
                'mauticLastError' => null,
            ]);
            return [
                'ok' => true,
                'status' => 'skipped',
                'reason' => 'consent_required',
                'campaign' => ['ok' => true, 'status' => 'skipped'],
            ];
        }

        $sync = self::sync_snapshot_contact($snapshot, $repository, $inquiry_id);
        if (empty($sync['ok'])) {
            return $sync;
        }

        $campaign = self::sync_campaign(
            $inquiry_id,
            (int) ($sync['contact_id'] ?? 0),
            $repository
        );
        $sync['campaign'] = $campaign;
        $sync['consent'] = [
            'acceptedAt' => (string) $consent['acceptedAt'],
            'source' => (string) $consent['source'],
            'textVersion' => (string) $consent['textVersion'],
        ];
        return $sync;
    }

    /**
     * Compatibilidad para administración/seguimientos anteriores.
     *
     * Sin evidencia de consentimiento esta vía sincroniza únicamente perfil y
     * tags; nunca incorpora automáticamente a una campaña.
     */
    public static function sync_inquiry(
        string|int $inquiry_id,
        array $inquiry_data = [],
        ?FLACSO_Offer_Inquiry_Repository $repository = null
    ): array {
        $repository = $repository ?? self::get_repository();
        $id = trim((string) $inquiry_id);

        if (empty($inquiry_data) && $repository !== null && $id !== '') {
            $found = $repository->find_by_id($id);
            if (is_array($found)) {
                $inquiry_data = $found;
            }
        }
        if (empty($inquiry_data)) {
            return ['ok' => false, 'status' => 'failed', 'error' => 'Inquiry not found'];
        }

        $email = trim((string) ($inquiry_data['email'] ?? $inquiry_data['user_email'] ?? ''));
        if ($email === '') {
            return ['ok' => false, 'status' => 'failed', 'error' => 'Email is required'];
        }

        $context = [
            'offerWpId' => $inquiry_data['offerWpId'] ?? null,
            'offerName' => $inquiry_data['offerName'] ?? $inquiry_data['offer_name'] ?? '',
            'offerAbbreviation' => $inquiry_data['offerAbbreviation'] ?? $inquiry_data['offer_abbreviation'] ?? '',
            'offerType' => $inquiry_data['offerType'] ?? $inquiry_data['offer_type'] ?? 'oferta',
            'cohortWpId' => $inquiry_data['cohortWpId'] ?? null,
            'cohortNumber' => $inquiry_data['cohortNumber'] ?? $inquiry_data['cohort_number'] ?? null,
            'cohortName' => $inquiry_data['cohortName'] ?? $inquiry_data['cohort_name'] ?? '',
            'offerStatus' => $inquiry_data['offerStatus'] ?? $inquiry_data['offer_status'] ?? 'sin_cohorte',
            'startDate' => $inquiry_data['startDate'] ?? $inquiry_data['fecha_inicio'] ?? '',
            'startDatePrecision' => $inquiry_data['startDatePrecision'] ?? $inquiry_data['precision_fecha_inicio'] ?? 'day',
            'modality' => $inquiry_data['modality'] ?? $inquiry_data['modalidad'] ?? '',
            'preinscripcionUrl' => $inquiry_data['preinscripcionUrl'] ?? '',
            'replyToEmail' => $inquiry_data['replyToEmail'] ?? null,
        ];

        $form = array_merge($inquiry_data, [
            'email' => $email,
            'programUrl' => $inquiry_data['programUrl'] ?? $inquiry_data['urlBase'] ?? '',
        ]);
        $consulta_id = (string) ($inquiry_data['consultaId'] ?? $id);
        $snapshot = FLACSO_Inquiry_Snapshot::from_offer($form, $context, $consulta_id);

        $result = self::sync_snapshot_contact($snapshot, $repository, $id);
        if (!empty($result['ok'])) {
            $result['campaign'] = [
                'ok' => true,
                'status' => 'skipped',
                'reason' => 'consent_required',
            ];
        }
        return $result;
    }

    /**
     * Compatibilidad de tokens para consumidores antiguos. El resultado se
     * deriva del mismo InquirySnapshot que usa la cola nueva.
     */
    public static function compile_tokens(array $inquiry, array $program = [], bool $is_open = false): array {
        if (($inquiry['schemaVersion'] ?? '') === FLACSO_Inquiry_Snapshot::SCHEMA_VERSION) {
            return FLACSO_Inquiry_Snapshot::delivery_tokens($inquiry);
        }

        $context = [
            'offerWpId' => $program['id'] ?? $inquiry['offerWpId'] ?? null,
            'offerName' => $program['name'] ?? $program['titulo_posgrado'] ?? $program['title'] ?? $inquiry['offerName'] ?? '',
            'offerAbbreviation' => $program['offerAbbreviation'] ?? $inquiry['offerAbbreviation'] ?? '',
            'offerType' => $program['offerType'] ?? $inquiry['offerType'] ?? 'oferta',
            'cohortNumber' => $inquiry['cohortNumber'] ?? $inquiry['cohort_number'] ?? $program['cohortNumber'] ?? null,
            'cohortName' => $inquiry['cohortName'] ?? $inquiry['cohort_name'] ?? $program['cohortName'] ?? '',
            'offerStatus' => $is_open ? 'abierta' : ($inquiry['offerStatus'] ?? 'sin_cohorte'),
            'startDate' => $program['startDate'] ?? $program['startValue'] ?? $program['fecha_inicio'] ?? $inquiry['startDate'] ?? $inquiry['startValue'] ?? '',
            'startDatePrecision' => $program['startDatePrecision'] ?? $program['startPrecision'] ?? $program['precision_fecha_inicio'] ?? $inquiry['startDatePrecision'] ?? $inquiry['startPrecision'] ?? 'day',
            'modality' => $program['modality'] ?? $program['modalityLabel'] ?? $program['modalidad'] ?? $inquiry['modality'] ?? $inquiry['modalityLabel'] ?? $inquiry['modalidad'] ?? '',
            'preinscripcionUrl' => $program['preinscripcionUrl'] ?? $program['preinscripcion_url'] ?? $program['link_preinscripcion'] ?? $inquiry['preinscripcionUrl'] ?? '',
        ];

        $form = array_merge($inquiry, [
            'programUrl' => $program['programUrl'] ?? $program['urlBase'] ?? $program['url'] ?? $inquiry['programUrl'] ?? $inquiry['urlBase'] ?? '',
            'cartaUrl' => $program['cartaUrl'] ?? $program['carta_url'] ?? $program['url_carta'] ?? $inquiry['cartaUrl'] ?? '',
        ]);

        return FLACSO_Inquiry_Snapshot::delivery_tokens(
            FLACSO_Inquiry_Snapshot::from_offer(
                $form,
                $context,
                (string) ($inquiry['consultaId'] ?? $inquiry['id'] ?? 'legacy-preview')
            )
        );
    }

    private static function sync_snapshot_contact(
        array $snapshot,
        ?FLACSO_Offer_Inquiry_Repository $repository,
        string $inquiry_id
    ): array {
        if (!FLACSO_Mautic_Client::is_configured()) {
            self::update_repository($repository, $inquiry_id, ['mauticSyncStatus' => 'skipped']);
            return [
                'ok' => true,
                'status' => 'skipped',
                'message' => 'Mautic sync disabled or unconfigured',
            ];
        }

        $built = FLACSO_Mautic_Payload_Builder::build($snapshot);
        $email = (string) ($snapshot['recipient']['email'] ?? '');
        try {
            $result = FLACSO_Mautic_Client::create_or_update_contact(
                $email,
                $built['fields'],
                $built['tags']
            );
        } catch (Throwable $e) {
            $result = ['ok' => false, 'contact_id' => null, 'error' => $e->getMessage()];
        }

        if (!empty($result['ok'])) {
            $contact_id = (int) ($result['contact_id'] ?? 0);
            self::update_repository($repository, $inquiry_id, [
                'mauticContactId' => $contact_id > 0 ? $contact_id : null,
                'mauticSyncStatus' => 'synced',
                'mauticSyncedAt' => gmdate('c'),
                'mauticLastError' => null,
            ]);
            return [
                'ok' => true,
                'status' => 'synced',
                'contact_id' => $contact_id,
                'tags' => $built['tags'],
            ];
        }

        $error = (string) ($result['error'] ?? 'Mautic API error');
        self::update_repository($repository, $inquiry_id, [
            'mauticSyncStatus' => 'failed',
            'mauticLastError' => $error,
        ]);
        return ['ok' => false, 'status' => 'failed', 'error' => $error];
    }

    private static function sync_campaign(
        string $inquiry_id,
        int $contact_id,
        ?FLACSO_Offer_Inquiry_Repository $repository
    ): array {
        $enabled = function_exists('get_option')
            && (string) get_option('flacso_mautic_campaign_enabled', '0') === '1';
        $campaign_id = function_exists('get_option')
            ? (int) get_option('flacso_mautic_campaign_consultas_id', 0)
            : 0;

        if (!$enabled || $campaign_id <= 0 || $contact_id <= 0) {
            return ['ok' => true, 'status' => 'skipped'];
        }

        try {
            $result = FLACSO_Mautic_Client::add_contact_to_campaign($campaign_id, $contact_id);
        } catch (Throwable $e) {
            $result = ['ok' => false, 'error' => $e->getMessage(), 'http_code' => 0];
        }

        $status = !empty($result['ok']) ? 'joined' : 'failed';
        if ($repository !== null && $inquiry_id !== '' && method_exists($repository, 'update_mautic_campaign_status')) {
            $repository->update_mautic_campaign_status($inquiry_id, [
                'mauticCampaignId' => $campaign_id,
                'mauticCampaignStatus' => $status,
                'mauticCampaignLastError' => $result['error'] ?? null,
            ]);
        }

        return [
            'ok' => !empty($result['ok']),
            'status' => $status,
            'error' => $result['error'] ?? null,
        ];
    }

    private static function valid_consent(array $consent): bool {
        $granted = $consent['granted'] ?? false;
        $granted = $granted === true || $granted === 1 || $granted === '1';
        $accepted_at = trim((string) ($consent['acceptedAt'] ?? ''));
        $source = trim((string) ($consent['source'] ?? ''));
        $version = trim((string) ($consent['textVersion'] ?? ''));

        return $granted
            && $accepted_at !== ''
            && strtotime($accepted_at) !== false
            && $source !== ''
            && $version !== '';
    }

    private static function update_repository(
        ?FLACSO_Offer_Inquiry_Repository $repository,
        string $inquiry_id,
        array $data
    ): void {
        if ($repository === null || $inquiry_id === '' || !method_exists($repository, 'update_mautic_status')) {
            return;
        }
        try {
            $repository->update_mautic_status($inquiry_id, $data);
        } catch (Throwable $e) {
            // La sincronización comercial nunca invalida la consulta guardada.
        }
    }
}
