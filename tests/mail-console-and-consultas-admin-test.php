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

// Ana in Uruguay (2 inquiries for same Maestría -> deduplicates to 1 pair)
$stmt->execute(['c01', 'cid-1', 501, 'Maestría en Género', 'mg', 2, '2ª Cohorte', 'abierta', 'Ana', 'Pérez', 'Ana Pérez', 'ana@ejemplo.com', 'ana@ejemplo.com', '099111', 'Uruguay', 'web', 'meta', 'facebook', 'cpc', 'Campaña Género', 'sent', '{"test":1}', $now, $now, $now]);
$stmt->execute(['c02', 'cid-2', 501, 'Maestría en Género', 'mg', 2, '2ª Cohorte', 'abierta', 'Ana', 'Pérez', 'Ana Pérez', 'ana@ejemplo.com', 'ana@ejemplo.com', '099111', 'Uruguay', 'web', 'meta', 'facebook', 'cpc', 'Campaña Género', 'sent', '{"test":2}', $now, $now, $now]);
// Ana also in Argentina for another offer -> creates UY/EXT intersection for ana@ejemplo.com
$stmt->execute(['c03', 'cid-3', 502, 'Diploma en Educación', null, null, null, 'cerrada', 'Ana', 'Pérez', 'Ana Pérez', 'ana@ejemplo.com', 'ana@ejemplo.com', '099111', 'Argentina', 'web', 'google', 'google', 'cpc', 'Campaña Educación', 'failed', '{"test":3}', $now, $now, $now]);

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

echo "OK mail-console-and-consultas-admin-test\n";
