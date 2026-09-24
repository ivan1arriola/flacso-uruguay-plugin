<?php
// tests/inquiry-db-connection-test.php
$root = dirname(__DIR__);
require_once $root . '/includes/database/class-flacso-db.php';

function db_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// 1. Sin configuración inicial y sin conexión inyectada
FLACSO_DB::reset();
db_assert(FLACSO_DB::is_configured() === false, 'Sin constantes FLACSO_PG_* debe retornar false en is_configured');

$threw = false;
try {
    FLACSO_DB::connection();
} catch (RuntimeException $e) {
    $threw = true;
    db_assert(strpos($e->getMessage(), 'PostgreSQL no está configurado') !== false, 'Mensaje de excepción debe indicar falta de configuración');
}
db_assert($threw, 'connection() sin configurar debe lanzar RuntimeException');

// 2. Inyección de conexión SQLite en memoria
$test_pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
FLACSO_DB::set_connection($test_pdo);
db_assert(FLACSO_DB::connection() === $test_pdo, 'connection() debe retornar la instancia inyectada');
db_assert(FLACSO_DB::is_configured() === true, 'Con conexión inyectada is_configured debe retornar true');

// 3. Reset
FLACSO_DB::reset();
db_assert(FLACSO_DB::is_configured() === false, 'Tras reset() vuelve a no configurado');

// 4. Inyección y reseteo vía set_connection(null)
FLACSO_DB::set_connection($test_pdo);
db_assert(FLACSO_DB::is_configured() === true, 'Conexión re-inyectada');
FLACSO_DB::set_connection(null);
db_assert(FLACSO_DB::is_configured() === false, 'set_connection(null) vuelve a no configurado');

// 5. Verificación de constantes en proceso aislado
$cmd_configured = sprintf(
    'php -r "define(\'FLACSO_PG_HOST\', \'127.0.0.1\'); define(\'FLACSO_PG_PORT\', 5432); define(\'FLACSO_PG_DATABASE\', \'flacso_db\'); define(\'FLACSO_PG_USER\', \'flacso_user\'); define(\'FLACSO_PG_PASSWORD\', \'flacso_pass\'); require_once \'%s/includes/database/class-flacso-db.php\'; exit(FLACSO_DB::is_configured() ? 0 : 2);"',
    addslashes($root)
);
exec($cmd_configured, $out_conf, $code_conf);
db_assert($code_conf === 0, 'Con todas las constantes FLACSO_PG_* definidas, is_configured() debe retornar true');

// 6. Verificación con constante vacía en proceso aislado
$cmd_empty = sprintf(
    'php -r "define(\'FLACSO_PG_HOST\', \'\'); define(\'FLACSO_PG_PORT\', 5432); define(\'FLACSO_PG_DATABASE\', \'flacso_db\'); define(\'FLACSO_PG_USER\', \'flacso_user\'); define(\'FLACSO_PG_PASSWORD\', \'flacso_pass\'); require_once \'%s/includes/database/class-flacso-db.php\'; exit(FLACSO_DB::is_configured() ? 2 : 0);"',
    addslashes($root)
);
exec($cmd_empty, $out_empty, $code_empty);
db_assert($code_empty === 0, 'Con FLACSO_PG_HOST vacío, is_configured() debe retornar false');

echo "OK inquiry-db-connection-test\n";
