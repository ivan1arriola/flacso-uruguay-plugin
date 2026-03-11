<?php
// ==================================================
// SECCIÓN QUIÉNES SOMOS - SCROLL REVEAL LIMPIO
// ==================================================

if (!function_exists('flacso_section_quienes_somos_render')) {
function flacso_section_quienes_somos_render() {
    $settings = Flacso_Main_Page_Settings::get_section('quienes');
    
    $url_sobre_nosotros = Flacso_Main_Page_Settings::normalize_url_output($settings['cta_url']);
    $unique_id = 'quienes_' . wp_generate_password(6, false);
    $logo_url = 'https://flacso.edu.uy/wp-content/uploads/2026/02/cropped-cropped-Logos-FLACSO-Claro.png';
    $title = esc_html($settings['title']);
    $content = wp_kses_post($settings['content']);
    $cta_label = esc_html($settings['cta_label']);
    $background_image = esc_url($settings['background_image']);
    $accent = esc_attr($settings['highlight_color'] ?: '#fcd116');
    
    ob_start();
    ?>
    
    <style>
    .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-section {
        position: relative;
        background: #ffffff;
        color: var(--global-palette3, #0f1a2d);
        padding: clamp(1.25rem, 2vw, 1.8rem) 0;
        text-align: left;
        overflow: hidden;
        width: 100%;
    }

    .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-inner {
        max-width: 1060px;
        margin: 0 auto;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border: 1px solid rgba(29, 58, 114, 0.12);
        border-radius: 24px;
        padding: clamp(1.15rem, 2.2vw, 2rem);
        box-shadow: 0 16px 36px rgba(15, 26, 45, 0.08);
    }

    .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-layout {
        display: grid;
        grid-template-columns: 160px minmax(0, 1fr);
        gap: clamp(0.85rem, 1.8vw, 1.4rem);
        align-items: start;
    }

    .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-copy {
        min-width: 0;
    }

    .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-title {
        font-size: clamp(2rem, 3vw, 2.8rem);
        font-weight: 800;
        margin-bottom: 1rem;
        color: var(--global-palette1, #1d3a72);
        font-family: var(--global-heading-font-family, "Helvetica Neue", sans-serif);
    }

    .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-brand {
        width: 160px;
        height: 160px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border-radius: 0;
        border: 0;
        margin: 0;
        box-shadow: none;
        overflow: hidden;
    }

    .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-brand img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: contain;
        transform: none;
        opacity: 1 !important;
        filter: none !important;
    }

    .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-text {
        font-size: 1.08rem;
        line-height: 1.75;
        opacity: 1;
        font-family: var(--global-body-font-family, "Helvetica Neue", sans-serif);
        text-align: left;
        max-width: 100%;
        margin: 0;
    }

    .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-text,
    .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-text * {
        color: var(--global-palette3, #0f1a2d) !important;
        opacity: 1 !important;
    }

    .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-text p {
        margin-bottom: 1rem;
    }

    .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-btn {
        background-color: <?php echo $accent; ?>;
        color: var(--global-palette3, #0f1a2d);
        border: none;
        font-weight: 600;
        padding: 12px 30px;
        border-radius: 50px;
        margin-top: 1.1rem;
        display: inline-flex;
        transition: all 0.25s ease;
    }

    .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-btn:hover {
        background-color: #fff;
        transform: translateY(-3px);
    }

    @media (max-width: 768px) {
        .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-section {
            padding: 1rem 0;
            background-attachment: scroll;
        }
        .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-inner {
            border-radius: 18px;
            padding: 1rem;
        }
        .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-layout {
            grid-template-columns: 102px minmax(0, 1fr);
            gap: 0.85rem;
        }
        .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-brand {
            width: 102px;
            height: 102px;
        }
        .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-brand img {
            transform: none;
        }
        .flacso-quienes-<?php echo esc_attr($unique_id); ?> .flacso-quienes-text {
            font-size: 0.98rem;
            line-height: 1.65;
            text-align: left;
        }
    }
    </style>

    <div class="flacso-quienes-<?php echo esc_attr($unique_id); ?>">
        <section class="flacso-quienes-section">
            <div class="flacso-content-shell">
                <div class="flacso-quienes-inner">
                    <div class="flacso-quienes-layout">
                        <div class="flacso-quienes-brand">
                            <img src="<?php echo esc_url($logo_url); ?>" alt="FLACSO Uruguay">
                        </div>
                        <div class="flacso-quienes-copy">
                            <h2 class="flacso-quienes-title"><?php echo $title; ?></h2>
                            <div class="flacso-quienes-text">
                                <?php echo $content; ?>
                            </div>
                            <?php if ($url_sobre_nosotros): ?>
                                <a class="flacso-btn" href="<?php echo esc_url($url_sobre_nosotros); ?>">
                                    <?php echo $cta_label; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    
    <?php
    return ob_get_clean();
    }
}

