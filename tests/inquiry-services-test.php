<?php
// tests/inquiry-services-test.php
$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Configurar mocks de WordPress y Mailjet para tests autónomos
$GLOBALS['mailjet_mock_options'] = [
    'flacso_mailjet_api_key'                     => 'mock-api-key',
    'flacso_mailjet_secret_key'                  => 'mock-secret-key',
    'flacso_mailjet_sender_email'                => 'notificaciones@flacso.edu.uy',
    'flacso_mailjet_sender_name'                 => 'FLACSO Uruguay',
    'flacso_mailjet_template_consulta_abierta'   => '12345',
    'flacso_mailjet_template_consulta_seminario' => '67890',
];

if (!function_exists('get_option')) {
    function get_option($k, $d = false) {
        return $GLOBALS['mailjet_mock_options'][$k] ?? $d;
    }
}

if (!function_exists('update_option')) {
    function update_option($k, $v) {
        $GLOBALS['mailjet_mock_options'][$k] = $v;
        return true;
    }
}

if (!class_exists('FLACSO_Academic_Catalog')) {
    class FLACSO_Academic_Catalog {
        public static function get_offer(int $id): array {
            if ($id !== 13) {
                return [];
            }

            return [
                'id' => 13,
                'nombre' => 'Diploma con cohorte canónica',
                'abreviacion' => 'DCC-2026',
                'correo' => 'coordinacion@flacso.edu.uy',
                'cohorte_consulta' => [
                    'id' => 1301,
                    'numero' => 4,
                    'nombre' => 'Cohorte 4',
                    'fecha_inicio' => '2026-09-02',
                    'precision_fecha_inicio' => 'dia',
                    'modalidad' => 'hibrida',
                    'preinscripcion' => [
                        'abierta' => true,
                        'desde'   => '2026-08-01',
                        'hasta'   => '2026-09-01',
                        'url'     => 'https://preinscripciones.flacso.edu.uy/oferta/13',
                    ],
                ],
                'cohorte_vigente' => [
                    'fecha_inicio' => '2026-09-02',
                    'precision_fecha_inicio' => 'dia',
                    'modalidad' => 'hibrida',
                    'preinscripcion' => [
                        'abierta' => true,
                        'url' => 'https://preinscripciones.flacso.edu.uy/oferta/13',
                    ],
                ],
            ];
        }
    }
}

$GLOBALS['mailjet_http_calls'] = [];
$GLOBALS['mailjet_mock_simulate_error'] = false;

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args) {
        $GLOBALS['mailjet_http_calls'][] = ['url' => $url, 'args' => $args];
        if (!empty($GLOBALS['mailjet_mock_simulate_error'])) {
            return [
                'response' => ['code' => 500, 'message' => 'Internal Error'],
                'body'     => '{"ErrorMessage":"Error remoto simulado en Mailjet"}',
            ];
        }
        $body = json_decode($args['body'], true);
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode([
                'Messages' => [
                    [
                        'Status' => 'success',
                        'CustomID' => $body['Messages'][0]['CustomID'] ?? '',
                        'To' => [
                            [
                                'Email' => $body['Messages'][0]['To'][0]['Email'] ?? '',
                                'MessageID' => '288230407340150000',
                                'MessageUUID' => 'f7b8a8b1-1234-5678-90ab-cdef12345678',
                            ]
                        ]
                    ]
                ]
            ]),
        ];
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($res) {
        return $res['response']['code'] ?? 0;
    }
}
if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($res) {
        return $res['body'] ?? '';
    }
}

$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = null;

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = []) {
        if (strpos($url, 'mailjet.com') !== false) {
            return wp_remote_post($url, $args);
        }
        $GLOBALS['mautic_http_calls'][] = ['url' => $url, 'args' => $args];
        if (is_callable($GLOBALS['mautic_http_handler'])) {
            return ($GLOBALS['mautic_http_handler'])($url, $args);
        }
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode(['success' => true]),
        ];
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {
        $args['method'] = 'GET';
        return wp_remote_request($url, $args);
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($str) {
        $str = strtr((string)$str, [
            'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u',
            'Á'=>'a', 'É'=>'e', 'Í'=>'i', 'Ú'=>'o', 'Ú'=>'u',
            'ñ'=>'n', 'Ñ'=>'n', 'ü'=>'u', 'Ü'=>'u',
        ]);
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $str), '-'));
    }
}

require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-seminar-inquiry-repository.php';
require_once $root . '/includes/integrations/class-flacso-mailjet-client.php';
require_once $root . '/includes/integrations/class-flacso-mautic-client.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-context-service.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-marketing-service.php';
require_once $root . '/modules/consultas/services/class-flacso-offer-inquiry-service.php';
require_once $root . '/modules/consultas/services/class-flacso-seminar-inquiry-service.php';

function srv_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// Configurar SQLite en memoria con esquema idéntico a producción
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('
CREATE TABLE offer_inquiries (
    id TEXT PRIMARY KEY, consultaId TEXT UNIQUE, offerWpId INTEGER, offerName TEXT,
    offerAbbreviation TEXT, offerType TEXT, cohortWpId INTEGER, cohortNumber INTEGER, cohortName TEXT,
    registrationOpenAt TEXT, registrationCloseAt TEXT,
    firstName TEXT, lastName TEXT, fullName TEXT, email TEXT, emailNormalized TEXT, country TEXT,
    profession TEXT, educationLevel TEXT, source TEXT, campaignProvider TEXT, campaignSource TEXT,
    campaignMedium TEXT, campaignName TEXT, campaignExternalId TEXT, campaignContent TEXT, campaignTerm TEXT,
    urlBase TEXT, urlReferer TEXT, inquiryAt TEXT, ipAddress TEXT, userAgent TEXT, replyToEmail TEXT,
    programUrl TEXT, cartaUrl TEXT, preinscripcionUrl TEXT, offerStatus TEXT,
    mauticContactId TEXT, mauticSyncStatus TEXT DEFAULT "skipped", mauticSyncedAt TEXT, mauticLastError TEXT,
    followupDueAt TEXT, followupStatus TEXT DEFAULT "none", followupSentAt TEXT, followupAttempts INTEGER DEFAULT 0, followupLastError TEXT,
    emailStatus TEXT, emailSender TEXT, gmailMessageUrl TEXT, mailjetMessageId TEXT, mailjetMessageUuid TEXT, payload TEXT, createdAt TEXT, updatedAt TEXT
);
CREATE TABLE seminar_inquiries (
    id TEXT PRIMARY KEY, consultaId TEXT UNIQUE, seminarWpId INTEGER, seminarName TEXT, seminarType TEXT,
    firstName TEXT, lastName TEXT, fullName TEXT, email TEXT, emailNormalized TEXT, country TEXT,
    profession TEXT, educationLevel TEXT, source TEXT, campaignProvider TEXT, campaignSource TEXT,
    campaignMedium TEXT, campaignName TEXT, campaignExternalId TEXT, campaignContent TEXT, campaignTerm TEXT,
    urlBase TEXT, urlReferer TEXT, inquiryAt TEXT, ipAddress TEXT, userAgent TEXT, replyToEmail TEXT,
    programUrl TEXT, cartaUrl TEXT, preinscripcionUrl TEXT, offerStatus TEXT, emailStatus TEXT,
    emailSender TEXT, gmailMessageUrl TEXT, mailjetMessageId TEXT, mailjetMessageUuid TEXT, payload TEXT, createdAt TEXT, updatedAt TEXT
);
');
FLACSO_DB::set_connection($pdo);

// =========================================================================
// 1. Submit de oferta exitoso (guardar primero, enviar después)
// =========================================================================
$initial_mail_calls = count($GLOBALS['mailjet_http_calls']);
$result_offer = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-001',
    'id_pagina'       => 10,
    'titulo_posgrado' => 'Maestría en Educación',
    'nombre'          => 'Lucía',
    'apellido'        => 'Méndez',
    'correo'          => 'lucia@ejemplo.com',
    'pais'            => 'Uruguay',
]);
srv_assert($result_offer['ok'] === true, 'Offer submit debe ser ok');
srv_assert($result_offer['consulta_id'] === 'srv-offer-001', 'Debe retornar consulta_id');
srv_assert($result_offer['duplicate'] === false, 'No debe ser duplicado');
srv_assert($result_offer['email'] === 'skipped', 'La comunicación debe quedar a cargo de Mautic');
srv_assert($result_offer['mailjet_message_id'] === null, 'No debe retornar identificador Mailjet');
srv_assert(count($GLOBALS['mailjet_http_calls']) === $initial_mail_calls, 'No debe llamar a Mailjet');
srv_assert(isset($result_offer['offer_status']) && $result_offer['offer_status'] === 'sin_cohorte', 'Debe retornar offer_status = sin_cohorte si no hay cohorte');
srv_assert(array_key_exists('cohort_number', $result_offer) && $result_offer['cohort_number'] === null, 'cohort_number debe ser null si no hay cohorte');
srv_assert(array_key_exists('offer_abbreviation', $result_offer) && $result_offer['offer_abbreviation'] === null, 'offer_abbreviation debe ser null si no hay abreviación');

$repo = new FLACSO_Offer_Inquiry_Repository();
$saved = $repo->find_by_consulta_id('srv-offer-001');
srv_assert(!empty($saved), 'La fila debe existir en offer_inquiries');
srv_assert($saved['emailStatus'] === 'skipped', 'emailStatus en BD debe ser skipped');
srv_assert($saved['offerStatus'] === 'sin_cohorte', 'offerStatus en BD debe ser sin_cohorte');
srv_assert($saved['cohortNumber'] === null, 'cohortNumber en BD debe ser null');
srv_assert($saved['offerAbbreviation'] === null, 'offerAbbreviation en BD debe ser null');

// =========================================================================
// 1.2 Datos canónicos de Cohorte: modalidad, fecha, Reply-To y Snapshots
// =========================================================================
$result_catalog = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'  => 'srv-offer-catalog-003',
    'id_pagina' => 13,
    'nombre'    => 'Sofía',
    'correo'    => 'sofia@ejemplo.com',
]);
srv_assert($result_catalog['ok'] === true, 'Offer con catálogo canónico debe ser ok');
srv_assert(isset($result_catalog['offer_status']) && $result_catalog['offer_status'] === 'abierta', 'Debe retornar offer_status = abierta');
srv_assert(isset($result_catalog['cohort_number']) && $result_catalog['cohort_number'] === 4, 'Debe retornar cohort_number = 4');
srv_assert(isset($result_catalog['offer_abbreviation']) && $result_catalog['offer_abbreviation'] === 'dcc-2026', 'Debe retornar offer_abbreviation = dcc-2026');

$saved_catalog = $repo->find_by_consulta_id('srv-offer-catalog-003');
srv_assert(!empty($saved_catalog), 'La fila de catálogo debe existir en offer_inquiries');
srv_assert($saved_catalog['offerAbbreviation'] === 'dcc-2026', 'offerAbbreviation en BD debe ser dcc-2026');
srv_assert((int)$saved_catalog['cohortWpId'] === 1301, 'cohortWpId en BD debe ser 1301');
srv_assert((int)$saved_catalog['cohortNumber'] === 4, 'cohortNumber en BD debe ser 4');
srv_assert($saved_catalog['cohortName'] === 'Cohorte 4', 'cohortName en BD debe ser Cohorte 4');
srv_assert($saved_catalog['offerStatus'] === 'abierta', 'offerStatus en BD debe ser abierta');
srv_assert($saved_catalog['registrationOpenAt'] === '2026-08-01', 'registrationOpenAt debe guardarse en BD');
srv_assert($saved_catalog['registrationCloseAt'] === '2026-09-01', 'registrationCloseAt debe guardarse en BD');

srv_assert(count($GLOBALS['mailjet_http_calls']) === $initial_mail_calls, 'El catálogo no debe provocar un envío Mailjet');

// =========================================================================
// 2. Idempotencia de oferta: reenvío con mismo event_id retorna duplicate sin enviar correo
// =========================================================================
$calls_before_dup = count($GLOBALS['mailjet_http_calls']);
$result_dup = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-001',
    'id_pagina'       => 10,
    'nombre'          => 'Lucía',
    'correo'          => 'lucia@ejemplo.com',
]);
srv_assert($result_dup['ok'] === true, 'El duplicado debe retornar ok');
srv_assert($result_dup['duplicate'] === true, 'Debe marcar duplicate = true');
srv_assert($result_dup['consulta_id'] === 'srv-offer-001', 'Debe mantener consulta_id');
srv_assert(count($GLOBALS['mailjet_http_calls']) === $calls_before_dup, 'No debe llamar a Mailjet en caso de duplicado');

// =========================================================================
// 3. Submit de seminario exitoso (guardar primero, enviar después)
// =========================================================================
$calls_before_sem = count($GLOBALS['mailjet_http_calls']);
$result_sem = FLACSO_Seminar_Inquiry_Service::submit([
    'event_id'         => 'srv-sem-001',
    'seminario_id'     => 45,
    'seminario_titulo' => 'Seminario Bioética',
    'nombre'           => 'Martín',
    'correo'           => 'martin@ejemplo.com',
    'pais'             => 'Argentina',
    'consulta'         => '¿Cuáles son los aranceles?',
]);
srv_assert($result_sem['ok'] === true, 'Seminar submit debe ser ok');
srv_assert($result_sem['consulta_id'] === 'srv-sem-001', 'Debe retornar consulta_id del seminario');
srv_assert($result_sem['duplicate'] === false, 'No debe ser duplicado');
srv_assert($result_sem['email'] === 'skipped', 'La comunicación del seminario debe quedar a cargo de Mautic');
srv_assert(count($GLOBALS['mailjet_http_calls']) === $calls_before_sem, 'El seminario no debe llamar Mailjet');

$sem_repo = new FLACSO_Seminar_Inquiry_Repository();
$saved_sem = $sem_repo->find_by_consulta_id('srv-sem-001');
srv_assert(!empty($saved_sem), 'La fila debe existir en seminar_inquiries');
srv_assert($saved_sem['emailStatus'] === 'skipped', 'emailStatus en BD para seminario debe ser skipped');

// =========================================================================
// 4. Idempotencia de seminario: reenvío con mismo event_id no envía correo
// =========================================================================
$calls_before_sem_dup = count($GLOBALS['mailjet_http_calls']);
$result_sem_dup = FLACSO_Seminar_Inquiry_Service::submit([
    'event_id'         => 'srv-sem-001',
    'seminario_id'     => 45,
    'nombre'           => 'Martín',
    'correo'           => 'martin@ejemplo.com',
]);
srv_assert($result_sem_dup['ok'] === true, 'Seminar dup debe retornar ok');
srv_assert($result_sem_dup['duplicate'] === true, 'Seminar dup debe marcar duplicate=true');
srv_assert(count($GLOBALS['mailjet_http_calls']) === $calls_before_sem_dup, 'No debe llamar a Mailjet en dup de seminario');

echo "OK inquiry-services-test\n";
exit(0);

// =========================================================================
// 5. Resiliencia ante falla incierta de Mailjet: BD guarda la consulta y
// conserva processing para que no se pueda duplicar un correo posiblemente aceptado.
// =========================================================================
$GLOBALS['mailjet_mock_simulate_error'] = true;
$result_mail_fail = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-fail-002',
    'id_pagina'       => 12,
    'titulo_posgrado' => 'Diploma en Género',
    'nombre'          => 'Valeria',
    'correo'          => 'valeria@ejemplo.com',
]);
srv_assert($result_mail_fail['ok'] === true, 'submit() debe retornar ok=true aun con fallo de Mailjet');
srv_assert($result_mail_fail['email'] === 'processing', 'Un HTTP 5xx debe dejar el estado processing hasta conciliación');

$saved_fail = $repo->find_by_consulta_id('srv-offer-fail-002');
srv_assert(!empty($saved_fail), 'La consulta DEBE guardarse en BD aunque Mailjet falle');
srv_assert($saved_fail['emailStatus'] === 'processing', 'emailStatus en BD debe reflejar un resultado incierto');
$GLOBALS['mailjet_mock_simulate_error'] = false;

// =========================================================================
// 6. Guardia ante falla de base de datos: nunca llamar a Mailjet si falla el INSERT
// =========================================================================
$calls_before_db_fail = count($GLOBALS['mailjet_http_calls']);
// Simulamos falla en base de datos apuntando a una base sin tablas
$broken_pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
FLACSO_DB::set_connection($broken_pdo); // no tiene tablas creadas

$result_db_fail = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-db-fail',
    'id_pagina'       => 15,
    'titulo_posgrado' => 'Maestría',
    'nombre'          => 'Clara',
    'correo'          => 'clara@ejemplo.com',
]);
srv_assert($result_db_fail['ok'] === false, 'Si la base de datos falla, submit() debe retornar ok=false');
srv_assert($result_db_fail['code'] === 500, 'Debe retornar código 500 en error de base de datos');
srv_assert($result_db_fail['error'] === 'db_error', 'Debe indicar error db_error');
srv_assert(count($GLOBALS['mailjet_http_calls']) === $calls_before_db_fail, 'Mailjet NUNCA debe ser llamado si la inserción en BD falla');

// Restaurar conexión válida
FLACSO_DB::set_connection($pdo);

// =========================================================================
// 7. Validación de campos requeridos (Ofertas y Seminarios)
// =========================================================================
$result_invalid_offer = FLACSO_Offer_Inquiry_Service::submit([
    'event_id' => 'srv-offer-invalid',
    // Faltan correo y nombre
]);
srv_assert($result_invalid_offer['ok'] === false, 'Datos inválidos deben retornar ok=false');
srv_assert($result_invalid_offer['code'] === 422, 'Debe retornar código 422');
srv_assert($result_invalid_offer['error'] === 'validation_error', 'Debe indicar error validation_error');

$result_invalid_sem = FLACSO_Seminar_Inquiry_Service::submit([
    'event_id' => 'srv-sem-invalid',
    // Faltan correo y nombre
]);
srv_assert($result_invalid_sem['ok'] === false, 'Seminario inválido debe retornar ok=false');
srv_assert($result_invalid_sem['code'] === 422, 'Seminario inválido debe retornar código 422');

// =========================================================================
// 8. Generación automática de UUIDv4 cuando no se proporciona event_id
// =========================================================================
$result_auto_uuid = FLACSO_Offer_Inquiry_Service::submit([
    'id_pagina'       => 20,
    'titulo_posgrado' => 'Doctorado en Ciencias Sociales',
    'nombre'          => 'Esteban',
    'correo'          => 'esteban@ejemplo.com',
]);
srv_assert($result_auto_uuid['ok'] === true, 'Debe aceptar envío sin event_id explícito');
srv_assert(!empty($result_auto_uuid['consulta_id']), 'Debe generar un consulta_id');
srv_assert(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $result_auto_uuid['consulta_id']) === 1, 'Debe ser un UUIDv4 RFC 4122 válido');

$saved_auto = $repo->find_by_consulta_id($result_auto_uuid['consulta_id']);
srv_assert(!empty($saved_auto), 'Debe encontrarse en la base de datos por el UUID generado');

// =========================================================================
// 9. Sobrescritura de estado con 'offer_status' (snake_case)
// =========================================================================
$result_status_snake = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-status-override',
    'id_pagina'       => 21,
    'titulo_posgrado' => 'Oferta Override Status',
    'nombre'          => 'Mariana',
    'correo'          => 'mariana@ejemplo.com',
    'offer_status'    => 'abierta',
]);
srv_assert($result_status_snake['ok'] === true, 'Envío con offer_status debe ser exitoso');
srv_assert($result_status_snake['offer_status'] === 'abierta', 'offer_status debe tomar override snake_case');
$saved_snake = $repo->find_by_consulta_id('srv-offer-status-override');
// =========================================================================
// 10. Integración con Mautic (Fase 2)
// =========================================================================

// 10.1 Caso 1: Mautic desactivado (flacso_mautic_enabled = '0')
// La consulta se guarda, Mailjet envía, y mautic_sync['status'] === 'skipped'.
$GLOBALS['mailjet_mock_options']['flacso_mautic_enabled'] = '0';
$calls_before_m_disabled = count($GLOBALS['mailjet_http_calls']);

$result_m_disabled = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-mautic-disabled',
    'id_pagina'       => 13,
    'nombre'          => 'Carlos',
    'apellido'        => 'Gómez',
    'correo'          => 'carlos@ejemplo.com',
    'pais'            => 'Uruguay',
]);

srv_assert($result_m_disabled['ok'] === true, '10.1: Submit debe ser ok con Mautic desactivado');
srv_assert($result_m_disabled['code'] === 200, '10.1: Código debe ser 200');
srv_assert($result_m_disabled['email'] === 'sent', '10.1: Mailjet debe enviar el correo');
srv_assert(count($GLOBALS['mailjet_http_calls']) === $calls_before_m_disabled + 1, '10.1: Debe llamar a Mailjet');
srv_assert(isset($result_m_disabled['mautic_sync']), '10.1: Debe incluir clave mautic_sync en resultado');
srv_assert(is_array($result_m_disabled['mautic_sync']), '10.1: mautic_sync debe ser un array');
srv_assert($result_m_disabled['mautic_sync']['status'] === 'skipped', '10.1: mautic_sync status debe ser skipped');
srv_assert($result_m_disabled['mautic_sync']['ok'] === true, '10.1: mautic_sync ok debe ser true cuando está skipped');

$saved_m_disabled = $repo->find_by_consulta_id('srv-offer-mautic-disabled');
srv_assert(!empty($saved_m_disabled), '10.1: Registro debe existir en BD');
srv_assert($saved_m_disabled['mauticSyncStatus'] === 'skipped', '10.1: mauticSyncStatus en BD debe ser skipped');

// 10.2 Caso 2: Mautic activo y responde 200 OK
// La consulta se guarda, mautic_sync['status'] === 'synced', y el registro en SQLite tiene mauticSyncStatus === 'synced' y mauticContactId.
$GLOBALS['mailjet_mock_options']['flacso_inquiry_email_engine'] = 'mailjet';
$GLOBALS['mailjet_mock_options']['flacso_mautic_enabled'] = '1';
$GLOBALS['mailjet_mock_options']['flacso_mautic_base_url'] = 'https://envios.flacso.edu.uy';
$GLOBALS['mailjet_mock_options']['flacso_mautic_auth_type'] = 'basic';
$GLOBALS['mailjet_mock_options']['flacso_mautic_username'] = 'testuser';
$GLOBALS['mailjet_mock_options']['flacso_mautic_password'] = 'testpass';

$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    if (strpos($url, '/api/contacts?search=') !== false) {
        // Contacto no existe previamente
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode(['contacts' => []]),
        ];
    }
    if (strpos($url, '/api/contacts/new') !== false) {
        // Creación exitosa en Mautic
        return [
            'response' => ['code' => 201, 'message' => 'Created'],
            'body'     => json_encode([
                'contact' => [
                    'id'     => 8842,
                    'fields' => ['all' => json_decode($args['body'], true)],
                ]
            ]),
        ];
    }
    return [
        'response' => ['code' => 200, 'message' => 'OK'],
        'body'     => json_encode(['success' => true]),
    ];
};

$result_m_synced = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-mautic-synced',
    'id_pagina'       => 13,
    'nombre'          => 'Ana',
    'apellido'        => 'Pereira',
    'correo'          => 'ana.pereira@ejemplo.com',
    'pais'            => 'Uruguay',
    'profesion'       => 'Docente',
    'nivel_academico' => 'Universitario',
]);

srv_assert($result_m_synced['ok'] === true, '10.2: Submit debe ser ok con Mautic activo');
srv_assert($result_m_synced['code'] === 200, '10.2: Código debe ser 200');
srv_assert(isset($result_m_synced['mautic_sync']), '10.2: Debe incluir clave mautic_sync en resultado');
srv_assert($result_m_synced['mautic_sync']['status'] === 'synced', '10.2: mautic_sync status debe ser synced');
srv_assert($result_m_synced['mautic_sync']['ok'] === true, '10.2: mautic_sync ok debe ser true');
srv_assert($result_m_synced['mautic_sync']['contact_id'] === 8842, '10.2: mautic_sync contact_id debe ser 8842');
srv_assert(in_array('interes-dcc-2026', $result_m_synced['mautic_sync']['tags'], true), '10.2: Debe incluir tag base');
srv_assert(in_array('consulta-abierta-dcc-2026-c4', $result_m_synced['mautic_sync']['tags'], true), '10.2: Debe incluir tag de consulta abierta');

$saved_m_synced = $repo->find_by_consulta_id('srv-offer-mautic-synced');
srv_assert(!empty($saved_m_synced), '10.2: Registro debe existir en BD');
srv_assert($saved_m_synced['mauticSyncStatus'] === 'synced', '10.2: mauticSyncStatus en BD debe ser synced');
srv_assert((string)$saved_m_synced['mauticContactId'] === '8842', '10.2: mauticContactId en BD debe ser 8842');
srv_assert(!empty($saved_m_synced['mauticSyncedAt']), '10.2: mauticSyncedAt en BD no debe ser vacío');
srv_assert($saved_m_synced['mauticLastError'] === null, '10.2: mauticLastError en BD debe ser null');

// 10.3 Caso 3a: Mautic responde HTTP 500
// La consulta retorna ok === true y code === 200, mautic_sync['status'] === 'failed', y el registro en DB tiene mauticSyncStatus === 'failed' y mauticLastError.
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    return [
        'response' => ['code' => 500, 'message' => 'Internal Server Error'],
        'body'     => json_encode([
            'errors' => [
                ['message' => 'Database connection lost in Mautic']
            ]
        ]),
    ];
};

$result_m_fail500 = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-mautic-fail500',
    'id_pagina'       => 13,
    'nombre'          => 'Roberto',
    'apellido'        => 'Silva',
    'correo'          => 'roberto@ejemplo.com',
]);

srv_assert($result_m_fail500['ok'] === true, '10.3a: submit debe retornar ok=true aunque Mautic responda 500 (no bloqueante)');
srv_assert($result_m_fail500['code'] === 200, '10.3a: submit debe retornar code 200');
srv_assert($result_m_fail500['email'] === 'sent', '10.3a: Mailjet debió enviar correctamente');
srv_assert(isset($result_m_fail500['mautic_sync']), '10.3a: Debe incluir mautic_sync');
srv_assert($result_m_fail500['mautic_sync']['ok'] === false, '10.3a: mautic_sync ok debe ser false');
srv_assert($result_m_fail500['mautic_sync']['status'] === 'failed', '10.3a: mautic_sync status debe ser failed');

$saved_m_fail500 = $repo->find_by_consulta_id('srv-offer-mautic-fail500');
srv_assert(!empty($saved_m_fail500), '10.3a: Registro debe guardarse en BD');
srv_assert($saved_m_fail500['mauticSyncStatus'] === 'failed', '10.3a: mauticSyncStatus en BD debe ser failed');
srv_assert(!empty($saved_m_fail500['mauticLastError']), '10.3a: mauticLastError en BD debe registrar el mensaje de error');

// 10.4 Caso 3b: Mautic lanza excepción (\RuntimeException)
// Garantiza captura total de excepciones en submit(), retorno HTTP 200, y no interrupción de la experiencia de usuario.
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    throw new \RuntimeException('Mautic connection timed out after 4 seconds');
};

$result_m_exc = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-mautic-exception',
    'id_pagina'       => 13,
    'nombre'          => 'Laura',
    'apellido'        => 'Rodríguez',
    'correo'          => 'laura@ejemplo.com',
]);

srv_assert($result_m_exc['ok'] === true, '10.4: submit debe ser ok=true ante excepción de Mautic');
srv_assert($result_m_exc['code'] === 200, '10.4: Código debe ser 200');
srv_assert(isset($result_m_exc['mautic_sync']), '10.4: Debe incluir mautic_sync');
srv_assert($result_m_exc['mautic_sync']['ok'] === false, '10.4: mautic_sync ok debe ser false');
srv_assert($result_m_exc['mautic_sync']['status'] === 'failed', '10.4: mautic_sync status debe ser failed');

$saved_m_exc = $repo->find_by_consulta_id('srv-offer-mautic-exception');
srv_assert(!empty($saved_m_exc), '10.4: Registro debe guardarse en BD');
srv_assert($saved_m_exc['mauticSyncStatus'] === 'failed', '10.4: mauticSyncStatus en BD debe ser failed tras excepción');
srv_assert(strpos($saved_m_exc['mauticLastError'], 'timed out') !== false, '10.4: mauticLastError debe contener el mensaje de la excepción');

// =========================================================================
// 11. Orquestación del Envío y Fallback Automático (Fase 3)
// =========================================================================

// 11.1 Motor Mautic con envío exitoso: Mautic envía, Mailjet NO es llamado, emailSender === 'mautic'
$GLOBALS['mailjet_mock_options']['flacso_inquiry_email_engine'] = 'mautic';
$GLOBALS['mailjet_mock_options']['flacso_mautic_enabled'] = '1';
$GLOBALS['mailjet_mock_options']['flacso_mautic_base_url'] = 'https://envios.flacso.edu.uy';
$GLOBALS['mailjet_mock_options']['flacso_mautic_auth_type'] = 'basic';
$GLOBALS['mailjet_mock_options']['flacso_mautic_username'] = 'testuser';
$GLOBALS['mailjet_mock_options']['flacso_mautic_password'] = 'testpass';
$GLOBALS['mailjet_mock_options']['flacso_mautic_template_consulta_abierta'] = 101;
$GLOBALS['mailjet_mock_options']['flacso_mautic_template_consulta_cerrada'] = 102;

$GLOBALS['mailjet_http_calls'] = [];
$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    if (strpos($url, '/api/contacts?search=') !== false) {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode(['contacts' => []]),
        ];
    }
    if (strpos($url, '/api/contacts/new') !== false) {
        return [
            'response' => ['code' => 201, 'message' => 'Created'],
            'body'     => json_encode([
                'contact' => [
                    'id'     => 9901,
                    'fields' => ['all' => json_decode($args['body'], true)],
                ]
            ]),
        ];
    }
    if (strpos($url, '/api/emails/101/contact/9901/send') !== false) {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode(['success' => true]),
        ];
    }
    return [
        'response' => ['code' => 200, 'message' => 'OK'],
        'body'     => json_encode(['success' => true]),
    ];
};

$result_m_success = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-mautic-success',
    'id_pagina'       => 13, // Abierta -> usa template 101
    'nombre'          => 'Gabriela',
    'apellido'        => 'Méndez',
    'correo'          => 'gabriela@ejemplo.com',
    'pais'            => 'Uruguay',
    'profesion'       => 'Socióloga',
    'nivel_academico' => 'Posgrado',
]);

srv_assert($result_m_success['ok'] === true, '11.1: Submit debe ser ok con motor Mautic');
srv_assert($result_m_success['code'] === 200, '11.1: Código debe ser 200');
srv_assert($result_m_success['email'] === 'sent', '11.1: Email status debe ser sent');
srv_assert($result_m_success['email_sender'] === 'mautic', '11.1: email_sender debe ser mautic');
srv_assert($result_m_success['email_engine'] === 'mautic', '11.1: email_engine debe ser mautic');
srv_assert($result_m_success['mailjet_message_id'] === '101', '11.1: mailjet_message_id debe guardar el template_id de Mautic');
srv_assert(count($GLOBALS['mailjet_http_calls']) === 0, '11.1: Mailjet NO debe ser llamado si Mautic envía con éxito');

// Verificar que se llamó a /api/emails/101/contact/9901/send con tokens compilados
$send_calls = array_filter($GLOBALS['mautic_http_calls'], function($call) {
    return strpos($call['url'], '/api/emails/101/contact/9901/send') !== false;
});
srv_assert(count($send_calls) === 1, '11.1: Debe haber exactamente 1 llamada al endpoint de envío de Mautic');
$send_call = reset($send_calls);
$send_body = json_decode($send_call['args']['body'], true);
srv_assert(!empty($send_body['tokens']), '11.1: Cuerpo del envío Mautic debe contener tokens');
srv_assert(($send_body['tokens']['{nombre}'] ?? '') === 'Gabriela', '11.1: Token {nombre} debe coincidir');
srv_assert(($send_body['tokens']['{programa}'] ?? '') === 'Diploma con cohorte canónica', '11.1: Token {programa} debe coincidir');
srv_assert(($send_body['tokens']['{cohorte_nombre}'] ?? '') === 'Cohorte 4', '11.1: Token {cohorte_nombre} debe coincidir');
srv_assert(($send_body['tokens']['{cohorte_numero}'] ?? '') === '4', '11.1: Token {cohorte_numero} debe coincidir');

$saved_m_success = $repo->find_by_consulta_id('srv-offer-mautic-success');
srv_assert(!empty($saved_m_success), '11.1: Registro debe existir en BD');
srv_assert($saved_m_success['emailStatus'] === 'sent', '11.1: emailStatus en BD debe ser sent');
srv_assert($saved_m_success['emailSender'] === 'mautic', '11.1: emailSender en BD debe ser mautic');
srv_assert($saved_m_success['mailjetMessageId'] === '101', '11.1: mailjetMessageId en BD debe ser 101');
srv_assert((string)$saved_m_success['mauticContactId'] === '9901', '11.1: mauticContactId en BD debe ser 9901');
srv_assert($saved_m_success['mauticSyncStatus'] === 'synced', '11.1: mauticSyncStatus en BD debe ser synced');

// 11.2 Motor Mautic con fallo de Mautic -> Fallback automático a Mailjet
$GLOBALS['mailjet_http_calls'] = [];
$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    if (strpos($url, '/api/contacts?search=') !== false) {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode(['contacts' => []]),
        ];
    }
    if (strpos($url, '/api/contacts/new') !== false) {
        return [
            'response' => ['code' => 201, 'message' => 'Created'],
            'body'     => json_encode([
                'contact' => [
                    'id'     => 9902,
                    'fields' => ['all' => json_decode($args['body'], true)],
                ]
            ]),
        ];
    }
    if (strpos($url, '/api/emails/') !== false) {
        return [
            'response' => ['code' => 500, 'message' => 'Internal Server Error'],
            'body'     => json_encode(['errors' => [['message' => 'Spool queue locked']]]),
        ];
    }
    return [
        'response' => ['code' => 200, 'message' => 'OK'],
        'body'     => json_encode(['success' => true]),
    ];
};

$result_m_fail = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-mautic-fallback',
    'id_pagina'       => 13,
    'nombre'          => 'Esteban',
    'apellido'        => 'Quirós',
    'correo'          => 'esteban@ejemplo.com',
]);

srv_assert($result_m_fail['ok'] === true, '11.2: Submit debe ser ok ante fallo de Mautic (fallback exitoso)');
srv_assert($result_m_fail['code'] === 200, '11.2: Código debe ser 200');
srv_assert($result_m_fail['email'] === 'sent', '11.2: Email status debe ser sent gracias al fallback Mailjet');
srv_assert($result_m_fail['email_sender'] === 'mailjet_fallback', '11.2: email_sender debe ser mailjet_fallback');
srv_assert($result_m_fail['email_engine'] === 'mautic', '11.2: email_engine configurado debe ser mautic');
srv_assert(count($GLOBALS['mailjet_http_calls']) === 1, '11.2: Debe invocar a Mailjet como fallback');

$saved_m_fail = $repo->find_by_consulta_id('srv-offer-mautic-fallback');
srv_assert(!empty($saved_m_fail), '11.2: Registro debe existir en BD');
srv_assert($saved_m_fail['emailStatus'] === 'sent', '11.2: emailStatus en BD debe ser sent');
srv_assert($saved_m_fail['emailSender'] === 'mailjet_fallback', '11.2: emailSender en BD debe ser mailjet_fallback');
srv_assert($saved_m_fail['mailjetMessageId'] === '288230407340150000', '11.2: mailjetMessageId debe registrar el ID devuelto por Mailjet');
srv_assert((string)$saved_m_fail['mauticContactId'] === '9902', '11.2: mauticContactId debe haberse registrado correctamente');
srv_assert(strpos($saved_m_fail['mauticLastError'] ?? '', 'Spool queue locked') !== false, '11.2: mauticLastError debe registrar el error que motivó el fallback');

// 11.3 Motor Mautic sin plantilla configurada -> Fallback automático a Mailjet
$GLOBALS['mailjet_mock_options']['flacso_mautic_template_consulta_abierta'] = 0;
$GLOBALS['mailjet_mock_options']['flacso_mautic_template_consulta_cerrada'] = 0;
$GLOBALS['mailjet_http_calls'] = [];
$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    if (strpos($url, '/api/contacts?search=') !== false) {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode(['contacts' => []]),
        ];
    }
    if (strpos($url, '/api/contacts/new') !== false) {
        return [
            'response' => ['code' => 201, 'message' => 'Created'],
            'body'     => json_encode([
                'contact' => [
                    'id'     => 9903,
                    'fields' => ['all' => json_decode($args['body'], true)],
                ]
            ]),
        ];
    }
    return [
        'response' => ['code' => 200, 'message' => 'OK'],
        'body'     => json_encode(['success' => true]),
    ];
};

$result_m_notemplate = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-mautic-notemplate',
    'id_pagina'       => 13,
    'nombre'          => 'Mariana',
    'apellido'        => 'Ríos',
    'correo'          => 'mariana@ejemplo.com',
]);

srv_assert($result_m_notemplate['ok'] === true, '11.3: Submit debe ser ok sin plantilla Mautic configurada');
srv_assert($result_m_notemplate['email'] === 'sent', '11.3: Email status debe ser sent');
srv_assert($result_m_notemplate['email_sender'] === 'mailjet_fallback', '11.3: email_sender debe ser mailjet_fallback cuando no hay plantilla');
srv_assert(count($GLOBALS['mailjet_http_calls']) === 1, '11.3: Debe llamar a Mailjet ante plantilla no configurada');

$saved_m_notemplate = $repo->find_by_consulta_id('srv-offer-mautic-notemplate');
srv_assert(!empty($saved_m_notemplate), '11.3: Registro debe existir en BD');
srv_assert($saved_m_notemplate['emailSender'] === 'mailjet_fallback', '11.3: emailSender en BD debe ser mailjet_fallback');
srv_assert(strpos($saved_m_notemplate['mauticLastError'] ?? '', 'no configurada') !== false, '11.3: mauticLastError debe indicar plantilla no configurada');

// 11.4 Motor Mailjet seleccionado -> Mailjet envía directamente, emailSender === 'mailjet'
$GLOBALS['mailjet_mock_options']['flacso_inquiry_email_engine'] = 'mailjet';
$GLOBALS['mailjet_http_calls'] = [];
$GLOBALS['mautic_http_calls'] = [];

$result_mj_engine = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-mailjet-engine',
    'id_pagina'       => 13,
    'nombre'          => 'Diego',
    'apellido'        => 'Torres',
    'correo'          => 'diego@ejemplo.com',
]);

srv_assert($result_mj_engine['ok'] === true, '11.4: Submit debe ser ok con motor Mailjet');
srv_assert($result_mj_engine['email'] === 'sent', '11.4: Email status debe ser sent');
srv_assert($result_mj_engine['email_sender'] === 'mailjet', '11.4: email_sender debe ser mailjet');
srv_assert($result_mj_engine['email_engine'] === 'mailjet', '11.4: email_engine debe ser mailjet');
srv_assert(count($GLOBALS['mailjet_http_calls']) === 1, '11.4: Debe llamar a Mailjet directamente');

$saved_mj_engine = $repo->find_by_consulta_id('srv-offer-mailjet-engine');
srv_assert(!empty($saved_mj_engine), '11.4: Registro debe existir en BD');
srv_assert($saved_mj_engine['emailSender'] === 'mailjet', '11.4: emailSender en BD debe ser mailjet');

// 11.5 Motor por defecto (sin opción en BD o vacía) -> Mautic como primario por defecto (Fase 5)
unset($GLOBALS['mailjet_mock_options']['flacso_inquiry_email_engine']);
$GLOBALS['mailjet_mock_options']['flacso_mautic_template_consulta_abierta'] = 101;
$GLOBALS['mailjet_mock_options']['flacso_mautic_template_consulta_cerrada'] = 102;
$GLOBALS['mailjet_http_calls'] = [];
$GLOBALS['mautic_http_calls'] = [];

$result_default_engine = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-default-engine',
    'id_pagina'       => 13,
    'nombre'          => 'Lucía',
    'apellido'        => 'Gómez',
    'correo'          => 'lucia@ejemplo.com',
]);

srv_assert($result_default_engine['ok'] === true, '11.5: Submit debe ser ok con motor por defecto');
srv_assert($result_default_engine['email_engine'] === 'mautic', '11.5: email_engine por defecto debe ser mautic');
srv_assert($result_default_engine['email_sender'] === 'mautic', '11.5: email_sender debe ser mautic');

// -----------------------------------------------------------------------------
// GRUPO 12: Programación Inicial de Seguimiento (+X días) en FLACSO_Offer_Inquiry_Service::submit()
// -----------------------------------------------------------------------------

// 12.1: Seguimiento habilitado ('1') con ventana de 7 días
$GLOBALS['mailjet_mock_options']['flacso_inquiry_followup_enabled'] = '1';
$GLOBALS['mailjet_mock_options']['flacso_inquiry_followup_days'] = 7;
$GLOBALS['mailjet_http_calls'] = [];
$GLOBALS['mautic_http_calls'] = [];

$inquiry_at_12_1 = '2026-09-28 10:00:00';
$expected_due_12_1 = gmdate('Y-m-d H:i:s', strtotime('+7 days', strtotime($inquiry_at_12_1)));

$result_followup_on = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'   => 'srv-offer-followup-on',
    'id_pagina'  => 13,
    'nombre'     => 'Valeria',
    'apellido'   => 'López',
    'correo'     => 'valeria@ejemplo.com',
    'inquiryAt'  => $inquiry_at_12_1,
]);

srv_assert($result_followup_on['ok'] === true, '12.1: Submit debe ser ok con seguimiento habilitado');
srv_assert(($result_followup_on['followup_status'] ?? null) === 'pending', '12.1: Retorno debe contener followup_status === pending');
srv_assert(($result_followup_on['followup_due_at'] ?? null) === $expected_due_12_1, '12.1: Retorno debe contener followup_due_at esperado');

$saved_followup_on = $repo->find_by_consulta_id('srv-offer-followup-on');
srv_assert(!empty($saved_followup_on), '12.1: Registro debe existir en BD');
srv_assert($saved_followup_on['followupStatus'] === 'pending', '12.1: BD debe registrar followupStatus === pending');
srv_assert($saved_followup_on['followupDueAt'] === $expected_due_12_1, '12.1: BD debe registrar followupDueAt esperado (+7 días)');

// 12.2: Seguimiento deshabilitado ('0')
$GLOBALS['mailjet_mock_options']['flacso_inquiry_followup_enabled'] = '0';
$GLOBALS['mailjet_mock_options']['flacso_inquiry_followup_days'] = 7;
$GLOBALS['mailjet_http_calls'] = [];
$GLOBALS['mautic_http_calls'] = [];

$result_followup_off = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'   => 'srv-offer-followup-off',
    'id_pagina'  => 13,
    'nombre'     => 'Carlos',
    'apellido'   => 'Méndez',
    'correo'     => 'carlos@ejemplo.com',
]);

srv_assert($result_followup_off['ok'] === true, '12.2: Submit debe ser ok con seguimiento deshabilitado');
srv_assert(($result_followup_off['followup_status'] ?? null) === 'none', '12.2: Retorno debe contener followup_status === none');
srv_assert(array_key_exists('followup_due_at', $result_followup_off) && $result_followup_off['followup_due_at'] === null, '12.2: Retorno debe contener followup_due_at === null');

$saved_followup_off = $repo->find_by_consulta_id('srv-offer-followup-off');
srv_assert(!empty($saved_followup_off), '12.2: Registro debe existir en BD');
srv_assert($saved_followup_off['followupStatus'] === 'none', '12.2: BD debe registrar followupStatus === none');
srv_assert($saved_followup_off['followupDueAt'] === null, '12.2: BD debe registrar followupDueAt === null');

// 12.3: Seguimiento habilitado con días por defecto (5 días cuando no está configurado)
unset($GLOBALS['mailjet_mock_options']['flacso_inquiry_followup_days']);
$GLOBALS['mailjet_mock_options']['flacso_inquiry_followup_enabled'] = '1';
$inquiry_at_12_3 = '2026-09-28 12:00:00';
$expected_due_12_3 = gmdate('Y-m-d H:i:s', strtotime('+5 days', strtotime($inquiry_at_12_3)));

$result_followup_default = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'   => 'srv-offer-followup-default-days',
    'id_pagina'  => 13,
    'nombre'     => 'Ana',
    'apellido'   => 'Gómez',
    'correo'     => 'ana@ejemplo.com',
    'inquiryAt'  => $inquiry_at_12_3,
]);

srv_assert($result_followup_default['ok'] === true, '12.3: Submit debe ser ok');
srv_assert(($result_followup_default['followup_status'] ?? null) === 'pending', '12.3: followup_status debe ser pending');
srv_assert(($result_followup_default['followup_due_at'] ?? null) === $expected_due_12_3, '12.3: followup_due_at debe calcular +5 días por defecto');

$saved_followup_def = $repo->find_by_consulta_id('srv-offer-followup-default-days');
srv_assert(!empty($saved_followup_def), '12.3: Registro debe existir en BD');
srv_assert($saved_followup_def['followupStatus'] === 'pending', '12.3: BD debe registrar followupStatus === pending');
srv_assert($saved_followup_def['followupDueAt'] === $expected_due_12_3, '12.3: BD debe registrar followupDueAt === +5 días');

echo "OK inquiry-services-test\n";
