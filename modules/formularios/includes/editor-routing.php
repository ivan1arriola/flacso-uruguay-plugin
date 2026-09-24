<?php
/**
 * Ruteo de compatibilidad para la consulta general.
 *
 * Las consultas de ofertas y seminarios ya se procesan internamente en
 * WordPress. Solo la consulta general conserva por ahora un destino externo.
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
 * Completa únicamente la ruta de la consulta general y corrige el host Vercel
 * legado. No toca fc_oferta_webhook_url: ese flujo fue desmantelado.
 */
function fc_ensure_info_request_editor_routes(): void {
    $endpoint = fc_get_canonical_info_request_endpoint();
    $general_endpoint = (string) get_option('fc_consultas_webhook_url', '');

    if (fc_should_repair_info_request_endpoint($general_endpoint)) {
        update_option('fc_consultas_webhook_url', $endpoint, false);
    }
}

add_action('init', 'fc_ensure_info_request_editor_routes', 1);
