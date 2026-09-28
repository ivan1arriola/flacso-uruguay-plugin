<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

$GLOBALS['flacso_test_options'] = [
    'flacso_mailjet_api_key' => 'key-123',
    'flacso_mailjet_secret_key' => 'sec-456',
    'flacso_mailjet_sender_email' => 'inscripciones@flacso.edu.uy',
    'flacso_mailjet_sender_name' => 'FLACSO Uruguay',
    'flacso_mailjet_list_id' => '100',
    'flacso_mailjet_offer_lists' => ['501' => '200'],
    'flacso_mailjet_seminar_lists' => ['601' => '300'],
    'flacso_consultas_excluded_campaigns' => [],
];

$GLOBALS['mailjet_http_calls'] = [];

if (!function_exists('get_option')) {
    function get_option(string $name, $default = false) {
        return $GLOBALS['flacso_test_options'][$name] ?? $default;
    }
}
if (!function_exists('update_option')) {
    function update_option(string $name, $value, $autoload = null): bool {
        $GLOBALS['flacso_test_options'][$name] = $value;
        return true;
    }
}
if (!function_exists('add_action')) {
    function add_action(string $tag, $callback, int $priority = 10, int $accepted_args = 1): void {
        $GLOBALS['wp_actions'][$tag][] = $callback;
    }
}
if (!function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = []) {
        if (!empty($GLOBALS['custom_http_handler']) && is_callable($GLOBALS['custom_http_handler'])) {
            return call_user_func($GLOBALS['custom_http_handler'], $url, $args);
        }
        $GLOBALS['mailjet_http_calls'][] = ['method' => 'POST', 'url' => $url, 'args' => $args];
        return [
            'response' => ['code' => 200],
            'body' => json_encode([
                'Messages' => [[
                    'Status' => 'success',
                    'To' => [['MessageID' => 998877, 'MessageUUID' => 'uuid-test-998877']],
                ]],
            ]),
        ];
    }
}
if (!function_exists('wp_remote_request')) {
    function wp_remote_request(string $url, array $args = []) {
        if (!empty($GLOBALS['custom_http_handler']) && is_callable($GLOBALS['custom_http_handler'])) {
            return call_user_func($GLOBALS['custom_http_handler'], $url, $args);
        }
        $GLOBALS['mailjet_http_calls'][] = ['method' => $args['method'] ?? 'POST', 'url' => $url, 'args' => $args];
        return ['response' => ['code' => 201], 'body' => '{"Count":1}'];
    }
}
if (!function_exists('wp_remote_get')) {
    function wp_remote_get(string $url, array $args = []) {
        if (!empty($GLOBALS['custom_http_handler']) && is_callable($GLOBALS['custom_http_handler'])) {
            return call_user_func($GLOBALS['custom_http_handler'], $url, $args);
        }
        return ['response' => ['code' => 200], 'body' => json_encode(['Data' => [], 'Total' => 0])];
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool {
        return false;
    }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response): int {
        return (int) ($response['response']['code'] ?? 200);
    }
}
if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response): string {
        return (string) ($response['body'] ?? '');
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data): string {
        return (string) json_encode($data);
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can(string $cap): bool {
        return true;
    }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($v) {
        return $v;
    }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($k) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$k));
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($t) {
        return trim(strip_tags((string)$t));
    }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'mock-nonce-' . $action;
    }
}
if (!function_exists('admin_url')) {
    function admin_url($path = '', $scheme = 'admin') {
        return 'https://example.org/wp-admin/' . $path;
    }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg(...$args) {
        return 'https://example.org/wp-admin/admin.php';
    }
}
if (!function_exists('esc_url')) {
    function esc_url($u) {
        return (string)$u;
    }
}
if (!function_exists('esc_attr')) {
    function esc_attr($a) {
        return htmlspecialchars((string)$a, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_html')) {
    function esc_html($h) {
        return htmlspecialchars((string)$h, ENT_NOQUOTES, 'UTF-8');
    }
}
if (!function_exists('selected')) {
    function selected($selected, $current = true, $echo = true) {
        $result = (string)$selected === (string)$current ? ' selected="selected"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return (string)$text;
    }
}
if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default') {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return htmlspecialchars((string)$text, ENT_NOQUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') {
        echo htmlspecialchars((string)$text, ENT_NOQUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default') {
        echo htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('is_admin')) {
    function is_admin(): bool {
        return true;
    }
}
if (!function_exists('register_setting')) {
    function register_setting(string $option_group, string $option_name, array $args = []): void {
        $GLOBALS['flacso_registered_settings'][$option_group][$option_name] = $args;
    }
}
if (!function_exists('settings_errors')) {
    function settings_errors(): void {}
}
if (!function_exists('settings_fields')) {
    function settings_fields($group): void {
        echo '<input type="hidden" name="option_page" value="' . esc_attr($group) . '" />';
    }
}
if (!function_exists('submit_button')) {
    function submit_button($text = 'Save', $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null): void {
        echo '<button type="submit" name="' . esc_attr($name) . '">' . esc_html($text) . '</button>';
    }
}
if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0) {
        return (string)$number;
    }
}
if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        return (object)['user_email' => 'admin@flacso.edu.uy'];
    }
}
if (!function_exists('get_transient')) {
    function get_transient($t) { return false; }
}
if (!function_exists('set_transient')) {
    function set_transient($t, $val, $exp = 0) { return true; }
}
if (!function_exists('delete_transient')) {
    function delete_transient($t) { return true; }
}
if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true) {
        $result = (string)$checked === (string)$current ? ' checked="checked"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}
if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return trim((string)$email);
    }
}
if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs((int)$maybeint);
    }
}
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') {
        return 'FLACSO Uruguay';
    }
}
if (!function_exists('wp_specialchars_decode')) {
    function wp_specialchars_decode($str, $quote_style = ENT_NOQUOTES) {
        return htmlspecialchars_decode($str, $quote_style);
    }
}
if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return trim((string)$url);
    }
}
if (!function_exists('get_posts')) {
    function get_posts(array $args = []): array {
        return [];
    }
}
if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = true) {
        return 1;
    }
}

class TestAjaxException extends Exception {
    public $data;
    public $status_code;
    public $is_success;
    public function __construct(bool $is_success, $data, $status_code = null) {
        $this->is_success = $is_success;
        $this->data = $data;
        $this->status_code = $status_code;
        parent::__construct($is_success ? 'AJAX Success' : 'AJAX Error');
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        throw new TestAjaxException(true, $data, $status_code ?? 200);
    }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        throw new TestAjaxException(false, $data, $status_code ?? 500);
    }
}

require_once dirname(__DIR__) . '/modules/consultas/init.php';
require_once dirname(__DIR__) . '/modules/mailing/includes/class-flacso-mail-settings.php';

function assert_true(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

assert_true(
    method_exists('FLACSO_Consultas_Admin', 'is_retryable_email_status'),
    'la consola debe definir qué estados permiten reenvío manual'
);
assert_true(
    FLACSO_Consultas_Admin::is_retryable_email_status('failed') === true,
    'sólo una entrega fallida debe poder reenviarse'
);
assert_true(
    FLACSO_Consultas_Admin::is_retryable_email_status('sent') === false,
    'una entrega ya enviada no debe poder reenviarse para evitar duplicados'
);
assert_true(
    FLACSO_Consultas_Admin::is_retryable_email_status('skipped') === false,
    'una entrega omitida no debe poder reenviarse desde la consola'
);
assert_true(
    FLACSO_Consultas_Admin::is_retryable_email_status('processing') === false,
    'un reenvío pendiente de conciliación no debe poder reenviarse para evitar duplicados'
);

// 1. Setup SQLite in-memory schema matching Prisma PostgreSQL columns
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec('CREATE TABLE "offer_inquiries" (
    "id" TEXT PRIMARY KEY,
    "consultaId" TEXT UNIQUE,
    "offerWpId" INTEGER,
    "offerName" TEXT,
    "offerAbbreviation" TEXT,
    "offerType" TEXT,
    "cohortWpId" INTEGER,
    "cohortNumber" INTEGER,
    "cohortName" TEXT,
    "offerStatus" TEXT,
    "firstName" TEXT,
    "lastName" TEXT,
    "fullName" TEXT,
    "email" TEXT,
    "emailNormalized" TEXT,
    "phone" TEXT,
    "country" TEXT,
    "source" TEXT,
    "pageUrl" TEXT,
    "campaignProvider" TEXT,
    "campaignSource" TEXT,
    "campaignMedium" TEXT,
    "campaignName" TEXT,
    "campaignExternalId" TEXT,
    "campaignContent" TEXT,
    "campaignTerm" TEXT,
    "emailStatus" TEXT,
    "emailSender" TEXT,
    "mailjetMessageId" TEXT,
    "mailjetMessageUuid" TEXT,
    "mauticContactId" TEXT,
    "mauticSyncStatus" TEXT,
    "mauticSyncedAt" TEXT,
    "mauticLastError" TEXT,
    "payload" TEXT,
    "inquiryAt" TEXT,
    "createdAt" TEXT,
    "updatedAt" TEXT
)');

$pdo->exec('CREATE TABLE "seminar_inquiries" (
    "id" TEXT PRIMARY KEY,
    "consultaId" TEXT UNIQUE,
    "seminarWpId" INTEGER,
    "seminarName" TEXT,
    "seminarType" TEXT,
    "offerStatus" TEXT,
    "firstName" TEXT,
    "lastName" TEXT,
    "fullName" TEXT,
    "email" TEXT,
    "emailNormalized" TEXT,
    "phone" TEXT,
    "country" TEXT,
    "source" TEXT,
    "pageUrl" TEXT,
    "campaignProvider" TEXT,
    "campaignSource" TEXT,
    "campaignMedium" TEXT,
    "campaignName" TEXT,
    "campaignExternalId" TEXT,
    "campaignContent" TEXT,
    "campaignTerm" TEXT,
    "emailStatus" TEXT,
    "emailSender" TEXT,
    "mailjetMessageId" TEXT,
    "mailjetMessageUuid" TEXT,
    "payload" TEXT,
    "inquiryAt" TEXT,
    "createdAt" TEXT,
    "updatedAt" TEXT
)');

FLACSO_DB::set_connection($pdo);

$today = gmdate('Y-m-d');
$now   = gmdate('Y-m-d H:i:s');

$stmt = $pdo->prepare('INSERT INTO "offer_inquiries"
    ("id","consultaId","offerWpId","offerName","offerAbbreviation","cohortNumber","cohortName","offerStatus","firstName","lastName","fullName","email","emailNormalized","phone","country","source","campaignProvider","campaignSource","campaignMedium","campaignName","emailStatus","payload","inquiryAt","createdAt","updatedAt")
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

$time_c01 = gmdate('Y-m-d H:i:s', time() - 5);
$time_c02 = gmdate('Y-m-d H:i:s', time() - 10);
$time_c03 = gmdate('Y-m-d H:i:s', time() - 15);

// Ana in Uruguay (2 inquiries for same Maestría -> deduplicates to 1 pair)
$stmt->execute(['c01', 'cid-1', 501, 'Maestría en Género', 'mg', 2, '2ª Cohorte', 'abierta', 'Ana', 'Pérez', 'Ana Pérez', 'ana@ejemplo.com', 'ana@ejemplo.com', '099111', 'Uruguay', 'web', 'meta', 'facebook', 'cpc', 'Campaña Género', 'sent', '{"test":1}', $time_c01, $time_c01, $time_c01]);
$stmt->execute(['c02', 'cid-2', 501, 'Maestría en Género', 'mg', 2, '2ª Cohorte', 'abierta', 'Ana', 'Pérez', 'Ana Pérez', 'ana@ejemplo.com', 'ana@ejemplo.com', '099111', 'Uruguay', 'web', 'meta', 'facebook', 'cpc', 'Campaña Género', 'sent', '{"test":2}', $time_c02, $time_c02, $time_c02]);
// Ana also in Argentina for another offer -> creates UY/EXT intersection for ana@ejemplo.com
$stmt->execute(['c03', 'cid-3', 502, 'Diploma en Educación', null, null, null, 'cerrada', 'Ana', 'Pérez', 'Ana Pérez', 'ana@ejemplo.com', 'ana@ejemplo.com', '099111', 'Argentina', 'web', 'google', 'google', 'cpc', 'Campaña Educación', 'failed', '{"test":3}', $time_c03, $time_c03, $time_c03]);

$pdo->exec("UPDATE \"offer_inquiries\" SET \"mauticSyncStatus\" = 'synced', \"mauticContactId\" = '7788', \"mauticSyncedAt\" = '{$time_c01}' WHERE \"id\" = 'c01'");
$pdo->exec("UPDATE \"offer_inquiries\" SET \"mauticSyncStatus\" = 'pending' WHERE \"id\" = 'c02'");
$pdo->exec("UPDATE \"offer_inquiries\" SET \"mauticSyncStatus\" = 'failed', \"mauticLastError\" = 'Connection timeout' WHERE \"id\" = 'c03'");

// Seminar inquiry
$stmt_sem = $pdo->prepare('INSERT INTO "seminar_inquiries"
    ("id","consultaId","seminarWpId","seminarName","firstName","lastName","fullName","email","emailNormalized","phone","country","source","campaignProvider","campaignSource","campaignMedium","campaignName","emailStatus","payload","inquiryAt","createdAt","updatedAt")
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$stmt_sem->execute(['s01', 'cid-s1', 601, 'Seminario IA y Sociedad', 'Luis', 'Gómez', 'Luis Gómez', 'luis@ejemplo.com', 'luis@ejemplo.com', '098222', 'Chile', 'web', 'mailjet', 'newsletter', 'email', 'Newsletter Abril', 'sent', '{"sem":1}', $now, $now, $now]);

// Test 1: Delivery metrics
$metrics = FLACSO_Inquiry_Analytics_Repository::get_email_delivery_metrics();
assert_true($metrics['db_connected'] === true, 'Metrics db_connected should be true');
assert_true($metrics['last_24h']['sent'] === 3, 'Expected 3 sent emails in 24h');
assert_true($metrics['last_24h']['failed'] === 1, 'Expected 1 failed email in 24h');

// Test 2: Analytics summary (deduplication, UY/EXT intersection, campaigns)
$summary = FLACSO_Inquiry_Analytics_Repository::get_analytics_summary($today, $today, '', 'offer_inquiries');
assert_true($summary['resumen']['total']['totalConsultas'] === 2, 'Expected 2 deduplicated [email + offer] pairs in offer_inquiries');
assert_true($summary['resumen']['uruguay']['totalConsultas'] === 1, 'Expected 1 Uruguay pair');
assert_true($summary['resumen']['exterior']['totalConsultas'] === 1, 'Expected 1 Exterior pair (Argentina)');
assert_true($summary['resumen']['correosInterseccionUyExt'] === 1, 'Expected 1 email in UY/EXT intersection (ana@ejemplo.com)');
assert_true(count($summary['campanas']['filas']) === 2, 'Expected 2 distinct campaigns in offer_inquiries');

// Test 3: Campaign exclusion
$camp_key_to_hide = $summary['campanas']['filas'][1]['clave'];
FLACSO_Inquiry_Analytics_Repository::set_campaign_exclusion($camp_key_to_hide, 'Campaña Educación', true);
$summary_after_ex = FLACSO_Inquiry_Analytics_Repository::get_analytics_summary($today, $today, '', 'offer_inquiries');
assert_true(count($summary_after_ex['campanas']['filas']) === 1, 'Expected 1 campaign after excluding Campaña Educación');
assert_true(count($summary_after_ex['campanas']['ocultas']) === 1, 'Expected 1 hidden campaign in ocultas');

// Test 4: Paginated inquiries (grouped vs raw)
$grouped_page = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries(['table' => 'offer_inquiries', 'mode' => 'grouped', 'desde' => $today, 'hasta' => $today]);
assert_true($grouped_page['pageInfo']['totalItems'] === 2, 'Expected 2 grouped rows in offer_inquiries');
$raw_page = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries(['table' => 'offer_inquiries', 'mode' => 'raw', 'desde' => $today, 'hasta' => $today]);
assert_true($raw_page['pageInfo']['totalItems'] === 3, 'Expected 3 raw rows in offer_inquiries');

// Test 5: Export rows
$export_dedup = FLACSO_Inquiry_Analytics_Repository::get_export_rows('offer_inquiries', $today, $today, ['Maestría en Género'], true);
assert_true(count($export_dedup) === 1, 'Expected 1 deduplicated export row for Maestría en Género');
$export_raw = FLACSO_Inquiry_Analytics_Repository::get_export_rows('offer_inquiries', $today, $today, ['Maestría en Género'], false);
assert_true(count($export_raw) === 2, 'Expected 2 raw export rows for Maestría en Género');

// Test 6: Mail Settings target lists per offer and seminar
$offer_lists = FLACSO_Mail_Settings::get_target_lists_for_offer(501);
assert_true(in_array('100', $offer_lists, true) && in_array('200', $offer_lists, true), 'Expected global list 100 and offer list 200');
$sem_lists = FLACSO_Mail_Settings::get_target_lists_for_seminar(601);
assert_true(in_array('100', $sem_lists, true) && in_array('300', $sem_lists, true), 'Expected global list 100 and seminar list 300');

// Test 7: Mailjet Client preview HTML & contact sync
$preview = FLACSO_Mailjet_Client::get_preview_html('consulta_abierta');
assert_true(!empty($preview['subject']) && strpos($preview['html'], 'FLACSO Uruguay') !== false, 'Expected institutional HTML preview');
$sync_res = FLACSO_Mailjet_Client::sync_contact_to_lists('ana@ejemplo.com', 'Ana Pérez', ['pais' => 'Uruguay'], [100, 200]);
assert_true($sync_res['ok'] === true && count($sync_res['synced']) === 2, 'Expected contact synced to 2 lists');

// Test 8: Consultas Admin & Analytics filter by offer_status and context columns
$page_abierta = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries([
    'table'        => 'offer_inquiries',
    'mode'         => 'grouped',
    'offer_status' => 'abierta',
    'desde'        => $today,
    'hasta'        => $today,
]);
assert_true($page_abierta['pageInfo']['totalItems'] === 1, 'Expected exactly 1 group for offer_status=abierta');
assert_true($page_abierta['items'][0]['item_name'] === 'Maestría en Género', 'Expected Maestría en Género in abierta filter');
assert_true($page_abierta['items'][0]['offerAbbreviation'] === 'mg', 'Expected offerAbbreviation mg in paginated item');
assert_true((int)$page_abierta['items'][0]['cohortNumber'] === 2, 'Expected cohortNumber 2 in paginated item');
assert_true($page_abierta['items'][0]['offerStatus'] === 'abierta', 'Expected offerStatus abierta in paginated item');

$page_cerrada = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries([
    'table'        => 'offer_inquiries',
    'mode'         => 'grouped',
    'offer_status' => 'cerrada',
    'desde'        => $today,
    'hasta'        => $today,
]);
assert_true($page_cerrada['pageInfo']['totalItems'] === 1, 'Expected exactly 1 group for offer_status=cerrada');
assert_true($page_cerrada['items'][0]['item_name'] === 'Diploma en Educación', 'Expected Diploma en Educación in cerrada filter');

// Test 9: Rendering Consultas Admin page with offer columns & badges
$_GET = [
    'page'  => 'flacso-consultas',
    'tab'   => 'historico',
    'table' => 'offer_inquiries',
];
ob_start();
FLACSO_Consultas_Admin::render_page();
$admin_html = ob_get_clean();

assert_true(strpos($admin_html, '<th>Oferta</th>') !== false, 'Admin header must contain Oferta column');
assert_true(strpos($admin_html, '<th>Cohorte</th>') !== false, 'Admin header must contain Cohorte column');
assert_true(strpos($admin_html, '<th>Al consultar</th>') !== false, 'Admin header must contain Al consultar column');
assert_true(strpos($admin_html, '<select name="offer_status">') !== false, 'Admin filters must contain offer_status select');
assert_true(strpos($admin_html, '<span class="flacso-badge abbr" title="Abreviación canónica">mg</span>') !== false, 'Admin must render abbreviation badge');
assert_true(strpos($admin_html, 'Cohorte 2') !== false, 'Admin must render Cohorte 2');
assert_true(strpos($admin_html, 'status-abierta') !== false, 'Admin must render status-abierta badge');
assert_true(strpos($admin_html, 'status-cerrada') !== false, 'Admin must render status-cerrada badge');

// Test 10: Mautic settings registration in FLACSO_Mail_Settings
assert_true(defined('FLACSO_Mail_Settings::OPTION_MAUTIC_ENABLED'), 'FLACSO_Mail_Settings must define OPTION_MAUTIC_ENABLED');
assert_true(defined('FLACSO_Mail_Settings::OPTION_MAUTIC_BASE_URL'), 'FLACSO_Mail_Settings must define OPTION_MAUTIC_BASE_URL');
assert_true(defined('FLACSO_Mail_Settings::OPTION_MAUTIC_AUTH_TYPE'), 'FLACSO_Mail_Settings must define OPTION_MAUTIC_AUTH_TYPE');
assert_true(defined('FLACSO_Mail_Settings::OPTION_MAUTIC_USERNAME'), 'FLACSO_Mail_Settings must define OPTION_MAUTIC_USERNAME');
assert_true(defined('FLACSO_Mail_Settings::OPTION_MAUTIC_PASSWORD'), 'FLACSO_Mail_Settings must define OPTION_MAUTIC_PASSWORD');
assert_true(defined('FLACSO_Mail_Settings::OPTION_MAUTIC_TOKEN'), 'FLACSO_Mail_Settings must define OPTION_MAUTIC_TOKEN');

FLACSO_Mail_Settings::register_settings();
$mail_settings_group = $GLOBALS['flacso_registered_settings']['flacso_correos_group'] ?? [];

assert_true(isset($mail_settings_group['flacso_mautic_enabled']), 'flacso_mautic_enabled must be registered');
assert_true(isset($mail_settings_group['flacso_mautic_base_url']), 'flacso_mautic_base_url must be registered');
assert_true(isset($mail_settings_group['flacso_mautic_auth_type']), 'flacso_mautic_auth_type must be registered');
assert_true(isset($mail_settings_group['flacso_mautic_username']), 'flacso_mautic_username must be registered');
assert_true(isset($mail_settings_group['flacso_mautic_password']), 'flacso_mautic_password must be registered');
assert_true(isset($mail_settings_group['flacso_mautic_token']), 'flacso_mautic_token must be registered');

// Test sanitization callbacks
$enabled_cb = $mail_settings_group['flacso_mautic_enabled']['sanitize_callback'];
assert_true($enabled_cb('1') === '1', 'Enabled callback should normalize 1 to 1');
assert_true($enabled_cb('0') === '0', 'Enabled callback should normalize 0 to 0');
assert_true($enabled_cb('') === '0', 'Enabled callback should normalize empty to 0');

$url_cb = $mail_settings_group['flacso_mautic_base_url']['sanitize_callback'];
assert_true(call_user_func($url_cb, 'https://envios.flacso.edu.uy/') === 'https://envios.flacso.edu.uy', 'URL callback should strip trailing slash');

$auth_cb = $mail_settings_group['flacso_mautic_auth_type']['sanitize_callback'];
assert_true(call_user_func($auth_cb, 'basic') === 'basic', 'Auth type callback should accept basic');
assert_true(call_user_func($auth_cb, 'BEARER') === 'bearer', 'Auth type callback should accept bearer and normalize');
assert_true(call_user_func($auth_cb, 'invalid') === 'basic', 'Auth type callback should default to basic');

// Test 11: AJAX test connection endpoint
assert_true(method_exists('FLACSO_Mail_Settings', 'ajax_test_mautic_connection'), 'FLACSO_Mail_Settings must define ajax_test_mautic_connection');

// Case 11a: Mautic not configured / disabled
$GLOBALS['flacso_test_options']['flacso_mautic_enabled'] = '0';
$ajax_caught = null;
try {
    FLACSO_Mail_Settings::ajax_test_mautic_connection();
} catch (TestAjaxException $e) {
    $ajax_caught = $e;
}
assert_true($ajax_caught !== null && $ajax_caught->is_success === false, 'ajax_test_mautic_connection must fail when Mautic disabled');

// Case 11b: Successful connection
$GLOBALS['flacso_test_options']['flacso_mautic_enabled'] = '1';
$GLOBALS['flacso_test_options']['flacso_mautic_base_url'] = 'https://envios.flacso.edu.uy';
$GLOBALS['flacso_test_options']['flacso_mautic_auth_type'] = 'basic';
$GLOBALS['flacso_test_options']['flacso_mautic_username'] = 'admin';
$GLOBALS['flacso_test_options']['flacso_mautic_password'] = 'secret123';

$GLOBALS['custom_http_handler'] = function (string $url, array $args) {
    if (strpos($url, '/api/contacts') !== false) {
        return [
            'response' => ['code' => 200],
            'body' => json_encode(['total' => 5, 'contacts' => []]),
        ];
    }
    return ['response' => ['code' => 404], 'body' => ''];
};

$ajax_caught = null;
try {
    FLACSO_Mail_Settings::ajax_test_mautic_connection();
} catch (TestAjaxException $e) {
    $ajax_caught = $e;
}
assert_true($ajax_caught !== null && $ajax_caught->is_success === true, 'ajax_test_mautic_connection must succeed when Mautic returns 200');
assert_true(!empty($ajax_caught->data['message']), 'ajax_test_mautic_connection success must return message');

// Case 11c: Failed connection (Mautic returns 401 Unauthorized)
$GLOBALS['custom_http_handler'] = function (string $url, array $args) {
    if (strpos($url, '/api/contacts') !== false) {
        return [
            'response' => ['code' => 401],
            'body' => json_encode(['errors' => [['message' => 'Credenciales inválidas']]]),
        ];
    }
    return ['response' => ['code' => 500], 'body' => ''];
};

$ajax_caught = null;
try {
    FLACSO_Mail_Settings::ajax_test_mautic_connection();
} catch (TestAjaxException $e) {
    $ajax_caught = $e;
}
assert_true($ajax_caught !== null && $ajax_caught->is_success === false, 'ajax_test_mautic_connection must fail when Mautic returns 401');
$GLOBALS['custom_http_handler'] = null;

// Test 12: FLACSO_Mail_Settings::render_page() HTML contains Mautic settings section and controls
ob_start();
FLACSO_Mail_Settings::render_page();
$mail_settings_html = ob_get_clean();

assert_true(strpos($mail_settings_html, 'Mautic Marketing Automation') !== false, 'Render must contain Mautic section title');
assert_true(strpos($mail_settings_html, 'name="flacso_mautic_enabled"') !== false, 'Render must contain flacso_mautic_enabled checkbox');
assert_true(strpos($mail_settings_html, 'name="flacso_mautic_base_url"') !== false, 'Render must contain flacso_mautic_base_url input');
assert_true(strpos($mail_settings_html, 'name="flacso_mautic_auth_type"') !== false, 'Render must contain flacso_mautic_auth_type select');
assert_true(strpos($mail_settings_html, 'name="flacso_mautic_username"') !== false, 'Render must contain flacso_mautic_username input');
assert_true(strpos($mail_settings_html, 'name="flacso_mautic_password"') !== false, 'Render must contain flacso_mautic_password input');
assert_true(strpos($mail_settings_html, 'name="flacso_mautic_token"') !== false, 'Render must contain flacso_mautic_token input');
assert_true(strpos($mail_settings_html, 'id="flacso-test-mautic-btn"') !== false, 'Render must contain Mautic test button');
assert_true(strpos($mail_settings_html, 'flacso_mautic_test_connection') !== false, 'Render script must call flacso_mautic_test_connection AJAX action');

// Test 13: FLACSO_Mail_Settings::init() registers wp_ajax_flacso_mautic_test_connection
FLACSO_Mail_Settings::init();
$ajax_hooks = $GLOBALS['wp_actions']['wp_ajax_flacso_mautic_test_connection'] ?? [];
assert_true(!empty($ajax_hooks), 'init() must register wp_ajax_flacso_mautic_test_connection action');

// Test 14: get_paginated_inquiries projects Mautic columns for offer_inquiries
$page_mautic = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries([
    'table' => 'offer_inquiries',
    'mode'  => 'grouped',
    'desde' => $today,
    'hasta' => $today,
]);
assert_true(array_key_exists('mauticSyncStatus', $page_mautic['items'][0]), 'Grouped inquiry must project mauticSyncStatus');
assert_true(array_key_exists('mauticContactId', $page_mautic['items'][0]), 'Grouped inquiry must project mauticContactId');
assert_true(array_key_exists('mauticSyncedAt', $page_mautic['items'][0]), 'Grouped inquiry must project mauticSyncedAt');
assert_true(array_key_exists('mauticLastError', $page_mautic['items'][0]), 'Grouped inquiry must project mauticLastError');

$raw_mautic = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries([
    'table' => 'offer_inquiries',
    'mode'  => 'raw',
    'desde' => $today,
    'hasta' => $today,
]);
assert_true(array_key_exists('mauticSyncStatus', $raw_mautic['items'][0]), 'Raw inquiry must project mauticSyncStatus');
assert_true(array_key_exists('mauticContactId', $raw_mautic['items'][0]), 'Raw inquiry must project mauticContactId');
assert_true(array_key_exists('mauticSyncedAt', $raw_mautic['items'][0]), 'Raw inquiry must project mauticSyncedAt');
assert_true(array_key_exists('mauticLastError', $raw_mautic['items'][0]), 'Raw inquiry must project mauticLastError');

// Test 15: HTML Rendering of Mautic column, badges, and retry button
$_GET = [
    'page'  => 'flacso-consultas',
    'tab'   => 'historico',
    'table' => 'offer_inquiries',
];
ob_start();
FLACSO_Consultas_Admin::render_page();
$consultas_html = ob_get_clean();

assert_true(strpos($consultas_html, '<th>Mautic</th>') !== false, 'Admin table header must contain Mautic column');
assert_true(strpos($consultas_html, 'flacso-mautic-cell') !== false, 'Admin table must render flacso-mautic-cell');
assert_true(strpos($consultas_html, 'mautic-synced') !== false, 'Admin must render mautic-synced badge');
assert_true(strpos($consultas_html, '🟣 ID 7788') !== false, 'Admin must render contact ID in synced badge');
assert_true(strpos($consultas_html, 'mautic-failed') !== false, 'Admin must render mautic-failed badge');
assert_true(strpos($consultas_html, 'Connection timeout') !== false, 'Admin must render error message in failed badge title');
assert_true(strpos($consultas_html, 'flacso-js-retry-mautic') !== false, 'Admin must render retry button for failed/pending inquiries');
assert_true(strpos($consultas_html, 'data-id="c03"') !== false, 'Retry button must exist for failed row c03');

// Raw mode renders pending inquiry c02
$_GET['mode'] = 'raw';
ob_start();
FLACSO_Consultas_Admin::render_page();
$raw_consultas_html = ob_get_clean();
assert_true(strpos($raw_consultas_html, 'mautic-pending') !== false, 'Admin must render mautic-pending badge in raw mode');
assert_true(strpos($raw_consultas_html, 'data-id="c02"') !== false, 'Retry button must exist for pending row c02 in raw mode');
unset($_GET['mode']);

// Unit testing render_mautic_badge() for all 4 states
assert_true(strpos(FLACSO_Consultas_Admin::render_mautic_badge(['mauticSyncStatus' => 'synced', 'mauticContactId' => '123']), '🟣 ID 123') !== false, 'Badge synced with contact ID');
assert_true(strpos(FLACSO_Consultas_Admin::render_mautic_badge(['mauticSyncStatus' => 'synced']), '🟣 Sincronizado') !== false, 'Badge synced without contact ID');
assert_true(strpos(FLACSO_Consultas_Admin::render_mautic_badge(['mauticSyncStatus' => 'failed', 'mauticLastError' => 'Bad request']), '🔴 Error') !== false, 'Badge failed');
assert_true(strpos(FLACSO_Consultas_Admin::render_mautic_badge(['mauticSyncStatus' => 'pending']), '🟡 Pendiente') !== false, 'Badge pending');
assert_true(strpos(FLACSO_Consultas_Admin::render_mautic_badge(['mauticSyncStatus' => 'skipped']), '⚪ Omitido') !== false, 'Badge skipped');

// Test 16: Seminar inquiries table does NOT render Mautic header column or retry button
$_GET = [
    'page'  => 'flacso-consultas',
    'tab'   => 'historico',
    'table' => 'seminar_inquiries',
];
ob_start();
FLACSO_Consultas_Admin::render_page();
$seminar_html = ob_get_clean();
assert_true(strpos($seminar_html, '<th>Mautic</th>') === false, 'Seminar inquiries must NOT render Mautic header column');
assert_true(strpos($seminar_html, '<button type="button" class="button button-small flacso-js-retry-mautic"') === false, 'Seminar inquiries must NOT render retry mautic button');
assert_true(strpos($seminar_html, '🔄 Mautic') === false, 'Seminar inquiries must NOT render retry mautic button label');

// Test 17: ajax_retry_mautic endpoint
assert_true(method_exists('FLACSO_Consultas_Admin', 'ajax_retry_mautic'), 'FLACSO_Consultas_Admin must implement ajax_retry_mautic');
FLACSO_Consultas_Admin::init();
$consultas_ajax_hooks = $GLOBALS['wp_actions']['wp_ajax_flacso_consultas_retry_mautic'] ?? [];
assert_true(!empty($consultas_ajax_hooks), 'init() must register wp_ajax_flacso_consultas_retry_mautic action');

// Case 17a: Invalid ID
$_POST = ['nonce' => 'mock-nonce', 'id' => ''];
$ajax_caught = null;
try {
    FLACSO_Consultas_Admin::ajax_retry_mautic();
} catch (TestAjaxException $e) {
    $ajax_caught = $e;
}
assert_true($ajax_caught !== null && $ajax_caught->is_success === false && $ajax_caught->status_code === 400, 'ajax_retry_mautic must return 400 when ID is empty');

// Case 17b: Successful retry
$GLOBALS['flacso_test_options']['flacso_mautic_enabled'] = '1';
$GLOBALS['flacso_test_options']['flacso_mautic_base_url'] = 'https://envios.flacso.edu.uy';
$GLOBALS['flacso_test_options']['flacso_mautic_auth_type'] = 'basic';
$GLOBALS['flacso_test_options']['flacso_mautic_username'] = 'admin';
$GLOBALS['flacso_test_options']['flacso_mautic_password'] = 'secret123';

$GLOBALS['custom_http_handler'] = function (string $url, array $args) {
    if (strpos($url, '/api/contacts?search=email') !== false) {
        return [
            'response' => ['code' => 200],
            'body'     => json_encode(['total' => 0, 'contacts' => []]),
        ];
    }
    if (strpos($url, '/api/contacts/new') !== false) {
        return [
            'response' => ['code' => 201],
            'body'     => json_encode(['contact' => ['id' => 9999]]),
        ];
    }
    return ['response' => ['code' => 404], 'body' => ''];
};

$_POST = ['nonce' => 'mock-nonce', 'id' => 'c03'];
$ajax_caught = null;
try {
    FLACSO_Consultas_Admin::ajax_retry_mautic();
} catch (TestAjaxException $e) {
    $ajax_caught = $e;
}
assert_true($ajax_caught !== null && $ajax_caught->is_success === true, 'ajax_retry_mautic must succeed when Mautic client returns success');
assert_true($ajax_caught->data['status'] === 'synced', 'ajax_retry_mautic response status must be synced');
assert_true((int)$ajax_caught->data['contact_id'] === 9999, 'ajax_retry_mautic response must return contact_id 9999');

// Verify DB updated
$stmt_check = $pdo->prepare('SELECT "mauticSyncStatus", "mauticContactId" FROM "offer_inquiries" WHERE "id" = ?');
$stmt_check->execute(['c03']);
$updated_row = $stmt_check->fetch(PDO::FETCH_ASSOC);
assert_true($updated_row['mauticSyncStatus'] === 'synced', 'DB mauticSyncStatus must be synced after successful retry');
assert_true($updated_row['mauticContactId'] === '9999', 'DB mauticContactId must be 9999 after successful retry');

// Case 17c: Failed retry (Mautic returns 500 error)
$GLOBALS['custom_http_handler'] = function (string $url, array $args) {
    return [
        'response' => ['code' => 500],
        'body'     => json_encode(['errors' => [['message' => 'Internal server error in Mautic']]]),
    ];
};

$_POST = ['nonce' => 'mock-nonce', 'id' => 'c02'];
$ajax_caught = null;
try {
    FLACSO_Consultas_Admin::ajax_retry_mautic();
} catch (TestAjaxException $e) {
    $ajax_caught = $e;
}
assert_true($ajax_caught !== null && $ajax_caught->is_success === false && $ajax_caught->status_code === 500, 'ajax_retry_mautic must return 500 when Mautic fails');
assert_true($ajax_caught->data['status'] === 'failed', 'ajax_retry_mautic error response status must be failed');
// Test 18: Engine and Mautic template constants and settings registration
assert_true(defined('FLACSO_Mail_Settings::OPTION_INQUIRY_EMAIL_ENGINE'), 'FLACSO_Mail_Settings must define OPTION_INQUIRY_EMAIL_ENGINE');
assert_true(defined('FLACSO_Mail_Settings::OPTION_MAUTIC_TEMPLATE_OPEN'), 'FLACSO_Mail_Settings must define OPTION_MAUTIC_TEMPLATE_OPEN');
assert_true(defined('FLACSO_Mail_Settings::OPTION_MAUTIC_TEMPLATE_CLOSED'), 'FLACSO_Mail_Settings must define OPTION_MAUTIC_TEMPLATE_CLOSED');

FLACSO_Mail_Settings::register_settings();
$mail_settings_group = $GLOBALS['flacso_registered_settings']['flacso_correos_group'] ?? [];

assert_true(isset($mail_settings_group['flacso_inquiry_email_engine']), 'flacso_inquiry_email_engine must be registered');
assert_true(isset($mail_settings_group['flacso_mautic_template_consulta_abierta']), 'flacso_mautic_template_consulta_abierta must be registered');
assert_true(isset($mail_settings_group['flacso_mautic_template_consulta_cerrada']), 'flacso_mautic_template_consulta_cerrada must be registered');

// Test sanitization callbacks for engine and templates
$engine_cb = $mail_settings_group['flacso_inquiry_email_engine']['sanitize_callback'];
assert_true(call_user_func($engine_cb, 'mailjet') === 'mailjet', 'Engine callback should accept mailjet');
assert_true(call_user_func($engine_cb, 'mautic') === 'mautic', 'Engine callback should accept mautic');
assert_true(call_user_func($engine_cb, 'invalid') === 'mailjet', 'Engine callback should default to mailjet');

$tpl_open_cb = $mail_settings_group['flacso_mautic_template_consulta_abierta']['sanitize_callback'];
assert_true(call_user_func($tpl_open_cb, '12abc') === '12', 'Template open callback should sanitize numeric ID');

$tpl_closed_cb = $mail_settings_group['flacso_mautic_template_consulta_cerrada']['sanitize_callback'];
assert_true(call_user_func($tpl_closed_cb, '34') === '34', 'Template closed callback should sanitize numeric ID');

// Test 19: ajax_send_test_mautic_email endpoint
assert_true(method_exists('FLACSO_Mail_Settings', 'ajax_send_test_mautic_email'), 'FLACSO_Mail_Settings must define ajax_send_test_mautic_email');
FLACSO_Mail_Settings::init();
$mautic_send_ajax_hooks = $GLOBALS['wp_actions']['wp_ajax_flacso_mautic_send_test_email'] ?? [];
assert_true(!empty($mautic_send_ajax_hooks), 'init() must register wp_ajax_flacso_mautic_send_test_email action');

// Case 19a: Invalid email
$_POST = ['nonce' => 'mock-nonce', 'test_email' => ''];
$ajax_caught = null;
try {
    FLACSO_Mail_Settings::ajax_send_test_mautic_email();
} catch (TestAjaxException $e) {
    $ajax_caught = $e;
}
assert_true($ajax_caught !== null && $ajax_caught->is_success === false && $ajax_caught->status_code === 400, 'ajax_send_test_mautic_email must return 400 on empty email');

// Case 19b: Mautic not configured / disabled
$GLOBALS['flacso_test_options']['flacso_mautic_enabled'] = '0';
$_POST = ['nonce' => 'mock-nonce', 'test_email' => 'admin@flacso.edu.uy', 'scenario' => 'abierta'];
$ajax_caught = null;
try {
    FLACSO_Mail_Settings::ajax_send_test_mautic_email();
} catch (TestAjaxException $e) {
    $ajax_caught = $e;
}
assert_true($ajax_caught !== null && $ajax_caught->is_success === false && $ajax_caught->status_code === 400, 'ajax_send_test_mautic_email must return 400 when Mautic disabled');

// Configure Mautic credentials for next tests
$GLOBALS['flacso_test_options']['flacso_mautic_enabled'] = '1';
$GLOBALS['flacso_test_options']['flacso_mautic_base_url'] = 'https://envios.flacso.edu.uy';
$GLOBALS['flacso_test_options']['flacso_mautic_auth_type'] = 'basic';
$GLOBALS['flacso_test_options']['flacso_mautic_username'] = 'admin';
$GLOBALS['flacso_test_options']['flacso_mautic_password'] = 'secret123';

// Case 19c: Template not configured
$GLOBALS['flacso_test_options']['flacso_mautic_template_consulta_abierta'] = '';
$_POST = ['nonce' => 'mock-nonce', 'test_email' => 'admin@flacso.edu.uy', 'scenario' => 'abierta'];
$ajax_caught = null;
try {
    FLACSO_Mail_Settings::ajax_send_test_mautic_email();
} catch (TestAjaxException $e) {
    $ajax_caught = $e;
}
assert_true($ajax_caught !== null && $ajax_caught->is_success === false && $ajax_caught->status_code === 400, 'ajax_send_test_mautic_email must return 400 when template empty');

// Case 19d: Mautic contact creation failure
$GLOBALS['flacso_test_options']['flacso_mautic_template_consulta_abierta'] = '12';
$GLOBALS['custom_http_handler'] = function (string $url, array $args) {
    return [
        'response' => ['code' => 500],
        'body'     => json_encode(['errors' => [['message' => 'Failed to create contact']]]),
    ];
};
$_POST = ['nonce' => 'mock-nonce', 'test_email' => 'admin@flacso.edu.uy', 'scenario' => 'abierta'];
$ajax_caught = null;
try {
    FLACSO_Mail_Settings::ajax_send_test_mautic_email();
} catch (TestAjaxException $e) {
    $ajax_caught = $e;
}
assert_true($ajax_caught !== null && $ajax_caught->is_success === false && $ajax_caught->status_code === 500, 'ajax_send_test_mautic_email must return 500 when contact creation fails');

// Case 19e: Mautic email send failure
$GLOBALS['custom_http_handler'] = function (string $url, array $args) {
    if (strpos($url, '/api/contacts?search=email') !== false) {
        return ['response' => ['code' => 200], 'body' => json_encode(['total' => 0, 'contacts' => []])];
    }
    if (strpos($url, '/api/contacts/new') !== false) {
        return ['response' => ['code' => 201], 'body' => json_encode(['contact' => ['id' => 8888]])];
    }
    if (strpos($url, '/send') !== false) {
        return ['response' => ['code' => 500], 'body' => json_encode(['errors' => [['message' => 'Template render error']]])];
    }
    return ['response' => ['code' => 404], 'body' => ''];
};
$_POST = ['nonce' => 'mock-nonce', 'test_email' => 'admin@flacso.edu.uy', 'scenario' => 'abierta'];
$ajax_caught = null;
try {
    FLACSO_Mail_Settings::ajax_send_test_mautic_email();
} catch (TestAjaxException $e) {
    $ajax_caught = $e;
}
assert_true($ajax_caught !== null && $ajax_caught->is_success === false && $ajax_caught->status_code === 500, 'ajax_send_test_mautic_email must return 500 when sending email fails');

// Case 19f: Mautic email send success
$GLOBALS['custom_http_handler'] = function (string $url, array $args) {
    if (strpos($url, '/api/contacts?search=email') !== false) {
        return ['response' => ['code' => 200], 'body' => json_encode(['total' => 0, 'contacts' => []])];
    }
    if (strpos($url, '/api/contacts/new') !== false) {
        return ['response' => ['code' => 201], 'body' => json_encode(['contact' => ['id' => 8888]])];
    }
    if (strpos($url, '/send') !== false) {
        return ['response' => ['code' => 200], 'body' => json_encode(['success' => true])];
    }
    return ['response' => ['code' => 404], 'body' => ''];
};
$_POST = ['nonce' => 'mock-nonce', 'test_email' => 'admin@flacso.edu.uy', 'scenario' => 'abierta'];
$ajax_caught = null;
try {
    FLACSO_Mail_Settings::ajax_send_test_mautic_email();
} catch (TestAjaxException $e) {
    $ajax_caught = $e;
}
assert_true($ajax_caught !== null && $ajax_caught->is_success === true, 'ajax_send_test_mautic_email must succeed when Mautic sends email');
assert_true(!empty($ajax_caught->data['message']), 'ajax_send_test_mautic_email success must return message');

// Case 19g: Mautic email send scenario cerrada uses closed template
$GLOBALS['flacso_test_options']['flacso_mautic_template_consulta_cerrada'] = '77';
$sent_url = null;
$GLOBALS['custom_http_handler'] = function (string $url, array $args) use (&$sent_url) {
    if (strpos($url, '/api/contacts?search=email') !== false) {
        return ['response' => ['code' => 200], 'body' => json_encode(['total' => 0, 'contacts' => []])];
    }
    if (strpos($url, '/api/contacts/new') !== false) {
        return ['response' => ['code' => 201], 'body' => json_encode(['contact' => ['id' => 9999]])];
    }
    if (strpos($url, '/send') !== false) {
        $sent_url = $url;
        return ['response' => ['code' => 200], 'body' => json_encode(['success' => true])];
    }
    return ['response' => ['code' => 404], 'body' => ''];
};
$_POST = ['nonce' => 'mock-nonce', 'test_email' => 'admin@flacso.edu.uy', 'scenario' => 'cerrada'];
$ajax_caught = null;
try {
    FLACSO_Mail_Settings::ajax_send_test_mautic_email();
} catch (TestAjaxException $e) {
    $ajax_caught = $e;
}
assert_true($ajax_caught !== null && $ajax_caught->is_success === true, 'ajax_send_test_mautic_email cerrada must succeed');
assert_true(strpos($sent_url, '/api/emails/77/contact/9999/send') !== false, 'ajax_send_test_mautic_email cerrada must target template ID 77');
$GLOBALS['custom_http_handler'] = null;

// Test 20: Render page contains engine selector, Mautic template inputs and Mautic test sender
ob_start();
FLACSO_Mail_Settings::render_page();
$rendered_html = ob_get_clean();

assert_true(strpos($rendered_html, 'name="flacso_inquiry_email_engine"') !== false, 'Render must contain flacso_inquiry_email_engine control');
assert_true(strpos($rendered_html, 'value="mailjet"') !== false, 'Render must contain mailjet engine option');
assert_true(strpos($rendered_html, 'value="mautic"') !== false, 'Render must contain mautic engine option');
assert_true(strpos($rendered_html, 'name="flacso_mautic_template_consulta_abierta"') !== false, 'Render must contain flacso_mautic_template_consulta_abierta input');
assert_true(strpos($rendered_html, 'name="flacso_mautic_template_consulta_cerrada"') !== false, 'Render must contain flacso_mautic_template_consulta_cerrada input');
assert_true(strpos($rendered_html, 'id="flacso-btn-send-test-mautic"') !== false, 'Render must contain #flacso-btn-send-test-mautic button');
assert_true(strpos($rendered_html, 'flacso_mautic_send_test_email') !== false, 'Render script must call flacso_mautic_send_test_email AJAX action');

// Test 21: FLACSO_Consultas_Admin::render_email_status_badge() for all combinations
assert_true(method_exists('FLACSO_Consultas_Admin', 'render_email_status_badge'), 'FLACSO_Consultas_Admin must define render_email_status_badge');

// 21a: sent via mautic
$badge_sent_mautic = FLACSO_Consultas_Admin::render_email_status_badge(['emailStatus' => 'sent', 'emailSender' => 'mautic']);
assert_true(strpos($badge_sent_mautic, 'flacso-badge sent mautic') !== false, 'Badge sent via mautic class');
assert_true(strpos($badge_sent_mautic, 'title="Enviado vía Mautic"') !== false, 'Badge sent via mautic title');
assert_true(strpos($badge_sent_mautic, 'sent (Mautic)') !== false, 'Badge sent via mautic label');

// 21b: sent via fallback
$badge_sent_fallback = FLACSO_Consultas_Admin::render_email_status_badge(['emailStatus' => 'sent', 'emailSender' => 'mailjet_fallback']);
assert_true(strpos($badge_sent_fallback, 'flacso-badge sent fallback') !== false, 'Badge sent via fallback class');
assert_true(strpos($badge_sent_fallback, 'title="Enviado vía Mailjet por conmutación (fallback Mautic)"') !== false, 'Badge sent via fallback title');
assert_true(strpos($badge_sent_fallback, 'sent (Fallback)') !== false, 'Badge sent via fallback label');

// 21c: sent via mailjet (explicit and default)
$badge_sent_mailjet = FLACSO_Consultas_Admin::render_email_status_badge(['emailStatus' => 'sent', 'emailSender' => 'mailjet']);
assert_true(strpos($badge_sent_mailjet, 'class="flacso-badge sent"') !== false, 'Badge sent via mailjet class');
assert_true(strpos($badge_sent_mailjet, 'title="Enviado vía Mailjet"') !== false, 'Badge sent via mailjet title');
assert_true(strpos($badge_sent_mailjet, '>sent<') !== false, 'Badge sent via mailjet label');

$badge_sent_def = FLACSO_Consultas_Admin::render_email_status_badge(['emailStatus' => 'sent', 'emailSender' => '']);
assert_true(strpos($badge_sent_def, 'class="flacso-badge sent"') !== false, 'Badge sent default class');
assert_true(strpos($badge_sent_def, 'title="Enviado vía Mailjet"') !== false, 'Badge sent default title');
assert_true(strpos($badge_sent_def, '>sent<') !== false, 'Badge sent default label');

// 21d: failed via fallback
$badge_failed_fallback = FLACSO_Consultas_Admin::render_email_status_badge(['emailStatus' => 'failed', 'emailSender' => 'mailjet_fallback']);
assert_true(strpos($badge_failed_fallback, 'flacso-badge failed fallback') !== false, 'Badge failed via fallback class');
assert_true(strpos($badge_failed_fallback, 'title="Falló el envío vía Mailjet tras conmutación desde Mautic"') !== false, 'Badge failed via fallback title');
assert_true(strpos($badge_failed_fallback, 'failed (Fallback)') !== false, 'Badge failed via fallback label');

// 21e: failed via mautic
$badge_failed_mautic = FLACSO_Consultas_Admin::render_email_status_badge(['emailStatus' => 'failed', 'emailSender' => 'mautic']);
assert_true(strpos($badge_failed_mautic, 'flacso-badge failed mautic') !== false, 'Badge failed via mautic class');
assert_true(strpos($badge_failed_mautic, 'title="Falló el envío vía Mautic"') !== false, 'Badge failed via mautic title');
assert_true(strpos($badge_failed_mautic, 'failed (Mautic)') !== false, 'Badge failed via mautic label');

// 21f: failed default
$badge_failed_def = FLACSO_Consultas_Admin::render_email_status_badge(['emailStatus' => 'failed', 'emailSender' => 'mailjet']);
assert_true(strpos($badge_failed_def, 'class="flacso-badge failed"') !== false, 'Badge failed default class');
assert_true(strpos($badge_failed_def, 'title="Envío fallido"') !== false, 'Badge failed default title');
assert_true(strpos($badge_failed_def, '>failed<') !== false, 'Badge failed default label');

// 21g: skipped
$badge_skipped = FLACSO_Consultas_Admin::render_email_status_badge(['emailStatus' => 'skipped']);
assert_true(strpos($badge_skipped, 'flacso-badge skipped') !== false, 'Badge skipped class');
assert_true(strpos($badge_skipped, 'title="Envío omitido"') !== false, 'Badge skipped title');
assert_true(strpos($badge_skipped, '>skipped<') !== false, 'Badge skipped label');

// 21h: other status fallback
$badge_other = FLACSO_Consultas_Admin::render_email_status_badge(['emailStatus' => 'processing']);
assert_true(strpos($badge_other, 'flacso-badge processing') !== false, 'Badge processing class');
assert_true(strpos($badge_other, '>processing<') !== false, 'Badge processing label');

// Test 22: HTML rendering contains fallback CSS classes and modal sender formatting
$_GET = [
    'page'  => 'flacso-consultas',
    'tab'   => 'historico',
    'table' => 'offer_inquiries',
];
ob_start();
FLACSO_Consultas_Admin::render_page();
$rendered_consultas_page = ob_get_clean();

assert_true(strpos($rendered_consultas_page, '.flacso-badge.sent.fallback') !== false, 'Page must include .flacso-badge.sent.fallback CSS');
assert_true(strpos($rendered_consultas_page, '.flacso-badge.sent.mautic') !== false, 'Page must include .flacso-badge.sent.mautic CSS');
assert_true(strpos($rendered_consultas_page, '.flacso-badge.failed.fallback') !== false, 'Page must include .flacso-badge.failed.fallback CSS');
assert_true(strpos($rendered_consultas_page, 'Mailjet (Conmutación por fallo de Mautic)') !== false, 'Modal script must contain fallback label formatting');

echo "OK mail-console-and-consultas-admin-test\n";

