<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra las taxonomías del CPT oferta-academica
 */
class Oferta_Taxonomies {
    private const TERM_IMAGE_META_KEY = 'featured_image_id';

    public static function init(): void {
        self::register_taxonomies();
        self::register_term_meta_fields();
        self::register_term_admin_hooks();

        add_action('init', [__CLASS__, 'create_default_terms'], 20);
        add_action('init', [__CLASS__, 'register_rewrite_rules'], 10);
        add_action('rest_api_init', [__CLASS__, 'register_rest_fields']);
        add_filter('term_link', [__CLASS__, 'filter_term_link'], 10, 3);
        add_action('template_redirect', [__CLASS__, 'redirect_old_taxonomy_urls']);
        add_action('pre_get_posts', [__CLASS__, 'exclude_password_protected_offers_from_public_lists']);
        add_action('admin_footer-edit-tags.php', [__CLASS__, 'print_term_image_admin_assets']);
    }

    public static function register_taxonomies(): void {
        $labels = [
            'name'              => 'Tipo de Oferta',
            'singular_name'     => 'Tipo de Oferta',
            'search_items'      => 'Buscar tipos',
            'all_items'         => 'Todos los tipos',
            'edit_item'         => 'Editar tipo',
            'update_item'       => 'Actualizar tipo',
            'add_new_item'      => 'Añadir nuevo tipo',
            'new_item_name'     => 'Nuevo tipo',
            'menu_name'         => 'Tipo de Oferta',
        ];

        $args = [
            'labels'            => $labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'tipo-oferta'],
        ];

        register_taxonomy('tipo-oferta-academica', ['oferta-academica'], $args);

        $labels_area = [
            'name'              => 'Programas',
            'singular_name'     => 'Programa',
            'search_items'      => 'Buscar programas',
            'all_items'         => 'Todos los programas',
            'edit_item'         => 'Editar programa',
            'update_item'       => 'Actualizar programa',
            'add_new_item'      => 'Añadir nuevo programa',
            'new_item_name'     => 'Nuevo programa',
            'menu_name'         => 'Programas',
        ];

        $args_area = [
            'labels'            => $labels_area,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'area-tematica'],
        ];

        register_taxonomy('area_tematica', ['oferta-academica'], $args_area);
    }

    public static function register_term_meta_fields(): void {
        foreach (self::supported_taxonomies() as $taxonomy) {
            register_term_meta($taxonomy, self::TERM_IMAGE_META_KEY, [
                'type' => 'integer',
                'single' => true,
                'sanitize_callback' => 'absint',
                'auth_callback' => [__CLASS__, 'can_manage_term_media'],
                'show_in_rest' => [
                    'schema' => [
                        'description' => __('ID de la imagen destacada del término', 'flacso-oferta-academica'),
                        'type' => 'integer',
                        'default' => 0,
                    ],
                ],
            ]);
        }
    }

    public static function register_rest_fields(): void {
        foreach (self::supported_taxonomies() as $taxonomy) {
            register_rest_field($taxonomy, 'featured_image_id', [
                'get_callback' => static function ($term_array) {
                    return self::get_term_featured_image_id((int) ($term_array['id'] ?? 0));
                },
                'schema' => [
                    'type' => 'integer',
                    'context' => ['view', 'edit'],
                ],
            ]);

            register_rest_field($taxonomy, 'featured_image_data', [
                'get_callback' => static function ($term_array) {
                    return self::get_term_featured_image_data((int) ($term_array['id'] ?? 0));
                },
                'schema' => null,
            ]);
        }
    }

    public static function can_manage_term_media(...$args): bool {
        return current_user_can('manage_categories');
    }

    public static function create_default_terms(): void {
        $tipos = [
            'Maestrías' => 'maestria',
            'Especializaciones' => 'especializacion',
            'Diplomados' => 'diplomado',
            'Diplomas' => 'diploma',
        ];

        foreach ($tipos as $name => $slug) {
            $term = get_term_by('slug', $slug, 'tipo-oferta-academica');
            if ($term && !is_wp_error($term)) {
                if ($term->name !== $name) {
                    wp_update_term($term->term_id, 'tipo-oferta-academica', ['name' => $name]);
                }
            } else {
                wp_insert_term($name, 'tipo-oferta-academica', ['slug' => $slug]);
            }
        }

        $areas = [
            'Educación' => 'educacion',
            'Género y Cultura' => 'genero-y-cultura',
            'Infancias y Adolescencias' => 'infancias-y-adolescencias',
            'Producción de Textos' => 'produccion-de-textos',
            'Salud Mental, Subjetividad y Trabajo' => 'salud-mental-subjetividad-y-trabajo',
        ];

        foreach ($areas as $name => $slug) {
            if (!term_exists($slug, 'area_tematica')) {
                wp_insert_term($name, 'area_tematica', ['slug' => $slug]);
            }
        }
    }

    public static function register_rewrite_rules(): void {
        $tipos = [
            'maestrias' => 'maestria',
            'especializaciones' => 'especializacion',
            'diplomados' => 'diplomado',
            'diplomas' => 'diploma',
        ];

        foreach ($tipos as $plural => $singular) {
            add_rewrite_rule(
                '^formacion/' . $plural . '/page/?([0-9]{1,})/?$',
                'index.php?tipo-oferta-academica=' . $singular . '&paged=$matches[1]',
                'top'
            );
            add_rewrite_rule(
                '^formacion/' . $plural . '/?$',
                'index.php?tipo-oferta-academica=' . $singular,
                'top'
            );
        }
    }

    public static function filter_term_link(string $url, $term, string $taxonomy): string {
        if ($taxonomy !== 'tipo-oferta-academica') {
            return $url;
        }

        $tipos = [
            'maestria' => 'maestrias',
            'especializacion' => 'especializaciones',
            'diplomado' => 'diplomados',
            'diploma' => 'diplomas',
        ];

        if (isset($tipos[$term->slug])) {
            return home_url('/formacion/' . $tipos[$term->slug] . '/');
        }

        return $url;
    }

    public static function redirect_old_taxonomy_urls(): void {
        if (!is_tax('tipo-oferta-academica')) {
            return;
        }

        $term = get_queried_object();
        if (!$term || !isset($term->slug)) {
            return;
        }

        $tipos = [
            'maestria' => 'maestrias',
            'especializacion' => 'especializaciones',
            'diplomado' => 'diplomados',
            'diploma' => 'diplomas',
        ];

        if (!isset($tipos[$term->slug])) {
            return;
        }

        $current_url = wp_unslash($_SERVER['REQUEST_URI'] ?? '');
        if (strpos($current_url, '/tipo-oferta/') !== false) {
            wp_safe_redirect(home_url('/formacion/' . $tipos[$term->slug] . '/'), 301);
            exit;
        }
    }

    public static function exclude_password_protected_offers_from_public_lists($query): void {
        if (!($query instanceof WP_Query) || is_admin() || !$query->is_main_query()) {
            return;
        }

        if (!$query->is_tax('tipo-oferta-academica')) {
            return;
        }

        $query->set('has_password', false);
        $query->set('posts_per_page', -1);
        $query->set('nopaging', true);
    }

    public static function serialize_term(WP_Term $term): array {
        return [
            'id' => (int) $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => (string) $term->description,
            'featured_image_id' => self::get_term_featured_image_id((int) $term->term_id),
            'featured_image_data' => self::get_term_featured_image_data((int) $term->term_id),
        ];
    }

    public static function get_term_featured_image_id(int $term_id): int {
        if ($term_id <= 0) {
            return 0;
        }

        return max(0, (int) get_term_meta($term_id, self::TERM_IMAGE_META_KEY, true));
    }

    public static function get_term_featured_image_data($term): ?array {
        $term_id = $term instanceof WP_Term ? (int) $term->term_id : (int) $term;
        $media_id = self::get_term_featured_image_id($term_id);

        return self::build_attachment_image_data($media_id);
    }

    private static function build_attachment_image_data(int $media_id): ?array {
        if ($media_id <= 0) {
            return null;
        }

        $full = wp_get_attachment_image_src($media_id, 'full');
        if (!$full) {
            return null;
        }

        $large = wp_get_attachment_image_src($media_id, 'large');
        $medium = wp_get_attachment_image_src($media_id, 'medium');
        $alt = get_post_meta($media_id, '_wp_attachment_image_alt', true);

        return [
            'id' => $media_id,
            'url' => $full[0] ?? '',
            'large' => $large[0] ?? ($full[0] ?? ''),
            'medium' => $medium[0] ?? ($large[0] ?? ($full[0] ?? '')),
            'alt' => is_string($alt) ? $alt : '',
            'width' => (int) ($full[1] ?? 0),
            'height' => (int) ($full[2] ?? 0),
        ];
    }

    public static function render_add_term_image_field(string $taxonomy): void {
        if (!self::is_supported_taxonomy($taxonomy)) {
            return;
        }

        wp_nonce_field('flacso_term_image_action', 'flacso_term_image_nonce');
        ?>
        <div class="form-field term-group">
            <label for="flacso-term-featured-image-id"><?php esc_html_e('Imagen destacada', 'flacso-oferta-academica'); ?></label>
            <?php self::render_term_image_control(0); ?>
            <p class="description"><?php esc_html_e('Esta imagen se puede usar en la página pública del tipo de oferta y en la app.', 'flacso-oferta-academica'); ?></p>
        </div>
        <?php
    }

    public static function render_edit_term_image_field(WP_Term $term, string $taxonomy = ''): void {
        $current_taxonomy = $taxonomy !== '' ? $taxonomy : $term->taxonomy;
        if (!self::is_supported_taxonomy($current_taxonomy)) {
            return;
        }

        $image_id = self::get_term_featured_image_id((int) $term->term_id);
        wp_nonce_field('flacso_term_image_action', 'flacso_term_image_nonce');
        ?>
        <tr class="form-field term-group-wrap">
            <th scope="row">
                <label for="flacso-term-featured-image-id"><?php esc_html_e('Imagen destacada', 'flacso-oferta-academica'); ?></label>
            </th>
            <td>
                <?php self::render_term_image_control($image_id); ?>
                <p class="description"><?php esc_html_e('Esta imagen se puede usar en la página pública del tipo de oferta y en la app.', 'flacso-oferta-academica'); ?></p>
            </td>
        </tr>
        <?php
    }

    public static function save_term_image_meta(int $term_id, ...$args): void {
        if (
            !isset($_POST['flacso_term_image_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flacso_term_image_nonce'])), 'flacso_term_image_action')
        ) {
            return;
        }

        if (!current_user_can('manage_categories')) {
            return;
        }

        $image_id = isset($_POST['flacso_term_featured_image_id'])
            ? absint(wp_unslash($_POST['flacso_term_featured_image_id']))
            : 0;

        if ($image_id > 0) {
            update_term_meta($term_id, self::TERM_IMAGE_META_KEY, $image_id);
            return;
        }

        delete_term_meta($term_id, self::TERM_IMAGE_META_KEY);
    }

    public static function print_term_image_admin_assets(): void {
        if (!is_admin() || !function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'edit-tags' || !self::is_supported_taxonomy((string) $screen->taxonomy)) {
            return;
        }

        wp_enqueue_media();
        ?>
        <style>
            .flacso-term-image-control {
                display: grid;
                gap: 12px;
                max-width: 520px;
            }

            .flacso-term-image-control__preview {
                width: 100%;
                max-width: 280px;
                aspect-ratio: 16 / 10;
                border: 1px solid #d0d7de;
                border-radius: 12px;
                overflow: hidden;
                background: #f6f8fa;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .flacso-term-image-control__preview img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .flacso-term-image-control__placeholder {
                padding: 16px;
                text-align: center;
                color: #667085;
                font-size: 13px;
                line-height: 1.4;
            }

            .flacso-term-image-control__actions {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
        </style>
        <script>
            (function ($) {
                function renderPreview(container, url, altText) {
                    const preview = container.find('.flacso-term-image-control__preview');
                    if (!url) {
                        preview.html('<div class="flacso-term-image-control__placeholder"><?php echo esc_js(__('Sin imagen seleccionada', 'flacso-oferta-academica')); ?></div>');
                        return;
                    }

                    const alt = altText ? altText.replace(/"/g, '&quot;') : '';
                    preview.html('<img src="' + url + '" alt="' + alt + '" />');
                }

                $(document).on('click', '.flacso-term-image-select', function (event) {
                    event.preventDefault();

                    const button = $(this);
                    const container = button.closest('.flacso-term-image-control');
                    const input = container.find('.flacso-term-image-control__input');

                    const frame = wp.media({
                        title: '<?php echo esc_js(__('Seleccionar imagen destacada', 'flacso-oferta-academica')); ?>',
                        button: {
                            text: '<?php echo esc_js(__('Usar esta imagen', 'flacso-oferta-academica')); ?>'
                        },
                        multiple: false
                    });

                    frame.on('select', function () {
                        const attachment = frame.state().get('selection').first().toJSON();
                        input.val(attachment.id || '');
                        renderPreview(container, attachment.sizes?.medium?.url || attachment.sizes?.large?.url || attachment.url || '', attachment.alt || '');
                    });

                    frame.open();
                });

                $(document).on('click', '.flacso-term-image-remove', function (event) {
                    event.preventDefault();

                    const button = $(this);
                    const container = button.closest('.flacso-term-image-control');
                    container.find('.flacso-term-image-control__input').val('');
                    renderPreview(container, '', '');
                });
            })(jQuery);
        </script>
        <?php
    }

    private static function register_term_admin_hooks(): void {
        foreach (self::supported_taxonomies() as $taxonomy) {
            add_action($taxonomy . '_add_form_fields', [__CLASS__, 'render_add_term_image_field']);
            add_action($taxonomy . '_edit_form_fields', [__CLASS__, 'render_edit_term_image_field']);
            add_action('created_' . $taxonomy, [__CLASS__, 'save_term_image_meta']);
            add_action('edited_' . $taxonomy, [__CLASS__, 'save_term_image_meta']);
        }
    }

    private static function render_term_image_control(int $image_id): void {
        $image_data = self::build_attachment_image_data($image_id);
        $image_url = $image_data['medium'] ?? ($image_data['large'] ?? ($image_data['url'] ?? ''));
        $image_alt = $image_data['alt'] ?? '';
        ?>
        <div class="flacso-term-image-control">
            <input
                type="hidden"
                id="flacso-term-featured-image-id"
                class="flacso-term-image-control__input"
                name="flacso_term_featured_image_id"
                value="<?php echo esc_attr((string) $image_id); ?>"
            />
            <div class="flacso-term-image-control__preview">
                <?php if ($image_url) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>" />
                <?php else : ?>
                    <div class="flacso-term-image-control__placeholder"><?php esc_html_e('Sin imagen seleccionada', 'flacso-oferta-academica'); ?></div>
                <?php endif; ?>
            </div>
            <div class="flacso-term-image-control__actions">
                <button type="button" class="button button-secondary flacso-term-image-select">
                    <?php esc_html_e('Seleccionar imagen', 'flacso-oferta-academica'); ?>
                </button>
                <button type="button" class="button button-link-delete flacso-term-image-remove">
                    <?php esc_html_e('Quitar imagen', 'flacso-oferta-academica'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    private static function is_supported_taxonomy(string $taxonomy): bool {
        return in_array($taxonomy, self::supported_taxonomies(), true);
    }

    private static function supported_taxonomies(): array {
        return ['tipo-oferta-academica', 'area_tematica'];
    }
}
