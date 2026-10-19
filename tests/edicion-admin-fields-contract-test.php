<?php

$root = dirname(__DIR__);
$admin = file_get_contents($root . '/modules/seminarios/includes/class-edicion-admin-fields.php');
$init = file_get_contents($root . '/modules/seminarios/init.php');

function edicion_admin_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

edicion_admin_assert(strpos($init, "class-edicion-admin-fields.php") !== false, 'el editor mejorado se carga desde el módulo');
edicion_admin_assert(strpos($init, 'FLACSO_Edicion_Admin_Fields::init()') !== false, 'el editor mejorado se inicializa');
edicion_admin_assert(strpos($admin, "name=\"flacso_docentes[]\"") !== false, 'los docentes usan una lista seleccionada independiente del checklist legado');
edicion_admin_assert(strpos($admin, 'flacso-docente-search') !== false, 'el selector de docentes permite buscar');
edicion_admin_assert(strpos($admin, "sortable({handle:'.dashicons-menu'") !== false, 'los docentes seleccionados se pueden ordenar');
edicion_admin_assert(strpos($admin, 'encuentros_sincronicos') !== false, 'se editan los encuentros sincrónicos');
edicion_admin_assert(strpos($admin, 'mensaje_preinscripcion_abierta') === false, 'no se configura mensaje de apertura');
edicion_admin_assert(strpos($admin, 'mensaje_preinscripcion_cerrada') === false, 'no se configura mensaje de cierre');
edicion_admin_assert(strpos($admin, 'mostrar_en_formulario') !== false, 'se edita la visibilidad en formularios');
edicion_admin_assert(strpos($admin, 'dias_cierre_post_inicio') === false, 'no se configura cierre automatico');
edicion_admin_assert(strpos($admin, 'ediciones_componentes') !== false, 'se editan las ediciones componentes');

echo "OK: contrato del editor de Edición\n";
