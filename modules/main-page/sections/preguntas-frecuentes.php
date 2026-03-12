<?php

/**
 * Shortcode: bloque de contacto / preguntas frecuentes.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('flacso_preguntas_frecuentes_shortcode')) {
    function flacso_preguntas_frecuentes_shortcode($atts = []): string {
        if (function_exists('flacso_global_styles')) {
            flacso_global_styles();
        }

        $atts = shortcode_atts([
            'titulo'            => __('Quedamos a tu disposición para despejar cualquier duda', 'flacso-main-page'),
            'descripcion'       => __('¿Querés saber más sobre contenidos, fechas o modalidades? Escribinos y coordinamos enseguida.', 'flacso-main-page'),
            'cta_url'           => 'https://flacso.edu.uy/preguntas-frecuentes/',
            'cta_label'         => __('Ver preguntas frecuentes', 'flacso-main-page'),
            'mail'              => 'inscripciones@flacso.edu.uy',
            'mail_label'        => __('Escribinos por correo', 'flacso-main-page'),
            'background_image'  => 'https://flacso.edu.uy/wp-content/uploads/2023/08/library-background.jpg',
        ], $atts, 'preguntas_frecuentes');

        $mail = sanitize_email($atts['mail']);
        $mail_label = sanitize_text_field($atts['mail_label']);
        $titulo = wp_kses_post($atts['titulo']);
        $descripcion = wp_kses_post($atts['descripcion']);
        $cta_url = esc_url($atts['cta_url']);
        $cta_label = sanitize_text_field($atts['cta_label']);
        $bg_image = esc_url($atts['background_image']);

        ob_start();
        ?>
        <section class="flacso-faq-contact-block flacso-fade-in" aria-labelledby="flacso-faq-heading">
            <div class="flacso-faq-card" style="--faq-bg:url('<?php echo $bg_image; ?>');">
                <div class="flacso-faq-card__overlay"></div>
                <div class="flacso-faq-card__inner flacso-content-shell">
                    <div class="flacso-faq-card__content">
                        <p class="flacso-faq-card__eyebrow"><?php esc_html_e('¿Tenés dudas?', 'flacso-main-page'); ?></p>
                        <h2 id="flacso-faq-heading"><?php echo $titulo; ?></h2>
                        <p class="flacso-faq-card__description">
                            <?php echo $descripcion; ?>
                        </p>
                        <p class="flacso-faq-card__mail">
                            <span><?php esc_html_e('Escribinos a', 'flacso-main-page'); ?></span>
                            <a href="mailto:<?php echo esc_attr($mail); ?>"><?php echo esc_html($mail); ?></a>
                        </p>
                        <div class="flacso-faq-card__actions">
                            <a href="<?php echo $cta_url; ?>" class="flacso-btn flacso-btn-primary flacso-btn-anim" target="_blank" rel="noopener">
                                <?php echo esc_html($cta_label); ?>
                            </a>
                            <a href="mailto:<?php echo esc_attr($mail); ?>" class="flacso-btn flacso-btn-outline flacso-btn-anim">
                                <?php echo esc_html($mail_label); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <style>
        .flacso-faq-contact-block {
            margin-top: clamp(2rem, 6vw, 4rem);
        }

        .flacso-faq-card {
            position: relative;
            border-radius: 28px;
            overflow: hidden;
            min-height: clamp(320px, 55vh, 480px);
            background: radial-gradient(circle at top, rgba(67, 4, 103, 0.85), rgba(15, 26, 45, 0.95));
            box-shadow: 0 30px 80px rgba(13, 18, 38, 0.35);
        }

        .flacso-faq-card__overlay {
            position: absolute;
            inset: 0;
            background-image: linear-gradient(120deg, rgba(114, 0, 139, 0.85), rgba(23, 5, 45, 0.9)), var(--faq-bg, none);
            background-size: cover;
            background-position: center;
            transform: scale(1.05);
            filter: brightness(0.85);
        }

        .flacso-faq-card__inner {
            position: relative;
            z-index: 1;
            padding-top: clamp(2.5rem, 6vw, 5rem);
            padding-bottom: clamp(2.5rem, 6vw, 5rem);
        }

        .flacso-faq-card__content {
            max-width: var(--flacso-section-max-width);
            color: var(--global-palette9, #ffffff);
            text-align: center;
            margin: 0 auto;
        }

        .flacso-faq-card__eyebrow {
            letter-spacing: 0.4em;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.75);
        }

        .flacso-faq-card__content h2 {
            font-size: clamp(2rem, 4vw, 2.8rem);
            margin-bottom: 1rem;
            color: inherit;
        }

        .flacso-faq-card__description {
            font-size: 1.05rem;
            line-height: 1.8;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 1.5rem;
        }

        .flacso-faq-card__mail {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--global-palette9, #ffffff);
            margin-bottom: 2rem;
        }

        .flacso-faq-card__mail span {
            display: block;
            font-size: 0.9rem;
            font-weight: 400;
            opacity: 0.75;
        }

        .flacso-faq-card__mail a {
            color: inherit;
            text-decoration: underline;
        }

        .flacso-faq-card__actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .flacso-faq-card .flacso-btn {
            min-width: 230px;
        }

        @media (max-width: 640px) {
            .flacso-faq-card {
                border-radius: 18px;
            }

            .flacso-faq-card__actions {
                flex-direction: column;
            }

            .flacso-faq-card .flacso-btn {
                width: 100%;
            }
        }
        </style>
        <?php

        return ob_get_clean();
    }

    add_shortcode('preguntas_frecuentes', 'flacso_preguntas_frecuentes_shortcode');
}
