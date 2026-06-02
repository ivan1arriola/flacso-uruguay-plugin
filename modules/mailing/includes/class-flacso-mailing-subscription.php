<?php

if (!defined('ABSPATH')) {
    exit;
}

class Flacso_Mailing_Subscription {
    private const SHORTCODE = 'flacso_mailing_form';
    private const FORM_ACTION = 'flacso_mailing_subscribe';
    private const UNSUBSCRIBE_ACTION = 'flacso_mailing_unsubscribe';
    private const SCRIPT_HANDLE = 'flacso-mailing-form-script';
    private const STATUS_QUERY_ARG = 'flacso_mailing_status';
    private const FORM_QUERY_ARG = 'flacso_mailing_form';
    private const NONCE_ACTION = 'flacso_mailing_subscribe';
    private const NONCE_FIELD = 'flacso_mailing_nonce';
    private const EMAIL_LOGO_URL = 'https://flacso.edu.uy/wp-content/uploads/2026/04/cropped-flacso_20_anos_horizontal_azul.png';
    private const CONTACT_PROPERTY_DEFINITIONS = [
        'nombre' => [
            'Datatype' => 'str',
            'NameSpace' => 'static',
        ],
        'apellido' => [
            'Datatype' => 'str',
            'NameSpace' => 'static',
        ],
        'profesion' => [
            'Datatype' => 'str',
            'NameSpace' => 'static',
        ],
        'institucion' => [
            'Datatype' => 'str',
            'NameSpace' => 'static',
        ],
        'pais' => [
            'Datatype' => 'str',
            'NameSpace' => 'static',
        ],
    ];

    public static function init(): void {
        add_action('init', [self::class, 'register_shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'register_assets']);
        add_action('admin_post_' . self::FORM_ACTION, [self::class, 'handle_submission']);
        add_action('admin_post_nopriv_' . self::FORM_ACTION, [self::class, 'handle_submission']);
        add_action('admin_post_' . self::UNSUBSCRIBE_ACTION, [self::class, 'handle_unsubscribe_request']);
        add_action('admin_post_nopriv_' . self::UNSUBSCRIBE_ACTION, [self::class, 'handle_unsubscribe_request']);
        add_action('wp_ajax_' . self::FORM_ACTION, [self::class, 'handle_ajax_submission']);
        add_action('wp_ajax_nopriv_' . self::FORM_ACTION, [self::class, 'handle_ajax_submission']);
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

        wp_register_script(
            self::SCRIPT_HANDLE,
            FLACSO_MAILING_MODULE_URL . 'assets/js/flacso-mailing.js',
            [],
            self::asset_version('assets/js/flacso-mailing.js'),
            false
        );

        if (function_exists('wp_script_add_data')) {
            wp_script_add_data(self::SCRIPT_HANDLE, 'strategy', 'defer');
        }

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'flacsoMailingSettings',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'submittingLabel' => __('Enviando...', 'flacso-uruguay'),
            ]
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
                'last_name_placeholder' => __('Tu apellido', 'flacso-uruguay'),
                'email_placeholder' => __('tu@email.com', 'flacso-uruguay'),
                'profession_placeholder' => __('Tu profesión', 'flacso-uruguay'),
                'institution_placeholder' => __('Tu institución', 'flacso-uruguay'),
                'country_placeholder' => __('Tu país', 'flacso-uruguay'),
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
        wp_enqueue_script(self::SCRIPT_HANDLE);

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
            'form_title' => '',
            'form_description' => '',
            'button_label' => __('Suscribirme', 'flacso-uruguay'),
            'consent_text' => __('Acepto recibir novedades y comunicaciones institucionales de FLACSO Uruguay.', 'flacso-uruguay'),
            'name_placeholder' => __('Tu nombre', 'flacso-uruguay'),
            'last_name_placeholder' => __('Tu apellido', 'flacso-uruguay'),
            'email_placeholder' => __('tu@email.com', 'flacso-uruguay'),
            'profession_placeholder' => __('Tu profesión', 'flacso-uruguay'),
            'institution_placeholder' => __('Tu institución', 'flacso-uruguay'),
            'country_placeholder' => __('Tu país', 'flacso-uruguay'),
            'extra_classes' => '',
            'show_name_field' => true,
            'show_last_name_field' => true,
            'show_profession_field' => true,
            'show_institution_field' => true,
            'show_country_field' => true,
        ];
        $args = wp_parse_args($args, $defaults);
        $args['button_label'] = trim((string) $args['button_label']) !== '' ? (string) $args['button_label'] : $defaults['button_label'];
        $args['consent_text'] = trim((string) $args['consent_text']) !== '' ? (string) $args['consent_text'] : $defaults['consent_text'];
        $args['name_placeholder'] = trim((string) $args['name_placeholder']) !== '' ? (string) $args['name_placeholder'] : $defaults['name_placeholder'];
        $args['last_name_placeholder'] = trim((string) $args['last_name_placeholder']) !== '' ? (string) $args['last_name_placeholder'] : $defaults['last_name_placeholder'];
        $args['email_placeholder'] = trim((string) $args['email_placeholder']) !== '' ? (string) $args['email_placeholder'] : $defaults['email_placeholder'];
        $args['profession_placeholder'] = trim((string) $args['profession_placeholder']) !== '' ? (string) $args['profession_placeholder'] : $defaults['profession_placeholder'];
        $args['institution_placeholder'] = trim((string) $args['institution_placeholder']) !== '' ? (string) $args['institution_placeholder'] : $defaults['institution_placeholder'];
        $args['country_placeholder'] = trim((string) $args['country_placeholder']) !== '' ? (string) $args['country_placeholder'] : $defaults['country_placeholder'];
        $args['form_title'] = trim((string) $args['form_title']);
        $args['form_description'] = trim((string) $args['form_description']);

        $form_id = sanitize_key((string) $args['form_id']);
        if ($form_id === '') {
            $form_id = 'general';
        }

        $show_first_name_field = !empty($args['show_name_field']);
        $show_last_name_field = !empty($args['show_last_name_field']);
        $show_profession_field = !empty($args['show_profession_field']);
        $show_institution_field = !empty($args['show_institution_field']);
        $show_country_field = !empty($args['show_country_field']);

        $grid_classes = [];
        if ($show_first_name_field || $show_last_name_field || $show_profession_field || $show_institution_field || $show_country_field) {
            $grid_classes[] = 'has-multiple-fields';
        }
        if (empty($grid_classes)) {
            $grid_classes[] = 'is-email-only';
        }

        $anchor = sanitize_title((string) $args['anchor']);
        $redirect_url = self::get_redirect_url((string) $args['redirect_url'], $anchor);
        $notice_html = self::get_status_notice($form_id);
        $extra_classes = sanitize_html_class((string) $args['extra_classes']);

        ob_start();
        ?>
        <div class="flacso-mailing-form-shell <?php echo esc_attr($extra_classes); ?>">
            <?php if ($args['form_title'] !== '' || $args['form_description'] !== ''): ?>
                <div class="flacso-mailing-form-intro">
                    <?php if ($args['form_title'] !== ''): ?>
                        <p class="flacso-mailing-form-intro-title"><?php echo esc_html($args['form_title']); ?></p>
                    <?php endif; ?>
                    <?php if ($args['form_description'] !== ''): ?>
                        <p class="flacso-mailing-form-intro-description"><?php echo esc_html($args['form_description']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="flacso-mailing-notice-slot" data-mailing-notice aria-live="polite"><?php echo $notice_html; ?></div>
            <form
                class="flacso-mailing-form"
                action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                method="post"
                data-mailing-form
                data-mailing-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                novalidate>
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

                <div class="flacso-mailing-form-grid <?php echo esc_attr(implode(' ', $grid_classes)); ?>">
                    <?php if ($show_first_name_field): ?>
                        <div class="flacso-mailing-field">
                            <label for="<?php echo esc_attr($form_id . '-name'); ?>"><?php esc_html_e('Nombre', 'flacso-uruguay'); ?></label>
                            <input
                                id="<?php echo esc_attr($form_id . '-name'); ?>"
                                type="text"
                                name="flacso_mailing_name"
                                maxlength="120"
                                required
                                placeholder="<?php echo esc_attr((string) $args['name_placeholder']); ?>">
                        </div>
                    <?php endif; ?>

                    <?php if ($show_last_name_field): ?>
                        <div class="flacso-mailing-field">
                            <label for="<?php echo esc_attr($form_id . '-last-name'); ?>"><?php esc_html_e('Apellido', 'flacso-uruguay'); ?></label>
                            <input
                                id="<?php echo esc_attr($form_id . '-last-name'); ?>"
                                type="text"
                                name="flacso_mailing_last_name"
                                maxlength="120"
                                required
                                placeholder="<?php echo esc_attr((string) $args['last_name_placeholder']); ?>">
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

                    <?php if ($show_profession_field): ?>
                        <div class="flacso-mailing-field">
                            <label for="<?php echo esc_attr($form_id . '-profession'); ?>"><?php esc_html_e('Profesión', 'flacso-uruguay'); ?></label>
                            <input
                                id="<?php echo esc_attr($form_id . '-profession'); ?>"
                                type="text"
                                name="flacso_mailing_profession"
                                maxlength="160"
                                placeholder="<?php echo esc_attr((string) $args['profession_placeholder']); ?>">
                        </div>
                    <?php endif; ?>

                    <?php if ($show_institution_field): ?>
                        <div class="flacso-mailing-field">
                            <label for="<?php echo esc_attr($form_id . '-institution'); ?>"><?php esc_html_e('Institución', 'flacso-uruguay'); ?></label>
                            <input
                                id="<?php echo esc_attr($form_id . '-institution'); ?>"
                                type="text"
                                name="flacso_mailing_institution"
                                maxlength="180"
                                placeholder="<?php echo esc_attr((string) $args['institution_placeholder']); ?>">
                        </div>
                    <?php endif; ?>

                    <?php if ($show_country_field): ?>
                        <div class="flacso-mailing-field">
                            <label for="<?php echo esc_attr($form_id . '-country'); ?>"><?php esc_html_e('País', 'flacso-uruguay'); ?></label>
                            <select
                                id="<?php echo esc_attr($form_id . '-country'); ?>"
                                name="flacso_mailing_country"
                                autocomplete="country-name">
                                <option value=""><?php echo esc_html((string) $args['country_placeholder']); ?></option>
                                <?php foreach (self::get_country_options() as $country_value => $country_label): ?>
                                    <option value="<?php echo esc_attr($country_value); ?>"><?php echo esc_html($country_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <label class="flacso-mailing-consent">
                    <input type="checkbox" name="flacso_mailing_consent" value="1" required>
                    <span><?php echo esc_html((string) $args['consent_text']); ?></span>
                </label>

                <div class="flacso-mailing-actions">
                    <button
                        type="submit"
                        class="flacso-btn flacso-btn-primary flacso-btn-anim flacso-mailing-submit"
                        data-mailing-submit
                        data-default-label="<?php echo esc_attr((string) $args['button_label']); ?>">
                        <?php echo esc_html((string) $args['button_label']); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php

        return ob_get_clean();
    }

    public static function handle_submission(): void {
        $result = self::process_submission_data($_POST);

        self::redirect_with_status($result['redirect_url'], $result['status'], $result['form_id']);
    }

    public static function handle_ajax_submission(): void {
        $result = self::process_submission_data($_POST);
        $payload = [
            'status' => $result['status'],
            'type' => $result['type'],
            'message' => $result['message'],
            'noticeHtml' => self::render_notice($result['type'], $result['message']),
            'formId' => $result['form_id'],
        ];

        if (!empty($result['success'])) {
            wp_send_json_success($payload);
        }

        wp_send_json_error($payload, 400);
    }

    public static function handle_unsubscribe_request(): void {
        $email = sanitize_email((string) wp_unslash($_GET['email'] ?? ''));
        $token = sanitize_text_field((string) wp_unslash($_GET['token'] ?? ''));

        if ($email === '' || !is_email($email) || !hash_equals(self::build_unsubscribe_token($email), $token)) {
            self::render_unsubscribe_page(
                'error',
                __('No pudimos validar la solicitud de baja. Revisá el enlace o volvé a intentarlo desde el último correo recibido.', 'flacso-uruguay')
            );
        }

        $result = self::unsubscribe_contact($email);
        if (!empty($result['success'])) {
            self::render_unsubscribe_page(
                'success',
                __('Tu baja de la lista de difusión quedó confirmada. Ya no vas a recibir nuevos envíos desde este canal.', 'flacso-uruguay')
            );
        }

        self::render_unsubscribe_page(
            'error',
            __('No pudimos completar la baja en este momento. Probá nuevamente desde el enlace del correo o escribinos si el problema continúa.', 'flacso-uruguay')
        );
    }

    private static function process_submission_data(array $raw_post): array {
        $form_id = sanitize_key((string) wp_unslash($raw_post['flacso_mailing_form_id'] ?? 'general'));
        if ($form_id === '') {
            $form_id = 'general';
        }

        $redirect_url = isset($raw_post['redirect_to']) ? (string) wp_unslash($raw_post['redirect_to']) : '';
        $redirect_url = self::get_redirect_url($redirect_url);

        if (!empty($raw_post['flacso_mailing_company'])) {
            return self::build_submission_result('success', $form_id, $redirect_url, true);
        }

        if (
            !isset($raw_post[self::NONCE_FIELD]) ||
            !wp_verify_nonce((string) wp_unslash($raw_post[self::NONCE_FIELD]), self::NONCE_ACTION)
        ) {
            return self::build_submission_result('security', $form_id, $redirect_url, false);
        }

        $email = sanitize_email((string) wp_unslash($raw_post['flacso_mailing_email'] ?? ''));
        $first_name = sanitize_text_field((string) wp_unslash($raw_post['flacso_mailing_name'] ?? ''));
        $last_name = sanitize_text_field((string) wp_unslash($raw_post['flacso_mailing_last_name'] ?? ''));
        $profession = sanitize_text_field((string) wp_unslash($raw_post['flacso_mailing_profession'] ?? ''));
        $institution = sanitize_text_field((string) wp_unslash($raw_post['flacso_mailing_institution'] ?? ''));
        $country = sanitize_text_field((string) wp_unslash($raw_post['flacso_mailing_country'] ?? ''));
        $has_consent = !empty($raw_post['flacso_mailing_consent']);

        if (!$has_consent || $email === '' || !is_email($email)) {
            return self::build_submission_result('invalid', $form_id, $redirect_url, false);
        }

        if (!self::is_configured()) {
            return self::build_submission_result('not_configured', $form_id, $redirect_url, false);
        }

        $display_name = trim($first_name . ' ' . $last_name);
        if ($display_name === '') {
            $display_name = $first_name;
        }

        $properties = [];
        if ($first_name !== '') {
            $properties['nombre'] = $first_name;
        }
        if ($last_name !== '') {
            $properties['apellido'] = $last_name;
        }
        if ($profession !== '') {
            $properties['profesion'] = $profession;
        }
        if ($institution !== '') {
            $properties['institucion'] = $institution;
        }
        if ($country !== '') {
            $properties['pais'] = $country;
        }

        $result = self::subscribe_contact($email, $display_name, $properties);
        if (!empty($result['success'])) {
            $confirmation_sent = self::send_confirmation_email($email, $display_name, $first_name);
            $status = $confirmation_sent ? 'success' : 'success_confirmation_warning';

            return self::build_submission_result($status, $form_id, $redirect_url, true);
        }

        return self::build_submission_result($result['code'] ?? 'error', $form_id, $redirect_url, false);
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

    private static function subscribe_contact(string $email, string $name, array $properties = []): array {
        $settings = self::get_mailjet_settings();
        if (!self::ensure_contact_properties(array_keys($properties))) {
            return [
                'success' => false,
                'code' => 'properties',
            ];
        }

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

        if (!empty($properties)) {
            $contact['Properties'] = $properties;
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

    private static function send_confirmation_email(string $email, string $display_name = '', string $first_name = ''): bool {
        $settings = self::get_mailjet_settings();
        if ($settings['api_key'] === '' || $settings['secret_key'] === '') {
            return false;
        }

        $sender_email = sanitize_email((string) ($settings['sender_email'] ?? ''));
        if ($sender_email === '') {
            $sender_email = sanitize_email((string) get_option('admin_email'));
        }

        if ($sender_email === '') {
            return false;
        }

        $sender_name = trim((string) ($settings['sender_name'] ?? ''));
        if ($sender_name === '') {
            $sender_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        }

        $recipient = [
            'Email' => $email,
        ];

        if ($display_name !== '') {
            $recipient['Name'] = $display_name;
        }

        $subject = __('Confirmación de suscripción a la lista de difusión de FLACSO Uruguay', 'flacso-uruguay');
        $greeting_name = $first_name !== '' ? $first_name : $display_name;
        $unsubscribe_url = self::build_unsubscribe_url($email);
        $text_part = self::build_confirmation_text($greeting_name, $unsubscribe_url);
        $html_part = self::build_confirmation_html($greeting_name, $unsubscribe_url);

        $response = wp_remote_post(
            'https://api.mailjet.com/v3.1/send',
            [
                'timeout' => 15,
                'headers' => [
                    'Authorization' => self::get_mailjet_auth_header($settings),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => wp_json_encode(
                    [
                        'Messages' => [
                            [
                                'From' => [
                                    'Email' => $sender_email,
                                    'Name' => $sender_name,
                                ],
                                'To' => [$recipient],
                                'Subject' => $subject,
                                'TextPart' => $text_part,
                                'HTMLPart' => $html_part,
                            ],
                        ],
                    ]
                ),
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);

        return $status_code >= 200 && $status_code < 300;
    }

    private static function unsubscribe_contact(string $email): array {
        $settings = self::get_mailjet_settings();
        $endpoint = sprintf(
            'https://api.mailjet.com/v3/REST/contactslist/%s/managemanycontacts',
            rawurlencode($settings['list_id'])
        );

        $response = wp_remote_post(
            $endpoint,
            [
                'timeout' => 15,
                'headers' => [
                    'Authorization' => self::get_mailjet_auth_header($settings),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => wp_json_encode(
                    [
                        'Action' => 'unsub',
                        'Contacts' => [
                            [
                                'Email' => $email,
                            ],
                        ],
                    ]
                ),
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

        if ($status_code === 401 || $status_code === 403) {
            return ['success' => false, 'code' => 'auth'];
        }

        if ($status_code === 404) {
            return ['success' => false, 'code' => 'list'];
        }

        return ['success' => false, 'code' => 'error'];
    }

    private static function build_confirmation_text(string $greeting_name = '', string $unsubscribe_url = ''): string {
        $lines = [];
        $lines[] = $greeting_name !== ''
            ? sprintf(__('Hola %s,', 'flacso-uruguay'), $greeting_name)
            : __('Hola,', 'flacso-uruguay');
        $lines[] = '';
        $lines[] = __('Tu suscripción a la lista de difusión de FLACSO Uruguay quedó confirmada correctamente.', 'flacso-uruguay');
        $lines[] = __('A partir de ahora vas a recibir novedades, actividades y comunicaciones institucionales en tu correo.', 'flacso-uruguay');
        $lines[] = '';
        $lines[] = __('Si en algún momento querés darte de baja, podés hacerlo desde este enlace:', 'flacso-uruguay');
        if ($unsubscribe_url !== '') {
            $lines[] = $unsubscribe_url;
        }
        $lines[] = '';
        $lines[] = __('Gracias por sumarte.', 'flacso-uruguay');
        $lines[] = __('FLACSO Uruguay', 'flacso-uruguay');

        return implode("\n", $lines);
    }

    private static function build_confirmation_html(string $greeting_name = '', string $unsubscribe_url = ''): string {
        $greeting = $greeting_name !== ''
            ? sprintf(__('Hola %s,', 'flacso-uruguay'), esc_html($greeting_name))
            : esc_html__('Hola,', 'flacso-uruguay');

        $intro = esc_html__('Tu suscripción a la lista de difusión de FLACSO Uruguay quedó confirmada correctamente.', 'flacso-uruguay');
        $body = esc_html__('A partir de ahora vas a recibir novedades, actividades y comunicaciones institucionales en tu correo.', 'flacso-uruguay');
        $footer = esc_html__('Si en algún momento querés dejar de recibir estos mensajes, podés darte de baja desde el botón que aparece más abajo.', 'flacso-uruguay');
        $closing = esc_html__('Gracias por sumarte.', 'flacso-uruguay');
        $signature = esc_html__('FLACSO Uruguay', 'flacso-uruguay');
        $unsubscribe_button = $unsubscribe_url !== ''
            ? '<p style="margin:0 0 24px;"><a href="' . esc_url($unsubscribe_url) . '" style="display:inline-block;padding:14px 22px;border-radius:999px;background:#153e75;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">' . esc_html__('Darme de baja de la lista de difusión', 'flacso-uruguay') . '</a></p>'
            : '';
        $unsubscribe_link = $unsubscribe_url !== ''
            ? '<p style="margin:0;font-size:12px;line-height:1.6;color:#66758f;">' . esc_html__('Si el botón no funciona, copiá y pegá este enlace en tu navegador:', 'flacso-uruguay') . '<br><a href="' . esc_url($unsubscribe_url) . '" style="color:#153e75;word-break:break-all;">' . esc_html($unsubscribe_url) . '</a></p>'
            : '';

        return '
            <div style="margin:0;padding:32px 20px;background:#f5f7fb;font-family:Arial,sans-serif;color:#10213a;">
                <div style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #dbe5f1;">
                    <div style="padding:28px 32px;background:linear-gradient(135deg,#14233f 0%,#274b7a 100%);color:#ffffff;">
                        <img src="' . esc_url(self::EMAIL_LOGO_URL) . '" alt="' . esc_attr__('FLACSO Uruguay 20 años', 'flacso-uruguay') . '" style="display:block;max-width:260px;width:100%;height:auto;margin:0 0 18px;">
                        <h1 style="margin:0;font-size:28px;line-height:1.15;">' . esc_html__('Suscripción confirmada', 'flacso-uruguay') . '</h1>
                    </div>
                    <div style="padding:30px 32px 34px;">
                        <p style="margin:0 0 16px;font-size:17px;line-height:1.6;">' . $greeting . '</p>
                        <p style="margin:0 0 14px;font-size:16px;line-height:1.7;">' . $intro . '</p>
                        <p style="margin:0 0 14px;font-size:16px;line-height:1.7;">' . $body . '</p>
                        <p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#4a5b78;">' . $footer . '</p>
                        ' . $unsubscribe_button . '
                        ' . $unsubscribe_link . '
                        <p style="margin:0;font-size:16px;line-height:1.7;font-weight:700;">' . $closing . '<br>' . $signature . '</p>
                    </div>
                </div>
            </div>';
    }

    private static function build_unsubscribe_url(string $email): string {
        $normalized_email = strtolower(trim($email));

        return add_query_arg(
            [
                'action' => self::UNSUBSCRIBE_ACTION,
                'email' => $normalized_email,
                'token' => self::build_unsubscribe_token($normalized_email),
            ],
            admin_url('admin-post.php')
        );
    }

    private static function build_unsubscribe_token(string $email): string {
        $normalized_email = strtolower(trim($email));
        $settings = self::get_mailjet_settings();

        return hash_hmac('sha256', $normalized_email . '|' . $settings['list_id'], wp_salt('auth'));
    }

    private static function render_unsubscribe_page(string $type, string $message): void {
        $type = $type === 'success' ? 'success' : 'error';
        $title = $type === 'success'
            ? __('Baja confirmada', 'flacso-uruguay')
            : __('No pudimos completar la baja', 'flacso-uruguay');
        $accent = $type === 'success' ? '#166534' : '#991b1b';
        $background = $type === 'success' ? '#ecfdf5' : '#fef2f2';
        $border = $type === 'success' ? 'rgba(22, 101, 52, 0.12)' : 'rgba(153, 27, 27, 0.12)';

        nocache_headers();
        status_header($type === 'success' ? 200 : 400);
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($title); ?></title>
        </head>
        <body style="margin:0;padding:32px 20px;background:#f5f7fb;font-family:Arial,sans-serif;color:#10213a;">
            <div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #dbe5f1;">
                <div style="padding:28px 32px;background:linear-gradient(135deg,#14233f 0%,#274b7a 100%);">
                    <img src="<?php echo esc_url(self::EMAIL_LOGO_URL); ?>" alt="<?php esc_attr_e('FLACSO Uruguay 20 años', 'flacso-uruguay'); ?>" style="display:block;max-width:270px;width:100%;height:auto;">
                </div>
                <div style="padding:30px 32px 34px;">
                    <div style="margin:0 0 18px;padding:16px 18px;border-radius:18px;background:<?php echo esc_attr($background); ?>;border:1px solid <?php echo esc_attr($border); ?>;color:<?php echo esc_attr($accent); ?>;font-size:15px;font-weight:700;line-height:1.6;">
                        <?php echo esc_html($message); ?>
                    </div>
                    <h1 style="margin:0 0 14px;font-size:28px;line-height:1.18;"><?php echo esc_html($title); ?></h1>
                    <p style="margin:0 0 18px;font-size:16px;line-height:1.7;color:#46546f;"><?php esc_html_e('Si querés volver a suscribirte más adelante, podés hacerlo directamente desde la portada de FLACSO Uruguay.', 'flacso-uruguay'); ?></p>
                    <p style="margin:0;"><a href="<?php echo esc_url(home_url('/')); ?>" style="display:inline-block;padding:14px 22px;border-radius:999px;background:#153e75;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;"><?php esc_html_e('Volver al sitio', 'flacso-uruguay'); ?></a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    private static function ensure_contact_properties(array $property_names): bool {
        $normalized_names = [];
        foreach ($property_names as $property_name) {
            $property_name = strtolower(trim((string) $property_name));
            if ($property_name === '' || !isset(self::CONTACT_PROPERTY_DEFINITIONS[$property_name])) {
                continue;
            }
            $normalized_names[] = $property_name;
        }

        if (empty($normalized_names)) {
            return true;
        }

        $existing_properties = self::get_existing_contact_properties();
        if ($existing_properties === null) {
            return false;
        }

        $created_any = false;
        foreach ($normalized_names as $property_name) {
            if (in_array($property_name, $existing_properties, true)) {
                continue;
            }

            if (!self::create_contact_property($property_name, self::CONTACT_PROPERTY_DEFINITIONS[$property_name])) {
                return false;
            }

            $existing_properties[] = $property_name;
            $created_any = true;
        }

        if ($created_any) {
            self::clear_contact_properties_cache();
        }

        return true;
    }

    private static function get_existing_contact_properties(bool $force_refresh = false): ?array {
        $settings = self::get_mailjet_settings();
        if ($settings['api_key'] === '' || $settings['secret_key'] === '') {
            return [];
        }

        $cache_key = self::get_contact_properties_cache_key($settings);
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $offset = 0;
        $limit = 1000;
        $properties = [];

        do {
            $response = wp_remote_get(
                add_query_arg(
                    [
                        'Limit' => $limit,
                        'Offset' => $offset,
                    ],
                    'https://api.mailjet.com/v3/REST/contactmetadata'
                ),
                [
                    'timeout' => 15,
                    'headers' => [
                        'Authorization' => self::get_mailjet_auth_header($settings),
                        'Accept' => 'application/json',
                    ],
                ]
            );

            if (is_wp_error($response)) {
                return null;
            }

            $status_code = (int) wp_remote_retrieve_response_code($response);
            if ($status_code < 200 || $status_code >= 300) {
                return null;
            }

            $body = wp_remote_retrieve_body($response);
            $decoded = json_decode($body, true);
            $page_items = isset($decoded['Data']) && is_array($decoded['Data']) ? $decoded['Data'] : [];
            foreach ($page_items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $name = strtolower(trim((string) ($item['Name'] ?? '')));
                if ($name === '') {
                    continue;
                }

                $properties[] = $name;
            }

            $page_count = count($page_items);
            $total = isset($decoded['Total']) ? absint($decoded['Total']) : 0;
            $offset += $page_count;
        } while ($page_count === $limit || ($total > 0 && $offset < $total));

        $properties = array_values(array_unique($properties));
        set_transient($cache_key, $properties, 10 * MINUTE_IN_SECONDS);

        return $properties;
    }

    private static function create_contact_property(string $property_name, array $definition): bool {
        $settings = self::get_mailjet_settings();
        $response = wp_remote_post(
            'https://api.mailjet.com/v3/REST/contactmetadata',
            [
                'timeout' => 15,
                'headers' => [
                    'Authorization' => self::get_mailjet_auth_header($settings),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => wp_json_encode(
                    [
                        'Name' => $property_name,
                        'Datatype' => $definition['Datatype'] ?? 'str',
                        'NameSpace' => $definition['NameSpace'] ?? 'static',
                    ]
                ),
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code >= 200 && $status_code < 300) {
            return true;
        }

        $existing_properties = self::get_existing_contact_properties(true);
        if (is_array($existing_properties) && in_array(strtolower($property_name), $existing_properties, true)) {
            return true;
        }

        return false;
    }

    private static function get_mailjet_auth_header(array $settings): string {
        return 'Basic ' . base64_encode($settings['api_key'] . ':' . $settings['secret_key']);
    }

    private static function get_contact_properties_cache_key(array $settings): string {
        return 'flacso_mailjet_contact_properties_v1_' . md5($settings['api_key'] . '|' . $settings['secret_key']);
    }

    private static function clear_contact_properties_cache(): void {
        delete_transient(self::get_contact_properties_cache_key(self::get_mailjet_settings()));
    }

    private static function get_country_options(): array {
        return [
            'Uruguay' => 'Uruguay',
            'Argentina' => 'Argentina',
            'Bolivia' => 'Bolivia',
            'Brasil' => 'Brasil',
            'Chile' => 'Chile',
            'Colombia' => 'Colombia',
            'Costa Rica' => 'Costa Rica',
            'Cuba' => 'Cuba',
            'Ecuador' => 'Ecuador',
            'El Salvador' => 'El Salvador',
            'Guatemala' => 'Guatemala',
            'Haití' => 'Haití',
            'Honduras' => 'Honduras',
            'México' => 'México',
            'Nicaragua' => 'Nicaragua',
            'Panamá' => 'Panamá',
            'Paraguay' => 'Paraguay',
            'Perú' => 'Perú',
            'República Dominicana' => 'República Dominicana',
            'Venezuela' => 'Venezuela',
            'Otro' => __('Otro', 'flacso-uruguay'),
        ];
    }

    private static function get_mailjet_settings(): array {
        if (class_exists('FLACSO_Integrations_Settings') && method_exists('FLACSO_Integrations_Settings', 'get_mailjet_settings')) {
            return FLACSO_Integrations_Settings::get_mailjet_settings();
        }

        return [
            'api_key' => trim((string) get_option('flacso_mailjet_api_key', '')),
            'secret_key' => trim((string) get_option('flacso_mailjet_secret_key', '')),
            'list_id' => trim((string) get_option('flacso_mailjet_list_id', '')),
            'sender_email' => sanitize_email((string) get_option('flacso_mailjet_sender_email', get_option('admin_email'))),
            'sender_name' => trim((string) get_option('flacso_mailjet_sender_name', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES))),
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

    private static function build_submission_result(string $status, string $form_id, string $redirect_url, bool $success): array {
        $payload = self::get_status_payload($status);

        return [
            'success' => $success,
            'status' => $status,
            'type' => $payload['type'],
            'message' => $payload['message'],
            'form_id' => $form_id,
            'redirect_url' => $redirect_url,
        ];
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
        $payload = self::get_status_payload($status);
        if ($payload === null) {
            return '';
        }

        return self::render_notice($payload['type'], $payload['message']);
    }

    private static function get_status_payload(string $status): ?array {
        $message_map = [
            'success' => [
                'type' => 'success',
                'message' => __('Tu suscripción a la lista de difusión quedó registrada correctamente.', 'flacso-uruguay'),
            ],
            'success_confirmation_warning' => [
                'type' => 'warning',
                'message' => __('La suscripción a la lista de difusión quedó registrada, pero no pudimos enviarte el correo de confirmación en este momento.', 'flacso-uruguay'),
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
            'properties' => [
                'type' => 'error',
                'message' => __('No pudimos preparar los campos personalizados en Mailjet. Revisá las credenciales e intentá otra vez.', 'flacso-uruguay'),
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
            return null;
        }

        return $message_map[$status];
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
