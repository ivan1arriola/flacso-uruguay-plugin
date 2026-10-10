<?php
$root = dirname(__DIR__);
$admin = file_get_contents($root . '/modules/consultas/includes/class-flacso-consultas-admin.php');
function routing_assert(bool $condition, string $message): void { if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
routing_assert(strpos($admin, "'flacso-consultas-resumen'") !== false, 'registra Resumen como página propia');
routing_assert(strpos($admin, "'flacso-consultas-exportar'") !== false, 'registra Exportar como página propia');
routing_assert(strpos($admin, 'redirect_legacy_tab') !== false, 'redirige enlaces heredados por pestaña');
routing_assert(strpos($admin, 'render_page_for_route') !== false, 'despacha cada ruta de consultas');
routing_assert(strpos($admin, 'MANAGE_INQUIRIES') !== false, 'exportar conserva autorización de gestión');
echo "OK consultas pages routing contract\n";
