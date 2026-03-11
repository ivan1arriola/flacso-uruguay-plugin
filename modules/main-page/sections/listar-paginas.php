<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('flacso_posgrados_vigentes')) {
    /**
     * Tabla de siglas y tipos para los posgrados vigentes.
     */
    function flacso_posgrados_vigentes(): array {
        return [
            12330 => ['EDUTIC', 'Maestría'],
            12336 => ['MESYP', 'Maestría'],
            12343 => ['MG', 'Maestría'],
            12310 => ['EAPET', 'Especialización'],
            12316 => ['EGCCD', 'Especialización'],
            12278 => ['DEPPI', 'Diplomado'],
            14444 => ['DESI', 'Diplomado'],
            12282 => ['DEVBG', 'Diplomado'],
            12288 => ['DEVNNA', 'Diplomado'],
            13202 => ['DCCH', 'Diploma'],
            12295 => ['DAVIA', 'Diploma'],
            12299 => ['DG', 'Diploma'],
            20668 => ['IAPE', 'Diploma'],
            12302 => ['DIDYP', 'Diploma'],
            14657 => ['DSMSYT', 'Diploma'],
        ];
    }
}

if (!function_exists('flacso_listar_paginas_shortcode')) {
    /**
     * Shortcode principal: [listar_paginas padre="Diplomas"] o [listar_paginas padre_id="12294"].
     */
    function flacso_listar_paginas_shortcode($atts = []): string {
        if (function_exists('flacso_global_styles')) {
            flacso_global_styles();
        }

        $atts = shortcode_atts([
            'padre'             => '',
            'padre_id'          => '',
            'posts_per_page'    => -1,
            'mostrar_inactivos' => '0',
        ], $atts, 'listar_paginas');

        $parent_id = 0;
        if (!empty($atts['padre_id'])) {
            $parent_id = absint($atts['padre_id']);
        } else {
            $padre = sanitize_text_field($atts['padre']);
            if ('' === $padre) {
                return '<div class="notice notice-error"><p>' . esc_html__('Debes indicar el atributo "padre" o "padre_id".', 'flacso-main-page') . '</p></div>';
            }

            $padre_query = new WP_Query([
                'post_type'              => 'page',
                'title'                  => $padre,
                'post_status'            => 'all',
                'posts_per_page'         => 1,
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => false,
                'update_post_meta_cache' => false,
                'orderby'                => 'post_date ID',
                'order'                  => 'ASC',
            ]);

            if (!empty($padre_query->post)) {
                $parent_id = (int) $padre_query->post->ID;
            } else {
                wp_reset_postdata();
                return sprintf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    esc_html(sprintf(__('No existe la página padre "%s".', 'flacso-main-page'), $padre))
                );
            }

            wp_reset_postdata();
        }

        if (!$parent_id) {
            return '<div class="notice notice-error"><p>' . esc_html__('No se pudo determinar la página padre solicitada.', 'flacso-main-page') . '</p></div>';
        }

        $query = new WP_Query([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'post_parent'    => $parent_id,
            'posts_per_page' => intval($atts['posts_per_page']),
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ]);

        if (!$query->have_posts()) {
            return '<div class="notice notice-info"><p>' . esc_html__('No hay páginas disponibles.', 'flacso-main-page') . '</p></div>';
        }

        $vigentes = flacso_posgrados_vigentes();
        $mostrar_inactivos = rest_sanitize_boolean($atts['mostrar_inactivos']);

        ob_start();
        ?>
        <style>
            .flacso-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 1rem 0;
            }

            @media (min-width: 768px) {
                .flacso-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (min-width: 1024px) {
                .flacso-grid {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
            }

            .flacso-card {
                display: flex;
                flex-direction: column;
                border-radius: 1rem;
                overflow: hidden;
                background: var(--global-palette9, #ffffff);
                box-shadow: 0 4px 16px rgba(0,0,0,0.12);
                text-decoration: none;
                color: inherit;
                transition: transform .22s ease, box-shadow .22s ease;
                cursor: pointer;
                touch-action: manipulation;
            }

            .flacso-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 10px 26px rgba(0,0,0,0.2);
            }

            .flacso-card__img {
                width: 100%;
                aspect-ratio: 1 / 1;
                background-size: cover;
                background-position: center;
                position: relative;
            }

            .flacso-card__img::after {
                content: "";
                position: absolute;
                inset: 0;
                background: linear-gradient(180deg, rgba(0,0,0,0) 45%, rgba(0,0,0,.65) 100%);
            }

            .flacso-card__title {
                position: absolute;
                bottom: .9rem;
                left: .9rem;
                right: .9rem;
                color: #fff;
                font-family: var(--global-heading-font-family, sans-serif);
                font-size: 1.15rem;
                font-weight: 600;
                line-height: 1.3;
                z-index: 3;
                text-shadow: 0 2px 6px rgba(0,0,0,.6);
            }

            .flacso-card__content {
                display: flex;
                flex-direction: column;
                gap: .65rem;
                padding: 1rem 1.1rem 1.2rem;
                background: var(--global-palette9, #ffffff);
            }

            .flacso-badges {
                display: flex;
                flex-wrap: wrap;
                gap: .4rem;
            }

            .flacso-badge {
                background: var(--global-palette1, #13294b);
                color: #fff;
                font-size: .7rem;
                padding: .28rem .6rem;
                border-radius: 50px;
                display: inline-flex;
                align-items: center;
                gap: .3rem;
            }

            .flacso-badge.nuevo {
                background: var(--global-palette2, #f7b733);
                color: var(--global-palette3, #0f1a2d);
            }

            .flacso-card.inactivo .flacso-badge {
                background: var(--global-palette6, #cbd5f5);
                color: var(--global-palette4, #475569);
            }

            .flacso-card.inactivo {
                opacity: .45;
                filter: grayscale(65%);
                pointer-events: none;
            }
        </style>

        <div class="flacso-grid">
            <?php
            while ($query->have_posts()) :
                $query->the_post();
                $id = get_the_ID();
                $title = get_the_title();
                $thumb = get_the_post_thumbnail_url($id, 'large') ?: 'https://via.placeholder.com/900?text=FLACSO';
                $vigente = array_key_exists($id, $vigentes);

                if (!$vigente && !$mostrar_inactivos) {
                    continue;
                }
                $created = get_post_time('U', true, $id);
                $es_nuevo = (current_time('timestamp') - $created) < (30 * DAY_IN_SECONDS);
                $abbr = $vigente ? ($vigentes[$id][0] ?? '') : '';
                $tipo = $vigente ? ($vigentes[$id][1] ?? '') : '';
                ?>
                <a class="flacso-card <?php echo $vigente ? '' : 'inactivo'; ?>" href="<?php the_permalink(); ?>">
                    <div class="flacso-card__img" style="background-image:url('<?php echo esc_url($thumb); ?>');">
                        <div class="flacso-card__title"><?php echo esc_html($title); ?></div>
                    </div>
                    <div class="flacso-card__content">
                        <div class="flacso-badges">
                            <?php if ($vigente) : ?>
                                <?php if ($es_nuevo) : ?>
                                    <span class="flacso-badge nuevo"><i class="bi bi-stars"></i> <?php esc_html_e('Nuevo', 'flacso-main-page'); ?></span>
                                <?php endif; ?>
                                <?php if ($abbr) : ?>
                                    <span class="flacso-badge"><i class="bi bi-hash"></i> <?php echo esc_html($abbr); ?></span>
                                <?php endif; ?>
                                <?php if ($tipo) : ?>
                                    <span class="flacso-badge"><i class="bi bi-mortarboard"></i> <?php echo esc_html($tipo); ?></span>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="flacso-badge"><i class="bi bi-x-circle"></i> <?php esc_html_e('No vigente', 'flacso-main-page'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
        <?php

        wp_reset_postdata();
        return ob_get_clean();
    }

    add_shortcode('listar_paginas', 'flacso_listar_paginas_shortcode');
}
