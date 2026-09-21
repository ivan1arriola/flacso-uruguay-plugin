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

team_contact_assert(count($legacy) === 1, 'el grupo histórico debe conservarse');
team_contact_assert(($legacy[0]['tipo'] ?? '') === FLACSO_Academic_Team_Editor::TYPE_CUSTOM, 'el histórico debe ser personalizado');
team_contact_assert(($legacy[0]['nombre'] ?? '') === 'Equipo académico', 'el histórico debe mostrarse como Equipo académico');
team_contact_assert(($legacy[0]['docentes'][0]['id'] ?? 0) === 7, 'el histórico debe conservar integrantes');
team_contact_assert(($legacy[0]['docentes'][0]['rol'] ?? '') === 'Dirección', 'el histórico debe conservar roles');
team_contact_assert(($legacy[0]['docentes'][0]['correo'] ?? '') === 'persona7@example.org', 'el histórico debe conservar correos');
team_contact_assert(($legacy[0]['descripcion'] ?? '') === '<p>Descripción histórica</p>', 'el histórico debe conservar descripción');

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

$GLOBALS['test_meta'][100] = [
    FLACSO_Offer_Carta_Contact_Admin::META_PERSON_ID => 7,
    FLACSO_Offer_Carta_Contact_Admin::META_TITLE => 'Título anterior',
    FLACSO_Offer_Carta_Contact_Admin::META_EMAIL => 'anterior@example.org',
];

$post = new WP_Post();
$post->post_type = FLACSO_Oferta_Academica::POST_TYPE;

$_POST = [
    'flacso_offer_carta_contact_nonce' => 'ok',
    'flacso_carta_contact' => [
        'person_id' => '8',
        'title' => 'Título inválido',
        'email' => 'invalido@example.org',
    ],
    'flacso_oferta_equipos' => $submitted_groups,
];

FLACSO_Offer_Carta_Contact_Admin::save(100, $post);

team_contact_assert($GLOBALS['test_meta'][100][FLACSO_Offer_Carta_Contact_Admin::META_PERSON_ID] === 7, 'un contacto inválido debe conservar la persona anterior');
team_contact_assert($GLOBALS['test_meta'][100][FLACSO_Offer_Carta_Contact_Admin::META_TITLE] === 'Título anterior', 'un contacto inválido debe conservar el título anterior');
team_contact_assert($GLOBALS['test_meta'][100][FLACSO_Offer_Carta_Contact_Admin::META_EMAIL] === 'anterior@example.org', 'un contacto inválido debe conservar el correo anterior');
team_contact_assert(!empty($GLOBALS['test_transients']), 'un contacto inválido debe producir un error visible');

$_POST = [
    'flacso_offer_carta_contact_nonce' => 'ok',
    'flacso_carta_contact' => [
        'person_id' => '10',
        'title' => 'Coordinación de la oferta',
        'email' => 'coord@example.org',
    ],
    'flacso_oferta_equipos' => $submitted_groups,
];

FLACSO_Offer_Carta_Contact_Admin::save(100, $post);

team_contact_assert($GLOBALS['test_meta'][100][FLACSO_Offer_Carta_Contact_Admin::META_PERSON_ID] === 10, 'un segundo integrante de Coordinación debe poder guardarse');
team_contact_assert($GLOBALS['test_meta'][100][FLACSO_Offer_Carta_Contact_Admin::META_TITLE] === 'Coordinación de la oferta', 'debe guardar el título del contacto válido');
team_contact_assert($GLOBALS['test_meta'][100][FLACSO_Offer_Carta_Contact_Admin::META_EMAIL] === 'coord@example.org', 'debe guardar el correo del contacto válido');

echo "OK academic team and carta contact model\n";
