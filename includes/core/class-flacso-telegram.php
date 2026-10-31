<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Única conexión con Telegram del plugin: consultas, formularios y alertas de
 * errores usan el mismo bot y el mismo chat, que se configuran en FLACSO > Sistema.
 */
final class FLACSO_Telegram {
    public const OPTION_BOT_TOKEN = 'fc_telegram_bot_token';
    public const OPTION_CHAT_ID = 'fc_telegram_chat_id';

    /** Opciones anteriores de las alertas de errores; se leen sólo hasta migrarlas. */
    private const LEGACY_BOT_TOKEN = 'flacso_error_alert_telegram_bot_token';
    private const LEGACY_CHAT_ID = 'flacso_error_alert_telegram_chat_id';

    public static function init(): void {
        if (function_exists('add_action')) {
            add_action('admin_init', [self::class, 'migrate_legacy_options']);
        }
    }

    /**
     * @return array{token:string,chat_id:string}
     */
    public static function credentials(): array {
        $token = trim((string) get_option(self::OPTION_BOT_TOKEN, ''));
        $chat_id = trim((string) get_option(self::OPTION_CHAT_ID, ''));
        if ($token === '' && $chat_id === '') {
            $token = trim((string) get_option(self::LEGACY_BOT_TOKEN, ''));
            $chat_id = trim((string) get_option(self::LEGACY_CHAT_ID, ''));
        }
        return ['token' => $token, 'chat_id' => $chat_id];
    }

    public static function is_configured(): bool {
        $credentials = self::credentials();
        return $credentials['token'] !== '' && $credentials['chat_id'] !== '';
    }

    /** Copia las credenciales de las alertas a la conexión única y borra las anteriores. */
    public static function migrate_legacy_options(): void {
        $legacy_token = trim((string) get_option(self::LEGACY_BOT_TOKEN, ''));
        $legacy_chat = trim((string) get_option(self::LEGACY_CHAT_ID, ''));
        if ($legacy_token !== '' && $legacy_chat !== '' && !self::is_current_configured()) {
            update_option(self::OPTION_BOT_TOKEN, $legacy_token);
            update_option(self::OPTION_CHAT_ID, $legacy_chat);
        }
        foreach ([self::LEGACY_BOT_TOKEN, self::LEGACY_CHAT_ID, 'fc_use_telegram', 'flacso_preinscripciones_telegram_bot_token', 'flacso_preinscripciones_telegram_chat_id'] as $legacy) {
            if (get_option($legacy, null) !== null) {
                delete_option($legacy);
            }
        }
    }

    private static function is_current_configured(): bool {
        return trim((string) get_option(self::OPTION_BOT_TOKEN, '')) !== ''
            && trim((string) get_option(self::OPTION_CHAT_ID, '')) !== '';
    }

    /** @param bool $html Interpreta el texto como HTML de Telegram (b, i, a). */
    public static function send(string $text, bool $html = false, int $timeout = 10): bool {
        $credentials = self::credentials();
        if ($credentials['token'] === '' || $credentials['chat_id'] === '' || !function_exists('wp_remote_post')) {
            return false;
        }

        $body = [
            'chat_id' => $credentials['chat_id'],
            'text' => $text,
            'disable_web_page_preview' => true,
        ];
        if ($html) {
            $body['parse_mode'] = 'HTML';
        }

        $response = wp_remote_post(
            'https://api.telegram.org/bot' . rawurlencode($credentials['token']) . '/sendMessage',
            ['timeout' => $timeout, 'body' => $body]
        );
        if (is_wp_error($response)) {
            error_log('[FLACSO Telegram] ' . $response->get_error_message());
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            error_log('[FLACSO Telegram] HTTP ' . $code);
            return false;
        }
        return true;
    }
}

FLACSO_Telegram::init();
