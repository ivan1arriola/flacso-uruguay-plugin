<?php
/**
 * Funciones helper compartidas para todos los módulos
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// Funciones generales
// ============================================

/**
 * Obtener versión de activo para cache busting
 */
if (!function_exists('flacso_asset_version')) {
    function flacso_asset_version(string $relative_path): string {
        $absolute_path = FLACSO_URUGUAY_PATH . ltrim($relative_path, '/');
        if (file_exists($absolute_path)) {
            return (string) filemtime($absolute_path);
        }
        return FLACSO_URUGUAY_VERSION;
    }
}

/**
 * Cargar archivo de forma segura
 */
if (!function_exists('flacso_safe_require')) {
    function flacso_safe_require(string $path): bool {
        $path = ltrim($path, '/');
        $full_path = FLACSO_URUGUAY_PATH . $path;
        
        if (!file_exists($full_path)) {
            error_log("[FLACSO] Archivo no encontrado: $path");
            return false;
        }
        
        try {
            require_once $full_path;
            return true;
        } catch (Throwable $e) {
            error_log("[FLACSO] Error al cargar $path: " . $e->getMessage());
            return false;
        }
    }
}

// ============================================
// Funciones para docentes
// ============================================

if (!function_exists('dp_docentes_asset_version')) {
    function dp_docentes_asset_version(string $relative_path): string {
        return flacso_asset_version($relative_path);
    }
}

if (!function_exists('dp_is_docentes_view')) {
    function dp_is_docentes_view() {
        return is_post_type_archive('docente')
            || is_singular('docente');
    }
}

if (!function_exists('dp_nombre_completo')) {
    function dp_nombre_completo(int $post_id, bool $with_complete_prefix = false): string {
        $prefijo_abrev = get_post_meta($post_id, 'prefijo_abrev', true);
        $titulo = get_post_meta($post_id, 'titulo', true);
        $nombre = get_post_meta($post_id, 'nombre', true);
        $apellido = get_post_meta($post_id, 'apellido', true);

        if (!$nombre && !$apellido) {
            return (string) get_the_title($post_id);
        }

        $prefijo = $with_complete_prefix ? $titulo : $prefijo_abrev;
        $partes = array_filter([$prefijo, $nombre, $apellido]);
        return implode(' ', $partes);
    }
}

// ============================================
// Funciones para seminarios
// ============================================

if (!function_exists('flacso_seminario_asset_version')) {
    function flacso_seminario_asset_version(string $relative_path): string {
        return flacso_asset_version($relative_path);
    }
}

// ============================================
// Funciones para posgrados
// ============================================

if (!function_exists('flacso_posgrados_safe_require')) {
    function flacso_posgrados_safe_require(string $path): bool {
        return flacso_safe_require($path);
    }
}

if (!function_exists('flacso_posgrados_asset_version')) {
    function flacso_posgrados_asset_version(string $relative_path): string {
        return flacso_asset_version($relative_path);
    }
}
