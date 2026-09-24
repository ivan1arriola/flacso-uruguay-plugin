<?php
/**
 * Test de carga del módulo de consultas y sus dependencias.
 *
 * Verifica:
 * 1. modules/consultas/init.php existe y carga los 7 archivos en el orden estricto de dependencias.
 * 2. flacso-uruguay.php invoca $loader->load_module('consultas') inmediatamente después de 'core'.
 * 3. Simulación de carga de modules/consultas/init.php define todas las clases requeridas.
 */

$root = dirname(__DIR__);

function loader_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// =========================================================================
// 1. Verificar existencia de modules/consultas/init.php y orden de dependencias
// =========================================================================
$init_file = $root . '/modules/consultas/init.php';
loader_assert(file_exists($init_file), "modules/consultas/init.php debe existir en {$init_file}");

$init_content = file_get_contents($init_file);

$expected_files_in_order = [
    'includes/database/class-flacso-db.php',
    'includes/database/repositories/class-flacso-base-inquiry-repository.php',
    'includes/database/repositories/class-flacso-offer-inquiry-repository.php',
    'includes/database/repositories/class-flacso-seminar-inquiry-repository.php',
    'includes/integrations/class-flacso-mailjet-client.php',
    'modules/consultas/services/class-flacso-offer-inquiry-service.php',
    'modules/consultas/services/class-flacso-seminar-inquiry-service.php',
];

$last_pos = -1;
foreach ($expected_files_in_order as $idx => $rel_file) {
    $pos = strpos($init_content, $rel_file);
    loader_assert($pos !== false, "modules/consultas/init.php debe referenciar {$rel_file}");
    loader_assert($pos > $last_pos, "{$rel_file} debe cargarse en orden estricto (esperado después del archivo anterior)");
    $last_pos = $pos;
}

// Verificar guardia de seguridad ABSPATH en init.php
loader_assert(
    strpos($init_content, "if (!defined('ABSPATH'))") !== false || strpos($init_content, "if ( ! defined( 'ABSPATH' ) )") !== false,
    "modules/consultas/init.php debe tener guardia if (!defined('ABSPATH')) { exit; }"
);

// Verificar definición de constantes del módulo
loader_assert(strpos($init_content, 'FLACSO_CONSULTAS_MODULE_PATH') !== false, "modules/consultas/init.php debe definir FLACSO_CONSULTAS_MODULE_PATH");
loader_assert(strpos($init_content, 'FLACSO_CONSULTAS_MODULE_VERSION') !== false, "modules/consultas/init.php debe definir FLACSO_CONSULTAS_MODULE_VERSION");

// =========================================================================
// 2. Verificar que flacso-uruguay.php carga el módulo consultas tras core
// =========================================================================
$main_file = $root . '/flacso-uruguay.php';
loader_assert(file_exists($main_file), "flacso-uruguay.php debe existir en {$main_file}");

$main_content = file_get_contents($main_file);

loader_assert(
    strpos($main_content, "\$loader->load_module('consultas')") !== false || strpos($main_content, '$loader->load_module("consultas")') !== false,
    "flacso-uruguay.php debe invocar \$loader->load_module('consultas')"
);

// Verificar que se carga justo después de 'core' y antes de 'oferta-academica' y 'main-page'
$core_pos = strpos($main_content, "load_module('core')");
$consultas_pos = strpos($main_content, "load_module('consultas')");
$oferta_pos = strpos($main_content, "load_module('oferta-academica')");
$main_page_pos = strpos($main_content, "load_module('main-page')");

loader_assert($core_pos !== false, "flacso-uruguay.php debe contener load_module('core')");
loader_assert($consultas_pos !== false, "flacso-uruguay.php debe contener load_module('consultas')");
loader_assert($consultas_pos > $core_pos, "load_module('consultas') debe invocarse después de load_module('core')");
if ($oferta_pos !== false) {
    loader_assert($consultas_pos < $oferta_pos, "load_module('consultas') debe invocarse antes de load_module('oferta-academica')");
}
if ($main_page_pos !== false) {
    loader_assert($consultas_pos < $main_page_pos, "load_module('consultas') debe invocarse antes de load_module('main-page')");
}

// =========================================================================
// 3. Simular carga de modules/consultas/init.php en proceso aislado con helpers (flacso_safe_require)
// =========================================================================
$isolated_script_with_helpers = sprintf(
    'php -r "%s if (!function_exists(\'add_action\')) { function add_action(\$t, \$c = null, \$p = 10, \$a = 1) { return true; } } define(\'ABSPATH\', \'%s/\'); define(\'FLACSO_URUGUAY_VERSION\', \'7.0.0\'); define(\'FLACSO_URUGUAY_PATH\', \'%s/\'); require_once \'%s/includes/core/helpers.php\'; require_once \'%s/modules/consultas/init.php\'; ' .
    '\$ok = class_exists(\'FLACSO_DB\') && ' .
    'class_exists(\'FLACSO_Base_Inquiry_Repository\') && ' .
    'class_exists(\'FLACSO_Offer_Inquiry_Repository\') && ' .
    'class_exists(\'FLACSO_Seminar_Inquiry_Repository\') && ' .
    'class_exists(\'FLACSO_Mailjet_Client\') && ' .
    'class_exists(\'FLACSO_Offer_Inquiry_Service\') && ' .
    'class_exists(\'FLACSO_Seminar_Inquiry_Service\') && ' .
    'defined(\'FLACSO_CONSULTAS_MODULE_PATH\') && ' .
    'defined(\'FLACSO_CONSULTAS_MODULE_VERSION\'); ' .
    'exit(\$ok ? 0 : 3);"',
    '',
    addslashes($root),
    addslashes($root),
    addslashes($root),
    addslashes($root)
);

exec($isolated_script_with_helpers, $out_helpers, $code_helpers);
loader_assert($code_helpers === 0, "Carga aislada con flacso_safe_require debe retornar 0 y definir todas las clases y constantes (código recibido: {$code_helpers})");

// =========================================================================
// 4. Simular carga de modules/consultas/init.php en proceso aislado sin flacso_safe_require (fallback require_once)
// =========================================================================
$isolated_script_without_helpers = sprintf(
    'php -r "%s define(\'ABSPATH\', \'%s/\'); define(\'FLACSO_URUGUAY_VERSION\', \'7.0.0\'); define(\'FLACSO_URUGUAY_PATH\', \'%s/\'); require_once \'%s/modules/consultas/init.php\'; ' .
    '\$ok = class_exists(\'FLACSO_DB\') && ' .
    'class_exists(\'FLACSO_Base_Inquiry_Repository\') && ' .
    'class_exists(\'FLACSO_Offer_Inquiry_Repository\') && ' .
    'class_exists(\'FLACSO_Seminar_Inquiry_Repository\') && ' .
    'class_exists(\'FLACSO_Mailjet_Client\') && ' .
    'class_exists(\'FLACSO_Offer_Inquiry_Service\') && ' .
    'class_exists(\'FLACSO_Seminar_Inquiry_Service\') && ' .
    'defined(\'FLACSO_CONSULTAS_MODULE_PATH\') && ' .
    'defined(\'FLACSO_CONSULTAS_MODULE_VERSION\'); ' .
    'exit(\$ok ? 0 : 4);"',
    '',
    addslashes($root),
    addslashes($root),
    addslashes($root)
);

exec($isolated_script_without_helpers, $out_no_helpers, $code_no_helpers);
loader_assert($code_no_helpers === 0, "Carga aislada sin flacso_safe_require debe retornar 0 y definir todas las clases y constantes vía fallback require_once (código recibido: {$code_no_helpers})");

// =========================================================================
// 5. Carga directa en proceso actual
// =========================================================================
if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}
if (!defined('FLACSO_URUGUAY_VERSION')) {
    define('FLACSO_URUGUAY_VERSION', '7.0.0');
}
if (!defined('FLACSO_URUGUAY_PATH')) {
    define('FLACSO_URUGUAY_PATH', $root . '/');
}

require_once $init_file;

loader_assert(class_exists('FLACSO_DB'), "Clase FLACSO_DB debe existir tras cargar init.php");
loader_assert(class_exists('FLACSO_Base_Inquiry_Repository'), "Clase FLACSO_Base_Inquiry_Repository debe existir tras cargar init.php");
loader_assert(class_exists('FLACSO_Offer_Inquiry_Repository'), "Clase FLACSO_Offer_Inquiry_Repository debe existir tras cargar init.php");
loader_assert(class_exists('FLACSO_Seminar_Inquiry_Repository'), "Clase FLACSO_Seminar_Inquiry_Repository debe existir tras cargar init.php");
loader_assert(class_exists('FLACSO_Mailjet_Client'), "Clase FLACSO_Mailjet_Client debe existir tras cargar init.php");
loader_assert(class_exists('FLACSO_Offer_Inquiry_Service'), "Clase FLACSO_Offer_Inquiry_Service debe existir tras cargar init.php");
loader_assert(class_exists('FLACSO_Seminar_Inquiry_Service'), "Clase FLACSO_Seminar_Inquiry_Service debe existir tras cargar init.php");
loader_assert(defined('FLACSO_CONSULTAS_MODULE_PATH'), "Constante FLACSO_CONSULTAS_MODULE_PATH debe estar definida");
loader_assert(defined('FLACSO_CONSULTAS_MODULE_VERSION'), "Constante FLACSO_CONSULTAS_MODULE_VERSION debe estar definida");

echo "OK inquiry-plugin-loader-test\n";
