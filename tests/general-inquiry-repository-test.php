<?php
// La consulta general se copia a general_inquiries usando sólo las columnas existentes.
$root = dirname(__DIR__);
require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-general-inquiry-repository.php';

function gi_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE general_inquiries ("id" TEXT PRIMARY KEY, "consultaId" TEXT UNIQUE, "fullName" TEXT, "email" TEXT, "unit" TEXT, "source" TEXT, "createdAt" TEXT)');
FLACSO_DB::set_connection($pdo);

$repo = new FLACSO_General_Inquiry_Repository();
$data = ['consultaId' => 'evt-1', 'firstName' => 'Ana', 'lastName' => 'Pérez', 'email' => 'ana@example.org', 'subject' => 'Hola', 'message' => 'Consulta'];
$first = $repo->insert($data);
gi_assert($first['duplicate'] === false, 'primera inserción no es duplicado');
$row = $pdo->query('SELECT * FROM general_inquiries')->fetch();
gi_assert($row['fullName'] === 'Ana Pérez' && $row['source'] === 'Contacto' && $row['unit'] === 'Secretaría', 'guarda nombre, origen y unidad');

$second = $repo->insert($data);
gi_assert($second['duplicate'] === true && $second['id'] === $first['id'], 'reintento con el mismo consultaId es idempotente');
gi_assert((int) $pdo->query('SELECT COUNT(*) FROM general_inquiries')->fetchColumn() === 1, 'no duplica filas');

FLACSO_DB::reset();
fwrite(STDOUT, "OK general inquiry repository\n");
