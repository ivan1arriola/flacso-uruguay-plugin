<?php
/**
 * Cargador de módulos del plugin FLACSO Uruguay
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLACSO_Uruguay_Loader {
    
    private static $instance = null;
    private $loaded_modules = [];
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Cargar un módulo
     */
    public function load_module(string $module_name): bool {
        if (isset($this->loaded_modules[$module_name])) {
            return true;
        }
        
        $module_path = FLACSO_URUGUAY_PATH . "modules/{$module_name}";
        
        if (!is_dir($module_path)) {
            error_log("[FLACSO] Módulo no encontrado: $module_name");
            return false;
        }
        
        // Buscar archivo de inicialización del módulo
        $init_file = $module_path . '/init.php';
        
        if (!file_exists($init_file)) {
            error_log("[FLACSO] No se encontró init.php en: $module_path");
            return false;
        }
        
        try {
            require_once $init_file;
            $this->loaded_modules[$module_name] = true;
            return true;
        } catch (Throwable $e) {
            error_log("[FLACSO] Error cargando módulo $module_name: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener módulos cargados
     */
    public function get_loaded_modules(): array {
        return array_keys($this->loaded_modules);
    }
    
    /**
     * Verificar si un módulo está cargado
     */
    public function is_module_loaded(string $module_name): bool {
        return isset($this->loaded_modules[$module_name]);
    }
}
