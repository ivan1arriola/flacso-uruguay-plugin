<?php

$root = dirname(__DIR__);
$cpt = file_get_contents($root . '/modules/seminarios/includes/class-seminario-cpt.php');

function seminario_list_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

seminario_list_assert(strpos($cpt, 'flacso-seminario-row') !== false, 'el listado debe identificar filas operativas de seminarios');
seminario_list_assert(strpos($cpt, 'flacso-seminario-row--open') !== false, 'las preinscripciones abiertas deben destacarse en la fila');
seminario_list_assert(strpos($cpt, 'flacso-edicion-summary__meta') !== false, 'la edición debe agrupar estado y fechas como metadatos');
seminario_list_assert(strpos($cpt, 'flacso-status--open') !== false, 'se conserva el estado semántico de preinscripción abierta');
seminario_list_assert(strpos($cpt, '@media screen and (max-width: 1100px)') !== false, 'el listado debe conservar una presentación adaptable');

fwrite(STDOUT, "OK seminario admin list layout contract\n");
