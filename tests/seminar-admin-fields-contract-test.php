<?php

$root = dirname(__DIR__);
$init = file_get_contents($root . '/modules/seminarios/init.php');
$admin = file_get_contents($root . '/modules/seminarios/includes/class-seminario-admin-fields.php');
$cpt = file_get_contents($root . '/modules/seminarios/includes/class-seminario-cpt.php');

function seminar_admin_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

seminar_admin_assert(strpos($init, 'class-seminario-admin-fields.php') !== false, 'el módulo carga el editor académico');
seminar_admin_assert(strpos($init, 'FLACSO_Seminario_Admin_Fields::init()') !== false, 'el editor académico se inicializa');
seminar_admin_assert(strpos($admin, 'flacso_seminario_academico_box') !== false, 'se registra el metabox académico');
seminar_admin_assert(strpos($admin, "'save_post_' . FLACSO_Seminario::POST_TYPE") !== false, 'se guardan los campos desde wp-admin');
seminar_admin_assert(strpos($admin, '_seminario_presentacion_seminario') !== false, 'se leen presentaciones históricas');
seminar_admin_assert(strpos($admin, '_seminario_seminarios_componentes') !== false, 'se leen componentes históricos');
seminar_admin_assert(strpos($admin, 'Información académica del Seminario') !== false, 'el formulario identifica los campos académicos');
seminar_admin_assert(strpos($admin, 'Las fechas, modalidad, docentes, precios y preinscripción se editan dentro de cada Edición.') !== false, 'el formulario explica la propiedad operativa');
seminar_admin_assert(strpos($admin, "wp_enqueue_script('jquery-ui-sortable')") !== false, 'los campos ordenables cargan su dependencia');
seminar_admin_assert(strpos($admin, 'wp_verify_nonce') !== false && strpos($admin, "current_user_can('edit_post'") !== false, 'el guardado valida nonce y permisos');
seminar_admin_assert(strpos($admin, "update_post_meta(\$post_id, 'acredita_maestria'") !== false, 'los booleanos desmarcados se persisten');
seminar_admin_assert(strpos($cpt, "'default'") !== false, 'el metabox de ediciones queda después de los datos académicos');

fwrite(STDOUT, "OK seminar admin fields contract\n");
