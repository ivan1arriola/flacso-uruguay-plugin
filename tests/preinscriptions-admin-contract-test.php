<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

$GLOBALS['flacso_registered_actions'] = [];
$GLOBALS['flacso_registered_metaboxes'] = [];
$GLOBALS['flacso_post_meta_store'] = [];
$GLOBALS['flacso_test_capability_granted'] = true;
$GLOBALS['flacso_test_nonce_valid'] = true;

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
    $GLOBALS['flacso_registered_actions'][$hook][] = [
        'callback'      => $callback,
        'priority'      => $priority,
        'accepted_args' => $accepted_args,
    ];
}

function add_meta_box(string $id, string $title, $callback, $screen = null, string $context = 'advanced', string $priority = 'default', $callback_args = null): void {
    $GLOBALS['flacso_registered_metaboxes'][$id] = [
        'title'    => $title,
        'callback' => $callback,
        'screen'   => $screen,
        'context'  => $context,
        'priority' => $priority,
    ];
}

function wp_nonce_field(string $action, string $name = '_wpnonce', bool $referer = true, bool $echo = true): string {
    $html = sprintf('<input type="hidden" name="%s" value="valid_nonce_%s" />', htmlspecialchars($name), htmlspecialchars($action));
    if ($echo) {
        echo $html;
    }
    return $html;
}

function wp_verify_nonce($nonce, $action): bool {
    return $GLOBALS['flacso_test_nonce_valid'];
}

function current_user_can($capability, ...$args): bool {
    return $GLOBALS['flacso_test_capability_granted'];
}

function get_post_meta(int $post_id, string $key = '', bool $single = false) {
    if (!isset($GLOBALS['flacso_post_meta_store'][$post_id][$key])) {
        return $single ? '' : [];
    }
    return $GLOBALS['flacso_post_meta_store'][$post_id][$key];
}

function update_post_meta(int $post_id, string $key, $value): bool {
    $GLOBALS['flacso_post_meta_store'][$post_id][$key] = $value;
    return true;
}

function delete_post_meta(int $post_id, string $key): bool {
    unset($GLOBALS['flacso_post_meta_store'][$post_id][$key]);
    return true;
}

function wp_is_post_revision(int $post_id) {
    return false;
}

function wp_is_post_autosave(int $post_id) {
    return false;
}

function get_post_type($post = null): ?string {
    if (is_object($post) && isset($post->post_type)) {
        return $post->post_type;
    }
    if (is_numeric($post) && isset($GLOBALS['flacso_test_post_types'][(int) $post])) {
        return $GLOBALS['flacso_test_post_types'][(int) $post];
    }
    return 'cohorte';
}

function esc_attr($text): string {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_html($text): string {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_html_e($text, $domain = 'default'): void {
    echo htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function checked($checked, $current = true, bool $echo = true): string {
    $result = ((string) $checked === (string) $current) ? 'checked="checked"' : '';
    if ($echo) {
        echo $result;
    }
    return $result;
}

function __($text, $domain = 'default'): string {
    return (string) $text;
}

function flacso_admin_assert_same($expected, $actual, string $message): void {
    static $assertions = 0;
    $assertions++;
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
    $GLOBALS['flacso_admin_assertions'] = $assertions;
}

function flacso_admin_assert_true($condition, string $message): void {
    flacso_admin_assert_same(true, (bool) $condition, $message);
}

// Include required module files
require_once __DIR__ . '/../modules/preinscripciones/includes/class-preinscriptions-field-catalog.php';
require_once __DIR__ . '/../modules/preinscripciones/includes/class-preinscriptions-config.php';
require_once __DIR__ . '/../modules/preinscripciones/includes/class-preinscriptions-meta.php';

$admin_file = __DIR__ . '/../modules/preinscripciones/includes/class-preinscriptions-admin.php';
if (!is_file($admin_file)) {
    fwrite(STDERR, "FAIL: class-preinscriptions-admin.php does not exist\n");
    exit(1);
}
require_once $admin_file;

// Test init hooks
FLACSO_Preinscriptions_Admin::init();

flacso_admin_assert_true(isset($GLOBALS['flacso_registered_actions']['add_meta_boxes']), 'metabox action is registered');
flacso_admin_assert_true(isset($GLOBALS['flacso_registered_actions']['save_post_cohorte']), 'save_post_cohorte action is registered');
flacso_admin_assert_true(isset($GLOBALS['flacso_registered_actions']['save_post_edicion']), 'save_post_edicion action is registered');

// Test add_meta_boxes registration
FLACSO_Preinscriptions_Admin::add_meta_boxes();

flacso_admin_assert_true(isset($GLOBALS['flacso_registered_metaboxes']['flacso_preinscripcion_meta_cohorte']), 'metabox for cohorte is registered');
flacso_admin_assert_true(isset($GLOBALS['flacso_registered_metaboxes']['flacso_preinscripcion_meta_edicion']), 'metabox for edicion is registered');
flacso_admin_assert_same('cohorte', $GLOBALS['flacso_registered_metaboxes']['flacso_preinscripcion_meta_cohorte']['screen'], 'screen is cohorte');
flacso_admin_assert_same('edicion', $GLOBALS['flacso_registered_metaboxes']['flacso_preinscripcion_meta_edicion']['screen'], 'screen is edicion');

// Test rendering
$dummy_post = (object) [
    'ID'        => 1234,
    'post_type' => 'cohorte',
];

ob_start();
FLACSO_Preinscriptions_Admin::render_meta_box($dummy_post);
$rendered = ob_get_clean();

flacso_admin_assert_true(strpos($rendered, 'preinscripcion_admin_nonce') !== false, 'nonce field is rendered');
flacso_admin_assert_true(strpos($rendered, 'preinscripcion_formulario') !== false, 'inputs configuration field is rendered');
flacso_admin_assert_true(strpos($rendered, 'preinscripcion_orientaciones') !== false, 'orientations configuration field is rendered');

// Ensure approved keys from catalog are rendered in field choices
foreach (FLACSO_Preinscriptions_Field_Catalog::keys() as $key) {
    flacso_admin_assert_true(strpos($rendered, $key) !== false, "catalog key {$key} is rendered in admin UI");
}

// Ensure no arbitrary type/regex/sheets column inputs exist in rendered HTML
flacso_admin_assert_same(false, strpos($rendered, 'name="input_regex"'), 'no regex input in admin UI');
flacso_admin_assert_same(false, strpos($rendered, 'name="sheets_column"'), 'no sheets column input in admin UI');
flacso_admin_assert_same(false, strpos($rendered, 'name="html_type"'), 'no html type input in admin UI');

// Test saving with valid data
$_POST['preinscripcion_admin_nonce'] = 'valid_nonce_flacso_preinscripcion_save';
$_POST['preinscripcion_formulario'] = [
    ['key' => 'documento', 'position' => 10, 'required' => '1'],
    ['key' => 'titulo', 'position' => 20, 'required' => '0'],
];
$_POST['preinscripcion_orientaciones'] = [
    [
        'id'       => 'educacion',
        'name'     => 'Educación',
        'mentions' => [
            ['id' => 'tec-edu', 'name' => 'Tecnología Educativa'],
        ],
    ],
];

FLACSO_Preinscriptions_Admin::save(1234, (object) ['ID' => 1234, 'post_type' => 'cohorte']);

$saved_form = get_post_meta(1234, 'preinscripcion_formulario', true);
$saved_orientations = get_post_meta(1234, 'preinscripcion_orientaciones', true);

flacso_admin_assert_same([
    ['key' => 'documento', 'position' => 10, 'required' => true],
    ['key' => 'titulo', 'position' => 20, 'required' => false],
], $saved_form, 'saved form is sanitized and stored in post meta');

flacso_admin_assert_same([
    ['id' => 'educacion', 'name' => 'Educación'],
], $saved_orientations, 'saved orientations are sanitized and stored independently');

// Test saving guarded by capability
$GLOBALS['flacso_test_capability_granted'] = false;
$_POST['preinscripcion_formulario'] = [
    ['key' => 'institucion', 'position' => 5, 'required' => '1'],
];
FLACSO_Preinscriptions_Admin::save(1234, (object) ['ID' => 1234, 'post_type' => 'cohorte']);
$saved_form_after_unauthorized = get_post_meta(1234, 'preinscripcion_formulario', true);
flacso_admin_assert_same($saved_form, $saved_form_after_unauthorized, 'unauthorized save is rejected');

// Test saving guarded by nonce
$GLOBALS['flacso_test_capability_granted'] = true;
$GLOBALS['flacso_test_nonce_valid'] = false;
FLACSO_Preinscriptions_Admin::save(1234, (object) ['ID' => 1234, 'post_type' => 'cohorte']);
$saved_form_after_invalid_nonce = get_post_meta(1234, 'preinscripcion_formulario', true);
flacso_admin_assert_same($saved_form, $saved_form_after_invalid_nonce, 'invalid nonce save is rejected');

printf("PASS: preinscriptions admin contract (%d assertions)\n", $GLOBALS['flacso_admin_assertions'] ?? 0);
