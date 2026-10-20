<?php
/**
 * Verifica el render responsive del metabox de cohortes sin WordPress real.
 * Las tarjetas no deben forzar tablas de cinco columnas en móvil.
 */
$root = dirname(__DIR__);
if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}

$GLOBALS['flacso_test_cohortes'] = [
    (object) ['ID' => 201],
    (object) ['ID' => 202],
];
$GLOBALS['flacso_test_cohorte_meta'] = [
    201 => ['numero' => 2, 'estado' => 'en_curso', 'link_preinscripcion' => 'https://preinscripciones.flacso.edu.uy/a'],
    202 => ['numero' => 3, 'estado' => 'planificada', 'link_preinscripcion' => ''],
];
function get_posts($args) { return $GLOBALS['flacso_test_cohortes']; }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['flacso_test_cohorte_meta'][$id][$key] ?? ''; }
function admin_url($path) { return 'https://flacso.edu.uy/wp-admin/' . $path; }
function get_edit_post_link($id) { return admin_url('post.php?post=' . $id . '&action=edit'); }
function __( $text, $domain = '' ) { return $text; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return esc_html($text); }
function esc_url($text) { return esc_attr($text); }
function esc_html_e($text, $domain = '') { echo esc_html($text); }
function esc_attr_e($text, $domain = '') { echo esc_attr($text); }
function get_current_screen() { return (object) ['post_type' => 'oferta-academica']; }

class FLACSO_Cohorte {
    public static function to_roman(int $number): string {
        return [2 => 'II', 3 => 'III'][$number] ?? '';
    }
    public static function sanitize_state($state): string { return (string) $state; }
    public static function format_dates(int $id): string {
        return $id === 201 ? '29 de julio de 2026' : '<script>alert(1)</script>';
    }
    public static function accepts_registration(int $id): bool { return $id === 201; }
}

require_once $root . '/modules/oferta-academica/includes/class-cpt-oferta-academica.php';

function flacso_cohort_ui_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

ob_start();
CPT_Oferta_Academica::render_cohortes_meta_box((object) ['ID' => 4]);
$html = ob_get_clean();

flacso_cohort_ui_assert(strpos($html, 'class="flacso-cohortes-list"') !== false, 'renderiza el listado en tarjetas');
flacso_cohort_ui_assert(substr_count($html, '<li class="flacso-cohortes-card">') === 2, 'renderiza una tarjeta por cohorte');
flacso_cohort_ui_assert(strpos($html, '<table') === false, 'no genera tablas anchas de escritorio');
flacso_cohort_ui_assert(strpos($html, 'Cohorte II') !== false, 'mantiene números romanos');
flacso_cohort_ui_assert(strpos($html, 'En curso') !== false, 'humaniza estado en curso');
flacso_cohort_ui_assert(strpos($html, 'Planificada') !== false, 'conserva estados académicos');
flacso_cohort_ui_assert(strpos($html, '29 de julio de 2026') !== false, 'mantiene fechas');
flacso_cohort_ui_assert(strpos($html, 'flacso-cohortes-card__registration--open') !== false, 'conserva estado de preinscripción');
flacso_cohort_ui_assert(strpos($html, 'preinscripciones.flacso.edu.uy/a') !== false, 'conserva acceso al portal');
flacso_cohort_ui_assert(strpos($html, 'rel="noopener noreferrer"') !== false, 'protege enlace externo');
flacso_cohort_ui_assert(substr_count($html, 'Editar cohorte') === 2, 'conserva enlaces de edición');
flacso_cohort_ui_assert(strpos($html, 'Agregar nueva cohorte para esta oferta') !== false, 'conserva creación');
flacso_cohort_ui_assert(strpos($html, '<script>') === false, 'escapa fechas maliciosas');
flacso_cohort_ui_assert(strpos($html, '&lt;script&gt;') !== false, 'escapa correctamente el contenido');

ob_start();
CPT_Oferta_Academica::render_cohortes_admin_styles();
$css = ob_get_clean();
flacso_cohort_ui_assert(strpos($css, '#flacso_oferta_cohortes_box .flacso-cohortes-list') !== false, 'CSS exclusivo del metabox');
flacso_cohort_ui_assert(strpos($css, 'grid-template-columns: repeat(auto-fit') !== false, 'se adapta al ancho disponible');
flacso_cohort_ui_assert(strpos($css, '@media (max-width: 782px)') !== false, 'incluye vista móvil');
flacso_cohort_ui_assert(strpos($css, 'white-space: normal') !== false, 'botones admiten salto de línea');
flacso_cohort_ui_assert(strpos($css, 'max-width: 100%') !== false, 'limita ancho del panel');

echo "OK oferta cohortes responsive metabox\n";
