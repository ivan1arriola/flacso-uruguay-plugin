<?php
/**
 * Campañas Mautic por oferta: consentimiento del servidor, ruta de la entrega,
 * alta en el plugin y vuelta al acuse transaccional si la oferta no tiene campaña.
 */
$root = dirname(__DIR__);
require_once __DIR__ . '/support/inquiry-delivery-bootstrap.php';

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $message;
        public function __construct(string $message) { $this->message = $message; }
        public function get_error_message(): string { return $this->message; }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($value) { return $value instanceof WP_Error; }
}
$GLOBALS['campaign_options'] = [
    'flacso_mautic_enabled' => '1',
    'flacso_mautic_base_url' => 'https://mautic.example.org',
    'flacso_mautic_auth_type' => 'basic',
    'flacso_mautic_username' => 'test',
    'flacso_mautic_password' => 'secret',
    'flacso_inquiry_delivery_provider' => 'mautic',
];
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['campaign_options'][$key] ?? $default;
    }
}

require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-base-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-tag-factory.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-snapshot.php';
require_once $root . '/modules/consultas/services/class-flacso-mautic-contract-manifest.php';
require_once $root . '/includes/database/repositories/class-flacso-inquiry-delivery-repository.php';
require_once $root . '/includes/integrations/class-flacso-mautic-client.php';
require_once $root . '/modules/consultas/services/class-flacso-mautic-contract-validator.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-delivery-service.php';

function campaign_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function campaigns_on(bool $on): void {
    $GLOBALS['campaign_options'][FLACSO_Offer_Campaigns::OPTION_ENABLED] = $on ? '1' : '0';
}

$pdo = flacso_test_delivery_pdo();
FLACSO_DB::set_connection($pdo);
$source = new FLACSO_Offer_Inquiry_Repository();
$repository = new FLACSO_Inquiry_Delivery_Repository($pdo);
FLACSO_Inquiry_Delivery_Service::set_repository($repository);
FLACSO_Inquiry_Delivery_Service::set_contract_validator(fn() => ['ok' => true, 'status' => 'valid']);

$form_consent = static fn (): array => FLACSO_Offer_Campaigns::form_consent(123, '2026-10-25T12:00:00+00:00');

// --- Consentimiento del servidor
campaigns_on(false);
campaign_assert($form_consent() === [], 'con el interruptor apagado no se registra consentimiento');
campaigns_on(true);
$consent = $form_consent();
campaign_assert($consent['granted'] === true && $consent['source'] === 'mas-informacion-123', 'el formulario genera la evidencia con su origen');
campaign_assert($consent['textVersion'] === FLACSO_Offer_Campaigns::CONSENT_TEXT_VERSION, 'registra la versión del texto');
campaign_assert(FLACSO_Offer_Campaigns::form_consent(0, 'x') === [], 'sin oferta no hay evidencia');
campaign_assert(FLACSO_Offer_Campaigns::valid_consent($consent), 'la evidencia del formulario es válida');
campaign_assert(!FLACSO_Offer_Campaigns::valid_consent(['granted' => '1'] + $consent), 'granted debe ser true literal');
campaign_assert(!FLACSO_Offer_Campaigns::valid_consent(['acceptedAt' => 'ayer'] + $consent), 'fecha inválida');
campaign_assert(!FLACSO_Offer_Campaigns::valid_consent(['textVersion' => ''] + $consent), 'sin versión del texto');

// --- Ruta de la entrega
$offer_delivery = ['inquiryType' => 'offer', 'assignedProvider' => 'mautic'];
$with_consent = ['marketingConsent' => $consent];
campaign_assert(FLACSO_Offer_Campaigns::routes_to_campaign($offer_delivery, $with_consent), 'oferta con consentimiento va a la campaña');
campaign_assert(!FLACSO_Offer_Campaigns::routes_to_campaign(['inquiryType' => 'seminar'] + $offer_delivery, $with_consent), 'los seminarios no');
campaign_assert(!FLACSO_Offer_Campaigns::routes_to_campaign(['assignedProvider' => 'flacso_mail'] + $offer_delivery, $with_consent), 'otro proveedor no');
campaign_assert(!FLACSO_Offer_Campaigns::routes_to_campaign($offer_delivery, ['marketingConsent' => ['granted' => false]]), 'sin consentimiento no');
campaigns_on(false);
campaign_assert(!FLACSO_Offer_Campaigns::routes_to_campaign($offer_delivery, $with_consent), 'con el interruptor apagado no');
campaigns_on(true);

// --- Entregas
function campaign_delivery(FLACSO_Inquiry_Delivery_Repository $repository, FLACSO_Offer_Inquiry_Repository $source, string $consulta, array $consent): array {
    $form = [
        'nombre' => 'Ana', 'apellido' => 'Pérez', 'correo' => 'ana@example.org',
        'country' => 'Uruguay', 'educationLevel' => 'Grado', 'profession' => 'Docente',
        'programUrl' => 'https://flacso.edu.uy/mae', 'inquiryAt' => '2026-10-25T12:00:00+00:00',
    ];
    $context = [
        'offerWpId' => 123, 'offerName' => 'Maestría en Educación', 'offerAbbreviation' => 'MAE',
        'offerType' => 'maestria', 'cohortNumber' => 6, 'offerStatus' => 'abierta',
        'startDate' => '2027-04-15', 'startDatePrecision' => 'day', 'modality' => 'virtual',
    ];
    $snapshot = FLACSO_Inquiry_Snapshot::from_offer($form, $context, $consulta);
    $result = $repository->persist_inquiry_with_delivery($source, [
        'consultaId' => $consulta, 'offerWpId' => 123, 'offerName' => 'Maestría en Educación',
        'offerAbbreviation' => 'mae', 'firstName' => 'Ana', 'lastName' => 'Pérez', 'email' => 'ana@example.org',
        'inquiryAt' => '2026-10-25T12:00:00+00:00', 'emailStatus' => 'pending', 'emailSender' => 'mautic_transactional_queue',
    ], $snapshot, 'offer', 'mautic', $consent);
    FLACSO_DB::connection()->prepare('UPDATE inquiry_deliveries SET templateId = 4, templateVersion = :v, templateSha256 = :s WHERE id = :id')
        ->execute([':v' => 'test', ':s' => str_repeat('a', 64), ':id' => $result['delivery_id']]);
    $repository->claim_pending_batch(10, 60);
    return $result;
}

/** Transporte HTTP falso: el alta responde según $enroll; el acuse transaccional siempre acepta. */
function campaign_transport(array $enroll, array &$calls): void {
    FLACSO_Mautic_Client::set_http_transport(function ($url, $args) use ($enroll, &$calls) {
        if (str_contains($url, '/api/flacso/inquiries')) {
            $calls[] = ['enroll', json_decode((string) $args['body'], true), $args['timeout'] ?? null];
            return $enroll[0] === 'wp_error' ? new WP_Error('timeout') : ['response' => ['code' => $enroll[0]], 'body' => json_encode($enroll[1])];
        }
        if (str_contains($url, '/api/contacts?search=')) {
            return ['response' => ['code' => 200], 'body' => json_encode(['contacts' => [['id' => 42]]])];
        }
        if (str_contains($url, '/api/contacts/42/edit')) {
            return ['response' => ['code' => 200], 'body' => json_encode(['contact' => ['id' => 42]])];
        }
        if (str_contains($url, '/contact/42/send')) {
            $calls[] = ['acuse', null, null];
            return ['response' => ['code' => 200], 'body' => json_encode(['success' => true])];
        }
        return ['response' => ['code' => 404], 'body' => '{}'];
    });
}

function delivery_row(string $id): array {
    $stmt = FLACSO_DB::connection()->prepare('SELECT * FROM inquiry_deliveries WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Alta correcta
$calls = [];
campaign_transport([201, ['ok' => true, 'contactId' => 77, 'campaignId' => 7]], $calls);
$d = campaign_delivery($repository, $source, 'camp-1', $consent);
$r = FLACSO_Inquiry_Delivery_Service::process($d['delivery_id']);
campaign_assert($r['status'] === 'accepted' && ($r['route'] ?? '') === 'campaign', 'el alta aceptada cierra la entrega por la campaña');
campaign_assert(count($calls) === 1 && $calls[0][0] === 'enroll', 'no envía el acuse transaccional');
$body = $calls[0][1];
campaign_assert($body['consultaId'] === 'camp-1' && $body['offerWpId'] === 123, 'identifica consulta y oferta');
campaign_assert($body['contact']['email'] === 'ana@example.org' && $body['contact']['pais'] === 'Uruguay' && $body['contact']['profesion'] === 'Docente', 'envía el perfil estable');
campaign_assert($body['consent'] === $consent, 'envía la evidencia de consentimiento');
campaign_assert(in_array('interes-mae', $body['tags'], true), 'envía los tags de la consulta');
campaign_assert($calls[0][2] === 30, 'usa un timeout largo para el alta');
campaign_assert((int) delivery_row($d['delivery_id'])['contactId'] === 77, 'guarda el contacto devuelto por el plugin');

// Oferta sin campaña: vuelve al acuse transaccional
$calls = [];
campaign_transport([422, ['ok' => false, 'error' => 'campaign_not_configured', 'message' => 'La oferta 123 no tiene una campaña asociada.']], $calls);
$d = campaign_delivery($repository, $source, 'camp-2', $consent);
$r = FLACSO_Inquiry_Delivery_Service::process($d['delivery_id']);
campaign_assert($r['status'] === 'accepted' && !isset($r['route']), 'sin campaña, la entrega se completa con el acuse transaccional');
campaign_assert(array_column($calls, 0) === ['enroll', 'acuse'], 'intenta el alta y luego envía el acuse');

$calls = [];
campaign_transport([422, ['ok' => false, 'error' => 'campaign_unpublished']], $calls);
$d = campaign_delivery($repository, $source, 'camp-3', $consent);
campaign_assert(FLACSO_Inquiry_Delivery_Service::process($d['delivery_id'])['status'] === 'accepted', 'campaña sin publicar también vuelve al acuse');

// Datos rechazados: definitivo, sin acuse transaccional
$calls = [];
campaign_transport([422, ['ok' => false, 'error' => 'validation_error', 'message' => 'contact.email inválido']], $calls);
$d = campaign_delivery($repository, $source, 'camp-4', $consent);
$r = FLACSO_Inquiry_Delivery_Service::process($d['delivery_id']);
campaign_assert($r['status'] === 'failed' && array_column($calls, 0) === ['enroll'], 'un rechazo de datos falla sin reintentar');
campaign_assert(str_contains((string) delivery_row($d['delivery_id'])['lastError'], 'contact.email'), 'conserva el motivo');

// Errores transitorios: reintenta y, al agotar los intentos, falla
foreach ([[503, []], ['wp_error', []], [401, []]] as $transient) {
    $calls = [];
    campaign_transport($transient, $calls);
    $d = campaign_delivery($repository, $source, 'camp-t-' . $transient[0], $consent);
    $r = FLACSO_Inquiry_Delivery_Service::process($d['delivery_id']);
    campaign_assert($r['status'] === 'retryable_failed', "HTTP {$transient[0]} se reintenta");
    campaign_assert(delivery_row($d['delivery_id'])['state'] === 'retryable_failed' || delivery_row($d['delivery_id'])['state'] === 'pending', 'la entrega queda para reintento');
}
$calls = [];
campaign_transport([503, []], $calls);
$d = campaign_delivery($repository, $source, 'camp-last', $consent);
FLACSO_DB::connection()->prepare('UPDATE inquiry_deliveries SET attempts = 5 WHERE id = :id')->execute([':id' => $d['delivery_id']]);
$r = FLACSO_Inquiry_Delivery_Service::process($d['delivery_id']);
campaign_assert($r['status'] === 'failed', 'al agotar los intentos la entrega falla');

// Sin consentimiento o con el interruptor apagado: acuse transaccional de siempre
$calls = [];
campaign_transport([201, ['ok' => true]], $calls);
$d = campaign_delivery($repository, $source, 'camp-noconsent', []);
campaign_assert(FLACSO_Inquiry_Delivery_Service::process($d['delivery_id'])['status'] === 'accepted' && array_column($calls, 0) === ['acuse'], 'sin consentimiento recibe el acuse transaccional');

campaigns_on(false);
$calls = [];
campaign_transport([201, ['ok' => true]], $calls);
$d = campaign_delivery($repository, $source, 'camp-off', $consent);
campaign_assert(FLACSO_Inquiry_Delivery_Service::process($d['delivery_id'])['status'] === 'accepted' && array_column($calls, 0) === ['acuse'], 'con el interruptor apagado nada cambia');
campaigns_on(true);

// Payload: el snapshot debe identificar la oferta
$threw = false;
try {
    FLACSO_Offer_Campaigns::enrollment_payload(['consultaId' => 'x', 'academic' => []], $consent);
} catch (InvalidArgumentException $e) {
    $threw = true;
}
campaign_assert($threw, 'sin academic.wpId no arma el alta');

// El servicio de ofertas ya no toma el consentimiento del navegador
$service = file_get_contents($root . '/modules/consultas/services/class-flacso-offer-inquiry-service.php');
campaign_assert(!str_contains($service, 'extract_consent($data)'), 'submit() no lee el consentimiento de $data');
campaign_assert(str_contains($service, 'public static function submit(array $data, array $consent = []): array'), 'submit() recibe la evidencia del servidor');
$form = file_get_contents($root . '/modules/main-page/includes/flacso-consultas.php');
campaign_assert(str_contains($form, 'FLACSO_Offer_Campaigns::form_consent(') && str_contains($form, 'FLACSO_Offer_Campaigns::CONSENT_TEXT'), 'el formulario muestra el texto y genera la evidencia');

fwrite(STDOUT, "OK offer campaigns\n");
