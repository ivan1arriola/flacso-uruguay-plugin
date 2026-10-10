<?php

$root = dirname(__DIR__);
$offers = file_get_contents($root . '/modules/oferta-academica/includes/class-cpt-oferta-academica.php');
$seminars = file_get_contents($root . '/modules/seminarios/includes/class-seminario-cpt.php');

function current_instance_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

foreach (['ofertas' => $offers, 'seminarios' => $seminars] as $name => $source) {
    current_instance_assert(strpos($source, 'flacso-current-instance') !== false, "{$name} debe usar el bloque visual compartido de instancia vigente");
    current_instance_assert(strpos($source, 'sin_vigente') !== false, "{$name} debe filtrar registros sin instancia vigente");
    current_instance_assert(strpos($source, 'preinscripcion_abierta') !== false, "{$name} debe filtrar instancias con preinscripción abierta");
    current_instance_assert(strpos($source, 'restrict_manage_posts') !== false, "{$name} debe registrar filtros operativos");
}

fwrite(STDOUT, "OK academic current instance list contract\n");
