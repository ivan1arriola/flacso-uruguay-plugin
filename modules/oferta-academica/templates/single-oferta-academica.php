<?php
/**
 * Template para la página individual de una Oferta Académica.
 * Replicando fielmente el diseño de flacso.edu.uy
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
                    <nav id="kadence-breadcrumbs" aria-label="Migas de pan" class="kadence-breadcrumbs">
                        <div class="kadence-breadcrumb-container">
                            <span><a href="<?php echo home_url(); ?>">Inicio</a></span> 
                            <span class="bc-delimiter">/</span> 
                            <span><a href="<?php echo home_url('/formacion/'); ?>">Oferta Académica</a></span> 
                            <span class="bc-delimiter">/</span> 
                            <span class="kadence-bread-current"><?php the_title(); ?></span>
                        </div>
                    </nav>
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
                                
                                <!-- Banner Superior -->
                                <div class="wp-block-kadence-column mb-5">
                                    <div class="kt-inside-inner-col">
                                        <?php 
                                        if (class_exists('Flacso_Inscripciones_Banner_Block')) {
                                            echo Flacso_Inscripciones_Banner_Block::init()->render_block([]); 
                                        } else {
                                        ?>
                                            <div class="flacso-inscripciones-banner">
                                                <?php if ($thumbnail_url) : ?>
                                                    <img decoding="async" class="flacso-inscripciones-banner__img" src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php the_title_attribute(); ?>">
                                                <?php endif; ?>
                                                <div class="flacso-inscripciones-banner__overlay">
                                                    <div class="flacso-inscripciones-banner__top">
                                                        <div class="flacso-inscripciones-banner__tag">
                                                            <?php echo !empty($data['inscripciones_abiertas']) ? __('Inscripciones 2026', 'flacso-uruguay') : __('Próximamente', 'flacso-uruguay'); ?>
                                                        </div>
                                                        <?php
                                                        $logo_url = 'https://flacso.edu.uy/wp-content/uploads/2026/05/logo_flacso_uruguay_20anos_blanco.png';
                                                        ?>
                                                        <img decoding="async" src="<?php echo esc_url($logo_url); ?>" alt="FLACSO Uruguay" class="flacso-inscripciones-banner__logo">
                                                    </div>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>

                                <!-- Grid Principal (Descripción + Formulario) -->
                                <div class="kb-row-layout-wrap wp-block-kadence-rowlayout mb-5">
                                    <div class="kt-row-column-wrap kt-has-2-columns kt-row-layout-left-golden kt-mobile-layout-row">
                                        
                                        <!-- Columna Izquierda: Contenido -->
                                        <div class="wp-block-kadence-column inner-column-1">
                                            <div class="kt-inside-inner-col">
                                                <?php if (!empty($data['duracion_meses'])) : ?>
                                                    <h2 class="wp-block-heading mb-4"><?php printf(__('Duración: %s meses', 'flacso-uruguay'), $data['duracion_meses']); ?></h2>
                                                <?php endif; ?>

                                                <div class="entry-content-text" style="font-size: 1.1rem; line-height: 1.7; color: #444;">
                                                    <?php the_content(); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Columna Derecha: Formulario -->
                                        <div class="wp-block-kadence-column inner-column-2">
                                            <div class="kt-inside-inner-col">
                                                <div class="flacso-consultas-formulario-wrapper">
                                                    <?php 
                                                    if (function_exists('flacso_consultas_render_form')) {
                                                        echo flacso_consultas_render_form(['mostrar_preinscripcion' => true]);
                                                    } else {
                                                    ?>
                                                        <div class="flacso-consultas-formulario">
                                                            <h3 class="mb-2" style="font-weight: 800;"><?php _e('Solicitá información', 'flacso-uruguay'); ?></h3>
                                                            <p class="mb-4 small text-muted"><?php _e('Llená el formulario y recibí toda la información de cursada 2026.', 'flacso-uruguay'); ?></p>
                                                            
                                                            <?php 
                                                            if (class_exists('Oferta_Consulta_Form')) {
                                                                echo Oferta_Consulta_Form::render_inline_form($post_id);
                                                            }
                                                            ?>
                                                        </div>

                                                        <?php if (!empty($data['inscripciones_abiertas'])) : ?>
                                                            <div class="mt-4">
                                                                <a href="<?php echo trailingslashit(get_permalink($post_id)) . 'preinscripcion'; ?>" class="flacso-btn-preinsc">
                                                                    <?php _e('Preinscripción 2026', 'flacso-uruguay'); ?>
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Próximo Inicio -->
                                <?php if (class_exists('Oferta_Blocks')) : ?>
                                    <div class="mb-5">
                                        <?php echo Oferta_Blocks::render_dato_proximo_inicio(['ofertaId' => $post_id]); ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Acordeones de Información -->
                                <div class="kb-row-layout-wrap wp-block-kadence-rowlayout mb-5" style="padding: 40px 0;">
                                    <div class="kt-row-column-wrap kt-has-1-columns">
                                        <div class="wp-block-kadence-column inner-column-1">
                                            <div class="kt-inside-inner-col">
                                                <div class="wp-block-kadence-accordion alignnone">
                                                    <div class="kt-accordion-wrap kt-accordion-block kt-accodion-icon-style-basic kt-accodion-icon-side-right">
                                                        <div class="kt-accordion-inner-wrap" data-allow-multiple-open="false">
                                                            
                                                            <!-- MODALIDAD -->
                                                            <div class="wp-block-kadence-pane kt-accordion-pane">
                                                                <div class="kt-accordion-header-wrap">
                                                                    <button class="kt-blocks-accordion-header" type="button">
                                                                        <span class="kt-blocks-accordion-title"><?php _e('MODALIDAD', 'flacso-uruguay'); ?></span>
                                                                        <span class="kt-blocks-accordion-icon-trigger"></span>
                                                                    </button>
                                                                </div>
                                                                <div class="kt-accordion-panel">
                                                                    <div class="kt-accordion-panel-inner">
                                                                        <?php echo !empty($data['modalidad_html']) ? $data['modalidad_html'] : __('Virtual.', 'flacso-uruguay'); ?>
                                                                    </div>
                                                                </div>
                                                            </div>

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
                                                                        <p><?php _e('Las becas para cursar en FLACSO Uruguay están sujetas a convenios inter institucionales y son limitadas por cohorte.', 'flacso-uruguay'); ?></p>
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

                                <!-- CTA Intermedio -->
                                <div class="kb-row-layout-wrap wp-block-kadence-rowlayout alignfull mb-5" style="background-color: #163970; color: white; padding: 40px 0;">
                                    <div class="site-container">
                                        <p class="text-center m-0" style="font-size: 1.1rem;"><?php _e('Para realizar las postulaciones puede completar el formulario y en breve el personal de asistencia académica se pondrá en contacto.', 'flacso-uruguay'); ?></p>
                                    </div>
                                </div>

                                <!-- Equipo Académico -->
                                <div class="kb-row-layout-wrap wp-block-kadence-rowlayout alignfull" style="background-color: #f8fafc; padding: 80px 0;">
                                    <div class="site-container">
                                        <h2 class="wp-block-heading text-center mb-5" style="text-transform:uppercase; font-weight: 800; color: #163970;"><?php _e('Equipo Académico', 'flacso-uruguay'); ?></h2>
                                        
                                        <?php if (!empty($data['coordinacion_academica'])) : ?>
                                            <?php foreach ($data['coordinacion_academica'] as $coord) : ?>
                                                <div class="mb-5">
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Seminarios Vinculados -->
                                <?php
                                if (class_exists('Oferta_Seminarios_Integration')) {
                                    $seminarios_all = Oferta_Seminarios_Integration::get_programa_seminarios_data($post_id);
                                    $seminarios = array();
                                    $hoy = new DateTimeImmutable('today', wp_timezone());
                                    foreach ($seminarios_all as $s) {
                                        if (empty($s['fecha_inicio'])) {
                                            continue;
                                        }
                                        $inicio_obj = DateTimeImmutable::createFromFormat('Y-m-d|', $s['fecha_inicio'], wp_timezone());
                                        if (!$inicio_obj) {
                                            continue;
                                        }
                                        $dias_hasta_inicio = (int) floor(($inicio_obj->getTimestamp() - $hoy->getTimestamp()) / DAY_IN_SECONDS);
                                        $is_upcoming = $dias_hasta_inicio >= 0;
                                        $is_started_recent = $dias_hasta_inicio < 0 && $dias_hasta_inicio >= -7;
                                        if ($is_upcoming || $is_started_recent) {
                                            $seminarios[] = $s;
                                        }
                                    }
                                    if (!empty($seminarios)) :
                                ?>
                                    <div class="site-container my-5 py-5">
                                        <div class="d-flex justify-content-between align-items-center mb-4">
                                            <h2 class="wp-block-heading m-0" style="color: #163970; font-weight: 800;"><?php _e('Seminarios Vinculados', 'flacso-uruguay'); ?></h2>
                                            <a href="<?php echo trailingslashit(get_permalink($post_id)) . 'seminarios/'; ?>" class="btn btn-primary rounded-pill px-4">
                                                <?php _e('Ver todos', 'flacso-uruguay'); ?> <i class="bi bi-arrow-right ms-2"></i>
                                            </a>
                                        </div>
                                        <div class="row g-4">
                                            <?php foreach (array_slice($seminarios, 0, 3) as $seminario) : ?>
                                                <div class="col-md-4">
                                                    <div class="card h-100 border-0 shadow-sm p-4" style="border-radius: 15px; border-left: 4px solid #fcd116;">
                                                        <h5 class="fw-bold mb-3" style="line-height: 1.4;">
                                                            <a href="<?php echo esc_url($seminario['permalink']); ?>" class="text-dark text-decoration-none">
                                                                <?php echo esc_html($seminario['titulo']); ?>
                                                            </a>
                                                        </h5>
                                                        <div class="text-muted small mt-auto">
                                                            <i class="bi bi-calendar3 me-2" style="color: #163970;"></i> <?php echo esc_html($seminario['fecha_inicio']); ?>
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
        
        $wrap.find('.kt-accordion-pane').not($pane).removeClass('kt-accordion-pane-active').find('.kt-accordion-panel').slideUp();
        $panel.slideToggle();
        $pane.toggleClass('kt-accordion-pane-active');
    });
});
</script>

<style>
/* Estilos Base para emular el diseño exacto de flacso.edu.uy */
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

/* Breadcrumbs */
.flacso-oferta-academica-premium .kadence-breadcrumbs {
    font-size: 0.9rem;
    opacity: 0.8;
}
.flacso-oferta-academica-premium .kadence-breadcrumbs a {
    color: white;
    text-decoration: none;
}

/* Formulario */
.flacso-consultas-formulario {
    background: white;
    padding: 35px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    border-top: 6px solid var(--flacso-yellow);
}
.flacso-oa-consulta__field {
    margin-bottom: 20px;
    text-align: left;
}
.flacso-oa-consulta__field label {
    display: block;
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--flacso-blue-light);
    font-size: 0.9rem;
}
.flacso-oa-consulta__field input,
.flacso-oa-consulta__field textarea {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
    transition: all 0.3s;
}
.flacso-oa-consulta__field input:focus,
.flacso-oa-consulta__field textarea:focus {
    border-color: var(--flacso-blue-light);
    background: white;
    box-shadow: 0 0 0 3px rgba(22, 57, 112, 0.1);
    outline: none;
}
.flacso-oa-consulta__grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}
.flacso-oa-consulta__submit {
    width: 100%;
    background: var(--flacso-yellow);
    color: var(--flacso-blue-light);
    border: none;
    padding: 15px;
    border-radius: 50px;
    font-weight: 800;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.3s;
}
.flacso-oa-consulta__submit:hover {
    background: #e5bd14;
    transform: translateY(-2px);
}
.flacso-btn-preinsc {
    display: block;
    width: 100%;
    text-align: center;
    background: var(--flacso-blue-light);
    color: white;
    padding: 15px;
    border-radius: 50px;
    font-weight: 800;
    text-decoration: none;
    text-transform: uppercase;
    transition: all 0.3s;
}
.flacso-btn-preinsc:hover {
    background: #0d254a;
    color: white;
    transform: translateY(-2px);
}

/* Banner */
.flacso-inscripciones-banner {
    border-radius: 15px;
    overflow: hidden;
    height: 380px;
    position: relative;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.flacso-inscripciones-banner__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.flacso-inscripciones-banner__overlay {
    position: absolute;
    inset: 0;
    padding: 30px;
    background: linear-gradient(to top, rgba(0,0,0,0.5), transparent);
}
.flacso-inscripciones-banner__tag {
    background: var(--flacso-yellow);
    color: black;
    padding: 10px 25px;
    border-radius: 50px;
    font-weight: 800;
    display: inline-block;
    text-transform: uppercase;
}
.flacso-inscripciones-banner__logo {
    height: 60px;
    float: right;
}

/* Docente Card */
.flacso-docente-card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    height: 100%;
}
.flacso-docente-card__avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    overflow: hidden;
    background: #e2e8f0;
    margin-bottom: 20px;
}
.flacso-docente-card__avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.flacso-docente-card__avatar i {
    font-size: 4rem;
    line-height: 120px;
    color: #cbd5e1;
}
.flacso-docente-card__info h4 {
    margin: 0 0 5px 0;
    font-weight: 800;
    color: var(--flacso-blue-light);
}
.flacso-docente-card__title {
    color: #64748b;
    font-size: 0.85rem;
    margin-bottom: 10px;
    font-weight: 600;
}

/* Acordeones */
.flacso-oferta-academica-premium .kt-accordion-pane {
    border: none;
    margin-bottom: 12px;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.flacso-oferta-academica-premium .kt-blocks-accordion-header {
    width: 100%;
    background: var(--flacso-blue-light);
    color: white;
    padding: 20px 25px;
    font-weight: 700;
    font-size: 1.1rem;
    border: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.flacso-oferta-academica-premium .kt-accordion-pane-active .kt-blocks-accordion-header {
    background: var(--flacso-yellow);
    color: var(--flacso-blue-light);
}
</style>

<?php
get_footer();
