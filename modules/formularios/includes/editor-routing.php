<?php
/**
 * Ruteo canónico de formularios hacia Editor FLACSO.
 *
 * Los formularios públicos pertenecen al plugin, pero la persistencia de
 * solicitudes y el correo transaccional se procesan en editor.flacso.edu.uy.
 * Este archivo mantiene las opciones históricas como overrides explícitos y
 * completa únicamente las que estén vacías.
 */

if (!defined('ABSPATH')) {
    exit;
}

function fc_get_canonical_editor_base_url(): string {
    $configured = trim((string) get_option('flacso_external_editor_url', ''));
    if ($configured === '') {
        $configured = 'https://editor.flacso.edu.uy';
    }

    $url = esc_url_raw($configured);
    if ($url === '') {
        $url = 'https://editor.flacso.edu.uy';
    }

    return rtrim($url, '/');
}

function fc_get_canonical_info_request_endpoint(): string {
    $base = fc_get_canonical_editor_base_url();

    // Tolerar una configuración antigua que haya guardado el endpoint completo.
    if (preg_match('#/api/consultas/?$#', $base)) {
        return rtrim($base, '/');
    }

    return $base . '/api/consultas';
}

/**
 * Completa rutas faltantes sin sobreescribir configuraciones explícitas.
 *
 * - fc_oferta_webhook_url -> solicitudes de información de ofertas
 * - fc_consultas_webhook_url -> consultas generales; su handler agrega /general
 */
function fc_ensure_info_request_editor_routes(): void {
    $endpoint = fc_get_canonical_info_request_endpoint();

    if (trim((string) get_option('fc_oferta_webhook_url', '')) === '') {
        update_option('fc_oferta_webhook_url', $endpoint, false);
    }

    if (trim((string) get_option('fc_consultas_webhook_url', '')) === '') {
        update_option('fc_consultas_webhook_url', $endpoint, false);
    }
}

add_action('init', 'fc_ensure_info_request_editor_routes', 1);
