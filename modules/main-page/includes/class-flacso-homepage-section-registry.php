<?php
/**
 * Registro de secciones de portada.
 *
 * `main-page` compone la página; cada dominio declara su propia sección
 * mediante `flacso_homepage_sections`.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Flacso_Homepage_Section_Registry {
    /** @var array<string,array<string,mixed>>|null */
    private static $sections = null;

    public static function reset(): void {
        self::$sections = null;
    }

    public static function all(): array {
        if (self::$sections !== null) {
            return self::$sections;
        }

        // Sólo contenido cuyo propietario real es main-page.
        $sections = [
            'hero' => [
                'function' => 'flacso_section_hero_render',
                'owner' => 'main-page',
            ],
            'novedades' => [
                'function' => 'flacso_section_novedades_render',
                'owner' => 'main-page',
            ],
            'quienes' => [
                'function' => 'flacso_section_quienes_somos_render',
                'owner' => 'main-page',
            ],
            'congreso' => [
                'function' => 'flacso_section_congreso_render',
                'owner' => 'main-page',
            ],
            'contacto' => [
                'function' => 'flacso_section_contacto_render',
                'owner' => 'main-page',
            ],
        ];

        self::$sections = self::normalize(apply_filters('flacso_homepage_sections', $sections));
        return self::$sections;
    }

    public static function get(string $key): ?array {
        $key = class_exists('Flacso_Main_Page_Section_Keys')
            ? Flacso_Main_Page_Section_Keys::canonicalize($key)
            : sanitize_key($key);
        $sections = self::all();
        return $sections[$key] ?? null;
    }

    private static function normalize($sections): array {
        if (!is_array($sections)) {
            return [];
        }

        $normalized = [];
        foreach ($sections as $key => $definition) {
            $key = class_exists('Flacso_Main_Page_Section_Keys')
                ? Flacso_Main_Page_Section_Keys::canonicalize((string) $key)
                : sanitize_key((string) $key);
            if ($key === '') {
                continue;
            }
            if (class_exists('Flacso_Main_Page_Section_Keys') && Flacso_Main_Page_Section_Keys::is_retired($key)) {
                continue;
            }
            if (!is_array($definition)) {
                continue;
            }

            $function = isset($definition['function']) ? (string) $definition['function'] : '';
            if ($function === '') {
                continue;
            }

            $normalized[$key] = [
                'key' => $key,
                'function' => $function,
                'owner' => sanitize_key((string) ($definition['owner'] ?? 'main-page')),
                'react_component' => sanitize_key((string) ($definition['react_component'] ?? '')),
            ];
        }
        return $normalized;
    }
}
