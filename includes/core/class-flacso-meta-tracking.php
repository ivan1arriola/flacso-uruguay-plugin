<?php

if (!defined('ABSPATH')) {
    exit;
}

class FLACSO_Meta_Tracking {
    private const AJAX_ACTION = 'flacso_meta_track_event';
    private const GRAPH_API_VERSION = 'v25.0';
    private static $noscript_rendered = false;

    public static function init(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_frontend_assets'], 1);
        add_action('wp_body_open', [self::class, 'render_noscript_pixel']);
        add_action('wp_footer', [self::class, 'render_noscript_pixel_fallback'], 1);
        add_action('wp_ajax_' . self::AJAX_ACTION, [self::class, 'handle_track_event']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [self::class, 'handle_track_event']);
    }

    public static function enqueue_frontend_assets(): void {
        if (!self::should_boot_frontend_tracking()) {
            return;
        }

        wp_register_script(
            'flacso-meta-tracking',
            FLACSO_URUGUAY_URL . 'includes/core/assets/meta-tracking.js',
            [],
            FLACSO_URUGUAY_VERSION,
            false
        );

        $settings = self::get_settings();
        $config = [
            'enabled' => $settings['enabled'] && $settings['pixel_id'] !== '',
            'pixelId' => $settings['pixel_id'],
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'ajaxAction' => self::AJAX_ACTION,
            'capiEnabled' => $settings['enabled'] && $settings['capi_enabled'],
            'trackPageView' => $settings['enabled'] && $settings['track_pageview'],
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
        ];

        wp_enqueue_script('flacso-meta-tracking');
        wp_add_inline_script(
            'flacso-meta-tracking',
            'window.flacsoMetaConfig = ' . wp_json_encode($config) . ';',
            'before'
        );
    }

    public static function handle_track_event(): void {
        if (!self::should_accept_event_request()) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }

        $settings = self::get_settings();
        if (!$settings['enabled'] || !$settings['capi_enabled'] || $settings['pixel_id'] === '') {
            wp_send_json_success(['sent' => false, 'reason' => 'disabled']);
        }

        $event_name = self::sanitize_event_name($_POST['event_name'] ?? '');
        $event_id = sanitize_text_field((string) wp_unslash($_POST['event_id'] ?? ''));
        $event_source_url = esc_url_raw((string) wp_unslash($_POST['event_source_url'] ?? ''));
        $params = self::decode_params($_POST['params'] ?? '');
        $fbp = sanitize_text_field((string) wp_unslash($_POST['fbp'] ?? ''));
        $fbc = sanitize_text_field((string) wp_unslash($_POST['fbc'] ?? ''));
        $event_type = sanitize_key((string) wp_unslash($_POST['event_type'] ?? 'track'));

        if ($event_name === '') {
            wp_send_json_error(['message' => 'invalid_event_name'], 400);
        }

        if ($event_id === '') {
            $event_id = wp_generate_uuid4();
        }

        $payload = self::build_capi_request_payload($event_name, $event_id, $params, [
            'event_source_url' => $event_source_url,
            'fbp' => $fbp,
            'fbc' => $fbc,
            'event_type' => $event_type,
        ]);

        $response = self::send_capi_event($settings, $payload);

        if (is_wp_error($response)) {
            self::log_error('request_failed', $response->get_error_message());
            wp_send_json_error(['message' => 'request_failed'], 502);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            self::log_error('http_' . $status_code, wp_remote_retrieve_body($response));
            wp_send_json_error(['message' => 'meta_rejected', 'status' => $status_code], 502);
        }

        wp_send_json_success(['sent' => true, 'event_id' => $event_id]);
    }

    public static function render_noscript_pixel(): void {
        if (self::$noscript_rendered) {
            return;
        }

        $settings = self::get_settings();
        if (!$settings['enabled'] || $settings['pixel_id'] === '') {
            return;
        }

        self::$noscript_rendered = true;

        printf(
            '<noscript><img height="1" width="1" style="display:none" alt="" src="%s" /></noscript>',
            esc_url(
                add_query_arg(
                    [
                        'id' => $settings['pixel_id'],
                        'ev' => 'PageView',
                        'noscript' => '1',
                    ],
                    'https://www.facebook.com/tr'
                )
            )
        );
    }

    public static function render_noscript_pixel_fallback(): void {
        self::render_noscript_pixel();
    }

    private static function should_boot_frontend_tracking(): bool {
        if (is_admin()) {
            return false;
        }

        if ((defined('REST_REQUEST') && REST_REQUEST) || wp_doing_ajax()) {
            return false;
        }

        if (is_feed() || is_trackback() || is_robots()) {
            return false;
        }

        return (bool) apply_filters('flacso_meta_should_track', true);
    }

    private static function should_accept_event_request(): bool {
        if (!self::request_origin_matches_site()) {
            return false;
        }

        return (bool) apply_filters('flacso_meta_should_accept_event_request', true);
    }

    private static function get_settings(): array {
        if (class_exists('FLACSO_Integrations_Settings') && is_callable(['FLACSO_Integrations_Settings', 'get_meta_settings'])) {
            return FLACSO_Integrations_Settings::get_meta_settings();
        }

        return [
            'enabled' => false,
            'pixel_id' => '',
            'access_token' => '',
            'test_event_code' => '',
            'track_pageview' => true,
            'capi_enabled' => false,
            'is_ready' => false,
        ];
    }

    private static function sanitize_event_name($value): string {
        $value = trim((string) wp_unslash($value));
        $value = preg_replace('/[^A-Za-z0-9_\-]/', '', $value);
        return is_string($value) ? substr($value, 0, 64) : '';
    }

    private static function decode_params($raw): array {
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) wp_unslash($raw), true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function build_capi_request_payload(string $event_name, string $event_id, array $params, array $context): array {
        $reserved_user_data_keys = [
            'em',
            'ph',
            'fn',
            'ln',
            'ct',
            'st',
            'zp',
            'country',
            'external_id',
            'client_ip_address',
            'client_user_agent',
            'fbp',
            'fbc',
            'ge',
            'db',
            'madid',
            'lead_id',
            'subscription_id',
            'fb_login_id',
        ];

        $user_data = [
            'client_ip_address' => self::get_client_ip_address(),
            'client_user_agent' => self::get_user_agent(),
        ];

        if (!empty($context['fbp'])) {
            $user_data['fbp'] = trim((string) $context['fbp']);
        } elseif (!empty($_COOKIE['_fbp'])) {
            $user_data['fbp'] = sanitize_text_field(wp_unslash($_COOKIE['_fbp']));
        }

        if (!empty($context['fbc'])) {
            $user_data['fbc'] = trim((string) $context['fbc']);
        } elseif (!empty($_COOKIE['_fbc'])) {
            $user_data['fbc'] = sanitize_text_field(wp_unslash($_COOKIE['_fbc']));
        }

        $custom_data = [];

        foreach ($params as $key => $value) {
            $key = sanitize_key((string) $key);
            if ($key === '') {
                continue;
            }

            if (in_array($key, $reserved_user_data_keys, true)) {
                $normalized = self::normalize_user_data_value($key, $value);
                if ($normalized !== '') {
                    $user_data[$key] = $normalized;
                }
                continue;
            }

            $custom_data[$key] = self::sanitize_custom_data_value($value);
        }

        $event = [
            'event_name' => $event_name,
            'event_time' => time(),
            'action_source' => 'website',
            'event_id' => $event_id,
            'user_data' => array_filter($user_data, static function ($value) {
                return $value !== '' && $value !== null;
            }),
            'custom_data' => array_filter($custom_data, static function ($value) {
                return $value !== '' && $value !== null && $value !== [];
            }),
        ];

        if (!empty($context['event_source_url'])) {
            $event['event_source_url'] = esc_url_raw((string) $context['event_source_url']);
        }

        if (!empty($context['event_type']) && $context['event_type'] === 'trackCustom') {
            $event['custom_data']['event_origin'] = 'trackCustom';
        }

        return $event;
    }

    private static function normalize_user_data_value(string $key, $value): string {
        if (is_array($value)) {
            $value = reset($value);
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (in_array($key, ['fbp', 'fbc', 'client_ip_address', 'client_user_agent'], true)) {
            return $value;
        }

        if ($key === 'ph') {
            $value = preg_replace('/\D+/', '', $value);
        } else {
            $value = strtolower($value);
        }

        if ($value === '') {
            return '';
        }

        if (preg_match('/^[a-f0-9]{64}$/i', $value)) {
            return strtolower($value);
        }

        return hash('sha256', $value);
    }

    private static function sanitize_custom_data_value($value) {
        if (is_bool($value) || is_numeric($value)) {
            return $value;
        }

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $item) {
                $clean[] = self::sanitize_custom_data_value($item);
            }
            return array_values(array_filter($clean, static function ($item) {
                return $item !== '' && $item !== null;
            }));
        }

        if (is_object($value)) {
            return self::sanitize_custom_data_value((array) $value);
        }

        $value = sanitize_text_field((string) $value);
        return $value;
    }

    private static function send_capi_event(array $settings, array $event) {
        $endpoint = sprintf(
            'https://graph.facebook.com/%s/%s/events',
            self::GRAPH_API_VERSION,
            rawurlencode($settings['pixel_id'])
        );

        $body = [
            'data' => [$event],
        ];

        if ($settings['test_event_code'] !== '') {
            $body['test_event_code'] = $settings['test_event_code'];
        }

        return wp_remote_post(
            add_query_arg(
                ['access_token' => $settings['access_token']],
                $endpoint
            ),
            [
                'timeout' => 10,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => wp_json_encode($body),
            ]
        );
    }

    private static function get_client_ip_address(): string {
        $candidates = [
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];

        foreach ($candidates as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }

            $raw = is_array($_SERVER[$key]) ? reset($_SERVER[$key]) : $_SERVER[$key];
            $parts = explode(',', (string) $raw);
            $first = trim((string) ($parts[0] ?? ''));
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        return '';
    }

    private static function get_user_agent(): string {
        return !empty($_SERVER['HTTP_USER_AGENT'])
            ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 1024)
            : '';
    }

    private static function request_origin_matches_site(): bool {
        $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        if (!is_string($site_host) || $site_host === '') {
            return true;
        }

        foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $header) {
            if (empty($_SERVER[$header])) {
                continue;
            }

            $host = wp_parse_url((string) wp_unslash($_SERVER[$header]), PHP_URL_HOST);
            if (is_string($host) && $host !== '' && strcasecmp($host, $site_host) !== 0) {
                return false;
            }
        }

        return true;
    }

    private static function log_error(string $code, string $details): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        error_log('[FLACSO Meta] ' . $code . ' :: ' . $details);
    }
}

FLACSO_Meta_Tracking::init();
