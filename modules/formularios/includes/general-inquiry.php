<?php
/** Consulta general: registro privado en WordPress y notificación por proveedor asignado. */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Copia la consulta en PostgreSQL para que aparezca en la bandeja y la analítica.
 * Es de mejor esfuerzo: WordPress sigue siendo el registro principal y un fallo
 * aquí nunca debe impedir la notificación.
 */
function fc_mirror_general_inquiry(array $payload): string {
    try {
        if (!class_exists('FLACSO_DB')) {
            require_once dirname(__DIR__, 3) . '/includes/database/class-flacso-db.php';
        }
        if (!FLACSO_DB::is_configured()) {
            return 'not_configured';
        }
        if (!class_exists('FLACSO_General_Inquiry_Repository')) {
            require_once dirname(__DIR__, 3) . '/includes/database/repositories/class-flacso-general-inquiry-repository.php';
        }
        (new FLACSO_General_Inquiry_Repository())->insert([
            'consultaId'         => (string) ($payload['event_id'] ?? ''),
            'firstName'          => (string) ($payload['nombre'] ?? ''),
            'lastName'           => (string) ($payload['apellido'] ?? ''),
            'email'              => (string) ($payload['email'] ?? ''),
            'phone'              => (string) ($payload['telefono'] ?? ''),
            'subject'            => (string) ($payload['asunto'] ?? ''),
            'message'            => (string) ($payload['mensaje'] ?? ''),
            'source'             => 'Contacto',
            'campaignProvider'   => $payload['campaign_provider'] ?? null,
            'campaignSource'     => $payload['campaign_source'] ?? null,
            'campaignMedium'     => $payload['campaign_medium'] ?? null,
            'campaignName'       => $payload['campaign_name'] ?? null,
            'campaignExternalId' => $payload['campaign_external_id'] ?? null,
            'urlReferer'         => $payload['url_referer'] ?? null,
            'ipAddress'          => $payload['ip'] ?? null,
            'userAgent'          => $payload['user_agent'] ?? null,
        ]);
        return 'stored';
    } catch (Throwable $error) {
        error_log('[FLACSO] No se pudo copiar la consulta general a PostgreSQL: ' . $error->getMessage());
        return 'failed';
    }
}

function fc_send_general_inquiry(array $payload): array {
    $post_id = wp_insert_post([
        'post_type' => 'fc_consulta',
        'post_status' => 'private',
        'post_title' => (string) ($payload['asunto'] ?? 'Consulta general'),
        'post_content' => (string) ($payload['mensaje'] ?? ''),
        'meta_input' => [
            'fc_nombre' => (string) ($payload['nombre'] ?? ''),
            'fc_apellido' => (string) ($payload['apellido'] ?? ''),
            'fc_email' => (string) ($payload['email'] ?? ''),
            'fc_telefono' => (string) ($payload['telefono'] ?? ''),
            '_fc_event_id' => (string) ($payload['event_id'] ?? ''),
            '_fc_reference' => (string) ($payload['reference'] ?? ''),
            '_fc_delivery_lock' => (string) ($payload['delivery_lock'] ?? ''),
            '_fc_delivery_status' => 'pending',
        ],
    ], true);
    if (is_wp_error($post_id) || !$post_id) {
        return ['ok' => false, 'error' => 'No fue posible guardar la consulta.', 'acceptance_unknown' => false];
    }

    update_post_meta($post_id, '_fc_database_status', fc_mirror_general_inquiry($payload));

    if (!class_exists('FLACSO_Mailing_Client')) {
        require_once dirname(__DIR__, 3) . '/includes/integrations/class-flacso-mailing-client.php';
    }
    $provider = FLACSO_Mailing_Client::delivery_provider();
    if ($provider === 'flacso_mail') {
        try {
            $result = FLACSO_Mailing_Client::send_general_inquiry((int) $post_id, $payload);
        } catch (Throwable $error) {
            $result = ['ok' => false, 'status' => 'acceptance_unknown', 'acceptance_unknown' => true, 'error' => 'No fue posible confirmar la recepción en Mailing.'];
        }
        update_post_meta($post_id, '_fc_delivery_provider', 'flacso_mail');
        update_post_meta($post_id, '_fc_delivery_status', (string) ($result['status'] ?? 'failed'));
        $result['consulta_id'] = (int) $post_id;
        return $result;
    }

    try {
        $result = FLACSO_Mautic_Client::send_general_inquiry_notification($payload);
    } catch (Throwable $error) {
        // Once processing starts, do not encourage an automatic resend when
        // we cannot know whether the transport has already handed off email.
        $result = ['ok' => false, 'status' => 'acceptance_unknown', 'acceptance_unknown' => true, 'error' => 'No fue posible confirmar la entrega en Mautic.'];
    }
    update_post_meta($post_id, '_fc_delivery_status', (string) ($result['status'] ?? 'failed'));
    update_post_meta($post_id, '_fc_delivery_provider', 'mautic');
    update_post_meta($post_id, '_fc_mautic_email_id', (int) ($result['email_id'] ?? 0));
    $result['consulta_id'] = (int) $post_id;
    return $result;
}
