<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra el Custom Post Type: docente (Personas / Equipo FLACSO)
 * y gestiona el enrutamiento público (/equipo, /equipo/docentes, /equipo/administrativo).
 */
class CPT_Docente {
    public const POST_TYPE = 'docente';

    public static function init(): void {
        self::register_post_type();
        add_filter('use_block_editor_for_post_type', [self::class, 'disable_block_editor'], 10, 2);
        add_action('init', [self::class, 'register_rewrite_rules'], 10);
        add_filter('query_vars', [self::class, 'register_query_vars']);
        add_filter('template_include', [self::class, 'handle_template_routing'], 20);
        add_filter('pre_get_document_title', [self::class, 'filter_document_title'], 20);
    }

    public static function register_post_type(): void {
        $labels = [
            'name'                  => __('Personas / Equipo', 'flacso-uruguay'),
            'singular_name'         => __('Persona / Integrante', 'flacso-uruguay'),
            'add_new'               => __('Añadir persona', 'flacso-uruguay'),
            'add_new_item'          => __('Añadir nueva persona al equipo', 'flacso-uruguay'),
            'edit_item'             => __('Editar perfil de equipo', 'flacso-uruguay'),
            'new_item'              => __('Nueva persona', 'flacso-uruguay'),
            'view_item'             => __('Ver perfil', 'flacso-uruguay'),
            'all_items'             => __('Todo el Equipo', 'flacso-uruguay'),
            'search_items'          => __('Buscar personas', 'flacso-uruguay'),
            'not_found'             => __('No se encontraron integrantes del equipo', 'flacso-uruguay'),
            'not_found_in_trash'    => __('No hay integrantes en la papelera', 'flacso-uruguay'),
            'menu_name'             => __('Personas / Equipo', 'flacso-uruguay'),
        ];

        $args = [
            'labels'                => $labels,
            'public'                => true,
            'show_in_rest'          => true,
            'has_archive'           => 'equipo',
            'rewrite'               => ['slug' => 'equipo', 'with_front' => false],
            'supports'              => ['title', 'thumbnail', 'revisions'],
            'menu_icon'             => 'dashicons-groups',
            'menu_position'         => 6,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    public static function disable_block_editor(bool $use_block_editor, string $post_type): bool {
        if ($post_type === self::POST_TYPE) {
            return false;
        }
        return $use_block_editor;
    }

    public static function register_rewrite_rules(): void {
        add_rewrite_tag('%flacso_equipo_view%', '([^&]+)');
        add_rewrite_rule('^equipo/docentes/?$', 'index.php?flacso_equipo_view=docentes', 'top');
        add_rewrite_rule('^equipo/administrativo/?$', 'index.php?flacso_equipo_view=administrativo', 'top');
        add_rewrite_rule('^equipo/?$', 'index.php?flacso_equipo_view=all', 'top');
        add_rewrite_rule('^docentes/?$', 'index.php?flacso_equipo_view=docentes', 'top');
        add_rewrite_rule('^docente/([^/]+)/?$', 'index.php?docente=$matches[1]', 'top');
    }

    public static function register_query_vars(array $vars): array {
        $vars[] = 'flacso_equipo_view';
        return $vars;
    }

    public static function handle_template_routing($template) {
        $view = get_query_var('flacso_equipo_view');
        if ($view) {
            global $wp_query;
            if ($wp_query) {
                $wp_query->is_404 = false;
                $wp_query->is_archive = true;
                $wp_query->is_post_type_archive = true;
            }
            status_header(200);

            $archive_template = locate_template(['archive-docente.php', 'archive.php']);
            if ($archive_template !== '') {
                return $archive_template;
            }
        }
        return $template;
    }

    public static function filter_document_title(string $title): string {
        $view = get_query_var('flacso_equipo_view');
        if (!$view) {
            return $title;
        }

        $site_name = trim((string) get_bloginfo('name'));
        if ($view === 'docentes') {
            $base = __('Cuerpo Docente', 'flacso-uruguay');
        } elseif ($view === 'administrativo') {
            $base = __('Equipo Administrativo y de Gestión', 'flacso-uruguay');
        } else {
            $base = __('Equipo FLACSO Uruguay', 'flacso-uruguay');
        }

        return $site_name !== '' ? $base . ' – ' . $site_name : $base;
    }
}
