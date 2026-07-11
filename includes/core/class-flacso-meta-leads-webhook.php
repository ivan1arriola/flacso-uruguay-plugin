<?php

if (!defined('ABSPATH')) {
    exit;
}

class FLACSO_Meta_Leads_Webhook {
    private const REST_NAMESPACE = 'flacso/v1';
    private const REST_ROUTE = '/meta-leads';
    private const POST_TYPE = 'flacso_meta_lead';

    public static function init(): void {
        add_action('init', [self::class, 'register_post_type']);
        add_action('rest_api_init', [self::class, 'register_routes']);
        add_action('admin_post_flacso_meta_leads_check_permissions', [self::class, 'handle_admin_permission_check']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Leads de Meta', 'flacso-uruguay'),
                'singular_name' => __('Lead de Meta', 'flacso-uruguay'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor', 'custom-fields'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'verify_webhook'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'receive_webhook'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public static function verify_webhook(WP_REST_Request $request): WP_REST_Response {
        $settings = self::get_settings();
        $mode = (string) $request->get_param('hub_mode');
        $token = (string) $request->get_param('hub_verify_token');
        $challenge = (string) $request->get_param('hub_challenge');

        if ($mode === '') {
            $mode = (string) $request->get_param('hub.mode');
        }
        if ($token === '') {
            $token = (string) $request->get_param('hub.verify_token');
        }
        if ($challenge === '') {
            $challenge = (string) $request->get_param('hub.challenge');
        }

        if ($mode === 'subscribe' && $settings['verify_token'] !== '' && hash_equals($settings['verify_token'], $token)) {
            $response = new WP_REST_Response($challenge, 200);
            $response->header('Content-Type', 'text/plain; charset=' . get_option('blog_charset'));

            return $response;
        }

        return new WP_REST_Response(['error' => 'invalid_verify_token'], 403);
    }

    public static function receive_webhook(WP_REST_Request $request): WP_REST_Response {
        $settings = self::get_settings();

        if (!$settings['enabled']) {
            return new WP_REST_Response(['received' => true, 'processed' => 0, 'reason' => 'disabled'], 200);
        }

        if (!self::is_valid_signature($request, $settings['app_secret'])) {
            return new WP_REST_Response(['error' => 'invalid_signature'], 403);
        }

        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = json_decode($request->get_body(), true);
        }
        if (!is_array($payload)) {
            return new WP_REST_Response(['error' => 'invalid_payload'], 400);
        }

        $summary = [
            'received' => true,
            'processed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach (self::extract_lead_events($payload) as $event) {
            $result = self::process_lead_event($event, $settings);

            if (!empty($result['processed'])) {
                $summary['processed']++;
                continue;
            }

            $summary['skipped']++;
            if (!empty($result['error'])) {
                $summary['errors'][] = $result['error'];
            }
        }

        return new WP_REST_Response($summary, 200);
    }

    public static function handle_admin_permission_check(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tenés permisos para ejecutar este chequeo.', 'flacso-uruguay'));
        }

        check_admin_referer('flacso_meta_leads_check_permissions', 'flacso_meta_leads_check_permissions_nonce');

        $redirect_url = class_exists('FLACSO_Integrations_Settings')
            ? FLACSO_Integrations_Settings::get_redirect_url_from_request([], FLACSO_Integrations_Settings::get_meta_page_url())
            : admin_url('options-general.php?page=flacso-integracion-meta');

        $result = self::check_permissions();

        if (class_exists('FLACSO_Integrations_Settings')) {
            FLACSO_Integrations_Settings::store_meta_leads_last_permission_check($result);
        }

        wp_safe_redirect(add_query_arg([
            'flacso_meta_leads_check' => !empty($result['ok']) ? 'success' : 'fail',
        ], $redirect_url));
        exit;
    }

    private static function process_lead_event(array $event, array $settings): array {
        $leadgen_id = self::sanitize_meta_id($event['leadgen_id'] ?? '');
        $form_id = self::sanitize_meta_id($event['form_id'] ?? '');

        if ($leadgen_id === '') {
            return ['processed' => false, 'error' => 'missing_leadgen_id'];
        }

        if (!empty($settings['form_ids']) && ($form_id === '' || !in_array($form_id, $settings['form_ids'], true))) {
            return ['processed' => false, 'reason' => 'form_not_allowed'];
        }

        $existing_id = self::find_lead_post_id($leadgen_id);
        if ($existing_id > 0) {
            return ['processed' => true, 'post_id' => $existing_id, 'duplicate' => true];
        }

        $lead = self::fetch_lead($leadgen_id, $settings);
        if (is_wp_error($lead)) {
            self::store_failed_lead($leadgen_id, $form_id, $event, $lead->get_error_message());

            return ['processed' => false, 'error' => $lead->get_error_message()];
        }

        $normalized = self::normalize_lead($lead, $settings);
        $post_id = self::store_lead($leadgen_id, $form_id, $event, $lead, $normalized);

        if ($post_id <= 0) {
            return ['processed' => false, 'error' => 'could_not_store_lead'];
        }

        $forward_result = self::forward_lead($normalized, $settings);
        update_post_meta($post_id, '_flacso_meta_lead_forward_result', wp_json_encode($forward_result));
        update_post_meta($post_id, '_flacso_meta_lead_forwarded', !empty($forward_result['ok']) ? '1' : '0');

        $crm_event_result = self::send_initial_crm_event($normalized, $leadgen_id);
        update_post_meta($post_id, '_flacso_meta_lead_crm_capi_result', wp_json_encode($crm_event_result));
        update_post_meta($post_id, '_flacso_meta_lead_crm_capi_sent', !empty($crm_event_result['ok']) ? '1' : '0');

        do_action('flacso_meta_lead_received', $normalized, $lead, $post_id, $forward_result);

        return [
            'processed' => true,
            'post_id' => $post_id,
            'forwarded' => !empty($forward_result['ok']),
            'crm_capi_sent' => !empty($crm_event_result['ok']),
        ];
    }

    private static function check_permissions(): array {
        $settings = self::get_settings();
        $checks = [];
        $all_ok = true;

        $checks[] = [
            'label' => __('Page Access Token configurado', 'flacso-uruguay'),
            'ok' => $settings['page_access_token'] !== '',
            'message' => $settings['page_access_token'] !== ''
                ? __('Hay un token guardado para consultar Graph API.', 'flacso-uruguay')
                : __('Falta guardar un Page Access Token.', 'flacso-uruguay'),
        ];

        if ($settings['page_access_token'] === '') {
            return [
                'ok' => false,
                'checked_at' => time(),
                'checks' => $checks,
            ];
        }

        if ($settings['page_id'] === '') {
            $all_ok = false;
            $checks[] = [
                'label' => __('Page ID configurado', 'flacso-uruguay'),
                'ok' => false,
                'message' => __('Falta guardar el Page ID de Facebook para chequear formularios y suscripción de webhook.', 'flacso-uruguay'),
            ];
        }

        $me = self::graph_get('me', ['fields' => 'id,name'], $settings);
        self::append_graph_check(
            $checks,
            $all_ok,
            __('Token válido', 'flacso-uruguay'),
            $me,
            __('Meta aceptó el token y devolvió información de la cuenta/página.', 'flacso-uruguay')
        );

        if ($settings['page_id'] !== '') {
            $page = self::graph_get($settings['page_id'], ['fields' => 'id,name'], $settings);
            self::append_graph_check(
                $checks,
                $all_ok,
                __('Acceso a la página', 'flacso-uruguay'),
                $page,
                __('Meta devolvió la página configurada.', 'flacso-uruguay')
            );

            $forms = self::graph_get($settings['page_id'] . '/leadgen_forms', ['fields' => 'id,name,status', 'limit' => 1], $settings);
            self::append_graph_check(
                $checks,
                $all_ok,
                __('Lectura de formularios', 'flacso-uruguay'),
                $forms,
                __('El token permite listar formularios instantáneos de la página.', 'flacso-uruguay')
            );

            $subscriptions = self::graph_get($settings['page_id'] . '/subscribed_apps', ['fields' => 'id,name,subscribed_fields'], $settings);
            self::append_graph_check(
                $checks,
                $all_ok,
                __('Suscripción de webhook', 'flacso-uruguay'),
                $subscriptions,
                __('Meta devolvió las apps suscritas a la página.', 'flacso-uruguay')
            );
        }

        foreach ($settings['form_ids'] as $form_id) {
            $form = self::graph_get($form_id, ['fields' => 'id,name,status'], $settings);
            self::append_graph_check(
                $checks,
                $all_ok,
                sprintf(__('Formulario %s', 'flacso-uruguay'), $form_id),
                $form,
                __('Meta devolvió el formulario configurado.', 'flacso-uruguay')
            );

            $lead_probe = self::graph_get($form_id . '/leads', ['fields' => 'id,created_time', 'limit' => 1], $settings);
            self::append_graph_check(
                $checks,
                $all_ok,
                sprintf(__('Lectura de leads %s', 'flacso-uruguay'), $form_id),
                $lead_probe,
                __('El token permite consultar leads del formulario.', 'flacso-uruguay')
            );
        }

        return [
            'ok' => $all_ok,
            'checked_at' => time(),
            'checks' => $checks,
        ];
    }

    private static function append_graph_check(array &$checks, bool &$all_ok, string $label, $response, string $success_message): void {
        if (is_wp_error($response)) {
            $all_ok = false;
            $checks[] = [
                'label' => $label,
                'ok' => false,
                'message' => $response->get_error_message(),
            ];

            return;
        }

        $checks[] = [
            'label' => $label,
            'ok' => true,
            'message' => $success_message,
        ];
    }

    private static function extract_lead_events(array $payload): array {
        $events = [];
        $entries = isset($payload['entry']) && is_array($payload['entry']) ? $payload['entry'] : [];

        foreach ($entries as $entry) {
            $changes = isset($entry['changes']) && is_array($entry['changes']) ? $entry['changes'] : [];

            foreach ($changes as $change) {
                if (($change['field'] ?? '') !== 'leadgen') {
                    continue;
                }

                $value = isset($change['value']) && is_array($change['value']) ? $change['value'] : [];
                $events[] = [
                    'leadgen_id' => $value['leadgen_id'] ?? '',
                    'form_id' => $value['form_id'] ?? '',
                    'page_id' => $value['page_id'] ?? ($entry['id'] ?? ''),
                    'created_time' => $value['created_time'] ?? ($entry['time'] ?? ''),
                    'raw' => $change,
                ];
            }
        }

        return $events;
    }

    private static function fetch_lead(string $leadgen_id, array $settings) {
        return self::graph_get($leadgen_id, [
            'fields' => 'id,created_time,form_id,field_data,ad_id,ad_name,adset_id,adset_name,campaign_id,campaign_name',
        ], $settings);
    }

    private static function graph_get(string $path, array $query, array $settings) {
        $version = self::sanitize_graph_version($settings['graph_version'] ?? 'v25.0');
        $token = (string) ($settings['page_access_token'] ?? '');
        $url = add_query_arg(array_merge($query, ['access_token' => $token]), 'https://graph.facebook.com/' . $version . '/' . ltrim($path, '/'));
        $response = wp_remote_get($url, ['timeout' => 20]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status < 200 || $status >= 300) {
            $message = is_array($body) && !empty($body['error']['message'])
                ? (string) $body['error']['message']
                : sprintf('Meta devolvió HTTP %d.', $status);

            return new WP_Error('meta_graph_error', $message, ['status' => $status, 'body' => $body]);
        }

        return is_array($body) ? $body : [];
    }

    private static function normalize_lead(array $lead, array $settings): array {
        $fields = self::lead_fields_to_map($lead['field_data'] ?? []);
        $full_name = self::pick_field($fields, ['full_name', 'nombre_completo', 'name']);
        $first_name = self::pick_field($fields, ['first_name', 'nombre']);
        $last_name = self::pick_field($fields, ['last_name', 'apellido']);

        if ($first_name === '' && $full_name !== '') {
            [$first_name, $last_name] = self::split_full_name($full_name);
        }

        $offer_field = (string) ($settings['offer_field'] ?? 'programa');

        return [
            'nombre' => $first_name,
            'apellido' => $last_name,
            'correo' => self::pick_field($fields, ['email', 'correo', 'correo_electronico']),
            'telefono' => self::pick_field($fields, ['phone_number', 'telefono', 'celular']),
            'pais' => self::pick_field($fields, ['country', 'pais', 'pais_de_residencia']),
            'profesion' => self::pick_field($fields, ['profession', 'profesion']),
            'nivel_academico' => self::pick_field($fields, ['education_level', 'nivel_academico', 'nivel_educativo']),
            'programa' => self::pick_field($fields, [$offer_field, 'oferta', 'programa', 'propuesta', 'curso', 'diploma']),
            'id_pagina' => '',
            'url_referer' => 'https://www.facebook.com/lead_ads',
            'fecha_envio' => self::normalize_meta_created_time((string) ($lead['created_time'] ?? '')),
            'origen' => 'Meta Lead Ads',
            'source' => 'Meta Lead Ads',
            'campaign_provider' => 'meta',
            'campaign_source' => 'Meta Lead Ads',
            'campaign_medium' => 'lead_ad',
            'campaign_name' => (string) ($lead['campaign_name'] ?? ''),
            'campaign_external_id' => (string) ($lead['campaign_id'] ?? ''),
            'campaign_content' => (string) ($lead['ad_name'] ?? ''),
            'campaign_term' => (string) ($lead['adset_name'] ?? ''),
            'meta_leadgen_id' => (string) ($lead['id'] ?? ''),
            'meta_form_id' => self::extract_form_id($lead['form_id'] ?? ''),
            'meta_campaign_id' => (string) ($lead['campaign_id'] ?? ''),
            'meta_campaign_name' => (string) ($lead['campaign_name'] ?? ''),
            'meta_adset_id' => (string) ($lead['adset_id'] ?? ''),
            'meta_adset_name' => (string) ($lead['adset_name'] ?? ''),
            'meta_ad_id' => (string) ($lead['ad_id'] ?? ''),
            'meta_ad_name' => (string) ($lead['ad_name'] ?? ''),
            'raw_fields' => $fields,
        ];
    }

    private static function store_lead(string $leadgen_id, string $form_id, array $event, array $lead, array $normalized): int {
        $title_parts = array_filter([
            trim(($normalized['nombre'] ?? '') . ' ' . ($normalized['apellido'] ?? '')),
            $normalized['correo'] ?? '',
            $leadgen_id,
        ]);

        $post_id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'private',
            'post_title' => implode(' - ', $title_parts),
            'post_content' => wp_json_encode([
                'normalized' => $normalized,
                'lead' => $lead,
                'event' => $event,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ], true);

        if (is_wp_error($post_id) || (int) $post_id <= 0) {
            return 0;
        }

        $post_id = (int) $post_id;
        update_post_meta($post_id, '_flacso_meta_leadgen_id', $leadgen_id);
        update_post_meta($post_id, '_flacso_meta_form_id', $form_id ?: self::extract_form_id($lead['form_id'] ?? ''));
        update_post_meta($post_id, '_flacso_meta_lead_status', 'imported');
        update_post_meta($post_id, '_flacso_meta_lead_email', sanitize_email((string) ($normalized['correo'] ?? '')));
        update_post_meta($post_id, '_flacso_meta_lead_created_time', (string) ($lead['created_time'] ?? ''));
        update_post_meta($post_id, '_flacso_meta_lead_normalized', wp_json_encode($normalized));
        update_post_meta($post_id, '_flacso_meta_lead_raw', wp_json_encode($lead));

        return $post_id;
    }

    private static function store_failed_lead(string $leadgen_id, string $form_id, array $event, string $error): void {
        $post_id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'private',
            'post_title' => 'Lead Meta fallido - ' . $leadgen_id,
            'post_content' => wp_json_encode([
                'event' => $event,
                'error' => $error,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ], true);

        if (!is_wp_error($post_id) && (int) $post_id > 0) {
            update_post_meta((int) $post_id, '_flacso_meta_leadgen_id', $leadgen_id);
            update_post_meta((int) $post_id, '_flacso_meta_form_id', $form_id);
            update_post_meta((int) $post_id, '_flacso_meta_lead_status', 'failed');
            update_post_meta((int) $post_id, '_flacso_meta_lead_error', $error);
        }
    }

    private static function forward_lead(array $normalized, array $settings): array {
        if (empty($settings['forward_to_webhook'])) {
            return ['ok' => false, 'skipped' => true, 'error' => 'forwarding_disabled'];
        }

        if (function_exists('fc_build_info_request_webhook_payload') && function_exists('fc_dispatch_info_request_webhook')) {
            $payload = fc_build_info_request_webhook_payload($normalized);
            $payload['source'] = 'Meta Lead Ads';
            $payload['origen'] = 'Meta Lead Ads';
            $payload['meta_leadgen_id'] = $normalized['meta_leadgen_id'] ?? '';
            $payload['meta_form_id'] = $normalized['meta_form_id'] ?? '';
            $payload['meta_campaign_id'] = $normalized['meta_campaign_id'] ?? '';
            $payload['meta_campaign_name'] = $normalized['meta_campaign_name'] ?? '';
            $payload['campaign_provider'] = 'meta';
            $payload['campaign_source'] = $normalized['campaign_source'] ?? 'Meta Lead Ads';
            $payload['campaign_medium'] = $normalized['campaign_medium'] ?? 'lead_ad';
            $payload['campaign_name'] = $normalized['campaign_name'] ?? '';
            $payload['campaign_external_id'] = $normalized['campaign_external_id'] ?? '';
            $payload['campaign_content'] = $normalized['campaign_content'] ?? '';
            $payload['campaign_term'] = $normalized['campaign_term'] ?? '';

            return fc_dispatch_info_request_webhook($payload);
        }

        if (function_exists('fc_send_info_request_webhook')) {
            return fc_send_info_request_webhook($normalized);
        }

        return ['ok' => false, 'error' => 'webhook_helpers_unavailable'];
    }

    private static function send_initial_crm_event(array $normalized, string $leadgen_id): array {
        $meta_settings = self::get_meta_capi_settings();

        if (empty($meta_settings['enabled']) || empty($meta_settings['pixel_id']) || empty($meta_settings['access_token'])) {
            return [
                'ok' => false,
                'skipped' => true,
                'error' => 'meta_capi_not_configured',
            ];
        }

        $event = self::build_crm_conversion_lead_event($normalized, $leadgen_id);
        if (empty($event['user_data'])) {
            return [
                'ok' => false,
                'skipped' => true,
                'error' => 'missing_user_data',
            ];
        }

        $endpoint = sprintf(
            'https://graph.facebook.com/%s/%s/events',
            self::sanitize_graph_version((string) ($meta_settings['graph_version'] ?? 'v25.0')),
            rawurlencode((string) $meta_settings['pixel_id'])
        );
        $body = [
            'data' => [$event],
        ];

        if (!empty($meta_settings['test_event_code'])) {
            $body['test_event_code'] = (string) $meta_settings['test_event_code'];
        }

        $response = wp_remote_post(
            add_query_arg(['access_token' => (string) $meta_settings['access_token']], $endpoint),
            [
                'timeout' => 15,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => wp_json_encode($body),
            ]
        );

        if (is_wp_error($response)) {
            self::log_error('crm_capi_request_failed', $response->get_error_message());

            return [
                'ok' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code < 200 || $status_code >= 300) {
            $message = is_array($response_body) && !empty($response_body['error']['message'])
                ? (string) $response_body['error']['message']
                : sprintf('Meta devolvió HTTP %d al enviar el evento CRM.', $status_code);
            self::log_error('crm_capi_http_' . $status_code, $message);

            return [
                'ok' => false,
                'http_code' => $status_code,
                'error' => $message,
                'fbtrace_id' => is_array($response_body) ? (string) ($response_body['fbtrace_id'] ?? '') : '',
            ];
        }

        return [
            'ok' => true,
            'http_code' => $status_code,
            'events_received' => is_array($response_body) ? (int) ($response_body['events_received'] ?? 0) : 0,
            'fbtrace_id' => is_array($response_body) ? (string) ($response_body['fbtrace_id'] ?? '') : '',
            'event_name' => (string) $event['event_name'],
            'test_mode' => !empty($meta_settings['test_event_code']),
        ];
    }

    private static function build_crm_conversion_lead_event(array $normalized, string $leadgen_id): array {
        $event_name = (string) apply_filters('flacso_meta_conversion_leads_initial_event_name', 'initial_lead', $normalized);
        $event_name = self::sanitize_crm_event_name($event_name);
        $lead_event_source = (string) apply_filters(
            'flacso_meta_conversion_leads_event_source_name',
            'FLACSO Uruguay WordPress CRM',
            $normalized
        );

        $user_data = [];
        $clean_lead_id = self::sanitize_meta_id($leadgen_id);
        if (preg_match('/^\d{15,17}$/', $clean_lead_id)) {
            $user_data['lead_id'] = $clean_lead_id;
        }

        $hashed_fields = [
            'em' => self::hash_email((string) ($normalized['correo'] ?? '')),
            'ph' => self::hash_phone((string) ($normalized['telefono'] ?? '')),
            'fn' => self::hash_text((string) ($normalized['nombre'] ?? '')),
            'ln' => self::hash_text((string) ($normalized['apellido'] ?? '')),
            'country' => self::hash_text((string) ($normalized['pais'] ?? '')),
        ];

        foreach ($hashed_fields as $key => $hash) {
            if ($hash !== '') {
                $user_data[$key] = [$hash];
            }
        }

        return [
            'event_name' => $event_name !== '' ? $event_name : 'initial_lead',
            'event_time' => time(),
            'action_source' => 'system_generated',
            'user_data' => $user_data,
            'custom_data' => [
                'lead_event_source' => sanitize_text_field($lead_event_source) ?: 'FLACSO Uruguay WordPress CRM',
                'event_source' => 'crm',
                'programa' => sanitize_text_field((string) ($normalized['programa'] ?? '')),
                'meta_form_id' => self::sanitize_meta_id($normalized['meta_form_id'] ?? ''),
                'meta_campaign_id' => self::sanitize_meta_id($normalized['meta_campaign_id'] ?? ''),
                'meta_campaign_name' => sanitize_text_field((string) ($normalized['meta_campaign_name'] ?? '')),
                'meta_adset_id' => self::sanitize_meta_id($normalized['meta_adset_id'] ?? ''),
                'meta_ad_id' => self::sanitize_meta_id($normalized['meta_ad_id'] ?? ''),
            ],
        ];
    }

    private static function get_meta_capi_settings(): array {
        $settings = [
            'enabled' => false,
            'pixel_id' => '',
            'access_token' => '',
            'test_event_code' => '',
            'graph_version' => 'v25.0',
        ];

        if (class_exists('FLACSO_Integrations_Settings') && is_callable(['FLACSO_Integrations_Settings', 'get_meta_settings'])) {
            $meta_settings = FLACSO_Integrations_Settings::get_meta_settings();
            $settings['enabled'] = !empty($meta_settings['enabled']);
            $settings['pixel_id'] = (string) ($meta_settings['pixel_id'] ?? '');
            $settings['access_token'] = (string) ($meta_settings['access_token'] ?? '');
            $settings['test_event_code'] = (string) ($meta_settings['test_event_code'] ?? '');
        }

        if (class_exists('FLACSO_Integrations_Settings') && is_callable(['FLACSO_Integrations_Settings', 'get_meta_leads_settings'])) {
            $lead_settings = FLACSO_Integrations_Settings::get_meta_leads_settings();
            $settings['graph_version'] = (string) ($lead_settings['graph_version'] ?? 'v25.0');
        }

        return $settings;
    }

    private static function sanitize_crm_event_name(string $value): string {
        $value = trim($value);
        $value = preg_replace('/[^A-Za-z0-9_\-\s]/', '', $value);

        return is_string($value) ? substr($value, 0, 64) : '';
    }

    private static function hash_email(string $value): string {
        $value = strtolower(trim($value));

        return is_email($value) ? hash('sha256', $value) : '';
    }

    private static function hash_phone(string $value): string {
        $value = preg_replace('/\D+/', '', $value);

        return $value !== '' ? hash('sha256', $value) : '';
    }

    private static function hash_text(string $value): string {
        $value = strtolower(trim($value));

        return $value !== '' ? hash('sha256', $value) : '';
    }

    private static function find_lead_post_id(string $leadgen_id): int {
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_flacso_meta_leadgen_id',
                    'value' => $leadgen_id,
                ],
                [
                    'key' => '_flacso_meta_lead_status',
                    'value' => 'imported',
                ],
            ],
            'no_found_rows' => true,
        ]);

        return !empty($posts[0]) ? (int) $posts[0] : 0;
    }

    private static function get_settings(): array {
        if (class_exists('FLACSO_Integrations_Settings')) {
            return FLACSO_Integrations_Settings::get_meta_leads_settings();
        }

        return [
            'enabled' => false,
            'verify_token' => '',
            'page_access_token' => '',
            'app_secret' => '',
            'page_id' => '',
            'form_ids' => [],
            'offer_field' => 'programa',
            'graph_version' => 'v25.0',
            'forward_to_webhook' => false,
        ];
    }

    private static function is_valid_signature(WP_REST_Request $request, string $app_secret): bool {
        if ($app_secret === '') {
            return true;
        }

        $signature = (string) $request->get_header('x_hub_signature_256');
        if ($signature === '') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->get_body(), $app_secret);

        return hash_equals($expected, $signature);
    }

    private static function lead_fields_to_map($field_data): array {
        $map = [];
        if (!is_array($field_data)) {
            return $map;
        }

        foreach ($field_data as $field) {
            if (!is_array($field) || empty($field['name'])) {
                continue;
            }

            $name = sanitize_key((string) $field['name']);
            $values = isset($field['values']) && is_array($field['values']) ? $field['values'] : [];
            $map[$name] = sanitize_text_field((string) ($values[0] ?? ''));
        }

        return $map;
    }

    private static function pick_field(array $fields, array $names): string {
        foreach ($names as $name) {
            $key = sanitize_key((string) $name);
            if (!empty($fields[$key])) {
                return (string) $fields[$key];
            }
        }

        return '';
    }

    private static function split_full_name(string $full_name): array {
        $parts = preg_split('/\s+/', trim($full_name)) ?: [];
        if (count($parts) <= 1) {
            return [$full_name, ''];
        }

        $first_name = array_shift($parts);

        return [(string) $first_name, implode(' ', $parts)];
    }

    private static function normalize_meta_created_time(string $created_time): string {
        if ($created_time === '') {
            return current_time('c');
        }

        $timestamp = strtotime($created_time);
        if (!$timestamp) {
            return current_time('c');
        }

        return wp_date('c', $timestamp, wp_timezone());
    }

    private static function extract_form_id($form_id): string {
        if (is_array($form_id) && isset($form_id['id'])) {
            return self::sanitize_meta_id($form_id['id']);
        }

        return self::sanitize_meta_id($form_id);
    }

    private static function sanitize_meta_id($value): string {
        return preg_replace('/[^0-9]/', '', (string) $value) ?: '';
    }

    private static function sanitize_graph_version(string $version): string {
        return preg_match('/^v[0-9]+\.[0-9]+$/', $version) ? $version : 'v25.0';
    }
}

FLACSO_Meta_Leads_Webhook::init();
