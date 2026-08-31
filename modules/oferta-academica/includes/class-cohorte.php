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
            'show_in_menu' => FLACSO_Admin_Panel::PAGE_SLUG,
            'show_in_rest' => false,
            // El titulo se deriva exclusivamente del numero; no se edita.
            'supports' => ['revisions'],
            'rewrite' => false,
            'query_var' => false,
            'map_meta_cap' => true,
        ]);

        $definitions = [
            self::META_PARENT_ID => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'numero' => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'fecha_inicio' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_date']],
            'fecha_fin' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_date']],
            'precision_fecha_inicio' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_precision']],
            'estado' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_state']],
            'calendario_academico' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'tabla_precio_id' => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'link_preinscripcion' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_registration_url']],
            'preinscripcion_desde' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_datetime']],
            'preinscripcion_hasta' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_datetime']],
        ];
        foreach ($definitions as $key => $definition) {
            register_post_meta(self::POST_TYPE, $key, array_merge([
                'single' => true,
                'show_in_rest' => false,
                'auth_callback' => static function (): bool { return current_user_can('edit_posts'); },
            ], $definition));
        }

        add_action('added_post_meta', [self::class, 'sync_title_from_number'], 10, 4);
        add_action('updated_post_meta', [self::class, 'sync_title_from_number'], 10, 4);
    }

    public static function sanitize_state($value): string {
        $state = sanitize_key((string) $value);
        return in_array($state, self::ESTADOS, true) ? $state : 'planificada';
    }

    public static function sanitize_precision($value): string {
        $precision = sanitize_key((string) $value);
        return in_array($precision, ['dia', 'mes', 'anio'], true) ? $precision : 'dia';
    }

    public static function sanitize_date($value): string {
        $value = sanitize_text_field((string) $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    public static function sanitize_datetime($value): string {
        $value = sanitize_text_field((string) $value);
        return $value !== '' && strtotime($value) !== false ? $value : '';
    }

    public static function sanitize_registration_url($value): string {
        $url = esc_url_raw((string) $value, ['https']);
        return wp_parse_url($url, PHP_URL_HOST) === 'preinscripciones.flacso.edu.uy' ? $url : '';
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

    public static function to_roman(int $number): string {
        if ($number < 1) {
            return '';
        }
        $table = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
        ];
        $result = '';
        foreach ($table as $value => $symbol) {
            while ($number >= $value) {
                $result .= $symbol;
                $number -= $value;
            }
        }
        return $result;
    }

    public static function display_name(int $number): string {
        $roman = self::to_roman($number);
        return $roman !== '' ? sprintf('Cohorte %s', $roman) : '';
    }

    public static function sync_title_from_number($meta_id, $post_id, $meta_key, $meta_value): void {
        if ($meta_key !== 'numero' || get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }
        $title = self::display_name(absint($meta_value));
        if ($title !== '' && get_the_title($post_id) !== $title) {
            wp_update_post(['ID' => absint($post_id), 'post_title' => $title]);
        }
    }
}
