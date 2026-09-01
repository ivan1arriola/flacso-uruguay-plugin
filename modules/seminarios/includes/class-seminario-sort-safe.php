<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Corrige el ORDER BY por fecha de Edición evitando comparar strings con
 * collations distintas. `seminario_id` contiene un ID numérico, por lo que la
 * comparación correcta es numérica.
 */
final class FLACSO_Seminario_Sort_Safe {
    public static function init(): void {
        add_filter('posts_clauses', [self::class, 'fix_edition_date_order'], 20, 2);
    }

    public static function fix_edition_date_order(array $clauses, WP_Query $query): array {
        if (
            !$query->is_main_query()
            || $query->get('post_type') !== Seminario_CPT::POST_TYPE
            || $query->get('orderby') !== 'edicion_fecha_inicio'
        ) {
            return $clauses;
        }

        global $wpdb;
        $direction = strtoupper((string) $query->get('order')) === 'ASC' ? 'ASC' : 'DESC';
        $edition_post_type = esc_sql(FLACSO_Edicion::POST_TYPE);

        $latest_start = "(
            SELECT MAX(CAST(NULLIF(fecha.meta_value, '') AS DATE))
            FROM {$wpdb->posts} edicion
            INNER JOIN {$wpdb->postmeta} relacion
                ON relacion.post_id = edicion.ID
                AND relacion.meta_key = 'seminario_id'
            INNER JOIN {$wpdb->postmeta} fecha
                ON fecha.post_id = edicion.ID
                AND fecha.meta_key = 'fecha_inicio'
            WHERE edicion.post_type = '{$edition_post_type}'
                AND edicion.post_status IN ('publish', 'draft', 'pending', 'private')
                AND CAST(relacion.meta_value AS UNSIGNED) = {$wpdb->posts}.ID
        )";

        $clauses['orderby'] = "{$latest_start} {$direction}, {$wpdb->posts}.post_title ASC";
        return $clauses;
    }
}
