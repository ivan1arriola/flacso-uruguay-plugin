<?php
/** Editar y borrar por la API académica exige permiso sobre el registro, no solo `edit_posts`. */

define('ABSPATH', __DIR__ . '/');

class WP_REST_Server {
    const READABLE = 'GET';
    const CREATABLE = 'POST';
    const EDITABLE = 'POST, PUT, PATCH';
    const DELETABLE = 'DELETE';
}
class WP_REST_Request implements ArrayAccess {
    private array $params;
    public function __construct(array $params) { $this->params = $params; }
    public function offsetExists($key): bool { return isset($this->params[$key]); }
    #[\ReturnTypeWillChange]
    public function offsetGet($key) { return $this->params[$key] ?? null; }
    public function offsetSet($key, $value): void { $this->params[$key] = $value; }
    public function offsetUnset($key): void { unset($this->params[$key]); }
}
class FLACSO_Academic_Repository {
    public static function definitions(): array { return ['ofertas' => ['post_type' => 'oferta-academica']]; }
}

$GLOBALS['routes'] = [];
$GLOBALS['caps'] = [];
function add_action(...$args): void {}
function absint($value): int { return abs((int) $value); }
function register_rest_route(string $namespace, string $route, array $args): void { $GLOBALS['routes'][$route] = $args; }
function current_user_can(string $cap, ...$args): bool {
    $key = $args ? $cap . ':' . $args[0] : $cap;
    return !empty($GLOBALS['caps'][$key]);
}

require dirname(__DIR__) . '/modules/oferta-academica/includes/class-academic-api.php';

function permissions_assert(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

FLACSO_Academic_API::register_routes();
$item = $GLOBALS['routes']['/ofertas/(?P<id>\d+)'] ?? null;
permissions_assert(is_array($item), 'existe la ruta de registro /ofertas/{id}');

$callback_for = static function (string $method) use ($item) {
    foreach ($item as $handler) {
        if (($handler['methods'] ?? '') === $method) {
            return $handler['permission_callback'];
        }
    }
    return null;
};
$request = new WP_REST_Request(['id' => '5']);

// Colaborador: puede crear borradores (edit_posts) pero no tocar el registro 5.
$GLOBALS['caps'] = ['edit_posts' => true];
permissions_assert(call_user_func($callback_for(WP_REST_Server::DELETABLE), $request) === false, 'edit_posts solo no alcanza para borrar');
permissions_assert(call_user_func($callback_for(WP_REST_Server::EDITABLE), $request) === false, 'edit_posts solo no alcanza para editar');

// Editor del registro: puede editarlo, pero borrar sigue pidiendo delete_post.
$GLOBALS['caps'] = ['edit_posts' => true, 'edit_post:5' => true];
permissions_assert(call_user_func($callback_for(WP_REST_Server::EDITABLE), $request) === true, 'edit_post sobre el registro permite editar');
permissions_assert(call_user_func($callback_for(WP_REST_Server::DELETABLE), $request) === false, 'editar no implica borrar');

$GLOBALS['caps'] = ['delete_post:5' => true];
permissions_assert(call_user_func($callback_for(WP_REST_Server::DELETABLE), $request) === true, 'delete_post sobre el registro permite borrar');

echo "OK academic API permissions: editar y borrar por registro\n";
