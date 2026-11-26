<?php
/** Contrato de GA4 por Measurement Protocol: cookies, nombres de evento y cuerpo del pedido. */
define('ABSPATH', dirname(__DIR__) . '/');
function wp_json_encode($v) { return json_encode($v); }
require dirname(__DIR__) . '/includes/core/class-flacso-ga4-measurement-protocol.php';

function ga4mp_assert(bool $ok, string $msg): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); }
}

$C = 'FLACSO_GA4_Measurement_Protocol';
ga4mp_assert($C::ga4_name_for('Lead') === 'generate_lead', 'Lead -> generate_lead');
ga4mp_assert($C::ga4_name_for('SubmitApplication') === 'submit_application', 'SubmitApplication -> submit_application');
ga4mp_assert($C::ga4_name_for('PageView') === '', 'PageView no se envía por servidor');
ga4mp_assert($C::client_id_from_cookie('GA1.1.123456789.1700000000') === '123456789.1700000000', 'client_id desde _ga');
ga4mp_assert($C::client_id_from_cookie('basura') === '', '_ga inválida');
ga4mp_assert($C::session_id_from_cookie('GS1.1.1700000001.3.1.1700000002.0.0.0') === '1700000001', 'session_id GS1');
ga4mp_assert($C::session_id_from_cookie('GS2.1.s1700000001$o3$g1') === '1700000001', 'session_id GS2');

$p = $C::build_payload('Lead', 'evt-1', ['content_name' => 'MG', 'email' => 'a@b.c'], 'GA1.1.11.22', 'GS1.1.99.1.1.2.0.0.0', 'ext');
ga4mp_assert($p['client_id'] === '11.22', 'usa el client_id del navegador');
ga4mp_assert($p['events'][0]['name'] === 'generate_lead', 'nombre del evento');
ga4mp_assert($p['events'][0]['params']['session_id'] === '99', 'une el evento a la sesión');
ga4mp_assert(!array_key_exists('email', $p['events'][0]['params']), 'no envía datos personales');
$fallback = $C::build_payload('SubmitApplication', 'evt-2', [], '', '', 'ext-abc');
ga4mp_assert(preg_match('/^\d+\.\d+$/', $fallback['client_id']) === 1, 'client_id derivado sin _ga');
ga4mp_assert($C::build_payload('Lead', 'e', [], '', '', '') === [], 'sin _ga ni id externo no se envía');
ga4mp_assert($C::build_payload('PageView', 'e', [], 'GA1.1.1.2', '', 'x') === [], 'evento no mapeado');
ga4mp_assert($C::is_enabled() === false, 'desactivado sin constantes');
echo "OK\n";
