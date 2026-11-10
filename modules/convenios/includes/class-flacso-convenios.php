<?php
/**
 * Gestión del tipo de contenido Convenio.
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
