<?php
/**
 * Estado real de las consultas en campaña: pedido en lote a Mautic, caché, vuelta
 * a lo que la bandeja ya sabe si Mautic no responde y celda «Estado del correo».
 */
$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
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

$GLOBALS['status_options'] = [
    'flacso_mautic_enabled' => '1',
    'flacso_mautic_base_url' => 'https://mautic.example.org',
    'flacso_mautic_auth_type' => 'basic',
    'flacso_mautic_username' => 'test',
    'flacso_mautic_password' => 'secret',
];
$GLOBALS['status_transients'] = [];
function get_option($key, $default = false) { return $GLOBALS['status_options'][$key] ?? $default; }
function get_transient($key) { return $GLOBALS['status_transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['status_transients'][$key] = $value; $GLOBALS['status_ttl'][$key] = $ttl; return true; }
// Hora de WordPress: Montevideo, UTC-3.
function wp_date($format, $timestamp) { return gmdate($format, $timestamp - 3 * 3600); }
function __($text) { return $text; }
function esc_html__($text) { return htmlspecialchars($text); }
function esc_attr__($text) { return htmlspecialchars($text); }
function esc_html($text) { return htmlspecialchars((string) $text); }
function esc_attr($text) { return htmlspecialchars((string) $text); }
function add_action(...$args) {}
function add_filter(...$args) {}
function current_user_can(...$args) { return true; }

require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-inquiry-analytics-repository.php';
require_once $root . '/includes/integrations/class-flacso-mautic-client.php';
require_once $root . '/modules/consultas/services/class-flacso-offer-campaign-status.php';
require_once $root . '/modules/consultas/includes/class-flacso-consultas-admin.php';

function status_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function joined_row(string $id, array $overrides = []): array {
    return array_replace([
        'consultaId' => $id, 'email' => $id . '@example.org', 'mauticCampaignStatus' => 'joined', 'mauticCampaignId' => 24,
        'item_wp_id' => 24160, 'createdAt' => '2026-10-29 18:09:05', 'inquiryAt' => '2026-10-29 15:09:05',
    ], $overrides);
}

/** @param list<array{0:int,1:array}|string> $responses una respuesta por llamada; 'wp_error' simula un corte */
function status_transport(array $responses, array &$calls): void {
    FLACSO_Mautic_Client::set_http_transport(function ($url, $args) use (&$responses, &$calls) {
        $calls[] = ['url' => $url, 'body' => json_decode((string) $args['body'], true), 'timeout' => $args['timeout'] ?? null];
        $next = array_shift($responses);
        if ($next === 'wp_error' || $next === null) {
            return new WP_Error('timeout');
        }
        return ['response' => ['code' => $next[0]], 'body' => json_encode($next[1])];
    });
}

$live = [
    'found' => true, 'inCampaign' => true, 'enrolled' => false, 'removed' => false,
    'acuse' => ['state' => 'sent', 'kind' => 'abierta', 'at' => '2026-10-29T18:10:04Z'],
    'followUp' => ['state' => 'scheduled', 'at' => '2026-11-05T18:10:04Z'],
];

// Qué consultas se piden: sólo las que están en campaña, con la ventana en UTC
$item = FLACSO_Offer_Campaign_Status::item_from_row(joined_row('c-1', ['nextInquiryAt' => '2026-10-30 12:00:00']));
status_assert($item['since'] === '2026-10-29T18:09:05+00:00' && $item['until'] === '2026-10-30T12:00:00+00:00', 'la ventana usa createdAt (UTC), no la hora local de inquiryAt');
status_assert($item['campaignId'] === 24 && $item['offerWpId'] === 24160 && $item['email'] === 'c-1@example.org', 'pide campaña, oferta y correo');
status_assert(!array_key_exists('until', FLACSO_Offer_Campaign_Status::item_from_row(joined_row('c-2'))), 'la consulta más reciente no tiene tope');
foreach ([
    'sin campaña' => ['mauticCampaignStatus' => 'skipped'],
    'pendiente' => ['mauticCampaignStatus' => 'pending'],
    'sin id de campaña' => ['mauticCampaignId' => null],
    'sin oferta' => ['item_wp_id' => 0],
    'sin fecha' => ['createdAt' => ''],
    'sin correo' => ['email' => ''],
] as $case => $override) {
    status_assert(FLACSO_Offer_Campaign_Status::item_from_row(joined_row('c-x', $override)) === null, "no pide: {$case}");
}

// Un pedido por página, a la ruta correcta, con timeout corto
$calls = [];
status_transport([[200, ['ok' => true, 'statuses' => ['a' => $live, 'b' => ['found' => false]]]]], $calls);
$result = FLACSO_Offer_Campaign_Status::for_rows([joined_row('a'), joined_row('b'), joined_row('c', ['mauticCampaignStatus' => 'skipped'])]);
status_assert(count($calls) === 1 && str_ends_with($calls[0]['url'], '/api/flacso/inquiries/status'), 'un solo pedido para toda la página');
status_assert(array_column($calls[0]['body']['items'], 'consultaId') === ['a', 'b'], 'sólo pide las consultas en campaña');
status_assert($calls[0]['timeout'] === 6, 'timeout corto');
status_assert(array_keys($result) === ['a'], 'sólo devuelve las que Mautic resolvió');

// Caché: la segunda carga no vuelve a preguntar; el estado definitivo dura más
$calls = [];
status_transport([], $calls);
$again = FLACSO_Offer_Campaign_Status::for_rows([joined_row('a')]);
status_assert($calls === [] && $again['a'] === $live, 'la segunda carga sale de la caché');
$key = 'flacso_campaign_status_' . md5('a');
status_assert($GLOBALS['status_ttl'][$key] === FLACSO_Offer_Campaign_Status::TTL_LIVE, 'con seguimiento programado se refresca pronto');
$done = $live;
$done['followUp'] = ['state' => 'sent', 'at' => '2026-11-05T18:10:30Z'];
$calls = [];
status_transport([[200, ['ok' => true, 'statuses' => ['d' => $done]]]], $calls);
FLACSO_Offer_Campaign_Status::for_rows([joined_row('d')]);
status_assert($GLOBALS['status_ttl']['flacso_campaign_status_' . md5('d')] === FLACSO_Offer_Campaign_Status::TTL_FINAL, 'un recorrido terminado se guarda un día');

// Mautic no responde: la bandeja sigue sin estado real y no insiste
foreach (['wp_error', [500, ['ok' => false, 'error' => 'internal_error']], [401, []], [200, ['ok' => true]]] as $failure) {
    $calls = [];
    status_transport([$failure], $calls);
    $result = FLACSO_Offer_Campaign_Status::for_rows([joined_row('f-' . md5(json_encode($failure)))]);
    status_assert($result === [], 'si Mautic falla no hay estado real y no se rompe');
}
$calls = [];
status_transport(['wp_error', 'wp_error', 'wp_error'], $calls);
$rows = [];
for ($i = 0; $i < 120; $i++) {
    $rows[] = joined_row('lote-' . $i);
}
FLACSO_Offer_Campaign_Status::for_rows($rows);
status_assert(count($calls) === 1, 'tras el primer fallo no sigue pidiendo');
$calls = [];
status_transport([[200, ['ok' => true, 'statuses' => []]], [200, ['ok' => true, 'statuses' => []]], [200, ['ok' => true, 'statuses' => []]]], $calls);
FLACSO_Offer_Campaign_Status::for_rows($rows);
status_assert(count($calls) === 3 && count($calls[0]['body']['items']) === 50 && count($calls[2]['body']['items']) === 20, '120 consultas se piden en lotes de 50');

// Mautic apagado: ni siquiera intenta
$GLOBALS['status_options']['flacso_mautic_enabled'] = '0';
$calls = [];
status_transport([], $calls);
status_assert(FLACSO_Offer_Campaign_Status::for_rows([joined_row('apagado')]) === [] && $calls === [], 'con Mautic apagado no hace pedidos');
$GLOBALS['status_options']['flacso_mautic_enabled'] = '1';

// Ventana desde la base: la consulta vieja termina donde empieza la nueva
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE offer_inquiries (
    id TEXT PRIMARY KEY, consultaId TEXT, offerName TEXT, offerWpId INTEGER, email TEXT, emailNormalized TEXT,
    fullName TEXT, firstName TEXT, lastName TEXT, country TEXT, source TEXT, campaignName TEXT, campaignSource TEXT,
    campaignMedium TEXT, emailStatus TEXT, emailSender TEXT, mailjetMessageId TEXT, mailjetMessageUuid TEXT,
    inquiryAt TEXT, createdAt TEXT, mauticCampaignStatus TEXT, mauticCampaignId INTEGER
)');
$insert = $pdo->prepare('INSERT INTO offer_inquiries (id, consultaId, offerName, offerWpId, email, emailNormalized, fullName, emailStatus, inquiryAt, createdAt, mauticCampaignStatus, mauticCampaignId)
    VALUES (:id, :id, "Maestría", :offer, :email, :email, "X", "accepted", :local, :utc, "joined", 24)');
foreach ([
    ['vieja', 24160, 'ana@example.org', '2026-10-26 14:54:00', '2026-10-26 17:54:00'],
    ['nueva', 24160, 'ana@example.org', '2026-10-29 15:09:05', '2026-10-29 18:09:05'],
    ['otra-oferta', 99, 'ana@example.org', '2026-10-27 10:00:00', '2026-10-27 13:00:00'],
] as [$id, $offer, $email, $local, $utc]) {
    $insert->execute([':id' => $id, ':offer' => $offer, ':email' => $email, ':local' => $local, ':utc' => $utc]);
}
FLACSO_DB::set_connection($pdo);
$page = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries(['table' => 'offer_inquiries', 'mode' => 'raw', 'page' => 1, 'page_size' => 25]);
$by_id = array_column($page['items'], null, 'consultaId');
status_assert($by_id['vieja']['nextInquiryAt'] === '2026-10-29 18:09:05', 'la consulta vieja termina donde empieza la siguiente de la misma oferta');
status_assert($by_id['nueva']['nextInquiryAt'] === null, 'la más reciente no tiene tope');
status_assert($by_id['otra-oferta']['nextInquiryAt'] === null, 'otra oferta no cuenta como siguiente');
status_assert($by_id['nueva']['createdAt'] === '2026-10-29 18:09:05', 'trae createdAt en UTC');
$grouped = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries(['table' => 'offer_inquiries', 'mode' => 'grouped', 'page' => 1, 'page_size' => 25]);
$head = array_values(array_filter($grouped['items'], static fn(array $r): bool => $r['consultaId'] === 'nueva'));
status_assert(count($head) === 1 && array_key_exists('nextInquiryAt', $head[0]), 'la vista agrupada también trae la ventana');

// Celda «Estado del correo»
$cell = FLACSO_Consultas_Admin::render_delivery_cell(joined_row('x'), true, $live);
status_assert(str_contains($cell, 'acuse enviado 29/10 15:10') && str_contains($cell, 'Seguimiento: 05/11'), 'acuse enviado y seguimiento programado, en hora de WordPress');
status_assert(!str_contains($cell, 'Acuse y seguimiento en la campaña'), 'con datos reales no muestra el texto genérico');

$render = static fn(array $changes): string => FLACSO_Consultas_Admin::render_delivery_cell(joined_row('x'), true, array_replace_recursive($live, $changes));
status_assert(str_contains($render(['enrolled' => true, 'removed' => true, 'followUp' => ['state' => 'cancelled']]), 'Seguimiento cancelado: se inscribió'), 'se inscribió: seguimiento cancelado');
status_assert(str_contains($render(['removed' => true, 'followUp' => ['state' => 'cancelled']]), 'retirada de la campaña'), 'retirada sin inscripción');
status_assert(str_contains($render(['followUp' => ['state' => 'sent', 'at' => '2026-11-05T18:10:30Z']]), 'Seguimiento enviado 05/11 15:10'), 'seguimiento enviado');
status_assert(str_contains($render(['acuse' => ['kind' => 'cerrada'], 'followUp' => ['state' => 'not_applicable']]), 'Sin seguimiento: oferta cerrada'), 'oferta cerrada');
status_assert(str_contains($render(['acuse' => ['state' => 'failed']]), 'acuse falló'), 'acuse fallido');
status_assert(str_contains($render(['acuse' => ['state' => 'queued']]), 'acuse en cola'), 'acuse en cola');
status_assert(str_contains($render(['acuse' => ['state' => 'pending', 'at' => null], 'followUp' => ['state' => 'pending', 'at' => null]]), 'Seguimiento pendiente'), 'recién incorporada');

// Sin datos de Mautic: lo de siempre
$fallback = FLACSO_Consultas_Admin::render_delivery_cell(joined_row('x'), true, null);
status_assert(str_contains($fallback, 'Acuse y seguimiento en la campaña'), 'sin respuesta de Mautic muestra el texto genérico');
$not_found = FLACSO_Consultas_Admin::render_delivery_cell(joined_row('x'), true, ['found' => false]);
status_assert(str_contains($not_found, 'Acuse y seguimiento en la campaña'), 'contacto no encontrado en Mautic: texto genérico');
status_assert(!str_contains(FLACSO_Consultas_Admin::render_delivery_cell(joined_row('x', ['mauticCampaignStatus' => 'skipped']), true, $live), 'acuse enviado'), 'fuera de campaña no se usa el estado de Mautic');

echo "OK offer-campaign-status-test\n";
