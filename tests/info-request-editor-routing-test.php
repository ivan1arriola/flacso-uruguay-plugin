<?php
/**
 * Contrato de compatibilidad del ruteo externo.
 *
 * Las consultas de ofertas ya son internas. Únicamente la consulta general
 * conserva por ahora el endpoint del Editor.
 */

$root = dirname(__DIR__);
$routing_file = $root . '/modules/formularios/includes/editor-routing.php';
$init_file = $root . '/modules/formularios/init.php';

function editor_routing_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

editor_routing_assert(file_exists($routing_file), 'debe existir editor-routing.php');
$routing = (string) file_get_contents($routing_file);
$init = (string) file_get_contents($init_file);

editor_routing_assert(strpos($init, "modules/formularios/includes/editor-routing.php") !== false, 'formularios/init.php debe cargar editor-routing.php');
editor_routing_assert(strpos($routing, 'https://editor.flacso.edu.uy') !== false, 'la consulta general conserva el Editor canónico como fallback');
editor_routing_assert(strpos($routing, "'/api/consultas'") !== false, 'el destino canónico general debe partir de /api/consultas');
editor_routing_assert(strpos($routing, "get_option('fc_consultas_webhook_url'") !== false, 'debe completar la opción de consultas generales');
editor_routing_assert(strpos($routing, "get_option('fc_oferta_webhook_url'") === false, 'el ruteo no debe leer el webhook de ofertas');
editor_routing_assert(strpos($routing, "update_option('fc_oferta_webhook_url'") === false, 'el ruteo no debe escribir el webhook de ofertas');
editor_routing_assert(strpos($routing, 'editor-flacso-uy.vercel.app') !== false, 'debe reconocer el host Vercel legado para la consulta general');

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

$GLOBALS['editor_routing_options'] = [];
$GLOBALS['editor_routing_updates'] = [];
$GLOBALS['editor_routing_actions'] = [];

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return array_key_exists($key, $GLOBALS['editor_routing_options'])
            ? $GLOBALS['editor_routing_options'][$key]
            : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value, $autoload = null) {
        $GLOBALS['editor_routing_options'][$key] = $value;
        $GLOBALS['editor_routing_updates'][$key] = $value;
        return true;
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return trim((string) $url);
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['editor_routing_actions'][] = [$hook, $callback, $priority, $accepted_args];
        return true;
    }
}

require_once $routing_file;

// Sin configuración: solo debe crear la ruta de consulta general.
fc_ensure_info_request_editor_routes();
editor_routing_assert(
    !array_key_exists('fc_oferta_webhook_url', $GLOBALS['editor_routing_options']),
    'no debe recrear fc_oferta_webhook_url'
);
editor_routing_assert(
    get_option('fc_consultas_webhook_url') === 'https://editor.flacso.edu.uy/api/consultas',
    'consultas generales deben partir del endpoint canónico; su handler agrega /general'
);

// Un Editor explícito no legado solo afecta la consulta general.
$GLOBALS['editor_routing_options'] = [
    'flacso_external_editor_url' => 'https://editor-ejemplo.test/',
    'fc_oferta_webhook_url' => 'https://obsolete.example/api/consultas',
];
$GLOBALS['editor_routing_updates'] = [];
fc_ensure_info_request_editor_routes();
editor_routing_assert(
    get_option('fc_consultas_webhook_url') === 'https://editor-ejemplo.test/api/consultas',
    'debe construir el endpoint general desde un Editor explícito'
);
editor_routing_assert(
    get_option('fc_oferta_webhook_url') === 'https://obsolete.example/api/consultas',
    'no debe modificar una opción legacy de oferta'
);
editor_routing_assert(
    !isset($GLOBALS['editor_routing_updates']['fc_oferta_webhook_url']),
    'fc_oferta_webhook_url no puede formar parte de las actualizaciones'
);

// Vercel histórico se repara únicamente para la consulta general.
$GLOBALS['editor_routing_options'] = [
    'flacso_external_editor_url' => 'https://editor.flacso.edu.uy',
    'fc_oferta_webhook_url' => 'https://editor-flacso-uy.vercel.app/api/consultas',
    'fc_consultas_webhook_url' => 'https://editor-flacso-uy.vercel.app/api/consultas',
];
$GLOBALS['editor_routing_updates'] = [];
fc_ensure_info_request_editor_routes();
editor_routing_assert(
    get_option('fc_consultas_webhook_url') === 'https://editor.flacso.edu.uy/api/consultas',
    'debe reparar el endpoint general Vercel legado'
);
editor_routing_assert(
    get_option('fc_oferta_webhook_url') === 'https://editor-flacso-uy.vercel.app/api/consultas',
    'no debe tocar la opción legacy de ofertas'
);

// Overrides explícitos generales no se pisan.
$GLOBALS['editor_routing_options'] = [
    'flacso_external_editor_url' => 'https://editor.flacso.edu.uy',
    'fc_consultas_webhook_url' => 'https://override.example/api/consultas',
];
$GLOBALS['editor_routing_updates'] = [];
fc_ensure_info_request_editor_routes();
editor_routing_assert(empty($GLOBALS['editor_routing_updates']), 'no debe sobreescribir el endpoint general configurado explícitamente');

// Si la base ya contiene /api/consultas, no debe duplicarla.
$GLOBALS['editor_routing_options'] = [
    'flacso_external_editor_url' => 'https://editor.flacso.edu.uy/api/consultas/',
];
$GLOBALS['editor_routing_updates'] = [];
fc_ensure_info_request_editor_routes();
editor_routing_assert(
    get_option('fc_consultas_webhook_url') === 'https://editor.flacso.edu.uy/api/consultas',
    'no debe generar /api/consultas/api/consultas'
);

echo "OK general inquiry editor routing test\n";
