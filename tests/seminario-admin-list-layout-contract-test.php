<?php

$root = dirname(__DIR__);
$cpt = file_get_contents($root . '/modules/seminarios/includes/class-seminario-cpt.php');

function seminario_list_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

seminario_list_assert(strpos($cpt, 'flacso-current-instance') !== false, 'el listado debe usar el bloque compartido de instancia vigente');
seminario_list_assert(strpos($cpt, 'Sin edición vigente') !== false, 'debe informar cuando no existe edición vigente');
seminario_list_assert(strpos($cpt, 'sin_vigente') !== false, 'debe filtrar seminarios sin edición vigente');
seminario_list_assert(strpos($cpt, 'flacso-status--open') !== false, 'se conserva el estado semántico de preinscripción abierta');
seminario_list_assert(strpos($cpt, '@media screen and (max-width: 1100px)') !== false, 'el listado debe conservar una presentación adaptable');

fwrite(STDOUT, "OK seminario admin list layout contract\n");
