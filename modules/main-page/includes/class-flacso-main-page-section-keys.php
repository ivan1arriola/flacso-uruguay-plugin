<?php
/**
 * Claves canónicas de la portada y compatibilidad con nombres históricos.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Flacso_Main_Page_Section_Keys {
    public const OFFER = 'oferta_academica';

    private const CANONICAL = [
        'hero',
        'eventos',
        'novedades',
        'novedades_busqueda',
        'seminarios',
        'quienes',
        'instagram',
        self::OFFER,
        'mailing',
        'contacto',
        'congreso',
    ];

    private const ALIASES = [
        'posgrados' => self::OFFER,
    ];

    private const RETIRED = [
        'festejos',
    ];

    public static function all(): array {
        return self::CANONICAL;
    }

    public static function aliases(): array {
        return self::ALIASES;
    }

    public static function retired(): array {
        return self::RETIRED;
    }

    public static function canonicalize(string $key): string {
        $key = sanitize_key($key);
        return self::ALIASES[$key] ?? $key;
    }

    public static function is_retired(string $key): bool {
        return in_array(sanitize_key($key), self::RETIRED, true);
    }

    public static function normalize_order(array $order): array {
        $normalized = [];
        foreach ($order as $key) {
            if (!is_scalar($key)) {
                continue;
            }
            $key = self::canonicalize((string) $key);
            if (self::is_retired($key) || !in_array($key, self::CANONICAL, true)) {
                continue;
            }
            if (!in_array($key, $normalized, true)) {
                $normalized[] = $key;
            }
        }
        return $normalized;
    }

    public static function normalize_keyed_map(array $map): array {
        $normalized = [];
        foreach ($map as $key => $value) {
            $key = self::canonicalize((string) $key);
            if (self::is_retired($key) || !in_array($key, self::CANONICAL, true)) {
                continue;
            }
            $normalized[$key] = $value;
        }
        return $normalized;
    }

    /**
     * Migra un array completo de settings en memoria sin destruir el option
     * original. La persistencia ocurre recién en el siguiente guardado válido.
     */
    public static function normalize_settings(array $settings): array {
        if (!isset($settings[self::OFFER]) && isset($settings['posgrados']) && is_array($settings['posgrados'])) {
            $settings[self::OFFER] = $settings['posgrados'];
        } elseif (
            isset($settings[self::OFFER], $settings['posgrados'])
            && is_array($settings[self::OFFER])
            && is_array($settings['posgrados'])
        ) {
            // Los valores ya canónicos tienen prioridad; el legado completa
            // campos faltantes para que la migración sea no destructiva.
            $settings[self::OFFER] = wp_parse_args($settings[self::OFFER], $settings['posgrados']);
        }

        unset($settings['posgrados'], $settings['festejos']);

        if (isset($settings['sections_order']) && is_array($settings['sections_order'])) {
            $settings['sections_order'] = self::normalize_order($settings['sections_order']);
        }
        if (isset($settings['sections_visibility']) && is_array($settings['sections_visibility'])) {
            $settings['sections_visibility'] = self::normalize_keyed_map($settings['sections_visibility']);
        }
        if (isset($settings['section_heading_colors']) && is_array($settings['section_heading_colors'])) {
            $settings['section_heading_colors'] = self::normalize_keyed_map($settings['section_heading_colors']);
        }

        return $settings;
    }
}
