<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Definicion academica estable de un seminario. */
final class FLACSO_Seminario {
    public const POST_TYPE = 'seminario';
    public const META_PROGRAM_ID = 'programa_academico_id';
    public const META_OFERTA_ID = 'oferta_academica_id';
    public const META_COMPONENTES = 'componentes';

    public static function register_meta(): void {
        $definitions = [
            self::META_PROGRAM_ID => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            self::META_OFERTA_ID => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'correo' => ['type' => 'string', 'sanitize_callback' => 'sanitize_email'],
            'presentacion' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'objetivo_general' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'objetivos_especificos' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_html_list']],
            'composicion_academica' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_sections']],
            'forma_aprobacion' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'carga_horaria' => ['type' => 'number', 'sanitize_callback' => [self::class, 'sanitize_number']],
            'carga_horaria_descripcion' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'creditos' => ['type' => 'number', 'sanitize_callback' => [self::class, 'sanitize_number']],
            'acreditacion' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'acredita_maestria' => ['type' => 'boolean', 'sanitize_callback' => [self::class, 'sanitize_boolean']],
            'acredita_doctorado' => ['type' => 'boolean', 'sanitize_callback' => [self::class, 'sanitize_boolean']],
            'docentes_base' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_ids']],
            self::META_COMPONENTES => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_components']],
        ];
        foreach ($definitions as $key => $definition) {
            register_post_meta(self::POST_TYPE, $key, array_merge([
                'single' => true,
                'show_in_rest' => false,
                'auth_callback' => static function (): bool { return current_user_can('edit_posts'); },
            ], $definition));
        }
    }

    public static function sanitize_number($value): float {
        return max(0, (float) str_replace(',', '.', (string) $value));
    }

    public static function sanitize_boolean($value): bool {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function sanitize_ids($value): array {
        return is_array($value) ? array_values(array_unique(array_filter(array_map('absint', $value)))) : [];
    }

    public static function sanitize_html_list($value): array {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map('wp_kses_post', $value), static function ($item): bool {
            return trim(wp_strip_all_tags((string) $item)) !== '';
        }));
    }

    public static function sanitize_sections($value): array {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $section = [
                'titulo' => sanitize_text_field((string) ($item['titulo'] ?? '')),
                'contenido' => wp_kses_post((string) ($item['contenido'] ?? '')),
            ];
            if ($section['titulo'] !== '' || trim(wp_strip_all_tags($section['contenido'])) !== '') {
                $result[] = $section;
            }
        }
        return $result;
    }

    public static function sanitize_components($value): array {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        $seen = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = absint($item['seminario_id'] ?? 0);
            if ($id < 1 || isset($seen[$id])) {
                continue;
            }
            $result[] = ['seminario_id' => $id, 'orden' => absint($item['orden'] ?? count($result) + 1)];
            $seen[$id] = true;
        }
        usort($result, static function (array $a, array $b): int { return $a['orden'] <=> $b['orden']; });
        return $result;
    }
}
