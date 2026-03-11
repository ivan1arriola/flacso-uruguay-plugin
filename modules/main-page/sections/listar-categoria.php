<?php
// ==================================================
// SHORTCODE: [listar_categoria slug="nombre-del-slug"]
// Lista genérica de publicaciones (incluye subcategorías)
// Optimizado con cache y query eficiente
// ==================================================

if (!defined('ABSPATH')) {
    exit;
}

if (shortcode_exists('listar_categoria')) {
    remove_shortcode('listar_categoria');
}

function flacso_listar_categoria_shortcode($atts) {
    if (function_exists('flacso_global_styles')) {
        flacso_global_styles();
    }

    // Atributos configurables
    $atts = shortcode_atts([
        'slug' => '',
        'cantidad' => -1,
        'orden' => 'DESC',
        'ordenar_por' => 'date',
        'mostrar_extracto' => true,
        'mostrar_imagen' => true,
    ], $atts, 'listar_categoria');

    if (empty($atts['slug'])) {
        return '<p><em>⚠️ Debés especificar el atributo <code>slug</code> de la categoría.</em></p>';
    }

    // --------- Cache (in-memory por petición + transient) ----------
    static $flacso_listar_cache_mem = [];
    $cache_key = 'flacso_listar_categoria_' . md5(serialize($atts));

    if (isset($flacso_listar_cache_mem[$cache_key])) {
        return $flacso_listar_cache_mem[$cache_key];
    }
    $cached_html = get_transient($cache_key);
    if ($cached_html !== false) {
        $flacso_listar_cache_mem[$cache_key] = $cached_html;
        return $cached_html;
    }

    // Sanitizar parámetros de orden
    $orderby_allow = ['date','title','modified','menu_order','rand'];
    $orderby = in_array($atts['ordenar_por'], $orderby_allow, true) ? $atts['ordenar_por'] : 'date';
    $order   = strtoupper($atts['orden']) === 'ASC' ? 'ASC' : 'DESC';

    // --------- Query optimizada (incluye subcategorías con tax_query) ----------
    $slug = sanitize_title($atts['slug']);

    $query = new WP_Query([
        'post_type'              => 'post',
        'posts_per_page'         => intval($atts['cantidad']),
        'tax_query'              => [[
            'taxonomy'         => 'category',
            'field'            => 'slug',
            'terms'            => $slug,
            'include_children' => true,
            'operator'         => 'IN',
        ]],
        'orderby'                => $orderby,
        'order'                  => $order,
        'post_status'            => 'publish',
        // performance flags:
        'no_found_rows'          => true,
        'ignore_sticky_posts'    => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'fields'                 => '', // traer objetos completos (necesitamos excerpt, thumbnail)
    ]);

    if (!$query->have_posts()) {
        return '<p><em>No se encontraron publicaciones en esta categoría.</em></p>';
    }

    ob_start(); ?>
    <div class="flacso-grid-wrapper">
        <div class="flacso-grid-lista">
            <?php while ($query->have_posts()): $query->the_post(); ?>
                <article class="flacso-grid-item">
                    <?php if ($atts['mostrar_imagen'] && has_post_thumbnail()): ?>
                        <a href="<?php the_permalink(); ?>" class="flacso-grid-thumb" aria-label="<?php the_title_attribute(); ?>">
                            <?php the_post_thumbnail(
                                'medium',
                                [
                                    'class'          => 'flacso-grid-img',
                                    'loading'        => 'lazy',
                                    'decoding'       => 'async',
                                    'fetchpriority'  => 'low',
                                ]
                            ); ?>
                        </a>
                    <?php else: ?>
                        <a href="<?php the_permalink(); ?>" class="flacso-grid-thumb flacso-grid-placeholder" aria-label="<?php the_title_attribute(); ?>">
                            <div class="flacso-grid-placeholder-text">Sin imagen</div>
                        </a>
                    <?php endif; ?>

                    <div class="flacso-grid-contenido">
                        <h3 class="flacso-grid-titulo">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h3>
                        <?php if ($atts['mostrar_extracto']): ?>
                            <p class="flacso-grid-extracto"><?php echo wp_trim_words(get_the_excerpt(), 20, '…'); ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
    </div>

    <style>
    /* ===============================
       CONTENEDOR CENTRAL
    =============================== */
    .flacso-grid-wrapper {
        width: 100%;
        margin: 0;
        padding: 0;
    }
    /* Constrain contenido usando .flacso-content-shell si se envuelve externamente */

    /* ===============================
       GRID FLACSO - CATEGORÍAS
    =============================== */
    .flacso-grid-lista {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.8rem;
        margin: 1.5rem 0;
    }

    @media (min-width: 576px) {
        .flacso-grid-lista {
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
        }
    }

    @media (min-width: 1024px) {
        .flacso-grid-lista {
            grid-template-columns: repeat(3, 1fr);
            gap: 2.2rem;
            margin: 2.5rem 0;
        }
    }

    .flacso-grid-item {
        background: linear-gradient(145deg, #ffffff 0%, #fafbff 100%);
        border-radius: 20px;
        overflow: hidden;
        border: 1px solid rgba(15, 26, 45, 0.08);
        box-shadow: 0 8px 24px rgba(15, 26, 45, 0.08), 0 4px 8px rgba(0,0,0,0.04);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 100%;
        position: relative;
    }

    .flacso-grid-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--global-palette12, #1159af) 0%, var(--global-palette15, #f5a524) 100%);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .flacso-grid-item:hover {
        transform: translateY(-6px);
        box-shadow: 0 16px 40px rgba(15, 26, 45, 0.12), 0 8px 16px rgba(0,0,0,0.06);
    }

    .flacso-grid-item:hover::before {
        opacity: 1;
    }

    /* Imagen cuadrada optimizada para imágenes 1:1 */
    .flacso-grid-thumb {
        width: 100%;
        aspect-ratio: 1/1;
        overflow: hidden;
        display: block;
        position: relative;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    .flacso-grid-thumb::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(0,0,0,0) 60%, rgba(0,0,0,0.03) 100%);
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .flacso-grid-item:hover .flacso-grid-thumb::after {
        opacity: 1;
    }

    .flacso-grid-img {
        width: 100%;
        height: 100%;
        object-fit: cover; /* ✅ cubre todo el espacio para imágenes cuadradas */
        display: block;
        transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .flacso-grid-item:hover .flacso-grid-img {
        transform: scale(1.05);
    }

    /* Placeholder mejorado */
    .flacso-grid-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%);
        color: var(--global-palette5, #6b7280);
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 600;
        position: relative;
    }

    .flacso-grid-placeholder::before {
        content: '📄';
        display: block;
        font-size: 2.5rem;
        opacity: 0.3;
        position: absolute;
    }

    .flacso-grid-placeholder-text {
        opacity: 0.6;
        position: relative;
        z-index: 1;
        margin-top: 3rem;
    }

    /* Contenido mejorado */
    .flacso-grid-contenido {
        padding: 1.5rem 1.5rem 1.75rem;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.7rem;
        background: linear-gradient(180deg, #ffffff 0%, #fafbff 100%);
    }

    .flacso-grid-titulo {
        font-size: 1.15rem;
        font-weight: 700;
        margin: 0;
        line-height: 1.35;
        letter-spacing: -0.01em;
    }

    .flacso-grid-titulo a {
        color: var(--global-palette3, #0f1a2d);
        text-decoration: none;
        background: linear-gradient(135deg, var(--global-palette3, #0f1a2d) 0%, var(--global-palette1, #1d3a72) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        transition: all 0.2s ease;
        display: inline;
    }

    .flacso-grid-item:hover .flacso-grid-titulo a {
        letter-spacing: 0;
    }

    .flacso-grid-extracto {
        color: var(--global-palette5, #6b7280);
        font-size: 0.95rem;
        line-height: 1.6;
        margin: 0;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        opacity: 0.9;
    }

    .flacso-grid-item:hover .flacso-grid-extracto {
        opacity: 1;
    }

    @media (max-width: 768px) {
        .flacso-grid-lista { 
            gap: 1.5rem; 
            margin: 1rem 0;
        }
        .flacso-grid-titulo { 
            font-size: 1.05rem; 
        }
        .flacso-grid-contenido {
            padding: 1.2rem 1.3rem 1.4rem;
        }
        .flacso-grid-extracto {
            font-size: 0.9rem;
            -webkit-line-clamp: 2;
        }
    }

    @media (max-width: 480px) {
        .flacso-grid-lista {
            gap: 1.2rem;
        }
        .flacso-grid-item {
            border-radius: 16px;
        }
    }
    </style>
    <?php
    wp_reset_postdata();

    $html = ob_get_clean();
    set_transient($cache_key, $html, 10 * MINUTE_IN_SECONDS); // cache 10 min
    $flacso_listar_cache_mem[$cache_key] = $html;

    return $html;
}

add_shortcode('listar_categoria', 'flacso_listar_categoria_shortcode');
