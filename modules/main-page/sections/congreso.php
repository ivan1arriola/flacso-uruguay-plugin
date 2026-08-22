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
    $background_value = $background ? sprintf("url('%s')", $background) : 'none';
    $background_style = sprintf('--flacso-congreso-bg-image: %s;', $background_value);
    ob_start();
    ?>

    <section class="flacso-congreso-section" style="<?php echo esc_attr($background_style); ?>">
        <div class="flacso-congreso-media" aria-hidden="true"></div>
        <div class="flacso-congreso-content flacso-fade-in">
            <h2 class="flacso-congreso-title"><?php echo $title; ?></h2>
            <p class="flacso-congreso-text"><?php echo $content; ?></p>
            <?php if ($cta_url): ?>
                <a href="<?php echo esc_url($cta_url); ?>" class="flacso-btn flacso-congreso-btn">
                    <span><?php echo $cta_label; ?></span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <?php
    return ob_get_clean();
    }
}

