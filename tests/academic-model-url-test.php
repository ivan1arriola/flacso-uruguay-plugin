<?php

define('ABSPATH', __DIR__ . '/../');
define('OBJECT', 'OBJECT');

$GLOBALS['url_posts'] = [
    10 => (object) ['ID' => 10, 'post_type' => 'oferta-academica', 'post_name' => 'seminario-prueba'],
    11 => (object) ['ID' => 11, 'post_type' => 'seminario', 'post_name' => 'legacy-prueba'],
];
$GLOBALS['url_types'] = [10 => 'seminario'];

function __($text, $domain = null) { return $text; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_title($value) { return strtolower(trim(preg_replace('/[^a-z0-9\-]+/', '-', (string) $value), '-')); }
function get_post_type($id) { return $GLOBALS['url_posts'][(int) $id]->post_type ?? null; }
function wp_get_object_terms($id, $taxonomy) { return isset($GLOBALS['url_types'][(int) $id]) ? [(object) ['slug' => $GLOBALS['url_types'][(int) $id]]] : []; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function get_page_by_path($slug, $output, $post_type) {
    foreach ($GLOBALS['url_posts'] as $post) if ($post->post_type === $post_type && $post->post_name === $slug) return $post;
    return null;
}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
class WP_Error {}

require_once __DIR__ . '/../modules/oferta-academica/includes/class-oferta-academica.php';
require_once __DIR__ . '/../modules/oferta-academica/includes/class-cpt-oferta-academica.php';

function url_assert_same($expected, $actual, string $label): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "Fallo {$label}: esperado " . var_export($expected, true) . ', obtuve ' . var_export($actual, true) . "\n");
        exit(1);
    }
}

$link = CPT_Oferta_Academica::oferta_academica_permalink(
    'https://flacso.edu.uy/formacion/%tipo-oferta-academica%/seminario-prueba/',
    (object) ['ID' => 10, 'post_type' => 'oferta-academica']
);
url_assert_same('https://flacso.edu.uy/formacion/seminarios/seminario-prueba/', $link, 'URL publica legacy preservada');
$resolved = CPT_Oferta_Academica::resolve_migrated_seminar_request(['seminario' => 'seminario-prueba']);
url_assert_same('oferta-academica', $resolved['post_type'], 'rewrite resuelve al modelo unificado');
url_assert_same('seminario-prueba', $resolved['name'], 'rewrite conserva slug');
$legacy = CPT_Oferta_Academica::resolve_migrated_seminar_request(['seminario' => 'legacy-prueba']);
url_assert_same('legacy-prueba', $legacy['seminario'], 'CPT legacy sigue resolviendo antes de migrar');
url_assert_same('doctorados', FLACSO_Oferta_Academica::segmentos_url()['doctorado'], 'URL doctorado soportada');

fwrite(STDOUT, "OK academic model URLs\n");
