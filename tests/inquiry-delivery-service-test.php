<?php
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
$GLOBALS['delivery_options'] = [
    'flacso_mautic_enabled' => '1',
    'flacso_mautic_base_url' => 'https://mautic.example.org',
    'flacso_mautic_auth_type' => 'basic',
    'flacso_mautic_username' => 'test',
    'flacso_mautic_password' => 'secret',
];
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['delivery_options'][$key] ?? $default;
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

function delivery_service_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$pdo = flacso_test_delivery_pdo();
FLACSO_DB::set_connection($pdo);
$source = new FLACSO_Offer_Inquiry_Repository();
$repository = new FLACSO_Inquiry_Delivery_Repository($pdo);
FLACSO_Inquiry_Delivery_Service::set_repository($repository);
FLACSO_Inquiry_Delivery_Service::set_contract_validator(fn() => ['ok' => true, 'status' => 'valid']);

function make_delivery(
    FLACSO_Inquiry_Delivery_Repository $repository,
    FLACSO_Offer_Inquiry_Repository $source,
    string $consulta,
    string $program,
    string $code,
    string $date,
    string $modality
): array {
    $form = [
        'nombre' => 'Ana',
        'apellido' => 'Pérez',
        'correo' => 'misma@example.org',
        'programUrl' => 'https://flacso.edu.uy/' . strtolower($code),
        'inquiryAt' => '2026-09-30T12:00:00+00:00',
    ];
    $context = [
        'offerWpId' => random_int(10, 9999),
        'offerName' => $program,
        'offerAbbreviation' => $code,
        'offerType' => 'diploma',
        'cohortNumber' => 1,
        'offerStatus' => 'abierta',
        'startDate' => $date,
        'startDatePrecision' => 'day',
        'modality' => $modality,
    ];
    $snapshot = FLACSO_Inquiry_Snapshot::from_offer($form, $context, $consulta);
    $record = [
        'consultaId' => $consulta,
        'offerWpId' => $context['offerWpId'],
        'offerName' => $program,
        'offerAbbreviation' => strtolower($code),
        'firstName' => 'Ana',
        'lastName' => 'Pérez',
        'email' => 'misma@example.org',
        'inquiryAt' => '2026-09-30T12:00:00+00:00',
        'emailStatus' => 'pending',
        'emailSender' => 'mautic_transactional_queue',
    ];
    $result = $repository->persist_inquiry_with_delivery($source, $record, $snapshot, 'offer');

    // La prueba inyecta un contrato válido; asignamos la plantilla de prueba
    // porque el manifiesto productivo sigue bloqueado sin SHA aprobado.
    $pdo = FLACSO_DB::connection();
    $stmt = $pdo->prepare('UPDATE inquiry_deliveries SET templateId = 3, templateVersion = :version, templateSha256 = :sha WHERE id = :id');
    $stmt->execute([':version' => 'test', ':sha' => str_repeat('a', 64), ':id' => $result['delivery_id']]);
    return $result;
}

$send_bodies = [];
FLACSO_Mautic_Client::set_http_transport(function($url, $args) use (&$send_bodies) {
    if (str_contains($url, '/api/contacts?search=')) {
        return ['response' => ['code' => 200], 'body' => json_encode(['contacts' => [['id' => 42]]])];
    }
    if (str_contains($url, '/api/contacts/42/edit')) {
        return ['response' => ['code' => 200], 'body' => json_encode(['contact' => ['id' => 42]])];
    }
    if (str_contains($url, '/api/emails/3/contact/42/send')) {
        $send_bodies[] = json_decode((string) ($args['body'] ?? '{}'), true);
        return ['response' => ['code' => 200], 'body' => json_encode(['success' => true])];
    }
    return ['response' => ['code' => 404], 'body' => '{}'];
});

$first = make_delivery($repository, $source, 'delivery-1', 'DAVIA', 'DAVIA', '2027-04-08', 'hibrida');
$second = make_delivery($repository, $source, 'delivery-2', 'Maestría MG', 'MG', '2027-08-01', 'virtual');
$claimed = $repository->claim_pending_batch(10, 60);
delivery_service_assert(count($claimed) === 2, 'reclama dos entregas');

$r1 = FLACSO_Inquiry_Delivery_Service::process($first['delivery_id']);
$r2 = FLACSO_Inquiry_Delivery_Service::process($second['delivery_id']);
delivery_service_assert($r1['status'] === 'accepted' && $r2['status'] === 'accepted', 'dos entregas aceptadas');
delivery_service_assert(count($send_bodies) === 2, 'exactamente dos POST de email');
delivery_service_assert($send_bodies[0]['tokens']['{programa}'] === 'DAVIA', 'primer acuse mantiene su oferta');
delivery_service_assert($send_bodies[1]['tokens']['{programa}'] === 'Maestría MG', 'segundo acuse mantiene su oferta');
delivery_service_assert($send_bodies[0]['tokens']['{modalidad}'] === 'Híbrida', 'primer acuse mantiene modalidad');
delivery_service_assert($send_bodies[1]['tokens']['{modalidad}'] === 'Virtual', 'segundo acuse mantiene modalidad');

$before = count($send_bodies);
$again = FLACSO_Inquiry_Delivery_Service::process($first['delivery_id']);
delivery_service_assert($again['status'] === 'accepted', 'entrega terminal se reconoce');
delivery_service_assert(count($send_bodies) === $before, 'entrega accepted nunca se reenvía');

// Timeout después de iniciar el POST queda en acceptance_unknown.
$unknown = make_delivery($repository, $source, 'delivery-unknown', 'Programa incierto', 'PI', '2027-09-01', 'virtual');
$repository->claim_pending_batch(10, 60);
$unknown_posts = 0;
FLACSO_Mautic_Client::set_http_transport(function($url, $args) use (&$unknown_posts) {
    if (str_contains($url, '/api/contacts?search=')) {
        return ['response' => ['code' => 200], 'body' => json_encode(['contacts' => [['id' => 42]]])];
    }
    if (str_contains($url, '/api/contacts/42/edit')) {
        return ['response' => ['code' => 200], 'body' => json_encode(['contact' => ['id' => 42]])];
    }
    if (str_contains($url, '/send')) {
        $unknown_posts++;
        return new WP_Error('timeout after POST');
    }
    return ['response' => ['code' => 404], 'body' => '{}'];
});
$ru = FLACSO_Inquiry_Delivery_Service::process($unknown['delivery_id']);
delivery_service_assert($ru['status'] === 'acceptance_unknown', 'timeout de envío produce acceptance_unknown');
FLACSO_Inquiry_Delivery_Service::process($unknown['delivery_id']);
delivery_service_assert($unknown_posts === 1, 'acceptance_unknown no se reenvía');

// Contrato inválido bloquea antes de tocar Mautic.
$blocked = make_delivery($repository, $source, 'delivery-blocked', 'Programa bloqueado', 'PB', '2027-10-01', 'virtual');
$repository->claim_pending_batch(10, 60);
$network_calls = 0;
FLACSO_Mautic_Client::set_http_transport(function($url, $args) use (&$network_calls) {
    $network_calls++;
    return ['response' => ['code' => 500], 'body' => '{}'];
});
FLACSO_Inquiry_Delivery_Service::set_contract_validator(fn() => ['ok' => false, 'status' => 'blocked']);
$rb = FLACSO_Inquiry_Delivery_Service::process($blocked['delivery_id']);
delivery_service_assert($rb['status'] === 'blocked', 'contrato inválido bloquea entrega');
delivery_service_assert($network_calls === 0, 'contrato inválido no toca Mautic');

FLACSO_Mautic_Client::set_http_transport(null);
FLACSO_Inquiry_Delivery_Service::set_contract_validator(null);
FLACSO_Inquiry_Delivery_Service::set_repository(null);

echo "OK inquiry-delivery-service-test\n";
