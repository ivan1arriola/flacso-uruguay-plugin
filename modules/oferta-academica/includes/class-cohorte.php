<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Ocurrencia temporal de una OfertaAcademica. */
final class FLACSO_Cohorte {
    public const POST_TYPE = 'cohorte';
    public const META_PARENT_ID = 'oferta_academica_id';
    public const ESTADOS = ['planificada', 'en_curso', 'finalizada', 'cancelada'];

    public static function register(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Cohortes', 'flacso-uruguay'),
                'singular_name' => __('Cohorte', 'flacso-uruguay'),
                'add_new_item' => __('Agregar cohorte', 'flacso-uruguay'),
                'edit_item' => __('Editar cohorte', 'flacso-uruguay'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=oferta-academica',
            'show_in_rest' => false,
            'supports' => ['title', 'revisions'],
            'rewrite' => false,
            'query_var' => false,
            'map_meta_cap' => true,
        ]);

        $definitions = [
            self::META_PARENT_ID => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'anio' => ['type' => 'integer', 'sanitize_callback' => [self::class, 'sanitize_year']],
            'periodo' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'numero' => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'fecha_inicio' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_date']],
            'fecha_fin' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_date']],
            'precision_fecha_inicio' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_precision']],
            'estado' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_state']],
            'calendario_academico' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'modalidad' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'tabla_precio_id' => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'preinscripcion_desde' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_datetime']],
            'preinscripcion_hasta' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_datetime']],
            'mensaje_preinscripcion_abierta' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'mensaje_preinscripcion_cerrada' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
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

    public static function sanitize_precision($value): string {
        $precision = sanitize_key((string) $value);
        return in_array($precision, ['dia', 'mes', 'semestre', 'anio'], true) ? $precision : 'dia';
    }

    public static function sanitize_date($value): string {
        $value = sanitize_text_field((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    public static function sanitize_datetime($value): string {
        $value = sanitize_text_field((string) $value);
        return $value !== '' && strtotime($value) !== false ? $value : '';
    }

    public static function accepts_registration(int $cohort_id, ?int $timestamp = null): bool {
        if (self::sanitize_state(get_post_meta($cohort_id, 'estado', true)) === 'cancelada') {
            return false;
        }
        $timestamp = $timestamp ?? current_time('timestamp', true);
        $from = strtotime((string) get_post_meta($cohort_id, 'preinscripcion_desde', true));
        $until = strtotime((string) get_post_meta($cohort_id, 'preinscripcion_hasta', true));
        return (!$from || $timestamp >= $from) && (!$until || $timestamp < $until);
    }
}
