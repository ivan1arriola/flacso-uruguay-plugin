<?php
$root = dirname(__DIR__);
$admin = file_get_contents($root . '/modules/consultas/includes/class-flacso-consultas-admin.php');
$css = $root . '/modules/consultas/assets/css/consultas-admin.css';
function layout_assert(bool $condition, string $message): void { if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
layout_assert(strpos($admin, 'render_page_header') !== false, 'cada ruta usa cabecera compartida');
layout_assert(strpos($admin, 'render_operational_notice') !== false, 'el diagnóstico se muestra como aviso discreto');
layout_assert(strpos($admin, 'enqueue_admin_assets') !== false, 'carga estilos por ruta');
layout_assert(is_file($css), 'existe hoja de estilos exclusiva de Consultas');
layout_assert(is_file($css) && strpos(file_get_contents($css), '.flacso-consultas-page') !== false, 'el layout tiene contenedor propio');
echo "OK consultas pages layout contract\n";
