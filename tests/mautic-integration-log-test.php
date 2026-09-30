<?php

define('ABSPATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/includes/core/class-flacso-mautic-integration-log.php';

function mautic_log_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$log_file = tempnam(sys_get_temp_dir(), 'flacso-mautic-log-');
ini_set('error_log', $log_file);
FLACSO_Mautic_Integration_Log::write([
    'consulta_id' => 'consulta-123',
    'contact_id' => 42,
    'campaign_id' => 7,
    'operation' => 'campaign_membership',
    'result' => 'failed',
    'http_code' => 500,
    'error_class' => 'mautic_api',
    'email' => 'Persona@Ejemplo.uy',
    'token' => 'token-secreto',
    'password' => 'password-secreto',
    'message' => 'mensaje privado',
    'payload' => ['email' => 'Persona@Ejemplo.uy'],
]);

$output = (string) file_get_contents($log_file);
@unlink($log_file);
mautic_log_assert(strpos($output, hash('sha256', 'persona@ejemplo.uy')) !== false, 'El correo se registra como hash');
mautic_log_assert(strpos($output, 'Persona@Ejemplo.uy') === false, 'El correo no aparece en claro');
mautic_log_assert(strpos($output, 'token-secreto') === false, 'El token no aparece en el registro');
mautic_log_assert(strpos($output, 'password-secreto') === false, 'La contraseña no aparece en el registro');
mautic_log_assert(strpos($output, 'mensaje privado') === false, 'El mensaje no aparece en el registro');
mautic_log_assert(strpos($output, 'campaign_membership') !== false, 'La operación permitida se registra');

echo "OK mautic-integration-log-test\n";
