<?php

if (!defined('ABSPATH')) {
    exit;
}

class Flacso_Main_Page_Loader {
    public static function init(): void {
        $is_ajax_context = function_exists('wp_doing_ajax') && wp_doing_ajax();
        if (!is_admin() || $is_ajax_context || (defined('REST_REQUEST') && REST_REQUEST) || self::is_flacso_admin_request()) {
            self::load_shortcodes();
        }
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    private static function is_flacso_admin_request(): bool {
        if (!is_admin()) {
            return false;
        }

        if (!isset($_GET['page'])) {
            return false;
        }

        $page = sanitize_key((string) wp_unslash($_GET['page']));
        if ($page === '') {
            return false;
        }

        return strpos($page, 'flacso-main-page') === 0;
    }

    public static function enqueue_assets(): void {
        if (!self::should_enqueue_assets()) {
            return;
        }

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
            FLACSO_MAIN_PAGE_VERSION
        );
        wp_register_style(
            'flacso-main-page-base',
            FLACSO_MAIN_PAGE_MODULE_URL . 'assets/css/flacso-main-page.css',
            ['flacso-mobile-first'],
            FLACSO_MAIN_PAGE_VERSION
        );

        $heading_color_choice = Flacso_Main_Page_Settings::get_section_heading_color_choice();
        $resolve_value = static function (string $choice): string {
            return $choice === 'palette7'
                ? 'var(--global-palette7, #ffffff)'
                : 'var(--global-palette1, #1d3a72)';
        };
        $inline_styles = [];
        $inline_styles[] = sprintf(':root { --flacso-section-heading-color: %s; }', $resolve_value($heading_color_choice));
        $section_colors = Flacso_Main_Page_Settings::get_section_heading_colors();
        foreach ($section_colors as $section_key => $choice) {
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
        wp_enqueue_style('flacso-mobile-first');
        self::enqueue_bootstrap_style();
        wp_enqueue_style('flacso-main-page-base');
        self::enqueue_bootstrap_icons_style();

        wp_register_script(
            'flacso-main-page-react',
            FLACSO_MAIN_PAGE_MODULE_URL . 'assets/js/flacso-main-page-react.js',
            ['wp-element'],
            FLACSO_MAIN_PAGE_VERSION,
            true
        );

        wp_register_script(
            'flacso-convenios-react',
            FLACSO_MAIN_PAGE_MODULE_URL . 'assets/js/flacso-convenios-react.js',
            ['wp-element'],
            FLACSO_MAIN_PAGE_VERSION,
            true
        );
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
        $shortcodes = [
            'flacso_homepage_builder',
            'lista_seminarios',
            'listar_paginas',
            'preguntas_frecuentes',
            'convenios_responsivos',
            'listar_categoria',
            'oferta_academica',
            'Consultas_Fase_1',
        ];

        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($content, $shortcode)) {
                return true;
            }
        }

        if (function_exists('has_block') && strpos($content, 'wp:flacso-uruguay/') !== false) {
            return true;
        }

        return false;
    }

    private static function enqueue_bootstrap_style(): void {
        $bootstrap_handle = null;

        if (wp_style_is('bootstrap', 'registered') || wp_style_is('bootstrap', 'enqueued')) {
            $bootstrap_handle = 'bootstrap';
        } else {
            $bootstrap_handle = self::find_registered_style_handle_by_src_fragment('bootstrap@5');
            if (!$bootstrap_handle) {
                $bootstrap_handle = self::find_registered_style_handle_by_src_fragment('/bootstrap.min.css');
            }
        }

        if (!$bootstrap_handle) {
            $bootstrap_handle = 'bootstrap';
            wp_register_style(
                $bootstrap_handle,
                'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
                [],
                '5.3.3'
            );
        }

        wp_enqueue_style($bootstrap_handle);
    }

    private static function enqueue_bootstrap_icons_style(): void {
        $icons_handle = null;

        if (wp_style_is('bootstrap-icons', 'registered') || wp_style_is('bootstrap-icons', 'enqueued')) {
            $icons_handle = 'bootstrap-icons';
        } elseif (wp_style_is('bootstrap-icons-css', 'registered') || wp_style_is('bootstrap-icons-css', 'enqueued')) {
            $icons_handle = 'bootstrap-icons-css';
        } else {
            $icons_handle = self::find_registered_style_handle_by_src_fragment('bootstrap-icons');
        }

        if (!$icons_handle) {
            $icons_handle = 'flacso-main-page-icons';
            wp_register_style(
                $icons_handle,
                'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
                [],
                '1.11.3'
            );
        }

        wp_enqueue_style($icons_handle);
    }

    private static function find_registered_style_handle_by_src_fragment(string $fragment): ?string {
        global $wp_styles;

        if (!($wp_styles instanceof WP_Styles) || empty($wp_styles->registered)) {
            return null;
        }

        foreach ($wp_styles->registered as $handle => $dependency) {
            if (!isset($dependency->src)) {
                continue;
            }
            if (stripos((string) $dependency->src, $fragment) !== false) {
                return (string) $handle;
            }
        }

        return null;
    }

    public static function load_shortcodes(): void {
        require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/hero-inscripciones.php';
        require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/lista-seminarios.php';
        // REMOVED: oferta-academica.php - Movido a plugin separado flacso-formacion
        require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/listar-paginas.php';
        require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/eventos-carousel.php';
        require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/preguntas-frecuentes.php';
        require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/convenios-responsivos.php';
        require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/novedades-section.php';
        require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/quienes-somos.php';
        require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/instagram.php';
        require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/posgrados.php';
        require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/congreso.php';
        require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/contacto.php';
        require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/landing-page.php';
        require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/flacso-raw-content-api.php';
        // Nuevo shortcode genérico de categoría
        require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/listar-categoria.php';
    }
}

