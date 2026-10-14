<?php
/** Consulta general: registro privado en WordPress y notificación por Mautic. */
if (!defined('ABSPATH')) {
    exit;
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

    try {
        $result = FLACSO_Mautic_Client::send_general_inquiry_notification($payload);
    } catch (Throwable $error) {
        // Once processing starts, do not encourage an automatic resend when
        // we cannot know whether the transport has already handed off email.
        $result = ['ok' => false, 'status' => 'acceptance_unknown', 'acceptance_unknown' => true, 'error' => 'No fue posible confirmar la entrega en Mautic.'];
    }
    update_post_meta($post_id, '_fc_delivery_status', (string) ($result['status'] ?? 'failed'));
    update_post_meta($post_id, '_fc_mautic_email_id', (int) ($result['email_id'] ?? 0));
    $result['consulta_id'] = (int) $post_id;
    return $result;
}
