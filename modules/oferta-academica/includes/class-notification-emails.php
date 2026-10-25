<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Listas de correos de notificación (Correo de la Oferta Académica, Correo del
 * Seminario). Acepta una lista o un texto separado por comas, punto y coma o
 * saltos de línea; los valores históricos guardados como texto único siguen
 * funcionando. Estos correos no son públicos.
 */
final class FLACSO_Notification_Emails {
    public static function split($value): array {
        if (is_string($value)) {
            $value = preg_split('/[\s,;]+/', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }
        $emails = [];
        foreach ($value as $item) {
            $item = is_scalar($item) ? strtolower(trim((string) $item)) : '';
            if ($item !== '') {
                $emails[] = $item;
            }
        }
        return array_values(array_unique($emails));
    }

    public static function sanitize($value): array {
        $emails = array_filter(array_map('sanitize_email', self::split($value)), static fn(string $email): bool => is_email($email) !== false);
        return array_values(array_unique($emails));
    }

    /** @return string[] Valores que no son direcciones de correo válidas. */
    public static function invalid($value): array {
        return array_values(array_filter(self::split($value), static fn(string $email): bool => is_email($email) === false));
    }

    public static function from_meta(int $post_id, string $meta_key): array {
        return self::sanitize(get_post_meta($post_id, $meta_key, true));
    }
}
