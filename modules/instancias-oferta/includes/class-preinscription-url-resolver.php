<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Unica frontera para resolver destinos publicos y administrativos. */
final class FLACSO_Preinscription_URL_Resolver {
    private const GESTOR_BASE_URL = 'https://preinscripciones.flacso.edu.uy';

    public static function resolve(int $instance_id): string {
        $offer_id = FLACSO_Instancia_Oferta::get_offer_id($instance_id);
        if ($offer_id <= 0) {
            return '';
        }

        if (FLACSO_Instancia_Oferta::get_flow($instance_id) === FLACSO_Preinscription_Flow::GESTOR_PREINSCRIPCIONES) {
            $base = untrailingslashit((string) apply_filters(
                'flacso_gestor_preinscripciones_base_url',
                self::GESTOR_BASE_URL
            ));
            return sprintf('%s/ofertas/%d/instancias/%d/', $base, $offer_id, $instance_id);
        }

        $permalink = get_permalink($offer_id);
        return $permalink ? trailingslashit($permalink) . 'preinscripcion/' : '';
    }

    public static function resolve_backoffice(int $instance_id): string {
        $offer_id = FLACSO_Instancia_Oferta::get_offer_id($instance_id);
        if ($offer_id <= 0) {
            return '';
        }
        if (FLACSO_Instancia_Oferta::get_flow($instance_id) === FLACSO_Preinscription_Flow::GESTOR_PREINSCRIPCIONES) {
            $base = untrailingslashit((string) apply_filters(
                'flacso_gestor_preinscripciones_base_url',
                self::GESTOR_BASE_URL
            ));
            return sprintf('%s/gestion/ofertas/%d/instancias/%d/', $base, $offer_id, $instance_id);
        }
        return '';
    }

    public static function append_attribution(string $url): string {
        if ($url === '' || empty($_GET)) {
            return $url;
        }
        $allowed = [
            'campaign_provider', 'campaign_source', 'campaign_medium', 'campaign_name',
            'campaign_external_id', 'campaign_content', 'campaign_term', 'utm_source',
            'utm_medium', 'utm_campaign', 'utm_id', 'utm_content', 'utm_term',
            'gclid', 'gbraid', 'wbraid', 'fbclid',
        ];
        $params = [];
        foreach ($allowed as $key) {
            if (isset($_GET[$key]) && is_scalar($_GET[$key])) {
                $params[$key] = sanitize_text_field(wp_unslash((string) $_GET[$key]));
            }
        }
        return $params ? add_query_arg($params, $url) : $url;
    }
}

/** API estable para temas y otros consumidores dentro de WordPress. */
function flacso_get_preinscription_url(int $instance_id = 0, int $offer_id = 0): string {
    if ($instance_id <= 0) {
        $offer_id = $offer_id > 0 ? $offer_id : absint(get_the_ID());
        $instance_id = FLACSO_Instancia_Oferta::find_open_for_offer($offer_id);
        if ($instance_id <= 0) {
            $instance_id = FLACSO_Instancia_Oferta::find_latest_for_offer($offer_id);
        }
    }
    if ($instance_id > 0) {
        return FLACSO_Preinscription_URL_Resolver::append_attribution(
            FLACSO_Preinscription_URL_Resolver::resolve($instance_id)
        );
    }

    // Compatibilidad: ofertas todavia no migradas conservan el formulario legacy.
    $permalink = $offer_id > 0 ? get_permalink($offer_id) : '';
    return $permalink
        ? FLACSO_Preinscription_URL_Resolver::append_attribution(trailingslashit($permalink) . 'preinscripcion/')
        : '';
}

function flacso_get_open_preinscription_instance(int $offer_id = 0): int {
    $offer_id = $offer_id > 0 ? $offer_id : absint(get_the_ID());
    return FLACSO_Instancia_Oferta::find_open_for_offer($offer_id);
}

function flacso_offer_accepts_preinscriptions(int $offer_id = 0): bool {
    $offer_id = $offer_id > 0 ? $offer_id : absint(get_the_ID());
    $instance_id = FLACSO_Instancia_Oferta::find_open_for_offer($offer_id);
    if ($instance_id > 0) {
        return true;
    }
    if (FLACSO_Instancia_Oferta::find_latest_for_offer($offer_id) > 0) {
        return false;
    }
    $legacy_key = get_post_type($offer_id) === 'seminario'
        ? '_seminario_abierto_publico'
        : 'inscripciones_abiertas';
    $legacy = get_post_meta($offer_id, $legacy_key, true);
    return in_array($legacy, [true, 1, '1', 'true'], true);
}

function flacso_get_preinscription_cta(int $offer_id = 0): array {
    $offer_id = $offer_id > 0 ? $offer_id : absint(get_the_ID());
    $instance_id = FLACSO_Instancia_Oferta::find_open_for_offer($offer_id);
    if ($instance_id > 0) {
        return [
            'instance_id' => $instance_id,
            'is_open' => true,
            'flow' => FLACSO_Instancia_Oferta::get_flow($instance_id),
            'url' => flacso_get_preinscription_url($instance_id, $offer_id),
            'open_message' => (string) get_post_meta($instance_id, FLACSO_Instancia_Oferta::META_MENSAJE_ABIERTA, true),
            'closed_message' => (string) get_post_meta($instance_id, FLACSO_Instancia_Oferta::META_MENSAJE_CERRADA, true),
        ];
    }
    $instance_id = FLACSO_Instancia_Oferta::find_latest_for_offer($offer_id);
    if ($instance_id > 0) {
        return [
            'instance_id' => $instance_id,
            'is_open' => false,
            'flow' => FLACSO_Instancia_Oferta::get_flow($instance_id),
            'url' => flacso_get_preinscription_url($instance_id, $offer_id),
            'open_message' => (string) get_post_meta($instance_id, FLACSO_Instancia_Oferta::META_MENSAJE_ABIERTA, true),
            'closed_message' => (string) get_post_meta($instance_id, FLACSO_Instancia_Oferta::META_MENSAJE_CERRADA, true),
        ];
    }
    return [
        'instance_id' => 0,
        'is_open' => flacso_offer_accepts_preinscriptions($offer_id),
        'flow' => FLACSO_Preinscription_Flow::LEGACY_EDITOR,
        'url' => flacso_get_preinscription_url(0, $offer_id),
        'open_message' => (string) get_post_meta($offer_id, 'inscripciones_mensaje', true),
        'closed_message' => (string) get_post_meta($offer_id, 'inscripciones_mensaje_cerrado', true),
    ];
}
