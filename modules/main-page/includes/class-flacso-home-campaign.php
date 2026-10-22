<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Campaign source and resolver for the reversible home v2 rollout. */
final class FLACSO_Home_Campaign {
    public const POST_TYPE = 'flacso_home_campaign';
    public const META_KICKER = '_flacso_home_kicker';
    public const META_DESKTOP_IMAGE = '_flacso_home_desktop_image_id';
    public const META_MOBILE_IMAGE = '_flacso_home_mobile_image_id';
    public const META_START = '_flacso_home_start_at_utc';
    public const META_END = '_flacso_home_end_at_exclusive_utc';
    public const META_PRIORITY = '_flacso_home_priority';
    public const META_VARIANT = '_flacso_home_variant';
    public const OPTION_ENABLED = 'flacso_home_v2_enabled';

    public static function is_enabled(): bool {
        return (bool) get_option(self::OPTION_ENABLED, false);
    }

    public static function register(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Campañas de portada', 'flacso-uruguay'),
                'singular_name' => __('Campaña de portada', 'flacso-uruguay'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => false,
            'has_archive' => false,
            'rewrite' => false,
            'supports' => ['title', 'excerpt', 'revisions'], // Una sola interfaz visible para elegir la foto del hero.
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
        foreach ([self::META_KICKER, self::META_DESKTOP_IMAGE, self::META_MOBILE_IMAGE, self::META_START, self::META_END, self::META_PRIORITY, self::META_VARIANT, '_flacso_home_cta_primary_label', '_flacso_home_cta_primary_url', '_flacso_home_cta_secondary_label', '_flacso_home_cta_secondary_url'] as $key) {
            register_post_meta(self::POST_TYPE, $key, ['show_in_rest' => false, 'single' => true, 'type' => 'string', 'auth_callback' => static function () { return current_user_can('edit_posts'); }]);
        }
        add_action('add_meta_boxes_' . self::POST_TYPE, [__CLASS__, 'register_image_metabox']);
        add_action('save_post_' . self::POST_TYPE, [__CLASS__, 'save_images'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_image_editor']);
    }

    /** Fotografías editables en la misma pantalla que el título de la campaña. */
    public static function register_image_metabox(): void {
        add_meta_box(
            'flacso-home-campaign-photos',
            'Fotografías del encabezado de la portada',
            [__CLASS__, 'render_image_metabox'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function enqueue_image_editor(string $hook): void {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== self::POST_TYPE) {
            return;
        }
        wp_enqueue_media();
        $relative = 'assets/js/flacso-home-campaign-images.js';
        $path = FLACSO_MAIN_PAGE_MODULE_PATH . $relative;
        wp_enqueue_script(
            'flacso-home-campaign-images',
            FLACSO_MAIN_PAGE_MODULE_URL . $relative,
            [],
            file_exists($path) ? (string) filemtime($path) : FLACSO_MAIN_PAGE_VERSION,
            true
        );
    }

    public static function render_image_metabox($post): void {
        $data = self::from_post($post);
        wp_nonce_field('flacso_home_campaign_images', 'flacso_home_campaign_images_nonce');
        echo '<p>Elegí la fotografía que aparece junto al título. <strong>No agregues texto ni botones dentro de la imagen.</strong> Al terminar, hacé clic en <strong>Actualizar</strong> o <strong>Publicar</strong>.</p>';
        echo '<div class="flacso-home-campaign-image-fields" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px">';
        $fields = [
            'desktop' => [
                'label' => 'Imagen principal (computadoras)',
                'help' => 'Se muestra a la derecha del texto.',
                'id' => absint($data['desktop_image_id'] ?? 0),
            ],
            'mobile' => [
                'label' => 'Imagen para celulares (opcional)',
                'help' => 'Aparece arriba del texto. Si no elegís una, se utiliza la imagen principal.',
                'id' => absint($data['mobile_image_id'] ?? 0),
            ],
        ];
        foreach ($fields as $key => $field) {
            $url = $field['id'] ? wp_get_attachment_image_url($field['id'], 'large') : '';
            echo '<div class="flacso-home-campaign-image-field" style="padding:16px;background:#f7f9fc;border:1px solid #dcdcde;border-radius:10px">';
            echo '<h3 style="margin-top:0">' . esc_html($field['label']) . '</h3>';
            echo '<p>' . esc_html($field['help']) . '</p>';
            echo '<div style="background:#e9eef5;aspect-ratio:16/10;display:grid;place-items:center;margin-bottom:12px;overflow:hidden;border-radius:8px">';
            echo '<img alt="" src="' . esc_url((string) $url) . '" style="max-width:100%;width:100%;height:100%;object-fit:cover;' . ($url ? '' : 'display:none;') . '">';
            echo '<span ' . ($url ? 'hidden' : '') . '>No hay una imagen seleccionada</span>';
            echo '</div>';
            echo '<input type="hidden" name="flacso_campaign_image_' . esc_attr($key) . '" value="' . esc_attr($field['id']) . '">';
            echo '<button type="button" class="button button-primary flacso-campaign-image-choose">Elegir o cambiar foto</button> ';
            echo '<button type="button" class="button-link-delete flacso-campaign-image-remove">Quitar</button>';
            echo '</div>';
        }
        echo '</div>';
    }

    public static function save_images(int $post_id, $post): void {
        if (
            !isset($_POST['flacso_home_campaign_images_nonce'])
            || !is_string($_POST['flacso_home_campaign_images_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flacso_home_campaign_images_nonce'])), 'flacso_home_campaign_images')
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || wp_is_post_revision($post_id)
            || !current_user_can('edit_post', $post_id)
        ) {
            return;
        }
        $fields = [
            'flacso_campaign_image_desktop' => self::META_DESKTOP_IMAGE,
            'flacso_campaign_image_mobile' => self::META_MOBILE_IMAGE,
        ];
        foreach ($fields as $input => $meta_key) {
            if (!isset($_POST[$input]) || !is_scalar($_POST[$input])) {
                continue;
            }
            $id = absint($_POST[$input]);
            if ($id && !wp_attachment_is_image($id)) {
                continue;
            }
            update_post_meta($post_id, $meta_key, $id);
            if ($meta_key === self::META_DESKTOP_IMAGE) {
                if ($id) {
                    set_post_thumbnail($post_id, $id);
                } else {
                    delete_post_thumbnail($post_id);
                }
            }
        }
    }

    public static function fallback(): array {
        return [
            'id' => 0,
            'source' => 'fallback',
            'kicker' => '',
            'title' => 'Formación que conecta, conocimiento que transforma.',
            'description' => 'Conocé nuestra oferta de posgrados, especializaciones y seminarios.',
            'desktop_image_id' => 0,
            'mobile_image_id' => 0,
            'cta_primary' => ['label' => 'Conocé nuestra propuesta académica', 'url' => '/formacion/'],
            'cta_secondary' => null,
            'variant' => 'institutional',
            'start_at' => null,
            'end_at' => null,
        ];
    }

    public static function sanitize_url(string $url): string {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '//')) {
            return '';
        }
        $parts = wp_parse_url($url);
        if (is_array($parts) && isset($parts['scheme']) && !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return '';
        }
        if (isset($parts['host']) && strtolower((string) $parts['scheme']) !== 'https' && strpos((string) $parts['host'], 'flacso.edu.uy') !== false) {
            return '';
        }
        return esc_url_raw($url, ['http', 'https']);
    }

    public static function from_post($post): array {
        if (!$post || (int) $post->ID < 1) {
            return [];
        }
        $primary_url = self::sanitize_url((string) get_post_meta($post->ID, '_flacso_home_cta_primary_url', true));
        $primary_label = trim(wp_strip_all_tags((string) get_post_meta($post->ID, '_flacso_home_cta_primary_label', true)));
        $secondary_url = self::sanitize_url((string) get_post_meta($post->ID, '_flacso_home_cta_secondary_url', true));
        $secondary_label = trim(wp_strip_all_tags((string) get_post_meta($post->ID, '_flacso_home_cta_secondary_label', true)));
        $variant = sanitize_key((string) get_post_meta($post->ID, self::META_VARIANT, true));
        if (!in_array($variant, ['institutional', 'enrollment', 'event', 'call'], true)) {
            $variant = 'institutional';
        }
        $desktop_image_id = absint(get_post_meta($post->ID, self::META_DESKTOP_IMAGE, true));
        if (!$desktop_image_id) {
            $desktop_image_id = (int) get_post_thumbnail_id($post->ID);
        }
        return [
            'id' => (int) $post->ID,
            'source' => 'campaign',
            'kicker' => trim(wp_strip_all_tags((string) get_post_meta($post->ID, self::META_KICKER, true))),
            'title' => trim(wp_strip_all_tags((string) $post->post_title)),
            'description' => trim(wp_strip_all_tags((string) $post->post_excerpt)),
            'desktop_image_id' => $desktop_image_id,
            'mobile_image_id' => absint(get_post_meta($post->ID, self::META_MOBILE_IMAGE, true)),
            'cta_primary' => $primary_url !== '' && $primary_label !== '' ? ['label' => $primary_label, 'url' => $primary_url] : null,
            'cta_secondary' => $secondary_url !== '' && $secondary_label !== '' ? ['label' => $secondary_label, 'url' => $secondary_url] : null,
            'variant' => $variant,
            'start_at' => self::iso((int) get_post_meta($post->ID, self::META_START, true)),
            'end_at' => self::iso((int) get_post_meta($post->ID, self::META_END, true)),
        ];
    }

    private static function iso(int $timestamp): ?string {
        return $timestamp > 0 ? gmdate('c', $timestamp) : null;
    }

    public static function active(?DateTimeImmutable $at = null): array {
        $at = $at ?: new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $now = $at->getTimestamp();
        $posts = get_posts(['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 200, 'no_found_rows' => true]);
        $eligible = [];
        foreach ($posts as $post) {
            $start = (int) get_post_meta($post->ID, self::META_START, true);
            $end = (int) get_post_meta($post->ID, self::META_END, true);
            $priority = (int) get_post_meta($post->ID, self::META_PRIORITY, true);
            if ($start <= $now && (!$end || $now < $end) && $priority >= 0 && $priority <= 10) {
                $eligible[] = ['post' => $post, 'priority' => $priority, 'start' => $start];
            }
        }
        usort($eligible, static function (array $a, array $b): int {
            return ($b['priority'] <=> $a['priority']) ?: (($b['start'] <=> $a['start']) ?: ((int) $b['post']->ID <=> (int) $a['post']->ID));
        });
        return !empty($eligible) ? self::from_post($eligible[0]['post']) : self::fallback();
    }

    public static function view_model(?DateTimeImmutable $at = null): array {
        return ['campaign' => self::active($at), 'mode' => self::is_enabled() ? 'v2' : 'legacy'];
    }
}
