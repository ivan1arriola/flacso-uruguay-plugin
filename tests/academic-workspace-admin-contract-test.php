<?php
/**
 * El workspace moderniza la edición sin reemplazar el formulario de WordPress.
 */
$root = dirname(__DIR__);
$init = file_get_contents($root . '/modules/oferta-academica/init.php');
$php = file_get_contents($root . '/modules/oferta-academica/includes/class-academic-workspace-admin.php');
$css = file_get_contents($root . '/modules/oferta-academica/assets/css/academic-workspace-admin.css');
$js = file_get_contents($root . '/modules/oferta-academica/assets/js/academic-workspace-admin.js');

function academic_workspace_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

academic_workspace_assert(strpos($init, 'class-academic-workspace-admin.php') !== false, 'el módulo carga el workspace');
academic_workspace_assert(strpos($init, 'FLACSO_Academic_Workspace_Admin::init();') !== false, 'se inicializa la interfaz');
academic_workspace_assert(strpos($php, "'oferta-academica', 'cohorte'") !== false, 'solo aplica a Oferta y Cohorte');
academic_workspace_assert(strpos($php, "$screen->base === 'post'") !== false, 'solo afecta las pantallas de edición');
academic_workspace_assert(strpos($php, "'admin_enqueue_scripts'") !== false, 'los assets se registran en el hook apropiado');
academic_workspace_assert(strpos($php, "'admin_body_class'") !== false, 'los estilos se restringen por pantalla');
academic_workspace_assert(strpos($css, 'grid-template-areas:"navigation heading publication"') !== false, 'distribución editorial desktop');
academic_workspace_assert(strpos($css, '@media(max-width:1280px)') !== false, 'se adapta a tablets');
academic_workspace_assert(strpos($css, '@media(max-width:782px)') !== false, 'se adapta a móviles');
academic_workspace_assert(strpos($css, 'overflow-x:hidden') === false, 'no oculta desbordamientos');
academic_workspace_assert(strpos($js, "document.getElementById('publish')") !== false, 'el CTA reutiliza el botón nativo');
academic_workspace_assert(strpos($js, 'nativeSave.click()') !== false, 'delega a validaciones y hooks de WordPress');
academic_workspace_assert(strpos($js, "form.addEventListener('submit'") !== false, 'se informa envío al usuario');
academic_workspace_assert(strpos($js, "aria-current', 'location'") !== false, 'indica la sección activa de modo accesible');
academic_workspace_assert(strpos($js, 'section.scrollIntoView(') !== false, 'permite navegar sin ocultar secciones');
academic_workspace_assert(strpos($js, 'document.createElement(\'input\')') === false, 'no se duplican controles persistidos');

echo "OK academic workspace admin contract\n";
