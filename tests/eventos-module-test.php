<?php
// Eventos es el único módulo de eventos: sin el tipo heredado charla_abierta ni sus endpoints.
$root = dirname(__DIR__);
function check($ok, $message) { if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }

check(!is_dir($root . '/modules/charlas-abiertas'), 'El módulo charlas-abiertas ya no existe');
$loader = file_get_contents($root . '/flacso-uruguay.php');
check(strpos($loader, 'charlas-abiertas') === false, 'El plugin no carga charlas-abiertas');

$init = file_get_contents($root . '/modules/eventos/init.php');
foreach (['event-fields.php', 'rest.php'] as $file) {
    check(strpos($init, $file) !== false && is_file($root . '/modules/eventos/includes/' . $file), "Eventos carga $file");
}

$rest = file_get_contents($root . '/modules/eventos/includes/rest.php');
preg_match_all("/register_rest_route\('([^']+)', '([^']+)'/", $rest, $routes, PREG_SET_ORDER);
$paths = array_map(static fn($r) => $r[2], $routes);
check($paths === ['/eventos', '/eventos/(?P<id>\d+)'], 'La API expone sólo /eventos y /eventos/{id}');
check(strpos($rest, 'charla_abierta') === false, 'La API no lee el tipo heredado');

foreach (glob($root . '/modules/eventos/includes/*.php') as $file) {
    check(strpos(file_get_contents($file), "'charla_abierta'") === false, basename($file) . ' no registra ni consulta charla_abierta');
}
echo "OK eventos module\n";
