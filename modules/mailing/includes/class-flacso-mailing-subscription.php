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
        if (!self::is_configured()) return ['success' => false, 'status' => 'not_configured'];
        $result = FLACSO_Mautic_Client::create_or_update_contact($email, ['firstname' => sanitize_text_field((string) ($data['first_name'] ?? ''))], ['suscripcion_institucional']);
        return ['success' => !empty($result['ok']), 'status' => !empty($result['ok']) ? 'success' : 'error'];
    }
}
