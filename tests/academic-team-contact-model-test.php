<?php

define('ABSPATH', __DIR__ . '/../');
define('MINUTE_IN_SECONDS', 60);

class WP_Post {
    public string $post_type = '';
}

$GLOBALS['test_post_types'] = [
    7 => 'docente',
    8 => 'docente',
    10 => 'docente',
    100 => 'oferta-academica',
    101 => 'oferta-academica',
];
$GLOBALS['test_meta'] = [];
$GLOBALS['test_transients'] = [];

function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function __($value, $domain = null) { return (string) $value; }
function sanitize_email($value) { return filter_var((string) $value, FILTER_SANITIZE_EMAIL); }
function wp_kses_post($value) { return (string) $value; }
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function get_post_type($id) { return $GLOBALS['test_post_types'][(int) $id] ?? ''; }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['test_meta'][(int) $id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['test_meta'][(int) $id][$key] = $value; return true; }
function delete_post_meta($id, $key) { unset($GLOBALS['test_meta'][(int) $id][$key]); return true; }
function current_user_can($capability, ...$args) { return true; }
function wp_is_post_revision($id) { return false; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'ok'; }
function wp_unslash($value) { return $value; }
function get_current_user_id() { return 1; }
function set_transient($key, $value, $expiration) { $GLOBALS['test_transients'][$key] = $value; return true; }
function get_transient($key) { return $GLOBALS['test_transients'][$key] ?? false; }
function delete_transient($key) { unset($GLOBALS['test_transients'][$key]); return true; }

require_once __DIR__ . '/../modules/oferta-academica/includes/class-oferta-academica.php';
require_once __DIR__ . '/../modules/oferta-academica/includes/class-academic-team-editor.php';
require_once __DIR__ . '/../modules/oferta-academica/includes/class-offer-carta-contact-admin.php';
require_once __DIR__ . '/../modules/oferta-academica/includes/class-offer-carta-contact-bridge.php';

function team_contact_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$legacy = FLACSO_Academic_Team_Editor::sanitize_academic_team([[
    'nombre' => 'Nombre histórico',
    'descripcion' => '<p>Descripción histórica</p>',
    'importancia' => '2',
    'docentes' => [
        ['id' => 7, 'rol' => 'Dirección', 'correo' => 'persona7@example.org'],
    ],
]]);

$empty = FLACSO_Academic_Team_Editor::sanitize_academic_team([]);
team_contact_assert(count($empty) === 1, 'Oferta sin datos crea exactamente una Coordinación académica');
team_contact_assert(($empty[0]['tipo'] ?? '') === FLACSO_Academic_Team_Editor::TYPE_COORDINATION, 'el único grupo vacío es Coordinación académica');
team_contact_assert(($empty[0]['docentes'] ?? null) === [], 'se puede comenzar sin integrantes sin eliminar la Coordinación');

team_contact_assert(count($legacy) === 2, 'la oferta incluye Coordinación académica y conserva el grupo histórico');
team_contact_assert(($legacy[0]['tipo'] ?? '') === FLACSO_Academic_Team_Editor::TYPE_COORDINATION, 'la coordinación obligatoria debe aparecer primero');
team_contact_assert(($legacy[1]['tipo'] ?? '') === FLACSO_Academic_Team_Editor::TYPE_CUSTOM, 'el histórico debe ser personalizado');
team_contact_assert(($legacy[1]['nombre'] ?? '') === 'Equipo académico', 'el histórico debe mostrarse como Equipo académico');
team_contact_assert(($legacy[1]['docentes'][0]['id'] ?? 0) === 7, 'el histórico debe conservar integrantes');
team_contact_assert(($legacy[1]['docentes'][0]['rol'] ?? '') === 'Dirección', 'el histórico debe conservar roles');
team_contact_assert(($legacy[1]['docentes'][0]['correo'] ?? '') === 'persona7@example.org', 'el histórico debe conservar correos');
team_contact_assert(($legacy[1]['descripcion'] ?? '') === '<p>Descripción histórica</p>', 'el histórico debe conservar descripción');

$submitted_groups = [
    [
        'tipo' => 'coordinacion_academica',
        'nombre' => 'Coordinación académica',
        'descripcion' => 'Coordinación estable',
        'importancia' => '1',
        'docentes' => [
            ['id' => 7, 'rol' => 'Coordinación'],
            ['id' => 10, 'rol' => 'Coordinación adjunta'],
        ],
    ],
    [
        'tipo' => 'personalizado',
        'nombre' => 'Comité académico',
        'descripcion' => '',
        'importancia' => '2',
        'docentes' => [
            ['id' => 8, 'rol' => 'Integrante'],
        ],
    ],
    [
        'tipo' => 'coordinacion_academica',
        'nombre' => 'Duplicada',
        'descripcion' => '',
        'importancia' => '3',
        'docentes' => [
            ['id' => 8, 'rol' => 'Otro'],
        ],
    ],
];

$normalized = FLACSO_Academic_Team_Editor::sanitize_academic_team($submitted_groups);
$coordination_groups = array_values(array_filter($normalized, static function (array $group): bool {
    return ($group['tipo'] ?? '') === FLACSO_Academic_Team_Editor::TYPE_COORDINATION;
}));
team_contact_assert(count($coordination_groups) === 1, 'solo puede persistir una Coordinación académica');
team_contact_assert(count($normalized) === 3, 'el segundo grupo duplicado se conserva como grupo personalizado');
team_contact_assert(($normalized[2]['tipo'] ?? '') === FLACSO_Academic_Team_Editor::TYPE_CUSTOM, 'la segunda coordinación se transforma en un grupo no privilegiado');
team_contact_assert(($normalized[0]['tipo'] ?? '') === FLACSO_Academic_Team_Editor::TYPE_COORDINATION, 'Coordinación académica debe mostrarse primero');

team_contact_assert(
    FLACSO_Offer_Carta_Contact_Admin::is_valid_contact_person(100, 7, $submitted_groups),
    'el primer integrante de Coordinación debe ser contacto válido'
);
team_contact_assert(
    FLACSO_Offer_Carta_Contact_Admin::is_valid_contact_person(100, 10, $submitted_groups),
    'cualquier integrante de Coordinación debe ser contacto válido'
);
team_contact_assert(
    !FLACSO_Offer_Carta_Contact_Admin::is_valid_contact_person(100, 8, $submitted_groups),
    'un integrante de un grupo estable distinto no puede ser contacto'
);

// Orden: el orden enviado por el formulario es el orden guardado, para grupos e integrantes.
$ordered = FLACSO_Academic_Team_Editor::sanitize_academic_team([
    3 => ['tipo' => 'personalizado', 'nombre' => 'Tercero', 'docentes' => [['id' => 8], ['id' => 7], ['id' => 10]]],
    0 => ['tipo' => 'coordinacion_academica', 'docentes' => [['id' => 10, 'rol' => 'Asistencia Técnica'], ['id' => 7, 'rol' => 'Coordinación Ejecutiva']]],
    1 => ['tipo' => 'personalizado', 'nombre' => 'Primero', 'docentes' => [['id' => 7]]],
]);
team_contact_assert(array_column($ordered, 'nombre') === ['Coordinación académica', 'Tercero', 'Primero'], 'los grupos personalizados conservan el orden enviado y Coordinación va primero');
team_contact_assert(array_column($ordered[1]['docentes'], 'id') === [8, 7, 10], 'los integrantes conservan el orden enviado');
team_contact_assert(array_column($ordered[0]['docentes'], 'id') === [10, 7], 'los integrantes de Coordinación conservan el orden enviado');

team_contact_assert(
    FLACSO_Academic_Team_Editor::coordination_role_presets() === ['Coordinación Académica', 'Asistencia Académica', 'Asistencia Técnica', 'Coordinación Ejecutiva'],
    'Coordinación ofrece los cuatro roles predeterminados'
);
$custom_role = FLACSO_Academic_Team_Editor::sanitize_academic_team([[
    'tipo' => 'coordinacion_academica',
    'docentes' => [['id' => 7, 'rol' => 'Dirección de posgrados']],
]]);
team_contact_assert(($custom_role[0]['docentes'][0]['rol'] ?? '') === 'Dirección de posgrados', 'Coordinación admite roles personalizados');

// Contacto de la carta: un único integrante marcado de Coordinación.
$with_contact = [
    [
        'tipo' => 'coordinacion_academica',
        'docentes' => [
            ['id' => 7, 'rol' => 'Coordinación Académica'],
            ['id' => 10, 'rol' => 'Asistencia Académica', 'correo' => 'asistencia@example.org', 'carta' => '1'],
            ['id' => 8, 'rol' => 'Asistencia Técnica', 'carta' => '1'],
        ],
    ],
    ['tipo' => 'personalizado', 'nombre' => 'Comité', 'docentes' => [['id' => 8, 'carta' => '1']]],
];
$normalized_contact = FLACSO_Academic_Team_Editor::sanitize_academic_team($with_contact);
team_contact_assert(!empty($normalized_contact[0]['docentes'][1]['carta']), 'se conserva el contacto marcado');
team_contact_assert(empty($normalized_contact[0]['docentes'][2]['carta']), 'solo un integrante de Coordinación puede ser el contacto');
team_contact_assert(empty($normalized_contact[1]['docentes'][0]['carta']), 'fuera de Coordinación no hay contacto de la carta');
team_contact_assert((FLACSO_Academic_Team_Editor::coordination_contact_member($with_contact)['id'] ?? 0) === 10, 'el contacto es el integrante marcado');

FLACSO_Offer_Carta_Contact_Admin::sync_from_groups(100, $with_contact);
team_contact_assert($GLOBALS['test_meta'][100][FLACSO_Offer_Carta_Contact_Admin::META_PERSON_ID] === 10, 'se usa el docente seleccionado');
team_contact_assert($GLOBALS['test_meta'][100][FLACSO_Offer_Carta_Contact_Admin::META_TITLE] === 'Asistencia Académica', 'se usa el rol del integrante como título');
team_contact_assert($GLOBALS['test_meta'][100][FLACSO_Offer_Carta_Contact_Admin::META_EMAIL] === 'asistencia@example.org', 'se usa el correo de contacto del integrante');

// Sin correo propio no se guarda uno: la carta usa el de la persona.
$no_email = [['tipo' => 'coordinacion_academica', 'docentes' => [['id' => 7, 'rol' => 'Coordinación Ejecutiva', 'carta' => '1']]]];
FLACSO_Offer_Carta_Contact_Admin::sync_from_groups(100, $no_email);
team_contact_assert($GLOBALS['test_meta'][100][FLACSO_Offer_Carta_Contact_Admin::META_PERSON_ID] === 7, 'cambiar el contacto actualiza la persona');
team_contact_assert(!isset($GLOBALS['test_meta'][100][FLACSO_Offer_Carta_Contact_Admin::META_EMAIL]), 'sin correo propio se borra el correo guardado');

// Sin marcar a nadie: se limpia si el contacto guardado era de Coordinación.
$unmarked = [['tipo' => 'coordinacion_academica', 'docentes' => [['id' => 7, 'rol' => 'Coordinación Ejecutiva']]]];
FLACSO_Offer_Carta_Contact_Admin::sync_from_groups(100, $unmarked);
team_contact_assert(!isset($GLOBALS['test_meta'][100][FLACSO_Offer_Carta_Contact_Admin::META_PERSON_ID]), 'desmarcar el contacto lo limpia');

// Contacto histórico ajeno a Coordinación: se conserva salvo pedido explícito.
$GLOBALS['test_meta'][101] = [
    'asistente_academica_docente_id' => 8,
    'asistente_academica_rol' => 'Contacto histórico',
    'asistente_academica_correo' => 'historico@example.org',
];
$GLOBALS['test_meta'][102] = [FLACSO_Offer_Carta_Contact_Admin::META_PERSON_ID => 8];
$only_seven = [['tipo' => 'coordinacion_academica', 'docentes' => [['id' => 7]]]];
FLACSO_Offer_Carta_Contact_Admin::sync_from_groups(102, $only_seven);
team_contact_assert(($GLOBALS['test_meta'][102][FLACSO_Offer_Carta_Contact_Admin::META_PERSON_ID] ?? 0) === 8, 'un contacto histórico fuera de Coordinación se conserva');
FLACSO_Offer_Carta_Contact_Admin::sync_from_groups(102, $only_seven, true);
team_contact_assert(!isset($GLOBALS['test_meta'][102][FLACSO_Offer_Carta_Contact_Admin::META_PERSON_ID]), '“Sin contacto específico” limpia el contacto');

// Puente con los metas históricos: solo se consolida tras sincronizar un cambio.
$post = new WP_Post();
$post->post_type = FLACSO_Oferta_Academica::POST_TYPE;
FLACSO_Offer_Carta_Contact_Bridge::consolidate_legacy_meta(101, $post);
team_contact_assert(($GLOBALS['test_meta'][101]['asistente_academica_docente_id'] ?? 0) === 8, 'sin sincronización no se borran los metas históricos');
FLACSO_Offer_Carta_Contact_Admin::sync_from_groups(101, $with_contact);
FLACSO_Offer_Carta_Contact_Bridge::consolidate_legacy_meta(101, $post);
team_contact_assert(!isset($GLOBALS['test_meta'][101]['asistente_academica_docente_id']), 'tras sincronizar se consolidan los metas históricos');
team_contact_assert(($GLOBALS['test_meta'][101][FLACSO_Offer_Carta_Contact_Admin::META_PERSON_ID] ?? 0) === 10, 'queda el contacto canónico');

$teamEditorSource = file_get_contents(__DIR__ . '/../modules/oferta-academica/includes/class-academic-team-editor.php');
team_contact_assert(strpos($teamEditorSource, 'data-add-team="offer-coordination"') === false, 'no existe botón de alta de Coordinación académica');
team_contact_assert(strpos($teamEditorSource, 'offer-coordination') === false, 'no existe plantilla ni acción para duplicar Coordinación');
team_contact_assert(strpos($teamEditorSource, 'data-team-zone="coordination"') !== false, 'Coordinación permanente y separada');
team_contact_assert(strpos($teamEditorSource, 'data-move-team') !== false && strpos($teamEditorSource, 'data-move-member') !== false, 'grupos e integrantes son reordenables');
team_contact_assert(strpos($teamEditorSource, "wp_enqueue_script('jquery-ui-sortable')") !== false, 'el arrastre usa jQuery UI Sortable');

echo "OK academic team and carta contact model\n";
