<?php
/**
 * Sección institucional de la portada.
 */

if (!function_exists('flacso_section_quienes_somos_render')) {
    function flacso_section_quienes_somos_render() {
        $settings = Flacso_Main_Page_Settings::get_section('quienes');

        $url_sobre_nosotros = Flacso_Main_Page_Settings::normalize_url_output($settings['cta_url']);
        $unique_id = 'flacso-quienes-' . wp_generate_password(6, false);
        $logo_url = 'https://flacso.edu.uy/wp-content/uploads/2026/02/cropped-cropped-Logos-FLACSO-Claro.png';
        $title = esc_html($settings['title']);
        $content = wp_kses_post($settings['content']);
        $cta_label = esc_html($settings['cta_label']);
        $accent = sanitize_hex_color($settings['highlight_color'] ?: '#fcd116');

        if (!$accent) {
            $accent = '#fcd116';
        }

        ob_start();
        ?>
        <section
            class="flacso-quienes-section"
            aria-labelledby="<?php echo esc_attr($unique_id); ?>"
            style="--flacso-quienes-accent: <?php echo esc_attr($accent); ?>;"
        >
            <div class="flacso-content-shell">
                <div class="flacso-quienes-card">
                    <div class="flacso-quienes-brand">
                        <span class="flacso-quienes-brand__orb" aria-hidden="true"></span>
                        <div class="flacso-quienes-brand__logo">
                            <img src="<?php echo esc_url($logo_url); ?>" alt="FLACSO Uruguay">
                        </div>
                        <p class="flacso-quienes-brand__name">Facultad Latinoamericana de Ciencias Sociales</p>
                    </div>

                    <div class="flacso-quienes-copy">
                        <h2 id="<?php echo esc_attr($unique_id); ?>" class="flacso-quienes-title"><?php echo $title; ?></h2>
                        <div class="flacso-quienes-text">
                            <?php echo $content; ?>
                        </div>

                        <?php if ($url_sobre_nosotros): ?>
                            <a class="flacso-quienes-cta" href="<?php echo esc_url($url_sobre_nosotros); ?>">
                                <span><?php echo $cta_label; ?></span>
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M5 12h14"></path>
                                    <path d="m13 6 6 6-6 6"></path>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
