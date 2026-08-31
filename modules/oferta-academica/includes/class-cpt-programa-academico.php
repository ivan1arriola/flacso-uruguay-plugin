<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Agrupacion institucional de ofertas y seminarios. */
final class FLACSO_Programa_Academico {
    public const POST_TYPE = 'programa-academico';

    public static function init(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Programas académicos', 'flacso-uruguay'),
                'singular_name' => __('Programa académico', 'flacso-uruguay'),
                'add_new_item' => __('Agregar programa académico', 'flacso-uruguay'),
                'edit_item' => __('Editar programa académico', 'flacso-uruguay'),
            ],
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=oferta-academica',
            'show_in_rest' => true,
            'rest_base' => 'programas-academicos-wp',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions'],
            'has_archive' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);

        $fields = [
            'correo' => ['type' => 'string', 'sanitize_callback' => 'sanitize_email'],
            'coordinacion' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_coordination']],
            'orden' => ['type' => 'integer', 'sanitize_callback' => 'absint'],
        ];
        foreach ($fields as $key => $definition) {
            register_post_meta(self::POST_TYPE, $key, array_merge([
                'single' => true,
                'show_in_rest' => false,
                'auth_callback' => static function (): bool { return current_user_can('edit_posts'); },
            ], $definition));
        }
    }

    public static function sanitize_coordination($value): array {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized = [
                'docente_id' => absint($item['docente_id'] ?? 0),
                'nombre' => sanitize_text_field((string) ($item['nombre'] ?? '')),
                'rol' => sanitize_text_field((string) ($item['rol'] ?? '')),
            ];
            if ($normalized['docente_id'] > 0 || $normalized['nombre'] !== '') {
                $result[] = $normalized;
            }
        }
        return $result;
    }
}
