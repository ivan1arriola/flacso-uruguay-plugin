<?php
/**
 * Cargador de módulos del plugin FLACSO Uruguay.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLACSO_Uruguay_Loader {
    private static $instance = null;

    /** @var array<string,bool> */
    private $loaded_modules = [];

    /** @var array<string,array{message:string,required:bool}> */
    private $failed_modules = [];

    /** @var array<string,bool> */
    private $loading_modules = [];

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Compatibilidad con el API anterior. */
    public function load_module(string $module_name): bool {
        return $this->load_module_definition($module_name, [
            'path' => $module_name,
            'depends' => [],
            'optional_depends' => [],
            'required' => false,
            'legacy' => false,
        ], []);
    }

    public function load_registered_modules(array $definitions): bool {
        $all_required_loaded = true;

        foreach ($definitions as $module_key => $definition) {
            if (!$this->load_module_definition((string) $module_key, (array) $definition, $definitions)
                && !empty($definition['required'])) {
                $all_required_loaded = false;
            }
        }

        if (!empty($this->failed_modules)) {
            add_action('admin_notices', [$this, 'render_failure_notice']);
        }

        return $all_required_loaded;
    }

    private function load_module_definition(string $module_key, array $definition, array $definitions): bool {
        if (isset($this->loaded_modules[$module_key])) {
            return true;
        }
        if (isset($this->failed_modules[$module_key])) {
            return false;
        }
        if (isset($this->loading_modules[$module_key])) {
            return $this->fail(
                $module_key,
                sprintf('Dependencia circular detectada al cargar "%s".', $module_key),
                !empty($definition['required'])
            );
        }

        $this->loading_modules[$module_key] = true;

        foreach ($this->dependency_list($definition, 'depends') as $dependency_key) {
            if (!isset($definitions[$dependency_key])) {
                unset($this->loading_modules[$module_key]);
                return $this->fail(
                    $module_key,
                    sprintf('El módulo "%s" declara una dependencia inexistente: "%s".', $module_key, $dependency_key),
                    !empty($definition['required'])
                );
            }

            if (!$this->load_module_definition($dependency_key, (array) $definitions[$dependency_key], $definitions)) {
                unset($this->loading_modules[$module_key]);
                return $this->fail(
                    $module_key,
                    sprintf('No se pudo cargar la dependencia "%s" requerida por "%s".', $dependency_key, $module_key),
                    !empty($definition['required'])
                );
            }
        }

        // Una dependencia opcional se intenta cargar para que pueda registrar
        // hooks/adaptadores, pero nunca bloquea el módulo consumidor.
        foreach ($this->dependency_list($definition, 'optional_depends') as $dependency_key) {
            if (!isset($definitions[$dependency_key])) {
                error_log(sprintf('[FLACSO] Dependencia opcional inexistente "%s" declarada por "%s".', $dependency_key, $module_key));
                continue;
            }
            $this->load_module_definition($dependency_key, (array) $definitions[$dependency_key], $definitions);
        }

        $module_path_key = isset($definition['path']) && is_string($definition['path']) && $definition['path'] !== ''
            ? $definition['path']
            : $module_key;
        $module_path = FLACSO_URUGUAY_PATH . 'modules/' . trim($module_path_key, '/');
        $init_file = $module_path . '/init.php';
        $required = !empty($definition['required']);

        if (!is_dir($module_path)) {
            unset($this->loading_modules[$module_key]);
            return $this->fail($module_key, "Módulo no encontrado: {$module_path_key}", $required);
        }
        if (!file_exists($init_file)) {
            unset($this->loading_modules[$module_key]);
            return $this->fail($module_key, "No se encontró init.php en: {$module_path}", $required);
        }

        try {
            require_once $init_file;
            unset($this->loading_modules[$module_key]);
            $this->loaded_modules[$module_key] = true;
            do_action('flacso_module_loaded', $module_key, $definition);
            return true;
        } catch (Throwable $e) {
            unset($this->loading_modules[$module_key]);
            return $this->fail(
                $module_key,
                sprintf('Error cargando módulo %s: %s', $module_key, $e->getMessage()),
                $required
            );
        }
    }

    private function dependency_list(array $definition, string $key): array {
        if (!isset($definition[$key]) || !is_array($definition[$key])) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('strval', $definition[$key]))));
    }

    private function fail(string $module_key, string $message, bool $required): bool {
        $this->failed_modules[$module_key] = [
            'message' => $message,
            'required' => $required,
        ];
        error_log('[FLACSO] ' . $message);
        do_action('flacso_module_load_failed', $module_key, $message, $required);
        return false;
    }

    public function render_failure_notice(): void {
        if (!current_user_can('manage_options') || empty($this->failed_modules)) {
            return;
        }

        $required_failures = array_filter(
            $this->failed_modules,
            static fn(array $failure): bool => !empty($failure['required'])
        );
        $class = !empty($required_failures) ? 'notice notice-error' : 'notice notice-warning';

        echo '<div class="' . esc_attr($class) . '"><p><strong>';
        echo esc_html__('FLACSO Uruguay: problemas al cargar módulos', 'flacso-uruguay');
        echo '</strong></p><ul style="list-style:disc;padding-left:1.5rem">';
        foreach ($this->failed_modules as $module_key => $failure) {
            printf(
                '<li><code>%s</code>: %s%s</li>',
                esc_html($module_key),
                esc_html($failure['message']),
                !empty($failure['required']) ? ' <strong>(' . esc_html__('obligatorio', 'flacso-uruguay') . ')</strong>' : ''
            );
        }
        echo '</ul></div>';
    }

    public function get_loaded_modules(): array {
        return array_keys($this->loaded_modules);
    }

    public function get_failed_modules(): array {
        return $this->failed_modules;
    }

    public function is_module_loaded(string $module_name): bool {
        return isset($this->loaded_modules[$module_name]);
    }
}
