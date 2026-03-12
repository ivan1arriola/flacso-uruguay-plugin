<?php

// ==================================================
// BLOQUE / SHORTCODE: CONVENIOS RESPONSIVOS (MOBILE-FIRST)
// ==================================================

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('limpiarTituloConvenio')) {
    function limpiarTituloConvenio(string $title): string {
        $decoded = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (class_exists('Normalizer')) {
            $decoded = Normalizer::normalize($decoded, Normalizer::FORM_C);
        }

        $out = preg_replace('/^Convenio\s*\p{Pd}\s*/iu', '', $decoded);
        if ($out === $decoded) {
            $out = preg_replace('/^Convenio\W+\s*/iu', '', $decoded);
        }

        return trim((string) $out);
    }
}

if (!function_exists('flacso_convenios_normalize_string')) {
    function flacso_convenios_normalize_string(string $string): string
    {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        if ($converted === false) {
            $converted = $string;
        }
        $lower = strtolower($converted);
        return preg_replace('/[^a-z0-9\s]/', '', $lower);
    }
}

if (!function_exists('flacso_convenios_dataset_key')) {
    function flacso_convenios_dataset_key(): string
    {
        return 'flacso_convenios_dataset_v2';
    }
}

if (!function_exists('flacso_convenios_get_dataset')) {
    function flacso_convenios_get_dataset(): array
    {
        static $dataset = null;
        if ($dataset !== null) {
            return $dataset;
        }

        $cached = get_transient(flacso_convenios_dataset_key());
        if ($cached !== false) {
            $dataset = $cached;
            return $dataset;
        }

        $query = new WP_Query([
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'category_name'  => 'convenios',
            'meta_query'     => [
                [
                    'key'     => '_thumbnail_id',
                    'compare' => 'EXISTS',
                ],
            ],
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);

        $dataset = [];
        foreach ($query->posts as $post_item) {
            $titulo = limpiarTituloConvenio($post_item->post_title);
            $thumb_id = get_post_thumbnail_id($post_item->ID);
            $image_url = '';
            if ($thumb_id) {
                foreach (['medium_large', 'large', 'full'] as $size) {
                    $candidate = wp_get_attachment_image_url($thumb_id, $size);
                    if (!empty($candidate)) {
                        $image_url = (string) $candidate;
                        break;
                    }
                }
            }

            $dataset[] = [
                'id'         => $post_item->ID,
                'title'      => $titulo,
                'normalized' => flacso_convenios_normalize_string($titulo),
                'permalink'  => get_permalink($post_item->ID),
                'image'      => $image_url ? esc_url_raw($image_url) : '',
            ];
        }

        set_transient(flacso_convenios_dataset_key(), $dataset, HOUR_IN_SECONDS);
        return $dataset;
    }
}

if (!function_exists('flacso_convenios_get_placeholder_image')) {
    function flacso_convenios_get_placeholder_image(): string
    {
        static $placeholder = null;
        if ($placeholder !== null) {
            return $placeholder;
        }

        $placeholder_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="240" viewBox="0 0 240 240">'
            . '<rect width="240" height="240" fill="#f4f6fa" rx="18"/>'
            . '<rect x="42" y="42" width="156" height="156" fill="#ffffff" rx="12"/>'
            . '<text x="50%" y="52%" dominant-baseline="middle" text-anchor="middle" '
            . 'font-family="Arial, sans-serif" font-size="22" fill="#93a0b5">Logo</text>'
            . '</svg>';

        $placeholder = 'data:image/svg+xml;base64,' . base64_encode($placeholder_svg);
        return $placeholder;
    }
}

if (!function_exists('flacso_convenios_react_items')) {
    function flacso_convenios_react_items(?array $dataset = null): array
    {
        $dataset = $dataset ?? flacso_convenios_get_dataset();
        if (empty($dataset)) {
            return [];
        }

        $placeholder = flacso_convenios_get_placeholder_image();
        $sanitize_image = static function (string $url) use ($placeholder): string {
            $trimmed = trim($url);
            if ($trimmed === '') {
                return $placeholder;
            }

            if (preg_match('#^data:image/[a-zA-Z0-9.+-]+;base64,#', $trimmed) === 1) {
                return $trimmed;
            }

            return esc_url_raw($trimmed);
        };

        return array_values(array_map(static function ($card) use ($placeholder, $sanitize_image): array {
            $title = (string) ($card['title'] ?? '');
            $normalized = (string) ($card['normalized'] ?? flacso_convenios_normalize_string($title));
            $image = (string) ($card['image'] ?? '');
            if ($image === '') {
                $image = $placeholder;
            }

            return [
                'id' => absint($card['id'] ?? 0),
                'title' => wp_strip_all_tags($title),
                'normalized' => wp_strip_all_tags($normalized),
                'permalink' => esc_url_raw((string) ($card['permalink'] ?? '')),
                'image' => $sanitize_image($image),
                'placeholder' => $sanitize_image($placeholder),
            ];
        }, $dataset));
    }
}

if (!function_exists('flacso_convenios_flush_cache')) {
    function flacso_convenios_flush_cache(): void
    {
        delete_transient(flacso_convenios_dataset_key());
    }

    add_action('save_post', 'flacso_convenios_flush_cache');
    add_action('deleted_post', 'flacso_convenios_flush_cache');
}

if (!function_exists('flacso_render_convenios_html')) {
    function flacso_render_convenios_html(?array $dataset = null): string {
        $dataset = $dataset ?? flacso_convenios_get_dataset();

        if (empty($dataset)) {
            return '<p class="text-center text-muted py-5">' . esc_html__('No se encontraron convenios.', 'flacso-main-page') . '</p>';
        }

        $placeholder = flacso_convenios_get_placeholder_image();

        ob_start();
        foreach ($dataset as $card) {
            $titulo = $card['title'];
            $image  = $card['image'] ? esc_url($card['image']) : $placeholder;
            ?>
            <a class="convenio-card" href="<?php echo esc_url($card['permalink']); ?>">
                <div class="convenio-logo">
                    <img
                        src="<?php echo $image; ?>"
                        alt="<?php echo esc_attr(sprintf(__('Logo de %s', 'flacso-main-page'), $titulo)); ?>"
                        onerror="this.src='<?php echo esc_attr($placeholder); ?>';"
                    >
                </div>
                <div class="convenio-info">
                    <h3 class="convenio-titulo"><?php echo esc_html($titulo); ?></h3>
                </div>
                <i class="bi bi-chevron-right convenio-arrow" aria-hidden="true"></i>
            </a>
            <?php
        }

        return ob_get_clean();
    }
}

if (!function_exists('flacso_convenios_responsivos_sc')) {
    function flacso_convenios_responsivos_sc(): string {
        if (function_exists('flacso_global_styles')) {
            flacso_global_styles();
        }

        $dataset = flacso_convenios_get_dataset();
        if (empty($dataset)) {
            return '<p class="text-center text-muted py-5">' . esc_html__('No se encontraron convenios.', 'flacso-main-page') . '</p>';
        }

        if (!wp_script_is('flacso-convenios-react', 'registered')) {
            wp_register_script(
                'flacso-convenios-react',
                FLACSO_MAIN_PAGE_MODULE_URL . 'assets/js/flacso-convenios-react.js',
                ['wp-element'],
                FLACSO_MAIN_PAGE_VERSION,
                true
            );
        }
        wp_enqueue_script('flacso-convenios-react');

        $items = flacso_convenios_react_items($dataset);
        $app_id = 'flacso-convenios-react-' . wp_generate_password(8, false);
        $payload = [
            'app_id' => $app_id,
            'title' => __('Convenios', 'flacso-main-page'),
            'placeholder' => flacso_convenios_get_placeholder_image(),
            'search_placeholder' => __('Buscar convenio...', 'flacso-main-page'),
            'search_label' => __('Buscar convenio', 'flacso-main-page'),
            'clear_label' => __('Limpiar búsqueda', 'flacso-main-page'),
            'no_results' => __('No se encontraron resultados', 'flacso-main-page'),
            'no_results_hint' => __('Intenta con otros términos de búsqueda.', 'flacso-main-page'),
            'count_label' => __('resultados encontrados', 'flacso-main-page'),
            'items' => $items,
        ];

        $payload_json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload_json) || $payload_json === '') {
            return '<div class="flacso-convenios-fallback">' . flacso_render_convenios_html($dataset) . '</div>';
        }

        $payload_json = str_replace('</script', '<\\/script', $payload_json);

        ob_start(); ?>
        <section class="flacso-convenios-react-shortcode">
            <div id="<?php echo esc_attr($app_id); ?>" class="flacso-convenios-react-root" data-convenios-app="<?php echo esc_attr($app_id); ?>"></div>
            <script type="application/json" id="<?php echo esc_attr($app_id . '-data'); ?>"><?php echo $payload_json; ?></script>
            <noscript>
                <div class="flacso-convenios-fallback">
                    <?php echo flacso_render_convenios_html($dataset); ?>
                </div>
            </noscript>
        </section>
        <?php
        return ob_get_clean();
    }

    remove_shortcode('convenios_responsivos');
    add_shortcode('convenios_responsivos', 'flacso_convenios_responsivos_sc');
}

if (!function_exists('flacso_ajax_buscar_convenios_distancia')) {
    function flacso_ajax_buscar_convenios_distancia(): void {
        $query = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        if ($query === '') {
            echo flacso_render_convenios_html();
            wp_die();
        }

        $dataset = flacso_convenios_get_dataset();
        if (empty($dataset)) {
            echo flacso_render_convenios_html();
            wp_die();
        }

        $query_normalized = flacso_convenios_normalize_string($query);

        $matches = [];
        foreach ($dataset as $card) {
            $title_normal = $card['normalized'];

            $lev  = levenshtein($query_normalized, $title_normal);
            $len  = max(strlen($title_normal), 1);
            $sim  = 1 - ($lev / $len);
            $sub  = str_contains($title_normal, $query_normalized) ? 1 : 0;
            $pref = str_starts_with($title_normal, $query_normalized) ? 1 : 0;

            $threshold = strlen($query_normalized) <= 5 ? 0.45 : 0.55;
            $score     = ($sim * 0.6) + ($sub * 0.3) + ($pref * 0.3);
            if ($sub) {
                $score = max($score, 0.7);
            }

            if ($score >= $threshold) {
                $matches[] = [
                    'card' => $card,
                    'peso' => $score,
                ];
            }
        }

        if (empty($matches)) {
            printf(
                '<div class="text-center py-5"><p>%s</p></div>',
                wp_kses_post(
                    sprintf(
                        __('No se encontraron coincidencias para <strong>%s</strong>.', 'flacso-main-page'),
                        esc_html($query)
                    )
                )
            );
            wp_die();
        }

        usort($matches, static function ($a, $b) {
            return $b['peso'] <=> $a['peso'];
        });

        $ordered_cards = array_map(static function ($match) {
            return $match['card'];
        }, $matches);

        echo flacso_render_convenios_html($ordered_cards); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        wp_die();
    }

    add_action('wp_ajax_buscar_convenios_distancia', 'flacso_ajax_buscar_convenios_distancia');
    add_action('wp_ajax_nopriv_buscar_convenios_distancia', 'flacso_ajax_buscar_convenios_distancia');
}
