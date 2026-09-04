<?php
/**
 * Contrato del ruteo de Solicitudes de Información hacia Editor FLACSO.
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
editor_routing_assert(strpos($routing, 'https://editor.flacso.edu.uy') !== false, 'debe existir el Editor canónico como fallback');
editor_routing_assert(strpos($routing, "'/api/consultas'") !== false, 'el destino canónico debe ser /api/consultas');
editor_routing_assert(strpos($routing, "get_option('fc_oferta_webhook_url'") !== false, 'debe respetar la opción de solicitudes de oferta');
editor_routing_assert(strpos($routing, "get_option('fc_consultas_webhook_url'") !== false, 'debe completar la opción de consultas generales');

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

// Sin configuración histórica: debe restaurar automáticamente el camino a Editor.
fc_ensure_info_request_editor_routes();
editor_routing_assert(
    get_option('fc_oferta_webhook_url') === 'https://editor.flacso.edu.uy/api/consultas',
    'Solicitud de Información debe apuntar a Editor cuando la opción histórica está vacía'
);
editor_routing_assert(
    get_option('fc_consultas_webhook_url') === 'https://editor.flacso.edu.uy/api/consultas',
    'consultas generales deben partir del endpoint canónico; su handler agrega /general'
);

// Un Editor explícito debe usarse como base sin depender del dominio de producción.
$GLOBALS['editor_routing_options'] = [
    'flacso_external_editor_url' => 'https://editor-ejemplo.test/',
];
$GLOBALS['editor_routing_updates'] = [];
fc_ensure_info_request_editor_routes();
editor_routing_assert(
    get_option('fc_oferta_webhook_url') === 'https://editor-ejemplo.test/api/consultas',
    'debe construir /api/consultas desde flacso_external_editor_url'
);

// Overrides explícitos nunca se pisan.
$GLOBALS['editor_routing_options'] = [
    'flacso_external_editor_url' => 'https://editor.flacso.edu.uy',
    'fc_oferta_webhook_url' => 'https://override.example/api/consultas',
    'fc_consultas_webhook_url' => 'https://override.example/api/consultas',
];
$GLOBALS['editor_routing_updates'] = [];
fc_ensure_info_request_editor_routes();
editor_routing_assert(empty($GLOBALS['editor_routing_updates']), 'no debe sobreescribir endpoints configurados explícitamente');
editor_routing_assert(get_option('fc_oferta_webhook_url') === 'https://override.example/api/consultas', 'debe preservar override de oferta');

// Si flacso_external_editor_url ya contiene el endpoint, no debe duplicarlo.
$GLOBALS['editor_routing_options'] = [
    'flacso_external_editor_url' => 'https://editor.flacso.edu.uy/api/consultas/',
];
$GLOBALS['editor_routing_updates'] = [];
fc_ensure_info_request_editor_routes();
editor_routing_assert(
    get_option('fc_oferta_webhook_url') === 'https://editor.flacso.edu.uy/api/consultas',
    'no debe generar /api/consultas/api/consultas'
);

echo "OK info-request editor routing test\n";
