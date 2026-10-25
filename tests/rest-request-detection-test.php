<?php
/** Pruebas aisladas de flacso_is_rest_request() antes de que WordPress defina REST_REQUEST. */

define('ABSPATH', __DIR__ . '/');
define('FLACSO_URUGUAY_PATH', dirname(__DIR__) . '/');
define('FLACSO_URUGUAY_VERSION', 'test');

function add_action(...$args): void {}
function add_filter(...$args): void {}
function home_url(string $path = ''): string { return 'https://flacso.edu.uy' . $GLOBALS['home_prefix'] . $path; }
function wp_parse_url(string $url, int $component = -1) { return parse_url($url, $component); }
function trailingslashit(string $value): string { return rtrim($value, '/\\') . '/'; }
function rest_get_url_prefix(): string { return 'wp-json'; }
function wp_unslash($value) { return $value; }

require dirname(__DIR__) . '/includes/core/helpers.php';

function rest_detection_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$check = static function (string $uri, string $home_prefix = '', array $get = []): bool {
    $GLOBALS['home_prefix'] = $home_prefix;
    $_SERVER['REQUEST_URI'] = $uri;
    $_GET = $get;
    return flacso_is_rest_request();
};

rest_detection_assert($check('/wp-json/flacso/v1/main-page') === true, 'Detecta /wp-json/ sin REST_REQUEST');
rest_detection_assert($check('/wp-json/') === true, 'Detecta la raíz de la REST API');
rest_detection_assert($check('/wp-json') === true, 'Detecta la raíz sin barra final');
rest_detection_assert($check('/index.php', '', ['rest_route' => '/flacso/v1/x']) === true, 'Detecta ?rest_route=');
rest_detection_assert($check('/sitio/wp-json/flacso/v1/x', '/sitio') === true, 'Detecta REST en instalaciones en subdirectorio');
rest_detection_assert($check('/formacion/posgrados/') === false, 'No marca páginas normales como REST');
rest_detection_assert($check('/wp-json-fake/') === false, 'No confunde prefijos parecidos');
rest_detection_assert($check('/blog/wp-json/') === false, 'No detecta wp-json fuera de la raíz del sitio');
rest_detection_assert($check('/wp-admin/admin-ajax.php') === false, 'admin-ajax no es REST');

echo "OK rest request detection\n";
