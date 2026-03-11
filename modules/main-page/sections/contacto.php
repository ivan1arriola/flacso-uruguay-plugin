<?php
// ==================================================
// SECCION CONTACTO - FONDO CLARO + IMAGEN DECORATIVA
// ==================================================

if (!function_exists('flacso_main_page_hex_to_rgba')) {
function flacso_main_page_hex_to_rgba($hex, $opacity = 1.0) {
    $hex = trim((string) $hex);
    $hex = ltrim($hex, '#');
    if ($hex === '') {
        $hex = '000000';
    }
    if (strlen($hex) === 3) {
        $hex = str_repeat($hex[0], 2) . str_repeat($hex[1], 2) . str_repeat($hex[2], 2);
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        $hex = '000000';
    }
    $opacity = (float) $opacity;
    if ($opacity < 0) {
        $opacity = 0;
    } elseif ($opacity > 1) {
        $opacity = 1;
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return sprintf('rgba(%d, %d, %d, %.3f)', $r, $g, $b, $opacity);
}
}

if (!function_exists('flacso_section_contacto_render')) {
function flacso_section_contacto_render() {
    $settings = Flacso_Main_Page_Settings::get_section('contacto');
    $unique_id = 'contacto_' . wp_generate_password(6, false);

    $title = esc_html($settings['title']);
    $subtitle = esc_html($settings['subtitle']);
    $cta_label = esc_html($settings['cta_label']);
    $cta_url = Flacso_Main_Page_Settings::normalize_url_output($settings['cta_url']);
    $background_image = Flacso_Main_Page_Settings::normalize_url_output($settings['background_image'] ?? '');
    $background_color = sanitize_hex_color($settings['background_color'] ?? '#f2f6ff') ?: '#f2f6ff';
    $mode_choices = ['color', 'gradient', 'image', 'image_overlay'];
    $background_mode = $settings['background_mode'] ?? 'color';
    if (!in_array($background_mode, $mode_choices, true)) {
        $background_mode = 'color';
    }
    $gradient_start = sanitize_hex_color($settings['background_gradient_start'] ?? $background_color) ?: $background_color;
    $gradient_end = sanitize_hex_color($settings['background_gradient_end'] ?? $gradient_start) ?: $gradient_start;
    $gradient_angle = isset($settings['background_gradient_angle']) ? (int) $settings['background_gradient_angle'] : 135;
    $gradient_angle = max(0, min(360, $gradient_angle));
    $overlay_styles = ['solid', 'gradient'];
    $overlay_style = $settings['background_overlay_style'] ?? 'solid';
    if (!in_array($overlay_style, $overlay_styles, true)) {
        $overlay_style = 'solid';
    }
    $overlay_color = sanitize_hex_color($settings['background_overlay_color'] ?? '#0f1a2d') ?: '#0f1a2d';
    $overlay_color_secondary = sanitize_hex_color($settings['background_overlay_color_secondary'] ?? $overlay_color) ?: $overlay_color;
    $overlay_opacity = isset($settings['background_overlay_opacity']) ? (float) $settings['background_overlay_opacity'] : 0.78;
    $overlay_opacity = max(0, min(1, $overlay_opacity));
    $overlay_opacity_secondary = isset($settings['background_overlay_opacity_secondary']) ? (float) $settings['background_overlay_opacity_secondary'] : 0.45;
    $overlay_opacity_secondary = max(0, min(1, $overlay_opacity_secondary));
    if ($overlay_style === 'solid') {
        $overlay_color_secondary = $overlay_color;
        $overlay_opacity_secondary = $overlay_opacity;
    }
    $overlay_angle = isset($settings['background_overlay_angle']) ? (int) $settings['background_overlay_angle'] : 180;
    $overlay_angle = max(0, min(360, $overlay_angle));

    $background_layer = 'none';
    $background_color_value = $background_color ?: '#f2f6ff';

    switch ($background_mode) {
        case 'gradient':
            $background_color_value = $gradient_start ?: $background_color_value;
            $background_layer = sprintf(
                'linear-gradient(%1$ddeg, %2$s 0%%, %3$s 100%%)',
                $gradient_angle,
                $gradient_start,
                $gradient_end
            );
            break;
        case 'image':
            $background_layer = $background_image ? sprintf('url(%s)', esc_url($background_image)) : 'none';
            break;
        case 'image_overlay':
            $primary_overlay = flacso_main_page_hex_to_rgba($overlay_color, $overlay_opacity);
            $secondary_overlay = flacso_main_page_hex_to_rgba($overlay_color_secondary, $overlay_opacity_secondary);
            $overlay_layer = sprintf(
                'linear-gradient(%1$ddeg, %2$s 0%%, %3$s 100%%)',
                $overlay_angle,
                $primary_overlay,
                $secondary_overlay
            );
            if ($background_image) {
                $background_layer = sprintf('%s, url(%s)', $overlay_layer, esc_url($background_image));
            } else {
                $background_layer = $overlay_layer;
            }
            if (!$background_color_value) {
                $background_color_value = $overlay_color;
            }
            break;
        default:
            $background_layer = 'none';
            break;
    }

    $css_variables = sprintf(
        '--flacso-contacto-bg-color:%s; --flacso-contacto-bg-image:%s;',
        esc_attr($background_color_value),
        esc_attr($background_layer)
    );

    ob_start();
    ?>
    <div class="flacso-contacto flacso-contacto-<?php echo esc_attr($unique_id); ?>" id="<?php echo esc_attr($unique_id); ?>" style="<?php echo esc_attr($css_variables); ?>">
        <section class="flacso-contacto-section">
            <div class="flacso-content-shell flacso-contacto-content">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h2 class="flacso-contacto-title"><?php echo $title; ?></h2>
                        <p class="flacso-contacto-subtitle"><?php echo $subtitle; ?></p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <?php if ($cta_url): ?>
                            <a href="<?php echo esc_url($cta_url); ?>" class="flacso-btn flacso-btn-primary flacso-btn-anim">
                                <?php echo $cta_label; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <?php
    return ob_get_clean();
    }
}

