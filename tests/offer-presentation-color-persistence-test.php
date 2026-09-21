<?php

define('ABSPATH', __DIR__ . '/../');

class WP_Post {
    public string $post_type = 'oferta-academica';
}

$GLOBALS['color_test_meta'] = [];

function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_email($value) { return filter_var((string) $value, FILTER_SANITIZE_EMAIL); }
function wp_kses_post($value) { return (string) $value; }
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function esc_url_raw($value, $protocols = null) { return (string) $value; }
function wp_unslash($value) { return $value; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'ok'; }
function wp_is_post_revision($id) { return false; }
function current_user_can($capability, ...$args) { return true; }
function update_post_meta($id, $key, $value) { $GLOBALS['color_test_meta'][(int) $id][$key] = $value; return true; }
function delete_post_meta($id, $key) { unset($GLOBALS['color_test_meta'][(int) $id][$key]); return true; }
function wp_set_object_terms($id, $terms, $taxonomy) { return true; }

require_once __DIR__ . '/../modules/oferta-academica/includes/class-oferta-academica.php';
require_once __DIR__ . '/../modules/oferta-academica/includes/class-oferta-admin-fields.php';

function color_persistence_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$post = new WP_Post();

$_POST = [
    'flacso_oferta_academica_nonce' => 'ok',
    'flacso_oferta' => [],
    'flacso_oferta_lists' => [],
    'flacso_oferta_color_principal' => '#0057A8',
];

FLACSO_Oferta_Admin_Fields::save(321, $post);

color_persistence_assert(
    ($GLOBALS['color_test_meta'][321][FLACSO_Oferta_Academica::META_PRESENTATION_COLORS] ?? null)
        === ['principal' => '#0057a8', 'secundarios' => []],
    'un color válido debe persistirse con el contrato completo'
);

$_POST['flacso_oferta_color_principal'] = '#XYZ123';
FLACSO_Oferta_Admin_Fields::save(321, $post);

color_persistence_assert(
    !array_key_exists(FLACSO_Oferta_Academica::META_PRESENTATION_COLORS, $GLOBALS['color_test_meta'][321] ?? []),
    'un color inválido debe descartarse en vez de persistirse'
);

echo "OK offer presentation color persistence\n";
