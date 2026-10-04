<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}
if (!defined('FLACSO_URUGUAY_PATH')) {
    define('FLACSO_URUGUAY_PATH', dirname(__DIR__) . '/');
}
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

$GLOBALS['flacso_error_notifier_options'] = [
    'flacso_error_alert_telegram_bot_token' => '',
    'flacso_error_alert_telegram_chat_id' => '',
    'flacso_error_alert_email' => '',
    'admin_email' => 'admin@flacso.edu.uy',
];
$GLOBALS['flacso_error_notifier_http_calls'] = [];
$GLOBALS['flacso_error_notifier_mail_calls'] = [];
$GLOBALS['flacso_error_notifier_transients'] = [];
$GLOBALS['flacso_error_notifier_http_response'] = ['response' => ['code' => 200]];

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false) {
        return $GLOBALS['flacso_error_notifier_options'][$option] ?? $default;
    }
}
if (!function_exists('get_transient')) {
    function get_transient(string $key) {
        return $GLOBALS['flacso_error_notifier_transients'][$key] ?? false;
    }
}
if (!function_exists('set_transient')) {
    function set_transient(string $key, $value, int $expiration): bool {
        $GLOBALS['flacso_error_notifier_transients'][$key] = $value;
        return true;
    }
}
if (!function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = []) {
        $GLOBALS['flacso_error_notifier_http_calls'][] = ['url' => $url, 'args' => $args];
        return $GLOBALS['flacso_error_notifier_http_response'];
    }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response): int {
        return (int) ($response['response']['code'] ?? 0);
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($value): bool {
        return false;
    }
}
if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message): bool {
        $GLOBALS['flacso_error_notifier_mail_calls'][] = compact('to', 'subject', 'message');
        return true;
    }
}
if (!function_exists('sanitize_email')) {
    function sanitize_email($email): string {
        return filter_var((string) $email, FILTER_SANITIZE_EMAIL);
    }
}
if (!function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = ''): string {
        return 'FLACSO Uruguay';
    }
}
if (!function_exists('home_url')) {
    function home_url(): string {
        return 'https://flacso.edu.uy';
    }
}

function error_notifier_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

require_once dirname(__DIR__) . '/includes/core/class-flacso-error-notifier.php';

$GLOBALS['flacso_error_notifier_options']['flacso_error_alert_telegram_bot_token'] = '123:token';
$GLOBALS['flacso_error_notifier_options']['flacso_error_alert_telegram_chat_id'] = '-100123';

error_notifier_assert(
    FLACSO_Error_Notifier::report('Database unavailable', __FILE__, 101),
    'debe enviar la alerta por Telegram cuando bot y destinatario estan configurados'
);
error_notifier_assert(count($GLOBALS['flacso_error_notifier_http_calls']) === 1, 'debe realizar una solicitud a Telegram');
error_notifier_assert(count($GLOBALS['flacso_error_notifier_mail_calls']) === 0, 'no debe enviar correo cuando Telegram confirma la alerta');
$telegram_call = $GLOBALS['flacso_error_notifier_http_calls'][0];
error_notifier_assert(strpos($telegram_call['url'], 'api.telegram.org/bot123%3Atoken/sendMessage') !== false, 'debe usar el bot configurado');
error_notifier_assert($telegram_call['args']['body']['chat_id'] === '-100123', 'debe usar el destinatario Telegram configurado');
error_notifier_assert(strpos($telegram_call['args']['body']['text'], 'Database unavailable') !== false, 'debe incluir el diagnostico del error');

error_notifier_assert(
    FLACSO_Error_Notifier::test_notification('Mautic · FLACSO > Correos'),
    'la prueba manual debe enviar una notificación sin depender de un error real'
);
error_notifier_assert(count($GLOBALS['flacso_error_notifier_http_calls']) === 2, 'la prueba manual debe poder enviarse inmediatamente');
error_notifier_assert(
    strpos($GLOBALS['flacso_error_notifier_http_calls'][1]['args']['body']['text'], 'Mautic') !== false,
    'la prueba manual debe indicar su contexto'
);

$GLOBALS['flacso_error_notifier_options']['flacso_error_alert_telegram_bot_token'] = '';
$GLOBALS['flacso_error_notifier_options']['flacso_error_alert_telegram_chat_id'] = '';
$GLOBALS['flacso_error_notifier_options']['flacso_error_alert_email'] = 'operaciones@flacso.edu.uy';
error_notifier_assert(
    FLACSO_Error_Notifier::report('Mailer unavailable', __FILE__, 202),
    'debe recurrir a wp_mail cuando Telegram no esta configurado'
);
error_notifier_assert(count($GLOBALS['flacso_error_notifier_mail_calls']) === 1, 'debe enviar un correo de respaldo');
error_notifier_assert($GLOBALS['flacso_error_notifier_mail_calls'][0]['to'] === 'operaciones@flacso.edu.uy', 'debe usar el correo operativo configurado');

error_notifier_assert(
    !FLACSO_Error_Notifier::report('Mailer unavailable', __FILE__, 202),
    'debe suprimir el mismo error durante la ventana de deduplicacion'
);
error_notifier_assert(count($GLOBALS['flacso_error_notifier_mail_calls']) === 1, 'no debe repetir el correo para el mismo error');

$GLOBALS['flacso_error_notifier_options']['flacso_error_alert_telegram_bot_token'] = '123:token';
$GLOBALS['flacso_error_notifier_options']['flacso_error_alert_telegram_chat_id'] = '-100123';
$GLOBALS['flacso_error_notifier_http_response'] = ['response' => ['code' => 502]];
error_notifier_assert(
    FLACSO_Error_Notifier::report('Telegram unavailable', __FILE__, 252),
    'debe recurrir a wp_mail cuando Telegram no responde correctamente'
);
error_notifier_assert(count($GLOBALS['flacso_error_notifier_mail_calls']) === 2, 'debe usar correo tras un fallo de Telegram');

$GLOBALS['flacso_error_notifier_options']['flacso_error_alert_telegram_bot_token'] = '';
error_notifier_assert(
    FLACSO_Error_Notifier::handle_php_error(E_WARNING, 'Plugin warning', __FILE__, 303) === false,
    'debe preservar el manejador normal de PHP despues de alertar errores del plugin'
);
error_notifier_assert(count($GLOBALS['flacso_error_notifier_mail_calls']) === 3, 'debe alertar advertencias originadas dentro del plugin');

echo "OK error notifier\n";
