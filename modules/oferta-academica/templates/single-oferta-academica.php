<?php
/**
 * Template para la página individual de una Oferta Académica.
 * Diseño Premium exacto según requerimiento del usuario.
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = get_the_ID();
$data = Oferta_Data_Schema::get_schema($post_id);
$thumbnail_url = get_the_post_thumbnail_url($post_id, 'full');

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
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                    <?php if (function_exists('kadence_breadcrumbs')) : ?>
                        <?php kadence_breadcrumbs(); ?>
                    <?php else : ?>
                        <nav id="kadence-breadcrumbs" aria-label="Migas de pan" class="kadence-breadcrumbs">
                            <div class="kadence-breadcrumb-container">
                                <span><a href="<?php echo home_url(); ?>">Inicio</a></span> 
                                <span class="bc-delimiter">/</span> 
                                <span><a href="<?php echo home_url('/formacion/'); ?>">Oferta Académica</a></span> 
                                <span class="bc-delimiter">/</span> 
                                <span class="kadence-bread-current"><?php the_title(); ?></span>
                            </div>
                        </nav>
                    <?php endif; ?>
                </header>
            </div>
        </div>
    </section>

    <div id="primary" class="content-area">
        <div class="content-container site-container">
            <div id="main" class="site-main">
                <div class="content-wrap">
                    <article id="post-<?php echo $post_id; ?>" class="entry content-bg single-entry">
                        <div class="entry-content-wrap">
                            <div class="entry-content single-content">
                                
                                <!-- wp:kadence/column -->
                                <div class="wp-block-kadence-column mb-5">
                                    <div class="kt-inside-inner-col">
                                        <div class="flacso-inscripciones-banner">
                                            <?php if ($thumbnail_url) : ?>
                                                <img decoding="async" class="flacso-inscripciones-banner__img" src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php the_title_attribute(); ?>">
                                            <?php endif; ?>
                                            <div class="flacso-inscripciones-banner__overlay">
                                                <div class="flacso-inscripciones-banner__top">
                                                    <div class="flacso-inscripciones-banner__tag">
                                                        <?php echo !empty($data['inscripciones_abiertas']) ? __('Inscripciones 2026', 'flacso-uruguay') : __('Próximamente', 'flacso-uruguay'); ?>
                                                    </div>
                                                    <img decoding="async" src="https://flacso.edu.uy/wp-content/uploads/2024/10/384ddefb-522d-432a-bbc8-c86f09bdceef.png" alt="FLACSO Uruguay" class="flacso-inscripciones-banner__logo">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- wp:kadence/rowlayout {"colLayout":"left-golden"} -->
                                <div class="kb-row-layout-wrap wp-block-kadence-rowlayout mb-5">
                                    <div class="kt-row-column-wrap kt-has-2-columns kt-row-layout-left-golden kt-mobile-layout-row">
                                        
                                        <!-- Columna 1: Info Principal -->
                                        <div class="wp-block-kadence-column inner-column-1">
                                            <div class="kt-inside-inner-col">
                                                <?php if (!empty($data['duracion_meses'])) : ?>
                                                    <h2 class="wp-block-heading"><?php printf(__('Duración: %s meses', 'flacso-uruguay'), $data['duracion_meses']); ?></h2>
                                                <?php endif; ?>

                                                <div class="entry-content-text">
                                                    <?php the_content(); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Columna 2: Formulario -->
                                        <div class="wp-block-kadence-column inner-column-2">
                                            <div class="kt-inside-inner-col">
                                                <div class="flacso-consultas-formulario shadow-sm mx-auto">
                                                    <?php 
                                                    if (class_exists('Oferta_Consulta_Form')) {
                                                        echo Oferta_Consulta_Form::render_shortcode(['id' => $post_id]);
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- wp:flacso-uruguay/dato-proximo-inicio -->
                                <?php if (class_exists('Oferta_Blocks')) : ?>
                                    <div class="mb-5">
                                        <?php echo Oferta_Blocks::render_dato_proximo_inicio(['ofertaId' => $post_id]); ?>
                                    </div>
                                <?php endif; ?>

                                <!-- wp:kadence/rowlayout (Acordeones) -->
                                <div class="kb-row-layout-wrap wp-block-kadence-rowlayout mb-5" style="padding: 80px 0;">
                                    <div class="kt-row-column-wrap kt-has-1-columns">
                                        <div class="wp-block-kadence-column inner-column-1">
                                            <div class="kt-inside-inner-col">
                                                <div class="wp-block-kadence-accordion alignnone">
                                                    <div class="kt-accordion-wrap kt-accordion-block kt-accodion-icon-style-basic kt-accodion-icon-side-right">
                                                        <div class="kt-accordion-inner-wrap">
                                                            
                                                            <!-- MODALIDAD -->
                                                            <?php if (!empty($data['modalidad_html'])) : ?>
                                                            <div class="wp-block-kadence-pane kt-accordion-pane">
                                                                <div class="kt-accordion-header-wrap">
                                                                    <button class="kt-blocks-accordion-header" type="button">
                                                                        <span class="kt-blocks-accordion-title"><?php _e('MODALIDAD', 'flacso-uruguay'); ?></span>
                                                                        <span class="kt-blocks-accordion-icon-trigger"></span>
                                                                    </button>
                                                                </div>
                                                                <div class="kt-accordion-panel">
                                                                    <div class="kt-accordion-panel-inner">
                                                                        <?php echo $data['modalidad_html']; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php endif; ?>

                                                            <!-- OBJETIVOS -->
                                                            <?php if (!empty($data['objetivos_html'])) : ?>
                                                            <div class="wp-block-kadence-pane kt-accordion-pane">
                                                                <div class="kt-accordion-header-wrap">
                                                                    <button class="kt-blocks-accordion-header" type="button">
                                                                        <span class="kt-blocks-accordion-title"><?php _e('OBJETIVOS', 'flacso-uruguay'); ?></span>
                                                                        <span class="kt-blocks-accordion-icon-trigger"></span>
                                                                    </button>
                                                                </div>
                                                                <div class="kt-accordion-panel">
                                                                    <div class="kt-accordion-panel-inner">
                                                                        <?php echo $data['objetivos_html']; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php endif; ?>

                                                            <!-- MALLA CURRICULAR -->
                                                            <div class="wp-block-kadence-pane kt-accordion-pane">
                                                                <div class="kt-accordion-header-wrap">
                                                                    <button class="kt-blocks-accordion-header" type="button">
                                                                        <span class="kt-blocks-accordion-title"><?php _e('MALLA CURRICULAR', 'flacso-uruguay'); ?></span>
                                                                        <span class="kt-blocks-accordion-icon-trigger"></span>
                                                                    </button>
                                                                </div>
                                                                <div class="kt-accordion-panel">
                                                                    <div class="kt-accordion-panel-inner">
                                                                        <?php if (!empty($data['malla_curricular_html'])) echo $data['malla_curricular_html']; ?>
                                                                        <?php if (class_exists('Oferta_Blocks')) echo Oferta_Blocks::render_dato_malla_curricular(['ofertaId' => $post_id]); ?>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- CALENDARIO -->
                                                            <?php if (!empty($data['calendario']) || class_exists('Oferta_Blocks')) : ?>
                                                            <div class="wp-block-kadence-pane kt-accordion-pane">
                                                                <div class="kt-accordion-header-wrap">
                                                                    <button class="kt-blocks-accordion-header" type="button">
                                                                        <span class="kt-blocks-accordion-title"><?php _e('CALENDARIO', 'flacso-uruguay'); ?></span>
                                                                        <span class="kt-blocks-accordion-icon-trigger"></span>
                                                                    </button>
                                                                </div>
                                                                <div class="kt-accordion-panel">
                                                                    <div class="kt-accordion-panel-inner">
                                                                        <?php if (class_exists('Oferta_Blocks')) echo Oferta_Blocks::render_dato_calendario(['ofertaId' => $post_id]); ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php endif; ?>

                                                            <!-- FINANCIACION -->
                                                            <div class="wp-block-kadence-pane kt-accordion-pane">
                                                                <div class="kt-accordion-header-wrap">
                                                                    <button class="kt-blocks-accordion-header" type="button">
                                                                        <span class="kt-blocks-accordion-title"><?php _e('FINANCIACIÓN Y BECAS', 'flacso-uruguay'); ?></span>
                                                                        <span class="kt-blocks-accordion-icon-trigger"></span>
                                                                    </button>
                                                                </div>
                                                                <div class="kt-accordion-panel">
                                                                    <div class="kt-accordion-panel-inner">
                                                                        <p><?php _e('FLACSO ofrece financiación flexible, el monto de las cuotas puede variar dependiendo de las promociones que haya aprovechado, o el plan de pagos que se coordine con la Institución. Todos los posgrados pueden abonarse en cuotas, siguiendo un plan mensual de pagos que acompañan la cursada. No obstante, es posible extender los planes de pago en forma flexible, con valores de cuota a su alcance.', 'flacso-uruguay'); ?></p>
                                                                        <p><?php _e('Quienes cursen desde fuera de Uruguay pueden pagar de forma segura a través de la plataforma de pago de la institución, mientras las personas que cursan en el país, disponen de otras vías para pagar.', 'flacso-uruguay'); ?></p>
                                                                        <p><?php _e('Las becas para cursar en FLACSO Uruguay están sujetas a convenios inter institucionales y son limitadas por cohorte. Para obtener más información sobre las posibles becas disponibles puede comunicarse con la asistente académica.', 'flacso-uruguay'); ?></p>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- CTA Banner -->
                                <div class="kb-row-layout-wrap wp-block-kadence-rowlayout alignfull" style="background-color: #163970; color: white; padding: 40px 0;">
                                    <div class="site-container">
                                        <p class="text-center m-0"><?php _e('Para realizar las postulaciones puede completar el formulario y en breve el personal de asistencia académica se pondrá en contacto.', 'flacso-uruguay'); ?></p>
                                    </div>
                                </div>

                                <!-- Equipo Académico -->
                                <div class="kb-row-layout-wrap wp-block-kadence-rowlayout alignfull" style="background-color: #f1f5f9; padding: 120px 0 80px 0;">
                                    <div class="site-container">
                                        <h2 class="wp-block-heading text-center mb-5" style="text-transform:uppercase; font-weight: 800;"><?php _e('Equipo Académico', 'flacso-uruguay'); ?></h2>
                                        
                                        <?php if (!empty($data['coordinacion_academica'])) : ?>
                                            <?php foreach ($data['coordinacion_academica'] as $coord) : ?>
                                                <div class="mb-5">
                                                    <h3 class="wp-block-heading text-center mb-4" style="font-size: 1.5rem; text-transform: uppercase; color: #163970;"><?php echo esc_html($coord['rol']); ?></h3>
                                                    <div class="row g-4 justify-content-center">
                                                        <?php foreach ($coord['docentes'] as $docente_id) : 
                                                            $doc_avatar = get_the_post_thumbnail_url($docente_id, 'medium');
                                                            $doc_prefijo = get_post_meta($docente_id, '_docente_prefijo', true);
                                                        ?>
                                                            <div class="col-md-5 col-lg-4">
                                                                <div class="card h-100 border-0 shadow-sm text-center p-4" style="border-radius: 15px;">
                                                                    <div class="mb-3 mx-auto" style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; background: #e2e8f0;">
                                                                        <?php if ($doc_avatar) : ?>
                                                                            <img src="<?php echo esc_url($doc_avatar); ?>" class="w-100 h-100" style="object-fit: cover;">
                                                                        <?php else : ?>
                                                                            <i class="bi bi-person text-white" style="font-size: 4rem; line-height: 120px;"></i>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <h4 class="m-0 fw-bold"><?php echo esc_html(get_the_title($docente_id)); ?></h4>
                                                                    <?php if ($doc_prefijo) : ?>
                                                                        <p class="text-muted small mt-1"><?php echo esc_html($doc_prefijo); ?></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <!-- Acordeón de Docentes y Comité -->
                                        <div class="wp-block-kadence-accordion mt-5">
                                            <div class="kt-accordion-wrap kt-accordion-block">
                                                <div class="kt-accordion-inner-wrap">
                                                    <div class="wp-block-kadence-pane kt-accordion-pane">
                                                        <div class="kt-accordion-header-wrap">
                                                            <button class="kt-blocks-accordion-header" type="button">
                                                                <span class="kt-blocks-accordion-title"><?php _e('Docentes de la Especialización', 'flacso-uruguay'); ?></span>
                                                                <span class="kt-blocks-accordion-icon-trigger"></span>
                                                            </button>
                                                        </div>
                                                        <div class="kt-accordion-panel">
                                                            <div class="kt-accordion-panel-inner">
                                                                <!-- Aquí iría el listado de todos los docentes si fuera necesario -->
                                                                <p class="text-center text-muted"><?php _e('Consulta el equipo completo en la sección de información.', 'flacso-uruguay'); ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Seminarios Vinculados (Opcional, pero recomendado) -->
                                <?php
                                if (class_exists('Oferta_Seminarios_Integration')) {
                                    $seminarios = Oferta_Seminarios_Integration::get_programa_seminarios_data($post_id);
                                    if (!empty($seminarios)) :
                                ?>
                                    <div class="site-container my-5 py-5">
                                        <div class="d-flex justify-content-between align-items-center mb-4">
                                            <h2 class="wp-block-heading m-0"><?php _e('Seminarios Vinculados', 'flacso-uruguay'); ?></h2>
                                            <a href="<?php echo trailingslashit(get_permalink($post_id)) . 'seminarios/'; ?>" class="btn btn-outline-primary rounded-pill">
                                                <?php _e('Ver todos', 'flacso-uruguay'); ?> <i class="bi bi-arrow-right ms-1"></i>
                                            </a>
                                        </div>
                                        <div class="row g-4">
                                            <?php foreach (array_slice($seminarios, 0, 3) as $seminario) : ?>
                                                <div class="col-md-4">
                                                    <div class="card h-100 border-0 shadow-sm p-4" style="border-radius: 12px; transition: transform 0.2s;">
                                                        <h5 class="fw-bold mb-3">
                                                            <a href="<?php echo esc_url($seminario['permalink']); ?>" class="text-dark text-decoration-none">
                                                                <?php echo esc_html($seminario['titulo']); ?>
                                                            </a>
                                                        </h5>
                                                        <div class="text-muted small mt-auto">
                                                            <i class="bi bi-calendar3 me-1"></i> <?php echo esc_html($seminario['fecha_inicio']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php 
                                    endif;
                                } 
                                ?>

                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Acordeón funcional (si no hay Kadence activo)
    $('.kt-blocks-accordion-header').on('click', function() {
        const $pane = $(this).closest('.kt-accordion-pane');
        const $panel = $pane.find('.kt-accordion-panel');
        const $wrap = $(this).closest('.kt-accordion-inner-wrap');
        
        // Cerrar otros si es necesario
        $wrap.find('.kt-accordion-pane').not($pane).removeClass('kt-accordion-pane-active').find('.kt-accordion-panel').slideUp();
        
        $panel.slideToggle();
        $pane.toggleClass('kt-accordion-pane-active');
        $(this).attr('aria-expanded', function(i, attr) { return attr === 'true' ? 'false' : 'true'; });
    });
});
</script>

<style>
/* Estilos Base para emular Kadence Premium */
:root {
    --flacso-blue: #163970;
    --flacso-yellow: #fcd116;
}

.flacso-oferta-academica-premium .entry-hero {
    position: relative;
    padding: 100px 0;
    background: #051938;
    color: white;
    text-align: center;
}
.flacso-oferta-academica-premium .hero-section-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(5, 25, 56, 0.95) 0%, rgba(22, 57, 112, 0.6) 100%);
}
.flacso-oferta-academica-premium .entry-title {
    font-size: clamp(2.2rem, 6vw, 4rem);
    font-weight: 800;
    margin-bottom: 1.5rem;
    line-height: 1;
}

/* Acordeón Estilo Kadence */
.flacso-oferta-academica-premium .kt-accordion-pane {
    border: none;
    margin-bottom: 15px;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.flacso-oferta-academica-premium .kt-blocks-accordion-header {
    width: 100%;
    text-align: left;
    padding: 18px 24px;
    background: var(--flacso-blue);
    color: white;
    border: none;
    font-size: 1.1rem;
    font-weight: 700;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: all 0.3s ease;
}
.flacso-oferta-academica-premium .kt-accordion-pane-active .kt-blocks-accordion-header {
    background: var(--flacso-yellow);
    color: #163970;
}
.flacso-oferta-academica-premium .kt-blocks-accordion-icon-trigger::before {
    content: "+";
    font-size: 1.5rem;
    font-weight: 300;
}
.flacso-oferta-academica-premium .kt-accordion-pane-active .kt-blocks-accordion-icon-trigger::before {
    content: "−";
}

/* Document Cards */
.flacso-oferta-academica-premium .flacso-oferta-documento-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 20px;
}
.flacso-oferta-academica-premium .flacso-oferta-documento-card__icon {
    font-size: 2rem;
    color: var(--flacso-blue);
}
.flacso-oferta-academica-premium .flacso-oferta-documento-card__title {
    margin: 0;
    flex-grow: 1;
    font-size: 1.2rem;
    font-weight: 700;
}
.flacso-oferta-academica-premium .flacso-oferta-documento-card__button {
    background: var(--flacso-blue);
    color: white;
    padding: 10px 25px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: transform 0.2s;
}
.flacso-oferta-academica-premium .flacso-oferta-documento-card__button:hover {
    transform: translateY(-2px);
    color: white;
    background: #0f2a52;
}

/* Formulario */
.flacso-consultas-formulario {
    background: white;
    padding: 30px;
    border-radius: 20px;
    border-top: 5px solid var(--flacso-yellow);
}

/* Banner */
.flacso-inscripciones-banner {
    position: relative;
    height: 350px;
    border-radius: 20px;
    overflow: hidden;
    background: #eee;
}
.flacso-inscripciones-banner__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.flacso-inscripciones-banner__overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.6), transparent);
    padding: 30px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.flacso-inscripciones-banner__tag {
    background: var(--flacso-yellow);
    color: #000;
    padding: 8px 20px;
    border-radius: 50px;
    font-weight: 800;
    display: inline-block;
    text-transform: uppercase;
    font-size: 0.9rem;
}
.flacso-inscripciones-banner__logo {
    height: 50px;
    float: right;
}
</style>

<?php
get_footer();
