<?php

$root = dirname(__DIR__);
$workload = file_get_contents($root . '/modules/seminarios/includes/class-seminario-integrado-workload.php');
$registration = file_get_contents($root . '/modules/seminarios/includes/class-seminario-integrado-registration.php');
$init = file_get_contents($root . '/modules/seminarios/init.php');

function integrated_extra_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

integrated_extra_assert(strpos($workload, "'carga_horaria'") !== false, 'la carga horaria debe ser derivada');
integrated_extra_assert(strpos($workload, 'FLACSO_Seminario_Integrado::component_seminar_ids') !== false, 'la carga horaria debe recorrer componentes');
integrated_extra_assert(strpos($workload, 'prevent_derived_writes') !== false, 'la carga horaria derivada no debe persistirse manualmente');
integrated_extra_assert(strpos($registration, "'link_preinscripcion'") !== false, 'debe sincronizar el link de preinscripción');
integrated_extra_assert(strpos($registration, 'component_edition_ids') !== false, 'el link debe propagarse sólo a ediciones componentes válidas');
integrated_extra_assert(strpos($registration, 'preinscripcion_habilitada') !== false, 'debe documentar que no cambia el estado de apertura');
integrated_extra_assert(strpos($registration, "update_post_meta((int) $component_edition_id, 'preinscripcion_habilitada'") === false, 'no debe abrir ni cerrar las ediciones componentes');
integrated_extra_assert(strpos($init, 'FLACSO_Seminario_Integrado_Workload::init();') !== false, 'debe inicializar carga horaria derivada');
integrated_extra_assert(strpos($init, 'FLACSO_Seminario_Integrado_Registration::init();') !== false, 'debe inicializar herencia del link');

echo "OK seminario-integrado-workload-registration\n";
