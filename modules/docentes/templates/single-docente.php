<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $docente_id = get_the_ID();

    $prefijo_abrev = (string) get_post_meta($docente_id, 'prefijo_abrev', true);
    $titulo = (string) get_post_meta($docente_id, 'titulo', true);
    $nombre = (string) get_post_meta($docente_id, 'nombre', true);
    $apellido = (string) get_post_meta($docente_id, 'apellido', true);
    $cv_raw = (string) get_post_meta($docente_id, 'cv', true);

    $nombre_completo = function_exists('dp_nombre_completo')
        ? dp_nombre_completo($docente_id, true)
        : get_the_title($docente_id);
    $nombre_completo = $nombre_completo ?: get_the_title($docente_id);

    $iniciales = function_exists('dp_iniciales')
        ? dp_iniciales($nombre, $apellido, 'DP')
        : strtoupper(substr($nombre_completo, 0, 2));

    $docentes_url = get_post_type_archive_link('docente');
    if (!$docentes_url) {
        $docentes_url = home_url('/docentes/');
    }

    $slug_docente = (string) get_post_field('post_name', $docente_id);
    $ultima_actualizacion = get_the_modified_date(get_option('date_format'), $docente_id);

    $docente_correos = function_exists('dp_get_docente_emails') ? dp_get_docente_emails($docente_id) : [];
    $docente_redes = function_exists('dp_get_docente_socials') ? dp_get_docente_socials($docente_id) : [];

    $hero_color = function_exists('dp_color_from_string') ? dp_color_from_string($nombre_completo) : '#1d3a72';
    if (function_exists('ajustar_luminosidad')) {
        $hero_gradient = 'linear-gradient(135deg, ' . $hero_color . ' 0%, ' . ajustar_luminosidad($hero_color, -18) . ' 100%)';
    } else {
        $hero_gradient = 'linear-gradient(135deg, #1d3a72 0%, #0f254d 100%)';
    }
    ?>

    <div class="dp-single-docente-wrap site-container">
        <nav class="dp-docente-breadcrumb" aria-label="<?php echo esc_attr__('Breadcrumb', 'flacso-posgrados-docentes'); ?>">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Inicio', 'flacso-posgrados-docentes'); ?></a>
            <span aria-hidden="true">/</span>
            <a href="<?php echo esc_url($docentes_url); ?>"><?php esc_html_e('Docentes', 'flacso-posgrados-docentes'); ?></a>
            <span aria-hidden="true">/</span>
            <span><?php echo esc_html($nombre_completo); ?></span>
        </nav>

        <section class="dp-docente-hero" style="background: <?php echo esc_attr($hero_gradient); ?>;">
            <div class="dp-docente-hero__avatar">
                <?php if (has_post_thumbnail($docente_id)) : ?>
                    <?php
                    echo get_the_post_thumbnail(
                        $docente_id,
                        'large',
                        [
                            'class' => 'dp-docente-hero__avatar-img',
                            'alt' => $nombre_completo,
                            'loading' => 'eager',
                            'decoding' => 'async',
                        ]
                    );
                    ?>
                <?php else : ?>
                    <span class="dp-docente-hero__avatar-fallback"><?php echo esc_html($iniciales); ?></span>
                <?php endif; ?>
            </div>

            <div class="dp-docente-hero__content">
                <p class="dp-docente-hero__kicker"><?php esc_html_e('Perfil academico', 'flacso-posgrados-docentes'); ?></p>
                <h1><?php echo esc_html($nombre_completo); ?></h1>

                <?php if ($titulo !== '') : ?>
                    <p class="dp-docente-hero__subtitle"><?php echo esc_html($titulo); ?></p>
                <?php endif; ?>

                <div class="dp-docente-hero__meta">
                    <?php if ($prefijo_abrev !== '') : ?>
                        <span><?php echo esc_html($prefijo_abrev); ?></span>
                    <?php endif; ?>
                    <?php if ($slug_docente !== '') : ?>
                        <span><?php echo esc_html($slug_docente); ?></span>
                    <?php endif; ?>
                    <?php if ($ultima_actualizacion) : ?>
                        <span><?php echo esc_html(sprintf(__('Actualizado %s', 'flacso-posgrados-docentes'), $ultima_actualizacion)); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dp-docente-hero__cta">
                <a href="<?php echo esc_url($docentes_url); ?>" class="dp-btn dp-btn--light">
                    <?php esc_html_e('Ver listado completo', 'flacso-posgrados-docentes'); ?>
                </a>
            </div>
        </section>

        <div class="dp-docente-layout">
            <article class="dp-card dp-docente-cv">
                <h2><?php esc_html_e('Trayectoria y CV', 'flacso-posgrados-docentes'); ?></h2>
                <?php if ($cv_raw !== '') : ?>
                    <div class="dp-docente-cv__content">
                        <?php echo wp_kses_post(wpautop($cv_raw)); ?>
                    </div>
                <?php else : ?>
                    <p class="dp-empty"><?php esc_html_e('Este perfil aun no tiene un CV publicado.', 'flacso-posgrados-docentes'); ?></p>
                <?php endif; ?>
            </article>

            <aside class="dp-docente-sidebar">
                <section class="dp-card">
                    <h3><?php esc_html_e('Contacto', 'flacso-posgrados-docentes'); ?></h3>
                    <?php if (!empty($docente_correos)) : ?>
                        <ul class="dp-contact-list-clean">
                            <?php foreach ($docente_correos as $correo) :
                                $correo_email = isset($correo['email']) ? (string) $correo['email'] : '';
                                if ($correo_email === '') {
                                    continue;
                                }
                                $correo_label = isset($correo['label']) && $correo['label'] !== ''
                                    ? (string) $correo['label']
                                    : __('Correo', 'flacso-posgrados-docentes');
                                ?>
                                <li>
                                    <span class="dp-contact-label"><?php echo esc_html($correo_label); ?></span>
                                    <a href="mailto:<?php echo esc_attr(antispambot($correo_email)); ?>">
                                        <?php echo esc_html(antispambot($correo_email)); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="dp-empty"><?php esc_html_e('No hay correos publicados.', 'flacso-posgrados-docentes'); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($docente_redes)) : ?>
                        <div class="dp-social-list">
                            <?php foreach ($docente_redes as $red) :
                                $red_url = isset($red['url']) ? (string) $red['url'] : '';
                                if ($red_url === '') {
                                    continue;
                                }
                                $red_label = isset($red['label']) && $red['label'] !== ''
                                    ? (string) $red['label']
                                    : __('Perfil', 'flacso-posgrados-docentes');
                                ?>
                                <a href="<?php echo esc_url($red_url); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html($red_label); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </aside>
        </div>
    </div>

    <style>
        .dp-single-docente-wrap {
            --dp-primary: var(--global-palette1, #1d3a72);
            --dp-text: var(--global-palette3, #1f2937);
            --dp-muted: var(--global-palette5, #6b7280);
            --dp-surface: #ffffff;
            --dp-border: rgba(18, 36, 74, 0.12);
            --dp-shadow: 0 14px 40px rgba(17, 24, 39, 0.12);
            color: var(--dp-text);
            padding-block: 2.2rem 3.2rem;
        }

        .dp-docente-breadcrumb {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            font-size: 0.92rem;
            color: var(--dp-muted);
            margin-bottom: 1rem;
        }

        .dp-docente-breadcrumb a {
            color: var(--dp-primary);
            text-decoration: none;
        }

        .dp-docente-breadcrumb a:hover {
            text-decoration: underline;
        }

        .dp-docente-hero {
            border-radius: 20px;
            padding: 2rem;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 1.5rem;
            align-items: center;
            color: #ffffff;
            box-shadow: var(--dp-shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .dp-docente-hero__avatar {
            width: 128px;
            height: 128px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid rgba(255, 255, 255, 0.95);
            background: rgba(255, 255, 255, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .dp-docente-hero__avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .dp-docente-hero__avatar-fallback {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: #ffffff;
        }

        .dp-docente-hero__kicker {
            margin: 0 0 0.35rem;
            font-size: 0.78rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 700;
            opacity: 0.84;
        }

        .dp-docente-hero h1 {
            margin: 0;
            font-size: clamp(1.45rem, 2.4vw, 2.3rem);
            line-height: 1.14;
            color: #ffffff;
        }

        .dp-docente-hero__subtitle {
            margin: 0.45rem 0 0.9rem;
            font-size: 1.05rem;
            opacity: 0.92;
        }

        .dp-docente-hero__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .dp-docente-hero__meta span {
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
        }

        .dp-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.62rem 1.2rem;
            text-decoration: none;
            font-weight: 700;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .dp-btn--light {
            background: #ffffff;
            color: var(--dp-primary);
        }

        .dp-btn--light:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.22);
        }

        .dp-docente-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 1.2rem;
            align-items: start;
        }

        .dp-card {
            background: var(--dp-surface);
            border: 1px solid var(--dp-border);
            border-radius: 16px;
            padding: 1.2rem;
            box-shadow: 0 8px 26px rgba(15, 23, 42, 0.06);
        }

        .dp-card h2,
        .dp-card h3 {
            margin: 0 0 0.75rem;
            color: var(--dp-primary);
        }

        .dp-card h2 {
            font-size: 1.35rem;
        }

        .dp-card h3 {
            font-size: 1.05rem;
        }

        .dp-docente-cv__content {
            line-height: 1.65;
            color: var(--dp-text);
        }

        .dp-docente-cv__content p:last-child {
            margin-bottom: 0;
        }

        .dp-docente-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .dp-contact-list-clean {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }

        .dp-contact-list-clean li {
            border: 1px solid var(--dp-border);
            border-radius: 12px;
            padding: 0.65rem 0.75rem;
            background: rgba(255, 255, 255, 0.84);
        }

        .dp-contact-label {
            display: block;
            font-size: 0.78rem;
            color: var(--dp-muted);
            margin-bottom: 0.12rem;
        }

        .dp-contact-list-clean a {
            color: var(--dp-primary);
            text-decoration: none;
            word-break: break-word;
        }

        .dp-contact-list-clean a:hover {
            text-decoration: underline;
        }

        .dp-social-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.85rem;
        }

        .dp-social-list a {
            text-decoration: none;
            font-size: 0.82rem;
            color: var(--dp-primary);
            border: 1px solid var(--dp-border);
            border-radius: 999px;
            padding: 0.3rem 0.7rem;
            background: rgba(255, 255, 255, 0.92);
        }

        .dp-social-list a:hover {
            border-color: var(--dp-primary);
        }

        .dp-empty {
            margin: 0;
            color: var(--dp-muted);
        }

        @media (max-width: 1024px) {
            .dp-docente-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 820px) {
            .dp-single-docente-wrap {
                padding-top: 1.6rem;
            }

            .dp-docente-hero {
                grid-template-columns: 1fr;
                text-align: center;
                padding: 1.45rem;
            }

            .dp-docente-hero__avatar {
                margin-inline: auto;
                width: 112px;
                height: 112px;
            }

            .dp-docente-hero__meta,
            .dp-docente-hero__cta {
                justify-content: center;
            }
        }
    </style>

<?php
endwhile;
get_footer();
