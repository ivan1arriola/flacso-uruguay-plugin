<?php
/**
 * Builder de la portada FLACSO.
 *
 * Las secciones ya no se conocen aquí una por una: se obtienen del registry y
 * el orden/visibilidad proviene de Flacso_Main_Page_Settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('flacso_homepage_builder', 'flacso_homepage_builder_render');

if (!function_exists('flacso_homepage_builder_render')) {
    function flacso_homepage_builder_render(): string {
        if (!class_exists('Flacso_Homepage_Section_Registry')) {
            return '';
        }

        $registry = Flacso_Homepage_Section_Registry::all();
        $is_frontend_render = !is_admin() && !(defined('REST_REQUEST') && REST_REQUEST);
        $use_react = $is_frontend_render && apply_filters('flacso_main_page_use_react', false);

        $blocks_by_key = [];
        foreach ($registry as $section_key => $definition) {
            $section_key = class_exists('Flacso_Main_Page_Section_Keys')
                ? Flacso_Main_Page_Section_Keys::canonicalize((string) $section_key)
                : sanitize_key((string) $section_key);

            if ($section_key === '' || !Flacso_Main_Page_Settings::is_section_visible($section_key)) {
                continue;
            }

            $renderer = (string) ($definition['function'] ?? '');
            if ($renderer === '' || !is_callable($renderer)) {
                do_action('flacso_homepage_renderer_missing', $section_key, $definition);
                continue;
            }

            $react_component = (string) ($definition['react_component'] ?? '');
            $is_react_events = $use_react
                && $react_component === 'eventos-proximos'
                && function_exists('flacso_section_eventos_get_items');
            $content = $is_react_events ? '' : (string) call_user_func($renderer);
            if ($content === '' && !$is_react_events) {
                continue;
            }

            $blocks_by_key[$section_key] = [
                'key' => $section_key,
                'label' => Flacso_Main_Page_Settings::get_section_label($section_key),
                'content' => $content,
                'react_component' => $react_component,
                'owner' => (string) ($definition['owner'] ?? ''),
            ];
        }

        $ordered_blocks = [];
        foreach (Flacso_Main_Page_Settings::get_homepage_section_order() as $section_key) {
            if (isset($blocks_by_key[$section_key])) {
                $ordered_blocks[] = $blocks_by_key[$section_key];
                unset($blocks_by_key[$section_key]);
            }
        }
        foreach ($blocks_by_key as $remaining) {
            $ordered_blocks[] = $remaining;
        }

        if (!$use_react) {
            return flacso_homepage_builder_render_markup($ordered_blocks);
        }

        wp_enqueue_script('flacso-main-page-react');
        $main_id = 'main';
        $app_id = 'flacso-main-page-react-' . wp_generate_password(8, false);
        $sections_payload = [];

        foreach ($ordered_blocks as $section) {
            $payload_section = [
                'key' => (string) ($section['key'] ?? ''),
                'label' => (string) ($section['label'] ?? ''),
                'content' => (string) ($section['content'] ?? ''),
            ];

            if (
                ($section['react_component'] ?? '') === 'eventos-proximos'
                && function_exists('flacso_section_eventos_get_items')
            ) {
                $items = flacso_section_eventos_get_items(10);
                if (empty($items) || !is_array($items)) {
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
                    }, $items)),
                ];
                $payload_section['content'] = '';
            }

            $sections_payload[] = $payload_section;
        }

        $payload_json = wp_json_encode(
            ['main_id' => $main_id, 'sections' => $sections_payload],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if (!is_string($payload_json) || $payload_json === '') {
            return flacso_homepage_builder_render_markup($ordered_blocks, $main_id);
        }
        $payload_json = str_replace('</script', '<\\/script', $payload_json);

        ob_start();
        ?>
        <div id="<?php echo esc_attr($app_id); ?>" class="flacso-main-page-react-root" data-flacso-app="<?php echo esc_attr($app_id); ?>"></div>
        <script type="application/json" id="<?php echo esc_attr($app_id . '-data'); ?>"><?php echo $payload_json; ?></script>
        <noscript><?php echo flacso_homepage_builder_render_markup($ordered_blocks, $main_id); ?></noscript>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('flacso_homepage_builder_render_markup')) {
    function flacso_homepage_builder_render_markup(array $ordered_blocks, string $main_id = 'main'): string {
        ob_start();
        ?>
        <div class="flacso-main-page flacso-homepage-completa">
            <main class="flacso-home-layout" role="main" id="<?php echo esc_attr($main_id); ?>">
                <?php foreach ($ordered_blocks as $section) : ?>
                    <?php
                    $section_key = sanitize_key((string) ($section['key'] ?? ''));
                    $surface_variant = $section_key === 'hero'
                        ? 'flacso-home-block__surface--bleed'
                        : 'flacso-home-block__surface--card';
                    ?>
                    <article class="flacso-home-block flacso-home-block--<?php echo esc_attr($section_key); ?>"
                             data-section-key="<?php echo esc_attr($section_key); ?>"
                             data-section-label="<?php echo esc_attr((string) ($section['label'] ?? '')); ?>"
                             data-section-owner="<?php echo esc_attr((string) ($section['owner'] ?? '')); ?>">
                        <div class="flacso-home-block__surface <?php echo esc_attr($surface_variant); ?> flacso-home-block__surface--<?php echo esc_attr($section_key); ?>">
                            <?php echo (string) ($section['content'] ?? ''); ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </main>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('flacso_homepage_builder_page_has_builder')) {
    function flacso_homepage_builder_page_has_builder(int $post_id): bool {
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
    function flacso_homepage_builder_template_takeover(string $template): string {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || !is_front_page()) {
            return $template;
        }
        if (!apply_filters('flacso_main_page_template_takeover_enabled', true)) {
            return $template;
        }
        $post_id = (int) get_queried_object_id();
        if (!flacso_homepage_builder_page_has_builder($post_id)) {
            return $template;
        }
        $theme_front_page = locate_template('front-page.php');
        return $theme_front_page !== '' ? $theme_front_page : $template;
    }
}

add_filter('template_include', 'flacso_homepage_builder_template_takeover', 99);
