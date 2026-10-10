<?php
$source = file_get_contents(dirname(__DIR__) . '/modules/consultas/includes/class-flacso-consultas-admin.php');
function inbox_ux_assert($condition, $message): void { if (!$condition) { fwrite(STDERR, "Fallo: {$message}\n"); exit(1); } }
inbox_ux_assert(strpos($source, 'flacso-cp-inbox-toolbar') !== false, 'incluye una barra de resultados de la bandeja');
inbox_ux_assert(strpos($source, 'flacso-cp-filter-reset') !== false, 'permite limpiar filtros');
inbox_ux_assert(strpos($source, 'flacso-cp-person') !== false, 'agrupa persona y correo para lectura');
inbox_ux_assert(strpos($source, 'flacso-cp-delivery') !== false, 'agrupa estados de entrega');
inbox_ux_assert(strpos($source, 'flacso-cp-pagination') !== false, 'presenta paginación accesible');
echo "OK consultas inbox UX contract\n";
