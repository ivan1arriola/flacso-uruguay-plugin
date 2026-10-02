<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Envía alertas operativas por errores originados dentro del plugin.
 */
final class FLACSO_Error_Notifier {
    public const OPTION_TELEGRAM_BOT_TOKEN = 'flacso_error_alert_telegram_bot_token';
    public const OPTION_TELEGRAM_CHAT_ID = 'flacso_error_alert_telegram_chat_id';
    public const OPTION_EMAIL = 'flacso_error_alert_email';

    private const DEDUPLICATION_SECONDS = 900;
    private static bool $initialized = false;

    public static function init(): void {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;
        set_error_handler([self::class, 'handle_php_error']);
        register_shutdown_function([self::class, 'handle_shutdown']);
    }

    public static function handle_php_error(int $level, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $level) || !self::is_plugin_file($file)) {
            return false;
        }

        self::report($message, $file, $line, self::error_level_name($level));

        return false;
    }

    public static function handle_shutdown(): void {
        $error = error_get_last();
        if (!is_array($error) || !isset($error['type'], $error['message'], $error['file'], $error['line'])) {
            return;
        }

        $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
        if (!in_array((int) $error['type'], $fatal_types, true) || !self::is_plugin_file((string) $error['file'])) {
            return;
        }

        self::report((string) $error['message'], (string) $error['file'], (int) $error['line'], 'fatal');
    }

    public static function report_exception(\Throwable $error): bool {
        return self::report($error->getMessage(), $error->getFile(), $error->getLine(), 'exception');
    }

    public static function report(string $message, string $file, int $line, string $severity = 'error'): bool {
        if (!self::is_plugin_file($file)) {
            return false;
        }

        $message = self::redact($message);
        $signature = 'flacso_error_alert_' . sha1($severity . '|' . $message . '|' . $file . '|' . $line);
        if (function_exists('get_transient') && get_transient($signature)) {
            return false;
        }

        $text = self::format_message($message, $file, $line, $severity);
        $sent = self::send_telegram($text);
        if (!$sent) {
            $sent = self::send_email($text);
        }

        if ($sent && function_exists('set_transient')) {
            set_transient($signature, 1, self::DEDUPLICATION_SECONDS);
        }

        return $sent;
    }

    private static function send_telegram(string $text): bool {
        $token = trim((string) get_option(self::OPTION_TELEGRAM_BOT_TOKEN, ''));
        $chat_id = trim((string) get_option(self::OPTION_TELEGRAM_CHAT_ID, ''));
        if ($token === '' || $chat_id === '' || !function_exists('wp_remote_post')) {
            return false;
        }

        $response = wp_remote_post(
            'https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage',
            [
                'timeout' => 5,
                'body' => [
                    'chat_id' => $chat_id,
                    'text' => self::truncate($text, 3900),
                    'disable_web_page_preview' => true,
                ],
            ]
        );

        return !is_wp_error($response)
            && function_exists('wp_remote_retrieve_response_code')
            && wp_remote_retrieve_response_code($response) >= 200
            && wp_remote_retrieve_response_code($response) < 300;
    }

    private static function send_email(string $text): bool {
        if (!function_exists('wp_mail')) {
            return false;
        }

        $configured = trim((string) get_option(self::OPTION_EMAIL, ''));
        $recipient = sanitize_email($configured !== '' ? $configured : (string) get_option('admin_email', ''));
        if ($recipient === '') {
            return false;
        }

        return (bool) wp_mail($recipient, '[FLACSO] Alerta de error del plugin', $text);
    }

    private static function format_message(string $message, string $file, int $line, string $severity): string {
        $relative_file = ltrim(str_replace(FLACSO_URUGUAY_PATH, '', $file), '/');

        return implode("\n", [
            'Alerta de error del plugin FLACSO Uruguay',
            'Severidad: ' . $severity,
            'Sitio: ' . (function_exists('home_url') ? home_url('/') : ''),
            'Archivo: ' . $relative_file . ':' . $line,
            'Mensaje: ' . self::truncate($message, 1500),
        ]);
    }

    private static function is_plugin_file(string $file): bool {
        $plugin_path = realpath(FLACSO_URUGUAY_PATH);
        $error_path = realpath($file);

        if ($plugin_path === false || $error_path === false) {
            return strncmp($file, FLACSO_URUGUAY_PATH, strlen(FLACSO_URUGUAY_PATH)) === 0;
        }

        return strncmp($error_path, $plugin_path . DIRECTORY_SEPARATOR, strlen($plugin_path) + 1) === 0;
    }

    private static function error_level_name(int $level): string {
        $levels = [
            E_WARNING => 'warning',
            E_USER_WARNING => 'warning',
            E_NOTICE => 'notice',
            E_USER_NOTICE => 'notice',
            E_DEPRECATED => 'deprecated',
            E_USER_DEPRECATED => 'deprecated',
        ];

        return $levels[$level] ?? 'error';
    }

    private static function redact(string $message): string {
        $message = preg_replace('/((?:password|token|secret|api[_-]?key)\s*[=:]\s*)[^\s,;]+/i', '$1[redacted]', $message) ?? $message;

        return trim(str_replace(["\r", "\n"], ' ', $message));
    }

    private static function truncate(string $text, int $length): string {
        return function_exists('mb_substr') ? mb_substr($text, 0, $length) : substr($text, 0, $length);
    }
}

FLACSO_Error_Notifier::init();
