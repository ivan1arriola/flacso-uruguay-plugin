<?php
/** Las rutas de escáneres reciben un 404 mínimo; las URL antiguas legítimas siguen viendo la página completa. */

define('ABSPATH', __DIR__ . '/');
function add_action(...$args): void {}
require dirname(__DIR__) . '/modules/core/includes/class-flacso-custom-404.php';

function scanner_assert(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

foreach ([
    '.env', 'wp-content/.env', 'lib/aws-sdk/src/data/ec2/2016-11-15', 'wp-json/wso.php',
    'wp-json/wp/v2/pages/19886/shell.php', '.git/config', 'phpinfo.php', 'www/phpinfo.php',
    'vendor/phpunit/src/Util/PHP/eval-stdin.php', 'node_modules/x', 'cgi-bin/test', 'backup.zip', 'WSO2.PHP',
] as $path) {
    scanner_assert(Flacso_Custom_404::is_scanner_path($path), "{$path} es de escáner y debe recibir el 404 mínimo");
}
foreach ([
    '', 'web/formacion/diplomados/especializacion-violencias', 'formacion/maestrias/maestria-en-genero',
    'programa.html', 'pagina-vieja.php', 'novedad-sobre-el-envio-de-backup', 'docente/alexis-larrosa',
    'formacion__trashed/diplomas/diploma-genero_old/preinscripcion', 'wp-content/uploads/2026/programa.pdf',
] as $path) {
    scanner_assert(!Flacso_Custom_404::is_scanner_path($path), "{$path} es una URL legítima y debe ver la página 404 completa");
}

echo "OK custom 404 scanner: rutas de escáner detectadas, URL legítimas intactas\n";
