<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Ocurrencia temporal de un Seminario. */
final class FLACSO_Edicion_Seminario {
    public const POST_TYPE = 'edicion-seminario';
    public const META_PARENT_ID = 'seminario_id';
    public const ESTADOS = ['planificada', 'en_curso', 'finalizada', 'cancelada'];

    public static function register(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Ediciones de seminarios', 'flacso-uruguay'),
                'singular_name' => __('Edición de seminario', 'flacso-uruguay'),
                'add_new_item' => __('Agregar edición', 'flacso-uruguay'),
                'edit_item' => __('Editar edición', 'flacso-uruguay'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=seminario',
            'show_in_rest' => false,
            'supports' => ['title', 'revisions'],
            'rewrite' => false,
            'query_var' => false,
            'map_meta_cap' => true,
        ]);

        $definitions = [
            self::META_PARENT_ID => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'anio' => ['type' => 'integer', 'sanitize_callback' => [self::class, 'sanitize_year']],
            'fecha_inicio' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_date']],
            'fecha_fin' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_date']],
            'estado' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_state']],
            'modalidad' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'encuentros_sincronicos' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_meetings']],
            'docentes' => ['type' => 'array', 'sanitize_callback' => [FLACSO_Seminario::class, 'sanitize_ids']],
            'tabla_precio_id' => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'preinscripcion_desde' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_datetime']],
            'preinscripcion_hasta' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_datetime']],
            'mensaje_preinscripcion_abierta' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'mensaje_preinscripcion_cerrada' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'mostrar_en_formulario' => ['type' => 'boolean', 'sanitize_callback' => [FLACSO_Seminario::class, 'sanitize_boolean']],
            'ediciones_componentes' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_components']],
        ];
        foreach ($definitions as $key => $definition) {
            register_post_meta(self::POST_TYPE, $key, array_merge([
                'single' => true,
                'show_in_rest' => false,
                'auth_callback' => static function (): bool { return current_user_can('edit_posts'); },
            ], $definition));
        }
    }

    public static function sanitize_year($value): int {
        $year = absint($value);
        return $year >= 2000 && $year <= 2200 ? $year : 0;
    }

    public static function sanitize_state($value): string {
        $state = sanitize_key((string) $value);
        return in_array($state, self::ESTADOS, true) ? $state : 'planificada';
    }

    public static function sanitize_date($value): string {
        $value = sanitize_text_field((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    public static function sanitize_datetime($value): string {
        $value = sanitize_text_field((string) $value);
        return $value !== '' && strtotime($value) !== false ? $value : '';
    }

    public static function sanitize_meetings($value): array {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $meeting = [
                'fecha' => self::sanitize_date($item['fecha'] ?? ''),
                'hora_inicio' => sanitize_text_field((string) ($item['hora_inicio'] ?? '')),
                'hora_fin' => sanitize_text_field((string) ($item['hora_fin'] ?? '')),
                'zona_horaria' => sanitize_text_field((string) ($item['zona_horaria'] ?? 'America/Montevideo')),
            ];
            if ($meeting['fecha'] !== '') {
                $result[] = $meeting;
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
            $id = absint($item['edicion_id'] ?? 0);
            if ($id < 1 || isset($seen[$id])) {
                continue;
            }
            $result[] = ['edicion_id' => $id, 'orden' => absint($item['orden'] ?? count($result) + 1)];
            $seen[$id] = true;
        }
        usort($result, static function (array $a, array $b): int { return $a['orden'] <=> $b['orden']; });
        return $result;
    }

    public static function accepts_registration(int $edition_id, ?int $timestamp = null): bool {
        if (self::sanitize_state(get_post_meta($edition_id, 'estado', true)) === 'cancelada') {
            return false;
        }
        $timestamp = $timestamp ?? current_time('timestamp', true);
        $from = strtotime((string) get_post_meta($edition_id, 'preinscripcion_desde', true));
        $until = strtotime((string) get_post_meta($edition_id, 'preinscripcion_hasta', true));
        return (!$from || $timestamp >= $from) && (!$until || $timestamp < $until);
    }
}
