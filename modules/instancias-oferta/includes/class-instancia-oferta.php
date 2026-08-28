<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * InstanciaOferta representa una Cohorte o Edicion con identidad WordPress propia.
 * La etiqueta visible se deriva del tipo de Oferta, pero la persistencia es unica.
 */
final class FLACSO_Instancia_Oferta {
    private const MIGRATION_OPTION = 'flacso_instancias_oferta_schema_version';
    private const SCHEMA_VERSION = 1;
    public const POST_TYPE = 'instancia-oferta';
    public const ESTADO_PLANIFICADA = 'planificada';
    public const ESTADO_ABIERTA = 'preinscripciones_abiertas';
    public const ESTADO_CERRADA = 'preinscripciones_cerradas';
    public const ESTADO_EN_CURSO = 'en_curso';
    public const ESTADO_FINALIZADA = 'finalizada';
    public const ESTADO_CANCELADA = 'cancelada';

    public const META_OFERTA_ID = 'oferta_academica_id';
    public const META_ANIO = 'anio';
    public const META_SEMESTRE = 'semestre';
    public const META_NUMERO = 'numero';
    public const META_FECHA_INICIO = 'fecha_inicio';
    public const META_FECHA_FIN = 'fecha_fin';
    public const META_ESTADO = 'estado';
    public const META_FLUJO = 'flujo_preinscripcion';
    public const META_INSCRIPCION_INICIO = 'inscripcion_fecha_inicio';
    public const META_INSCRIPCION_FIN = 'inscripcion_fecha_fin';
    public const META_MENSAJE_ABIERTA = 'mensaje_inscripcion_abierta';
    public const META_MENSAJE_CERRADA = 'mensaje_inscripcion_cerrada';
    public const META_FLUJO_BLOQUEADO = '_flujo_preinscripcion_bloqueado';

    public static function init(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Cohortes y Ediciones', 'flacso-uruguay'),
                'singular_name' => __('Cohorte o Edicion', 'flacso-uruguay'),
            ],
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'revisions'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);

        foreach (self::meta_schema() as $key => $definition) {
            register_post_meta(self::POST_TYPE, $key, array_merge([
                'single' => true,
                'show_in_rest' => false,
                'auth_callback' => static function (): bool {
                    return current_user_can('edit_posts');
                },
            ], $definition));
        }
    }

    public static function estados(): array {
        return [
            self::ESTADO_PLANIFICADA,
            self::ESTADO_ABIERTA,
            self::ESTADO_CERRADA,
            self::ESTADO_EN_CURSO,
            self::ESTADO_FINALIZADA,
            self::ESTADO_CANCELADA,
        ];
    }

    /** Backfill idempotente: ninguna instancia previa cambia de circuito. */
    public static function migrate_defaults(): void {
        if (absint(get_option(self::MIGRATION_OPTION, 0)) >= self::SCHEMA_VERSION) {
            return;
        }
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        foreach ($ids as $id) {
            $id = absint($id);
            if (!FLACSO_Preinscription_Flow::is_valid(get_post_meta($id, self::META_FLUJO, true))) {
                update_post_meta($id, self::META_FLUJO, FLACSO_Preinscription_Flow::LEGACY_EDITOR);
            }
            if (self::get_state($id) === self::ESTADO_ABIERTA) {
                update_post_meta($id, self::META_FLUJO_BLOQUEADO, true);
            }
        }
        update_option(self::MIGRATION_OPTION, self::SCHEMA_VERSION, false);
    }

    public static function meta_schema(): array {
        return [
            self::META_OFERTA_ID => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            self::META_ANIO => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            self::META_SEMESTRE => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            self::META_NUMERO => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            self::META_FECHA_INICIO => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            self::META_FECHA_FIN => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            self::META_ESTADO => ['type' => 'string', 'sanitize_callback' => 'sanitize_key'],
            self::META_FLUJO => ['type' => 'string', 'sanitize_callback' => [FLACSO_Preinscription_Flow::class, 'normalize']],
            self::META_INSCRIPCION_INICIO => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            self::META_INSCRIPCION_FIN => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            self::META_MENSAJE_ABIERTA => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
            self::META_MENSAJE_CERRADA => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
            self::META_FLUJO_BLOQUEADO => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
        ];
    }

    public static function get_flow(int $instance_id): string {
        return FLACSO_Preinscription_Flow::normalize(
            get_post_meta($instance_id, self::META_FLUJO, true)
        );
    }

    public static function get_offer_id(int $instance_id): int {
        return absint(get_post_meta($instance_id, self::META_OFERTA_ID, true));
    }

    public static function get_state(int $instance_id): string {
        $state = sanitize_key((string) get_post_meta($instance_id, self::META_ESTADO, true));
        return in_array($state, self::estados(), true) ? $state : self::ESTADO_PLANIFICADA;
    }

    public static function is_flow_locked(int $instance_id): bool {
        return self::get_state($instance_id) === self::ESTADO_ABIERTA
            || rest_sanitize_boolean(get_post_meta($instance_id, self::META_FLUJO_BLOQUEADO, true));
    }

    public static function find_open_for_offer(int $offer_id): int {
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => self::META_OFERTA_ID, 'value' => $offer_id, 'compare' => '=', 'type' => 'NUMERIC'],
                ['key' => self::META_ESTADO, 'value' => self::ESTADO_ABIERTA, 'compare' => '='],
            ],
            'no_found_rows' => true,
        ]);
        return !empty($ids) ? absint($ids[0]) : 0;
    }

    public static function find_latest_for_offer(int $offer_id): int {
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_query' => [[
                'key' => self::META_OFERTA_ID,
                'value' => $offer_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ]],
            'no_found_rows' => true,
        ]);
        return !empty($ids) ? absint($ids[0]) : 0;
    }

    public static function close_other_open_instances(int $offer_id, int $except_id): void {
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post__not_in' => $except_id > 0 ? [$except_id] : [],
            'meta_query' => [
                'relation' => 'AND',
                ['key' => self::META_OFERTA_ID, 'value' => $offer_id, 'compare' => '=', 'type' => 'NUMERIC'],
                ['key' => self::META_ESTADO, 'value' => self::ESTADO_ABIERTA, 'compare' => '='],
            ],
            'no_found_rows' => true,
        ]);
        foreach ($ids as $id) {
            update_post_meta(absint($id), self::META_ESTADO, self::ESTADO_CERRADA);
            update_post_meta(absint($id), self::META_FLUJO_BLOQUEADO, true);
        }
    }
}
