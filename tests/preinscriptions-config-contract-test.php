<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

$GLOBALS['flacso_registered_preinscription_meta'] = [];
$GLOBALS['flacso_test_capability_granted'] = false;
$GLOBALS['flacso_test_capability_checked'] = null;

function register_post_meta($post_type, $meta_key, $args): void {
    $GLOBALS['flacso_registered_preinscription_meta'][$post_type . ':' . $meta_key] = $args;
}

function current_user_can($capability): bool {
    $GLOBALS['flacso_test_capability_checked'] = $capability;
    return $GLOBALS['flacso_test_capability_granted'];
}

function flacso_preinscriptions_assert_same($expected, $actual, string $message): void {
    static $assertions = 0;
    $assertions++;

    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }

    $GLOBALS['flacso_preinscriptions_assertion_count'] = $assertions;
}

function flacso_preinscriptions_assert_true($condition, string $message): void {
    flacso_preinscriptions_assert_same(true, $condition, $message);
}

$module_files = [
    __DIR__ . '/../modules/preinscripciones/includes/class-preinscriptions-field-catalog.php',
    __DIR__ . '/../modules/preinscripciones/includes/class-preinscriptions-config.php',
    __DIR__ . '/../modules/preinscripciones/includes/class-preinscriptions-meta.php',
];

foreach ($module_files as $module_file) {
    if (!is_file($module_file)) {
        fwrite(STDERR, 'FAIL: required preinscriptions class does not exist: ' . basename($module_file) . "\n");
        exit(1);
    }
    require_once $module_file;
}

$expected_labels = [
    'documento'       => 'Documento',
    'fechaNacimiento' => 'Fecha de nacimiento',
    'titulo'          => 'Título',
    'escolaridad'     => 'Escolaridad',
    'orientacion'     => 'Orientación',
    'mencion'         => 'Mención',
    'institucion'     => 'Institución',
    'ocupacion'       => 'Ocupación',
];

flacso_preinscriptions_assert_same(array_keys($expected_labels), FLACSO_Preinscriptions_Field_Catalog::keys(), 'the catalog exposes exactly the approved eight keys');
flacso_preinscriptions_assert_same($expected_labels, FLACSO_Preinscriptions_Field_Catalog::labels(), 'the catalog exposes an admin label for every approved key');
flacso_preinscriptions_assert_true(FLACSO_Preinscriptions_Field_Catalog::has(' fechaNacimiento '), 'catalog membership normalizes surrounding whitespace');
flacso_preinscriptions_assert_true(FLACSO_Preinscriptions_Field_Catalog::has('FECHANACIMIENTO'), 'catalog membership normalizes key casing');
flacso_preinscriptions_assert_same(false, FLACSO_Preinscriptions_Field_Catalog::has('correo'), 'catalog membership rejects unknown keys');

$sanitized_inputs = FLACSO_Preinscriptions_Config::sanitize_inputs([
    ['key' => ' ocupacion ', 'position' => '30', 'required' => 'false'],
    ['required' => 'yes', 'key' => 'DOCUMENTO', 'position' => '20'],
    ['key' => 'documento', 'position' => 10, 'required' => false],
    ['key' => 'desconocido', 'position' => 1, 'required' => true],
    ['key' => 'titulo', 'position' => -4, 'required' => 1],
    ['key' => 'escolaridad', 'position' => 'después', 'required' => true],
    ['key' => 'institucion', 'position' => '7.5', 'required' => true],
    'not-a-record',
]);

flacso_preinscriptions_assert_same([
    ['key' => 'titulo', 'position' => 4, 'required' => true],
    ['key' => 'documento', 'position' => 20, 'required' => true],
    ['key' => 'ocupacion', 'position' => 30, 'required' => false],
], $sanitized_inputs, 'inputs are normalized, deduplicated by normalized key, filtered, and ordered by positive numeric position');

$sanitized_orientations = FLACSO_Preinscriptions_Config::sanitize_orientations([
    [
        'name' => ' Educación ',
        'mentions' => [
            ['name' => ' Tecnología educativa ', 'id' => 'tecnologia-educativa'],
            ['id' => 'tecnologia-educativa', 'name' => 'Duplicada'],
            ['id' => '', 'name' => 'Sin identificador'],
            'not-a-mention',
        ],
        'id' => ' educacion ',
    ],
    ['id' => 'gestion', 'name' => ' Gestión ', 'mentions' => []],
    ['id' => 'educacion', 'name' => 'Duplicada', 'mentions' => []],
    ['id' => '', 'name' => 'Inválida'],
    'not-an-orientation',
]);

flacso_preinscriptions_assert_same([
    [
        'id' => 'educacion',
        'name' => 'Educación',
        'mentions' => [
            ['id' => 'tecnologia-educativa', 'name' => 'Tecnología educativa'],
        ],
    ],
    ['id' => 'gestion', 'name' => 'Gestión', 'mentions' => []],
], $sanitized_orientations, 'orientations preserve normalized orientation-to-mention nesting and reject duplicate or malformed records');

$inputs_same_meaning_a = [
    ['required' => false, 'position' => 20, 'key' => 'ocupacion'],
    ['key' => 'documento', 'required' => true, 'position' => 10],
];
$inputs_same_meaning_b = [
    ['position' => '10', 'required' => 1, 'key' => ' DOCUMENTO '],
    ['key' => 'ocupacion', 'position' => '20', 'required' => 0],
];
$orientations_same_meaning_a = [
    ['mentions' => [['name' => 'Tecnología educativa', 'id' => 'tecnologia-educativa']], 'name' => 'Educación', 'id' => 'educacion'],
];
$orientations_same_meaning_b = [
    ['id' => ' educacion ', 'name' => ' Educación ', 'mentions' => [['id' => 'tecnologia-educativa', 'name' => ' Tecnología educativa ']]],
];

$canonical_a = FLACSO_Preinscriptions_Config::canonical_payload($inputs_same_meaning_a, $orientations_same_meaning_a);
$canonical_b = FLACSO_Preinscriptions_Config::canonical_payload($inputs_same_meaning_b, $orientations_same_meaning_b);
flacso_preinscriptions_assert_same($canonical_a, $canonical_b, 'canonical payload ignores accidental associative insertion order and normalized scalar representation');

$canonical_orientations_a = FLACSO_Preinscriptions_Config::canonical_payload([], [
    ['id' => 'gestion', 'name' => 'Gestión', 'mentions' => [
        ['id' => 'publica', 'name' => 'Pública'],
        ['id' => 'privada', 'name' => 'Privada'],
    ]],
    ['id' => 'educacion', 'name' => 'Educación', 'mentions' => []],
]);
$canonical_orientations_b = FLACSO_Preinscriptions_Config::canonical_payload([], [
    ['id' => 'educacion', 'name' => 'Educación', 'mentions' => []],
    ['id' => 'gestion', 'name' => 'Gestión', 'mentions' => [
        ['id' => 'privada', 'name' => 'Privada'],
        ['id' => 'publica', 'name' => 'Pública'],
    ]],
]);
flacso_preinscriptions_assert_same(
    $canonical_orientations_a,
    $canonical_orientations_b,
    'canonical payload ignores accidental orientation and mention insertion order'
);

$revision_a = FLACSO_Preinscriptions_Config::revision($canonical_a);
$revision_b = FLACSO_Preinscriptions_Config::revision($canonical_b);
flacso_preinscriptions_assert_same($revision_a, $revision_b, 'semantically identical configuration has the same revision');
flacso_preinscriptions_assert_true((bool) preg_match('/^sha256:[a-f0-9]{64}$/', $revision_a), 'revision uses the sha256 digest contract');

$required_changed = FLACSO_Preinscriptions_Config::canonical_payload([
    ['key' => 'documento', 'position' => 10, 'required' => false],
    ['key' => 'ocupacion', 'position' => 20, 'required' => false],
], $orientations_same_meaning_a);
$field_changed = FLACSO_Preinscriptions_Config::canonical_payload([
    ['key' => 'titulo', 'position' => 10, 'required' => true],
    ['key' => 'ocupacion', 'position' => 20, 'required' => false],
], $orientations_same_meaning_a);
flacso_preinscriptions_assert_true($revision_a !== FLACSO_Preinscriptions_Config::revision($required_changed), 'changing a required flag changes the revision');
flacso_preinscriptions_assert_true($revision_a !== FLACSO_Preinscriptions_Config::revision($field_changed), 'changing a field changes the revision');

$warning = null;
set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
    $warning = [$severity, $message];
    return true;
});
FLACSO_Preinscriptions_Config::sanitize_inputs(null);
FLACSO_Preinscriptions_Config::sanitize_inputs([[], ['key' => []], ['position' => []]]);
FLACSO_Preinscriptions_Config::sanitize_orientations(null);
FLACSO_Preinscriptions_Config::sanitize_orientations([[], ['id' => []], ['mentions' => 'invalid']]);
restore_error_handler();
flacso_preinscriptions_assert_same(null, $warning, 'malformed records are rejected without PHP warnings');

FLACSO_Preinscriptions_Meta::init();
$registrations = $GLOBALS['flacso_registered_preinscription_meta'];
flacso_preinscriptions_assert_same([
    'cohorte:preinscripcion_formulario',
    'cohorte:preinscripcion_orientaciones',
    'edicion:preinscripcion_formulario',
    'edicion:preinscripcion_orientaciones',
], array_keys($registrations), 'both serialized meta values are registered for cohorts and editions');

foreach ($registrations as $registration) {
    flacso_preinscriptions_assert_same('array', $registration['type'] ?? null, 'preinscription meta is registered as an array');
    flacso_preinscriptions_assert_same(true, $registration['single'] ?? null, 'preinscription meta is single-value');
    flacso_preinscriptions_assert_same(false, $registration['show_in_rest'] ?? null, 'preinscription meta is not exposed through generic REST');
    flacso_preinscriptions_assert_true(is_callable($registration['sanitize_callback'] ?? null), 'preinscription meta has a sanitizer');
    flacso_preinscriptions_assert_true(is_callable($registration['auth_callback'] ?? null), 'preinscription meta has a write authorization callback');

    $GLOBALS['flacso_test_capability_granted'] = false;
    flacso_preinscriptions_assert_same(false, $registration['auth_callback'](), 'meta writes are rejected without administrator capability');
    flacso_preinscriptions_assert_same('manage_options', $GLOBALS['flacso_test_capability_checked'], 'meta writes check the administrator capability');
    $GLOBALS['flacso_test_capability_granted'] = true;
    flacso_preinscriptions_assert_same(true, $registration['auth_callback'](), 'meta writes are accepted with administrator capability');
}

printf("PASS: preinscriptions configuration contract (%d assertions)\n", $GLOBALS['flacso_preinscriptions_assertion_count'] ?? 0);
