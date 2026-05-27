<?php
/**
 * Template para el archivo de Taxonomía: Tipo de Oferta Académica.
 * Replicando fielmente el diseño de flacso.edu.uy
 */

if (!defined('ABSPATH')) {
    exit;
}

$term = get_queried_object();
$taxonomy_name = $term->name;
if ($term->slug === 'maestria') {
    $taxonomy_name = 'Maestrías';
} elseif ($term->slug === 'especializacion') {
    $taxonomy_name = 'Especializaciones';
} elseif ($term->slug === 'diplomado') {
    $taxonomy_name = 'Diplomados';
} elseif ($term->slug === 'diploma') {
    $taxonomy_name = 'Diplomas';
}

// Cargar estilos de la oferta
if (class_exists('Oferta_Renderer')) {
    Oferta_Renderer::enqueue_styles();
}

get_header();
?>

<div id="inner-wrap" class="wrap kt-clear flacso-oferta-academica-premium">
    <!-- Hero Section -->
    <section class="entry-hero page-hero-section entry-hero-layout-standard">
        <div class="entry-hero-container-inner">
            <div class="hero-section-overlay"></div>
            <div class="hero-container site-container">
                <header class="entry-header page-title title-align-center">
                    <h1 class="entry-title"><?php echo esc_html($taxonomy_name); ?></h1>
                    <nav id="kadence-breadcrumbs" aria-label="Migas de pan" class="kadence-breadcrumbs">
                        <div class="kadence-breadcrumb-container">
                            <span><a href="<?php echo esc_url(home_url()); ?>">Inicio</a></span> 
                            <span class="bc-delimiter">/</span> 
                            <span><a href="<?php echo esc_url(home_url('/formacion/')); ?>">Formación</a></span> 
                            <span class="bc-delimiter">/</span> 
                            <span class="kadence-bread-current"><?php echo esc_html($taxonomy_name); ?></span>
                        </div>
                    </nav>
                </header>
            </div>
        </div>
    </section>

    <div id="primary" class="content-area">
        <div class="content-container site-container" style="padding-top: 40px; padding-bottom: 60px;">
            <div class="row g-4 justify-content-center">
                <?php if (have_posts()) : ?>
                    <?php while (have_posts()) : the_post(); 
                        $post_id = get_the_ID();
                        $thumbnail_url = get_the_post_thumbnail_url($post_id, 'medium');
                        $inscripciones_abiertas = get_post_meta($post_id, 'inscripciones_abiertas', true);
                        $is_open = ($inscripciones_abiertas === '1' || $inscripciones_abiertas === 'true' || $inscripciones_abiertas === true || $inscripciones_abiertas === 1);
                    ?>
                        <div class="col-md-6 col-lg-4 d-flex align-items-stretch">
                            <article class="card h-100 border-0 shadow-sm w-100 flacso-oferta-card" style="border-radius: 12px; overflow: hidden; transition: transform 0.3s ease;">
                                <div class="position-relative">
                                    <?php if ($thumbnail_url) : ?>
                                        <img src="<?php echo esc_url($thumbnail_url); ?>" class="card-img-top" alt="<?php the_title_attribute(); ?>" style="height: 220px; object-fit: cover;">
                                    <?php else : ?>
                                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 220px;">
                                            <i class="bi bi-mortarboard text-muted" style="font-size: 4rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="position-absolute top-0 end-0 m-3">
                                        <?php if ($is_open) : ?>
                                            <span class="badge" style="background-color: var(--flacso-yellow); color: var(--flacso-blue-dark); padding: 8px 12px; font-weight: 700; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">Inscripciones Abiertas</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body d-flex flex-column p-4">
                                    <h3 class="card-title h5 fw-bold mb-3" style="color: var(--flacso-blue-dark); line-height: 1.4;">
                                        <a href="<?php the_permalink(); ?>" class="text-decoration-none text-reset stretched-link">
                                            <?php the_title(); ?>
                                        </a>
                                    </h3>
                                    <div class="card-text text-muted small mb-4 flex-grow-1">
                                        <?php echo wp_trim_words(get_the_excerpt(), 20, '...'); ?>
                                    </div>
                                    <div class="mt-auto">
                                        <span class="text-primary fw-bold" style="color: var(--flacso-blue-light) !important;">Ver detalles <i class="bi bi-arrow-right ms-1"></i></span>
                                    </div>
                                </div>
                            </article>
                        </div>
                    <?php endwhile; ?>
                <?php else : ?>
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-info-circle text-muted mb-3 d-block" style="font-size: 3rem;"></i>
                        <h3 class="fw-bold" style="color: var(--flacso-blue-dark);">Próximamente</h3>
                        <p class="text-muted">En este momento no hay propuestas académicas listadas en esta categoría. Te invitamos a estar atento/a a nuestras próximas aperturas.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-5 text-center">
                <?php
                the_posts_pagination(array(
                    'mid_size'  => 2,
                    'prev_text' => '&laquo; Anterior',
                    'next_text' => 'Siguiente &raquo;',
                    'screen_reader_text' => 'Navegación de ofertas'
                ));
                ?>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --flacso-blue-dark: #051938;
    --flacso-blue-light: #163970;
    --flacso-yellow: #fcd116;
    --flacso-gray-bg: #f8fafc;
}

.flacso-oferta-academica-premium .entry-hero {
    background: var(--flacso-blue-dark);
    padding: 80px 0;
    text-align: center;
    color: white;
    position: relative;
    overflow: hidden;
}
.flacso-oferta-academica-premium .hero-section-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(5, 25, 56, 0.9) 0%, rgba(22, 57, 112, 0.5) 100%);
    z-index: 1;
}
.flacso-oferta-academica-premium .hero-container {
    position: relative;
    z-index: 2;
}
.flacso-oferta-academica-premium .entry-title {
    font-size: clamp(2.5rem, 5vw, 4rem);
    font-weight: 800;
    margin-bottom: 20px;
    line-height: 1.1;
}
.flacso-oferta-academica-premium .kadence-breadcrumbs {
    font-size: 0.9rem;
    opacity: 0.8;
}
.flacso-oferta-academica-premium .kadence-breadcrumbs a {
    color: white;
    text-decoration: none;
}
.flacso-oferta-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}
.pagination {
    display: inline-flex;
    gap: 5px;
}
.pagination .page-numbers {
    display: inline-block;
    padding: 8px 16px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    color: var(--flacso-blue-dark);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s;
}
.pagination .page-numbers:hover,
.pagination .page-numbers.current {
    background: var(--flacso-blue-light);
    color: white;
    border-color: var(--flacso-blue-light);
}
</style>

<?php get_footer(); ?>
