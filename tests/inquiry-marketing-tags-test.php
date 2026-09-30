<?php
$root = dirname(__DIR__);
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
$GLOBALS['marketing_options'] = [];
if (!function_exists('get_option')) {
    function get_option($key, $default = false) { return $GLOBALS['marketing_options'][$key] ?? $default; }
}
if (!function_exists('update_option')) {
    function update_option($key, $value) { $GLOBALS['marketing_options'][$key] = $value; return true; }
}
if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(private string $message) {}
        public function get_error_message(): string { return $this->message; }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($value) { return $value instanceof WP_Error; }
}

require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-base-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
require_once $root . '/includes/integrations/class-flacso-mautic-client.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-tag-factory.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-snapshot.php';
require_once $root . '/modules/consultas/services/class-flacso-mautic-payload-builder.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-marketing-service.php';

function marketing_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

marketing_assert(
    FLACSO_Inquiry_Marketing_Service::generate_tags('DAVIA', 10, 'abierta')
        === ['interes-davia', 'davia-c10', 'origen-web-consultas'],
    'tags de cohorte usan definición canónica'
);
marketing_assert(
    FLACSO_Inquiry_Marketing_Service::generate_tags('DAVIA', null, 'sin_cohorte')
        === ['interes-davia', 'origen-web-consultas'],
    'sin cohorte conserva interés y origen'
);
marketing_assert(
    FLACSO_Inquiry_Marketing_Service::generate_tags('', 10, 'abierta') === [],
    'sin código no inventa tags'
);

$tokens = FLACSO_Inquiry_Marketing_Service::compile_tokens(
    [
        'firstName'=>'Ana','lastName'=>'Pérez','email'=>'ana@example.org',
        'country'=>'Uruguay','profession'=>'Socióloga','educationLevel'=>'Maestría',
        'cohortName'=>'Cohorte 4','cohortNumber'=>4,
    ],
    [
        'name'=>'Diploma en Derechos Humanos',
        'urlBase'=>'https://flacso.edu.uy/dh/',
        'preinscripcionUrl'=>'https://pre.flacso.edu.uy/dh',
        'startValue'=>'2026-05-15','startPrecision'=>'dia','modalityLabel'=>'virtual',
    ],
    true
);
marketing_assert($tokens['{programa}'] === 'Diploma en Derechos Humanos', 'compatibilidad de tokens');
marketing_assert($tokens['{fecha_inicio}'] === '15 de mayo de 2026', 'fecha de compatibilidad');
marketing_assert($tokens['{modalidad}'] === 'Virtual', 'modalidad de compatibilidad');

echo "OK inquiry-marketing-tags-test\n";
