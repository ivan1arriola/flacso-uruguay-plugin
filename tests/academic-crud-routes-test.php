<?php

define('ABSPATH', __DIR__ . '/../');

$GLOBALS['flacso_crud_routes'] = [];

class WP_REST_Server {
    public const READABLE = 'GET';
    public const CREATABLE = 'POST';
    public const EDITABLE = 'PUT,PATCH';
    public const DELETABLE = 'DELETE';
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function register_rest_route($namespace, $route, $definition) {
    $GLOBALS['flacso_crud_routes'][$namespace . $route] = $definition;
}

require_once __DIR__ . '/../modules/oferta-academica/includes/class-academic-offer-api.php';
require_once __DIR__ . '/../modules/instancias-oferta/includes/class-instancia-oferta-api.php';

function crud_assert_methods(string $route, array $expected): void {
    $definitions = $GLOBALS['flacso_crud_routes'][$route] ?? [];
    if (isset($definitions['methods'])) {
        $definitions = [$definitions];
    }

    $actual = array_map(static fn(array $definition): string => (string) ($definition['methods'] ?? ''), $definitions);
    foreach ($expected as $method) {
        if (!in_array($method, $actual, true)) {
            fwrite(STDERR, "Fallo en CRUD {$route}: falta {$method}\n");
            exit(1);
        }
    }
}

FLACSO_Academic_Offer_API::register_routes();
FLACSO_Instancia_Oferta_API::register_routes();

crud_assert_methods('flacso/v1/ofertas', ['GET', 'POST']);
crud_assert_methods('flacso/v1/ofertas/(?P<id>\d+)', ['GET', 'PUT,PATCH', 'DELETE']);
crud_assert_methods('flacso/v1/instancias-oferta', ['GET', 'POST']);
crud_assert_methods('flacso/v1/instancias-oferta/(?P<id>\d+)', ['GET', 'PUT,PATCH', 'DELETE']);

$offer_source = file_get_contents(__DIR__ . '/../modules/oferta-academica/includes/class-academic-offer-api.php');
$instance_source = file_get_contents(__DIR__ . '/../modules/instancias-oferta/includes/class-instancia-oferta-api.php');
if (strpos($offer_source, "['force' => \$force]") === false || strpos($instance_source, 'wp_trash_post($post_id)') === false) {
    fwrite(STDERR, "Fallo: DELETE debe ser recuperable salvo force=true explicito\n");
    exit(1);
}

fwrite(STDOUT, "OK academic CRUD routes\n");
