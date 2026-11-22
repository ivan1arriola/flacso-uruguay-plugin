<?php
/**
 * Menú FLACSO unificado: un solo menú de primer nivel con todo lo que introduce el plugin.
 *
 * Apagado por defecto (opción en Ajustes → Sistema). Encendido, los tipos de contenido y las
 * pantallas de ajustes cuelgan de `flacso-panel` en vez de mezclarse con los menús nativos.
 * No toca los menús de WordPress ni de otros plugins.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLACSO_Admin_Menu {
    public const PARENT = 'flacso-panel';
    public const OPTION = 'flacso_unified_admin_menu';
    private const TAXONOMY_SLUG = 'edit-tags.php?taxonomy=tipo-oferta-academica&post_type=oferta-academica';

    private const POST_TYPES = [
        'oferta-academica', 'articulacion-academica', 'seminario', 'docente', 'programa-academico',
        'tabla-precio', 'convenio', 'evento', 'pregunta-frecuente',
    ];

    /** Orden por uso; lo que no figura aquí queda al final, en su orden original. */
    private const ORDER = [
        'flacso-panel',
        'edit.php?post_type=oferta-academica',
        'edit.php?post_type=articulacion-academica',
        self::TAXONOMY_SLUG,
        'flacso-carta-textos',
        'edit.php?post_type=seminario',
        'flacso-seminarios-settings',
        'edit.php?post_type=docente',
        'edit.php?post_type=programa-academico',
        'edit.php?post_type=tabla-precio',
        'edit.php?post_type=convenio',
        'edit.php?post_type=evento',
        'visualizador_eventos',
        'edit.php?post_type=pregunta-frecuente',
        'flacso-autoridades',
        'flacso-consultas',
        'flacso-main-page',
        'flacso-main-page-migrations',
        'fc_options_page',
        'flacso-correos',
        'flacso-integracion-meta',
        'flacso-preinscripcion-links',
        'flacso-sistema',
    ];

    public static function init(): void {
        add_filter('register_post_type_args', [self::class, 'nest_post_types'], 10, 2);
        add_action('admin_menu', [self::class, 'add_taxonomy_submenu'], 997);
        add_action('admin_menu', [self::class, 'sort_submenu'], 998);
        add_filter('parent_file', [self::class, 'highlight_parent'], 20);
        add_filter('submenu_file', [self::class, 'highlight_submenu'], 20);
    }

    public static function enabled(): bool {
        return (bool) apply_filters('flacso_unified_admin_menu', '1' === (string) get_option(self::OPTION, '0'));
    }

    /** Padre de una pantalla propia: FLACSO si el menú unificado está activo, o el nativo de siempre. */
    public static function parent(string $legacy): string {
        return self::enabled() ? self::PARENT : $legacy;
    }

    public static function nest_post_types($args, $post_type) {
        if (self::enabled() && in_array($post_type, self::POST_TYPES, true) && !empty($args['show_in_menu'])) {
            $args['show_in_menu'] = self::PARENT;
        }
        return $args;
    }

    public static function add_taxonomy_submenu(): void {
        global $submenu;
        if (!self::enabled() || in_array(self::TAXONOMY_SLUG, array_column($submenu[self::PARENT] ?? [], 2), true)) {
            return;
        }
        add_submenu_page(self::PARENT, __('Tipos de oferta', 'flacso-uruguay'), __('Tipos de oferta', 'flacso-uruguay'), 'manage_categories', self::TAXONOMY_SLUG);
    }

    public static function sort_submenu(): void {
        global $submenu;
        if (!self::enabled() || empty($submenu[self::PARENT])) {
            return;
        }
        $rank = array_flip(self::ORDER);
        $items = array_values($submenu[self::PARENT]);
        $keyed = [];
        foreach ($items as $i => $item) {
            $keyed[] = [$rank[$item[2]] ?? count($rank), $i, $item];
        }
        usort($keyed, static fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        $submenu[self::PARENT] = array_column($keyed, 2);
    }

    private static function in_consultas(): bool {
        $page = sanitize_key(wp_unslash($_GET['page'] ?? ''));
        return 0 === strpos($page, 'flacso-consultas-');
    }

    public static function highlight_parent($parent_file) {
        if (!self::enabled()) {
            return $parent_file;
        }
        if (self::in_consultas()) {
            return self::PARENT;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && !empty($screen->post_type) && in_array($screen->post_type, self::POST_TYPES, true)
            && 0 === strpos((string) $parent_file, 'edit.php?post_type=')) {
            return self::PARENT;
        }
        return $parent_file;
    }

    public static function highlight_submenu($submenu_file) {
        return self::enabled() && self::in_consultas() ? 'flacso-consultas' : $submenu_file;
    }
}
