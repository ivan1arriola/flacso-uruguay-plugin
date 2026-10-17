<?php
/** Signed event client for the FLACSO Mailing service. */
if (!defined('ABSPATH') && !defined('STDIN')) { exit; }

final class FLACSO_Mailing_Client {
    private static $transport = null;

    public static function set_http_transport(?callable $transport): void { self::$transport = $transport; }

    public static function is_configured(): bool {
        return self::base_url() !== '' && defined('WP_WEBHOOK_HMAC_SECRET')
            && is_string(WP_WEBHOOK_HMAC_SECRET) && strlen(WP_WEBHOOK_HMAC_SECRET) >= 32;
    }

    public static function delivery_provider(): string {
        $provider = function_exists('get_option') ? strtolower(trim((string) get_option('flacso_inquiry_delivery_provider', 'mautic'))) : 'mautic';
        return in_array($provider, ['mautic', 'flacso_mail'], true) ? $provider : 'mautic';
    }

    /** Send the immutable academic inquiry event using the same key and bytes on every retry. */
    public static function send_academic_inquiry(array $delivery, array $snapshot, array $consent): array {
        if (!self::is_configured()) return ['ok' => false, 'status' => 'blocked', 'error' => 'Mailing no está configurado.'];
        $delivery_id = trim((string) ($delivery['id'] ?? ''));
        $consulta_id = trim((string) ($snapshot['consultaId'] ?? ''));
        if ($delivery_id === '' || $consulta_id === '' || !in_array(($snapshot['inquiryType'] ?? ''), ['offer', 'seminar'], true)) {
            return ['ok' => false, 'status' => 'blocked', 'error' => 'Identidad de consulta incompleta.'];
        }
        $granted = in_array($consent['granted'] ?? false, [true, 1, '1'], true);
        $accepted_at = trim((string) ($consent['acceptedAt'] ?? ''));
        $valid_consent = $granted && $accepted_at !== '' && strtotime($accepted_at) !== false
            && trim((string) ($consent['source'] ?? '')) !== ''
            && trim((string) ($consent['textVersion'] ?? '')) !== '';
        $marketing_consent = ['granted' => $valid_consent];
        if ($valid_consent) {
            $marketing_consent['acceptedAt'] = (string) $consent['acceptedAt'];
            $marketing_consent['source'] = (string) $consent['source'];
            $marketing_consent['textVersion'] = (string) $consent['textVersion'];
        }
        $event = [
            'event_type' => 'academic.inquiry.created',
            'idempotency_key' => 'wp_delivery_' . $delivery_id,
            'occurred_at' => (string) ($snapshot['inquiryAt'] ?? gmdate('c')),
            'assigned_provider' => 'flacso_mail',
            'data' => [
                'external_inquiry_id' => $consulta_id,
                'marketingConsent' => $marketing_consent,
                'followup_policy' => [
                    'enabled' => $valid_consent && ($snapshot['inquiryType'] ?? '') === 'offer'
                        && ($snapshot['academic']['status'] ?? '') === 'abierta',
                    'delay_seconds' => 604800,
                ],
                'snapshot' => $snapshot,
            ],
        ];
        $body = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) return ['ok' => false, 'status' => 'blocked', 'error' => 'No se pudo serializar el evento.'];
        $timestamp = (string) time();
        $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, WP_WEBHOOK_HMAC_SECRET);
        $args = [
            'timeout' => 8,
            'redirection' => 0,
            'headers' => [
                'Content-Type' => 'application/json',
                'Idempotency-Key' => $event['idempotency_key'],
                'X-Flacso-Timestamp' => $timestamp,
                'X-Flacso-Signature' => $signature,
            ],
            'body' => $body,
        ];
        try {
            $response = is_callable(self::$transport)
                ? call_user_func(self::$transport, self::base_url() . '/api/v1/events/academic-inquiries', $args)
                : wp_remote_post(self::base_url() . '/api/v1/events/academic-inquiries', $args);
        } catch (Throwable $error) {
            return ['ok' => false, 'status' => 'acceptance_unknown', 'error' => 'No se pudo confirmar la recepción en Mailing.'];
        }
        if (function_exists('is_wp_error') && is_wp_error($response)) {
            return ['ok' => false, 'status' => 'acceptance_unknown', 'error' => 'No se pudo confirmar la recepción en Mailing.'];
        }
        $code = (int) (function_exists('wp_remote_retrieve_response_code')
            ? wp_remote_retrieve_response_code($response) : ($response['response']['code'] ?? 0));
        if ($code === 200 || $code === 202) return ['ok' => true, 'status' => 'accepted', 'http_code' => $code];
        if ($code === 409) return ['ok' => false, 'status' => 'failed', 'http_code' => $code, 'error' => 'Conflicto de idempotencia en Mailing.'];
        if ($code === 0 || $code === 408 || $code === 429 || $code >= 500) {
            return ['ok' => false, 'status' => 'retryable_failed', 'http_code' => $code ?: null, 'error' => 'Mailing no pudo aceptar el evento.'];
        }
        return ['ok' => false, 'status' => 'failed', 'http_code' => $code, 'error' => 'Mailing rechazó el evento.'];
    }

    /** Deliver a general inquiry to the operational template without creating a contact. */
    public static function send_general_inquiry(int $post_id, array $payload): array {
        if (!self::is_configured()) return ['ok' => false, 'status' => 'blocked', 'error' => 'Mailing no está configurado.'];
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $message = trim((string) ($payload['mensaje'] ?? ''));
        if ($post_id <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
            return ['ok' => false, 'status' => 'blocked', 'error' => 'Identidad de consulta general incompleta.'];
        }
        $occurred_at = trim((string) ($payload['fecha_envio'] ?? ''));
        if ($occurred_at === '' || strtotime($occurred_at) === false) $occurred_at = gmdate('c');
        $event = [
            'event_type' => 'general.inquiry.created',
            'idempotency_key' => 'wp_general_' . $post_id,
            'occurred_at' => $occurred_at,
            'data' => [
                'external_submission_id' => (string) $post_id,
                'visitor' => [
                    'email' => $email,
                    'first_name' => trim((string) ($payload['nombre'] ?? '')),
                    'last_name' => trim((string) ($payload['apellido'] ?? '')),
                ],
                'message_body' => $message,
                'page_url' => (string) ($payload['page_url'] ?? $payload['url'] ?? 'https://flacso.edu.uy/contacto/'),
            ],
        ];
        $body = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) return ['ok' => false, 'status' => 'blocked', 'error' => 'No se pudo serializar el evento.'];
        $timestamp = (string) time();
        $url = self::base_url() . '/api/v1/events/general-inquiries';
        $args = [
            'timeout' => 8, 'redirection' => 0,
            'headers' => [
                'Content-Type' => 'application/json',
                'Idempotency-Key' => $event['idempotency_key'],
                'X-Flacso-Timestamp' => $timestamp,
                'X-Flacso-Signature' => 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, WP_WEBHOOK_HMAC_SECRET),
            ],
            'body' => $body,
        ];
        try {
            $response = is_callable(self::$transport) ? call_user_func(self::$transport, $url, $args) : wp_remote_post($url, $args);
        } catch (Throwable $error) {
            return ['ok' => false, 'status' => 'acceptance_unknown', 'acceptance_unknown' => true, 'error' => 'No se pudo confirmar la recepción en Mailing.'];
        }
        if (function_exists('is_wp_error') && is_wp_error($response)) {
            return ['ok' => false, 'status' => 'acceptance_unknown', 'acceptance_unknown' => true, 'error' => 'No se pudo confirmar la recepción en Mailing.'];
        }
        $code = (int) (function_exists('wp_remote_retrieve_response_code')
            ? wp_remote_retrieve_response_code($response) : ($response['response']['code'] ?? 0));
        if ($code === 200 || $code === 202) return ['ok' => true, 'status' => 'accepted', 'http_code' => $code];
        if ($code === 409) return ['ok' => false, 'status' => 'failed', 'http_code' => $code, 'error' => 'Conflicto de idempotencia en Mailing.'];
        if ($code === 0 || $code === 408 || $code >= 500) {
            return ['ok' => false, 'status' => 'acceptance_unknown', 'acceptance_unknown' => true, 'http_code' => $code ?: null, 'error' => 'Mailing no pudo confirmar la recepción en Mailing.'];
        }
        if ($code === 429) {
            return ['ok' => false, 'status' => 'retryable_failed', 'http_code' => $code ?: null, 'error' => 'Mailing no pudo aceptar el evento.'];
        }
        return ['ok' => false, 'status' => 'failed', 'http_code' => $code, 'error' => 'Mailing rechazó el evento.'];
    }

    /** Synchronize an explicitly consented subscriber through the signed contact event. */
    public static function sync_contact(array $data, string $idempotency_key): array {
        if (!self::is_configured()) return ['ok' => false, 'status' => 'blocked', 'error' => 'Mailing no está configurado.'];
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $accepted_at = trim((string) ($data['acceptedAt'] ?? ''));
        $source = trim((string) ($data['source'] ?? ''));
        $text_version = trim((string) ($data['textVersion'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $accepted_at === '' || strtotime($accepted_at) === false
            || $source === '' || $text_version === '' || !preg_match('/^[A-Za-z0-9_-]{1,180}$/', $idempotency_key)) {
            return ['ok' => false, 'status' => 'blocked', 'error' => 'Consentimiento o identidad de suscripción inválidos.'];
        }
        $event = [
            'event_type' => 'contact.synced',
            'idempotency_key' => $idempotency_key,
            'occurred_at' => $accepted_at,
            'data' => [
                'email' => $email,
                'first_name' => trim((string) ($data['first_name'] ?? '')),
                'last_name' => trim((string) ($data['last_name'] ?? '')),
                'stable_attributes' => [],
                'marketingConsent' => [
                    'granted' => true, 'acceptedAt' => $accepted_at,
                    'source' => $source, 'textVersion' => $text_version,
                ],
                'tags' => ['suscripcion_institucional'],
                'list_slugs' => ['suscripcion-institucional'],
            ],
        ];
        $body = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) return ['ok' => false, 'status' => 'blocked', 'error' => 'No se pudo serializar el evento.'];
        $timestamp = (string) time();
        $url = self::base_url() . '/api/v1/events/contacts';
        $args = [
            'timeout' => 8, 'redirection' => 0,
            'headers' => [
                'Content-Type' => 'application/json', 'Idempotency-Key' => $idempotency_key,
                'X-Flacso-Timestamp' => $timestamp,
                'X-Flacso-Signature' => 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, WP_WEBHOOK_HMAC_SECRET),
            ],
            'body' => $body,
        ];
        try {
            $response = is_callable(self::$transport) ? call_user_func(self::$transport, $url, $args) : wp_remote_post($url, $args);
        } catch (Throwable $error) {
            return ['ok' => false, 'status' => 'acceptance_unknown', 'acceptance_unknown' => true, 'error' => 'No se pudo confirmar la suscripción en Mailing.'];
        }
        if (function_exists('is_wp_error') && is_wp_error($response)) {
            return ['ok' => false, 'status' => 'acceptance_unknown', 'acceptance_unknown' => true, 'error' => 'No se pudo confirmar la suscripción en Mailing.'];
        }
        $code = (int) (function_exists('wp_remote_retrieve_response_code')
            ? wp_remote_retrieve_response_code($response) : ($response['response']['code'] ?? 0));
        if ($code === 200 || $code === 202) return ['ok' => true, 'status' => 'accepted', 'http_code' => $code];
        if ($code === 409) return ['ok' => false, 'status' => 'failed', 'http_code' => $code, 'error' => 'Conflicto de idempotencia en Mailing.'];
        if ($code === 0 || $code === 408 || $code === 429 || $code >= 500) {
            return ['ok' => false, 'status' => 'retryable_failed', 'http_code' => $code ?: null, 'error' => 'Mailing no pudo aceptar la suscripción.'];
        }
        return ['ok' => false, 'status' => 'failed', 'http_code' => $code, 'error' => 'Mailing rechazó la suscripción.'];
    }

    private static function base_url(): string {
        $url = function_exists('get_option') ? (string) get_option('flacso_mailing_base_url', '') : '';
        $url = function_exists('esc_url_raw') ? esc_url_raw($url) : filter_var($url, FILTER_SANITIZE_URL);
        $parts = is_string($url) ? parse_url($url) : false;
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return '';
        return rtrim((string) $url, '/');
    }
}
