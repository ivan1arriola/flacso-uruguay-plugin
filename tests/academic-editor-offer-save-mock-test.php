<?php
/**
 * Prueba de guardado con dobles de WordPress: evita pérdida de metadatos
 * y cambios parciales sin realizar consultas ni escribir datos reales.
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
require_once dirname(__DIR__) . '/editor/contracts/interface-editor-offer-form.php';
require_once dirname(__DIR__) . '/editor/infrastructure/class-wordpress-offer-form.php';

class WP_Post {
    public int $ID = 11;
    public string $post_type = 'oferta-academica';
    public string $post_status = 'draft';
}

$GLOBALS['offer_test_post'] = new WP_Post();
$GLOBALS['offer_test_can_publish'] = true;
$GLOBALS['offer_test_write_count'] = 0;
$GLOBALS['offer_test_thumbnail'] = 0;

function get_post($id) { return $id === 11 ? $GLOBALS['offer_test_post'] : null; }
function current_user_can($capability, ...$args) {
    if ($capability === 'publish_posts') return $GLOBALS['offer_test_can_publish'];
    return $capability === 'edit_post' && ($args[0] ?? 0) === 11;
}
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_verify_nonce($value, $action) { return $value === 'test:' . $action; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function absint($value) { return abs((int) $value); }
function get_post_type_object($type) { return (object) ['cap' => (object) ['publish_posts' => 'publish_posts']]; }
function wp_update_post($args, $return_error = false) {
    $GLOBALS['offer_test_write_count']++;
    $GLOBALS['offer_test_args'] = $args;
    return 11;
}
function is_wp_error($result) { return false; }
function wp_attachment_is_image($id) { return $id === 42; }
function set_post_thumbnail($id, $image_id) { $GLOBALS['offer_test_thumbnail'] = $image_id; }
function delete_post_thumbnail($id) { $GLOBALS['offer_test_thumbnail'] = 0; }

function assert_editor_save(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

$form = new FLACSO_WordPress_Offer_Form();

$_POST = [];
$result = $form->save(11, ['title' => 'Oferta nueva', 'status' => 'draft']);
assert_editor_save(!$result['success'], 'rechaza formularios incompletos');
assert_editor_save($GLOBALS['offer_test_write_count'] === 0, 'no borra información ante POST incompleto');

$_POST = [
    'flacso_editor_offer_form_present' => '1',
    'flacso_oferta' => ['presentacion' => 'Texto'],
    'flacso_oferta_academica_nonce' => 'test:flacso_save_oferta_academica',
    'flacso_academic_teams_v2_nonce' => 'test:flacso_save_academic_teams_v2',
    'flacso_offer_carta_contact_nonce' => 'test:flacso_save_offer_carta_contact',
];

$result = $form->save(11, ['title' => 'Oferta nueva', 'status' => 'draft', 'thumbnail_id' => 93]);
assert_editor_save(!$result['success'], 'rechaza imagen inválida');
assert_editor_save($GLOBALS['offer_test_write_count'] === 0, 'imagen inválida no produce guardado parcial');

$GLOBALS['offer_test_can_publish'] = false;
$result = $form->save(11, ['title' => 'Oferta nueva', 'status' => 'publish']);
assert_editor_save(!$result['success'], 'no permite publicar sin permiso');
assert_editor_save($GLOBALS['offer_test_write_count'] === 0, 'no hace cambios sin capacidad de publicación');

$result = $form->save(11, ['title' => ' Oferta guardada ', 'status' => 'draft', 'thumbnail_id' => 42]);
assert_editor_save($result['success'], 'guarda cuando los contratos son válidos');
assert_editor_save($GLOBALS['offer_test_write_count'] === 1, 'una llamada a WordPress canónico');
assert_editor_save($GLOBALS['offer_test_args']['ID'] === 11, 'mantiene el ID');
assert_editor_save($GLOBALS['offer_test_args']['post_title'] === 'Oferta guardada', 'sanea el título');
assert_editor_save($GLOBALS['offer_test_thumbnail'] === 42, 'cambia la imagen destacada');

echo "OK academic editor offer save mocks\n";
