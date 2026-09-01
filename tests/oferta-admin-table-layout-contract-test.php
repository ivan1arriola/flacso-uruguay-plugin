<?php

$root = dirname(__DIR__);
$layout = file_get_contents($root . '/modules/oferta-academica/includes/class-oferta-admin-table-layout.php');
$init = file_get_contents($root . '/modules/oferta-academica/init.php');

function oferta_layout_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

oferta_layout_assert(strpos($init, 'class-oferta-admin-table-layout.php') !== false, 'debe cargar la corrección del listado');
oferta_layout_assert(strpos($init, 'FLACSO_Oferta_Admin_Table_Layout::init();') !== false, 'debe inicializar la corrección del listado');
oferta_layout_assert(strpos($layout, "'esquema'") !== false, 'debe ocultar columnas SEO de esquema');
oferta_layout_assert(strpos($layout, "'metadescripción'") !== false, 'debe ocultar metadescripción en el listado');
oferta_layout_assert(strpos($layout, "'buscar'") !== false, 'debe ocultar la columna Buscar de terceros');
oferta_layout_assert(strpos($layout, '.column-title') !== false, 'debe reservar ancho útil al título');
oferta_layout_assert(strpos($layout, '.column-cohortes') !== false, 'debe reservar ancho a cohortes');
oferta_layout_assert(strpos($layout, 'word-break: normal') !== false, 'no debe cortar títulos letra por letra');

echo "OK oferta admin table layout contract\n";
