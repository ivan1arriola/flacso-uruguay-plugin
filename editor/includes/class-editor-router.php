<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Primera etapa del nuevo editor académico: navegación bidireccional segura.
 *
 * No guarda datos ni sustituye metaboxes: las vistas preliminares son de
 * consulta hasta migrar los formularios a servicios PHP compartidos.
 */
final class FLACSO_Editor_Router {
    public const PAGE_SLUG = 'flacso-editor';

    private const POST_TYPES = ['oferta-academica', 'cohorte'];
    private static $page_hook = '';
    private static ?FLACSO_Editor_Repository_Interface $repository = null;
    private static ?FLACSO_Editor_Controller_Factory $factory = null;

    public static function init(
        FLACSO_Editor_Repository_Interface $repository,
        FLACSO_Editor_Controller_Factory $factory
    ): void {
        self::$repository = $repository;
        self::$factory = $factory;
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [self::class, 'register_page'], 30);
        add_action('edit_form_top', [self::class, 'render_legacy_switch'], 20);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function register_page(): void {
        self::$page_hook = (string) add_submenu_page(
            FLACSO_Admin_Panel::PAGE_SLUG,
            __('Editor académico FLACSO', 'flacso-uruguay'),
            __('Editor académico (nuevo)', 'flacso-uruguay'),
            FLACSO_Academic_Assistant::ACCESS,
            self::PAGE_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function new_url(int $post_id): string {
        return add_query_arg(
            [
                'page' => self::PAGE_SLUG,
                'post' => $post_id,
            ],
            admin_url('admin.php')
        );
    }

    public static function render_legacy_switch($post): void {
        if (!$post instanceof WP_Post || !in_array($post->post_type, self::POST_TYPES, true)) {
            return;
        }
        if (!current_user_can('edit_post', $post->ID)) {
            return;
        }

        echo '<div class="flacso-editor-switch" role="region" aria-label="';
        echo esc_attr__('Cambiar de editor académico', 'flacso-uruguay');
        echo '">';
        echo '<div class="flacso-editor-switch__copy"><strong>';
        esc_html_e('Editor académico FLACSO', 'flacso-uruguay');
        echo '</strong><span>';
        esc_html_e('Probá la nueva interfaz sin modificar el editor actual.', 'flacso-uruguay');
        echo '</span></div>';

        // El auto-draft inicial no tiene cambios guardados y no debe
        // trasladarse a una vista que aún no implementa la creación.
        if ($post->post_status === 'auto-draft') {
            echo '<span class="flacso-editor-switch__pending">';
            esc_html_e('Guardá primero el borrador para abrir el nuevo editor.', 'flacso-uruguay');
            echo '</span>';
        } else {
            echo '<a class="button button-secondary flacso-editor-switch__link" href="';
            echo esc_url(self::new_url((int) $post->ID));
            echo '">';
            esc_html_e('Abrir nuevo editor', 'flacso-uruguay');
            echo ' &rarr;</a>';
        }
        echo '</div>';
    }

    public static function enqueue_assets(string $hook): void {
        $is_new_page = self::$page_hook !== '' && $hook === self::$page_hook;
        $screen = get_current_screen();
        $is_old_editor = $screen instanceof WP_Screen
            && $screen->base === 'post'
            && in_array((string) $screen->post_type, self::POST_TYPES, true);

        if (!$is_new_page && !$is_old_editor) {
            return;
        }

        $relative_css = 'editor/assets/css/editor.css';
        $absolute_css = FLACSO_URUGUAY_PATH . $relative_css;
        wp_enqueue_style(
            'flacso-editor-switch',
            FLACSO_URUGUAY_URL . $relative_css,
            [],
            is_readable($absolute_css) ? (string) filemtime($absolute_css) : FLACSO_URUGUAY_VERSION
        );

        if ($is_old_editor) {
            $relative_js = 'editor/assets/js/editor-switch.js';
            $absolute_js = FLACSO_URUGUAY_PATH . $relative_js;
            wp_enqueue_script(
                'flacso-editor-switch',
                FLACSO_URUGUAY_URL . $relative_js,
                [],
                is_readable($absolute_js) ? (string) filemtime($absolute_js) : FLACSO_URUGUAY_VERSION,
                true
            );
        }
    }

    public static function render_page(): void {
        if (!current_user_can(FLACSO_Academic_Assistant::ACCESS)) {
            wp_die(
                esc_html__('No tenés permisos para acceder al editor académico.', 'flacso-uruguay'),
                '',
                ['response' => 403]
            );
        }

        $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;
        if ($post_id === 0) {
            self::render_landing();
            return;
        }

        // El router sólo controla rutas y autorizaciones. No consulta CPT,
        // metadatos ni relaciones del dominio: eso pertenece al adaptador.
        $entity = self::$repository->find($post_id);
        if (
            !is_array($entity)
            || !in_array($entity['type'] ?? '', self::POST_TYPES, true)
            || !self::$repository->can_edit($post_id)
        ) {
            wp_die(
                esc_html__('No existe el contenido o no tenés permisos para editarlo.', 'flacso-uruguay'),
                '',
                ['response' => 403]
            );
        }

        // Evitar abrir como editable algo que WordPress todavía no guardó.
        if (($entity['status'] ?? '') === 'auto-draft') {
            wp_die(
                esc_html__('Guardá primero el borrador en el editor actual.', 'flacso-uruguay'),
                '',
                ['response' => 400]
            );
        }

        $legacy_url = self::$repository->legacy_edit_url($post_id);
        if ($legacy_url === '') {
            wp_die(esc_html__('No se puede abrir el editor actual.', 'flacso-uruguay'));
        }

        try {
            $controller = self::$factory->for_type((string) $entity['type']);
            $model = $controller->build_view_model($entity);
        } catch (InvalidArgumentException $exception) {
            wp_die(esc_html__('El tipo de contenido no está disponible en este editor.', 'flacso-uruguay'));
        }

        // Contrato de presentación: la vista sólo recibe arrays normalizados.
        $is_offer = (bool) $model['is_offer'];
        $entity = $model['entity'];
        $fields = (array) ($entity['fields'] ?? []);
        $cohorts = (array) $model['cohorts'];
        $parent = $model['parent'];
        $parent_url = is_array($parent) && !empty($parent['id'])
            ? self::new_url((int) $parent['id'])
            : '';

        require FLACSO_URUGUAY_PATH . 'editor/views/academic.php';
    }

    private static function render_landing(): void {
        ?>
        <div class="wrap flacso-new-editor">
            <div class="flacso-new-editor__card flacso-new-editor__landing">
                <p class="flacso-new-editor__eyebrow"><?php esc_html_e('FLACSO / Editor académico', 'flacso-uruguay'); ?></p>
                <h1><?php esc_html_e('Nuevo editor académico', 'flacso-uruguay'); ?></h1>
                <p><?php esc_html_e('Seleccioná una oferta o cohorte existente desde el catálogo académico y elegí «Abrir nuevo editor».', 'flacso-uruguay'); ?></p>
                <a class="button button-primary" href="<?php echo esc_url(admin_url('edit.php?post_type=oferta-academica')); ?>">
                    <?php esc_html_e('Ir a ofertas académicas', 'flacso-uruguay'); ?>
                </a>
            </div>
        </div>
        <?php
    }
}
