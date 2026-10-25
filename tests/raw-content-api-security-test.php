<?php
/** Pruebas aisladas de permisos de la API de contenido crudo (leer y actualizar posts). */

define('ABSPATH', __DIR__ . '/');

class WP_Error {
    public string $code;
    public array $data;
    public function __construct(string $code = '', string $message = '', array $data = []) {
        $this->code = $code;
        $this->data = $data;
    }
}
class WP_REST_Request implements ArrayAccess {
    private array $params;
    public function __construct(array $params) { $this->params = $params; }
    public function offsetExists($offset): bool { return isset($this->params[$offset]); }
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) { return $this->params[$offset] ?? null; }
    public function offsetSet($offset, $value): void { $this->params[$offset] = $value; }
    public function offsetUnset($offset): void { unset($this->params[$offset]); }
}

$GLOBALS['can_edit'] = [];
$GLOBALS['removed_filters'] = [];
$GLOBALS['updated'] = [];

function add_action(string $name, $callback): void {}
function absint($value): int { return abs((int) $value); }
function register_rest_field(...$args): void {}
function __($text, $domain = ''): string { return $text; }
function sanitize_key($key): string { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $key)); }
function wp_unslash($value) { return is_string($value) ? stripslashes($value) : $value; }
function post_type_exists($type): bool { return in_array($type, ['post', 'page'], true); }
function post_password_required($post): bool { return !empty($post->post_password); }
function get_permalink($post): string { return 'https://flacso.edu.uy/?p=' . $post->ID; }
function get_post($id) {
    $posts = [
        7  => (object) ['ID' => 7, 'post_type' => 'post', 'post_title' => 'Propio', 'post_name' => 'propio', 'post_status' => 'draft', 'post_content' => '<p>borrador</p>'],
        8  => (object) ['ID' => 8, 'post_type' => 'post', 'post_title' => 'Ajeno', 'post_name' => 'ajeno', 'post_status' => 'draft', 'post_content' => '<p>secreto</p>'],
    ];
    return $posts[(int) $id] ?? null;
}
function current_user_can(string $capability, int $id = 0): bool {
    if ($capability === 'edit_post') {
        return in_array($id, $GLOBALS['can_edit'], true);
    }
    return $capability === 'edit_posts';
}
function remove_filter(...$args): bool { $GLOBALS['removed_filters'][] = $args; return true; }
function add_filter(...$args): bool { return true; }
function wp_update_post(array $data, bool $wp_error = false) { $GLOBALS['updated'][] = $data; return $data['ID']; }
function is_wp_error($value): bool { return $value instanceof WP_Error; }

require dirname(__DIR__) . '/modules/main-page/includes/flacso-raw-content-api.php';

function raw_content_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$GLOBALS['can_edit'] = [7];

raw_content_assert(flacso_check_permissions(new WP_REST_Request(['id' => 7])) === true, 'Permite editar el post propio');
raw_content_assert(flacso_check_permissions(new WP_REST_Request(['id' => 8])) === false, 'Un colaborador no pasa el permiso sobre el post ajeno');

$read_other = flacso_get_raw_content(new WP_REST_Request(['id' => 8, 'type' => 'post']));
raw_content_assert($read_other instanceof WP_Error && $read_other->data['status'] === 403, 'No expone el borrador ajeno');
$read_own = flacso_get_raw_content(new WP_REST_Request(['id' => 7, 'type' => 'post']));
raw_content_assert(is_array($read_own) && $read_own['content_raw'] === '<p>borrador</p>', 'Devuelve el contenido del post propio');

$update_other = flacso_update_raw_content(new WP_REST_Request(['id' => 8, 'type' => 'post', 'content' => '<script>alert(1)</script>']));
raw_content_assert($update_other instanceof WP_Error && $update_other->data['status'] === 403, 'Rechaza editar el post ajeno aunque el endpoint sea alcanzable');
raw_content_assert($GLOBALS['updated'] === [], 'No actualiza el post ajeno');

$update_own = flacso_update_raw_content(new WP_REST_Request(['id' => 7, 'type' => 'post', 'content' => '<p>nuevo</p>']));
raw_content_assert(is_array($update_own) && $update_own['success'] === true, 'Actualiza el post propio');
raw_content_assert($GLOBALS['removed_filters'] === [], 'No desactiva los filtros KSES de WordPress');

$not_string = flacso_update_raw_content(new WP_REST_Request(['id' => 7, 'type' => 'post', 'content' => ['x']]));
raw_content_assert($not_string instanceof WP_Error && $not_string->data['status'] === 400, 'Rechaza contenido que no es texto');

echo "OK raw content api security\n";
