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
        add_action('init', [self::class, 'check_and_flush_rules'], 30);
        add_filter('query_vars', [self::class, 'register_query_vars']);
        add_filter('request', [self::class, 'fix_carta_request'], 5);
        add_filter('redirect_canonical', [self::class, 'prevent_canonical_redirect_for_carta'], 10, 2);
        add_filter('term_link', [self::class, 'filter_term_link'], 10, 3);
    }

    public static function register_query_vars(array $vars): array {
        $vars[] = 'es_carta';
        $vars[] = 'carta';
        return $vars;
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
        add_rewrite_endpoint('carta', EP_PERMALINK | EP_PAGES | EP_ALL);

        foreach (FLACSO_Oferta_Academica::segmentos_url() as $type => $segment) {
            add_rewrite_rule(
                '^formacion/' . $segment . '/([^/]+)/carta/?$',
                'index.php?post_type=' . FLACSO_Oferta_Academica::POST_TYPE . '&name=$matches[1]&es_carta=1&carta=1',
                'top'
            );
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
        add_rewrite_rule(
            '^formacion/([^/]+)/([^/]+)/carta/?$',
            'index.php?post_type=' . FLACSO_Oferta_Academica::POST_TYPE . '&name=$matches[2]&es_carta=1&carta=1',
            'top'
        );
    }

    public static function check_and_flush_rules(): void {
        $rules = get_option('rewrite_rules');
        if (!is_array($rules) || !isset($rules['^formacion/([^/]+)/([^/]+)/carta/?$'])) {
            flush_rewrite_rules(false);
        }
    }

    public static function fix_carta_request(array $query_vars): array {
        if (isset($_SERVER['REQUEST_URI'])) {
            $path = trim((string) wp_parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
            if (preg_match('#^formacion/([^/]+)/([^/]+)/carta$#', $path, $matches)) {
                $query_vars['post_type'] = FLACSO_Oferta_Academica::POST_TYPE;
                $query_vars[FLACSO_Oferta_Academica::POST_TYPE] = $matches[2];
                $query_vars['name'] = $matches[2];
                $query_vars['es_carta'] = '1';
                $query_vars['carta'] = '1';
            }
        }
        return $query_vars;
    }

    public static function prevent_canonical_redirect_for_carta($redirect_url, $requested_url) {
        if (get_query_var('es_carta') || (get_query_var('carta') !== false && get_query_var('carta') !== '')) {
            return false;
        }
        $path = trim((string) wp_parse_url((string) $requested_url, PHP_URL_PATH), '/');
        if (str_ends_with($path, '/carta') || str_ends_with($path, 'carta')) {
            return false;
        }
        return $redirect_url;
    }

    public static function filter_term_link(string $url, $term, string $taxonomy): string {
        if ($taxonomy !== FLACSO_Oferta_Academica::TYPE_TAXONOMY) {
            return $url;
        }
        $segments = FLACSO_Oferta_Academica::segmentos_url();
        return isset($segments[$term->slug]) ? home_url('/formacion/' . $segments[$term->slug] . '/') : $url;
    }
}
