<?php

if (!defined('ABSPATH')) exit;

/** Formulario público de suscripción sincronizado con Mautic. */
final class Flacso_Mailing_Subscription {
    private const SHORTCODE = 'flacso_mailing_form';
    private const ACTION = 'flacso_mailing_subscribe';
    private const NONCE = 'flacso_mailing_subscribe';

    public static function init(): void {
        add_action('init', [self::class, 'register_shortcode']);
        add_action('admin_post_' . self::ACTION, [self::class, 'handle_submission']);
        add_action('admin_post_nopriv_' . self::ACTION, [self::class, 'handle_submission']);
        add_action('wp_ajax_' . self::ACTION, [self::class, 'handle_ajax_submission']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [self::class, 'handle_ajax_submission']);
    }

    public static function register_shortcode(): void { add_shortcode(self::SHORTCODE, [self::class, 'render_form']); }

    public static function render_form(array $args = []): string {
        static $instance = 0;
        $instance++;

        $args = shortcode_atts([
            'form_id' => '',
            'anchor' => '',
            'form_title' => '',
            'form_description' => '',
            'button_label' => '',
            'consent_text' => '',
            'extra_classes' => '',
        ], $args, self::SHORTCODE);

        $raw_form_id = trim((string) $args['form_id']);
        $slug = $raw_form_id !== '' ? sanitize_key($raw_form_id) : 'form_' . $instance;
        $uid = 'flacso_mailing_' . $slug;

        $button_label = trim((string) $args['button_label']);
        if ($button_label === '') {
            $button_label = __('Suscribirme', 'flacso-uruguay');
        }

        $consent_text = trim((string) $args['consent_text']);
        if ($consent_text === '') {
            $consent_text = __('Acepto recibir novedades y comunicaciones institucionales.', 'flacso-uruguay');
        }

        $form_title = trim((string) $args['form_title']);
        $form_description = trim((string) $args['form_description']);

        $extra_classes = array_filter(array_map(
            static fn(string $token): string => function_exists('sanitize_html_class')
                ? sanitize_html_class($token)
                : (string) preg_replace('/[^A-Za-z0-9_-]/', '', $token),
            preg_split('/\s+/', trim((string) $args['extra_classes'])) ?: []
        ));
        $shell_class = trim('flacso-mailing-form-shell ' . implode(' ', $extra_classes));

        $notices = self::status_notices();
        $status = self::read_status();
        $active_notice = $status !== '' ? ($notices[$status] ?? $notices['error']) : null;

        $email_id = $uid . '_email';
        $email_error_id = $email_id . '_error';
        $name_id = $uid . '_first_name';
        $name_error_id = $name_id . '_error';
        $consent_id = $uid . '_consent';
        $consent_error_id = $consent_id . '_error';

        $messages_payload = [];
        foreach ($notices as $code => $item) {
            $messages_payload[$code] = [
                'type' => $item['type'],
                'message' => $item['message'],
            ];
        }
        $messages_json = function_exists('wp_json_encode') ? (string) wp_json_encode($messages_payload) : (string) json_encode($messages_payload);

        $ajax_url = function_exists('admin_url') ? admin_url('admin-ajax.php') : '/wp-admin/admin-ajax.php';
        $post_url = function_exists('admin_url') ? admin_url('admin-post.php') : '/wp-admin/admin-post.php';
        $loading_text = __('Enviando…', 'flacso-uruguay');

        ob_start();
        self::render_assets_once();
        ?>
        <div class="<?php echo esc_attr($shell_class); ?>" data-messages="<?php echo esc_attr($messages_json); ?>">
            <?php if ($form_title !== ''): ?>
                <h3 class="flacso-mailing-form__title"><?php echo esc_html($form_title); ?></h3>
            <?php endif; ?>
            <?php if ($form_description !== ''): ?>
                <p class="flacso-mailing-form__description"><?php echo esc_html($form_description); ?></p>
            <?php endif; ?>

            <?php if (is_array($active_notice)): ?>
                <div class="flacso-mailing-notice <?php echo esc_attr('success' === $active_notice['type'] ? 'is-success' : 'is-error'); ?>" role="<?php echo esc_attr($active_notice['role']); ?>" aria-live="<?php echo esc_attr($active_notice['live']); ?>" data-flacso-mailing-feedback="1"><?php echo esc_html($active_notice['message']); ?></div>
            <?php else: ?>
                <div class="flacso-mailing-notice" role="status" aria-live="polite" hidden data-flacso-mailing-feedback="1"></div>
            <?php endif; ?>

            <form class="flacso-mailing-form" action="<?php echo esc_url($post_url); ?>" method="post" novalidate data-flacso-mailing-ajax="1" data-ajax-url="<?php echo esc_url($ajax_url); ?>" aria-busy="false">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <input type="hidden" name="submission_id" value="<?php echo esc_attr(function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : bin2hex(random_bytes(16))); ?>">
                <?php wp_nonce_field(self::NONCE, 'flacso_mailing_nonce'); ?>

                <div class="flacso-mailing-field">
                    <label for="<?php echo esc_attr($email_id); ?>"><?php esc_html_e('Correo electrónico', 'flacso-uruguay'); ?> <span aria-hidden="true">*</span></label>
                    <input id="<?php echo esc_attr($email_id); ?>" type="email" name="email" autocomplete="email" required placeholder="<?php esc_attr_e('tu@email.com', 'flacso-uruguay'); ?>" aria-describedby="<?php echo esc_attr($email_error_id); ?>">
                    <small id="<?php echo esc_attr($email_error_id); ?>" class="flacso-mailing-field-error" role="alert" hidden><?php esc_html_e('Ingresá un correo electrónico válido.', 'flacso-uruguay'); ?></small>
                </div>

                <div class="flacso-mailing-field">
                    <label for="<?php echo esc_attr($name_id); ?>"><?php esc_html_e('Nombre', 'flacso-uruguay'); ?></label>
                    <input id="<?php echo esc_attr($name_id); ?>" type="text" name="first_name" autocomplete="given-name" placeholder="<?php esc_attr_e('Tu nombre', 'flacso-uruguay'); ?>" aria-describedby="<?php echo esc_attr($name_error_id); ?>">
                    <small id="<?php echo esc_attr($name_error_id); ?>" class="flacso-mailing-field-error" role="alert" hidden></small>
                </div>

                <div class="flacso-mailing-field flacso-mailing-consent">
                    <label for="<?php echo esc_attr($consent_id); ?>">
                        <input id="<?php echo esc_attr($consent_id); ?>" type="checkbox" name="consent" value="1" required aria-describedby="<?php echo esc_attr($consent_error_id); ?>">
                        <span><?php echo esc_html($consent_text); ?></span>
                    </label>
                    <small id="<?php echo esc_attr($consent_error_id); ?>" class="flacso-mailing-field-error" role="alert" hidden><?php esc_html_e('Confirmá esta casilla para recibir novedades.', 'flacso-uruguay'); ?></small>
                </div>

                <button class="flacso-mailing-submit" type="submit" data-default-label="<?php echo esc_attr($button_label); ?>" data-loading-text="<?php echo esc_attr($loading_text); ?>">
                    <span class="flacso-mailing-btn-label"><?php echo esc_html($button_label); ?></span>
                </button>
            </form>
        </div>
        <?php return (string) ob_get_clean();
    }

    /**
     * Mensajes visibles según el estado devuelto por admin-post o AJAX.
     *
     * @return array<string,array{type:string,role:string,live:string,message:string}>
     */
    private static function status_notices(): array {
        return [
            'success' => [
                'type' => 'success',
                'role' => 'status',
                'live' => 'polite',
                'message' => __('¡Suscripción confirmada! Ya quedaste registrada/o en la lista de difusión de FLACSO Uruguay.', 'flacso-uruguay'),
            ],
            'invalid' => [
                'type' => 'error',
                'role' => 'alert',
                'live' => 'assertive',
                'message' => __('Revisá tu correo electrónico y marcá la casilla de autorización para suscribirte.', 'flacso-uruguay'),
            ],
            'security' => [
                'type' => 'error',
                'role' => 'alert',
                'live' => 'assertive',
                'message' => __('No pudimos validar tu envío. Recargá la página e intentá nuevamente.', 'flacso-uruguay'),
            ],
            'rate_limited' => [
                'type' => 'error',
                'role' => 'alert',
                'live' => 'assertive',
                'message' => __('Recibimos demasiados intentos en poco tiempo. Esperá unos minutos y volvé a intentar.', 'flacso-uruguay'),
            ],
            'not_configured' => [
                'type' => 'error',
                'role' => 'alert',
                'live' => 'assertive',
                'message' => __('La suscripción no está disponible en este momento. Intentá más tarde.', 'flacso-uruguay'),
            ],
            'error' => [
                'type' => 'error',
                'role' => 'alert',
                'live' => 'assertive',
                'message' => __('No pudimos completar tu suscripción en este momento. Intentá nuevamente.', 'flacso-uruguay'),
            ],
        ];
    }

    private static function read_status(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lectura de estado visual devuelto tras redirección.
        $raw = isset($_GET['flacso_mailing_status']) ? (string) wp_unslash($_GET['flacso_mailing_status']) : '';
        return sanitize_key($raw);
    }

    private static function render_assets_once(): void {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;
        ?>
        <style>
            .flacso-mailing-form-shell .flacso-mailing-form__title { margin: 0 0 0.35rem; font-size: 1.15rem; font-weight: 700; color: var(--global-palette3, #0d203e); }
            .flacso-mailing-form-shell .flacso-mailing-form__description { margin: 0 0 0.9rem; font-size: 0.95rem; color: var(--global-palette4, #334155); }
            .flacso-mailing-form-shell .flacso-mailing-form { display: grid; gap: 0.85rem; }
            .flacso-mailing-form-shell .flacso-mailing-field { display: grid; gap: 0.35rem; }
            .flacso-mailing-form-shell .flacso-mailing-field label { font-weight: 600; font-size: 0.95rem; color: var(--global-palette3, #0d203e); }
            .flacso-mailing-form-shell .flacso-mailing-field input[type="email"],
            .flacso-mailing-form-shell .flacso-mailing-field input[type="text"] {
                width: 100%; min-height: 44px; padding: 0.65rem 0.85rem;
                border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; color: #0f172a; font-size: 1rem;
            }
            .flacso-mailing-form-shell .flacso-mailing-field input[aria-invalid="true"],
            .flacso-mailing-form-shell .flacso-mailing-consent input[aria-invalid="true"] { outline: 2px solid #b91c1c; outline-offset: 2px; border-color: #b91c1c; }
            .flacso-mailing-form-shell .flacso-mailing-consent label { display: flex; align-items: flex-start; gap: 0.55rem; font-weight: 500; cursor: pointer; }
            .flacso-mailing-form-shell .flacso-mailing-consent input[type="checkbox"] { flex: 0 0 auto; width: 20px; height: 20px; margin-top: 0.15rem; }
            .flacso-mailing-form-shell .flacso-mailing-field-error { color: #9f1239; font-size: 0.875rem; font-weight: 600; }
            .flacso-mailing-form-shell .flacso-mailing-notice { margin-bottom: 0.9rem; padding: 0.8rem 1rem; border-radius: 8px; font-size: 0.95rem; font-weight: 600; border: 1px solid transparent; }
            .flacso-mailing-form-shell .flacso-mailing-notice.is-success { background: #e8f5ec; color: #166534; border-color: #86efac; }
            .flacso-mailing-form-shell .flacso-mailing-notice.is-error,
            .flacso-mailing-form-shell .flacso-mailing-notice.is-warning { background: #fdecee; color: #9f1239; border-color: #fca5a5; }
            .flacso-mailing-form-shell .flacso-mailing-submit { min-height: 44px; padding: 0.7rem 1.25rem; border: 0; border-radius: 999px; background: var(--global-palette1, #1d3a72); color: #fff; font-weight: 700; cursor: pointer; }
            .flacso-mailing-form-shell .flacso-mailing-submit:focus-visible,
            .flacso-mailing-form-shell input:focus-visible { outline: 3px solid var(--global-palette1, #1d3a72); outline-offset: 2px; }
            .flacso-mailing-form-shell .flacso-mailing-submit:disabled,
            .flacso-mailing-form-shell .flacso-mailing-form[aria-busy="true"] .flacso-mailing-submit { opacity: 0.7; cursor: wait; }
        </style>
        <script>
            (function(){
                function setNotice(box, type, text) {
                    if (!box) return;
                    box.classList.remove('is-success', 'is-error');
                    if (!text) {
                        box.hidden = true;
                        box.textContent = '';
                        return;
                    }
                    box.classList.add(type === 'success' ? 'is-success' : 'is-error');
                    box.setAttribute('role', type === 'success' ? 'status' : 'alert');
                    box.setAttribute('aria-live', type === 'success' ? 'polite' : 'assertive');
                    box.textContent = text;
                    box.hidden = false;
                }
                function setFieldError(input, errorEl, show) {
                    if (input) input.setAttribute('aria-invalid', show ? 'true' : 'false');
                    if (errorEl) errorEl.hidden = !show;
                }
                document.addEventListener('submit', function(e){
                    var form = e.target;
                    if (!form || !form.matches || !form.matches('form.flacso-mailing-form[data-flacso-mailing-ajax="1"]')) return;
                    if (!window.fetch || !window.FormData) return;

                    e.preventDefault();
                    var shell = form.closest('.flacso-mailing-form-shell');
                    var feedback = shell ? shell.querySelector('[data-flacso-mailing-feedback]') : null;
                    var messages = {};
                    if (shell && shell.dataset && shell.dataset.messages) {
                        try { messages = JSON.parse(shell.dataset.messages); } catch (err) { messages = {}; }
                    }

                    var emailInput = form.querySelector('input[name="email"]');
                    var consentInput = form.querySelector('input[name="consent"]');
                    var emailError = emailInput ? document.getElementById(emailInput.id + '_error') : null;
                    var consentError = consentInput ? document.getElementById(consentInput.id + '_error') : null;

                    var emailOk = emailInput && emailInput.value.trim() !== '' && emailInput.checkValidity();
                    var consentOk = consentInput && consentInput.checked;
                    setFieldError(emailInput, emailError, !emailOk);
                    setFieldError(consentInput, consentError, !consentOk);

                    if (!emailOk || !consentOk) {
                        var invalidInfo = messages.invalid || { type: 'error', message: 'Revisá tu correo electrónico y marcá la casilla de autorización.' };
                        setNotice(feedback, 'error', invalidInfo.message);
                        var firstBad = !emailOk ? emailInput : consentInput;
                        if (firstBad && typeof firstBad.focus === 'function') firstBad.focus();
                        return;
                    }

                    var submitBtn = form.querySelector('button[type="submit"]');
                    var btnLabel = submitBtn ? (submitBtn.querySelector('.flacso-mailing-btn-label') || submitBtn) : null;
                    var defaultLabel = submitBtn && submitBtn.dataset.defaultLabel ? submitBtn.dataset.defaultLabel : (btnLabel ? btnLabel.textContent : '');
                    var loadingLabel = submitBtn && submitBtn.dataset.loadingText ? submitBtn.dataset.loadingText : 'Enviando…';

                    form.setAttribute('aria-busy', 'true');
                    if (submitBtn) submitBtn.disabled = true;
                    if (btnLabel) btnLabel.textContent = loadingLabel;
                    setNotice(feedback, 'status', '');

                    fetch(form.dataset.ajaxUrl || form.action, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: new FormData(form)
                    }).then(function(res){
                        return res.json().catch(function(){ return { success: false, status: 'error' }; });
                    }).then(function(data){
                        var code = data && data.status ? data.status : (data && data.success ? 'success' : 'error');
                        var info = messages[code] || messages.error || { type: 'error', message: 'No pudimos completar tu suscripción.' };
                        var kind = data && data.success ? 'success' : 'error';
                        setNotice(feedback, kind, info.message);
                        if (data && data.success) {
                            form.reset();
                            setFieldError(emailInput, emailError, false);
                            setFieldError(consentInput, consentError, false);
                        }
                    }).catch(function(){
                        var errInfo = messages.error || { type: 'error', message: 'No pudimos completar tu suscripción en este momento.' };
                        setNotice(feedback, 'error', errInfo.message);
                    }).finally(function(){
                        form.setAttribute('aria-busy', 'false');
                        if (submitBtn) submitBtn.disabled = false;
                        if (btnLabel) btnLabel.textContent = defaultLabel;
                    });
                });
            })();
        </script>
        <?php
    }

    public static function is_configured(): bool { return class_exists('FLACSO_Mautic_Client') && FLACSO_Mautic_Client::is_configured(); }

    public static function handle_submission(): void {
        $result = self::process($_POST);
        $redirect = wp_get_referer() ?: home_url('/');
        wp_safe_redirect(add_query_arg('flacso_mailing_status', $result['status'], $redirect));
        exit;
    }

    public static function handle_ajax_submission(): void {
        $result = self::process($_POST);
        wp_send_json($result, !empty($result['success']) ? 200 : 422);
    }

    private static function process(array $data): array {
        if (empty($data['flacso_mailing_nonce']) || !wp_verify_nonce((string) $data['flacso_mailing_nonce'], self::NONCE)) return ['success' => false, 'status' => 'security'];
        $email = sanitize_email((string) ($data['email'] ?? ''));
        if (!$email || empty($data['consent'])) return ['success' => false, 'status' => 'invalid'];
        if (!self::is_configured()) return ['success' => false, 'status' => 'not_configured'];
        // Cada envío llama a Mautic dentro del request: tope por IP contra altas masivas.
        if (flacso_public_rate_exceeded('mailing', 5, 600)) return ['success' => false, 'status' => 'rate_limited'];
        $result = FLACSO_Mautic_Client::create_or_update_contact($email, ['firstname' => sanitize_text_field((string) ($data['first_name'] ?? ''))], ['suscripcion_institucional']);
        return ['success' => !empty($result['ok']), 'status' => !empty($result['ok']) ? 'success' : 'error'];
    }
}
