<?php
// ==================================================
// SECCIÓN CONGRESO - SISTEMA UNIFICADO FLACSO (v2025)
// ==================================================

if (!function_exists('flacso_section_congreso_render')) {
function flacso_section_congreso_render() {
    $settings = Flacso_Main_Page_Settings::get_section('congreso');
    $title = esc_html($settings['title']);
    $content = wp_kses_post($settings['content']);
    $cta_label = esc_html($settings['cta_label']);
    $cta_url = Flacso_Main_Page_Settings::normalize_url_output($settings['cta_url']);
    $background = esc_url($settings['background_image']);
    $gradient = 'linear-gradient(color-mix(in srgb, var(--global-palette1, #1d3a72) 90%, transparent), color-mix(in srgb, var(--global-palette1, #1d3a72) 95%, transparent))';
    $background_value = $background ? sprintf('%s, url(%s)', $gradient, $background) : $gradient;
    $background_style = sprintf(
        'background: %s; background-position: center; background-size: cover; background-attachment: fixed; background-repeat: no-repeat;',
        $background_value
    );
    ob_start();
    ?>

    <section class="flacso-congreso-section" style="<?php echo esc_attr($background_style); ?>">
        <div class="flacso-congreso-content flacso-content-shell flacso-fade-in">
            <h2 class="flacso-congreso-title"><?php echo $title; ?></h2>
            <p class="flacso-congreso-text"><?php echo $content; ?></p>
            <?php if ($cta_url): ?>
                <a href="<?php echo esc_url($cta_url); ?>"
                   class="flacso-btn flacso-btn-primary flacso-btn-anim flacso-congreso-btn">
                   <?php echo $cta_label; ?>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <?php
    return ob_get_clean();
    }
}

