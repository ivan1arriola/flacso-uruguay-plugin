<?php
/**
 * El shortcode [lista_seminarios] (usado por 5 páginas públicas «Seminarios de …») se registra en lista-seminarios.php, que el
 * cargador de main-page solo incluye si el módulo de seminarios añade ese archivo con el filtro flacso_main_page_component_files
 * (modules/seminarios/includes/homepage.php). Si el módulo no requiere ese adaptador, las páginas muestran el shortcode como texto.
 * Ejecutable sin WordPress: php tests/seminarios-lista-shortcode-contract-test.php
 */
$root = dirname(__DIR__);
function sl_check($ok, $d) { if (!$ok) { fwrite(STDERR, "FAIL: " . $d . "\n"); exit(1); } }

$init = (string) file_get_contents($root . '/modules/seminarios/init.php');
sl_check(strpos($init, "modules/seminarios/includes/homepage.php") !== false, 'seminarios/init.php carga el adaptador homepage.php (registra [lista_seminarios])');
$adapter = (string) file_get_contents($root . '/modules/seminarios/includes/homepage.php');
sl_check(strpos($adapter, "flacso_main_page_component_files") !== false && strpos($adapter, 'lista-seminarios.php') !== false, 'el adaptador añade lista-seminarios.php a los componentes de main-page');
$section = (string) file_get_contents($root . '/modules/main-page/sections/lista-seminarios.php');
sl_check(strpos($section, "add_shortcode('lista_seminarios'") !== false, 'lista-seminarios.php registra el shortcode');
echo "OK shortcode lista_seminarios registrado\n";
