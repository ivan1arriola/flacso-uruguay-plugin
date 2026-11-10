<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ordena el menú de wp-admin en seis grupos de trabajo bajo la raíz FLACSO.
 *
 * Sólo cambia dónde se ve cada entrada: conserva slug, etiqueta, capacidad y
 * callback de cada pantalla. Las entradas que un plugin registre después de
 * este organizador no se pierden: quedan donde las registró su plugin.
 */
final class FLACSO_Admin_Menu_Organizer {
    public const GROUP_INICIO = 'inicio';
    public const GROUP_FORMACION = 'formacion';
    public const GROUP_CONTENIDOS = 'contenidos';
    public const GROUP_INSCRIPCIONES = 'inscripciones';
    public const GROUP_DISENO = 'diseno';
    public const GROUP_SISTEMA = 'sistema';

    private const ROOT_FALLBACK = 'flacso-panel';
    private const HEADING_CLASS = 'flacso-menu-group';

    /** @var array<string,string> Slug original de cada sección movida => slug de la raíz. */
    private static $moved_parents = [];
    /** @var string Slug original de la pantalla actual, para resaltar su entrada. */
    private static $current_origin = '';

    public static function init(): void {
        if (!is_admin()) {
            return;
        }
        add_action('admin_menu', [self::class, 'organize_menu'], 1000);
        add_filter('parent_file', [self::class, 'filter_parent_file'], 20);
        add_filter('submenu_file', [self::class, 'filter_submenu_file'], 20);
        add_action('admin_head', [self::class, 'print_menu_styles']);
    }

    /** @return array<string,string> Identificador => etiqueta, en el orden de presentación. */
    public static function groups(): array {
        return [
            self::GROUP_INICIO => __('Inicio FLACSO', 'flacso-uruguay'),
            self::GROUP_FORMACION => __('Formación', 'flacso-uruguay'),
            self::GROUP_CONTENIDOS => __('Sitio y contenidos', 'flacso-uruguay'),
            self::GROUP_INSCRIPCIONES => __('Inscripciones y consultas', 'flacso-uruguay'),
            self::GROUP_DISENO => __('Diseño del sitio', 'flacso-uruguay'),
            self::GROUP_SISTEMA => __('Sistema y herramientas', 'flacso-uruguay'),
        ];
    }

    /**
     * Slug de menú => grupo. Se indexa por slug y no por etiqueta traducida,
     * así el idioma de la interfaz no altera la clasificación.
     *
     * @return array<string,string>
     */
    public static function group_manifest(): array {
        return self::build_manifest()['manifest'];
    }

    /** @return string[] Slugs que el manifiesto asigna a más de un grupo (debe estar vacío). */
    public static function manifest_duplicates(): array {
        return self::build_manifest()['duplicates'];
    }

    /** @return array{manifest:array<string,string>,duplicates:string[]} */
    private static function build_manifest(): array {
        $manifest = [];
        $duplicates = [];
        $assign = static function (string $group, array $slugs) use (&$manifest, &$duplicates): void {
            foreach ($slugs as $slug) {
                if (isset($manifest[$slug])) {
                    $duplicates[] = $slug;
                    continue;
                }
                $manifest[$slug] = $group;
            }
        };

        $assign(self::GROUP_INICIO, ['flacso-panel', 'index.php', 'update-core.php']);

        $assign(self::GROUP_FORMACION, [
            'edit.php?post_type=programa-academico', 'edit.php?post_type=oferta-academica',
            'flacso-oferta-consulta', 'edit.php?post_type=cohorte',
            'edit.php?post_type=seminario', 'flacso-seminarios-settings', 'edit.php?post_type=edicion',
            'edit.php?post_type=tabla-precio',
            'edit.php?post_type=convenio',
            'edit.php?post_type=docente', 'docentes_panel', 'docentes_lista', 'docentes_documentacion',
            'docentes_api', 'docentes_migracion',
        ]);

        $assign(self::GROUP_CONTENIDOS, [
            'flacso-main-page', 'flacso-carta-textos', 'flacso-autoridades',
            'edit.php', 'edit.php?post_type=page', 'edit.php?post_type=evento', 'visualizador_eventos',
            'edit.php?post_type=flacso_faq', 'edit.php?post_type=pregunta-frecuente', 'upload.php', 'media-new.php', 'edit-comments.php',
        ]);

        $assign(self::GROUP_INSCRIPCIONES, [
            'flacso-consultas', 'flacso-preinscripcion-links', 'fc_options_page', 'flacso-correos',
            'flacso-consultas-resumen', 'flacso-consultas-oferta-pais', 'flacso-consultas-comparacion',
            'flacso-consultas-campanas', 'flacso-consultas-exportar',
            'fc_consultas_overview', 'fc_info_overview',
        ]);

        $assign(self::GROUP_DISENO, ['themes.php', 'customize.php', 'site-editor.php', 'nav-menus.php', 'widgets.php', 'font-library.php']);

        $assign(self::GROUP_SISTEMA, [
            'flacso-sistema', 'flacso-integracion-meta', 'flacso-integraciones',
            'users.php', 'profile.php', 'plugins.php', 'tools.php', 'import.php', 'export.php',
            'site-health.php', 'options-general.php',
        ]);

        return ['manifest' => $manifest, 'duplicates' => $duplicates];
    }

    /** Grupo de una ruta interna; las rutas sin clasificar caen en Sistema y herramientas. */
    public static function group_for_slug(string $slug): string {
        $slug = trim($slug);
        // Un enlace escrito como admin.php?page=x es la misma pantalla que x.
        if (preg_match('#^admin\.php\?page=([^&]+)#', $slug, $page)) {
            $slug = rawurldecode($page[1]);
        }
        $manifest = self::group_manifest();
        if (isset($manifest[$slug])) {
            return $manifest[$slug];
        }

        // Accesos externos de los asistentes: se agrupan según el servicio al que llevan.
        if (preg_match('#^https?://([^/]+)#i', $slug, $host)) {
            if (stripos($host[1], 'preinscripciones') !== false) {
                return self::GROUP_INSCRIPCIONES;
            }
            if (stripos($host[1], 'salavirtual') !== false) {
                return self::GROUP_FORMACION;
            }
            return self::GROUP_SISTEMA;
        }

        $query = [];
        $question = strpos($slug, '?');
        if ($question !== false) {
            parse_str(substr($slug, $question + 1), $query);
        }
        $by_type = [
            'programa-academico' => self::GROUP_FORMACION, 'oferta-academica' => self::GROUP_FORMACION,
            'cohorte' => self::GROUP_FORMACION, 'seminario' => self::GROUP_FORMACION,
            'edicion' => self::GROUP_FORMACION, 'tabla-precio' => self::GROUP_FORMACION,
            'convenio' => self::GROUP_FORMACION, 'docente' => self::GROUP_FORMACION,
            'page' => self::GROUP_CONTENIDOS, 'evento' => self::GROUP_CONTENIDOS,
            'flacso_faq' => self::GROUP_CONTENIDOS, 'pregunta-frecuente' => self::GROUP_CONTENIDOS, 'attachment' => self::GROUP_CONTENIDOS,
            'fc_consulta' => self::GROUP_INSCRIPCIONES, 'fc_info_request' => self::GROUP_INSCRIPCIONES,
        ];
        $post_type = isset($query['post_type']) ? (string) $query['post_type'] : '';
        if ($post_type !== '' && isset($by_type[$post_type])) {
            return $by_type[$post_type];
        }
        $taxonomy = isset($query['taxonomy']) ? (string) $query['taxonomy'] : '';
        if (in_array($taxonomy, ['category', 'post_tag'], true) && $post_type === '') {
            return self::GROUP_CONTENIDOS;
        }
        if ($taxonomy === 'tipo-oferta-academica') {
            return self::GROUP_FORMACION;
        }
        if (strpos($slug, 'post-new.php') === 0 && $post_type === '') {
            return self::GROUP_CONTENIDOS;
        }

        $prefixes = [
            'docentes_' => self::GROUP_FORMACION,
            'flacso-convenios' => self::GROUP_FORMACION,
            'flacso-seminario' => self::GROUP_FORMACION,
            'flacso-consultas' => self::GROUP_INSCRIPCIONES,
            'flacso-preinscripcion' => self::GROUP_INSCRIPCIONES,
            'kadence' => self::GROUP_DISENO,
            'themes.php' => self::GROUP_DISENO,
            'customize.php' => self::GROUP_DISENO,
        ];
        foreach ($prefixes as $prefix => $group) {
            if (strpos($slug, $prefix) === 0) {
                return $group;
            }
        }

        return self::GROUP_SISTEMA;
    }

    /**
     * Reubica las entradas visibles bajo la raíz FLACSO. Es idempotente: una segunda
     * pasada recoge lo registrado más tarde sin duplicar encabezados.
     */
    public static function organize_menu(): void {
        global $menu, $submenu, $admin_page_hooks, $_registered_pages, $wp_filter;

        $root = self::root_slug();
        if (!is_array($menu) || !is_array($submenu)) {
            return;
        }
        $submenu[$root] = isset($submenu[$root]) && is_array($submenu[$root]) ? $submenu[$root] : [];
        $admin_page_hooks = is_array($admin_page_hooks ?? null) ? $admin_page_hooks : [];

        // Quita los encabezados de una pasada anterior.
        $entries = [];
        foreach ($submenu[$root] as $item) {
            if (self::is_heading($item)) {
                continue;
            }
            $entries[] = $item;
        }

        $present = [];
        foreach ($entries as $item) {
            $present[(string) $item[2]] = true;
        }

        $root_type = isset($admin_page_hooks[$root]) ? (string) $admin_page_hooks[$root] : '';

        foreach ($menu as $position => $top) {
            $slug = isset($top[2]) ? (string) $top[2] : '';
            if ($slug === '' || $slug === $root || self::is_separator($top) || preg_match('#^https?://#i', $slug)) {
                continue;
            }

            $children = isset($submenu[$slug]) && is_array($submenu[$slug]) ? $submenu[$slug] : [];
            if ($children === []) {
                $children = [[$top[0], $top[1], $slug, $top[3] ?? '']];
            }

            $old_type = isset($admin_page_hooks[$slug]) ? (string) $admin_page_hooks[$slug] : 'admin';
            foreach ($children as $child) {
                $child_slug = (string) ($child[2] ?? '');
                if ($child_slug === '' || isset($present[$child_slug])) {
                    continue;
                }
                // La pantalla se invoca con el hook calculado desde su padre actual:
                // se enlaza el callback original con el hook que tendrá bajo FLACSO.
                if (!isset($admin_page_hooks[$child_slug]) && $root_type !== '') {
                    $name = preg_replace('!\.php!', '', $child_slug);
                    $old_hook = $old_type . '_page_' . $name;
                    $new_hook = $root_type . '_page_' . $name;
                    if ($old_hook !== $new_hook && is_array($wp_filter ?? null) && isset($wp_filter[$old_hook]) && !isset($wp_filter[$new_hook])) {
                        $wp_filter[$new_hook] = $wp_filter[$old_hook];
                        $_registered_pages = is_array($_registered_pages ?? null) ? $_registered_pages : [];
                        $_registered_pages[$new_hook] = true;
                    }
                }
                $entries[] = $child;
                $present[$child_slug] = true;
            }

            self::$moved_parents[$slug] = $root;
            unset($menu[$position], $submenu[$slug]);
        }

        $submenu[$root] = self::arrange($entries);
    }

    /**
     * @param array<int,array> $entries
     * @return array<int,array>
     */
    private static function arrange(array $entries): array {
        $groups = self::groups();
        $order = array_keys($groups);
        $manifest_position = array_flip(array_keys(self::group_manifest()));

        $indexed = [];
        foreach ($entries as $sequence => $item) {
            $slug = (string) $item[2];
            $group = self::group_for_slug($slug);
            $indexed[] = [
                'item' => $item,
                'group' => $group,
                'group_rank' => (int) array_search($group, $order, true),
                'manifest' => $manifest_position[$slug] ?? PHP_INT_MAX,
                'sequence' => $sequence,
            ];
        }
        usort($indexed, static function (array $a, array $b): int {
            return [$a['group_rank'], $a['manifest'], $a['sequence']] <=> [$b['group_rank'], $b['manifest'], $b['sequence']];
        });

        $result = [];
        $seen = [];
        foreach ($indexed as $row) {
            $group = $row['group'];
            if (!isset($seen[$group]) && self::user_can_see($row['item'])) {
                // WordPress enlaza el menú FLACSO a su primera entrada: el primer encabezado
                // apunta a una pantalla real; los demás son anclas inertes.
                $target = $seen === [] ? self::link_for((string) $row['item'][2]) : '#flacso-grupo-' . $group;
                $seen[$group] = true;
                $result[] = [$groups[$group], 'read', $target, '', self::HEADING_CLASS];
            }
            $result[] = $row['item'];
        }
        return $result;
    }

    private static function link_for(string $slug): string {
        return strpos($slug, '.php') !== false ? $slug : 'admin.php?page=' . $slug;
    }

    public static function filter_parent_file($parent_file) {
        $parent_file = (string) $parent_file;
        if (isset(self::$moved_parents[$parent_file])) {
            self::$current_origin = $parent_file;
            return self::$moved_parents[$parent_file];
        }
        self::$current_origin = '';
        return $parent_file;
    }

    public static function filter_submenu_file($submenu_file) {
        if ((string) $submenu_file === '' && self::$current_origin !== '') {
            return self::$current_origin;
        }
        return $submenu_file;
    }

    /** Los encabezados de grupo no son enlaces. */
    public static function print_menu_styles(): void {
        echo '<style id="flacso-menu-groups">'
            . '#adminmenu .wp-submenu li.' . self::HEADING_CLASS . '{pointer-events:none;margin-top:6px;border-top:1px solid rgba(255,255,255,.12)}'
            . '#adminmenu .wp-submenu li.' . self::HEADING_CLASS . ' a{color:#8c93a0!important;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase}'
            . '</style>';
    }

    private static function root_slug(): string {
        return class_exists('FLACSO_Admin_Panel') ? FLACSO_Admin_Panel::PAGE_SLUG : self::ROOT_FALLBACK;
    }

    private static function is_heading(array $item): bool {
        return isset($item[4]) && strpos((string) $item[4], self::HEADING_CLASS) !== false;
    }

    private static function is_separator(array $top): bool {
        return isset($top[4]) && strpos((string) $top[4], 'wp-menu-separator') !== false;
    }

    private static function user_can_see(array $item): bool {
        $capability = isset($item[1]) ? (string) $item[1] : 'read';
        return function_exists('current_user_can') ? (bool) current_user_can($capability) : true;
    }
}

FLACSO_Admin_Menu_Organizer::init();
