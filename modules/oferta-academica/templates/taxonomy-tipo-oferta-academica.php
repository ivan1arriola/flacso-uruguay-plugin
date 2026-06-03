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
    <div id="primary" class="content-area">
        <div class="content-container site-container" style="padding-top: 60px; padding-bottom: 60px;">
            
            <header class="entry-header page-title" style="margin-bottom: 40px;">
                <h1 class="entry-title" style="color: var(--flacso-blue-dark); font-weight: 800; font-size: clamp(2.5rem, 5vw, 3.5rem); margin-bottom: 1rem;"><?php echo esc_html($taxonomy_name); ?></h1>
                <?php 
                $term_desc = term_description();
                if (!empty($term_desc)) : ?>
                    <div class="taxonomy-description" style="font-size: 1.15rem; line-height: 1.6; max-width: 900px; color: #475569;">
                        <?php echo wp_kses_post($term_desc); ?>
                    </div>
                <?php endif; ?>
            </header>

            <div class="flacso-ofertas-grid">
                <?php if (have_posts()) : ?>
                    <?php while (have_posts()) : the_post(); 
                        $post_id = get_the_ID();
                        $thumbnail_url = get_the_post_thumbnail_url($post_id, 'medium_large');
                        $inscripciones_abiertas = get_post_meta($post_id, 'inscripciones_abiertas', true);
                        $is_open = ($inscripciones_abiertas === '1' || $inscripciones_abiertas === 'true' || $inscripciones_abiertas === true || $inscripciones_abiertas === 1);
                        
                        $data = class_exists('Oferta_Data_Schema') ? Oferta_Data_Schema::get_schema($post_id) : [];
                        $duracion = !empty($data['duracion_meses']) ? $data['duracion_meses'] . ' meses' : '';
                    ?>
                        <div class="grid-item-wrap">
                            <article class="flacso-premium-card h-100 w-100">
                                <div class="flacso-premium-card__image-wrap">
                                    <?php if ($thumbnail_url) : ?>
                                        <img src="<?php echo esc_url($thumbnail_url); ?>" class="flacso-premium-card__img" alt="<?php the_title_attribute(); ?>">
                                    <?php else : ?>
                                        <div class="flacso-premium-card__img-placeholder">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-mortarboard" viewBox="0 0 16 16">
                                                <path d="M8.211 2.047a.5.5 0 0 0-.422 0l-7.5 3.5a.5.5 0 0 0 .025.917l7.5 3a.5.5 0 0 0 .372 0L14 7.14V13a1 1 0 0 0-1 1v2h3v-2a2 2 0 0 0-1-1V6.739l.686-.275a.5.5 0 0 0 .025-.917l-7.5-3.5ZM8 8.46 1.758 5.965 8 3.052l6.242 2.913L8 8.46Z"/>
                                                <path d="M4.176 9.032a.5.5 0 0 0-.656.327l-.5 1.7a.5.5 0 0 0 .294.605l4.5 1.8a.5.5 0 0 0 .372 0l4.5-1.8a.5.5 0 0 0 .294-.605l-.5-1.7a.5.5 0 0 0-.656-.327L8 10.466 4.176 9.032Zm-.068 1.873.22-.748 3.496 1.311a.5.5 0 0 0 .352 0l3.496-1.311.22.748L8 12.46l-3.892-1.555Z"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="flacso-premium-card__badges">
                                        <?php if ($is_open) : ?>
                                            <span class="flacso-badge flacso-badge--open">Inscripciones Abiertas</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flacso-premium-card__body">
                                    <div class="flacso-premium-card__meta">
                                        <?php if ($duracion) : ?>
                                            <span class="flacso-meta-item">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/></svg>
                                                <?php echo esc_html($duracion); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="flacso-premium-card__title">
                                        <a href="<?php the_permalink(); ?>" class="stretched-link">
                                            <?php the_title(); ?>
                                        </a>
                                    </h3>
                                    <div class="flacso-premium-card__excerpt">
                                        <?php echo wp_trim_words(get_the_excerpt(), 18, '...'); ?>
                                    </div>
                                    <div class="flacso-premium-card__footer">
                                        <span class="flacso-premium-card__cta">Ver detalles <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/></svg></span>
                                    </div>
                                </div>
                            </article>
                        </div>
                    <?php endwhile; ?>
                <?php else : ?>
                    <div class="col-12 text-center py-5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="text-muted mb-3 d-block mx-auto" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/><path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/></svg>
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

.flacso-ofertas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr));
    gap: 2rem;
    padding-bottom: 2rem;
}

.flacso-ofertas-grid .grid-item-wrap {
    width: 100%;
}

/* Premium Card Styles */
.flacso-premium-card {
    background: #ffffff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    border: 1px solid rgba(0, 0, 0, 0.04);
    display: flex;
    flex-direction: column;
    transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
    position: relative;
    transform: translateY(0);
}

.flacso-premium-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 35px rgba(5, 25, 56, 0.12);
}

.flacso-premium-card__image-wrap {
    position: relative;
    height: 220px;
    overflow: hidden;
    background: #f1f5f9;
}

.flacso-premium-card__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
}

.flacso-premium-card:hover .flacso-premium-card__img {
    transform: scale(1.05);
}

.flacso-premium-card__img-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #cbd5e1;
}

.flacso-premium-card__badges {
    position: absolute;
    top: 16px;
    right: 16px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.flacso-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}

.flacso-badge--open {
    background: var(--flacso-yellow);
    color: var(--flacso-blue-dark);
}

.flacso-premium-card__body {
    padding: 24px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}

.flacso-premium-card__meta {
    display: flex;
    gap: 16px;
    margin-bottom: 12px;
    font-size: 0.8rem;
    color: #64748b;
    font-weight: 600;
}

.flacso-meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.flacso-meta-item svg {
    color: var(--flacso-blue-light);
}

.flacso-premium-card__title {
    font-size: 1.25rem;
    font-weight: 800;
    line-height: 1.35;
    margin-bottom: 12px;
    color: var(--flacso-blue-dark);
}

.flacso-premium-card__title a {
    color: inherit;
    text-decoration: none;
    transition: color 0.2s ease;
}

.flacso-premium-card:hover .flacso-premium-card__title a {
    color: var(--flacso-blue-light);
}

.flacso-premium-card__excerpt {
    font-size: 0.9rem;
    line-height: 1.6;
    color: #475569;
    margin-bottom: 24px;
    flex-grow: 1;
}

.flacso-premium-card__footer {
    margin-top: auto;
    padding-top: 16px;
    border-top: 1px solid #f1f5f9;
}

.flacso-premium-card__cta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--flacso-blue-light);
    transition: gap 0.3s ease;
}

.flacso-premium-card:hover .flacso-premium-card__cta {
    gap: 12px;
    color: var(--flacso-yellow);
}

.flacso-premium-card:hover .flacso-premium-card__cta svg {
    fill: var(--flacso-yellow);
}

/* Pagination */
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
