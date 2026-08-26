<?php
if (!defined('ABSPATH')) {
    exit;
}

class Seminario_Taxonomies
{
    private const LEGACY_CLEANUP_OPTION = 'flacso_seminario_legacy_taxonomies_removed';
    private const PROGRAM_BACKFILL_OPTION = 'flacso_seminario_program_backfill_v1';

    public static function register()
    {
        self::maybe_cleanup_legacy_taxonomies();
        add_filter('rest_pre_insert_seminario', array(__CLASS__, 'validate_program_before_rest_insert'), 9, 2);
    }

    public static function register_term_meta()
    {
        // Legacy: no term meta para taxonomias eliminadas.
    }

    public static function get_taxonomies($post_id)
    {
        $terms = wp_get_post_terms((int) $post_id, 'area_tematica', array('fields' => 'all'));
        if (is_wp_error($terms)) {
            $terms = array();
        }

        return array(
            'area_tematica' => array_map(array(__CLASS__, 'term_response'), (array) $terms),
        );
    }

    public static function get_program($post_id)
    {
        $taxonomies = self::get_taxonomies($post_id);
        return $taxonomies['area_tematica'][0] ?? null;
    }

    public static function set_terms_from_request($post_id, $taxonomies, $program = null, $area_tematica = null)
    {
        if (!is_array($taxonomies) && $program === null && $area_tematica === null) {
            return;
        }

        if (!is_array($taxonomies)) {
            $taxonomies = array();
        }

        $program_value = $program
            ?? ($taxonomies['area_tematica'] ?? ($taxonomies['program'] ?? $area_tematica));
        if ($program_value !== null) {
            $program_ids = self::normalize_program_term_ids($program_value);
            wp_set_object_terms($post_id, $program_ids, 'area_tematica');
        }

        // --- Ofertas Académicas (CPT Relation) ---
        if (isset($taxonomies['ofertas_academicas'])) {
            $new_ofertas = $taxonomies['ofertas_academicas'];
            if (!is_array($new_ofertas)) {
                $new_ofertas = array();
            }

            // Convertir a IDs
            $new_oferta_ids = array();
            foreach ($new_ofertas as $o) {
                if (is_array($o) && isset($o['id'])) {
                    $new_oferta_ids[] = absint($o['id']);
                } elseif (is_numeric($o)) {
                    $new_oferta_ids[] = absint($o);
                }
            }
            $new_oferta_ids = array_unique(array_filter($new_oferta_ids));

            // Obtener ofertas actualmente vinculadas
            $current_oferta_ids = self::get_related_oferta_ids($post_id);

            // 1. Desvincular de ofertas que ya no están
            $to_remove = array_diff($current_oferta_ids, $new_oferta_ids);
            foreach ($to_remove as $oferta_id) {
                $seminarios = get_post_meta($oferta_id, '_oferta_seminarios_ids', true);
                if (is_array($seminarios)) {
                    $seminarios = array_values(array_diff($seminarios, array($post_id)));
                    update_post_meta($oferta_id, '_oferta_seminarios_ids', $seminarios);
                }
            }

            // 2. Vincular a nuevas ofertas
            $to_add = array_diff($new_oferta_ids, $current_oferta_ids);
            foreach ($to_add as $oferta_id) {
                $seminarios = get_post_meta($oferta_id, '_oferta_seminarios_ids', true);
                if (!is_array($seminarios)) {
                    $seminarios = array();
                }
                if (!in_array($post_id, $seminarios)) {
                    $seminarios[] = $post_id;
                    update_post_meta($oferta_id, '_oferta_seminarios_ids', $seminarios);
                }
            }
        }
    }

    public static function normalize_program_term_ids($value): array
    {
        return array_slice(self::normalize_all_program_term_ids($value), 0, 1);
    }

    public static function normalize_all_program_term_ids($value): array
    {
        $values = is_array($value) && array_key_exists('id', $value)
            ? array($value)
            : (is_array($value) ? $value : array($value));
        $ids = array();

        foreach ($values as $term) {
            if (is_array($term) && isset($term['id'])) {
                $ids[] = absint($term['id']);
            } elseif (is_object($term) && isset($term->term_id)) {
                $ids[] = absint($term->term_id);
            } elseif (is_numeric($term)) {
                $ids[] = absint($term);
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    public static function validate_program_request($taxonomies, bool $required = true, $program = null, $area_tematica = null)
    {
        $representations = array();
        if ($program !== null) {
            $representations[] = $program;
        }
        if (is_array($taxonomies) && array_key_exists('area_tematica', $taxonomies)) {
            $representations[] = $taxonomies['area_tematica'];
        } elseif (is_array($taxonomies) && array_key_exists('program', $taxonomies)) {
            $representations[] = $taxonomies['program'];
        }
        if ($area_tematica !== null) {
            $representations[] = $area_tematica;
        }

        if (empty($representations)) {
            return $required
                ? new WP_Error('seminario_program_required', 'Debés seleccionar un Programa para el seminario.', array('status' => 400))
                : true;
        }

        $all_ids = array();
        foreach ($representations as $value) {
            $ids = self::normalize_all_program_term_ids($value);
            if (count($ids) !== 1) {
                return new WP_Error('seminario_program_invalid', 'Cada seminario debe pertenecer a un único Programa.', array('status' => 400));
            }
            $all_ids[] = $ids[0];
        }

        if (count(array_unique($all_ids)) !== 1) {
            return new WP_Error('seminario_program_invalid', 'Cada seminario debe pertenecer a un único Programa.', array('status' => 400));
        }

        return true;
    }

    public static function validate_program_before_rest_insert($prepared_post, WP_REST_Request $request)
    {
        $current_id = $prepared_post instanceof WP_Post
            ? (int) $prepared_post->ID
            : (int) $request->get_param('id');
        $taxonomies = $request->get_param('taxonomies');
        $program = $request->get_param('program');
        $area_tematica = $request->get_param('area_tematica');
        $supplied = $taxonomies !== null || $program !== null || $area_tematica !== null;
        $validation = self::validate_program_request(
            $taxonomies,
            $current_id <= 0 || $supplied,
            $program,
            $area_tematica
        );

        return is_wp_error($validation) ? $validation : $prepared_post;
    }

    public static function resolve_single_program_id(array $candidate_ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('absint', $candidate_ids))));
        return count($ids) === 1 ? (int) $ids[0] : 0;
    }

    public static function maybe_backfill_program_relationships(): void
    {
        if (get_option(self::PROGRAM_BACKFILL_OPTION, false) !== false) {
            return;
        }

        $statuses = array('publish', 'draft', 'private', 'pending', 'future');
        $seminar_ids = get_posts(array(
            'post_type' => 'seminario',
            'post_status' => $statuses,
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));
        $assigned = array();
        $unresolved = array();

        foreach ((array) $seminar_ids as $seminar_id) {
            $seminar_id = absint($seminar_id);
            $current_ids = wp_get_post_terms($seminar_id, 'area_tematica', array('fields' => 'ids'));
            if (!is_wp_error($current_ids) && count($current_ids) === 1) {
                continue;
            }
            if (!is_wp_error($current_ids) && count($current_ids) > 1) {
                $unresolved[] = $seminar_id;
                continue;
            }

            $candidate_ids = self::get_program_candidates_for_backfill($seminar_id, $statuses);
            $program_id = self::resolve_single_program_id($candidate_ids);

            if ($program_id > 0) {
                $result = wp_set_object_terms($seminar_id, array($program_id), 'area_tematica');
                if (!is_wp_error($result)) {
                    $assigned[] = $seminar_id;
                    continue;
                }
            }

            $unresolved[] = $seminar_id;
        }

        update_option(self::PROGRAM_BACKFILL_OPTION, array(
            'completed_at' => current_time('mysql', true),
            'assigned' => $assigned,
            'unresolved' => $unresolved,
        ), false);
    }

    public static function get_program_integrity_report(): array
    {
        $seminar_ids = get_posts(array(
            'post_type' => 'seminario',
            'post_status' => array('publish', 'draft', 'private', 'pending', 'future'),
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));
        $missing = 0;
        $multiple = 0;

        foreach ((array) $seminar_ids as $seminar_id) {
            $program_ids = wp_get_post_terms((int) $seminar_id, 'area_tematica', array('fields' => 'ids'));
            $count = is_wp_error($program_ids) ? 0 : count($program_ids);
            if ($count === 0) {
                $missing++;
            } elseif ($count > 1) {
                $multiple++;
            }
        }

        return array(
            'seminars_total' => count($seminar_ids),
            'seminars_missing_program' => $missing,
            'seminars_multiple_programs' => $multiple,
            'valid' => $missing === 0 && $multiple === 0,
        );
    }

    private static function get_program_candidates_for_backfill(int $seminar_id, array $statuses): array
    {
        $candidate_ids = array();
        $offer_ids = self::get_related_oferta_ids($seminar_id, $statuses);
        $component_ids = get_post_meta($seminar_id, '_seminario_seminarios_componentes', true);

        foreach ((array) $component_ids as $component_id) {
            $component_id = absint($component_id);
            if ($component_id <= 0) {
                continue;
            }

            $component_program_ids = wp_get_post_terms($component_id, 'area_tematica', array('fields' => 'ids'));
            if (!is_wp_error($component_program_ids)) {
                $candidate_ids = array_merge($candidate_ids, (array) $component_program_ids);
            }
            $offer_ids = array_merge($offer_ids, self::get_related_oferta_ids($component_id, $statuses));
        }

        foreach (array_unique(array_map('absint', $offer_ids)) as $offer_id) {
            $program_ids = wp_get_post_terms($offer_id, 'area_tematica', array('fields' => 'ids'));
            if (!is_wp_error($program_ids)) {
                $candidate_ids = array_merge($candidate_ids, (array) $program_ids);
            }
        }

        return $candidate_ids;
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

    public static function get_related_oferta_ids(int $seminario_id, ?array $statuses = null): array
    {
        if ($seminario_id <= 0 || !post_type_exists('oferta-academica')) {
            return array();
        }

        if ($statuses === null) {
            $statuses = current_user_can('manage_options')
                ? array('publish', 'private')
                : array('publish');
        }

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
