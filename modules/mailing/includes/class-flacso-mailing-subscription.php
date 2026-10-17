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
        $args = shortcode_atts(['button_label' => __('Suscribirme', 'flacso-uruguay')], $args, self::SHORTCODE);
        ob_start(); ?>
        <form class="flacso-mailing-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
            <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
            <input type="hidden" name="submission_id" value="<?php echo esc_attr(function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : bin2hex(random_bytes(16))); ?>">
            <?php wp_nonce_field(self::NONCE, 'flacso_mailing_nonce'); ?>
            <input type="email" name="email" required placeholder="<?php esc_attr_e('tu@email.com', 'flacso-uruguay'); ?>">
            <input type="text" name="first_name" placeholder="<?php esc_attr_e('Tu nombre', 'flacso-uruguay'); ?>">
            <label><input type="checkbox" name="consent" value="1" required> <?php esc_html_e('Acepto recibir novedades y comunicaciones institucionales.', 'flacso-uruguay'); ?></label>
            <button type="submit"><?php echo esc_html($args['button_label']); ?></button>
        </form>
        <?php return (string) ob_get_clean();
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
        $provider = class_exists('FLACSO_Mailing_Client')
            ? FLACSO_Mailing_Client::delivery_provider()
            : (function_exists('get_option') ? (string) get_option('flacso_inquiry_delivery_provider', 'mautic') : 'mautic');
        if ($provider === 'flacso_mail') {
            $submission_id = sanitize_text_field((string) ($data['submission_id'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9_-]{16,64}$/', $submission_id)) return ['success' => false, 'status' => 'invalid'];
            if (!class_exists('FLACSO_Mailing_Client')) {
                require_once dirname(__DIR__, 3) . '/includes/integrations/class-flacso-mailing-client.php';
            }
            $cache_key = 'flacso_mailing_subscription_' . $submission_id;
            $submission = function_exists('get_transient') ? get_transient($cache_key) : false;
            $first_name = sanitize_text_field((string) ($data['first_name'] ?? ''));
            if (is_array($submission)) {
                if (($submission['email'] ?? '') !== strtolower($email) || ($submission['first_name'] ?? '') !== $first_name) {
                    return ['success' => false, 'status' => 'conflict'];
                }
            } else {
                $submission = [
                    'email' => strtolower($email),
                    'first_name' => $first_name,
                    'acceptedAt' => function_exists('current_time') ? current_time('c') : gmdate('c'),
                    'source' => 'shortcode_institucional',
                    'textVersion' => 'v1-2026',
                ];
                if (function_exists('set_transient')) set_transient($cache_key, $submission, 30 * DAY_IN_SECONDS);
            }
            $result = FLACSO_Mailing_Client::sync_contact($submission, 'wp_sub_' . $submission_id);
            return [
                'success' => !empty($result['ok']),
                'status' => !empty($result['ok']) ? 'success' : (string) ($result['status'] ?? 'error'),
            ];
        }
        if (!self::is_configured()) return ['success' => false, 'status' => 'not_configured'];
        $result = FLACSO_Mautic_Client::create_or_update_contact($email, ['firstname' => sanitize_text_field((string) ($data['first_name'] ?? ''))], ['suscripcion_institucional']);
        return ['success' => !empty($result['ok']), 'status' => !empty($result['ok']) ? 'success' : 'error'];
    }
}
