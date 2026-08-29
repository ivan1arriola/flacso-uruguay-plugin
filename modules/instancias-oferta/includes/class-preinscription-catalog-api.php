<?php

if (!defined('ABSPATH')) {
    exit;
}

/** DTO minimo publico consumido exclusivamente por el Gestor de Preinscripciones. */
final class FLACSO_Preinscription_Catalog_API {
    private const REST_NAMESPACE = 'flacso/v1';
    private const ROUTE = '/preinscripciones/catalogo';

    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, self::ROUTE, [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_catalog'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function get_catalog() {
        $ids = get_posts([
            'post_type' => FLACSO_Instancia_Oferta::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_query' => [[
                'key' => FLACSO_Instancia_Oferta::META_FLUJO,
                'value' => FLACSO_Preinscription_Flow::GESTOR_PREINSCRIPCIONES,
                'compare' => '=',
            ]],
            'no_found_rows' => true,
        ]);

        $items = [];
        $offers_seen = [];
        foreach ($ids as $instance_id) {
            $item = self::build_item(absint($instance_id));
            if (!$item) {
                continue;
            }
            $offer_id = absint($item['oferta']['id']);
            if (isset($offers_seen[$offer_id])) {
                continue;
            }
            $offers_seen[$offer_id] = true;
            $items[] = $item;
        }

        return rest_ensure_response(['schema_version' => 1, 'items' => $items]);
    }

    public static function build_item(int $instance_id): ?array {
        if (!FLACSO_Instancia_Oferta::acepta_preinscripciones($instance_id)
            || FLACSO_Instancia_Oferta::get_flow($instance_id) !== FLACSO_Preinscription_Flow::GESTOR_PREINSCRIPCIONES) {
            return null;
        }

        $offer_id = FLACSO_Instancia_Oferta::get_offer_id($instance_id);
        $offer = get_post($offer_id);
        if (!$offer || $offer->post_type !== FLACSO_Oferta_Academica::POST_TYPE
            || $offer->post_status !== 'publish' || !empty($offer->post_password)) {
            return null;
        }

        $instance = get_post($instance_id);
        if (!$instance || $instance->post_type !== FLACSO_Instancia_Oferta::POST_TYPE || $instance->post_status !== 'publish') {
            return null;
        }

        $type = FLACSO_Oferta_Academica::get_tipo($offer_id);

        return [
            'oferta' => [
                'id' => $offer_id,
                'titulo' => get_the_title($offer_id),
                'slug' => (string) $offer->post_name,
                'tipo' => $type,
                'url_informacion' => (string) get_permalink($offer_id),
                'correo' => sanitize_email((string) get_post_meta(
                    $offer_id,
                    'correo',
                    true
                )),
            ],
            'instancia' => [
                'id' => $instance_id,
                'nombre' => (string) $instance->post_title,
                'anio' => ($year = absint(get_post_meta($instance_id, FLACSO_Instancia_Oferta::META_ANIO, true))) > 0 ? $year : null,
                'semestre' => (string) get_post_meta($instance_id, FLACSO_Instancia_Oferta::META_SEMESTRE, true),
                'numero' => ($number = absint(get_post_meta($instance_id, FLACSO_Instancia_Oferta::META_NUMERO, true))) > 0 ? $number : null,
                'estado' => FLACSO_Instancia_Oferta::get_state($instance_id),
                'preinscripcion_apertura' => (string) get_post_meta($instance_id, FLACSO_Instancia_Oferta::META_PREINSCRIPCION_APERTURA, true),
                'preinscripcion_cierre_efectivo' => FLACSO_Instancia_Oferta::get_preinscripcion_cierre_efectivo($instance_id),
                'actualizado' => mysql_to_rfc3339((string) $instance->post_modified_gmt),
            ],
        ];
    }
}
