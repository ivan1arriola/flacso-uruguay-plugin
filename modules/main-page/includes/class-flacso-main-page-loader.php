<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Flacso_Main_Page_Loader {
    private static $components_loaded = false;

    public static function init(): void {
        $is_ajax_context = function_exists('wp_doing_ajax') && wp_doing_ajax();
        if (!is_admin() || $is_ajax_context || (defined('REST_REQUEST') && REST_REQUEST) || self::is_flacso_admin_request()) {
            self::load_components();
        }

        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 60);
        add_action('wp_head', [__CLASS__, 'render_front_page_meta_description'], 1);
    }

    public static function render_front_page_meta_description(): void {
        if (!is_front_page()) {
            return;
        }
        $description = apply_filters(
            'flacso_front_page_meta_description',
            __('Oferta académica, investigación, eventos y novedades de FLACSO Uruguay.', 'flacso-main-page')
        );
        $description = trim(wp_strip_all_tags((string) $description));
        if ($description !== '') {
            printf("<meta name=\"description\" content=\"%s\">\n", esc_attr($description));
        }
    }

    private static function is_flacso_admin_request(): bool {
        if (!is_admin() || !isset($_GET['page'])) {
            return false;
        }
        $page = sanitize_key((string) wp_unslash($_GET['page']));
        return $page !== '' && strpos($page, 'flacso-main-page') === 0;
    }

    public static function enqueue_assets(): void {
        if (!self::should_enqueue_assets()) {
            return;
        }

        $mobile_first_version = self::asset_version('assets/css/flacso-mobile-first.css');
        $base_css_version = self::asset_version('assets/css/flacso-main-page.css');
        $mobile_fixes_version = self::asset_version('assets/css/flacso-main-page-mobile-fixes.css');
        $react_js_version = self::asset_version('assets/js/flacso-main-page-react.js');
        $convenios_js_version = self::asset_version('assets/js/flacso-convenios-react.js');
        $theme_owns_layout = current_theme_supports('flacso-front-page-layout');

        wp_register_style(
            'flacso-main-page-fonts',
            'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap',
            [],
            null
        );
        wp_register_style(
            'flacso-mobile-first',
            FLACSO_MAIN_PAGE_MODULE_URL . 'assets/css/flacso-mobile-first.css',
            [],
            $mobile_first_version
        );
        wp_register_style(
            'flacso-main-page-base',
            FLACSO_MAIN_PAGE_MODULE_URL . 'assets/css/flacso-main-page.css',
            $theme_owns_layout ? [] : ['flacso-mobile-first'],
            $base_css_version
        );
        wp_register_style(
            'flacso-main-page-mobile-fixes',
            FLACSO_MAIN_PAGE_MODULE_URL . 'assets/css/flacso-main-page-mobile-fixes.css',
            ['flacso-main-page-base'],
            $mobile_fixes_version
        );

        $heading_color_choice = Flacso_Main_Page_Settings::get_section_heading_color_choice();
        $resolve_value = static function (string $choice): string {
            return $choice === 'palette7'
                ? 'var(--global-palette7, #ffffff)'
                : 'var(--global-palette1, #1d3a72)';
        };
        $inline_styles = [sprintf(':root { --flacso-section-heading-color: %s; }', $resolve_value($heading_color_choice))];
        foreach (Flacso_Main_Page_Settings::get_section_heading_colors() as $section_key => $choice) {
            if ($section_key === 'hero' || $choice === $heading_color_choice) {
                continue;
            }
            $inline_styles[] = sprintf(
                '.flacso-home-block--%1$s { --flacso-section-heading-color: %2$s; }',
                esc_attr($section_key),
                $resolve_value($choice)
            );
        }
        wp_add_inline_style('flacso-main-page-base', implode("\n", $inline_styles));

        wp_enqueue_style('flacso-main-page-fonts');
        if (!$theme_owns_layout) {
            wp_enqueue_style('flacso-mobile-first');
        }
        self::enqueue_bootstrap_style();
        wp_enqueue_style('flacso-main-page-base');
        wp_enqueue_style('flacso-main-page-mobile-fixes');
        self::enqueue_bootstrap_icons_style();

        wp_register_script(
            'flacso-main-page-react',
            FLACSO_MAIN_PAGE_MODULE_URL . 'assets/js/flacso-main-page-react.js',
            ['wp-element'],
            $react_js_version,
            true
        );
        wp_register_script(
            'flacso-convenios-react',
            FLACSO_MAIN_PAGE_MODULE_URL . 'assets/js/flacso-convenios-react.js',
            ['wp-element'],
            $convenios_js_version,
            true
        );
    }

    private static function asset_version(string $relative_path): string {
        $absolute_path = FLACSO_MAIN_PAGE_MODULE_PATH . ltrim($relative_path, '/');
        return file_exists($absolute_path) ? (string) filemtime($absolute_path) : (string) FLACSO_MAIN_PAGE_VERSION;
    }

    private static function should_enqueue_assets(): bool {
        if (is_admin()) {
            return false;
        }
        if (is_front_page()) {
            return true;
        }
        if (!is_singular()) {
            return false;
        }
        $post = get_post();
        if (!$post instanceof WP_Post) {
            return false;
        }

        $content = (string) $post->post_content;
        foreach ([
            'flacso_homepage_builder',
            'lista_seminarios',
            'listar_paginas',
            'preguntas_frecuentes',
            'convenios_responsivos',
            'listar_categoria',
            'oferta_academica',
            'Consultas_Fase_1',
        ] as $shortcode) {
            if (has_shortcode($content, $shortcode)) {
                return true;
            }
        }
        return function_exists('has_block') && strpos($content, 'wp:flacso-uruguay/') !== false;
    }

    private static function enqueue_bootstrap_style(): void {
        $handle = null;
        if (wp_style_is('bootstrap', 'registered') || wp_style_is('bootstrap', 'enqueued')) {
            $handle = 'bootstrap';
        } else {
            $handle = self::find_registered_style_handle_by_src_fragment('bootstrap@5')
                ?: self::find_registered_style_handle_by_src_fragment('/bootstrap.min.css');
        }
        if (!$handle) {
            $handle = 'bootstrap';
            wp_register_style($handle, 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', [], '5.3.3');
        }
        wp_enqueue_style($handle);
    }

    private static function enqueue_bootstrap_icons_style(): void {
        $handle = null;
        if (wp_style_is('bootstrap-icons', 'registered') || wp_style_is('bootstrap-icons', 'enqueued')) {
            $handle = 'bootstrap-icons';
        } elseif (wp_style_is('bootstrap-icons-css', 'registered') || wp_style_is('bootstrap-icons-css', 'enqueued')) {
            $handle = 'bootstrap-icons-css';
        } else {
            $handle = self::find_registered_style_handle_by_src_fragment('bootstrap-icons');
        }
        if (!$handle) {
            $handle = 'flacso-main-page-icons';
            wp_register_style($handle, 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css', [], '1.11.3');
        }
        wp_enqueue_style($handle);
    }

    private static function find_registered_style_handle_by_src_fragment(string $fragment): ?string {
        global $wp_styles;
        if (!($wp_styles instanceof WP_Styles) || empty($wp_styles->registered)) {
            return null;
        }
        foreach ($wp_styles->registered as $handle => $dependency) {
            if (isset($dependency->src) && stripos((string) $dependency->src, $fragment) !== false) {
                return (string) $handle;
            }
        }
        return null;
    }

    /**
     * Carga únicamente componentes propiedad de main-page. Los dominios anexan
     * sus adaptadores con `flacso_main_page_component_files`.
     */
    public static function load_components(): void {
        if (self::$components_loaded) {
            return;
        }
        self::$components_loaded = true;

        $files = [
            'sections/hero-inscripciones.php',
            'sections/listar-paginas.php',
            'sections/preguntas-frecuentes.php',
            'sections/convenios-responsivos.php',
            'sections/novedades-section.php',
            'sections/quienes-somos.php',
            'sections/congreso.php',
            'sections/contacto.php',
            'sections/landing-page.php',
            'sections/listar-categoria.php',
        ];

        $files = apply_filters('flacso_main_page_component_files', $files);
        $seen = [];
        foreach ((array) $files as $file) {
            $file = ltrim((string) $file, '/');
            if ($file === '' || isset($seen[$file])) {
                continue;
            }
            $seen[$file] = true;
            $absolute = strpos($file, FLACSO_URUGUAY_PATH) === 0
                ? $file
                : FLACSO_MAIN_PAGE_MODULE_PATH . $file;
            if (file_exists($absolute)) {
                require_once $absolute;
            } else {
                error_log('[FLACSO main-page] Componente no encontrado: ' . $absolute);
            }
        }
    }

    /** @deprecated Usar load_components(). */
    public static function load_shortcodes(): void {
        self::load_components();
    }
}
