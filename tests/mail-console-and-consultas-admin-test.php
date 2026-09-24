<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
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
        $GLOBALS['mailjet_http_calls'][] = ['method' => $args['method'] ?? 'POST', 'url' => $url, 'args' => $args];
        return ['response' => ['code' => 201], 'body' => '{"Count":1}'];
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

require_once dirname(__DIR__) . '/modules/consultas/init.php';
require_once dirname(__DIR__) . '/modules/mailing/includes/class-flacso-mail-settings.php';

function assert_true(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// 1. Setup SQLite in-memory schema matching Prisma PostgreSQL columns
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec('CREATE TABLE "offer_inquiries" (
    "id" TEXT PRIMARY KEY,
    "consultaId" TEXT UNIQUE,
    "offerWpId" INTEGER,
    "offerName" TEXT,
    "offerType" TEXT,
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
    ("id","consultaId","offerWpId","offerName","firstName","lastName","fullName","email","emailNormalized","phone","country","source","campaignProvider","campaignSource","campaignMedium","campaignName","emailStatus","payload","inquiryAt","createdAt","updatedAt")
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

// Ana in Uruguay (2 inquiries for same Maestría -> deduplicates to 1 pair)
$stmt->execute(['c01', 'cid-1', 501, 'Maestría en Género', 'Ana', 'Pérez', 'Ana Pérez', 'ana@ejemplo.com', 'ana@ejemplo.com', '099111', 'Uruguay', 'web', 'meta', 'facebook', 'cpc', 'Campaña Género', 'sent', '{"test":1}', $now, $now, $now]);
$stmt->execute(['c02', 'cid-2', 501, 'Maestría en Género', 'Ana', 'Pérez', 'Ana Pérez', 'ana@ejemplo.com', 'ana@ejemplo.com', '099111', 'Uruguay', 'web', 'meta', 'facebook', 'cpc', 'Campaña Género', 'sent', '{"test":2}', $now, $now, $now]);
// Ana also in Argentina for another offer -> creates UY/EXT intersection for ana@ejemplo.com
$stmt->execute(['c03', 'cid-3', 502, 'Diploma en Educación', 'Ana', 'Pérez', 'Ana Pérez', 'ana@ejemplo.com', 'ana@ejemplo.com', '099111', 'Argentina', 'web', 'google', 'google', 'cpc', 'Campaña Educación', 'failed', '{"test":3}', $now, $now, $now]);

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

echo "OK mail-console-and-consultas-admin-test\n";
