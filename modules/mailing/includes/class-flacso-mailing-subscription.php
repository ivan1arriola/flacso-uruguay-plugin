<?php

if (!defined('ABSPATH')) {
    exit;
}

class Flacso_Mailing_Subscription {
    private const SHORTCODE = 'flacso_mailing_form';
    private const FORM_ACTION = 'flacso_mailing_subscribe';
    private const STATUS_QUERY_ARG = 'flacso_mailing_status';
    private const FORM_QUERY_ARG = 'flacso_mailing_form';
    private const NONCE_ACTION = 'flacso_mailing_subscribe';
    private const NONCE_FIELD = 'flacso_mailing_nonce';

    public static function init(): void {
        add_action('init', [self::class, 'register_shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'register_assets']);
        add_action('admin_post_' . self::FORM_ACTION, [self::class, 'handle_submission']);
        add_action('admin_post_nopriv_' . self::FORM_ACTION, [self::class, 'handle_submission']);
    }

    public static function register_shortcode(): void {
        add_shortcode(self::SHORTCODE, [self::class, 'render_shortcode']);
    }

    public static function register_assets(): void {
        wp_register_style(
            'flacso-mailing-form',
            FLACSO_MAILING_MODULE_URL . 'assets/css/flacso-mailing.css',
            [],
            self::asset_version('assets/css/flacso-mailing.css')
        );
    }

    public static function render_shortcode($atts = []): string {
        $atts = shortcode_atts(
            [
                'form_id' => 'shortcode',
                'anchor' => '',
                'button_label' => __('Suscribirme', 'flacso-uruguay'),
                'consent_text' => __('Acepto recibir novedades y comunicaciones institucionales de FLACSO Uruguay.', 'flacso-uruguay'),
                'name_placeholder' => __('Tu nombre', 'flacso-uruguay'),
                'email_placeholder' => __('tu@email.com', 'flacso-uruguay'),
            ],
            $atts,
            self::SHORTCODE
        );

        return self::render_form($atts);
    }

    public static function render_form(array $args = []): string {
        if (!wp_style_is('flacso-mailing-form', 'registered')) {
            self::register_assets();
        }

        wp_enqueue_style('flacso-mailing-form');

        if (!self::is_configured()) {
            if (!current_user_can('manage_options')) {
                return '';
            }

            return self::render_notice(
                'warning',
                __('Configurá Mailjet en Ajustes > Integraciones FLACSO para habilitar este formulario.', 'flacso-uruguay')
            );
        }

        $defaults = [
            'form_id' => 'general',
            'anchor' => '',
            'redirect_url' => '',
            'button_label' => __('Suscribirme', 'flacso-uruguay'),
            'consent_text' => __('Acepto recibir novedades y comunicaciones institucionales de FLACSO Uruguay.', 'flacso-uruguay'),
            'name_placeholder' => __('Tu nombre', 'flacso-uruguay'),
            'email_placeholder' => __('tu@email.com', 'flacso-uruguay'),
            'extra_classes' => '',
            'show_name_field' => true,
        ];
        $args = wp_parse_args($args, $defaults);
        $args['button_label'] = trim((string) $args['button_label']) !== '' ? (string) $args['button_label'] : $defaults['button_label'];
        $args['consent_text'] = trim((string) $args['consent_text']) !== '' ? (string) $args['consent_text'] : $defaults['consent_text'];
        $args['name_placeholder'] = trim((string) $args['name_placeholder']) !== '' ? (string) $args['name_placeholder'] : $defaults['name_placeholder'];
        $args['email_placeholder'] = trim((string) $args['email_placeholder']) !== '' ? (string) $args['email_placeholder'] : $defaults['email_placeholder'];

        $form_id = sanitize_key((string) $args['form_id']);
        if ($form_id === '') {
            $form_id = 'general';
        }

        $anchor = sanitize_title((string) $args['anchor']);
        $redirect_url = self::get_redirect_url((string) $args['redirect_url'], $anchor);
        $notice_html = self::get_status_notice($form_id);
        $extra_classes = sanitize_html_class((string) $args['extra_classes']);

        ob_start();
        ?>
        <div class="flacso-mailing-form-shell <?php echo esc_attr($extra_classes); ?>">
            <?php echo $notice_html; ?>
            <form class="flacso-mailing-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" novalidate>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::FORM_ACTION); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_url); ?>">
                <input type="hidden" name="flacso_mailing_form_id" value="<?php echo esc_attr($form_id); ?>">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>

                <div class="flacso-mailing-honeypot" aria-hidden="true">
                    <label for="<?php echo esc_attr($form_id . '-company'); ?>"><?php esc_html_e('Empresa', 'flacso-uruguay'); ?></label>
                    <input
                        id="<?php echo esc_attr($form_id . '-company'); ?>"
                        type="text"
                        name="flacso_mailing_company"
                        value=""
                        tabindex="-1"
                        autocomplete="off">
                </div>

                <div class="flacso-mailing-form-grid <?php echo !empty($args['show_name_field']) ? 'has-name-field' : 'is-email-only'; ?>">
                    <?php if (!empty($args['show_name_field'])): ?>
                        <div class="flacso-mailing-field">
                            <label for="<?php echo esc_attr($form_id . '-name'); ?>"><?php esc_html_e('Nombre', 'flacso-uruguay'); ?></label>
                            <input
                                id="<?php echo esc_attr($form_id . '-name'); ?>"
                                type="text"
                                name="flacso_mailing_name"
                                maxlength="120"
                                placeholder="<?php echo esc_attr((string) $args['name_placeholder']); ?>">
                        </div>
                    <?php endif; ?>

                    <div class="flacso-mailing-field">
                        <label for="<?php echo esc_attr($form_id . '-email'); ?>"><?php esc_html_e('Correo electrónico', 'flacso-uruguay'); ?></label>
                        <input
                            id="<?php echo esc_attr($form_id . '-email'); ?>"
                            type="email"
                            name="flacso_mailing_email"
                            maxlength="190"
                            required
                            placeholder="<?php echo esc_attr((string) $args['email_placeholder']); ?>">
                    </div>
                </div>

                <label class="flacso-mailing-consent">
                    <input type="checkbox" name="flacso_mailing_consent" value="1" required>
                    <span><?php echo esc_html((string) $args['consent_text']); ?></span>
                </label>

                <div class="flacso-mailing-actions">
                    <button type="submit" class="flacso-btn flacso-btn-primary flacso-btn-anim flacso-mailing-submit">
                        <?php echo esc_html((string) $args['button_label']); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php

        return ob_get_clean();
    }

    public static function handle_submission(): void {
        $form_id = sanitize_key((string) wp_unslash($_POST['flacso_mailing_form_id'] ?? 'general'));
        if ($form_id === '') {
            $form_id = 'general';
        }

        $redirect_url = isset($_POST['redirect_to']) ? (string) wp_unslash($_POST['redirect_to']) : '';
        $redirect_url = self::get_redirect_url($redirect_url);

        if (!empty($_POST['flacso_mailing_company'])) {
            self::redirect_with_status($redirect_url, 'success', $form_id);
        }

        if (
            !isset($_POST[self::NONCE_FIELD]) ||
            !wp_verify_nonce((string) wp_unslash($_POST[self::NONCE_FIELD]), self::NONCE_ACTION)
        ) {
            self::redirect_with_status($redirect_url, 'security', $form_id);
        }

        $email = sanitize_email((string) wp_unslash($_POST['flacso_mailing_email'] ?? ''));
        $name = sanitize_text_field((string) wp_unslash($_POST['flacso_mailing_name'] ?? ''));
        $has_consent = !empty($_POST['flacso_mailing_consent']);

        if (!$has_consent || $email === '' || !is_email($email)) {
            self::redirect_with_status($redirect_url, 'invalid', $form_id);
        }

        if (!self::is_configured()) {
            self::redirect_with_status($redirect_url, 'not_configured', $form_id);
        }

        $result = self::subscribe_contact($email, $name);
        $status = !empty($result['success']) ? 'success' : ($result['code'] ?? 'error');

        self::redirect_with_status($redirect_url, $status, $form_id);
    }

    public static function is_configured(): bool {
        if (class_exists('FLACSO_Integrations_Settings') && method_exists('FLACSO_Integrations_Settings', 'is_mailjet_configured')) {
            return FLACSO_Integrations_Settings::is_mailjet_configured();
        }

        $settings = self::get_mailjet_settings();

        return $settings['api_key'] !== ''
            && $settings['secret_key'] !== ''
            && $settings['list_id'] !== '';
    }

    private static function subscribe_contact(string $email, string $name): array {
        $settings = self::get_mailjet_settings();
        $endpoint = sprintf(
            'https://api.mailjet.com/v3/REST/contactslist/%s/managemanycontacts',
            rawurlencode($settings['list_id'])
        );

        $contact = [
            'Email' => $email,
            'IsExcludedFromCampaigns' => 'false',
        ];

        if ($name !== '') {
            $contact['Name'] = $name;
        }

        $payload = [
            'Action' => 'addnoforce',
            'Contacts' => [$contact],
        ];

        $response = wp_remote_post(
            $endpoint,
            [
                'timeout' => 15,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($settings['api_key'] . ':' . $settings['secret_key']),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => wp_json_encode($payload),
            ]
        );

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'code' => 'network',
            ];
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code >= 200 && $status_code < 300) {
            return [
                'success' => true,
                'code' => 'success',
            ];
        }

        if ($status_code === 400) {
            return ['success' => false, 'code' => 'invalid'];
        }

        if ($status_code === 401 || $status_code === 403) {
            return ['success' => false, 'code' => 'auth'];
        }

        if ($status_code === 404) {
            return ['success' => false, 'code' => 'list'];
        }

        return ['success' => false, 'code' => 'error'];
    }

    private static function get_mailjet_settings(): array {
        if (class_exists('FLACSO_Integrations_Settings') && method_exists('FLACSO_Integrations_Settings', 'get_mailjet_settings')) {
            return FLACSO_Integrations_Settings::get_mailjet_settings();
        }

        return [
            'api_key' => trim((string) get_option('flacso_mailjet_api_key', '')),
            'secret_key' => trim((string) get_option('flacso_mailjet_secret_key', '')),
            'list_id' => trim((string) get_option('flacso_mailjet_list_id', '')),
        ];
    }

    private static function asset_version(string $relative_path): string {
        $full_path = FLACSO_MAILING_MODULE_PATH . ltrim($relative_path, '/');
        if (file_exists($full_path)) {
            return (string) filemtime($full_path);
        }

        return (string) FLACSO_MAILING_MODULE_VERSION;
    }

    private static function get_redirect_url(string $redirect_url = '', string $anchor = ''): string {
        $base_url = trim($redirect_url);
        if ($base_url !== '') {
            $validated = wp_validate_redirect($base_url, '');
            if ($validated !== '') {
                return self::clean_redirect_url($validated, $anchor);
            }
        }

        if (is_singular()) {
            $permalink = get_permalink();
            if (is_string($permalink) && $permalink !== '') {
                return self::clean_redirect_url($permalink, $anchor);
            }
        }

        return self::clean_redirect_url(home_url('/'), $anchor);
    }

    private static function clean_redirect_url(string $url, string $anchor = ''): string {
        $fragment = '';
        $hash_position = strpos($url, '#');
        if ($hash_position !== false) {
            $fragment = substr($url, $hash_position + 1);
            $url = substr($url, 0, $hash_position);
        }

        $url = remove_query_arg([self::STATUS_QUERY_ARG, self::FORM_QUERY_ARG], $url);
        $anchor = $anchor !== '' ? sanitize_title($anchor) : sanitize_title($fragment);

        if ($anchor !== '') {
            $url .= '#' . $anchor;
        }

        return $url;
    }

    private static function redirect_with_status(string $redirect_url, string $status, string $form_id): void {
        $fragment = '';
        $hash_position = strpos($redirect_url, '#');
        if ($hash_position !== false) {
            $fragment = substr($redirect_url, $hash_position + 1);
            $redirect_url = substr($redirect_url, 0, $hash_position);
        }

        $redirect_url = add_query_arg(
            [
                self::STATUS_QUERY_ARG => sanitize_key($status),
                self::FORM_QUERY_ARG => sanitize_key($form_id),
            ],
            $redirect_url
        );

        if ($fragment !== '') {
            $redirect_url .= '#' . sanitize_title($fragment);
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    private static function get_status_notice(string $form_id): string {
        if (!isset($_GET[self::STATUS_QUERY_ARG], $_GET[self::FORM_QUERY_ARG])) {
            return '';
        }

        $requested_form = sanitize_key((string) wp_unslash($_GET[self::FORM_QUERY_ARG]));
        if ($requested_form !== sanitize_key($form_id)) {
            return '';
        }

        $status = sanitize_key((string) wp_unslash($_GET[self::STATUS_QUERY_ARG]));
        $message_map = [
            'success' => [
                'type' => 'success',
                'message' => __('Tu suscripción al mailing quedó registrada correctamente.', 'flacso-uruguay'),
            ],
            'invalid' => [
                'type' => 'error',
                'message' => __('Ingresá un correo válido y aceptá recibir novedades para continuar.', 'flacso-uruguay'),
            ],
            'security' => [
                'type' => 'error',
                'message' => __('La sesión del formulario venció. Recargá la página e intentá nuevamente.', 'flacso-uruguay'),
            ],
            'not_configured' => [
                'type' => 'error',
                'message' => __('El formulario todavía no está configurado en Mailjet.', 'flacso-uruguay'),
            ],
            'auth' => [
                'type' => 'error',
                'message' => __('Mailjet rechazó las credenciales configuradas. Revisá la API Key y la Secret Key.', 'flacso-uruguay'),
            ],
            'list' => [
                'type' => 'error',
                'message' => __('La lista configurada en Mailjet no existe o no está disponible para esta API Key.', 'flacso-uruguay'),
            ],
            'network' => [
                'type' => 'error',
                'message' => __('No se pudo conectar con Mailjet en este momento. Probá nuevamente en unos minutos.', 'flacso-uruguay'),
            ],
            'error' => [
                'type' => 'error',
                'message' => __('No pudimos completar la suscripción. Revisá la configuración e intentá otra vez.', 'flacso-uruguay'),
            ],
        ];

        if (!isset($message_map[$status])) {
            return '';
        }

        return self::render_notice($message_map[$status]['type'], $message_map[$status]['message']);
    }

    private static function render_notice(string $type, string $message): string {
        $allowed_types = ['success', 'error', 'warning'];
        if (!in_array($type, $allowed_types, true)) {
            $type = 'warning';
        }

        return sprintf(
            '<div class="flacso-mailing-notice is-%1$s" role="status">%2$s</div>',
            esc_attr($type),
            esc_html($message)
        );
    }
}
