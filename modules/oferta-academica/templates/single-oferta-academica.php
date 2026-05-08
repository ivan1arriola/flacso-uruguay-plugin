<?php
/**
 * Template para la página individual de una Oferta Académica.
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = get_the_ID();
$data = Oferta_Data_Schema::get_schema($post_id);
$thumbnail_url = get_the_post_thumbnail_url($post_id, 'full');

// Cargar estilos
if (class_exists('Oferta_Renderer')) {
    Oferta_Renderer::enqueue_styles();
}

get_header();
?>

<div class="flacso-oferta-academica-single">
    <!-- Hero Section -->
    <section class="oferta-hero position-relative text-white py-5 mb-5" style="background: #051938;">
        <?php if ($thumbnail_url) : ?>
            <div class="oferta-hero__bg position-absolute top-0 start-0 w-100 h-100" style="background-image: url('<?php echo esc_url($thumbnail_url); ?>'); background-size: cover; background-position: center; opacity: 0.3;"></div>
        <?php endif; ?>
        
        <div class="container position-relative py-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <p class="text-uppercase tracking-wider mb-2" style="font-weight: 600; font-size: 0.9rem; color: #fbc02d;">
                        <?php
                        $tipo_html = get_the_term_list($post_id, 'tipo-oferta-academica', '', ', ');
                        if (!is_wp_error($tipo_html) && !empty($tipo_html)) {
                            echo wp_kses_post($tipo_html);
                        }
                        ?>
                    </p>
                    <h1 class="display-3 fw-bold mb-3"><?php the_title(); ?></h1>
                    
                    <?php if (!empty($data['abreviacion'])) : ?>
                        <span class="badge bg-light text-dark mb-4 px-3 py-2"><?php echo esc_html($data['abreviacion']); ?></span>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-4 mt-4">
                        <?php if (!empty($data['duracion_meses'])) : ?>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-clock-history fs-4 me-2"></i>
                                <div>
                                    <small class="d-block text-white-50"><?php _e('Duración', 'flacso-uruguay'); ?></small>
                                    <strong><?php echo esc_html($data['duracion_meses']); ?> <?php _e('meses', 'flacso-uruguay'); ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($data['proximo_inicio']['valor'])) : ?>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-calendar-check fs-4 me-2"></i>
                                <div>
                                    <small class="d-block text-white-50"><?php _e('Próximo inicio', 'flacso-uruguay'); ?></small>
                                    <strong><?php echo esc_html($data['proximo_inicio']['valor']); ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($data['inscripciones_abiertas'])) : ?>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-success px-3 py-2">
                                    <i class="bi bi-check-circle me-1"></i> <?php _e('Inscripciones Abiertas', 'flacso-uruguay'); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container mb-5">
        <div class="row g-5">
            <!-- Main Content -->
            <div class="col-lg-8">
                <nav class="oferta-nav-tabs mb-4 sticky-top bg-white py-3 shadow-sm rounded px-3" style="top: 20px; z-index: 100;">
                    <div class="nav nav-pills gap-2" id="oferta-tab" role="tablist">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#presentacion"><?php _e('Presentación', 'flacso-uruguay'); ?></button>
                        <?php if (!empty($data['objetivos_html'])) : ?>
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#objetivos"><?php _e('Objetivos', 'flacso-uruguay'); ?></button>
                        <?php endif; ?>
                        <?php if (!empty($data['malla_curricular_html'])) : ?>
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#malla"><?php _e('Malla Curricular', 'flacso-uruguay'); ?></button>
                        <?php endif; ?>
                        <?php if (!empty($data['coordinacion_academica'])) : ?>
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#equipo"><?php _e('Equipo', 'flacso-uruguay'); ?></button>
                        <?php endif; ?>
                    </div>
                </nav>

                <div class="tab-content" id="oferta-tabContent">
                    <!-- Presentación -->
                    <div class="tab-pane fade show active" id="presentacion" role="tabpanel">
                        <div class="oferta-content-block bg-white p-4 p-md-5 rounded shadow-sm border">
                            <h2 class="h3 fw-bold mb-4 border-bottom pb-2 text-primary"><?php _e('Sobre el programa', 'flacso-uruguay'); ?></h2>
                            <?php 
                            if (!empty($data['modalidad_html'])) {
                                echo '<div class="mb-4">' . $data['modalidad_html'] . '</div>';
                            }
                            ?>
                            <div class="rich-text">
                                <?php the_content(); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Objetivos -->
                    <?php if (!empty($data['objetivos_html'])) : ?>
                    <div class="tab-pane fade" id="objetivos" role="tabpanel">
                        <div class="oferta-content-block bg-white p-4 p-md-5 rounded shadow-sm border">
                            <h2 class="h3 fw-bold mb-4 border-bottom pb-2 text-primary"><?php _e('Objetivos', 'flacso-uruguay'); ?></h2>
                            <div class="rich-text">
                                <?php echo $data['objetivos_html']; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Malla -->
                    <?php if (!empty($data['malla_curricular_html'])) : ?>
                    <div class="tab-pane fade" id="malla" role="tabpanel">
                        <div class="oferta-content-block bg-white p-4 p-md-5 rounded shadow-sm border">
                            <h2 class="h3 fw-bold mb-4 border-bottom pb-2 text-primary"><?php _e('Malla Curricular', 'flacso-uruguay'); ?></h2>
                            <div class="rich-text">
                                <?php echo $data['malla_curricular_html']; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Equipo -->
                    <?php if (!empty($data['coordinacion_academica'])) : ?>
                    <div class="tab-pane fade" id="equipo" role="tabpanel">
                        <div class="oferta-content-block bg-white p-4 p-md-5 rounded shadow-sm border">
                            <h2 class="h3 fw-bold mb-4 border-bottom pb-2 text-primary"><?php _e('Equipo Docente', 'flacso-uruguay'); ?></h2>
                            <?php foreach ($data['coordinacion_academica'] as $coord) : ?>
                                <div class="mb-4">
                                    <h4 class="h5 fw-bold mb-2"><?php echo esc_html($coord['rol']); ?></h4>
                                    <ul class="list-unstyled">
                                        <?php 
                                        if (class_exists('CPT_Docentes')) {
                                            foreach ($coord['docentes'] as $docente_id) {
                                                echo '<li><i class="bi bi-person me-2 text-muted"></i> ' . esc_html(get_the_title($docente_id)) . '</li>';
                                            }
                                        }
                                        ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Seminarios Vinculados -->
                <?php
                if (class_exists('Oferta_Seminarios_Integration')) {
                    $seminarios = Oferta_Seminarios_Integration::get_programa_seminarios_data($post_id);
                    if (!empty($seminarios)) :
                ?>
                    <section class="mt-5">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2 class="h3 fw-bold m-0"><?php _e('Seminarios Disponibles', 'flacso-uruguay'); ?></h2>
                            <a href="<?php echo trailingslashit(get_permalink($post_id)) . 'seminarios/'; ?>" class="btn btn-link text-primary fw-bold text-decoration-none p-0">
                                <?php _e('Ver todos', 'flacso-uruguay'); ?> <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                        <div class="row g-3">
                            <?php foreach (array_slice($seminarios, 0, 3) as $seminario) : ?>
                                <div class="col-md-4">
                                    <a href="<?php echo esc_url($seminario['permalink']); ?>" class="card h-100 border-0 shadow-sm text-decoration-none">
                                        <div class="card-body">
                                            <h5 class="card-title text-dark fw-bold small mb-2"><?php echo esc_html($seminario['titulo']); ?></h5>
                                            <div class="text-muted" style="font-size: 0.75rem;">
                                                <i class="bi bi-calendar me-1"></i> <?php echo esc_html($seminario['fecha_inicio']); ?>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php 
                    endif;
                } 
                ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <aside class="oferta-sidebar sticky-top" style="top: 20px;">
                    <div class="card border-0 shadow-sm mb-4 overflow-hidden">
                        <div class="card-header bg-primary text-white text-center py-3">
                            <h3 class="h5 fw-bold mb-0"><?php _e('Inscripciones', 'flacso-uruguay'); ?></h3>
                        </div>
                        <div class="card-body p-4 text-center">
                            <p class="text-muted small mb-4"><?php _e('Si deseas recibir más información sobre este programa, completa el formulario de consulta.', 'flacso-uruguay'); ?></p>
                            
                            <a href="#contacto" class="btn btn-primary w-100 py-3 fw-bold mb-3">
                                <?php _e('Consultar ahora', 'flacso-uruguay'); ?>
                            </a>

                            <?php if (!empty($data['correo'])) : ?>
                                <a href="mailto:<?php echo esc_attr($data['correo']); ?>" class="btn btn-outline-secondary w-100 py-2">
                                    <i class="bi bi-envelope me-2"></i> <?php echo esc_html($data['correo']); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($data['malla_curricular']) || !empty($data['calendario'])) : ?>
                        <div class="card border-0 shadow-sm p-4">
                            <h4 class="h6 fw-bold mb-3 border-bottom pb-2"><?php _e('Recursos', 'flacso-uruguay'); ?></h4>
                            <div class="d-grid gap-2">
                                <?php if (!empty($data['malla_curricular'])) : ?>
                                    <a href="<?php echo esc_url($data['malla_curricular']); ?>" class="btn btn-light text-start text-primary" target="_blank">
                                        <i class="bi bi-file-earmark-pdf me-2"></i> <?php _e('Descargar Malla', 'flacso-uruguay'); ?>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($data['calendario'])) : ?>
                                    <a href="<?php echo esc_url($data['calendario']); ?>" class="btn btn-light text-start text-primary" target="_blank">
                                        <i class="bi bi-calendar3 me-2"></i> <?php _e('Ver Calendario', 'flacso-uruguay'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </div>

    <!-- Contacto -->
    <section id="contacto" class="bg-light py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="bg-white p-4 p-md-5 rounded shadow-sm">
                        <div class="row align-items-center">
                            <div class="col-md-6 mb-4 mb-md-0">
                                <h2 class="fw-bold mb-3"><?php _e('¿Tienes dudas?', 'flacso-uruguay'); ?></h2>
                                <p class="text-muted"><?php _e('Nuestro equipo está disponible para asesorarte en el proceso de inscripción y responder tus preguntas sobre el programa académico.', 'flacso-uruguay'); ?></p>
                                <ul class="list-unstyled mt-4">
                                    <li class="mb-2"><i class="bi bi-geo-alt me-3 text-primary"></i> Zelmar Michelini 1220, Montevideo</li>
                                    <li class="mb-2"><i class="bi bi-telephone me-3 text-primary"></i> (+598) 2903 0144</li>
                                    <li><i class="bi bi-envelope me-3 text-primary"></i> <?php echo !empty($data['correo']) ? esc_html($data['correo']) : 'inscripciones@flacso.edu.uy'; ?></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <?php 
                                // Renderizar formulario de consulta si el módulo existe
                                if (class_exists('Oferta_Consulta_Form')) {
                                    echo Oferta_Consulta_Form::render_shortcode(['id' => $post_id]);
                                } else {
                                    echo '<p class="alert alert-info">' . __('Formulario de contacto disponible próximamente.', 'flacso-uruguay') . '</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
.oferta-nav-tabs .nav-link {
    color: #6c757d;
    font-weight: 600;
    font-size: 0.9rem;
    padding: 0.6rem 1.2rem;
    border-radius: 0.5rem;
}
.oferta-nav-tabs .nav-link.active {
    background-color: #0d6efd !important;
    color: white;
}
.rich-text {
    line-height: 1.7;
    color: #4a5568;
}
.rich-text p {
    margin-bottom: 1.5rem;
}
.oferta-hero__bg {
    filter: brightness(0.7);
}
</style>

<?php
get_footer();
