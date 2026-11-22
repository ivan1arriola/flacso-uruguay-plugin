<?php
/**
 * El aviso de error al administrador lleva el correo del usuario; el log del servidor no.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

function policy_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

// Stubs mínimos de WordPress.
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
function __($text, $domain = null) { return $text; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function esc_url_raw($url) { return trim((string) $url); }
function sanitize_email($email) { return trim(strtolower((string) $email)); }
function current_time($format) { return date($format); }
function get_transient($key) { return false; }
function set_transient($key, $value, $ttl) { return true; }
function get_option($key, $default = false) { return 'fc_destinatario_email' === $key ? 'recepcion@example.test' : $default; }
function is_email($email) { return (bool) filter_var($email, FILTER_VALIDATE_EMAIL); }
function wp_generate_password($length = 12, $special = true, $extra = false) { return 'ABCD'; }
function wp_mail($to, $subject, $body, $headers = []) {
    $GLOBALS['policy_mail_body'] = $body;
    return true;
}

$log = sys_get_temp_dir() . '/fc-error-policy-test-' . getmypid() . '.log';
ini_set('log_errors', '1');
ini_set('error_log', $log);

require_once dirname(__DIR__) . '/modules/formularios/includes/error-policy.php';

$result = fc_notify_form_admin_error([
    'reference'  => 'CONSULTA-TEST',
    'form'       => 'Consulta general',
    'stage'      => 'prueba',
    'detail'     => 'HTTP 500',
    'url'        => 'https://flacso.edu.uy/contactos/',
    'user_email' => 'persona@example.org',
]);

policy_assert(($result['email'] ?? '') === 'sent', 'El aviso por correo al administrador debe enviarse');
policy_assert(strpos((string) ($GLOBALS['policy_mail_body'] ?? ''), 'persona@example.org') !== false, 'El aviso al administrador debe seguir llevando el correo del usuario');

$logged = is_file($log) ? (string) file_get_contents($log) : '';
@unlink($log);
policy_assert(strpos($logged, 'CONSULTA-TEST') !== false, 'El log debe registrar la referencia del incidente');
policy_assert(strpos($logged, 'HTTP 500') !== false, 'El log debe registrar el detalle técnico');
policy_assert(strpos($logged, 'persona@example.org') === false, 'El log del servidor no debe llevar el correo del usuario');

echo "OK error-policy: el correo del usuario no llega al log del servidor\n";
