<?php

if (!class_exists('FLACSO_Posgrados_Pages')) {
    class FLACSO_Posgrados_Pages {
        public const ROOT_PAGE_ID      = 12261;
        public const EXCLUDED_BRANCH_ID = 12349;

        private static $allowed_ids = null;

        public static function get_allowed_page_ids(): array {
            if (self::$allowed_ids !== null) {
                return self::$allowed_ids;
            }

            $allowed = [];
            $children = self::get_children_ids(self::ROOT_PAGE_ID);

            foreach ($children as $child_id) {
                if (self::is_in_excluded_branch($child_id)) {
                    continue;
                }

                foreach (self::get_children_ids($child_id) as $grandchild_id) {
                    if (self::is_in_excluded_branch($grandchild_id)) {
                        continue;
                    }
                    $allowed[] = $grandchild_id;
                }
            }

            $allowed = array_values(array_unique(array_map('intval', $allowed)));

            self::$allowed_ids = apply_filters('flacso_pos_allowed_page_ids', $allowed);

            return self::$allowed_ids;
        }

        private static function get_children_ids(int $parent_id): array {
            if (!$parent_id) {
                return [];
            }

            $posts = get_posts([
                'post_type'      => FLACSO_Posgrados_Fields::POST_TYPE,
                'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
                'post_parent'    => $parent_id,
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'orderby'        => 'menu_order title',
                'order'          => 'ASC',
            ]);

            return array_map('intval', $posts);
        }

        private static function is_in_excluded_branch(int $post_id): bool {
            if (!$post_id) {
                return false;
            }

            if ($post_id === self::EXCLUDED_BRANCH_ID) {
                return true;
            }

            $ancestors = get_post_ancestors($post_id);
            return in_array(self::EXCLUDED_BRANCH_ID, array_map('intval', $ancestors), true);
        }

        public static function get_tipo_for_page(int $post_id): string {
            $post_id = (int) $post_id;
            if (!$post_id) {
                return '';
            }

            $ancestors = get_post_ancestors($post_id);
            $category_id = null;

            foreach ($ancestors as $ancestor_id) {
                $parent = (int) wp_get_post_parent_id($ancestor_id);
                if ($parent === self::ROOT_PAGE_ID) {
                    $category_id = (int) $ancestor_id;
                    break;
                }
            }

            if ($category_id === null) {
                $direct_parent = (int) wp_get_post_parent_id($post_id);
                if ($direct_parent === self::ROOT_PAGE_ID) {
                    $category_id = $post_id;
                }
            }

            if (!$category_id) {
                return '';
            }

            $title = get_the_title($category_id);
            if (!$title) {
                return '';
            }

            $normalized = sanitize_title(remove_accents($title));
            $map = [
                'maestrias'        => 'Maestria',
                'maestria'         => 'Maestria',
                'especializaciones'=> 'Especializacion',
                'especializacion'  => 'Especializacion',
                'diplomados'       => 'Diplomado',
                'diplomado'        => 'Diplomado',
                'diplomas'         => 'Diploma',
                'diploma'          => 'Diploma',
            ];

            $tipo = $map[$normalized] ?? '';

            return apply_filters('flacso_pos_tipo_for_page', $tipo, $post_id, $title);
        }
    }
}
