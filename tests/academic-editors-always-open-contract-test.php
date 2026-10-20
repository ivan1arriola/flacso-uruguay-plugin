<?php
/**
 * El editor siempre muestra secciones en Oferta y Cohorte.
 * Seminario y Edición conservan secciones HTML normales y metaboxes visibles.
 * Otros editores académicos conservan su propio comportamiento.
 */
$root = dirname(__DIR__);
if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}
$GLOBALS['flacso_static_screen'] = 'cohorte';
function get_current_screen() {
    return (object) ['post_type' => $GLOBALS['flacso_static_screen']];
}
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function sanitize_html_class($value) { return preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $value); }
function __($text, $domain = '') { return $text; }

require_once $root . '/modules/oferta-academica/includes/class-academic-admin-ui.php';

function static_editor_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

foreach (['oferta-academica', 'cohorte', 'seminario', 'edicion'] as $post_type) {
    $GLOBALS['flacso_static_screen'] = $post_type;
    ob_start();
    FLACSO_Academic_Admin_UI::render_static_postboxes();
    $guard = ob_get_clean();
    static_editor_assert(strpos($guard, '#poststuff .postbox[id^="flacso_"]') !== false, $post_type . ': desactiva el colapso de metaboxes FLACSO');
    static_editor_assert(strpos($guard, "box.classList.remove('closed')") !== false, $post_type . ': reabre metaboxes previamente cerrados');
    static_editor_assert(strpos($guard, 'stopImmediatePropagation') !== false, $post_type . ': impide el plegado por clic');
    static_editor_assert(strpos($guard, "event.key !== 'Enter'") !== false, $post_type . ': impide el plegado por teclado');
}

$GLOBALS['flacso_static_screen'] = 'cohorte';
ob_start();
FLACSO_Academic_Admin_UI::section_start('prueba-seccion', 'Datos de la cohorte', 'Campos editables', 'dashicons-admin-home', '3/4');
echo '<input type="text" name="prueba" value="conservado">';
FLACSO_Academic_Admin_UI::section_end();
$cohort = ob_get_clean();
static_editor_assert(strpos($cohort, '<section class="flacso-academic-section"') !== false, 'Cohorte usa sección permanente');
static_editor_assert(strpos($cohort, '<header class="flacso-academic-section__summary">') !== false, 'el encabezado no es un control');
static_editor_assert(strpos($cohort, '<details') === false, 'Cohorte carece de acordeones');
static_editor_assert(strpos($cohort, 'name="prueba"') !== false, 'mantiene campos dentro de las secciones');

$GLOBALS['flacso_static_screen'] = 'programa-academico';
ob_start();
FLACSO_Academic_Admin_UI::section_start('otra-seccion', 'Programa', '', 'dashicons-admin-home');
FLACSO_Academic_Admin_UI::section_end();
$program = ob_get_clean();
static_editor_assert(strpos($program, '<details class="flacso-academic-section"') !== false, 'Programa mantiene su comportamiento anterior');
ob_start();
FLACSO_Academic_Admin_UI::render_static_postboxes();
$other = ob_get_clean();
static_editor_assert($other === '', 'otros CPT no reciben el bloqueo de metaboxes');

$offer = file_get_contents($root . '/modules/oferta-academica/includes/class-oferta-admin-fields.php');
$seminar = file_get_contents($root . '/modules/seminarios/includes/class-seminario-admin-fields.php');
$edition = file_get_contents($root . '/modules/seminarios/includes/class-edicion-admin-fields.php');
static_editor_assert(strpos($offer, '<details class="flacso-oferta-section"') === false, 'Oferta no tiene acordeones');
static_editor_assert(strpos($offer, '<section class="flacso-oferta-section"') !== false, 'Oferta tiene secciones fijas');
static_editor_assert(strpos($offer, 'data-flacso-sections') === false, 'Oferta no tiene Abrir/Cerrar todas');
static_editor_assert(strpos($seminar, '<section class="flacso-seminar-section">') !== false, 'Seminarios conserva sus secciones visibles');
static_editor_assert(strpos($edition, '<section class="flacso-edicion-card"') !== false, 'Ediciones conserva sus secciones visibles');
static_editor_assert(strpos($seminar, 'flacso_seminario[abreviacion]') === false, 'no se agregan abreviaciones a Seminarios');

echo "OK editores academicos con secciones permanentes\n";
