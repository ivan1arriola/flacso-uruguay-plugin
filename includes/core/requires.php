<?php
/**
 * Utilidades de carga explícita.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('flacso_require')) {
    /**
     * Carga obligatoria. Cualquier falla se propaga al Module Loader para que
     * el módulo no quede marcado como cargado parcialmente.
     */
    function flacso_require(string $relative_path): void {
        $relative_path = ltrim($relative_path, '/');
        $full_path = FLACSO_URUGUAY_PATH . $relative_path;
        if (!file_exists($full_path)) {
            throw new RuntimeException('Archivo obligatorio no encontrado: ' . $relative_path);
        }
        require_once $full_path;
    }
}

if (!function_exists('flacso_optional_require')) {
    /**
     * Carga opcional. Registra la falla y permite continuar.
     */
    function flacso_optional_require(string $relative_path): bool {
        $relative_path = ltrim($relative_path, '/');
        $full_path = FLACSO_URUGUAY_PATH . $relative_path;
        if (!file_exists($full_path)) {
            error_log('[FLACSO] Archivo opcional no encontrado: ' . $relative_path);
            return false;
        }
        try {
            require_once $full_path;
            return true;
        } catch (Throwable $e) {
            error_log('[FLACSO] Error en archivo opcional ' . $relative_path . ': ' . $e->getMessage());
            return false;
        }
    }
}
