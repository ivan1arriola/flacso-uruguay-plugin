<?php
/**
 * Regresión del acceso explícito a editar la cohorte en el listado de ofertas.
 * Simula únicamente las dependencias de WordPress usadas por esa columna.
 */
$root = dirname(__DIR__);
if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}

if (!class_exists('WP_Post')) {
    class WP_Post {
        public int $ID;
        public function __construct(int $id) { $this->ID = $id; }
    }
}
$GLOBALS['flacso_test_current_cohorts'] = [new WP_Post(202)];
$GLOBALS['flacso_test_can_edit_cohort'] = true;
$GLOBALS['flacso_test_edit_url_exists'] = true;

function get_posts($args) { return $GLOBALS['flacso_test_current_cohorts']; }
function get_post_meta($id, $key, $single = true) {
    $meta = [
        202 => ['numero' => 6, 'estado' => 'planificada', 'fecha_inicio' => '2027-04-01'],
    ];
    return $meta[$id][$key] ?? '';
}
function get_the_title($id) { return 'Cohorte'; }
function admin_url($path) { return 'https://flacso.edu.uy/wp-admin/' . $path; }
function get_edit_post_link($id) {
    return $GLOBALS['flacso_test_edit_url_exists'] ? admin_url('post.php?post=' . $id . '&action=edit') : '';
}
function current_user_can($capability, $id = null) {
    return $capability === 'edit_post' && $id === 202 && $GLOBALS['flacso_test_can_edit_cohort'];
}
function __($value, $domain = '') { return $value; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html__($value, $domain = '') { return esc_html($value); }
function esc_url($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
class FLACSO_Cohorte {
    public static function sanitize_state($state) { return (string) $state; }
    public static function to_roman($n) { return $n === 6 ? 'VI' : ''; }
    public static function format_dates($id) { return 'abril 2027'; }
}
require_once $root . '/modules/oferta-academica/includes/class-cpt-oferta-academica.php';

function edit_cohort_assert(bool $value, string $description): void {
    if (!$value) {
        fwrite(STDERR, 'FAIL: ' . $description . "\n");
        exit(1);
    }
}

ob_start();
CPT_Oferta_Academica::render_column('cohorte_actual', 42);
$html = ob_get_clean();
edit_cohort_assert(strpos($html, 'Cohorte VI') !== false, 'conserva el identificador de cohorte actual');
edit_cohort_assert(substr_count($html, 'Editar cohorte') === 1, 'muestra un botón explícito sin duplicados');
edit_cohort_assert(strpos($html, 'class="button button-small flacso-edit-cohort"') !== false, 'usa el estilo de acción administrativa');
edit_cohort_assert(strpos($html, 'post.php?post=202&amp;action=edit') !== false, 'edita el ID de la cohorte mostrada');
edit_cohort_assert(strpos($html, 'abril 2027') !== false, 'mantiene los datos de la cohorte');

$GLOBALS['flacso_test_can_edit_cohort'] = false;
ob_start();
CPT_Oferta_Academica::render_column('cohorte_actual', 42);
$html = ob_get_clean();
edit_cohort_assert(strpos($html, 'Editar cohorte') === false, 'no muestra acción sin permiso de edición');

$GLOBALS['flacso_test_can_edit_cohort'] = true;
$GLOBALS['flacso_test_edit_url_exists'] = false;
ob_start();
CPT_Oferta_Academica::render_column('cohorte_actual', 42);
$html = ob_get_clean();
edit_cohort_assert(strpos($html, 'Editar cohorte') === false, 'no muestra botón si WP no proporciona URL');

$GLOBALS['flacso_test_edit_url_exists'] = true;
$GLOBALS['flacso_test_current_cohorts'] = [];
ob_start();
CPT_Oferta_Academica::render_column('cohorte_actual', 42);
$html = ob_get_clean();
edit_cohort_assert(strpos($html, 'Crear cohorte') !== false, 'mantiene Crear cohorte cuando no existe');
edit_cohort_assert(strpos($html, 'Editar cohorte') === false, 'no ofrece editar una cohorte inexistente');

echo "OK: listado de ofertas — botón Editar cohorte\n";
