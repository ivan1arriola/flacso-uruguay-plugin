<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Experiencia administrativa y autorización para Asistentes Académicas.
 *
 * Mantiene WordPress como fuente de verdad y restringe la experiencia del rol
 * a las tareas académicas cotidianas sin dar acceso a áreas técnicas.
 */
final class FLACSO_Academic_Assistant {
    public const ROLE = 'asistente_academica';

    public const ACCESS = 'flacso_access_academic_management';
    public const EDIT_OFFERS = 'flacso_edit_offers';
    public const CREATE_OFFERS = 'flacso_create_offers';

    public const CREATE_COHORTS = 'flacso_create_cohorts';
    public const EDIT_COHORTS = 'flacso_edit_cohorts';

    public const CREATE_SEMINARS = 'flacso_create_seminars';
    public const EDIT_SEMINARS = 'flacso_edit_seminars';

    public const CREATE_EDITIONS = 'flacso_create_editions';
    public const EDIT_EDITIONS = 'flacso_edit_editions';

    public const CREATE_TEACHERS = 'flacso_create_teachers';
    public const EDIT_TEACHERS = 'flacso_edit_teachers';
    public const ASSIGN_TEACHERS = 'flacso_assign_teachers';

    public const VIEW_EXTERNAL_SERVICES = 'flacso_view_external_services';
    public const MANAGE_PREINSCRIPTIONS = 'flacso_manage_preinscriptions';

    private const ROLE_VERSION = '1';
    private const ROLE_VERSION_OPTION = 'flacso_academic_assistant_role_version';

    public static function init(): void {
        add_action('init', [self::class, 'sync_roles'], 1);

        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [self::class, 'simplify_menu'], 1000);
        add_action('admin_bar_menu', [self::class, 'simplify_admin_bar'], 1000);
        add_action('admin_init', [self::class, 'redirect_dashboard']);
        add_action('current_screen', [self::class, 'guard_screens'], 1000);
        add_filter('login_redirect', [self::class, 'login_redirect'], 10, 3);
        add_filter('post_row_actions', [self::class, 'filter_row_actions'], 100, 2);
        add_filter('page_row_actions', [self::class, 'filter_row_actions'], 100, 2);
        add_filter('admin_body_class', [self::class, 'admin_body_class']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function is_assistant($user = null): bool {
        $user = $user instanceof WP_User ? $user : wp_get_current_user();
        return $user instanceof WP_User && in_array(self::ROLE, (array) $user->roles, true);
    }

    /**
     * Mapa de capabilities para CPT académicos.
     *
     * @return array<string,string>
     */
    public static function post_type_capabilities(string $entity): array {
        $config = [
            'offer' => [
                'edit' => self::EDIT_OFFERS,
                'create' => self::CREATE_OFFERS,
                'delete' => 'flacso_delete_offers',
                'singular' => 'flacso_offer',
            ],
            'cohort' => [
                'edit' => self::EDIT_COHORTS,
                'create' => self::CREATE_COHORTS,
                'delete' => 'flacso_delete_cohorts',
                'singular' => 'flacso_cohort',
            ],
            'seminar' => [
                'edit' => self::EDIT_SEMINARS,
                'create' => self::CREATE_SEMINARS,
                'delete' => 'flacso_delete_seminars',
                'singular' => 'flacso_seminar',
            ],
            'edition' => [
                'edit' => self::EDIT_EDITIONS,
                'create' => self::CREATE_EDITIONS,
                'delete' => 'flacso_delete_editions',
                'singular' => 'flacso_edition',
            ],
            'teacher' => [
                'edit' => self::EDIT_TEACHERS,
                'create' => self::CREATE_TEACHERS,
                'delete' => 'flacso_delete_teachers',
                'singular' => 'flacso_teacher',
            ],
        ];

        if (!isset($config[$entity])) {
            return [];
        }

        $item = $config[$entity];

        return [
            'edit_post' => 'edit_' . $item['singular'],
            'read_post' => 'read_' . $item['singular'],
            'delete_post' => 'delete_' . $item['singular'],
            'edit_posts' => $item['edit'],
            'edit_others_posts' => $item['edit'],
            'edit_private_posts' => $item['edit'],
            'edit_published_posts' => $item['edit'],
            'publish_posts' => $item['create'],
            'read_private_posts' => $item['edit'],
            'create_posts' => $item['create'],
            'delete_posts' => $item['delete'],
            'delete_private_posts' => $item['delete'],
            'delete_published_posts' => $item['delete'],
            'delete_others_posts' => $item['delete'],
        ];
    }

    public static function sync_roles(): void {
        if (
            get_option(self::ROLE_VERSION_OPTION) === self::ROLE_VERSION
            && get_role(self::ROLE) instanceof WP_Role
        ) {
            return;
        }

        $assistant_caps = [
            'read' => true,
            'upload_files' => true,
            self::ACCESS => true,
            self::EDIT_OFFERS => true,
            self::CREATE_COHORTS => true,
            self::EDIT_COHORTS => true,
            self::CREATE_SEMINARS => true,
            self::EDIT_SEMINARS => true,
            self::CREATE_EDITIONS => true,
            self::EDIT_EDITIONS => true,
            self::CREATE_TEACHERS => true,
            self::EDIT_TEACHERS => true,
            self::ASSIGN_TEACHERS => true,
            self::VIEW_EXTERNAL_SERVICES => true,
        ];

        $role = get_role(self::ROLE);
        if (!$role) {
            $role = add_role(
                self::ROLE,
                __('Asistente Académica', 'flacso-uruguay'),
                $assistant_caps
            );
        }

        if ($role instanceof WP_Role) {
            foreach ($assistant_caps as $capability => $grant) {
                $role->add_cap($capability, $grant);
            }
        }

        // Administradores conservan todo el wp-admin y todas las acciones académicas.
        self::grant_full_academic_caps_to_role('administrator');

        // Compatibilidad de transición: los usuarios Editor no pierden el acceso que ya tenían.
        self::grant_full_academic_caps_to_role('editor');

        update_option(self::ROLE_VERSION_OPTION, self::ROLE_VERSION, false);
    }

    private static function grant_full_academic_caps_to_role(string $role_name): void {
        $role = get_role($role_name);
        if (!$role instanceof WP_Role) {
            return;
        }

        foreach (self::all_academic_capabilities() as $capability) {
            $role->add_cap($capability, true);
        }
    }

    /**
     * @return string[]
     */
    private static function all_academic_capabilities(): array {
        $caps = [
            self::ACCESS,
            self::EDIT_OFFERS,
            self::CREATE_OFFERS,
            self::CREATE_COHORTS,
            self::EDIT_COHORTS,
            self::CREATE_SEMINARS,
            self::EDIT_SEMINARS,
            self::CREATE_EDITIONS,
            self::EDIT_EDITIONS,
            self::CREATE_TEACHERS,
            self::EDIT_TEACHERS,
            self::ASSIGN_TEACHERS,
            self::VIEW_EXTERNAL_SERVICES,
            self::MANAGE_PREINSCRIPTIONS,
        ];

        foreach (['offer', 'cohort', 'seminar', 'edition', 'teacher'] as $entity) {
            foreach (self::post_type_capabilities($entity) as $capability) {
                $caps[] = $capability;
            }
        }

        return array_values(array_unique($caps));
    }

    public static function simplify_menu(): void {
        if (!self::is_assistant()) {
            return;
        }

        global $menu, $submenu;

        // Para una Asistente el único menú raíz es FLACSO Gestión.
        if (is_array($menu)) {
            foreach ($menu as $item) {
                $slug = isset($item[2]) ? (string) $item[2] : '';
                if ($slug !== FLACSO_Admin_Panel::PAGE_SLUG) {
                    remove_menu_page($slug);
                }
            }
        }

        $allowed = [
            FLACSO_Admin_Panel::PAGE_SLUG,
            'edit.php?post_type=oferta-academica',
            'edit.php?post_type=seminario',
        ];

        if (isset($submenu[FLACSO_Admin_Panel::PAGE_SLUG]) && is_array($submenu[FLACSO_Admin_Panel::PAGE_SLUG])) {
            $submenu[FLACSO_Admin_Panel::PAGE_SLUG] = array_values(array_filter(
                $submenu[FLACSO_Admin_Panel::PAGE_SLUG],
                static function (array $item) use ($allowed): bool {
                    return isset($item[2]) && in_array((string) $item[2], $allowed, true);
                }
            ));
        } else {
            $submenu[FLACSO_Admin_Panel::PAGE_SLUG] = [];
        }

        $submenu[FLACSO_Admin_Panel::PAGE_SLUG][] = [
            __('Personas / Equipo', 'flacso-uruguay'),
            self::EDIT_TEACHERS,
            'edit.php?post_type=docente',
        ];
        $submenu[FLACSO_Admin_Panel::PAGE_SLUG][] = [
            __('Preinscripciones ↗', 'flacso-uruguay'),
            self::VIEW_EXTERNAL_SERVICES,
            self::preinscripciones_url(),
        ];
        $submenu[FLACSO_Admin_Panel::PAGE_SLUG][] = [
            __('Sala Virtual ↗', 'flacso-uruguay'),
            self::VIEW_EXTERNAL_SERVICES,
            self::sala_virtual_url(),
        ];
    }

    public static function simplify_admin_bar(WP_Admin_Bar $admin_bar): void {
        if (!self::is_assistant()) {
            return;
        }

        foreach ([
            'wp-logo',
            'updates',
            'comments',
            'new-content',
            'customize',
            'themes',
            'widgets',
            'menus',
        ] as $node_id) {
            $admin_bar->remove_node($node_id);
        }

        if (self::is_assistant()) {
            foreach (['site-name-flacso-tablas', 'site-name-flacso-portada'] as $node_id) {
                $admin_bar->remove_node($node_id);
            }
        }
    }

    /**
     * Navegación mínima para la barra superior de una Asistente Académica.
     *
     * @return array<string,array{title:string,href:string}>
     */
    public static function assistant_admin_bar_items(): array {
        return [
            'resumen'    => ['title' => __('Panel FLACSO', 'flacso-uruguay'), 'href' => admin_url('admin.php?page=' . FLACSO_Admin_Panel::PAGE_SLUG)],
            'ofertas'    => ['title' => __('Ofertas Académicas', 'flacso-uruguay'), 'href' => admin_url('edit.php?post_type=oferta-academica')],
            'seminarios' => ['title' => __('Seminarios', 'flacso-uruguay'), 'href' => admin_url('edit.php?post_type=seminario')],
            'docentes'   => ['title' => __('Personas / Equipo', 'flacso-uruguay'), 'href' => admin_url('edit.php?post_type=docente')],
        ];
    }

    public static function redirect_dashboard(): void {
        if (!self::is_assistant() || wp_doing_ajax()) {
            return;
        }

        global $pagenow;
        if ($pagenow === 'index.php') {
            wp_safe_redirect(admin_url('admin.php?page=' . FLACSO_Admin_Panel::PAGE_SLUG));
            exit;
        }
    }

    public static function login_redirect(string $redirect_to, string $requested_redirect_to, $user): string {
        if ($user instanceof WP_User && self::is_assistant($user)) {
            return admin_url('admin.php?page=' . FLACSO_Admin_Panel::PAGE_SLUG);
        }
        return $redirect_to;
    }

    public static function guard_screens($screen): void {
        if (!self::is_assistant() || wp_doing_ajax() || !$screen instanceof WP_Screen) {
            return;
        }

        $post_type = isset($screen->post_type) ? (string) $screen->post_type : '';
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if ($screen->base === 'post' && $post_type === 'oferta-academica' && $screen->action === 'add') {
            wp_safe_redirect(add_query_arg(
                'flacso_notice',
                'offer_creation_blocked',
                admin_url('edit.php?post_type=oferta-academica')
            ));
            exit;
        }

        $allowed_post_types = ['oferta-academica', 'cohorte', 'seminario', 'edicion', 'docente'];
        if ($post_type !== '' && !in_array($post_type, $allowed_post_types, true)) {
            wp_die(
                esc_html__('Esta sección no forma parte de la gestión académica habilitada para tu usuario.', 'flacso-uruguay'),
                esc_html__('Acceso restringido', 'flacso-uruguay'),
                ['response' => 403]
            );
        }

        if ($page !== '' && $page !== FLACSO_Admin_Panel::PAGE_SLUG) {
            wp_die(
                esc_html__('Esta herramienta está reservada para usuarios administradores.', 'flacso-uruguay'),
                esc_html__('Acceso restringido', 'flacso-uruguay'),
                ['response' => 403]
            );
        }
    }

    public static function filter_row_actions(array $actions, WP_Post $post): array {
        if (!self::is_assistant()) {
            return $actions;
        }

        if (!in_array($post->post_type, ['oferta-academica', 'cohorte', 'seminario', 'edicion', 'docente'], true)) {
            return $actions;
        }

        foreach (['trash', 'delete', 'inline hide-if-no-js', 'clone', 'duplicate'] as $key) {
            unset($actions[$key]);
        }

        return $actions;
    }

    public static function admin_body_class(string $classes): string {
        if (self::is_assistant()) {
            $classes .= ' flacso-academic-assistant';
        }
        return $classes;
    }

    public static function enqueue_assets(): void {
        if (!self::is_assistant()) {
            return;
        }

        $relative = 'includes/assets/flacso-academic-assistant.css';
        $path = FLACSO_URUGUAY_PATH . $relative;
        wp_enqueue_style(
            'flacso-academic-assistant',
            FLACSO_URUGUAY_URL . $relative,
            [],
            is_readable($path) ? (string) filemtime($path) : FLACSO_URUGUAY_VERSION
        );
    }

    public static function preinscripciones_url(): string {
        return (string) apply_filters(
            'flacso_preinscripciones_admin_url',
            'https://preinscripciones.flacso.edu.uy'
        );
    }

    public static function sala_virtual_url(): string {
        return (string) apply_filters(
            'flacso_sala_virtual_admin_url',
            'https://salavirtual.flacso.edu.uy'
        );
    }
}

FLACSO_Academic_Assistant::init();
