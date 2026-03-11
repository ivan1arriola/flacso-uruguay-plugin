<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FLACSO_Posgrados_Docentes_Sync')) {
    class FLACSO_Posgrados_Docentes_Sync {
        private const DEFAULT_COLOR_MAP = [
            'Maestria'        => '#0d6efd',
            'Especializacion' => '#6f42c1',
            'Diplomado'       => '#0aa27a',
            'Diploma'         => '#fd7e14',
        ];

        private static $creating_children = false;

        public static function init(): void {
            add_action('init', [__CLASS__, 'bootstrap'], 15);
        }

        public static function bootstrap(): void {
            add_filter('dp_posgrado_root_page_id', [__CLASS__, 'sync_root_page_id']);
            add_filter('dp_posgrado_excluded_branch_ids', [__CLASS__, 'sync_excluded_branch_ids']);

            add_action('init', [__CLASS__, 'synchronize_terms_with_programs'], 20);
            add_action('save_post_' . FLACSO_Posgrados_Fields::POST_TYPE, [__CLASS__, 'handle_program_save'], 20, 3);
        }

        public static function sync_root_page_id($value): int {
            if (class_exists('FLACSO_Posgrados_Pages')) {
                return (int) FLACSO_Posgrados_Pages::ROOT_PAGE_ID;
            }

            return (int) $value;
        }

        public static function sync_excluded_branch_ids($value): array {
            if (class_exists('FLACSO_Posgrados_Pages')) {
                return [ (int) FLACSO_Posgrados_Pages::EXCLUDED_BRANCH_ID ];
            }

            return array_values(array_map('intval', (array) $value));
        }

        public static function synchronize_terms_with_programs(): void {
            if (!taxonomy_exists('equipo-docente')) {
                return;
            }

            foreach (self::get_syncable_page_ids() as $page_id) {
                self::sync_tipo_with_parent($page_id);
                self::ensure_child_pages($page_id);
            }
        }

        public static function handle_program_save(int $post_id, WP_Post $post, bool $update): void {
            if ($post->post_type !== FLACSO_Posgrados_Fields::POST_TYPE) {
                return;
            }

            if (self::$creating_children) {
                return;
            }

            if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
                return;
            }

            if (!$update && $post->post_status !== 'publish') {
                return;
            }

            if (!in_array($post_id, self::get_syncable_page_ids(), true)) {
                return;
            }

            self::sync_tipo_with_parent($post_id);
            self::ensure_child_pages($post_id);
        }

        private static function get_syncable_page_ids(): array {
            if (!class_exists('FLACSO_Posgrados_Pages')) {
                return [];
            }

            return FLACSO_Posgrados_Pages::get_allowed_page_ids();
        }

        private static function sync_term_with_page(int $page_id): void {
            $page = get_post($page_id);
            if (!$page || $page->post_type !== FLACSO_Posgrados_Fields::POST_TYPE) {
                return;
            }

            $term_id = self::get_term_id_by_page($page_id);

            if (!$term_id) {
                $result = wp_insert_term(
                    $page->post_title,
                    'equipo-docente',
                    [
                        'slug'        => sanitize_title($page->post_name ?: $page->post_title),
                        'description' => self::generate_description($page),
                    ]
                );

                if (is_wp_error($result)) {
                    if ($result->get_error_code() === 'term_exists') {
                        $term_id = (int) $result->get_error_data('term_exists');
                    } else {
                        return;
                    }
                } else {
                    $term_id = (int) $result['term_id'];
                }
            }

            $term = get_term($term_id, 'equipo-docente');
            $auto_sync = get_term_meta($term_id, 'equipo_docente_autosync', true);
            $relacion_nombre = get_term_meta($term_id, 'equipo_docente_relacion_nombre', true);
            $page_slug = sanitize_title($page->post_name ?: $page->post_title);

            $should_sync_name = !empty($auto_sync);
            if (!$should_sync_name && $term && !is_wp_error($term)) {
                $should_sync_name = ($relacion_nombre === '' && sanitize_title($term->slug) === $page_slug);
            }

            if ($should_sync_name) {
                wp_update_term(
                    $term_id,
                    'equipo-docente',
                    [
                        'name'        => $page->post_title,
                        'description' => self::generate_description($page),
                    ]
                );
                if (empty($auto_sync)) {
                    update_term_meta($term_id, 'equipo_docente_autosync', 1);
                }
            }

            update_term_meta($term_id, 'equipo_docente_page_id', $page_id);

            self::maybe_assign_color($term_id, $page_id);
        }

        private static function generate_description(WP_Post $page): string {
            if (has_excerpt($page)) {
                return wp_strip_all_tags($page->post_excerpt);
            }

            return wp_trim_words(wp_strip_all_tags($page->post_content), 40);
        }

        private static function maybe_assign_color(int $term_id, int $page_id): void {
            $existing_color = get_term_meta($term_id, 'equipo_docente_color', true);
            if ($existing_color) {
                return;
            }

            $tipo      = get_post_meta($page_id, 'tipo_posgrado', true);
            $color_map = apply_filters('flacso_pos_docentes_color_map', self::DEFAULT_COLOR_MAP);

            if (!empty($color_map[$tipo])) {
                update_term_meta($term_id, 'equipo_docente_color', sanitize_hex_color($color_map[$tipo]) ?: $color_map[$tipo]);
            }
        }

        private static function get_term_id_by_page(int $page_id): int {
            if (function_exists('dp_get_equipo_term_id_by_page')) {
                return (int) dp_get_equipo_term_id_by_page($page_id);
            }

            $terms = get_terms([
                'taxonomy'   => 'equipo-docente',
                'hide_empty' => false,
                'fields'     => 'ids',
                'number'     => 1,
                'meta_query' => [
                    [
                        'key'   => 'equipo_docente_page_id',
                        'value' => $page_id,
                    ],
                ],
            ]);

            if (is_wp_error($terms) || empty($terms)) {
                return 0;
            }

            return (int) $terms[0];
        }

        private static function sync_tipo_with_parent(int $post_id): void {
            if (!class_exists('FLACSO_Posgrados_Pages')) {
                return;
            }

            $tipo = FLACSO_Posgrados_Pages::get_tipo_for_page($post_id);
            if ($tipo) {
                update_post_meta($post_id, 'tipo_posgrado', $tipo);
            }
        }

        private static function ensure_child_pages(int $post_id): void {
            $parent = get_post($post_id);
            if (!$parent || $parent->post_type !== FLACSO_Posgrados_Fields::POST_TYPE) {
                return;
            }

            $title = get_the_title($parent) ?: '';
            $defaults = [
                'carta' => [
                    'title'   => sprintf(__('Carta del programa %s', 'flacso-posgrados-docentes'), $title),
                    'content' => sprintf(
                        "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->",
                        esc_html__('Edita esta página con la información de la carta de motivación del programa.', 'flacso-posgrados-docentes')
                    ),
                ],
                'preinscripcion' => [
                    'title'   => sprintf(__('Preinscripcion %s', 'flacso-posgrados-docentes'), $title),
                    'content' => sprintf(
                        "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->",
                        esc_html__('Actualiza este contenido con los pasos y formularios de preinscripción.', 'flacso-posgrados-docentes')
                    ),
                ],
            ];

            $required = apply_filters('flacso_pos_required_child_pages', $defaults, $parent);

            foreach ($required as $slug => $config) {
                $slug = sanitize_title($slug);
                if (!$slug) {
                    continue;
                }

                if (self::child_page_exists($parent->ID, $slug)) {
                    continue;
                }

                self::$creating_children = true;
                wp_insert_post([
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_parent'  => $parent->ID,
                    'post_author'  => $parent->post_author,
                    'post_title'   => wp_strip_all_tags($config['title'] ?? ucfirst($slug)),
                    'post_name'    => $slug,
                    'post_content' => $config['content'] ?? '',
                ]);
                self::$creating_children = false;
            }
        }

        private static function child_page_exists(int $parent_id, string $slug): bool {
            $path = ltrim(trailingslashit(get_page_uri($parent_id)) . $slug, '/');
            $existing = get_page_by_path($path, OBJECT, 'page');

            if ($existing) {
                return true;
            }

            $children = get_children([
                'post_parent' => $parent_id,
                'post_type'   => 'page',
                'post_status' => 'any',
                'fields'      => 'ids',
            ]);

            foreach ($children as $child_id) {
                $name = get_post_field('post_name', $child_id);
                if ($name === $slug) {
                    return true;
                }
            }

            return false;
        }
    }
}
