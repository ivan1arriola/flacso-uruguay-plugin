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
            'meta_query' => [
                'relation' => 'AND',
                ['key' => FLACSO_Instancia_Oferta::META_ESTADO, 'value' => FLACSO_Instancia_Oferta::ESTADO_ABIERTA, 'compare' => '='],
                ['key' => FLACSO_Instancia_Oferta::META_FLUJO, 'value' => FLACSO_Preinscription_Flow::GESTOR_PREINSCRIPCIONES, 'compare' => '='],
            ],
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
        if (FLACSO_Instancia_Oferta::get_state($instance_id) !== FLACSO_Instancia_Oferta::ESTADO_ABIERTA
            || FLACSO_Instancia_Oferta::get_flow($instance_id) !== FLACSO_Preinscription_Flow::GESTOR_PREINSCRIPCIONES) {
            return null;
        }

        $offer_id = FLACSO_Instancia_Oferta::get_offer_id($instance_id);
        $offer = get_post($offer_id);
        if (!$offer || !in_array($offer->post_type, ['oferta-academica', 'seminario'], true)
            || $offer->post_status !== 'publish' || !empty($offer->post_password)) {
            return null;
        }

        $instance = get_post($instance_id);
        if (!$instance || $instance->post_type !== FLACSO_Instancia_Oferta::POST_TYPE || $instance->post_status !== 'publish') {
            return null;
        }

        $type = $offer->post_type === 'seminario' ? 'seminario' : '';
        if ($type === '') {
            $terms = wp_get_object_terms($offer_id, 'tipo-oferta-academica');
            if (!is_wp_error($terms) && !empty($terms)) {
                $type = sanitize_key((string) $terms[0]->slug);
            }
        }

        return [
            'oferta' => [
                'id' => $offer_id,
                'titulo' => get_the_title($offer_id),
                'slug' => (string) $offer->post_name,
                'tipo' => $type,
                'url_informacion' => (string) get_permalink($offer_id),
                'correo' => sanitize_email((string) get_post_meta(
                    $offer_id,
                    $offer->post_type === 'seminario' ? 'correo_contacto' : 'correo',
                    true
                )),
            ],
            'instancia' => [
                'id' => $instance_id,
                'nombre' => (string) $instance->post_title,
                'anio' => absint(get_post_meta($instance_id, FLACSO_Instancia_Oferta::META_ANIO, true)),
                'semestre' => (string) get_post_meta($instance_id, FLACSO_Instancia_Oferta::META_SEMESTRE, true),
                'numero' => max(1, absint(get_post_meta($instance_id, FLACSO_Instancia_Oferta::META_NUMERO, true))),
                'estado' => FLACSO_Instancia_Oferta::ESTADO_ABIERTA,
                'actualizado' => mysql_to_rfc3339((string) $instance->post_modified_gmt),
            ],
        ];
    }
}
