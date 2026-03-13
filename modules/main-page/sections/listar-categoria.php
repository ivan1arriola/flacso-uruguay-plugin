<?php
/**
 * Shortcode: [listar_categoria slug="nombre-del-slug"]
 * Lista publicaciones de una categoria (incluye subcategorias).
 */

if (!defined('ABSPATH')) {
    exit;
}

if (shortcode_exists('listar_categoria')) {
    remove_shortcode('listar_categoria');
}

if (!function_exists('flacso_listar_categoria_parse_bool')) {
    function flacso_listar_categoria_parse_bool($value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return $default;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return $default;
        }

        if (in_array($value, ['1', 'true', 'yes', 'si', 'on'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }
}

if (!function_exists('flacso_listar_categoria_shortcode')) {
    function flacso_listar_categoria_shortcode($atts): string
    {
        if (function_exists('flacso_global_styles')) {
            flacso_global_styles();
        }

        $atts = shortcode_atts([
            'slug' => '',
            'cantidad' => -1,
            'orden' => 'DESC',
            'ordenar_por' => 'date',
            'mostrar_extracto' => true,
            'mostrar_imagen' => true,
            'mostrar_fecha' => true,
            'mostrar_encabezado' => true,
            'texto_boton' => 'Leer nota',
        ], $atts, 'listar_categoria');

        $slug = sanitize_title((string) $atts['slug']);
        if ($slug === '') {
            return '<p><em>Debes especificar el atributo <code>slug</code> de la categoria.</em></p>';
        }

        $show_excerpt = flacso_listar_categoria_parse_bool($atts['mostrar_extracto'], true);
        $show_image = flacso_listar_categoria_parse_bool($atts['mostrar_imagen'], true);
        $show_date = flacso_listar_categoria_parse_bool($atts['mostrar_fecha'], true);
        $show_header = flacso_listar_categoria_parse_bool($atts['mostrar_encabezado'], true);

        $orderby_allow = ['date', 'title', 'modified', 'menu_order', 'rand'];
        $orderby = in_array((string) $atts['ordenar_por'], $orderby_allow, true) ? (string) $atts['ordenar_por'] : 'date';
        $order = strtoupper((string) $atts['orden']) === 'ASC' ? 'ASC' : 'DESC';

        $button_label = trim((string) $atts['texto_boton']);
        if ($button_label === '') {
            $button_label = 'Leer nota';
        }

        $normalized = [
            'slug' => $slug,
            'cantidad' => (int) $atts['cantidad'],
            'orden' => $order,
            'ordenar_por' => $orderby,
            'mostrar_extracto' => $show_excerpt,
            'mostrar_imagen' => $show_image,
            'mostrar_fecha' => $show_date,
            'mostrar_encabezado' => $show_header,
            'texto_boton' => $button_label,
        ];

        static $flacso_listar_cache_mem = [];
        $cache_key = 'flacso_listar_categoria_v2_' . md5((string) wp_json_encode($normalized));

        if (isset($flacso_listar_cache_mem[$cache_key])) {
            return (string) $flacso_listar_cache_mem[$cache_key];
        }

        $cached_html = get_transient($cache_key);
        if ($cached_html !== false) {
            $flacso_listar_cache_mem[$cache_key] = $cached_html;
            return (string) $cached_html;
        }

        $query = new WP_Query([
            'post_type' => 'post',
            'posts_per_page' => (int) $atts['cantidad'],
            'tax_query' => [[
                'taxonomy' => 'category',
                'field' => 'slug',
                'terms' => $slug,
                'include_children' => true,
                'operator' => 'IN',
            ]],
            'orderby' => $orderby,
            'order' => $order,
            'post_status' => 'publish',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        if (!$query->have_posts()) {
            return '<p><em>No se encontraron publicaciones en esta categoria.</em></p>';
        }

        $term = get_term_by('slug', $slug, 'category');
        $term_title = $term instanceof WP_Term ? $term->name : ucwords(str_replace('-', ' ', $slug));
        $term_description = $term instanceof WP_Term ? term_description($term->term_id, 'category') : '';
        $term_total = $term instanceof WP_Term ? (int) $term->count : (int) $query->post_count;

        $count_label = sprintf(
            _n('%d publicacion', '%d publicaciones', max(1, $term_total), 'flacso-main-page'),
            max(1, $term_total)
        );

        $posts = is_array($query->posts) ? $query->posts : [];
        $featured_posts = array_slice($posts, 0, 3);
        $compact_posts = array_slice($posts, 3);

        $render_card = static function (WP_Post $post, bool $featured = false) use ($show_image, $show_date, $show_excerpt, $button_label): string {
            $post_link = get_permalink($post);
            $post_title = get_the_title($post);
            $excerpt_words = $featured ? 28 : 16;
            $excerpt = wp_trim_words((string) get_the_excerpt($post), $excerpt_words, '...');
            $date_human = get_the_date('', $post);
            $date_iso = get_the_date('c', $post);
            $card_class = $featured
                ? 'flacso-grid-item flacso-categoria-card flacso-categoria-card--featured'
                : 'flacso-grid-item flacso-categoria-card flacso-categoria-card--compact';

            ob_start();
            ?>
            <article class="<?php echo esc_attr($card_class); ?>">
                <a href="<?php echo esc_url($post_link); ?>" class="flacso-grid-thumb flacso-categoria-card__thumb" aria-label="<?php echo esc_attr($post_title); ?>">
                    <?php if ($show_image && has_post_thumbnail($post)) : ?>
                        <?php
                        echo get_the_post_thumbnail(
                            $post,
                            'large',
                            [
                                'class' => 'flacso-grid-img',
                                'loading' => 'lazy',
                                'decoding' => 'async',
                                'fetchpriority' => 'low',
                            ]
                        );
                        ?>
                    <?php else : ?>
                        <span class="flacso-grid-placeholder-text"><?php esc_html_e('Sin imagen', 'flacso-main-page'); ?></span>
                    <?php endif; ?>

                    <?php if ($show_date) : ?>
                        <time class="flacso-categoria-card__date-badge" datetime="<?php echo esc_attr($date_iso); ?>">
                            <?php echo esc_html(get_the_date('d M Y', $post)); ?>
                        </time>
                    <?php endif; ?>
                </a>

                <div class="flacso-grid-contenido flacso-categoria-card__content">
                    <?php if ($show_date) : ?>
                        <p class="flacso-categoria-card__meta">
                            <time datetime="<?php echo esc_attr($date_iso); ?>"><?php echo esc_html($date_human); ?></time>
                        </p>
                    <?php endif; ?>

                    <h3 class="flacso-grid-titulo flacso-categoria-card__title">
                        <a href="<?php echo esc_url($post_link); ?>"><?php echo esc_html($post_title); ?></a>
                    </h3>

                    <?php if ($show_excerpt) : ?>
                        <p class="flacso-grid-extracto flacso-categoria-card__excerpt"><?php echo esc_html($excerpt); ?></p>
                    <?php endif; ?>

                    <a class="flacso-categoria-card__cta" href="<?php echo esc_url($post_link); ?>">
                        <span><?php echo esc_html($button_label); ?></span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </article>
            <?php

            return (string) ob_get_clean();
        };

        ob_start();
        ?>
        <section class="flacso-grid-wrapper flacso-categoria-vista" data-categoria-slug="<?php echo esc_attr($slug); ?>">
            <?php if ($show_header) : ?>
                <header class="flacso-categoria-vista__header">
                    <p class="flacso-categoria-vista__kicker"><?php esc_html_e('Categoria', 'flacso-main-page'); ?></p>
                    <h2 class="flacso-categoria-vista__title"><?php echo esc_html($term_title); ?></h2>
                    <p class="flacso-categoria-vista__meta"><?php echo esc_html($count_label); ?></p>
                    <?php if ($term_description !== '') : ?>
                        <div class="flacso-categoria-vista__description"><?php echo wp_kses_post($term_description); ?></div>
                    <?php endif; ?>
                </header>
            <?php endif; ?>

            <?php if (!empty($featured_posts)) : ?>
                <div class="flacso-categoria-vista__featured" aria-label="<?php esc_attr_e('Novedades destacadas', 'flacso-main-page'); ?>">
                    <?php foreach ($featured_posts as $featured_post) : ?>
                        <?php echo $render_card($featured_post, true); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($compact_posts)) : ?>
                <div class="flacso-categoria-vista__compact">
                    <div class="flacso-categoria-vista__compact-grid" aria-label="<?php esc_attr_e('Mas novedades', 'flacso-main-page'); ?>">
                        <?php foreach ($compact_posts as $compact_post) : ?>
                            <?php echo $render_card($compact_post, false); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <style>
            .flacso-categoria-vista {
                width: min(1240px, 100%);
                margin-inline: auto;
                padding: clamp(0.5rem, 1.6vw, 1rem) clamp(0rem, 1.2vw, 0.4rem) clamp(1.1rem, 2.8vw, 2rem);
            }

            .flacso-categoria-vista__header {
                margin: 0 0 clamp(1rem, 2vw, 1.6rem);
                padding: clamp(1rem, 1.9vw, 1.4rem) clamp(1rem, 2.4vw, 1.8rem);
                border-radius: 18px;
                border: 1px solid rgba(17, 47, 96, 0.14);
                background:
                    radial-gradient(circle at top right, rgba(245, 165, 36, 0.14), transparent 42%),
                    linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            }

            .flacso-categoria-vista__kicker {
                margin: 0;
                font-size: 0.78rem;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: var(--global-palette5, #687284);
            }

            .flacso-categoria-vista__title {
                margin: 0.25rem 0 0;
                color: var(--global-palette1, #1d3a72);
                font-size: clamp(1.45rem, 1.12rem + 1.1vw, 2.2rem);
                line-height: 1.08;
                letter-spacing: 0.01em;
            }

            .flacso-categoria-vista__meta {
                margin: 0.45rem 0 0;
                color: var(--global-palette4, #2f3a4d);
                font-size: 0.95rem;
                font-weight: 600;
            }

            .flacso-categoria-vista__description {
                margin-top: 0.8rem;
                color: var(--global-palette4, #2f3a4d);
                font-size: 0.98rem;
                line-height: 1.62;
            }

            .flacso-categoria-vista__featured {
                display: grid;
                grid-template-columns: 1fr;
                gap: clamp(1rem, 2vw, 1.5rem);
            }

            .flacso-categoria-vista__compact {
                margin-top: clamp(1rem, 1.8vw, 1.5rem);
            }

            .flacso-categoria-vista__compact-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: clamp(0.85rem, 1.5vw, 1.15rem);
            }

            @media (min-width: 700px) {
                .flacso-categoria-vista__featured {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .flacso-categoria-vista__compact-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (min-width: 1080px) {
                .flacso-categoria-vista__featured {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }

                .flacso-categoria-vista__compact-grid {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
            }

            @media (min-width: 1320px) {
                .flacso-categoria-vista__compact-grid {
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                }
            }

            .flacso-categoria-card,
            .flacso-grid-item {
                display: flex;
                flex-direction: column;
                min-height: 100%;
                border-radius: 18px;
                overflow: hidden;
                border: 1px solid rgba(15, 26, 45, 0.1);
                background: #ffffff;
                box-shadow: 0 10px 28px rgba(15, 26, 45, 0.08);
                transition: transform 220ms ease, box-shadow 220ms ease;
            }

            .flacso-categoria-card:hover,
            .flacso-categoria-card:focus-within {
                transform: translateY(-3px);
                box-shadow: 0 18px 34px rgba(15, 26, 45, 0.14);
            }

            .flacso-categoria-card__thumb,
            .flacso-grid-thumb {
                position: relative;
                display: block;
                width: 100%;
                aspect-ratio: 16 / 10;
                overflow: hidden;
                background: linear-gradient(145deg, #eef3f9 0%, #dfe8f4 100%);
            }

            .flacso-grid-img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
                transition: transform 280ms ease;
            }

            .flacso-categoria-card:hover .flacso-grid-img,
            .flacso-categoria-card:focus-within .flacso-grid-img {
                transform: scale(1.04);
            }

            .flacso-grid-placeholder-text {
                position: absolute;
                inset: 0;
                display: grid;
                place-items: center;
                color: #607086;
                font-size: 0.82rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.07em;
            }

            .flacso-categoria-card__date-badge {
                position: absolute;
                top: 0.72rem;
                left: 0.72rem;
                background: rgba(15, 26, 45, 0.82);
                color: #fff;
                font-size: 0.72rem;
                font-weight: 700;
                letter-spacing: 0.03em;
                text-transform: uppercase;
                border-radius: 999px;
                padding: 0.34rem 0.55rem;
                line-height: 1;
            }

            .flacso-categoria-card__content,
            .flacso-grid-contenido {
                padding: 1rem 1rem 1.1rem;
                display: flex;
                flex-direction: column;
                gap: 0.55rem;
                flex: 1 1 auto;
            }

            .flacso-categoria-card__meta {
                margin: 0;
                color: var(--global-palette5, #687284);
                font-size: 0.76rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.06em;
            }

            .flacso-categoria-card__title,
            .flacso-grid-titulo {
                margin: 0;
                font-size: clamp(1rem, 0.95rem + 0.2vw, 1.14rem);
                font-weight: 800;
                line-height: 1.35;
                text-wrap: balance;
            }

            .flacso-categoria-card__title a,
            .flacso-grid-titulo a {
                color: var(--global-palette3, #0f1a2d);
                text-decoration: none;
            }

            .flacso-categoria-card__title a:hover,
            .flacso-categoria-card__title a:focus-visible {
                color: var(--global-palette1, #1d3a72);
                text-decoration: underline;
                text-underline-offset: 2px;
            }

            .flacso-categoria-card__excerpt,
            .flacso-grid-extracto {
                margin: 0;
                color: var(--global-palette4, #2f3a4d);
                font-size: 0.93rem;
                line-height: 1.58;
                display: -webkit-box;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 3;
                overflow: hidden;
            }

            .flacso-categoria-card__cta {
                margin-top: auto;
                align-self: flex-start;
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                font-size: 0.88rem;
                font-weight: 800;
                color: var(--global-palette1, #1d3a72);
                text-decoration: none;
                border-bottom: 2px solid rgba(29, 58, 114, 0.2);
                padding-bottom: 0.12rem;
                transition: border-color 150ms ease, color 150ms ease;
            }

            .flacso-categoria-card__cta:hover,
            .flacso-categoria-card__cta:focus-visible {
                color: var(--global-palette2, #d29d00);
                border-bottom-color: currentColor;
            }

            .flacso-categoria-card--featured .flacso-categoria-card__thumb {
                aspect-ratio: 16 / 10;
            }

            .flacso-categoria-card--featured .flacso-categoria-card__content {
                padding: 1.05rem 1.05rem 1.2rem;
                min-height: 225px;
            }

            .flacso-categoria-card--featured .flacso-categoria-card__title {
                font-size: clamp(1.06rem, 1rem + 0.3vw, 1.24rem);
            }

            .flacso-categoria-card--compact .flacso-categoria-card__thumb {
                aspect-ratio: 4 / 3;
            }

            .flacso-categoria-card--compact .flacso-categoria-card__content {
                padding: 0.88rem 0.88rem 0.95rem;
                gap: 0.45rem;
                min-height: 210px;
            }

            .flacso-categoria-card--compact .flacso-categoria-card__title {
                font-size: clamp(0.94rem, 0.9rem + 0.18vw, 1.03rem);
            }

            .flacso-categoria-card--compact .flacso-categoria-card__excerpt {
                font-size: 0.86rem;
                line-height: 1.45;
                -webkit-line-clamp: 2;
            }

            .flacso-categoria-card--compact .flacso-categoria-card__cta {
                font-size: 0.8rem;
            }

            @media (max-width: 480px) {
                .flacso-categoria-vista {
                    padding-top: 0.2rem;
                }

                .flacso-categoria-vista__header {
                    padding: 0.9rem 0.9rem 1rem;
                    border-radius: 14px;
                }

                .flacso-categoria-card,
                .flacso-grid-item {
                    border-radius: 14px;
                }

                .flacso-categoria-card--featured .flacso-categoria-card__content,
                .flacso-categoria-card--compact .flacso-categoria-card__content {
                    min-height: 0;
                }
            }
        </style>
        <?php

        wp_reset_postdata();

        $html = (string) ob_get_clean();
        set_transient($cache_key, $html, 10 * MINUTE_IN_SECONDS);
        $flacso_listar_cache_mem[$cache_key] = $html;

        return $html;
    }
}

add_shortcode('listar_categoria', 'flacso_listar_categoria_shortcode');
