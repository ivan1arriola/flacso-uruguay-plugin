<?php

// ==================================================
// HOMEPAGE COMPLETA FLACSO
// ==================================================

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('flacso_homepage_builder', 'flacso_homepage_builder_render');
if (!function_exists('flacso_homepage_builder_render')) {
    function flacso_homepage_builder_render() {
        $renderers = [
            [
                'key' => 'hero',
                'function' => 'flacso_section_hero_render',
            ],
            [
                'key' => 'novedades_destacadas',
                'function' => 'flacso_section_novedades_destacadas_render',
            ],
            [
                'key' => 'eventos',
                'function' => 'flacso_section_eventos_render',
            ],
            [
                'key' => 'seminarios',
                'function' => 'flacso_section_seminarios_proximos_render',
            ],
            [
                'key' => 'novedades',
                'function' => 'flacso_section_novedades_render',
            ],
            [
                'key' => 'novedades_busqueda',
                'function' => 'flacso_section_novedades_busqueda_render',
            ],
            [
                'key' => 'quienes',
                'function' => 'flacso_section_quienes_somos_render',
            ],
            [
                'key' => 'instagram',
                'function' => 'flacso_section_instagram_render',
            ],
            [
                'key' => 'posgrados',
                'function' => 'flacso_section_oferta_educativa_render',
            ],
            [
                'key' => 'congreso',
                'function' => 'flacso_section_congreso_render',
            ],
            [
                'key' => 'contacto',
                'function' => 'flacso_section_contacto_render',
            ],
        ];
        $is_frontend_render = !is_admin() && !(defined('REST_REQUEST') && REST_REQUEST);
        $use_react = $is_frontend_render && apply_filters('flacso_main_page_use_react', true);

        $section_blocks = [];
        foreach ($renderers as $renderer) {
            if (!is_callable($renderer['function'])) {
                continue;
            }

            if (!Flacso_Main_Page_Settings::is_section_visible($renderer['key'])) {
                continue;
            }

            $is_react_events = $use_react && $renderer['key'] === 'eventos' && function_exists('flacso_section_eventos_get_items');
            $content = $is_react_events ? '' : (string) call_user_func($renderer['function']);
            if ($content === '' && !$is_react_events) {
                continue;
            }

            $section_blocks[] = [
                'key' => $renderer['key'],
                'label' => Flacso_Main_Page_Settings::get_section_label($renderer['key']),
                'content' => $content,
            ];
        }

        $blocks_by_key = [];
        foreach ($section_blocks as $section) {
            $blocks_by_key[$section['key']] = $section;
        }

        $preferred_order = Flacso_Main_Page_Settings::get_homepage_section_order();
        $ordered_blocks = [];
        foreach ($preferred_order as $order_key) {
            if (isset($blocks_by_key[$order_key])) {
                $ordered_blocks[] = $blocks_by_key[$order_key];
                unset($blocks_by_key[$order_key]);
            }
        }

        if (!empty($blocks_by_key)) {
            foreach ($blocks_by_key as $remaining_block) {
                $ordered_blocks[] = $remaining_block;
            }
        }

        if (!$use_react) {
            return flacso_homepage_builder_render_markup($ordered_blocks);
        }

        wp_enqueue_script('flacso-main-page-react');

        $main_id = 'flacso-home-' . wp_generate_password(6, false);
        $app_id = 'flacso-main-page-react-' . wp_generate_password(8, false);

        $sections_payload = [];
        foreach ($ordered_blocks as $section) {
            $payload_section = [
                'key' => (string) ($section['key'] ?? ''),
                'label' => (string) ($section['label'] ?? ''),
                'content' => (string) ($section['content'] ?? ''),
            ];

            if ($payload_section['key'] === 'eventos' && function_exists('flacso_section_eventos_get_items')) {
                $events_items = flacso_section_eventos_get_items(10);
                if (empty($events_items) || !is_array($events_items)) {
                    continue;
                }

                $payload_section['component'] = 'eventos-proximos';
                $payload_section['data'] = [
                    'items' => array_values(array_map(static function ($item): array {
                        return [
                            'id' => absint($item['id'] ?? 0),
                            'link' => esc_url_raw((string) ($item['link'] ?? '')),
                            'title' => wp_strip_all_tags((string) ($item['title'] ?? '')),
                            'excerpt' => wp_strip_all_tags((string) ($item['excerpt'] ?? '')),
                            'weekday' => wp_strip_all_tags((string) ($item['weekday'] ?? '')),
                            'day' => wp_strip_all_tags((string) ($item['day'] ?? '')),
                            'month' => wp_strip_all_tags((string) ($item['month'] ?? '')),
                            'status' => wp_strip_all_tags((string) ($item['status'] ?? '')),
                            'class' => sanitize_html_class((string) ($item['class'] ?? '')),
                            'range' => wp_strip_all_tags((string) ($item['range'] ?? '')),
                            'hora' => wp_strip_all_tags((string) ($item['hora'] ?? '')),
                            'duration' => wp_strip_all_tags((string) ($item['duration'] ?? '')),
                            'thumbnail' => esc_url_raw((string) ($item['thumbnail'] ?? '')),
                            'datetime_iso' => wp_strip_all_tags((string) ($item['datetime_iso'] ?? '')),
                        ];
                    }, $events_items)),
                ];
                // Evita duplicar marcado pesado cuando React ya tiene datos estructurados.
                $payload_section['content'] = '';
            }

            $sections_payload[] = $payload_section;
        }

        $payload = [
            'main_id' => $main_id,
            'sections' => $sections_payload,
        ];

        $payload_json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload_json) || $payload_json === '') {
            return flacso_homepage_builder_render_markup($ordered_blocks, $main_id);
        }

        // Evita cerrar el bloque <script> si el contenido incluye "</script>".
        $payload_json = str_replace('</script', '<\\/script', $payload_json);

        ob_start(); ?>
        <div id="<?php echo esc_attr($app_id); ?>" class="flacso-main-page-react-root" data-flacso-app="<?php echo esc_attr($app_id); ?>"></div>
        <script type="application/json" id="<?php echo esc_attr($app_id . '-data'); ?>"><?php echo $payload_json; ?></script>
        <noscript><?php echo flacso_homepage_builder_render_markup($ordered_blocks, $main_id); ?></noscript>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('flacso_homepage_builder_render_markup')) {
    function flacso_homepage_builder_render_markup(array $ordered_blocks, string $main_id = 'flacso-home'): string
    {
        ob_start(); ?>
        <div class="flacso-main-page flacso-homepage-completa">
            <main class="flacso-home-layout" role="main" id="<?php echo esc_attr($main_id); ?>">
                <?php foreach ($ordered_blocks as $section) : ?>
                    <?php
                    $section_key = sanitize_key((string) ($section['key'] ?? ''));
                    $is_bleed_surface = $section_key === 'hero';
                    $surface_variant = $is_bleed_surface ? 'flacso-home-block__surface--bleed' : 'flacso-home-block__surface--card';
                    ?>
                    <article class="flacso-home-block flacso-home-block--<?php echo esc_attr($section['key']); ?>"
                             data-section-key="<?php echo esc_attr($section['key']); ?>"
                             data-section-label="<?php echo esc_attr($section['label']); ?>">
                        <div class="flacso-home-block__surface <?php echo esc_attr($surface_variant); ?> flacso-home-block__surface--<?php echo esc_attr($section_key); ?>">
                            <?php echo $section['content']; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </main>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('flacso_homepage_builder_page_has_builder')) {
    function flacso_homepage_builder_page_has_builder(int $post_id): bool
    {
        if ($post_id <= 0) {
            return false;
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return false;
        }

        $content = (string) $post->post_content;
        if ($content === '') {
            return false;
        }

        if (has_shortcode($content, 'flacso_homepage_builder')) {
            return true;
        }

        if (function_exists('has_block') && has_block('flacso-uruguay/homepage-builder', $content)) {
            return true;
        }

        return strpos($content, 'wp:flacso-uruguay/homepage-builder') !== false;
    }
}

if (!function_exists('flacso_homepage_builder_template_takeover')) {
    function flacso_homepage_builder_template_takeover(string $template): string
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return $template;
        }

        if (!is_front_page()) {
            return $template;
        }

        $enabled = apply_filters('flacso_main_page_template_takeover_enabled', true);
        if (!$enabled) {
            return $template;
        }

        $post_id = (int) get_queried_object_id();
        if (!flacso_homepage_builder_page_has_builder($post_id)) {
            return $template;
        }

        $takeover_template = FLACSO_MAIN_PAGE_MODULE_PATH . 'templates/homepage-takeover.php';
        if (!is_readable($takeover_template)) {
            return $template;
        }

        return $takeover_template;
    }
}

add_filter('template_include', 'flacso_homepage_builder_template_takeover', 99);


