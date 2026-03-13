<?php

if (!defined('ABSPATH')) {
    exit;
}

class Flacso_AJAX_Settings {
    public static function init(): void {
        add_action('wp_ajax_flacso_save_settings_section', [__CLASS__, 'save_settings_section']);
    }

    public static function save_settings_section(): void {
        // Verificar nonce
        check_ajax_referer('flacso-settings-nonce', 'nonce');

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No tienes permisos para hacer esto.', 'flacso-main-page')]);
        }

        $section = isset($_POST['section']) ? sanitize_text_field($_POST['section']) : '';
        $data = isset($_POST['data']) ? (array) $_POST['data'] : [];

        if (empty($section)) {
            wp_send_json_error(['message' => __('Sección no especificada.', 'flacso-main-page')]);
        }

        // Sanitizar datos según el tipo de sección
        $sanitized_data = self::sanitize_section_data($section, $data);

        // Obtener configuración actual
        $current_settings = Flacso_Main_Page_Settings::get_settings();

        // Actualizar solo la sección especificada
        if (!isset($current_settings[$section])) {
            $current_settings[$section] = [];
        }

        // Hacer un merge profundo de los datos
        if ($section === 'secciones') {
            foreach (['sections_visibility', 'sections_order', 'section_heading_color', 'section_heading_colors'] as $root_key) {
                if (isset($sanitized_data[$root_key])) {
                    $current_settings[$root_key] = $sanitized_data[$root_key];
                }
            }
        } else {
            if (!isset($current_settings[$section])) {
                $current_settings[$section] = [];
            }
            $current_settings[$section] = array_merge(
                $current_settings[$section],
                $sanitized_data
            );
        }

        // Guardar en base de datos
        $saved = update_option(
            Flacso_Main_Page_Settings::OPTION_KEY,
            $current_settings
        );

        if ($saved || $current_settings === get_option(Flacso_Main_Page_Settings::OPTION_KEY)) {
            // Limpiar cache si existe
            wp_cache_delete(Flacso_Main_Page_Settings::OPTION_KEY, 'options');
            // Invalidar el cache estático de la clase
            Flacso_Main_Page_Settings::invalidate_cache();

            wp_send_json_success([
                'message' => sprintf(
                    __('%s guardado exitosamente.', 'flacso-main-page'),
                    ucfirst(str_replace('_', ' ', $section))
                ),
                'section' => $section,
                'timestamp' => current_time('mysql'),
            ]);
        } else {
            wp_send_json_error(['message' => __('Error al guardar los datos.', 'flacso-main-page')]);
        }
    }

    private static function sanitize_section_data(string $section, array $data): array {
        $sanitized = [];

        switch ($section) {
            case 'hero':
                $sanitized = self::sanitize_hero($data);
                break;
            case 'eventos':
                $sanitized = self::sanitize_eventos($data);
                break;
            case 'secciones':
                $sanitized = self::sanitize_secciones($data);
                break;
            case 'novedades':
                $sanitized = self::sanitize_novedades($data);
                break;
            case 'posgrados':
                $sanitized = self::sanitize_posgrados($data);
                break;
            case 'oferta_academica':
                $sanitized = self::sanitize_oferta_academica($data);
                break;
            case 'congreso':
                $sanitized = self::sanitize_congreso($data);
                break;
            case 'quienes':
                $sanitized = self::sanitize_quienes($data);
                break;
            case 'contacto':
                $sanitized = self::sanitize_contacto($data);
                break;
            default:
                $sanitized = $data; // Fallback sin sanitización específica
        }

        return $sanitized;
    }

    private static function sanitize_hero(array $data): array {
        $sanitized = [];

        if (isset($data['title'])) {
            $sanitized['title'] = sanitize_text_field($data['title']);
        }

        if (isset($data['subtitle'])) {
            $sanitized['subtitle'] = sanitize_text_field($data['subtitle']);
        }

        if (isset($data['background_image'])) {
            $sanitized['background_image'] = esc_url($data['background_image']);
        }

        if (isset($data['show_buttons'])) {
            $sanitized['show_buttons'] = (bool) $data['show_buttons'];
        }

        if (isset($data['buttons']) && is_array($data['buttons'])) {
            $default_buttons = Flacso_Main_Page_Settings::get_hero_button_defaults();
            $sanitized_buttons = [];

            foreach ($data['buttons'] as $index => $button_data) {
                if (!is_array($button_data)) {
                    continue;
                }

                $sanitized_buttons[$index] = [
                    'label' => sanitize_text_field($button_data['label'] ?? ($default_buttons[$index]['label'] ?? '')),
                    'url' => esc_url_raw($button_data['url'] ?? ''),
                    'style' => sanitize_text_field($button_data['style'] ?? ($default_buttons[$index]['style'] ?? 'primary')),
                    'enabled' => !empty($button_data['enabled']),
                ];
            }

            if (!empty($sanitized_buttons)) {
                $sanitized['buttons'] = $sanitized_buttons;
            }
        }

        if (isset($data['bubble_primary_label'])) {
            $sanitized['bubble_primary_label'] = sanitize_text_field($data['bubble_primary_label']);
        }

        if (isset($data['bubble_primary_url'])) {
            $sanitized['bubble_primary_url'] = esc_url($data['bubble_primary_url']);
        }

        if (isset($data['bubble_primary_enabled'])) {
            $sanitized['bubble_primary_enabled'] = (bool) $data['bubble_primary_enabled'];
        }

        if (isset($data['bubble_secondary_label'])) {
            $sanitized['bubble_secondary_label'] = sanitize_text_field($data['bubble_secondary_label']);
        }

        if (isset($data['bubble_secondary_url'])) {
            $sanitized['bubble_secondary_url'] = esc_url($data['bubble_secondary_url']);
        }

        if (isset($data['bubble_secondary_enabled'])) {
            $sanitized['bubble_secondary_enabled'] = (bool) $data['bubble_secondary_enabled'];
        }

        return $sanitized;
    }

    private static function sanitize_eventos(array $data): array {
        // Placeholder para futuras sanitizaciones de eventos
        return $data;
    }

    private static function sanitize_secciones(array $data): array {
        $output = [];

        if (isset($data['sections_visibility']) && is_array($data['sections_visibility'])) {
            $visibility = [];
            foreach (Flacso_Main_Page_Settings::get_section_visibility_defaults() as $key => $default_state) {
                $visibility[$key] = !empty($data['sections_visibility'][$key]);
            }
            $output['sections_visibility'] = $visibility;
        }

        if (isset($data['sections_order']) && is_array($data['sections_order'])) {
            $order = Flacso_Main_Page_Settings::sanitize_homepage_section_order($data['sections_order']);
            $output['sections_order'] = $order;
        }

        if (isset($data['section_heading_color'])) {
            $choice = sanitize_key($data['section_heading_color']);
            $choices = ['primary', 'palette7'];
            $output['section_heading_color'] = in_array($choice, $choices, true) ? $choice : 'primary';
        }

        if (isset($data['section_heading_colors']) && is_array($data['section_heading_colors'])) {
            $choices = ['primary', 'palette7'];
            $colors = [];
            foreach (Flacso_Main_Page_Settings::get_homepage_section_order_defaults() as $section_key) {
                if (!isset($data['section_heading_colors'][$section_key])) {
                    continue;
                }
                $choice = sanitize_key($data['section_heading_colors'][$section_key]);
                if (!in_array($choice, $choices, true)) {
                    continue;
                }
                $colors[$section_key] = $choice;
            }
            if (!empty($colors)) {
                $output['section_heading_colors'] = $colors;
            }
        }

        return $output;
    }

    private static function sanitize_novedades(array $data): array {
        $defaults = Flacso_Main_Page_Settings::get_defaults();
        $default_legacy = self::sanitize_bounded_absint($defaults['novedades']['per_page'] ?? 12, 12, 3, 48);

        $legacy_source = $data['per_page'] ?? ($data['per_page_desktop'] ?? $default_legacy);
        $legacy = self::sanitize_bounded_absint($legacy_source, $default_legacy, 3, 48);
        $desktop = self::sanitize_bounded_absint($data['per_page_desktop'] ?? $legacy, $legacy, 3, 48);
        $mobile = self::sanitize_bounded_absint($data['per_page_mobile'] ?? $legacy, $legacy, 3, 48);

        return [
            // Compatibilidad con configuraciones anteriores.
            'per_page' => $desktop,
            'per_page_desktop' => $desktop,
            'per_page_mobile' => $mobile,
        ];
    }

    private static function sanitize_posgrados(array $data): array {
        // Placeholder para futuras sanitizaciones de posgrados
        return $data;
    }

    private static function sanitize_oferta_academica(array $data): array {
        // Placeholder para futuras sanitizaciones de oferta académica
        return $data;
    }

    private static function sanitize_congreso(array $data): array {
        // Placeholder para futuras sanitizaciones de congreso
        return $data;
    }

    private static function sanitize_quienes(array $data): array {
        // Placeholder para futuras sanitizaciones de quiénes somos
        return $data;
    }

    private static function sanitize_contacto(array $data): array {
        // Placeholder para futuras sanitizaciones de contacto
        return $data;
    }

    private static function sanitize_bounded_absint($value, int $fallback, int $min, int $max): int {
        $parsed = absint($value);
        if ($parsed <= 0) {
            $parsed = $fallback;
        }
        if ($parsed < $min) {
            $parsed = $min;
        }
        if ($parsed > $max) {
            $parsed = $max;
        }

        return $parsed;
    }
}
