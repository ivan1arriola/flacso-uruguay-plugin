<?php

if (!defined('ABSPATH')) {
    exit;
}

if (defined('FLACSO_OFERTA_ACADEMICA_DATA_ONLY') && FLACSO_OFERTA_ACADEMICA_DATA_ONLY) {
    return;
}

if (!function_exists('flacso_oferta_academica_shortcode')) {
    /**
     * Shortcode principal de oferta académica.
     */
    function flacso_oferta_academica_shortcode($atts = []): string {
        if (function_exists('flacso_global_styles')) {
            flacso_global_styles();
        }

        $atts = shortcode_atts([
            'mostrar_filtros'   => 'true',
            'mostrar_inactivos' => '0',
        ], $atts, 'oferta_academica');

        $mostrar_filtros = rest_sanitize_boolean($atts['mostrar_filtros']);
        $mostrar_inactivos = rest_sanitize_boolean($atts['mostrar_inactivos']);

        // Ajustes admin (si existen) prevalecen sobre atributos del shortcode
        if (class_exists('Flacso_Main_Page_Settings')) {
            $oa = Flacso_Main_Page_Settings::get_section('oferta_academica');
            $mostrar_filtros = !empty($oa['show_filters']);
            $mostrar_inactivos = !empty($oa['show_inactivos']);
        } else {
            $oa = [];
        }

        $seminarios_limit = isset($oa['seminarios_limit']) ? intval($oa['seminarios_limit']) : 10;
        $mostrar_seminarios = (empty($oa) || !empty($oa['show_seminarios']));
        
        // REMOVED: Esta lógica ocultaba toda la página si no había seminarios
        // Ahora mostramos la página incluso sin seminarios, solo ocultamos la sección de seminarios

        $unique_id = sanitize_html_class(wp_unique_id('oferta_'));
        $wrapper_class = 'flacso-oferta-' . $unique_id;

        $vigentes = function_exists('flacso_posgrados_vigentes') ? flacso_posgrados_vigentes() : [];

        ob_start();
        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>">
            <style>
                .<?php echo esc_html($wrapper_class); ?> .oferta-academica-hero {
                    --c1: var(--global-palette1, #1d3a72);
                    --c2: var(--global-palette3, #0f1a2d);
                    --accentA: var(--global-palette12, #1159af);
                    --accentB: var(--global-palette15, #f5a524);
                    background:
                        linear-gradient(135deg, var(--c1) 0%, var(--c2) 100%),
                        radial-gradient(circle at 30% 40%, rgba(17,89,175,0.35) 0%, rgba(17,89,175,0) 55%),
                        radial-gradient(circle at 70% 65%, rgba(245,165,36,0.30) 0%, rgba(245,165,36,0) 60%);
                    background-blend-mode: overlay, screen, normal;
                    background-size: 200% 200%, 100% 100%, 100% 100%;
                    animation: shimmer 10s ease-in-out infinite, glowPulse 6s ease-in-out infinite;
                    color: var(--global-palette9, #ffffff);
                    padding: 140px 0 120px;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                    isolation: isolate; /* asegura mezcla correcta de pseudo-elementos */
                }

                /* Nueva ambientación: bandas y velos luminosos animados */
                .<?php echo esc_html($wrapper_class); ?> .hero-ambient {
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    z-index: 2;
                    overflow: hidden;
                }

                .<?php echo esc_html($wrapper_class); ?> .hero-ambient span {
                    position: absolute;
                    width: 130%;
                    left: -15%;
                    height: 55%;
                    top: 8%;
                    background:
                        linear-gradient(95deg, rgba(17,89,175,0.35) 0%, rgba(17,89,175,0.05) 40%, rgba(17,89,175,0) 70%),
                        linear-gradient(95deg, rgba(245,165,36,0.25) 10%, rgba(245,165,36,0) 55%);
                    background-blend-mode: screen;
                    filter: blur(14px);
                    transform-origin: center;
                    animation: ambientDrift 34s linear infinite, ambientFade 9s ease-in-out infinite;
                    mix-blend-mode: screen;
                }

                .<?php echo esc_html($wrapper_class); ?> .hero-ambient span:nth-child(2) {
                    top: 42%;
                    height: 62%;
                    animation-duration: 42s, 11s;
                    animation-delay: 4s, 2s;
                    background:
                        linear-gradient(105deg, rgba(245,165,36,0.30) 0%, rgba(245,165,36,0.08) 40%, rgba(245,165,36,0) 75%),
                        linear-gradient(105deg, rgba(17,89,175,0.25) 15%, rgba(17,89,175,0) 70%);
                    background-blend-mode: screen;
                }

                .<?php echo esc_html($wrapper_class); ?> .hero-ambient span:nth-child(3) {
                    top: 23%;
                    height: 50%;
                    animation-duration: 50s, 13s;
                    animation-delay: 8s, 3s;
                    background:
                        linear-gradient(65deg, rgba(17,89,175,0.25) 0%, rgba(17,89,175,0.05) 50%, rgba(17,89,175,0) 80%),
                        linear-gradient(65deg, rgba(245,165,36,0.22) 20%, rgba(245,165,36,0) 75%);
                    background-blend-mode: screen;
                }

                @keyframes ambientDrift {
                    0% { transform: translateY(0) rotate(0deg); }
                    50% { transform: translateY(-8%) rotate(2deg); }
                    100% { transform: translateY(0) rotate(0deg); }
                }

                @keyframes ambientFade {
                    0%,100% { opacity: .65; }
                    50% { opacity: .95; }
                }

                /* Spotlight y capas de textura para enriquecer el fondo */
                .<?php echo esc_html($wrapper_class); ?> .oferta-academica-hero::before {
                    content: "";
                    position: absolute;
                    inset: 0;
                    background:
                        radial-gradient(circle at 35% 40%, rgba(245,165,36,0.25), rgba(245,165,36,0) 60%),
                        radial-gradient(circle at 70% 60%, rgba(17,89,175,0.22), rgba(17,89,175,0) 55%),
                        linear-gradient(160deg, rgba(255,255,255,0.14) 0%, rgba(255,255,255,0) 55%);
                    mix-blend-mode: screen;
                    pointer-events: none;
                    opacity: .70;
                    animation: spotlightShift 14s ease-in-out infinite;
                    z-index: 1;
                }

                .<?php echo esc_html($wrapper_class); ?> .oferta-academica-hero::after {
                    content: "";
                    position: absolute;
                    inset: 0;
                    /* SVG procedural noise muy liviano */
                    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='120' height='120'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3' stitchTiles='stitch'/></filter><rect width='120' height='120' filter='url(%23n)' opacity='0.28'/></svg>");
                    background-size: 180px 180px;
                    mix-blend-mode: overlay;
                    opacity: .18;
                    animation: noiseDrift 40s linear infinite;
                    pointer-events: none;
                    z-index: 2;
                }

                @keyframes spotlightShift {
                    0% { transform: translate3d(0,0,0) scale(1); }
                    50% { transform: translate3d(2%, -2%,0) scale(1.03); }
                    100% { transform: translate3d(0,0,0) scale(1); }
                }

                @keyframes noiseDrift {
                    0% { background-position: 0 0; }
                    100% { background-position: 300px 300px; }
                }

                @media (prefers-reduced-motion: reduce) {
                    .<?php echo esc_html($wrapper_class); ?> .oferta-academica-hero,
                    .<?php echo esc_html($wrapper_class); ?> .oferta-academica-hero::before,
                    .<?php echo esc_html($wrapper_class); ?> .oferta-academica-hero::after { animation: none !important; }
                    .<?php echo esc_html($wrapper_class); ?> .hero-ambient span { animation: none !important; }
                }

                @keyframes shimmer {
                    0% { background-position: 0% 50%; }
                    50% { background-position: 100% 50%; }
                    100% { background-position: 0% 50%; }
                }

                @keyframes glowPulse {
                    0%, 100% { filter: drop-shadow(0 0 0 rgba(255,255,255,0)); }
                    50% { filter: drop-shadow(0 0 18px rgba(255,255,255,0.25)); }
                }

                .<?php echo esc_html($wrapper_class); ?> h1.display-3 {
                    font-weight: 800;
                    letter-spacing: 1px;
                    opacity: 0;
                    animation: fadeUp 0.6s ease forwards 0.2s;
                }

                .<?php echo esc_html($wrapper_class); ?> .hero-subtitle {
                    opacity: 0;
                    font-size: 1.25rem;
                    margin-top: 1.1rem;
                    margin-bottom: 2rem;
                    animation: fadeUp 0.8s ease forwards 0.45s;
                }

                @keyframes fadeUp {
                    from { opacity: 0; transform: translateY(18px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                .<?php echo esc_html($wrapper_class); ?> .nav-buttons {
                    display: flex;
                    justify-content: center;
                    flex-wrap: wrap;
                    gap: 14px;
                    padding-top: 10px;
                    animation: fadeIn .8s ease forwards .2s;
                    opacity: 0;
                }

                @keyframes fadeIn { to { opacity: 1; } }

                .<?php echo esc_html($wrapper_class); ?> .nav-btn {
                    background: var(--global-palette9, #ffffff);
                    color: var(--global-palette1, #13294b);
                    border: none;
                    padding: 14px 30px;
                    border-radius: 6px;
                    font-weight: 700;
                    text-decoration: none;
                    transition: 0.3s ease;
                    box-shadow: 0 3px 12px rgba(0,0,0,0.15);
                }

                .<?php echo esc_html($wrapper_class); ?> .nav-btn:hover {
                    background: var(--global-palette3, #0f1a2d);
                    color: var(--global-palette9, #ffffff);
                    transform: translateY(-3px);
                }

                .<?php echo esc_html($wrapper_class); ?> .program-category {
                    padding: 80px 0;
                    background: var(--global-palette9, #ffffff);
                }

                .<?php echo esc_html($wrapper_class); ?> .section-title {
                    color: var(--global-palette1, #13294b);
                    text-align: center;
                    font-weight: 800;
                    font-size: 2.2rem;
                    margin-bottom: 1rem;
                }

                .<?php echo esc_html($wrapper_class); ?> .category-description {
                    text-align: center;
                    max-width: var(--flacso-section-max-width);
                    margin: 0 auto 50px;
                    color: var(--global-palette5, #6b7280);
                    font-size: 1.05rem;
                    line-height: 1.7;
                }

                .<?php echo esc_html($wrapper_class); ?> .flacso-grid {
                    display: grid;
                    grid-template-columns: 1fr;
                    gap: 1.5rem;
                }

                @media (min-width: 576px) {
                    .oferta-cards-grid {
                        grid-template-columns: repeat(2, 1fr);
                    }
                }

                @media (min-width: 1024px) {
                    .oferta-cards-grid {
                        grid-template-columns: repeat(3, 1fr);
                    }
                }

                .<?php echo esc_html($wrapper_class); ?> .flacso-card {
                    display: flex;
                    flex-direction: column;
                    border-radius: 12px;
                    overflow: hidden;
                    background: var(--global-palette9, #ffffff);
                    border: 1px solid #f0f1f3;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
                    text-decoration: none;
                    color: inherit;
                    transition: all 0.3s ease;
                    cursor: pointer;
                    touch-action: manipulation;
                }

                .<?php echo esc_html($wrapper_class); ?> .flacso-card:hover {
                    transform: translateY(-4px);
                    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
                }

                .<?php echo esc_html($wrapper_class); ?> .flacso-card__img {
                    width: 100%;
                    height: 200px;
                    background-size: contain;
                    background-position: center;
                    background-repeat: no-repeat;
                    position: relative;
                    background-color: #f8f9fa;
                }

                .<?php echo esc_html($wrapper_class); ?> .flacso-card__img::after {
                    content: "";
                    position: absolute;
                    inset: 0;
                    background: linear-gradient(180deg, rgba(0,0,0,0) 45%, rgba(0,0,0,.2) 100%);
                }

                .<?php echo esc_html($wrapper_class); ?> .flacso-card__title {
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    color: #fff;
                    font-family: var(--global-heading-font-family, "Helvetica Neue", sans-serif);
                    font-size: 1.1rem;
                    font-weight: 700;
                    line-height: 1.3;
                    z-index: 3;
                    padding: 12px;
                    text-shadow: 0 2px 6px rgba(0,0,0,.3);
                }

                .<?php echo esc_html($wrapper_class); ?> .flacso-card__content {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                    padding: 20px;
                    background: var(--global-palette9, #ffffff);
                    flex-grow: 1;
                }

                .<?php echo esc_html($wrapper_class); ?> .flacso-badges {
                    display: flex;
                    gap: 8px;
                    flex-wrap: wrap;
                }

                .<?php echo esc_html($wrapper_class); ?> .flacso-badge {
                    background: var(--global-palette2, #f7b733);
                    color: var(--global-palette3, #0f1a2d);
                    font-size: 0.75rem;
                    padding: 6px 12px;
                    border-radius: 20px;
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    font-weight: 600;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                }

                .<?php echo esc_html($wrapper_class); ?> .flacso-badge.nuevo {
                    background: var(--global-palette2, #f7b733);
                    color: var(--global-palette3, #0f1a2d);
                }

                .<?php echo esc_html($wrapper_class); ?> .flacso-badge.estado-amarillo {
                    background: var(--global-palette2, #f7b733);
                    color: var(--global-palette3, #0f1a2d);
                }

                .<?php echo esc_html($wrapper_class); ?> .flacso-badge.estado-verde {
                    background: var(--global-palette-btn-bg, var(--global-palette1, #13294b));
                    color: var(--global-palette9, #ffffff);
                }

                .<?php echo esc_html($wrapper_class); ?> .flacso-meta {
                    font-size: .82rem;
                    color: var(--global-palette5, #6b7280);
                }

                .<?php echo esc_html($wrapper_class); ?> .flacso-meta i {
                    margin-right: .25rem;
                }

                .<?php echo esc_html($wrapper_class); ?> .flacso-card.inactivo {
                    display: none !important;
                }

                @media (max-width: 768px) {
                    .<?php echo esc_html($wrapper_class); ?> .oferta-academica-hero {
                        padding: 90px 0 80px;
                    }

                    .<?php echo esc_html($wrapper_class); ?> .nav-btn {
                        width: 100%;
                        max-width: 260px;
                        text-align: center;
                    }
                }
            </style>

            <section class="oferta-academica-hero">
                <div class="container">
                    <h1 class="display-3"><?php esc_html_e('Oferta Académica', 'flacso-main-page'); ?></h1>
                    <p class="hero-subtitle"><?php esc_html_e('Formación con impacto para transformar la región', 'flacso-main-page'); ?></p>

                    <?php if ($mostrar_filtros) : ?>
                        <div class="nav-buttons">
                            <?php if (empty($oa) || !empty($oa['show_maestrias'])) : ?><a href="#maestrias" class="nav-btn"><?php esc_html_e('Maestrías', 'flacso-main-page'); ?></a><?php endif; ?>
                            <?php if (empty($oa) || !empty($oa['show_especializaciones'])) : ?><a href="#especializaciones" class="nav-btn"><?php esc_html_e('Especializaciones', 'flacso-main-page'); ?></a><?php endif; ?>
                            <?php if (empty($oa) || !empty($oa['show_diplomados'])) : ?><a href="#diplomados" class="nav-btn"><?php esc_html_e('Diplomados', 'flacso-main-page'); ?></a><?php endif; ?>
                            <?php if (empty($oa) || !empty($oa['show_diplomas'])) : ?><a href="#diplomas" class="nav-btn"><?php esc_html_e('Diplomas', 'flacso-main-page'); ?></a><?php endif; ?>
                            <?php if (empty($oa) || !empty($oa['show_seminarios'])) : ?><a href="#seminarios" class="nav-btn"><?php esc_html_e('Seminarios', 'flacso-main-page'); ?></a><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="hero-ambient" aria-hidden="true">
                    <span></span><span></span><span></span>
                </div>
            </section>

            <main class="container">
                <?php
                $categorias = [
                    'maestrias' => [
                        'titulo'      => __('Maestrías', 'flacso-main-page'),
                        'descripcion' => __('Una maestría es una oportunidad de crecimiento profesional y académico.', 'flacso-main-page'),
                        'padre'       => 'Maestrías',
                    ],
                    'especializaciones' => [
                        'titulo'      => __('Especializaciones', 'flacso-main-page'),
                        'descripcion' => __('La especialización es una salida intermedia hacia la maestría.', 'flacso-main-page'),
                        'padre'       => 'Especializaciones',
                    ],
                    'diplomados' => [
                        'titulo'      => __('Diplomados', 'flacso-main-page'),
                        'descripcion' => __('Formación especializada en temáticas de alta relevancia.', 'flacso-main-page'),
                        'padre'       => 'Diplomados',
                    ],
                    'diplomas' => [
                        'titulo'      => __('Diplomas', 'flacso-main-page'),
                        'descripcion' => __('Programas enfocados en áreas específicas de conocimiento.', 'flacso-main-page'),
                        'padre'       => 'Diplomas',
                    ],
                ];

                foreach ($categorias as $slug => $categoria) {
                    // Chequeo de ajuste admin para ocultar categoría
                    $map = [
                        'maestrias' => 'show_maestrias',
                        'especializaciones' => 'show_especializaciones',
                        'diplomados' => 'show_diplomados',
                        'diplomas' => 'show_diplomas',
                    ];
                    if (!empty($map[$slug]) && isset($oa[$map[$slug]]) && empty($oa[$map[$slug]])) {
                        continue;
                    }
                    echo flacso_render_categoria_cards_unificada($slug, $categoria, $vigentes, $mostrar_inactivos);
                }

                if ($mostrar_seminarios) {
                    echo flacso_render_seminarios_unificados($seminarios_limit);
                }
                ?>
            </main>
        </div>
        <?php

        return ob_get_clean();
    }

    add_shortcode('oferta_academica', 'flacso_oferta_academica_shortcode');
}

// Fallback: asegura que el bloque exista en Gutenberg aunque falle el registro dinжmico.
add_action('init', static function () {
    if (!function_exists('register_block_type')) {
        return;
    }

    if (class_exists('WP_Block_Type_Registry')) {
        $registry = WP_Block_Type_Registry::get_instance();
        if ($registry->is_registered('flacso-uruguay/oferta-academica')) {
            return;
        }
    }

    if (class_exists('Flacso_Main_Page_Blocks')) {
        Flacso_Main_Page_Blocks::register_editor_assets();
    }

    register_block_type('flacso-uruguay/oferta-academica', [
        'api_version'    => 2,
        'title'          => __('Oferta academica', 'flacso-main-page'),
        'description'    => __('Hero y cards unificadas de la oferta academica', 'flacso-main-page'),
        'category'       => 'flacso-uruguay',
        'icon'           => 'portfolio',
        'attributes'     => [
            'mostrar_filtros'   => ['type' => 'boolean', 'default' => true],
            'mostrar_inactivos' => ['type' => 'boolean', 'default' => false],
        ],
        'supports'       => [
            'html'     => false,
            'align'    => ['full', 'wide'],
            'inserter' => true,
        ],
        'editor_script'  => 'flacso-shortcode-blocks',
        'render_callback' => static function ($attributes = []) {
            return flacso_oferta_academica_shortcode($attributes);
        },
    ]);
}, 20);

if (!function_exists('flacso_render_categoria_cards_unificada')) {
    /**
     * Renderiza una sección de categoría de posgrados.
     */
    function flacso_render_categoria_cards_unificada(string $slug, array $categoria, array $vigentes, bool $mostrar_inactivos): string {
        $parent = get_page_by_title($categoria['padre']);
        if (!$parent) {
            return '';
        }

        $query = new WP_Query([
            'post_type'      => 'page',
            'post_parent'    => $parent->ID,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'posts_per_page' => -1,
        ]);

        ob_start();
        ?>
        <section class="program-category" id="<?php echo esc_attr($slug); ?>">
            <h2 class="section-title"><?php echo esc_html($categoria['titulo']); ?></h2>
            <p class="category-description"><?php echo esc_html($categoria['descripcion']); ?></p>

            <?php if ($query->have_posts()) : ?>
                <div class="flacso-grid">
                    <?php
                    while ($query->have_posts()) :
                        $query->the_post();
                        $id = get_the_ID();
                        $titulo = get_the_title();
                        $thumb = get_the_post_thumbnail_url($id, 'large') ?: 'https://via.placeholder.com/900?text=FLACSO';
                        $es_vigente = array_key_exists($id, $vigentes);

                        if (!$es_vigente && !$mostrar_inactivos) {
                            continue;
                        }

                        $inicio = $es_vigente ? ($vigentes[$id][2] ?? '') : '';

                        if (!$inicio) {
                            $start_keys = ['fecha_inicio', 'periodo_inicio', '_seminario_fecha_inicio', '_seminario_periodo_inicio'];
                            foreach ($start_keys as $meta_key) {
                                $value = get_post_meta($id, $meta_key, true);
                                if (!empty($value)) {
                                    $inicio = $value;
                                    break;
                                }
                            }
                        }

                        $fecha_inicio_legible = '';
                        if (!empty($inicio)) {
                            $inicio_ts = strtotime($inicio);
                            if ($inicio_ts) {
                                $fecha_inicio_legible = date_i18n('d/m/Y', $inicio_ts);
                            }
                        }
                        $creado_u = get_post_time('U', true, $id);
                        $es_nuevo = (current_time('timestamp') - $creado_u) < (30 * DAY_IN_SECONDS);
                        $abbr = $es_vigente ? ($vigentes[$id][0] ?? '') : '';
                        $tipo = $es_vigente ? ($vigentes[$id][1] ?? '') : '';
                        ?>
                        <a class="flacso-card <?php echo $es_vigente ? '' : 'inactivo'; ?>" href="<?php the_permalink(); ?>">
                            <div class="flacso-card__img" style="background-image:url('<?php echo esc_url($thumb); ?>');">
                                <div class="flacso-card__title"><?php echo esc_html($titulo); ?></div>
                            </div>
                            <div class="flacso-card__content">
                                <div class="flacso-badges">
                                    <?php if ($es_vigente) : ?>
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
                                <?php if ($fecha_inicio_legible) : ?>
                                    <div class="flacso-meta">
                                        <i class="bi bi-clock"></i> <?php echo esc_html($fecha_inicio_legible); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
                <?php
                wp_reset_postdata();
            endif;
            ?>
        </section>
        <?php

        return ob_get_clean();
    }
}

if (!function_exists('flacso_oferta_tiene_seminarios_disponibles')) {
    /**
     * Chequea si hay seminarios disponibles segカn la misma lケgica de filtrado usada en la secciИn.
     */
    function flacso_oferta_tiene_seminarios_disponibles(int $limit = 1): bool {
        $hace_diez = date('Y-m-d', strtotime('-10 days', current_time('timestamp')));
        $post_type = post_type_exists('seminario') ? 'seminario' : 'post';

        $start_keys = class_exists('Flacso_Main_Page_Seminarios')
            ? Flacso_Main_Page_Seminarios::get_meta_keys_for('periodo_inicio')
            : ['fecha_inicio'];

        $meta_query = ['relation' => 'OR'];
        foreach ($start_keys as $key) {
            $meta_query[] = [
                'key'     => $key,
                'value'   => $hace_diez,
                'compare' => '>=',
                'type'    => 'DATE',
            ];
        }

        $query_args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => max(1, $limit),
            'meta_query'     => count($meta_query) > 1 ? $meta_query : [],
            'orderby'        => 'date',
            'order'          => 'ASC',
        ];

        if ('post' === $post_type) {
            $query_args['category_name'] = 'seminarios';
            $query_args['meta_key'] = 'fecha_inicio';
            $query_args['orderby'] = 'meta_value';
        }

        $query = new WP_Query($query_args);

        $has = $query->have_posts();
        wp_reset_postdata();
        return $has;
    }
}

if (!function_exists('flacso_render_seminarios_unificados')) {
    /**
     * Renderiza la sección de seminarios con la misma tarjeta.
     */
    function flacso_render_seminarios_unificados(int $limit = 10): string {
        $today = current_time('Y-m-d');
        $hace_diez = date('Y-m-d', strtotime('-10 days', current_time('timestamp')));

        $post_type = post_type_exists('seminario') ? 'seminario' : 'post';
        $start_keys = class_exists('Flacso_Main_Page_Seminarios')
            ? Flacso_Main_Page_Seminarios::get_meta_keys_for('periodo_inicio')
            : ['fecha_inicio'];

        $meta_query = ['relation' => 'OR'];
        foreach ($start_keys as $key) {
            $meta_query[] = [
                'key'     => $key,
                'value'   => $hace_diez,
                'compare' => '>=',
                'type'    => 'DATE',
            ];
        }

        $query_args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => max(1, $limit),
            'meta_query'     => count($meta_query) > 1 ? $meta_query : [],
            'orderby'        => 'date',
            'order'          => 'ASC',
        ];

        if ('post' === $post_type) {
            $query_args['category_name'] = 'seminarios';
            $query_args['meta_key'] = 'fecha_inicio';
            $query_args['orderby'] = 'meta_value';
            $query_args['meta_type'] = 'DATE';
        }

        $query = new WP_Query($query_args);

        ob_start();
        ?>
        <section class="program-category" id="seminarios">
            <h2 class="section-title"><?php esc_html_e('Seminarios', 'flacso-main-page'); ?></h2>
            <p class="category-description"><?php esc_html_e('Espacios de formación intensiva y enfoque práctico.', 'flacso-main-page'); ?></p>

            <?php if ($query->have_posts()) : ?>
                <div class="flacso-grid">
                    <?php
                    while ($query->have_posts()) :
                        $query->the_post();
                        $img = get_the_post_thumbnail_url(get_the_ID(), 'large') ?: 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?auto=format&fit=crop&w=1350&q=80';
                        $inicio = class_exists('Flacso_Main_Page_Seminarios')
                            ? Flacso_Main_Page_Seminarios::get_start_date(get_the_ID())
                            : get_post_meta(get_the_ID(), 'fecha_inicio', true);
                        if (!$inicio) {
                            continue;
                        }

                        $inicio_ts = strtotime($inicio);
                        if (!$inicio_ts) {
                            continue;
                        }

                        $diff = ($inicio_ts - strtotime($today)) / DAY_IN_SECONDS;
                        if ($diff <= -10) {
                            continue;
                        }

                        if ($diff > 0) {
                            $estado_texto = sprintf(
                                /* translators: %s: días para que comience el seminario */
                                __('Empieza en %s días', 'flacso-main-page'),
                                intval($diff)
                            );
                            $badge_class = 'estado-amarillo';
                            $icon = 'bi-hourglass-split';
                        } else {
                            $estado_texto = sprintf(
                                /* translators: %s: días desde el inicio */
                                __('Iniciado hace %s días', 'flacso-main-page'),
                                abs(intval($diff))
                            );
                            $badge_class = 'estado-verde';
                            $icon = 'bi-play-circle';
                        }

                        $fecha = date_i18n('d/m/Y', $inicio_ts);
                        $hora_meta_keys = ['hora_inicio', '_seminario_hora_inicio', '_hora_inicio'];
                        $hora = '';
                        foreach ($hora_meta_keys as $hkey) {
                            $val = get_post_meta(get_the_ID(), $hkey, true);
                            if (!empty($val)) {
                                $hora = $val;
                                break;
                            }
                        }
                        ?>
                        <a class="flacso-card" href="<?php the_permalink(); ?>">
                            <div class="flacso-card__img" style="background-image:url('<?php echo esc_url($img); ?>');">
                                <div class="flacso-card__title"><?php the_title(); ?></div>
                            </div>
                            <div class="flacso-card__content">
                                <div class="flacso-badges">
                                    <span class="flacso-badge <?php echo esc_attr($badge_class); ?>">
                                        <i class="bi <?php echo esc_attr($icon); ?>"></i>
                                        <?php echo esc_html($estado_texto); ?>
                                    </span>
                                </div>
                                <div class="flacso-meta">
                                    <i class="bi bi-clock"></i>
                                    <?php echo esc_html($hora ? sprintf('%s · %s h', $fecha, $hora) : $fecha); ?>
                                </div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
                <div class="flacso-section-cta" style="text-align: center; margin-top: 3rem;">
                    <a href="https://flacso.edu.uy/formacion/seminarios/" class="button button-primary" style="background-color: var(--global-palette1, #1d3a72); color: #fff; padding: 0.75rem 2rem; border-radius: 8px; text-decoration: none; display: inline-block; font-weight: 600; border: none; cursor: pointer;">
                        <?php esc_html_e('Ver todos los seminarios', 'flacso-main-page'); ?>
                    </a>
                </div>
                <?php
                wp_reset_postdata();
            endif;
            ?>
        </section>
        <?php

        return ob_get_clean();
    }
}

