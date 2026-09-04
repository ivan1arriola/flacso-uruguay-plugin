<?php
/**
 * Ruteo canónico de formularios hacia Editor FLACSO.
 *
 * Los formularios públicos pertenecen al plugin, pero la persistencia de
 * solicitudes y el correo transaccional se procesan en editor.flacso.edu.uy.
 * Las opciones históricas siguen funcionando como overrides explícitos, salvo
 * el host Vercel legado, que se migra al Editor de producción actual.
 */

if (!defined('ABSPATH')) {
    exit;
}

function fc_is_legacy_editor_url(string $url): bool {
    $host = strtolower((string) parse_url(trim($url), PHP_URL_HOST));
    return $host === 'editor-flacso-uy.vercel.app';
}

function fc_get_canonical_editor_base_url(): string {
    $configured = trim((string) get_option('flacso_external_editor_url', ''));
    if ($configured === '' || fc_is_legacy_editor_url($configured)) {
        $configured = 'https://editor.flacso.edu.uy';
    }

    $url = esc_url_raw($configured);
    if ($url === '' || fc_is_legacy_editor_url($url)) {
        $url = 'https://editor.flacso.edu.uy';
    }

    return rtrim($url, '/');
}

function fc_get_canonical_info_request_endpoint(): string {
    $base = fc_get_canonical_editor_base_url();

    // Tolerar una configuración que ya haya guardado el endpoint completo.
    if (preg_match('#/api/consultas/?$#', $base)) {
        return rtrim($base, '/');
    }

    return $base . '/api/consultas';
}

function fc_should_repair_info_request_endpoint(string $current): bool {
    $current = trim($current);
    return $current === '' || fc_is_legacy_editor_url($current);
}

/**
 * Completa rutas faltantes y corrige únicamente el host Vercel legado.
 * Otros endpoints configurados explícitamente se preservan.
 *
 * - fc_oferta_webhook_url -> solicitudes de información de ofertas
 * - fc_consultas_webhook_url -> consultas generales; su handler agrega /general
 */
function fc_ensure_info_request_editor_routes(): void {
    $endpoint = fc_get_canonical_info_request_endpoint();

    $offer_endpoint = (string) get_option('fc_oferta_webhook_url', '');
    if (fc_should_repair_info_request_endpoint($offer_endpoint)) {
        update_option('fc_oferta_webhook_url', $endpoint, false);
    }

    $general_endpoint = (string) get_option('fc_consultas_webhook_url', '');
    if (fc_should_repair_info_request_endpoint($general_endpoint)) {
        update_option('fc_consultas_webhook_url', $endpoint, false);
    }
}

add_action('init', 'fc_ensure_info_request_editor_routes', 1);
