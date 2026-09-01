<?php

$root = dirname(__DIR__);
$admin = file_get_contents($root . '/modules/oferta-academica/includes/class-oferta-admin-fields.php');
$init = file_get_contents($root . '/modules/oferta-academica/init.php');

function oferta_admin_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

oferta_admin_assert(strpos($init, 'class-oferta-admin-fields.php') !== false, 'debe cargar el editor de Oferta');
oferta_admin_assert(strpos($init, 'FLACSO_Oferta_Admin_Fields::init();') !== false, 'debe inicializar el editor de Oferta');
oferta_admin_assert(strpos($admin, "save_post_' . FLACSO_Oferta_Academica::POST_TYPE") !== false, 'debe guardar el CPT Oferta Académica');
oferta_admin_assert(strpos($admin, 'Información académica de la Oferta') !== false, 'debe mostrar metabox académico');
oferta_admin_assert(strpos($admin, "'presentacion'") !== false, 'debe editar presentación');
oferta_admin_assert(strpos($admin, "'objetivo_general'") !== false, 'debe editar objetivo general');
oferta_admin_assert(strpos($admin, "'duracion_meses'") !== false, 'debe editar duración');
oferta_admin_assert(strpos($admin, "'carga_horaria'") !== false, 'debe editar carga horaria');
oferta_admin_assert(strpos($admin, "'creditos'") !== false, 'debe editar créditos');
oferta_admin_assert(strpos($admin, "'perfil_ingreso_html'") !== false, 'debe editar perfil de ingreso');
oferta_admin_assert(strpos($admin, 'wp_verify_nonce') !== false, 'debe validar nonce');
oferta_admin_assert(strpos($admin, "current_user_can('edit_post', $post_id)") !== false, 'debe validar permisos');

echo "OK oferta admin fields contract\n";
