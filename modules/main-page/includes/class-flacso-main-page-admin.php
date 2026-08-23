<?php
/**
 * Menú administrativo mínimo de la portada.
 *
 * La edición real vive en editor.flacso.edu.uy/main-page. Esta clase conserva
 * las rutas históricas de WordPress para no romper marcadores ni permisos.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Flacso_Main_Page_Admin {
    private const PAGE_SLUGS = [
        'flacso-main-page',
        'flacso-main-page-oferta-academica',
    ];

    public static function init(): void {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_bar_menu', [self::class, 'register_admin_bar_menu'], 1);
    }

    public static function get_admin_page_slugs(): array {
        return self::PAGE_SLUGS;
    }

    public static function register_menu(): void {
        add_menu_page(
            __('Portada FLACSO', 'flacso-main-page'),
            __('Portada FLACSO', 'flacso-main-page'),
            'manage_options',
            'flacso-main-page',
            [self::class, 'render_section_page'],
            'dashicons-admin-site-alt3',
            1
        );

        add_submenu_page(
            'flacso-main-page',
            __('Oferta Académica', 'flacso-main-page'),
            __('Oferta Académica', 'flacso-main-page'),
            'manage_options',
            'flacso-main-page-oferta-academica',
            [self::class, 'render_oferta_academica_page']
        );
    }

    public static function register_admin_bar_menu(WP_Admin_Bar $wp_admin_bar): void {
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
            return;
        }

        $wp_admin_bar->add_node([
            'id' => 'flacso-main-page-bar',
            'title' => __('Editar portada', 'flacso-main-page'),
            'href' => Flacso_Main_Page_Unified_Settings::editor_url(),
            'meta' => [
                'title' => __('Abrir Editor FLACSO', 'flacso-main-page'),
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
            ],
        ]);
    }

    public static function render_section_page(): void {
        Flacso_Main_Page_Unified_Settings::render_unified_page();
    }

    public static function render_oferta_academica_page(): void {
        Flacso_Main_Page_Unified_Settings::render_unified_page();
    }

    /** @deprecated La UI legacy fue retirada. */
    public static function render_section_page_legacy(): void {
        self::render_section_page();
    }
}
