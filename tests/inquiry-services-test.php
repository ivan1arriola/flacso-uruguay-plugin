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

if (!class_exists('FLACSO_Academic_Catalog')) {
    class FLACSO_Academic_Catalog {
        public static function get_offer(int $id): array {
            if ($id !== 13) {
                return [];
            }

            return [
                'id' => 13,
                'nombre' => 'Diploma con cohorte canónica',
                'correo' => 'coordinacion@flacso.edu.uy',
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

require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-seminar-inquiry-repository.php';
require_once $root . '/includes/integrations/class-flacso-mailjet-client.php';
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
    id TEXT PRIMARY KEY, consultaId TEXT UNIQUE, offerWpId INTEGER, offerName TEXT, offerType TEXT,
    firstName TEXT, lastName TEXT, fullName TEXT, email TEXT, emailNormalized TEXT, country TEXT,
    profession TEXT, educationLevel TEXT, source TEXT, campaignProvider TEXT, campaignSource TEXT,
    campaignMedium TEXT, campaignName TEXT, campaignExternalId TEXT, campaignContent TEXT, campaignTerm TEXT,
    urlBase TEXT, urlReferer TEXT, inquiryAt TEXT, ipAddress TEXT, userAgent TEXT, replyToEmail TEXT,
    programUrl TEXT, cartaUrl TEXT, preinscripcionUrl TEXT, offerStatus TEXT, emailStatus TEXT,
    emailSender TEXT, gmailMessageUrl TEXT, mailjetMessageId TEXT, mailjetMessageUuid TEXT, payload TEXT, createdAt TEXT, updatedAt TEXT
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
srv_assert($result_offer['email'] === 'sent', 'Email status debe ser sent');
srv_assert($result_offer['mailjet_message_id'] === '288230407340150000', 'Debe retornar mailjet_message_id');
srv_assert(count($GLOBALS['mailjet_http_calls']) === $initial_mail_calls + 1, 'Debe haber llamado a Mailjet una vez');

$repo = new FLACSO_Offer_Inquiry_Repository();
$saved = $repo->find_by_consulta_id('srv-offer-001');
srv_assert(!empty($saved), 'La fila debe existir en offer_inquiries');
srv_assert($saved['emailStatus'] === 'sent', 'emailStatus en BD debe ser sent');
srv_assert($saved['mailjetMessageId'] === '288230407340150000', 'mailjetMessageId debe guardarse en BD');
srv_assert($saved['mailjetMessageUuid'] === 'f7b8a8b1-1234-5678-90ab-cdef12345678', 'mailjetMessageUuid debe guardarse en BD');

// =========================================================================
// 1.2 Datos canónicos de Cohorte: modalidad, fecha y Reply-To de la oferta
// =========================================================================
$result_catalog = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'  => 'srv-offer-catalog-003',
    'id_pagina' => 13,
    'nombre'    => 'Sofía',
    'correo'    => 'sofia@ejemplo.com',
]);
srv_assert($result_catalog['ok'] === true, 'Offer con catálogo canónico debe ser ok');
$catalog_call = end($GLOBALS['mailjet_http_calls']);
$catalog_payload = json_decode($catalog_call['args']['body'], true);
srv_assert(($catalog_payload['Messages'][0]['ReplyTo']['Email'] ?? '') === 'coordinacion@flacso.edu.uy', 'Debe usar correo de coordinación como Reply-To');
srv_assert(($catalog_payload['Messages'][0]['Variables']['oferta_academica_modalidad'] ?? '') === 'Híbrida', 'Debe tomar y humanizar modalidad de cohorte vigente');
srv_assert(($catalog_payload['Messages'][0]['Variables']['oferta_academica_fecha_inicio'] ?? '') === '2 de septiembre de 2026', 'Debe formatear fecha de cohorte vigente');

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
srv_assert($result_sem['email'] === 'sent', 'Email status del seminario debe ser sent');
srv_assert(count($GLOBALS['mailjet_http_calls']) === $calls_before_sem + 1, 'Debe haber llamado a Mailjet para el seminario');

$sem_repo = new FLACSO_Seminar_Inquiry_Repository();
$saved_sem = $sem_repo->find_by_consulta_id('srv-sem-001');
srv_assert(!empty($saved_sem), 'La fila debe existir en seminar_inquiries');
srv_assert($saved_sem['emailStatus'] === 'sent', 'emailStatus en BD para seminario debe ser sent');
srv_assert($saved_sem['mailjetMessageId'] === '288230407340150000', 'mailjetMessageId del seminario debe guardarse en BD');

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

echo "OK inquiry-services-test\n";
