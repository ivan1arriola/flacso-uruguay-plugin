<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Compatibilidad AJAX del administrador clásico.
 *
 * El endpoint REST y este endpoint AJAX comparten ahora exactamente el mismo
 * saneamiento y modelo de settings.
 */
final class Flacso_AJAX_Settings {
    public static function init(): void {
        add_action('wp_ajax_flacso_save_settings_section', [__CLASS__, 'save_settings_section']);
    }

    public static function save_settings_section(): void {
        check_ajax_referer('flacso-settings-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No tienes permisos para hacer esto.', 'flacso-main-page')]);
        }

        $section = isset($_POST['section']) ? sanitize_key((string) wp_unslash($_POST['section'])) : '';
        $data = isset($_POST['data']) && is_array($_POST['data'])
            ? wp_unslash($_POST['data'])
            : [];

        if ($section === '') {
            wp_send_json_error(['message' => __('Sección no especificada.', 'flacso-main-page')]);
        }

        $current = Flacso_Main_Page_Settings::get_settings();

        if ($section === 'secciones') {
            foreach (['sections_visibility', 'sections_order', 'section_heading_color', 'section_heading_colors'] as $root_key) {
                if (array_key_exists($root_key, $data)) {
                    $current[$root_key] = $data[$root_key];
                }
            }
        } else {
            if (class_exists('Flacso_Main_Page_Section_Keys')) {
                $section = Flacso_Main_Page_Section_Keys::canonicalize($section);
                if (Flacso_Main_Page_Section_Keys::is_retired($section)) {
                    wp_send_json_error(['message' => __('La sección fue retirada de la portada.', 'flacso-main-page')]);
                }
            }

            $existing = isset($current[$section]) && is_array($current[$section])
                ? $current[$section]
                : [];
            $current[$section] = array_replace_recursive($existing, $data);
        }

        $sanitized = Flacso_Main_Page_Settings::sanitize($current);
        $saved = update_option(Flacso_Main_Page_Settings::OPTION_KEY, $sanitized, false);

        if ($saved || $sanitized === get_option(Flacso_Main_Page_Settings::OPTION_KEY)) {
            wp_cache_delete(Flacso_Main_Page_Settings::OPTION_KEY, 'options');
            Flacso_Main_Page_Settings::invalidate_cache();
            wp_send_json_success([
                'message' => sprintf(
                    __('%s guardado exitosamente.', 'flacso-main-page'),
                    Flacso_Main_Page_Settings::get_section_label($section)
                ),
                'section' => $section,
                'timestamp' => current_time('mysql'),
            ]);
        }

        wp_send_json_error(['message' => __('Error al guardar los datos.', 'flacso-main-page')]);
    }
}
