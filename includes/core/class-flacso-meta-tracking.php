<?php

if (!defined('ABSPATH')) {
    exit;
}

class FLACSO_Meta_Tracking {
    private const AJAX_ACTION = 'flacso_meta_track_event';
    private const GRAPH_API_VERSION = 'v25.0';
    private const LAST_TEST_OPTION = 'flacso_meta_last_test_result';
    private static $noscript_rendered = false;

    public static function init(): void {
        add_action('init', [self::class, 'maybe_handle_test_event_code_cookie']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_frontend_assets'], 1);
        add_action('wp_body_open', [self::class, 'render_noscript_pixel']);
        add_action('wp_footer', [self::class, 'render_noscript_pixel_fallback'], 1);
        add_action('wp_ajax_' . self::AJAX_ACTION, [self::class, 'handle_track_event']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [self::class, 'handle_track_event']);
        add_action('admin_post_flacso_meta_test_event', [self::class, 'handle_admin_test_event']);
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

    public static function handle_admin_test_event(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tenés permisos para ejecutar esta prueba.', 'flacso-uruguay'));
        }

        check_admin_referer('flacso_meta_test_event', 'flacso_meta_test_event_nonce');

        $settings = self::get_settings();
        $redirect_url = class_exists('FLACSO_Integrations_Settings')
            ? FLACSO_Integrations_Settings::get_redirect_url_from_request([], FLACSO_Integrations_Settings::get_meta_page_url())
            : admin_url('options-general.php?page=flacso-integracion-meta');

        if (!$settings['enabled']) {
            self::store_last_test_result([
                'status' => 'fail',
                'message' => __('Activá primero el tracking de Meta antes de ejecutar la prueba.', 'flacso-uruguay'),
            ]);
            wp_safe_redirect(add_query_arg([
                'flacso_meta_test' => 'fail',
                'flacso_meta_test_message' => __('Activá primero el tracking de Meta antes de ejecutar la prueba.', 'flacso-uruguay'),
            ], $redirect_url));
            exit;
        }

        if ($settings['pixel_id'] === '' || $settings['access_token'] === '') {
            self::store_last_test_result([
                'status' => 'fail',
                'message' => __('Completá el Pixel ID y el Access Token para poder probar CAPI.', 'flacso-uruguay'),
            ]);
            wp_safe_redirect(add_query_arg([
                'flacso_meta_test' => 'fail',
                'flacso_meta_test_message' => __('Completá el Pixel ID y el Access Token para poder probar CAPI.', 'flacso-uruguay'),
            ], $redirect_url));
            exit;
        }

        if ($settings['test_event_code'] === '') {
            self::store_last_test_result([
                'status' => 'fail',
                'message' => __('Agregá un Test Event Code para ejecutar una prueba segura sin enviar eventos reales.', 'flacso-uruguay'),
            ]);
            wp_safe_redirect(add_query_arg([
                'flacso_meta_test' => 'fail',
                'flacso_meta_test_message' => __('Agregá un Test Event Code para ejecutar una prueba segura sin enviar eventos reales.', 'flacso-uruguay'),
            ], $redirect_url));
            exit;
        }

        $event_id = 'flacso-meta-test-' . wp_generate_uuid4();
        $payload = self::build_capi_request_payload('PageView', $event_id, [
            'content_name' => 'Diagnostico Meta FLACSO',
            'content_category' => 'admin_meta_test',
            'flacso_stage' => 'admin_meta_test',
        ], [
            'event_source_url' => home_url('/'),
            'event_type' => 'track',
        ]);

        $response = self::send_capi_event($settings, $payload);

        if (is_wp_error($response)) {
            self::store_last_test_result([
                'status' => 'fail',
                'message' => $response->get_error_message(),
            ]);
            wp_safe_redirect(add_query_arg([
                'flacso_meta_test' => 'fail',
                'flacso_meta_test_message' => $response->get_error_message(),
            ], $redirect_url));
            exit;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code < 200 || $status_code >= 300) {
            $message = '';
            if (is_array($body) && !empty($body['error']['message'])) {
                $message = (string) $body['error']['message'];
            }

            if ($message === '') {
                $message = sprintf(
                    /* translators: %d: HTTP status code */
                    __('Meta devolvió un error HTTP %d durante la prueba.', 'flacso-uruguay'),
                    $status_code
                );
            }

            self::store_last_test_result([
                'status' => 'fail',
                'http_code' => $status_code,
                'fbtrace_id' => is_array($body) ? (string) ($body['fbtrace_id'] ?? '') : '',
                'message' => $message,
            ]);
            wp_safe_redirect(add_query_arg([
                'flacso_meta_test' => 'fail',
                'flacso_meta_test_code' => $status_code,
                'flacso_meta_test_message' => $message,
            ], $redirect_url));
            exit;
        }

        $message = __('Meta aceptó el evento de prueba por CAPI. Revisá Events Manager > Test Events para confirmarlo visualmente.', 'flacso-uruguay');
        if (is_array($body) && isset($body['events_received'])) {
            $message .= ' ' . sprintf(
                /* translators: %d: event count */
                __('Eventos recibidos: %d.', 'flacso-uruguay'),
                (int) $body['events_received']
            );
        }

        self::store_last_test_result([
            'status' => 'success',
            'http_code' => $status_code,
            'events_received' => is_array($body) ? (int) ($body['events_received'] ?? 0) : 0,
            'fbtrace_id' => is_array($body) ? (string) ($body['fbtrace_id'] ?? '') : '',
            'message' => $message,
            'event_id' => $event_id,
        ]);
        wp_safe_redirect(add_query_arg([
            'flacso_meta_test' => 'success',
            'flacso_meta_test_message' => $message,
        ], $redirect_url));
        exit;
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
            $settings = FLACSO_Integrations_Settings::get_meta_settings();
            
            // Only apply test_event_code if it is explicitly present in the current session (via cookie or query param)
            // or if we are executing an administrator action in the dashboard (like testing the connection).
            if (!empty($settings['test_event_code'])) {
                $configured_code = $settings['test_event_code'];
                $has_cookie = !empty($_COOKIE['flacso_meta_test_event_code']) && $_COOKIE['flacso_meta_test_event_code'] === $configured_code;
                $has_get_param = !empty($_GET['test_event_code']) && preg_replace('/[^A-Za-z0-9]/', '', (string) $_GET['test_event_code']) === $configured_code;
                $is_admin_action = is_admin() && (!function_exists('wp_doing_ajax') || !wp_doing_ajax());
                
                if (!$has_cookie && !$has_get_param && !$is_admin_action) {
                    $settings['test_event_code'] = '';
                }
            }
            return $settings;
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

    private static function store_last_test_result(array $result): void {
        $payload = [
            'status' => sanitize_key((string) ($result['status'] ?? 'fail')),
            'timestamp' => time(),
            'http_code' => isset($result['http_code']) ? absint($result['http_code']) : 0,
            'events_received' => isset($result['events_received']) ? absint($result['events_received']) : null,
            'fbtrace_id' => sanitize_text_field((string) ($result['fbtrace_id'] ?? '')),
            'message' => sanitize_text_field((string) ($result['message'] ?? '')),
            'event_id' => sanitize_text_field((string) ($result['event_id'] ?? '')),
        ];

        update_option(self::LAST_TEST_OPTION, $payload, false);
    }

    public static function maybe_handle_test_event_code_cookie(): void {
        $path = defined('COOKIEPATH') ? COOKIEPATH : '/';
        $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';

        if (isset($_GET['test_event_code'])) {
            $code = preg_replace('/[^A-Za-z0-9]/', '', (string) $_GET['test_event_code']);
            if ($code !== '') {
                setcookie('flacso_meta_test_event_code', $code, time() + 7200, $path, $domain, is_ssl(), true);
                $_COOKIE['flacso_meta_test_event_code'] = $code;
            } else {
                setcookie('flacso_meta_test_event_code', '', time() - 3600, $path, $domain, is_ssl(), true);
                unset($_COOKIE['flacso_meta_test_event_code']);
            }
        } elseif (isset($_GET['clear_test_event_code'])) {
            setcookie('flacso_meta_test_event_code', '', time() - 3600, $path, $domain, is_ssl(), true);
            unset($_COOKIE['flacso_meta_test_event_code']);
        }
    }
}

FLACSO_Meta_Tracking::init();
