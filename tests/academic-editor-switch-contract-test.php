<?php
/**
 * Contratos de transición: la UI preliminar no altera persistencia ni roles.
 */
$root = dirname(__DIR__);

function flacso_editor_switch_assert(bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$main = (string) file_get_contents($root . '/flacso-uruguay.php');
$bootstrap = (string) file_get_contents($root . '/editor/bootstrap.php');
$router = (string) file_get_contents($root . '/editor/includes/class-editor-router.php');
$view = (string) file_get_contents($root . '/editor/views/academic.php');
$css = (string) file_get_contents($root . '/editor/assets/css/editor.css');
$js = (string) file_get_contents($root . '/editor/assets/js/editor-switch.js');
$assistant = (string) file_get_contents($root . '/includes/core/class-flacso-academic-assistant.php');

flacso_editor_switch_assert(strpos($main, "editor/bootstrap.php") !== false, 'el plugin registra /editor');
flacso_editor_switch_assert(strpos($bootstrap, 'FLACSO_Editor_Router::init()') !== false, 'el editor inicia');
flacso_editor_switch_assert(strpos($router, "'edit_form_top'") !== false, 'hay botón en el editor actual');
flacso_editor_switch_assert(strpos($router, "add_submenu_page(") !== false, 'la nueva vista está dentro de wp-admin');
flacso_editor_switch_assert(strpos($router, "'oferta-academica', 'cohorte'") !== false, 'el cambio se restringe a Ofertas y Cohortes');
flacso_editor_switch_assert(strpos($router, "current_user_can('edit_post',") !== false, 'se respetan permisos por entrada');
flacso_editor_switch_assert(strpos($router, "get_edit_post_link(") !== false, 'el retorno conserva el mismo contenido');
flacso_editor_switch_assert(strpos($router, "'auto-draft'") !== false, 'no se permite cambiar a una entrada sin guardar');
flacso_editor_switch_assert(strpos($view, 'Volver al editor actual') !== false, 'el editor nuevo permite volver');
flacso_editor_switch_assert(strpos($view, 'Esta vista no modifica información') !== false, 'la vista es explícitamente preliminar');
flacso_editor_switch_assert(strpos($assistant, 'FLACSO_Editor_Router::PAGE_SLUG') !== false, 'Gestión web puede acceder al nuevo editor');
flacso_editor_switch_assert(strpos($js, 'window.confirm') !== false, 'advierte cambios sin guardar');
flacso_editor_switch_assert(strpos($css, '@media(max-width:782px)') !== false, 'se adapta a celulares');
flacso_editor_switch_assert(strpos($router, 'wp_update_post(') === false, 'la transición no escribe datos');
flacso_editor_switch_assert(strpos($router, 'update_post_meta(') === false, 'la transición no duplica la persistencia');
echo "OK academic editor switch contract\n";
