<?php
// tests/inquiry-verify-script-test.php
declare(strict_types=1);

define('FLACSO_TEST_MODE', true);

$root = dirname(__DIR__);

function verify_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

$script_file = $root . '/scripts/verify-postgres-connection.php';
$guide_file  = $root . '/docs/decommission-editor-deployment-guide.md';

// 1. Verificación de existencia de archivos
verify_assert(file_exists($script_file), "Script {$script_file} debe existir");
verify_assert(file_exists($guide_file), "Guía {$guide_file} debe existir");

// 2. Verificación de contratos en el contenido de los archivos
$script_content = (string) file_get_contents($script_file);
$guide_content  = (string) file_get_contents($guide_file);

verify_assert(strpos($script_content, 'ROLLBACK') !== false, 'El script debe contener ROLLBACK');
verify_assert(strpos($script_content, 'offer_inquiries') !== false, 'El script debe contener offer_inquiries');
verify_assert(strpos($script_content, 'seminar_inquiries') !== false, 'El script debe contener seminar_inquiries');
verify_assert(strpos($script_content, 'FLACSO_PG_HOST') !== false, 'El script debe contener FLACSO_PG_HOST');

verify_assert(strpos($guide_content, 'ROLLBACK') !== false, 'La guía debe contener ROLLBACK');
verify_assert(strpos($guide_content, 'offer_inquiries') !== false, 'La guía debe contener offer_inquiries');
verify_assert(strpos($guide_content, 'seminar_inquiries') !== false, 'La guía debe contener seminar_inquiries');
verify_assert(strpos($guide_content, 'FLACSO_PG_HOST') !== false, 'La guía debe contener FLACSO_PG_HOST');
verify_assert(strpos($guide_content, 'flacso-editor.service') !== false, 'La guía debe contener flacso-editor.service');

// 3. Cargar el script para verificar la función
require_once $script_file;

verify_assert(function_exists('flacso_run_postgres_verification'), 'flacso_run_postgres_verification debe estar definida');

// 4. Configurar SQLite en memoria con el esquema de tablas
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec('
CREATE TABLE offer_inquiries (
    id TEXT PRIMARY KEY,
    consultaId TEXT UNIQUE,
    offerWpId INTEGER,
    offerName TEXT,
    offerType TEXT,
    firstName TEXT,
    lastName TEXT,
    fullName TEXT,
    email TEXT,
    emailNormalized TEXT,
    country TEXT,
    profession TEXT,
    educationLevel TEXT,
    source TEXT DEFAULT "Web",
    campaignProvider TEXT,
    campaignSource TEXT,
    campaignMedium TEXT,
    campaignName TEXT,
    campaignExternalId TEXT,
    campaignContent TEXT,
    campaignTerm TEXT,
    urlBase TEXT,
    urlReferer TEXT,
    inquiryAt TEXT,
    ipAddress TEXT,
    userAgent TEXT,
    replyToEmail TEXT,
    programUrl TEXT,
    cartaUrl TEXT,
    preinscripcionUrl TEXT,
    offerStatus TEXT,
    emailStatus TEXT DEFAULT "skipped",
    emailSender TEXT,
    gmailMessageUrl TEXT,
    mailjetMessageId TEXT,
    mailjetMessageUuid TEXT,
    payload TEXT,
    createdAt TEXT,
    updatedAt TEXT
);

CREATE TABLE seminar_inquiries (
    id TEXT PRIMARY KEY,
    consultaId TEXT UNIQUE,
    seminarWpId INTEGER,
    seminarName TEXT,
    seminarType TEXT,
    firstName TEXT,
    lastName TEXT,
    fullName TEXT,
    email TEXT,
    emailNormalized TEXT,
    country TEXT,
    profession TEXT,
    educationLevel TEXT,
    source TEXT DEFAULT "Seminario",
    campaignProvider TEXT,
    campaignSource TEXT,
    campaignMedium TEXT,
    campaignName TEXT,
    campaignExternalId TEXT,
    campaignContent TEXT,
    campaignTerm TEXT,
    urlBase TEXT,
    urlReferer TEXT,
    inquiryAt TEXT,
    ipAddress TEXT,
    userAgent TEXT,
    replyToEmail TEXT,
    programUrl TEXT,
    cartaUrl TEXT,
    preinscripcionUrl TEXT,
    offerStatus TEXT,
    emailStatus TEXT DEFAULT "skipped",
    emailSender TEXT,
    gmailMessageUrl TEXT,
    mailjetMessageId TEXT,
    mailjetMessageUuid TEXT,
    payload TEXT,
    createdAt TEXT,
    updatedAt TEXT
);
');

// Insertar una fila previa en cada tabla para verificar que las existentes persisten
$pdo->exec("INSERT INTO offer_inquiries (id, consultaId, offerName, email) VALUES ('init-offer-1', 'cid-prev-1', 'Oferta Base', 'base@flacso.edu.uy')");
$pdo->exec("INSERT INTO seminar_inquiries (id, consultaId, seminarName, email) VALUES ('init-sem-1', 'cid-sem-prev-1', 'Seminario Base', 'base@flacso.edu.uy')");

// 5. Ejecutar la función de diagnóstico inyectando el PDO
$res = flacso_run_postgres_verification($pdo);

verify_assert(is_array($res), 'flacso_run_postgres_verification debe devolver un array');
verify_assert(isset($res['ok']) && $res['ok'] === true, 'El diagnóstico debe retornar ok => true');
verify_assert(isset($res['offer_inquiries']) && $res['offer_inquiries'] === 1, 'offer_inquiries debe reflejar 1 fila inicial');
verify_assert(isset($res['seminar_inquiries']) && $res['seminar_inquiries'] === 1, 'seminar_inquiries debe reflejar 1 fila inicial');
verify_assert(isset($res['rollback']) && $res['rollback'] === 'OK', 'rollback debe ser OK');
verify_assert(isset($res['permisos_escritura']) && $res['permisos_escritura'] === 'OK', 'permisos_escritura debe ser OK');

// 6. Verificar que después de ROLLBACK, las filas de diagnóstico no existen y las filas iniciales siguen intactas
$count_offers = (int) $pdo->query('SELECT COUNT(*) FROM offer_inquiries')->fetchColumn();
$count_seminars = (int) $pdo->query('SELECT COUNT(*) FROM seminar_inquiries')->fetchColumn();

verify_assert($count_offers === 1, "offer_inquiries debe tener exactamente 1 fila tras rollback (obtenido: {$count_offers})");
verify_assert($count_seminars === 1, "seminar_inquiries debe tener exactamente 1 fila tras rollback (obtenido: {$count_seminars})");

$diag_offers = (int) $pdo->query("SELECT COUNT(*) FROM offer_inquiries WHERE consultaId LIKE 'diag-%'")->fetchColumn();
$diag_seminars = (int) $pdo->query("SELECT COUNT(*) FROM seminar_inquiries WHERE consultaId LIKE 'diag-%'")->fetchColumn();

verify_assert($diag_offers === 0, "No deben quedar filas de diagnóstico en offer_inquiries tras rollback");
verify_assert($diag_seminars === 0, "No deben quedar filas de diagnóstico en seminar_inquiries tras rollback");

echo "OK inquiry-verify-script-test\n";
