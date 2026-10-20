<?php

$root = dirname(__DIR__);
$admin = file_get_contents($root . '/modules/oferta-academica/includes/class-oferta-admin-fields.php');
$academic = file_get_contents($root . '/modules/oferta-academica/includes/class-oferta-academica.php');
$repository = file_get_contents($root . '/modules/oferta-academica/includes/class-academic-repositories.php');
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
oferta_admin_assert(strpos($admin, "'objetivo_general'") === false, 'no debe editar objetivo general');
oferta_admin_assert(strpos($admin, "'objetivos_especificos'") === false, 'no debe editar objetivos específicos');
oferta_admin_assert(strpos($admin, "'financiacion_html'") === false, 'la financiación se edita fuera de cada oferta');
oferta_admin_assert(strpos($admin, 'Financiación - Texto') !== false, 'debe ofrecer el ajuste compartido de financiación');
oferta_admin_assert(strpos($admin, "'duracion_meses'") !== false, 'debe editar duración');
oferta_admin_assert(strpos($admin, "'carga_horaria'") !== false, 'debe editar carga horaria');
oferta_admin_assert(strpos($admin, "'creditos'") !== false, 'debe editar créditos');
oferta_admin_assert(strpos($admin, "'perfil_ingreso_html'") !== false, 'debe editar perfil de ingreso');
oferta_admin_assert(strpos($admin, '<section class="flacso-oferta-section"') !== false, 'debe organizar el editor en secciones permanentes');
oferta_admin_assert(strpos($admin, "'reconocimiento_red_flacso'") !== false, 'debe ofrecer reconocimiento de la red FLACSO como opción independiente');
oferta_admin_assert(strpos($admin, 'Título con validez y reconocimiento en la red del Sistema Internacional FLACSO') !== false, 'debe mostrar el texto del reconocimiento de la red FLACSO');
oferta_admin_assert(strpos($admin, "'reconocimiento_red_flacso' =>") !== false, 'debe explicar qué muestra el reconocimiento de la red FLACSO');
oferta_admin_assert(strpos($admin, "'reconocimiento_internacional', 'reconocimiento_red_flacso', 'convenio_iin_oea'") !== false, 'debe persistir el nuevo reconocimiento al guardar');
oferta_admin_assert(strpos($academic, "'reconocimiento_red_flacso' => ['type' => 'boolean'") !== false, 'debe registrar el nuevo reconocimiento como metadato booleano');
oferta_admin_assert(strpos($repository, "'reconocimiento_red_flacso'") !== false, 'el repositorio académico debe incluir el nuevo reconocimiento');
oferta_admin_assert(strpos($admin, '<header class="flacso-oferta-section__summary">') !== false, 'las secciones deben usar encabezados permanentes');
oferta_admin_assert(strpos($admin, '<details class="flacso-oferta-section"') === false, 'no debe incluir acordeones de Oferta');
oferta_admin_assert(strpos($admin, 'data-flacso-sections=') === false, 'no debe mostrar abrir/cerrar todas');
oferta_admin_assert(strpos($admin, 'campos con contenido') !== false, 'debe informar la cobertura editorial sin marcar campos opcionales como obligatorios');
oferta_admin_assert(strpos($admin, 'pertenecen a cada cohorte') !== false, 'debe diferenciar datos estables y temporales');
oferta_admin_assert(strpos($admin, 'wp_verify_nonce') !== false, 'debe validar nonce');
oferta_admin_assert(strpos($admin, "current_user_can('edit_post', \$post_id)") !== false, 'debe validar permisos');

echo "OK oferta admin fields contract\n";
