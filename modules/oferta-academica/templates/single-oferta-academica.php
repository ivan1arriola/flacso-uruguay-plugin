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
$tipo_oferta = '';
$tipo_terms = get_the_terms($post_id, 'tipo-oferta-academica');

if (!is_wp_error($tipo_terms) && !empty($tipo_terms)) {
    $tipo_oferta = $tipo_terms[0]->name;
}

$inscripciones_abiertas = !empty($data['inscripciones_abiertas']);
$hero_tag = $inscripciones_abiertas ? __('Inscripciones 2026', 'flacso-uruguay') : __('Próximamente', 'flacso-uruguay');
$hero_cta = $inscripciones_abiertas
    ? __('Descuentos especiales disponibles. Solicitá información e inscribite hoy.', 'flacso-uruguay')
    : __('Solicitá información y te avisaremos cuando abra la próxima cohorte.', 'flacso-uruguay');
$hero_cta_markup = esc_html($hero_cta);
$logo_url = 'https://flacso.edu.uy/wp-content/uploads/2026/05/logo_flacso_uruguay_20anos_blanco.png';

if ($inscripciones_abiertas) {
    $preinscripcion_url = trailingslashit(get_permalink($post_id)) . 'preinscripcion/';
    $hero_cta_markup = sprintf(
        '%s <a href="%s">%s</a>',
        esc_html__('Descuentos especiales disponibles.', 'flacso-uruguay'),
        esc_url($preinscripcion_url),
        esc_html__('Solicitá información e inscribite hoy.', 'flacso-uruguay')
    );
}

$programa_meta = array_filter([
    !empty($data['cohorte']) ? (string) $data['cohorte'] : '',
    !empty($data['abreviacion']) ? strtoupper((string) $data['abreviacion']) : '',
]);

// Cargar estilos de la oferta
if (class_exists('Oferta_Renderer')) {
    Oferta_Renderer::enqueue_styles();
}

get_header();
?>

<div id="inner-wrap" class="wrap kt-clear flacso-oferta-academica-premium">

    <div id="primary" class="content-area">
        <div class="content-container site-container">
            <div id="main" class="site-main">
                <div class="content-wrap">
                    <article id="post-<?php echo $post_id; ?>" class="entry content-bg single-entry">
                        <div class="entry-content-wrap">
                            <div class="entry-content single-content">
                                
                                <!-- Banner Superior -->
                                <section class="flacso-oa-single-hero mb-4" aria-label="<?php esc_attr_e('Hero de inscripciones', 'flacso-uruguay'); ?>">
                                    <div class="kt-inside-inner-col">
                                        <?php
                                        if (class_exists('Flacso_Inscripciones_Banner_Block')) {
                                            echo Flacso_Inscripciones_Banner_Block::get_instance()->render_block([]);
                                        } else {
                                        ?>
                                            <div class="flacso-inscripciones-banner">
                                                <?php if ($thumbnail_url) : ?>
                                                    <img decoding="async" class="flacso-inscripciones-banner__img" src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php the_title_attribute(); ?>">
                                                <?php else : ?>
                                                    <div class="flacso-inscripciones-banner__img flacso-inscripciones-banner__img--placeholder" aria-hidden="true"></div>
                                                <?php endif; ?>
                                                <div class="flacso-inscripciones-banner__overlay">
                                                    <div class="flacso-inscripciones-banner__top">
                                                        <div class="flacso-inscripciones-banner__tag">
                                                            <?php echo esc_html($hero_tag); ?>
                                                        </div>
                                                        <img decoding="async" src="<?php echo esc_url($logo_url); ?>" alt="FLACSO Uruguay" class="flacso-inscripciones-banner__logo">
                                                    </div>
                                                    <div class="flacso-inscripciones-banner__bottom">
                                                        <div class="flacso-inscripciones-banner__cta">
                                                            <?php echo wp_kses_post($hero_cta_markup); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </section>

                                <header class="flacso-oa-single-intro mb-5">
                                    <?php if ($tipo_oferta !== '') : ?>
                                        <p class="flacso-oa-single-intro__eyebrow"><?php echo esc_html($tipo_oferta); ?></p>
                                    <?php endif; ?>
                                    <h1 class="wp-block-heading flacso-oa-single-intro__title"><?php the_title(); ?></h1>
                                    <?php if (!empty($programa_meta)) : ?>
                                        <p class="flacso-oa-single-intro__meta">
                                            <?php foreach ($programa_meta as $meta_item) : ?>
                                                <span><?php echo esc_html($meta_item); ?></span>
                                            <?php endforeach; ?>
                                        </p>
                                    <?php endif; ?>
                                </header>

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

                                <!-- Próximo Inicio -->
                                <?php if (class_exists('Oferta_Blocks')) : ?>
                                    <div class="mb-5">
                                        <?php echo Oferta_Blocks::render_dato_proximo_inicio(['ofertaId' => $post_id]); ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Acordeones de Información -->
                                <div class="mt-5">
                                                <div class="flacso-oferta-cards-container flacso-oferta-cards-grid">
                                                    
                                                    <!-- MODALIDAD -->
                                                    <div class="card flacso-oferta-info-card mb-4 border-0 shadow-sm rounded-4 overflow-hidden" style="background-color: #f8fafc;">
                                                        <div class="card-header bg-white border-0 py-3 px-4" style="border-left: 5px solid #fcd116 !important;">
                                                            <h3 class="card-title m-0" style="color: #163970; font-weight: 800; font-size: 1.1rem; text-transform: uppercase;"><?php _e('MODALIDAD', 'flacso-uruguay'); ?></h3>
                                                        </div>
                                                        <div class="card-body px-4 py-3" style="font-size: 1.05rem; line-height: 1.6; color: #444;">
                                                            <?php echo !empty($data['modalidad_html']) ? $data['modalidad_html'] : __('Virtual.', 'flacso-uruguay'); ?>
                                                        </div>
                                                    </div>

                                                    <!-- OBJETIVOS -->
                                                    <?php if (!empty($data['objetivos_html'])) : ?>
                                                    <div class="card flacso-oferta-info-card mb-4 border-0 shadow-sm rounded-4 overflow-hidden" style="background-color: #f8fafc;">
                                                        <div class="card-header bg-white border-0 py-3 px-4" style="border-left: 5px solid #fcd116 !important;">
                                                            <h3 class="card-title m-0" style="color: #163970; font-weight: 800; font-size: 1.1rem; text-transform: uppercase;"><?php _e('OBJETIVOS', 'flacso-uruguay'); ?></h3>
                                                        </div>
                                                        <div class="card-body px-4 py-3" style="font-size: 1.05rem; line-height: 1.6; color: #444;">
                                                            <?php echo $data['objetivos_html']; ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <!-- MALLA CURRICULAR -->
                                                    <div class="card flacso-oferta-info-card flacso-oferta-info-card--span-2 mb-4 border-0 shadow-sm rounded-4 overflow-hidden" style="background-color: #f8fafc;">
                                                        <div class="card-header bg-white border-0 py-3 px-4" style="border-left: 5px solid #fcd116 !important;">
                                                            <h3 class="card-title m-0" style="color: #163970; font-weight: 800; font-size: 1.1rem; text-transform: uppercase;"><?php _e('MALLA CURRICULAR', 'flacso-uruguay'); ?></h3>
                                                        </div>
                                                        <div class="card-body px-4 py-3" style="font-size: 1.05rem; line-height: 1.6; color: #444;">
                                                            <?php 
                                                            $html = !empty($data['malla_curricular_html']) ? $data['malla_curricular_html'] : '';
                                                            $pdf_url = !empty($data['malla_curricular']) ? $data['malla_curricular'] : '';
                                                            
                                                            if ($pdf_url && $html) {
                                                                $html = preg_replace('/<p>(<strong[^>]*>)?\s*<a[^>]+>Malla curricular<\/a>\s*(<\/strong>)?<\/p>/i', '', $html);
                                                                $html = preg_replace('/<a[^>]+>Malla curricular<\/a>/i', '', $html);
                                                                $html = trim($html);
                                                            }
                                                            
                                                            if ($html) {
                                                                echo wp_kses_post($html);
                                                            }
                                                            
                                                            if (class_exists('Oferta_Blocks') && $pdf_url) {
                                                                echo Oferta_Blocks::render_dato_malla_curricular(['ofertaId' => $post_id]);
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>

                                                    <!-- CALENDARIO -->
                                                    <div class="card flacso-oferta-info-card flacso-oferta-info-card--span-2 mb-4 border-0 shadow-sm rounded-4 overflow-hidden" style="background-color: #f8fafc;">
                                                        <div class="card-header bg-white border-0 py-3 px-4" style="border-left: 5px solid #fcd116 !important;">
                                                            <h3 class="card-title m-0" style="color: #163970; font-weight: 800; font-size: 1.1rem; text-transform: uppercase;"><?php _e('CALENDARIO', 'flacso-uruguay'); ?></h3>
                                                        </div>
                                                        <div class="card-body px-4 py-3" style="font-size: 1.05rem; line-height: 1.6; color: #444;">
                                                            <?php 
                                                            $html_cal = !empty($data['calendario_html']) ? $data['calendario_html'] : '';
                                                            $pdf_cal = !empty($data['calendario']) ? $data['calendario'] : '';
                                                            
                                                            if ($pdf_cal && $html_cal) {
                                                                $html_cal = preg_replace('/<p>(<strong[^>]*>)?\s*<a[^>]+>(Calendario|Cronograma)<\/a>\s*(<\/strong>)?<\/p>/i', '', $html_cal);
                                                                $html_cal = preg_replace('/<a[^>]+>(Calendario|Cronograma)<\/a>/i', '', $html_cal);
                                                                $html_cal = trim($html_cal);
                                                            }
                                                            
                                                            if ($html_cal) {
                                                                echo wp_kses_post($html_cal);
                                                            }
                                                            
                                                            if (class_exists('Oferta_Blocks') && $pdf_cal) {
                                                                echo Oferta_Blocks::render_dato_calendario(['ofertaId' => $post_id]); 
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>

                                                    <!-- FINANCIACION -->
                                                    <div class="card flacso-oferta-info-card flacso-oferta-info-card--span-2 mb-4 border-0 shadow-sm rounded-4 overflow-hidden" style="background-color: #f8fafc;">
                                                        <div class="card-header bg-white border-0 py-3 px-4" style="border-left: 5px solid #fcd116 !important;">
                                                            <h3 class="card-title m-0" style="color: #163970; font-weight: 800; font-size: 1.1rem; text-transform: uppercase;"><?php _e('FINANCIACIÓN Y BECAS', 'flacso-uruguay'); ?></h3>
                                                        </div>
                                                        <div class="card-body px-4 py-3" style="font-size: 1.05rem; line-height: 1.6; color: #444;">
                                                            <?php 
                                                            $financiacion_html = !empty($data['financiacion_html']) ? $data['financiacion_html'] : '';
                                                            if (empty($financiacion_html)) {
                                                                $financiacion_html = '<p>' . __('FLACSO ofrece financiación flexible, el monto de las cuotas puede variar dependiendo de las promociones que haya aprovechado, o el plan de pagos que se coordine con la Institución. Todos los posgrados pueden abonarse en cuotas, siguiendo un plan mensual de pagos que acompañan la cursada. No obstante, es posible extender los planes de pago en forma flexible, con valores de cuota a su alcance.', 'flacso-uruguay') . '</p>';
                                                                $financiacion_html .= '<p>' . __('Quienes cursen desde fuera de Uruguay pueden pagar de forma segura a través de la plataforma de pago de la institución, mientras las personas que cursan en el país, disponen de otras vías para pagar.', 'flacso-uruguay') . '</p>';
                                                                $financiacion_html .= '<p>' . __('Las becas para cursar en FLACSO Uruguay están sujetas a convenios inter institucionales y son limitadas por cohorte. Para obtener más información sobre las posibles becas disponibles puede comunicarse con la asistente académica.', 'flacso-uruguay') . '</p>';
                                                            }
                                                            echo wp_kses_post($financiacion_html); 
                                                            ?>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                        <!-- FIN Columna Izquierda -->

                                        <!-- Columna Derecha: Formulario -->
                                        <div class="wp-block-kadence-column inner-column-2">
                                            <div class="kt-inside-inner-col" style="position: sticky; top: 2rem;">
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
                                        <!-- FIN Columna Derecha -->

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
                                                    <div class="row justify-content-center">
                                                        <?php foreach ($coord['docentes'] as $docente_id) : ?>
                                                            <div class="col-12 col-lg-10 mb-4">
                                                                <?php 
                                                                if (function_exists('dp_docente_destacado')) {
                                                                    echo dp_docentes_wrap_output(dp_docente_destacado(['docId' => $docente_id, 'rol' => $coord['rol']]));
                                                                }
                                                                ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <?php if (!empty($data['equipos'])) : ?>
                                            <?php foreach ($data['equipos'] as $grupo) : ?>
                                                <div class="mb-5">
                                                    <div class="row justify-content-center">
                                                        <div class="col-12 col-lg-10">
                                                            <?php 
                                                            if (function_exists('dp_docentes_grupo_block_render')) {
                                                                echo dp_docentes_grupo_block_render([
                                                                    'title' => $grupo['nombre'],
                                                                    'level' => 'h3',
                                                                    'docenteIds' => $grupo['docentes']
                                                                ]);
                                                            } else {
                                                                // Fallback if plugin is disabled
                                                                echo '<h3 class="wp-block-heading text-center mb-4">' . esc_html($grupo['nombre']) . '</h3>';
                                                            }
                                                            ?>
                                                        </div>
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
