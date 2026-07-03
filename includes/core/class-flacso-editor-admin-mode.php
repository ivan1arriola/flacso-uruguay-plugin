<?php

if (!defined('ABSPATH')) {
    exit;
}

class FLACSO_Editor_Admin_Mode {
    private const MANAGED_POST_TYPES = [
        'docente',
        'seminario',
        'charla_abierta',
        'fc_consulta',
        'fc_info_request',
    ];

    private const MANAGED_TAXONOMIES = [
        'tipo-oferta-academica',
        'area_tematica',
    ];

    private const MANAGED_PAGE_SLUGS = [
        'docentes_panel',
        'docentes_lista',
        'docentes_documentacion',
        'docentes_api',
        'docentes_migracion',
        'docente-migration',
        'fc_options_page',
        'fc_oferta_options_page',
        'fc_consultas_overview',
        'fc_info_overview',
        'seminario-config',
        'seminario-api',
        'flacso-oferta-data',
        'flacso-oferta-api-docs',
        'flacso-oferta-consulta-form',
        'oferta-seminarios-links',
        'flacso-oferta-migracion',
        'flacso-charlas-abiertas-settings',
        'flacso-charlas-abiertas-visualizador',
        'flacso-main-page',
        'flacso-main-page-oferta-academica',
    ];

    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [self::class, 'hide_menus'], 999);
        add_action('admin_bar_menu', [self::class, 'hide_admin_bar_nodes'], 999);
        add_action('current_screen', [self::class, 'redirect_managed_screens']);
        add_action('admin_notices', [self::class, 'render_notice']);
    }

    public static function is_enabled(): bool {
        return (bool) apply_filters('flacso_editor_admin_mode_enabled', false);
    }

    /**
     * @return string[]
     */
    private static function managed_post_types(): array {
        return apply_filters('flacso_editor_admin_managed_post_types', self::MANAGED_POST_TYPES);
    }

    /**
     * @return string[]
     */
    private static function managed_taxonomies(): array {
        return apply_filters('flacso_editor_admin_managed_taxonomies', self::MANAGED_TAXONOMIES);
    }

    /**
     * @return string[]
     */
    private static function managed_page_slugs(): array {
        $page_slugs = self::MANAGED_PAGE_SLUGS;

        if (class_exists('Flacso_Main_Page_Admin')) {
            $page_slugs = array_merge(
                $page_slugs,
                Flacso_Main_Page_Admin::get_admin_page_slugs()
            );
        }

        return apply_filters(
            'flacso_editor_admin_managed_page_slugs',
            array_values(array_unique($page_slugs))
        );
    }

    public static function hide_menus(): void {
        if (!self::is_enabled()) {
            return;
        }

        foreach (self::managed_post_types() as $post_type) {
            remove_menu_page('edit.php?post_type=' . $post_type);
        }

        remove_menu_page('docentes_panel');
        remove_menu_page('flacso-main-page');
        remove_submenu_page('options-general.php', 'fc_options_page');
        remove_submenu_page('options-general.php', 'fc_oferta_options_page');

        foreach (self::managed_taxonomies() as $taxonomy) {
            remove_submenu_page(
                'edit.php?post_type=oferta-academica',
                'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=oferta-academica'
            );
        }
    }

    public static function hide_admin_bar_nodes(WP_Admin_Bar $admin_bar): void {
        if (!self::is_enabled()) {
            return;
        }

        foreach (self::managed_post_types() as $post_type) {
            $admin_bar->remove_node('new-' . $post_type);
        }

        $admin_bar->remove_node('flacso-main-page-bar');
    }

    public static function redirect_managed_screens($screen): void {
        if (!self::is_enabled() || wp_doing_ajax() || !$screen instanceof WP_Screen) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $post_type = isset($screen->post_type) ? (string) $screen->post_type : '';
        $taxonomy = isset($screen->taxonomy) ? (string) $screen->taxonomy : '';

        if (!self::is_managed_screen($post_type, $taxonomy, $page)) {
            return;
        }

        $redirect_url = add_query_arg(
            ['flacso_editor_admin_hidden' => '1'],
            admin_url()
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    public static function render_notice(): void {
        if (!self::is_enabled()) {
            return;
        }

        $hidden = isset($_GET['flacso_editor_admin_hidden'])
            ? sanitize_text_field(wp_unslash($_GET['flacso_editor_admin_hidden']))
            : '';

        if ('1' !== $hidden) {
            return;
        }

        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong><?php esc_html_e('Panel FLACSO ahora se administra desde la app.', 'flacso-uruguay'); ?></strong>
                <?php esc_html_e('Las pantallas legacy de WordPress para la home, docentes, seminarios, ofertas, charlas y consultas fueron ocultadas para evitar lógica duplicada.', 'flacso-uruguay'); ?>
            </p>
        </div>
        <?php
    }

    private static function is_managed_screen(string $post_type, string $taxonomy, string $page): bool {
        if (in_array($post_type, self::managed_post_types(), true)) {
            return true;
        }

        if (in_array($taxonomy, self::managed_taxonomies(), true)) {
            return true;
        }

        return '' !== $page && in_array($page, self::managed_page_slugs(), true);
    }
}

FLACSO_Editor_Admin_Mode::init();
