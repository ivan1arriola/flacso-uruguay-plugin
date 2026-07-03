<?php

if (!defined('ABSPATH')) {
    exit;
}

class Flacso_Instagram_Post_Importer {
    private const META_MEDIA_ID = '_flacso_instagram_media_id';
    private const META_PERMALINK = '_flacso_instagram_permalink';
    private const META_MEDIA_TYPE = '_flacso_instagram_media_type';
    private const META_TIMESTAMP = '_flacso_instagram_timestamp';
    private const META_IMPORTED_AT = '_flacso_instagram_imported_at';

    public static function init(): void {
        add_action('wp_ajax_flacso_instagram_import_preview', [__CLASS__, 'ajax_preview']);
        add_action('wp_ajax_flacso_instagram_import_post', [__CLASS__, 'ajax_import_post']);
    }

    public static function ajax_preview(): void {
        if (!self::current_user_can_import()) {
            wp_send_json_error(['message' => __('No tienes permisos para importar publicaciones.', 'flacso-main-page')], 403);
        }

        check_ajax_referer('flacso-settings-nonce', 'nonce');

        if (!class_exists('Flacso_Instagram_API')) {
            wp_send_json_error(['message' => __('La API de Instagram no está disponible.', 'flacso-main-page')], 500);
        }

        $feed = Flacso_Instagram_API::get_feed();
        if (is_wp_error($feed)) {
            wp_send_json_error(['message' => $feed->get_error_message()], 400);
        }

        $feed = array_slice(array_values((array) $feed), 0, 24);
        $existing_posts = self::get_existing_posts_map(wp_list_pluck($feed, 'id'));
        $items = array_map(
            static function (array $item) use ($existing_posts): array {
                $media_id = sanitize_text_field((string) ($item['id'] ?? ''));
                $post_id = isset($existing_posts[$media_id]) ? (int) $existing_posts[$media_id] : 0;

                return [
                    'id' => $media_id,
                    'caption' => self::trim_text((string) ($item['caption'] ?? ''), 180),
                    'title' => self::generate_title($item),
                    'mediaType' => sanitize_text_field((string) ($item['media_type'] ?? '')),
                    'mediaUrl' => esc_url_raw((string) ($item['media_url'] ?? '')),
                    'thumbnailUrl' => esc_url_raw((string) ($item['thumbnail_url'] ?? $item['media_url'] ?? '')),
                    'permalink' => esc_url_raw((string) ($item['permalink'] ?? '')),
                    'timestamp' => sanitize_text_field((string) ($item['timestamp'] ?? '')),
                    'dateLabel' => self::format_instagram_date((string) ($item['timestamp'] ?? '')),
                    'imported' => $post_id > 0,
                    'postId' => $post_id,
                    'editUrl' => $post_id > 0 ? get_edit_post_link($post_id, 'raw') : '',
                ];
            },
            $feed
        );

        wp_send_json_success([
            'items' => $items,
            'message' => sprintf(
                /* translators: %d: number of Instagram posts found */
                _n('%d publicación encontrada.', '%d publicaciones encontradas.', count($items), 'flacso-main-page'),
                count($items)
            ),
        ]);
    }

    public static function ajax_import_post(): void {
        if (!self::current_user_can_import()) {
            wp_send_json_error(['message' => __('No tienes permisos para importar publicaciones.', 'flacso-main-page')], 403);
        }

        check_ajax_referer('flacso-settings-nonce', 'nonce');

        $media_id = isset($_POST['media_id']) ? sanitize_text_field(wp_unslash((string) $_POST['media_id'])) : '';
        if ($media_id === '') {
            wp_send_json_error(['message' => __('No se recibió la publicación de Instagram.', 'flacso-main-page')], 400);
        }

        $existing_post_id = self::find_existing_post_id($media_id);
        if ($existing_post_id > 0) {
            wp_send_json_success([
                'message' => __('Esta publicación ya estaba importada.', 'flacso-main-page'),
                'postId' => $existing_post_id,
                'editUrl' => get_edit_post_link($existing_post_id, 'raw'),
                'alreadyImported' => true,
            ]);
        }

        if (!class_exists('Flacso_Instagram_API')) {
            wp_send_json_error(['message' => __('La API de Instagram no está disponible.', 'flacso-main-page')], 500);
        }

        $feed = Flacso_Instagram_API::get_feed();
        if (is_wp_error($feed)) {
            wp_send_json_error(['message' => $feed->get_error_message()], 400);
        }

        $item = self::find_feed_item((array) $feed, $media_id);
        if (!$item) {
            wp_send_json_error(['message' => __('No se encontró esa publicación en el feed reciente.', 'flacso-main-page')], 404);
        }

        $post_id = self::create_draft_from_item($item);
        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => $post_id->get_error_message()], 500);
        }

        wp_send_json_success([
            'message' => __('Borrador creado. Ya podés editarlo antes de publicar.', 'flacso-main-page'),
            'postId' => (int) $post_id,
            'editUrl' => get_edit_post_link((int) $post_id, 'raw'),
            'alreadyImported' => false,
        ]);
    }

    private static function current_user_can_import(): bool {
        return current_user_can('edit_posts');
    }

    private static function get_existing_posts_map(array $media_ids): array {
        $media_ids = array_values(array_filter(array_map('sanitize_text_field', $media_ids)));
        if (!$media_ids) {
            return [];
        }

        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => ['draft', 'pending', 'publish', 'future', 'private'],
            'numberposts' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => self::META_MEDIA_ID,
                    'value' => $media_ids,
                    'compare' => 'IN',
                ],
            ],
        ]);

        $map = [];
        foreach ($posts as $post_id) {
            $instagram_id = (string) get_post_meta((int) $post_id, self::META_MEDIA_ID, true);
            if ($instagram_id !== '') {
                $map[$instagram_id] = (int) $post_id;
            }
        }

        return $map;
    }

    private static function find_existing_post_id(string $media_id): int {
        $map = self::get_existing_posts_map([$media_id]);
        return isset($map[$media_id]) ? (int) $map[$media_id] : 0;
    }

    private static function find_feed_item(array $feed, string $media_id): ?array {
        foreach ($feed as $item) {
            if ((string) ($item['id'] ?? '') === $media_id) {
                return is_array($item) ? $item : null;
            }
        }

        return null;
    }

    private static function create_draft_from_item(array $item) {
        $title = self::generate_title($item);
        $content = self::build_post_content($item);
        $caption = (string) ($item['caption'] ?? '');
        $post_data = [
            'post_type' => 'post',
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
            'post_title' => $title,
            'post_content' => $content,
            'post_excerpt' => self::trim_text(wp_strip_all_tags($caption), 220),
        ];

        $category_ids = self::get_novedades_category_ids();
        if ($category_ids) {
            $post_data['post_category'] = $category_ids;
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        update_post_meta((int) $post_id, self::META_MEDIA_ID, sanitize_text_field((string) ($item['id'] ?? '')));
        update_post_meta((int) $post_id, self::META_PERMALINK, esc_url_raw((string) ($item['permalink'] ?? '')));
        update_post_meta((int) $post_id, self::META_MEDIA_TYPE, sanitize_text_field((string) ($item['media_type'] ?? '')));
        update_post_meta((int) $post_id, self::META_TIMESTAMP, sanitize_text_field((string) ($item['timestamp'] ?? '')));
        update_post_meta((int) $post_id, self::META_IMPORTED_AT, current_time('mysql'));

        $attachment_id = self::maybe_set_featured_image((int) $post_id, $item, $title);
        if ($attachment_id > 0) {
            wp_update_post([
                'ID' => (int) $post_id,
                'post_content' => self::build_post_content($item, $attachment_id),
            ]);
        }

        return (int) $post_id;
    }

    private static function build_post_content(array $item, int $attachment_id = 0): string {
        $caption = trim((string) ($item['caption'] ?? ''));
        $media_type = strtoupper((string) ($item['media_type'] ?? ''));
        $media_url = esc_url_raw((string) ($item['media_url'] ?? ''));
        $thumbnail_url = esc_url_raw((string) ($item['thumbnail_url'] ?? $media_url));
        $permalink = esc_url_raw((string) ($item['permalink'] ?? ''));
        $blocks = [];

        if ($caption !== '') {
            foreach (preg_split('/\R{2,}/', $caption) as $paragraph) {
                $paragraph = trim($paragraph);
                if ($paragraph === '') {
                    continue;
                }

                $blocks[] = '<!-- wp:paragraph -->' .
                    '<p>' . nl2br(esc_html($paragraph), false) . '</p>' .
                    '<!-- /wp:paragraph -->';
            }
        }

        if ($media_url !== '') {
            if ($media_type === 'VIDEO') {
                $poster_attr = $thumbnail_url !== '' ? ' poster="' . esc_url($thumbnail_url) . '"' : '';
                $blocks[] = '<!-- wp:video -->' .
                    '<figure class="wp-block-video"><video controls src="' . esc_url($media_url) . '"' . $poster_attr . '></video></figure>' .
                    '<!-- /wp:video -->';
            } else {
                $local_url = $attachment_id > 0 ? wp_get_attachment_image_url($attachment_id, 'large') : '';
                $image_url = $local_url ?: $media_url;
                $image_attrs = $attachment_id > 0
                    ? ' class="wp-image-' . (int) $attachment_id . '"'
                    : '';
                $block_attrs = $attachment_id > 0
                    ? '{"id":' . (int) $attachment_id . ',"sizeSlug":"large","linkDestination":"none"}'
                    : '{"sizeSlug":"large"}';

                $blocks[] = '<!-- wp:image ' . $block_attrs . ' -->' .
                    '<figure class="wp-block-image size-large"><img src="' . esc_url($image_url) . '" alt="' . esc_attr(self::generate_title($item)) . '"' . $image_attrs . '/></figure>' .
                    '<!-- /wp:image -->';
            }
        }

        if ($permalink !== '') {
            $blocks[] = '<!-- wp:paragraph -->' .
                '<p><a href="' . esc_url($permalink) . '" target="_blank" rel="noopener noreferrer">' .
                esc_html__('Ver publicación original en Instagram', 'flacso-main-page') .
                '</a></p>' .
                '<!-- /wp:paragraph -->';
        }

        return implode("\n\n", $blocks);
    }

    private static function maybe_set_featured_image(int $post_id, array $item, string $title): int {
        $media_type = strtoupper((string) ($item['media_type'] ?? ''));
        $image_url = $media_type === 'VIDEO'
            ? (string) ($item['thumbnail_url'] ?? '')
            : (string) ($item['media_url'] ?? '');

        $image_url = esc_url_raw($image_url);
        if ($image_url === '') {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_sideload_image($image_url, $post_id, $title, 'id');
        if (!is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, (int) $attachment_id);
            return (int) $attachment_id;
        }

        return 0;
    }

    private static function get_novedades_category_ids(): array {
        $category = get_category_by_slug('novedades');
        if (!$category instanceof WP_Term) {
            $category = get_term_by('name', 'Novedades', 'category');
        }

        return $category instanceof WP_Term ? [(int) $category->term_id] : [];
    }

    private static function generate_title(array $item): string {
        $caption = trim(wp_strip_all_tags((string) ($item['caption'] ?? '')));
        $first_line = trim((string) strtok($caption, "\n"));
        $first_line = preg_replace('/\s+#\S+/', '', $first_line);
        $first_line = trim((string) $first_line);

        if ($first_line !== '') {
            return self::trim_text($first_line, 90);
        }

        $date = self::format_instagram_date((string) ($item['timestamp'] ?? ''));
        return $date !== ''
            ? sprintf(__('Publicación de Instagram - %s', 'flacso-main-page'), $date)
            : __('Publicación de Instagram', 'flacso-main-page');
    }

    private static function format_instagram_date(string $timestamp): string {
        if ($timestamp === '') {
            return '';
        }

        $time = strtotime($timestamp);
        if (!$time) {
            return '';
        }

        return wp_date('j/m/Y', $time);
    }

    private static function trim_text(string $text, int $max_length): string {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '' || function_exists('mb_strlen') && mb_strlen($text) <= $max_length) {
            return $text;
        }

        if (!function_exists('mb_substr')) {
            return strlen($text) > $max_length ? substr($text, 0, $max_length - 3) . '...' : $text;
        }

        return rtrim(mb_substr($text, 0, $max_length - 3)) . '...';
    }
}
