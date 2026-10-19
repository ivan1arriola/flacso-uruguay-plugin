<?php
/**
 * Pruebas de regresión del listado administrativo de Ofertas Académicas.
 * Se ejecuta sin bootstrap de WordPress para validar el contrato de la vista.
 */
$root = dirname(__DIR__);
if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}

if (!class_exists('WP_Post')) {
    class WP_Post {
        public int $ID;
        public string $post_type;
        public function __construct(int $id, string $post_type = 'oferta-academica') {
            $this->ID = $id;
            $this->post_type = $post_type;
        }
    }
}

$GLOBALS['flacso_list_test_meta'] = [
    101 => ['abreviacion' => 'DAVIA'],
    102 => ['abreviacion' => '  MESYP  '],
    103 => ['abreviacion' => ''],
    104 => ['abreviacion' => '<script>alert(1)</script>'],
];
if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key, $single = false) {
        return $GLOBALS['flacso_list_test_meta'][$post_id][$key] ?? '';
    }
}
if (!function_exists('esc_html')) {
    function esc_html($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
require_once $root . '/modules/oferta-academica/includes/class-cpt-oferta-academica.php';

function flacso_list_ui_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . "\n");
        exit(1);
    }
}

$base = ['publish' => 'Publicado'];
$with_abbreviation = CPT_Oferta_Academica::append_abbreviation_post_state($base, new WP_Post(101));
flacso_list_ui_assert($with_abbreviation['publish'] === 'Publicado', 'conserva estados de WordPress');
flacso_list_ui_assert($with_abbreviation['flacso_abbreviation'] === 'DAVIA', 'muestra abreviación original');
flacso_list_ui_assert(CPT_Oferta_Academica::append_abbreviation_post_state([], new WP_Post(102))['flacso_abbreviation'] === 'MESYP', 'recorta espacios');
flacso_list_ui_assert(CPT_Oferta_Academica::append_abbreviation_post_state($base, new WP_Post(103)) === $base, 'no muestra badge vacío');
flacso_list_ui_assert(CPT_Oferta_Academica::append_abbreviation_post_state($base, new WP_Post(101, 'post')) === $base, 'no altera otros tipos de contenido');

$escaped = CPT_Oferta_Academica::append_abbreviation_post_state([], new WP_Post(104));
flacso_list_ui_assert(strpos($escaped['flacso_abbreviation'], '<script>') === false, 'escapa HTML de la abreviación');
flacso_list_ui_assert(strpos($escaped['flacso_abbreviation'], '&lt;script&gt;') !== false, 'conserva texto seguro');

$source = file_get_contents($root . '/modules/oferta-academica/includes/class-cpt-oferta-academica.php');
flacso_list_ui_assert(strpos($source, "add_filter('display_post_states'") !== false, 'el badge está registrado en la lista');
flacso_list_ui_assert(strpos($source, '@media (max-width: 782px)') !== false, 'mantiene estilos móviles');
flacso_list_ui_assert(strpos($source, '#posts-filter .search-box') !== false, 'incluye búsqueda adaptable');
echo "OK: admin ofertas responsive y abreviaciones\n";
