<?php

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

final class FLACSO_Mautic_Delivery_Contract_Cache {
    private const TTL = 300;
    private static array $memory = [];

    public static function get(int $template_id, bool $force = false): ?array {
        if ($template_id <= 0) {
            return [
                'ok' => false,
                'status' => 'blocked',
                'requirements' => [[
                    'ok' => false,
                    'blocking' => true,
                    'message' => 'La plantilla transaccional no tiene un ID válido.',
                ]],
            ];
        }

        $key = self::key($template_id);
        if ($force) {
            unset(self::$memory[$key]);
            if (function_exists('delete_transient')) {
                delete_transient($key);
            }
        }

        if (isset(self::$memory[$key])) {
            return self::$memory[$key];
        }

        if (!$force && function_exists('get_transient')) {
            $cached = get_transient($key);
            if (is_array($cached)) {
                self::$memory[$key] = $cached;
                return $cached;
            }
        }

        $result = FLACSO_Mautic_Contract_Validator::validate_delivery_template($template_id, $force);
        if (!empty($result['ok'])) {
            self::$memory[$key] = $result;
            if (function_exists('set_transient')) {
                set_transient($key, $result, self::TTL);
            }
        }

        return $result;
    }

    public static function clear(): void {
        foreach (array_keys(self::$memory) as $key) {
            if (function_exists('delete_transient')) {
                delete_transient($key);
            }
        }
        self::$memory = [];
    }

    private static function key(int $template_id): string {
        $version = class_exists('FLACSO_Mautic_Contract_Manifest')
            ? FLACSO_Mautic_Contract_Manifest::VERSION
            : 'unknown';
        return 'flacso_mautic_delivery_contract_' . md5($version . ':' . $template_id);
    }
}
