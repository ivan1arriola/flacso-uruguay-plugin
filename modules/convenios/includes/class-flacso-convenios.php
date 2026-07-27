<?php
/**
 * CPT Convenio y migracion desde entradas de la categoria convenios.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Flacso_Convenios {
    public const POST_TYPE = 'convenio';
    public const META_EXTERNAL_URL = '_flacso_convenio_external_url';
    public const META_SOURCE_POST_ID = '_flacso_convenio_source_post_id';
    public const META_MIGRATED_ID = '_flacso_convenio_migrated_id';
    private const MIGRATION_ACTION = 'flacso_migrate_convenios';
    private const MIGRATION_NONCE = 'flacso_migrate_convenios_nonce';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('init', [__CLASS__, 'register_meta']);
        add_action('init', [__CLASS__, 'maybe_flush_rewrite_rules'], 30);
        add_action('add_meta_boxes_' . self::POST_TYPE, [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [__CLASS__, 'save_fields'], 20, 2);
        add_action('save_post_' . self::POST_TYPE, [__CLASS__, 'enforce_required_fields'], 100, 2);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [__CLASS__, 'admin_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [__CLASS__, 'render_admin_column'], 10, 2);
        add_action('admin_notices', [__CLASS__, 'required_fields_notice']);
        add_action('admin_menu', [__CLASS__, 'register_migration_page']);
        add_action('admin_post_' . self::MIGRATION_ACTION, [__CLASS__, 'handle_migration']);
        add_action('template_redirect', [__CLASS__, 'redirect_migrated_source']);
        add_action('save_post_' . self::POST_TYPE, [__CLASS__, 'flush_list_cache']);
        add_action('deleted_post', [__CLASS__, 'flush_list_cache']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Convenios', 'flacso-uruguay'),
                'singular_name' => __('Convenio', 'flacso-uruguay'),
                'add_new' => __('Agregar convenio', 'flacso-uruguay'),
                'add_new_item' => __('Agregar convenio', 'flacso-uruguay'),
                'edit_item' => __('Editar convenio', 'flacso-uruguay'),
                'new_item' => __('Nuevo convenio', 'flacso-uruguay'),
                'view_item' => __('Ver convenio', 'flacso-uruguay'),
                'view_items' => __('Ver convenios', 'flacso-uruguay'),
                'search_items' => __('Buscar convenios', 'flacso-uruguay'),
                'not_found' => __('No se encontraron convenios.', 'flacso-uruguay'),
                'not_found_in_trash' => __('No hay convenios en la papelera.', 'flacso-uruguay'),
                'all_items' => __('Todos los convenios', 'flacso-uruguay'),
                'featured_image' => __('Imagen del convenio', 'flacso-uruguay'),
                'set_featured_image' => __('Seleccionar imagen del convenio', 'flacso-uruguay'),
                'remove_featured_image' => __('Quitar imagen del convenio', 'flacso-uruguay'),
                'use_featured_image' => __('Usar como imagen del convenio', 'flacso-uruguay'),
            ],
            'public' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-awards',
            'menu_position' => 27,
            'has_archive' => 'convenios',
            'rewrite' => [
                'slug' => 'convenio',
                'with_front' => false,
            ],
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
            'show_in_nav_menus' => true,
            'exclude_from_search' => false,
            'publicly_queryable' => true,
            'map_meta_cap' => true,
        ]);
    }

    public static function register_meta(): void {
        register_post_meta(self::POST_TYPE, self::META_EXTERNAL_URL, [
            'type' => 'string',
            'single' => true,
            'default' => '',
            'sanitize_callback' => 'esc_url_raw',
            'show_in_rest' => [
                'schema' => [
                    'type' => 'string',
                    'format' => 'uri',
                    'context' => ['view', 'edit'],
                ],
            ],
            'auth_callback' => static function (): bool {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public static function maybe_flush_rewrite_rules(): void {
        $version = '1';
        if (get_option('flacso_convenios_rewrite_version') === $version) {
            return;
        }
        flush_rewrite_rules(false);
        update_option('flacso_convenios_rewrite_version', $version, false);
    }

    public static function add_meta_boxes(): void {
        add_meta_box(
            'flacso-convenio-details',
            __('Datos del convenio', 'flacso-uruguay'),
            [__CLASS__, 'render_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function render_meta_box(WP_Post $post): void {
        wp_nonce_field('flacso_convenio_save', 'flacso_convenio_nonce');
        $external_url = (string) get_post_meta($post->ID, self::META_EXTERNAL_URL, true);
        ?>
        <p>
            <label for="flacso-convenio-external-url"><strong><?php esc_html_e('Link externo (opcional)', 'flacso-uruguay'); ?></strong></label>
        </p>
        <input
            class="widefat"
            type="url"
            id="flacso-convenio-external-url"
            name="flacso_convenio_external_url"
            value="<?php echo esc_attr($external_url); ?>"
            placeholder="https://"
        >
        <p class="description"><?php esc_html_e('Se mostrará como acción adicional en la página individual. El nombre y la imagen destacada son obligatorios para publicar.', 'flacso-uruguay'); ?></p>
        <?php
    }

    public static function save_fields(int $post_id, WP_Post $post): void {
        if (
            !isset($_POST['flacso_convenio_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flacso_convenio_nonce'])), 'flacso_convenio_save')
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || !current_user_can('edit_post', $post_id)
        ) {
            return;
        }

        $url = isset($_POST['flacso_convenio_external_url'])
            ? esc_url_raw(wp_unslash($_POST['flacso_convenio_external_url']))
            : '';

        if ($url === '') {
            delete_post_meta($post_id, self::META_EXTERNAL_URL);
        } else {
            update_post_meta($post_id, self::META_EXTERNAL_URL, $url);
        }
    }

    public static function enforce_required_fields(int $post_id, WP_Post $post): void {
        static $updating = false;
        if ($updating || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || $post->post_status !== 'publish') {
            return;
        }

        $missing = [];
        if (trim(wp_strip_all_tags($post->post_title)) === '') {
            $missing[] = __('nombre', 'flacso-uruguay');
        }
        $submitted_thumbnail_id = isset($_POST['_thumbnail_id'])
            ? absint(wp_unslash($_POST['_thumbnail_id']))
            : 0;
        if (!has_post_thumbnail($post_id) && $submitted_thumbnail_id < 1) {
            $missing[] = __('imagen', 'flacso-uruguay');
        }
        if (empty($missing)) {
            return;
        }

        $updating = true;
        wp_update_post([
            'ID' => $post_id,
            'post_status' => 'draft',
        ]);
        $updating = false;
        set_transient(
            'flacso_convenio_required_' . get_current_user_id(),
            sprintf(__('No se publicó el convenio. Falta: %s.', 'flacso-uruguay'), implode(', ', $missing)),
            MINUTE_IN_SECONDS
        );
    }

    public static function required_fields_notice(): void {
        $key = 'flacso_convenio_required_' . get_current_user_id();
        $message = get_transient($key);
        if (!$message) {
            return;
        }
        delete_transient($key);
        printf('<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html($message));
    }

    public static function admin_columns(array $columns): array {
        $result = [];
        foreach ($columns as $key => $label) {
            if ($key === 'title') {
                $result['thumbnail'] = __('Imagen', 'flacso-uruguay');
            }
            $result[$key] = $label;
            if ($key === 'title') {
                $result['external_url'] = __('Link externo', 'flacso-uruguay');
            }
        }
        return $result;
    }

    public static function render_admin_column(string $column, int $post_id): void {
        if ($column === 'thumbnail') {
            echo get_the_post_thumbnail($post_id, [64, 64]) ?: '—';
        }
        if ($column === 'external_url') {
            $url = (string) get_post_meta($post_id, self::META_EXTERNAL_URL, true);
            echo $url !== '' ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Abrir', 'flacso-uruguay') . '</a>' : '—';
        }
    }

    public static function register_migration_page(): void {
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            __('Migrar convenios', 'flacso-uruguay'),
            __('Migrar entradas', 'flacso-uruguay'),
            'manage_options',
            'flacso-convenios-migration',
            [__CLASS__, 'render_migration_page']
        );
    }

    private static function source_posts(): array {
        return get_posts([
            'post_type' => 'post',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'category_name' => 'convenios',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);
    }

    private static function existing_target_id(int $source_id): int {
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => self::META_SOURCE_POST_ID,
            'meta_value' => $source_id,
        ]);
        return isset($ids[0]) ? (int) $ids[0] : 0;
    }

    private static function clean_source_title(string $title): string {
        $title = html_entity_decode(wp_strip_all_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = preg_replace('/^Convenio\s*\p{Pd}\s*/iu', '', $title);
        if ($clean === $title) {
            $clean = preg_replace('/^Convenio\W+\s*/iu', '', $title);
        }
        return trim((string) $clean);
    }

    private static function source_external_url(int $source_id): string {
        foreach (['enlace_externo', 'url_externa', 'link_externo', 'convenio_url', self::META_EXTERNAL_URL] as $key) {
            $url = esc_url_raw((string) get_post_meta($source_id, $key, true));
            if ($url !== '') {
                return $url;
            }
        }
        return '';
    }

    public static function render_migration_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $sources = self::source_posts();
        $ready = 0;
        $migrated = 0;
        $without_image = 0;
        foreach ($sources as $source) {
            if (self::existing_target_id((int) $source->ID)) {
                $migrated++;
            } elseif (!has_post_thumbnail($source->ID)) {
                $without_image++;
            } else {
                $ready++;
            }
        }
        $report = get_transient('flacso_convenios_migration_report_' . get_current_user_id());
        delete_transient('flacso_convenios_migration_report_' . get_current_user_id());
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Migrar convenios', 'flacso-uruguay'); ?></h1>
            <p><?php esc_html_e('Copia las entradas de la categoría “convenios” al nuevo CPT. No elimina ni modifica las entradas originales y puede ejecutarse más de una vez sin duplicar registros.', 'flacso-uruguay'); ?></p>
            <table class="widefat striped" style="max-width:760px">
                <tbody>
                    <tr><th><?php esc_html_e('Entradas encontradas', 'flacso-uruguay'); ?></th><td><?php echo esc_html((string) count($sources)); ?></td></tr>
                    <tr><th><?php esc_html_e('Listas para migrar', 'flacso-uruguay'); ?></th><td><?php echo esc_html((string) $ready); ?></td></tr>
                    <tr><th><?php esc_html_e('Ya migradas', 'flacso-uruguay'); ?></th><td><?php echo esc_html((string) $migrated); ?></td></tr>
                    <tr><th><?php esc_html_e('Sin imagen obligatoria', 'flacso-uruguay'); ?></th><td><?php echo esc_html((string) $without_image); ?></td></tr>
                </tbody>
            </table>
            <?php if (is_array($report)) : ?>
                <div class="notice notice-<?php echo empty($report['errors']) ? 'success' : 'warning'; ?> inline"><p>
                    <?php
                    printf(
                        esc_html__('Migrados: %1$d. Omitidos por falta de imagen: %2$d. Ya existentes: %3$d. Errores: %4$d.', 'flacso-uruguay'),
                        (int) $report['created'],
                        (int) $report['without_image'],
                        (int) $report['existing'],
                        count($report['errors'])
                    );
                    ?>
                </p></div>
            <?php endif; ?>
            <?php if ($ready > 0) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:20px">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::MIGRATION_ACTION); ?>">
                    <?php wp_nonce_field(self::MIGRATION_NONCE); ?>
                    <?php submit_button(__('Ejecutar migración', 'flacso-uruguay'), 'primary', 'submit', false); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_migration(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tenés permisos para ejecutar esta migración.', 'flacso-uruguay'));
        }
        check_admin_referer(self::MIGRATION_NONCE);

        $report = [
            'created' => 0,
            'without_image' => 0,
            'existing' => 0,
            'errors' => [],
        ];

        foreach (self::source_posts() as $source) {
            $source_id = (int) $source->ID;
            $existing_id = self::existing_target_id($source_id);
            if ($existing_id) {
                update_post_meta($source_id, self::META_MIGRATED_ID, $existing_id);
                $report['existing']++;
                continue;
            }
            $thumbnail_id = get_post_thumbnail_id($source_id);
            if (!$thumbnail_id) {
                $report['without_image']++;
                continue;
            }
            $title = self::clean_source_title((string) $source->post_title);
            if ($title === '') {
                $report['errors'][] = sprintf('Post %d: nombre vacío.', $source_id);
                continue;
            }

            $target_status = $source->post_status;
            $target_id = wp_insert_post([
                'post_type' => self::POST_TYPE,
                'post_status' => 'draft',
                'post_title' => $title,
                'post_name' => $source->post_name,
                'post_content' => $source->post_content,
                'post_excerpt' => $source->post_excerpt,
                'post_author' => $source->post_author,
                'post_date' => $source->post_date,
                'post_date_gmt' => $source->post_date_gmt,
                'comment_status' => 'closed',
                'ping_status' => 'closed',
                'meta_input' => [
                    self::META_SOURCE_POST_ID => $source_id,
                    self::META_EXTERNAL_URL => self::source_external_url($source_id),
                ],
            ], true);

            if (is_wp_error($target_id)) {
                $report['errors'][] = sprintf('Post %d: %s', $source_id, $target_id->get_error_message());
                continue;
            }

            set_post_thumbnail($target_id, $thumbnail_id);
            if ($target_status !== 'draft') {
                wp_update_post([
                    'ID' => (int) $target_id,
                    'post_status' => $target_status,
                ]);
            }
            update_post_meta($source_id, self::META_MIGRATED_ID, (int) $target_id);
            $report['created']++;
        }

        self::flush_list_cache();
        set_transient('flacso_convenios_migration_report_' . get_current_user_id(), $report, 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('edit.php?post_type=' . self::POST_TYPE . '&page=flacso-convenios-migration'));
        exit;
    }

    public static function redirect_migrated_source(): void {
        if (!is_singular('post')) {
            return;
        }
        $target_id = (int) get_post_meta(get_queried_object_id(), self::META_MIGRATED_ID, true);
        if ($target_id > 0 && get_post_status($target_id) === 'publish') {
            wp_safe_redirect(get_permalink($target_id), 301);
            exit;
        }
    }

    public static function flush_list_cache(): void {
        if (function_exists('flacso_convenios_flush_cache')) {
            flacso_convenios_flush_cache();
        } else {
            delete_transient('flacso_convenios_dataset_v2');
        }
    }
}
