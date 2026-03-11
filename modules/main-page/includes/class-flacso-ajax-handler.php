<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manejador AJAX simplificado para guardar secciones FLACSO
 */
class Flacso_AJAX_Handler {
    
    public static function init(): void {
        add_action('wp_ajax_flacso_save_section', [__CLASS__, 'save_section']);
    }

    public static function save_section(): void {
        try {
            // Validar nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'flacso-settings-ajax')) {
                wp_send_json_error(['message' => __('Token de seguridad inválido.', 'flacso-main-page')]);
            }

            // Validar permisos
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('No tienes permisos para hacer esto.', 'flacso-main-page')]);
            }

            // Obtener datos
            $section = isset($_POST['section']) ? sanitize_text_field($_POST['section']) : '';
            
            if (empty($section)) {
                wp_send_json_error(['message' => __('Sección no especificada.', 'flacso-main-page')]);
            }

            // Compilar datos de la sección desde el array principal (hero, posgrados, etc.)
            $data = $_POST[$section] ?? [];
            if (!is_array($data)) {
                $data = [];
            } else {
                $data = self::sanitize_array($data);
            }

            // Obtener configuración actual
            $current_settings = Flacso_Main_Page_Settings::get_settings();

            // Inicializar sección si no existe
            if (!isset($current_settings[$section])) {
                $current_settings[$section] = [];
            }

            // Actualizar solo los campos nuevos/modificados
            $current_settings[$section] = array_merge(
                $current_settings[$section],
                $data
            );

            // Guardar en BD
            $saved = update_option(
                Flacso_Main_Page_Settings::OPTION_KEY,
                $current_settings
            );

            if ($saved || $current_settings === get_option(Flacso_Main_Page_Settings::OPTION_KEY)) {
                wp_cache_delete(Flacso_Main_Page_Settings::OPTION_KEY, 'options');
                
                wp_send_json_success([
                    'message' => sprintf(
                        __('%s guardado correctamente.', 'flacso-main-page'),
                        ucfirst(str_replace('_', ' ', $section))
                    ),
                    'section' => $section,
                ]);
            } else {
                wp_send_json_error(['message' => __('Error al guardar los datos en la base de datos.', 'flacso-main-page')]);
            }

        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }

        wp_die();
    }

    private static function sanitize_field($value): string {
        if (is_array($value)) {
            return '';
        }
        
        // Detectar tipo de campo por el valor
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return esc_url_raw($value);
        }
        
        if ($value === '0' || $value === '1' || $value === 'on' || $value === 'off') {
            return $value;
        }
        
        return sanitize_text_field($value);
    }

    private static function sanitize_array(array $arr): array {
        $result = [];
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::sanitize_array($value);
            } else {
                $result[$key] = self::sanitize_field($value);
            }
        }
        return $result;
    }
}
