<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Taxonomia cerrada de tipos de OfertaAcademica. */
final class Oferta_Taxonomies {
    public static function init(): void {
        register_taxonomy(FLACSO_Oferta_Academica::TYPE_TAXONOMY, [FLACSO_Oferta_Academica::POST_TYPE], [
            'labels' => [
                'name' => __('Tipos de oferta', 'flacso-uruguay'),
                'singular_name' => __('Tipo de oferta', 'flacso-uruguay'),
                'menu_name' => __('Tipos de oferta', 'flacso-uruguay'),
            ],
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'tipo-oferta'],
        ]);

        add_action('init', [self::class, 'ensure_terms'], 20);
        add_action('init', [self::class, 'register_rewrite_rules'], 10);
        add_filter('term_link', [self::class, 'filter_term_link'], 10, 3);
    }

    public static function ensure_terms(): void {
        foreach (FLACSO_Oferta_Academica::tipos() as $slug => $name) {
            $term = get_term_by('slug', $slug, FLACSO_Oferta_Academica::TYPE_TAXONOMY);
            if (!$term || is_wp_error($term)) {
                wp_insert_term($name, FLACSO_Oferta_Academica::TYPE_TAXONOMY, ['slug' => $slug]);
            } elseif ($term->name !== $name) {
                wp_update_term($term->term_id, FLACSO_Oferta_Academica::TYPE_TAXONOMY, ['name' => $name]);
            }
        }
    }

    public static function register_rewrite_rules(): void {
        foreach (FLACSO_Oferta_Academica::segmentos_url() as $type => $segment) {
            add_rewrite_rule(
                '^formacion/' . $segment . '/page/?([0-9]{1,})/?$',
                'index.php?' . FLACSO_Oferta_Academica::TYPE_TAXONOMY . '=' . $type . '&paged=$matches[1]',
                'top'
            );
            add_rewrite_rule(
                '^formacion/' . $segment . '/?$',
                'index.php?' . FLACSO_Oferta_Academica::TYPE_TAXONOMY . '=' . $type,
                'top'
            );
        }
    }

    public static function filter_term_link(string $url, $term, string $taxonomy): string {
        if ($taxonomy !== FLACSO_Oferta_Academica::TYPE_TAXONOMY) {
            return $url;
        }
        $segments = FLACSO_Oferta_Academica::segmentos_url();
        return isset($segments[$term->slug]) ? home_url('/formacion/' . $segments[$term->slug] . '/') : $url;
    }
}
