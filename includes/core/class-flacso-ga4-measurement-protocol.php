<?php
/**
 * Envía a GA4 por Measurement Protocol las conversiones que el navegador puede perder
 * (bloqueadores, navegadores integrados de Instagram y Facebook, cierre de pestaña).
 *
 * Se activa solo si están definidas FLACSO_GA4_MEASUREMENT_ID y FLACSO_GA4_API_SECRET
 * (wp-config.php) o si el filtro `flacso_ga4_mp_settings` las devuelve. El secreto
 * nunca se guarda en el repositorio.
 */
if (!defined('ABSPATH')) {
    exit;
}

class FLACSO_GA4_Measurement_Protocol {
    private const ENDPOINT = 'https://www.google-analytics.com/mp/collect';

    /** Eventos de Meta que se envían a GA4 desde el servidor, con su nombre en GA4. */
    private const EVENT_MAP = [
        'Lead' => 'generate_lead',
        'SubmitApplication' => 'submit_application',
    ];

    public static function ga4_name_for(string $meta_event_name): string {
        return self::EVENT_MAP[$meta_event_name] ?? '';
    }

    public static function settings(): array {
        $settings = [
            'measurement_id' => defined('FLACSO_GA4_MEASUREMENT_ID') ? (string) FLACSO_GA4_MEASUREMENT_ID : '',
            'api_secret' => defined('FLACSO_GA4_API_SECRET') ? (string) FLACSO_GA4_API_SECRET : '',
        ];
        if (function_exists('apply_filters')) {
            $settings = (array) apply_filters('flacso_ga4_mp_settings', $settings);
        }
        $settings['measurement_id'] = preg_match('/^G-[A-Z0-9]{6,20}$/', (string) ($settings['measurement_id'] ?? '')) ? (string) $settings['measurement_id'] : '';
        $settings['api_secret'] = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($settings['api_secret'] ?? ''));
        return $settings;
    }

    public static function is_enabled(): bool {
        $settings = self::settings();
        return $settings['measurement_id'] !== '' && $settings['api_secret'] !== '';
    }

    /** `_ga` = GA1.<n>.<client>.<ts>; el client_id de GA4 son los dos últimos tramos. */
    public static function client_id_from_cookie(string $cookie): string {
        $parts = explode('.', $cookie);
        if (count($parts) >= 4 && ctype_digit($parts[count($parts) - 1]) && ctype_digit($parts[count($parts) - 2])) {
            return $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
        }
        return '';
    }

    /** `_ga_<ID>` = GS1.1.<session_id>.<n>... (GS2: `s<session_id>$o...`). */
    public static function session_id_from_cookie(string $cookie): string {
        if (preg_match('/^GS1\.\d+\.(\d+)\./', $cookie, $m)) {
            return $m[1];
        }
        if (preg_match('/^GS2\.\d+\.s(\d+)\$/', $cookie, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * Arma el cuerpo del pedido. Sin cookie `_ga` (gtag bloqueado) usa un client_id estable
     * derivado del identificador externo del navegador, para que igual cuente como usuario.
     */
    public static function build_payload(string $meta_event_name, string $event_id, array $params, string $ga_cookie, string $session_cookie, string $external_id): array {
        $name = self::ga4_name_for($meta_event_name);
        if ($name === '') {
            return [];
        }

        $client_id = self::client_id_from_cookie($ga_cookie);
        if ($client_id === '') {
            if ($external_id === '') {
                return [];
            }
            $client_id = sprintf('%u.%u', crc32('flacso:' . $external_id), crc32($external_id . ':flacso'));
        }

        $event_params = [
            'engagement_time_msec' => 1,
            'flacso_event_id' => substr($event_id, 0, 100),
            'source_channel' => 'server',
        ];
        foreach (['content_name', 'content_category', 'offer_code', 'lead_type', 'form_type'] as $key) {
            if (isset($params[$key]) && is_scalar($params[$key]) && (string) $params[$key] !== '') {
                $event_params[$key] = substr((string) $params[$key], 0, 100);
            }
        }
        $session_id = self::session_id_from_cookie($session_cookie);
        if ($session_id !== '') {
            $event_params['session_id'] = $session_id;
        }

        return [
            'client_id' => $client_id,
            'events' => [['name' => $name, 'params' => $event_params]],
        ];
    }

    /** Envío sin esperar respuesta: el seguimiento nunca retrasa al visitante. */
    public static function send(array $payload): bool {
        $settings = self::settings();
        if ($payload === [] || $settings['measurement_id'] === '' || $settings['api_secret'] === '') {
            return false;
        }
        $url = self::ENDPOINT . '?measurement_id=' . rawurlencode($settings['measurement_id']) . '&api_secret=' . rawurlencode($settings['api_secret']);
        $response = wp_remote_post($url, [
            'timeout' => 3,
            'blocking' => false,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($payload),
        ]);
        return !is_wp_error($response);
    }

    /** Punto de entrada desde el manejador AJAX, con las cookies de la petición. */
    public static function track_from_request(string $meta_event_name, string $event_id, array $params, string $external_id): bool {
        if (!self::is_enabled() || self::ga4_name_for($meta_event_name) === '') {
            return false;
        }
        $ga_cookie = isset($_COOKIE['_ga']) ? sanitize_text_field(wp_unslash((string) $_COOKIE['_ga'])) : '';
        $session_cookie = '';
        $measurement_id = self::settings()['measurement_id'];
        $cookie_name = '_ga_' . substr($measurement_id, 2);
        if (isset($_COOKIE[$cookie_name])) {
            $session_cookie = sanitize_text_field(wp_unslash((string) $_COOKIE[$cookie_name]));
        }
        return self::send(self::build_payload($meta_event_name, $event_id, $params, $ga_cookie, $session_cookie, $external_id));
    }
}
