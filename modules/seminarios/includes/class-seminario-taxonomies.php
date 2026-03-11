<?php
if (!defined('ABSPATH')) {
    exit;
}

class Seminario_Taxonomies
{
    private const LEGACY_CLEANUP_OPTION = 'flacso_seminario_legacy_taxonomies_removed';

    public static function register()
    {
        self::maybe_cleanup_legacy_taxonomies();
    }

    public static function register_term_meta()
    {
        // Legacy: no term meta para taxonomias eliminadas.
    }

    public static function get_taxonomies($post_id)
    {
        return array();
    }

    public static function set_terms_from_request($post_id, $taxonomies)
    {
        // Legacy: sin asignacion de taxonomias en seminarios.
    }

    public static function term_response($term)
    {
        if (!($term instanceof WP_Term)) {
            return array();
        }

        return array(
            'id'          => (int) $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description,
        );
    }

    public static function get_programa_color($term_id)
    {
        return '#1d3a72';
    }

    public static function update_programa_color($term_id, $color)
    {
        // Legacy: sin persistencia de color para taxonomia removida.
    }

    public static function get_related_oferta_ids(int $seminario_id): array
    {
        if ($seminario_id <= 0 || !post_type_exists('oferta-academica')) {
            return array();
        }

        $statuses = current_user_can('manage_options')
            ? array('publish', 'private')
            : array('publish');

        $ofertas_ids = get_posts(array(
            'post_type'      => 'oferta-academica',
            'post_status'    => $statuses,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => '_oferta_seminarios_ids',
                    'compare' => 'EXISTS',
                ),
            ),
        ));

        $related_ids = array();
        foreach ($ofertas_ids as $oferta_id) {
            $seminarios_ids = get_post_meta($oferta_id, '_oferta_seminarios_ids', true);
            if (!is_array($seminarios_ids) || empty($seminarios_ids)) {
                continue;
            }

            $seminarios_ids = array_map('intval', $seminarios_ids);
            if (in_array($seminario_id, $seminarios_ids, true)) {
                $related_ids[] = (int) $oferta_id;
            }
        }

        if (empty($related_ids)) {
            return array();
        }

        return array_values(array_unique($related_ids));
    }

    public static function get_related_ofertas(int $seminario_id): array
    {
        $ids = self::get_related_oferta_ids($seminario_id);
        if (empty($ids)) {
            return array();
        }

        $items = array();
        foreach ($ids as $oferta_id) {
            $post = get_post($oferta_id);
            if (!$post || $post->post_type !== 'oferta-academica') {
                continue;
            }

            $items[] = array(
                'id'    => $post->ID,
                'title' => get_the_title($post),
                'slug'  => $post->post_name,
                'url'   => get_permalink($post),
            );
        }

        usort($items, static function ($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });

        return $items;
    }

    public static function maybe_cleanup_legacy_taxonomies(): void
    {
        if (get_option(self::LEGACY_CLEANUP_OPTION, '0') === '1') {
            return;
        }

        global $wpdb;

        $legacy_taxonomies = array('programa', 'posgrado');
        $placeholders = implode(',', array_fill(0, count($legacy_taxonomies), '%s'));

        $term_taxonomy_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ($placeholders)",
            $legacy_taxonomies
        ));

        $term_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ($placeholders)",
            $legacy_taxonomies
        ));

        $term_taxonomy_ids = array_values(array_filter(array_map('intval', $term_taxonomy_ids)));
        $term_ids = array_values(array_filter(array_map('intval', $term_ids)));

        if (!empty($term_taxonomy_ids)) {
            $term_taxonomy_in = implode(',', $term_taxonomy_ids);
            $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($term_taxonomy_in)");
        }

        if (!empty($term_ids)) {
            $term_ids_in = implode(',', $term_ids);
            $wpdb->query(
                "DELETE FROM {$wpdb->termmeta} WHERE term_id IN ($term_ids_in) AND meta_key IN ('_programa_color', '_programa_id')"
            );
        }

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ($placeholders)",
            $legacy_taxonomies
        ));

        if (!empty($term_ids)) {
            $term_ids_in = implode(',', $term_ids);
            $remaining_term_ids = $wpdb->get_col("SELECT DISTINCT term_id FROM {$wpdb->term_taxonomy} WHERE term_id IN ($term_ids_in)");
            $remaining_term_ids = array_values(array_filter(array_map('intval', $remaining_term_ids)));
            $orphan_term_ids = array_diff($term_ids, $remaining_term_ids);

            if (!empty($orphan_term_ids)) {
                $orphan_term_ids_in = implode(',', array_map('intval', $orphan_term_ids));
                $wpdb->query("DELETE FROM {$wpdb->terms} WHERE term_id IN ($orphan_term_ids_in)");
            }
        }

        update_option(self::LEGACY_CLEANUP_OPTION, '1', false);
    }
}
