<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Enqueue Bootstrap CSS and JS
wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css', [], '5.3.2');
wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.2', true);

// Enqueue Bootstrap Icons
wp_enqueue_style('bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css', [], '1.11.3');

/**
 * Convierte una fecha YYYY-MM-DD al formato español: "Lunes 20 de Enero 2026"
 */
function format_fecha_es($fecha) {
    if (empty($fecha)) return '';
    $timestamp = strtotime($fecha);
    if ($timestamp === false) return esc_html($fecha);
    
    $dias = array('Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado');
    $meses = array('', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre');
    
    $dia_semana = $dias[date('w', $timestamp)];
    $dia = date('j', $timestamp);
    $mes = $meses[date('n', $timestamp)];
    $ano = date('Y', $timestamp);
    
    return "$dia_semana $dia de $mes $ano";
}

while (have_posts()) : the_post();
    $post_id = get_the_ID();
    $meta = class_exists('Seminario_Meta') ? Seminario_Meta::get_meta($post_id) : array();
    $posgrados = class_exists('Seminario_Taxonomies')
        ? Seminario_Taxonomies::get_related_ofertas($post_id)
        : array();

    // Extract array-based meta
    $objetivos = isset($meta['objetivos_especificos']) && is_array($meta['objetivos_especificos']) ? $meta['objetivos_especificos'] : array();
    $unidades = isset($meta['unidades_academicas']) && is_array($meta['unidades_academicas']) ? $meta['unidades_academicas'] : array();
    $docentes_ids = isset($meta['docentes']) && is_array($meta['docentes']) ? $meta['docentes'] : array();
    $docentes_posts = array();
    if (!empty($docentes_ids)) {
        $docentes_posts = get_posts(array(
            'post_type' => 'docente',
            'post__in' => $docentes_ids,
            'posts_per_page' => -1,
            'orderby' => 'post__in',
        ));
    }

    // Preinscription URL with tracking parameters
    $preinscripcion_base = apply_filters('flacso_seminario_preinscripcion_url', home_url('/formacion/preinscripciones'), $post_id);
    $preinscripcion_url = '';
    if (!empty($preinscripcion_base)) {
        $preinscripcion_url = add_query_arg(array(
            'ID' => $post_id,
        ), $preinscripcion_base);
    }
?>

<style>
    /* Custom styles to complement Bootstrap usando variables del tema */
    .seminario-breadcrumb {
        font-size: 0.9rem;
        color: var(--global-palette5);
    }
    
    .seminario-breadcrumb a {
        color: var(--global-palette5);
        text-decoration: none;
        transition: color 0.2s;
    }
    
    .seminario-breadcrumb a:hover {
        color: var(--global-palette1);
    }
    
    .seminario-breadcrumb .breadcrumb-item.active {
        color: var(--global-palette1);
        font-weight: 600;
    }
    
    .seminario-badge {
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        border: 1.5px solid rgba(29, 58, 114, 0.15);
        background-color: rgba(var(--global-palette9rgb), 0.1) !important;
    }
    
    .seminario-badge-primary {
        color: var(--global-palette1);
        border-color: rgba(29, 58, 114, 0.2);
    }
    
    .seminario-badge-secondary {
        color: var(--global-palette14);
        border-color: rgba(247, 99, 12, 0.2);
        background-color: rgba(247, 99, 12, 0.1) !important;
    }
    
    .seminario-image-placeholder {
        background: linear-gradient(135deg, var(--global-palette8) 0%, var(--global-palette7) 100%);
        color: var(--global-palette6);
        font-size: 3rem;
    }
    
    .info-card {
        transition: transform 0.3s, box-shadow 0.3s;
        height: 100%;
        border: 1px solid var(--global-palette7);
        border-radius: 12px;
        background-color: var(--global-palette9);
    }
    
    .info-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1) !important;
    }
    
    .info-card-icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        color: var(--global-palette1);
    }
    
    .section-header {
        position: relative;
        padding-bottom: 1rem;
        margin-bottom: 2rem;
    }
    
    .section-header h2 {
        font-size: 1.5rem;
        margin-bottom: 0;
        color: var(--global-palette1);
        font-family: var(--global-heading-font-family, var(--global-fallback-font));
    }
    
    .section-header::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 60px;
        height: 3px;
        background: linear-gradient(90deg, var(--global-palette1) 0%, var(--global-palette2) 100%);
        border-radius: 2px;
    }
    
    .unidad-item {
        counter-increment: unidad-counter;
        position: relative;
        padding: 1.5rem;
        padding-left: 5rem;
        margin-bottom: 1.5rem;
        background: linear-gradient(135deg, var(--global-palette8) 0%, var(--global-palette9) 100%);
        border-radius: 12px;
        border-left: 4px solid var(--global-palette1);
        border: 1px solid var(--global-palette7);
        transition: transform 0.3s;
    }
    
    .unidad-item:hover {
        transform: translateX(5px);
    }
    
    .unidad-item::before {
        content: counter(unidad-counter);
        position: absolute;
        top: 1.5rem;
        left: 1rem;
        width: 2.5rem;
        height: 2.5rem;
        background: linear-gradient(135deg, var(--global-palette1) 0%, var(--global-palette3) 100%);
        color: var(--global-palette9);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1rem;
        box-shadow: 0 4px 12px rgba(29, 58, 114, 0.3);
    }
    
    .docente-card {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--global-palette7);
        background-color: var(--global-palette9);
        transition: transform 0.3s, box-shadow 0.3s;
        height: 100%;
    }
    
    .docente-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0,0,0,0.1) !important;
    }
    
    .docente-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 4px solid var(--global-palette9);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .docente-avatar-placeholder {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, var(--global-palette1) 0%, var(--global-palette12) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--global-palette9);
        font-size: 2rem;
        font-weight: 900;
        letter-spacing: 2px;
    }
    
    .docente-bio-content {
        max-height: 300px;
        overflow-y: auto;
        padding-right: 10px;
    }
    
    .docente-bio-content::-webkit-scrollbar {
        width: 6px;
    }
    
    .docente-bio-content::-webkit-scrollbar-track {
        background: var(--global-palette7);
        border-radius: 10px;
    }
    
    .docente-bio-content::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, var(--global-palette1) 0%, var(--global-palette2) 100%);
        border-radius: 10px;
    }
    
    .encuentro-item {
        border-left: 4px solid var(--global-palette1);
        background: linear-gradient(135deg, var(--global-palette8) 0%, var(--global-palette9) 100%);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        transition: transform 0.3s, box-shadow 0.3s;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    
    .encuentro-item:hover {
        transform: translateX(5px);
        box-shadow: 0 8px 20px rgba(29, 58, 114, 0.15);
    }
    
    .encuentro-header {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 0;
    }
    
    .encuentro-dia-hora {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        flex: 1;
    }
    
    .encuentro-dia {
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--global-palette1);
        line-height: 1.3;
    }
    
    .encuentro-hora {
        font-size: 1rem;
        font-weight: 700;
        color: var(--global-palette14);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .encuentro-hora span {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .encuentro-hora-timezone {
        font-size: 0.85rem;
        color: var(--global-palette4);
        margin-left: 0;
    }
    
    .encuentro-hora-detalle {
        font-size: 0.95rem;
        color: var(--global-palette4);
        margin-top: 0.3rem;
    }
    
    .encuentro-plataforma {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1rem;
        background-color: var(--global-palette1);
        color: white;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        width: fit-content;
        margin-top: 0.5rem;
    }
    
    @media (min-width: 768px) {
        .encuentro-item {
            padding: 1.75rem 2rem;
        }
        
        .encuentro-header {
            flex-direction: row;
            align-items: center;
            gap: 1.5rem;
            justify-content: space-between;
        }
        
        .encuentro-dia {
            font-size: 1.25rem;
        }
        
        .encuentro-hora {
            font-size: 1.05rem;
        }
        
        .encuentro-plataforma {
            margin-top: 0;
        }
    }
    
    @media (min-width: 992px) {
        .encuentro-dia {
            font-size: 1.3rem;
        }
        
        .encuentro-hora {
            font-size: 1.1rem;
        }
    }
    
    .btn-inscripcion {
        padding: 1rem 2.5rem;
        font-weight: 700;
        border-radius: 50px;
        transition: all 0.3s;
        background-color: var(--global-palette-btn-bg);
        border: none;
        color: var(--global-palette-btn);
        box-shadow: 0 4px 15px rgba(37, 129, 56, 0.3);
        font-family: var(--global-body-font-family, var(--global-fallback-font));
    }
    
    .btn-inscripcion:hover {
        transform: translateY(-3px);
        background-color: var(--global-palette-btn-bg-hover);
        color: var(--global-palette-btn-hover);
        box-shadow: 0 8px 25px rgba(27, 109, 43, 0.4);
    }
    
    .final-cta {
        background: linear-gradient(135deg, var(--global-palette1) 0%, var(--global-palette12) 100%);
        border-radius: 16px;
    }
    
    .final-cta h2 {
        color: var(--global-palette9) !important;
    }
    
    .final-cta p {
        color: var(--global-palette9);
    }
    
    .final-cta .btn-inscripcion {
        background-color: var(--global-palette-btn-bg) !important;
        color: var(--global-palette-btn) !important;
        border: none !important;
    }
    
    .final-cta .btn-inscripcion:hover {
        background-color: var(--global-palette-btn-bg-hover) !important;
        color: var(--global-palette-btn-hover) !important;
    }
    
    .seminario-content {
        line-height: 1.7;
        color: var(--global-palette4);
        font-family: var(--global-body-font-family, var(--global-fallback-font));
    }
    
    .seminario-content ul,
    .seminario-content ol {
        padding-left: 1.5rem;
    }
    
    .seminario-content li {
        margin-bottom: 0.8rem;
    }
    
    h1, h2, h3, h4, h5, h6 {
        font-family: var(--global-heading-font-family, var(--global-fallback-font));
    }
    
    .card-title {
        font-family: var(--global-heading-font-family, var(--global-fallback-font));
    }
    
    /* Grid manual para cards de información */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    @media (min-width: 768px) {
        .info-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (min-width: 992px) {
        .info-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    
    .info-grid > * {
        min-height: 100%;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .section-header h2 {
            font-size: 1.25rem;
        }
        
        .info-card {
            margin-bottom: 1rem;
        }
        
        .unidad-item {
            padding-left: 3.5rem;
        }
        
        .unidad-item::before {
            left: 0.5rem;
            width: 2rem;
            height: 2rem;
            font-size: 0.9rem;
        }
    }
</style>

<main id="main" class="site-main flacso-cpt-seminario-main">
    <div class="flacso-cpt-seminario">
        <div class="entry-content-wrap entry content-bg single-entry post type-post status-publish format-standard has-post-thumbnail hentry">
            <div class="entry-content">
                <div class="content-container site-container">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb seminario-breadcrumb mb-2">
            <li class="breadcrumb-item">
                <a href="<?php echo home_url(); ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item">
                <a href="<?php echo home_url('/formacion'); ?>">Oferta académica (Formación)</a>
            </li>
            <li class="breadcrumb-item">
                <a href="<?php echo get_post_type_archive_link('seminario'); ?>">Seminarios</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">
                <?php echo esc_html(get_the_title()); ?>
            </li>
        </ol>
        
        <!-- Badges: Posgrados (oferta academica asociada) -->
        <?php if (!empty($posgrados)) : ?>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php foreach ($posgrados as $posgrado) : ?>
                    <span class="badge seminario-badge seminario-badge-secondary">
                        <?php echo esc_html($posgrado['title']); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </nav>
    
    <!-- Hero Section -->
    <div class="row align-items-center mb-5">
        <!-- Image Column -->
        <div class="col-lg-6 mb-4 mb-lg-0">
            <?php if (has_post_thumbnail()) : ?>
                <div class="ratio ratio-16x9 rounded-4 overflow-hidden shadow-lg">
                    <?php the_post_thumbnail('large', array('class' => 'img-fluid w-100 h-100 object-fit-cover', 'loading' => 'eager')); ?>
                </div>
            <?php else : ?>
                <div class="ratio ratio-16x9 rounded-4 overflow-hidden shadow-lg seminario-image-placeholder d-flex align-items-center justify-content-center">
                    <span class="display-4">📚</span>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Content Column -->
        <div class="col-lg-6">
            <h1 class="fw-bold mb-4" style="color: var(--global-palette1);">
                <?php echo esc_html(get_the_title()); ?>
            </h1>
            
            <!-- CTA Button -->
            <?php if (!empty($preinscripcion_url)) : ?>
                <div class="mt-4">
                    <a href="<?php echo esc_url($preinscripcion_url); ?>" class="btn btn-inscripcion btn-lg">
                        Inscríbete Ahora <i class="bi bi-arrow-right ms-2"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Información del Seminario -->
    <section class="py-5">
        <div class="section-header">
            <h2 class="fw-bold">Información del Seminario</h2>
        </div>
        
        <div class="info-grid">
            <!-- Fechas -->
            <?php if (!empty($meta['periodo_inicio']) || !empty($meta['periodo_fin'])) : ?>
                <div>
                    <div class="card info-card h-100 shadow-sm">
                        <div class="card-body text-start p-4">
                            <div class="info-card-icon">📅</div>
                            <h5 class="card-title fw-bold mb-3">Período</h5>
                            <?php if (!empty($meta['periodo_inicio'])) : ?>
                                <div class="mb-3">
                                    <p class="mb-0">
                                        <span class="text-muted">Inicio:</span>
                                        <span class="fw-bold ms-2" style="color: var(--global-palette1);"><?php echo esc_html(format_fecha_es($meta['periodo_inicio'])); ?></span>
                                    </p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($meta['periodo_fin'])) : ?>
                                <div class="mb-0">
                                    <p class="mb-0">
                                        <span class="text-muted">Fin:</span>
                                        <span class="fw-bold ms-2" style="color: var(--global-palette1);"><?php echo esc_html(format_fecha_es($meta['periodo_fin'])); ?></span>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Carga Horaria y Créditos -->
            <?php if (!empty($meta['carga_horaria']) || !empty($meta['creditos'])) : ?>
                <div>
                    <div class="card info-card h-100 shadow-sm">
                        <div class="card-body text-start p-4">
                            <div class="info-card-icon">⏱️</div>
                            <h5 class="card-title fw-bold mb-3">Duración y Créditos</h5>
                            <?php if (!empty($meta['carga_horaria'])) : ?>
                                <div class="mb-3">
                                    <p class="mb-0">
                                        <span class="text-muted">Duración:</span>
                                        <span class="fw-bold ms-2" style="color: var(--global-palette1);"><?php echo esc_html($meta['carga_horaria']); ?> horas</span>
                                    </p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($meta['creditos'])) : ?>
                                <div>
                                    <p class="mb-0">
                                        <span class="text-muted">Créditos:</span>
                                        <span class="fw-bold ms-2" style="color: var(--global-palette1);"><?php echo esc_html($meta['creditos']); ?></span>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Modalidad -->
            <?php if (!empty($meta['modalidad'])) : ?>
                <div>
                    <div class="card info-card h-100 shadow-sm">
                        <div class="card-body text-start p-4">
                            <div class="info-card-icon">📹</div>
                            <h5 class="card-title fw-bold mb-3">Modalidad</h5>
                            <p class="mb-0" style="color: var(--global-palette4);"><?php echo wp_kses_post($meta['modalidad']); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Acreditación -->
            <?php if (!empty($meta['acredita_maestria']) || !empty($meta['acredita_doctorado'])) : ?>
                <div>
                    <div class="card info-card h-100 shadow-sm">
                        <div class="card-body text-start p-4">
                            <div class="info-card-icon">🎓</div>
                            <h5 class="card-title fw-bold mb-3">Acreditación</h5>
                            <p class="mb-0" style="font-size: 0.95rem; line-height: 1.5; color: var(--global-palette4);">
                                <?php
                                $acreditacion_text = 'Acreditable a estudios de ';
                                $acreditaciones = array();
                                
                                if (!empty($meta['acredita_maestria'])) {
                                    $acreditaciones[] = 'Maestría';
                                }
                                if (!empty($meta['acredita_doctorado'])) {
                                    $acreditaciones[] = 'Doctorado';
                                }
                                
                                echo $acreditacion_text . implode(' y ', $acreditaciones);
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Forma de Aprobación -->
            <?php if (!empty($meta['forma_aprobacion'])) : ?>
                <div>
                    <div class="card info-card h-100 shadow-sm">
                        <div class="card-body text-start p-4">
                            <div class="info-card-icon">📝</div>
                            <h5 class="card-title fw-bold mb-3">Evaluación</h5>
                            <p class="mb-0" style="font-size: 0.95rem; line-height: 1.5; color: var(--global-palette4);">
                                <?php echo wp_kses_post($meta['forma_aprobacion']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Encuentros sincronicos Card -->
            <?php 
            $encuentros_card = isset($meta['encuentros_sincronicos']) && is_array($meta['encuentros_sincronicos']) ? $meta['encuentros_sincronicos'] : array();
            $encuentros_card_validos = array_filter($encuentros_card, function($e) {
                return !empty($e['fecha']);
            });
            if (!empty($encuentros_card_validos)) : 
            ?>
                <div>
                    <div class="card info-card h-100 shadow-sm">
                        <div class="card-body text-start p-4">
                            <div class="info-card-icon">🎥</div>
                            <h5 class="card-title fw-bold mb-3">Encuentros sincronicos</h5>
                            <div style="font-size: 0.9rem; color: var(--global-palette4);">
                                <?php foreach ($encuentros_card_validos as $index => $encuentro) : 
                                    $fecha = isset($encuentro['fecha']) ? $encuentro['fecha'] : '';
                                    $hora_inicio = isset($encuentro['hora_inicio']) ? $encuentro['hora_inicio'] : '';
                                    $hora_fin = isset($encuentro['hora_fin']) ? $encuentro['hora_fin'] : '';
                                    $plataforma = isset($encuentro['plataforma']) ? $encuentro['plataforma'] : 'zoom';
                                    $plataforma_otro = isset($encuentro['plataforma_otro']) ? $encuentro['plataforma_otro'] : '';
                                    
                                    $plataforma_display = $plataforma === 'otro' ? $plataforma_otro : ucfirst($plataforma);
                                    
                                    $dias_semana = array('Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado');
                                    $meses = array('', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre');
                                    $timestamp = strtotime($fecha);
                                    $dia_semana = $dias_semana[date('w', $timestamp)];
                                    $dia = date('j', $timestamp);
                                    $mes = $meses[date('n', $timestamp)];
                                ?>
                                    <div style="margin-bottom: 0.8rem; padding-bottom: 0.8rem; border-bottom: 1px solid var(--global-palette7);" <?php echo $index === count($encuentros_card_validos) - 1 ? 'style="margin-bottom: 0; padding-bottom: 0; border-bottom: none;"' : ''; ?>>
                                        <div style="font-weight: 700; color: var(--global-palette1); margin-bottom: 0.3rem;">
                                            <?php echo esc_html($dia_semana . ', ' . $dia . ' de ' . $mes); ?>
                                        </div>
                                        <div style="color: var(--global-palette14); font-weight: 600;">
                                            <?php 
                                            if (!empty($hora_inicio)) {
                                                echo esc_html($hora_inicio);
                                            }
                                            if (!empty($hora_fin)) {
                                                echo ' - ' . esc_html($hora_fin);
                                            }
                                            ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--global-palette5);">
                                            <?php echo esc_html($plataforma_display); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Sección detallada de "Encuentros sincrónicos" removida a pedido:
         se conserva solo la tarjeta-resumen en la grilla de información. -->
    
    <!-- Presentación del Seminario -->
    <?php if (!empty($meta['presentacion_seminario'])) : ?>
        <section class="py-5">
            <div class="section-header">
                <h2 class="fw-bold">Presentación del Seminario</h2>
            </div>
            <div class="seminario-content">
                <?php echo wp_kses_post($meta['presentacion_seminario']); ?>
            </div>
        </section>
    <?php endif; ?>
    
    <!-- Objetivo General -->
    <?php if (!empty($meta['objetivo_general'])) : ?>
        <section class="py-5">
            <div class="section-header">
                <h2 class="fw-bold">Objetivo General</h2>
            </div>
            <div class="seminario-content">
                <?php echo wp_kses_post($meta['objetivo_general']); ?>
            </div>
        </section>
    <?php endif; ?>
    
    <!-- Objetivos Específicos -->
    <?php if (!empty($objetivos) && count($objetivos) > 1) : ?>
        <section class="py-5">
            <div class="section-header">
                <h2 class="fw-bold">Objetivos Específicos</h2>
            </div>
            <ul class="seminario-content list-unstyled">
                <?php foreach ($objetivos as $item) : ?>
                    <li class="mb-3">
                        <div class="d-flex">
                            <i class="bi bi-check-circle-fill me-3 mt-1" style="color: var(--global-palette1);"></i>
                            <span><?php echo wp_kses_post($item); ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
    
    <!-- Contenidos / Unidades Académicas -->
    <?php if (!empty($unidades)) : ?>
        <section class="py-5">
            <div class="section-header">
                <h2 class="fw-bold">Contenidos del Seminario</h2>
            </div>
            <div style="counter-reset: unidad-counter;">
                <?php foreach ($unidades as $row) :
                    $titulo = isset($row['titulo']) ? trim($row['titulo']) : '';
                    $contenido = isset($row['contenido']) ? trim($row['contenido']) : '';
                    if (empty($titulo) && empty($contenido)) continue;
                ?>
                    <div class="unidad-item mb-4">
                        <?php if (!empty($titulo)) : ?>
                            <h3 class="h4 fw-bold mb-3" style="color: var(--global-palette1);"><?php echo esc_html($titulo); ?></h3>
                        <?php endif; ?>
                        <?php if (!empty($contenido)) : ?>
                            <div class="seminario-content">
                                <?php echo wp_kses_post($contenido); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    
    <!-- Docentes -->
    <?php if (!empty($docentes_posts)) : ?>
        <section class="py-5">
            <div class="section-header">
                <h2 class="fw-bold">
                    Equipo Docente<?php echo count($docentes_posts) > 1 ? 's' : ''; ?>
                </h2>
            </div>
            
            <?php
                $num_docentes = count($docentes_posts);
                $col_class = 'col-12';
            ?>
            
            <div class="row g-4">
                <?php foreach ($docentes_posts as $docente) : 
                    $doc_id = $docente->ID;
                    $nombre = (string)get_post_meta($doc_id, 'nombre', true);
                    $apellido = (string)get_post_meta($doc_id, 'apellido', true);
                    $nombre_completo = '';
                    
                    if ($nombre && $apellido) {
                        $nombre_completo = trim($nombre . ' ' . $apellido);
                    } else {
                        $nombre_completo = $docente->post_title;
                    }
                    
                    $prefijo_full = (string)get_post_meta($doc_id, 'prefijo_full', true);
                    $cv = (string)get_post_meta($doc_id, 'cv', true);
                    $cv_full = '';
                    
                    if ($cv) {
                        $allowed = [
                            'p' => [], 'br' => [],
                            'ul' => [], 'ol' => [], 'li' => [],
                            'strong' => [], 'em' => [], 'b' => [], 'i' => [],
                            'h3' => [], 'h4' => [], 'h5' => [],
                            'a' => ['href' => [], 'target' => [], 'rel' => []],
                        ];
                        $cv_full = wp_kses(wpautop(trim($cv)), $allowed);
                    }
                ?>
                    <div class="<?php echo $col_class; ?>">
                        <div class="card docente-card h-100 shadow-sm">
                            <div class="card-header bg-transparent border-0 p-4 pb-0">
                                <div class="d-flex align-items-center">
                                    <div class="docente-avatar me-3">
                                        <?php if (has_post_thumbnail($doc_id)) : ?>
                                            <?php echo wp_get_attachment_image(
                                                get_post_thumbnail_id($doc_id),
                                                'medium',
                                                false,
                                                ['class' => 'img-fluid w-100 h-100 rounded-circle object-fit-cover', 'alt' => esc_attr($nombre_completo), 'loading' => 'lazy']
                                            ); ?>
                                        <?php else : ?>
                                            <div class="docente-avatar-placeholder rounded-circle">
                                                <?php
                                                $inic = 'DC';
                                                if ($nombre && $apellido) {
                                                    $inic = mb_substr($nombre, 0, 1) . mb_substr($apellido, 0, 1);
                                                } elseif ($nombre) {
                                                    $inic = mb_substr($nombre, 0, 2);
                                                }
                                                echo esc_html(strtoupper($inic));
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h3 class="h5 fw-bold mb-1" style="color: var(--global-palette1);"><?php echo esc_html($nombre_completo); ?></h3>
                                        <?php if ($prefijo_full) : ?>
                                            <p class="text-muted mb-0" style="font-size: 0.9rem; color: var(--global-palette5) !important;"><?php echo esc_html($prefijo_full); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($cv_full) : ?>
                                <div class="card-body p-4 pt-3">
                                    <div class="docente-bio-content">
                                        <?php echo $cv_full; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    
    <!-- Final CTA -->
    <?php if (!empty($preinscripcion_url)) : ?>
        <section class="py-5">
            <div class="final-cta text-center py-5 px-4 rounded-4 shadow-lg">
                <h2 class="fw-bold mb-3">¿Querés Comenzar?</h2>
                <p class="lead mb-4 opacity-90">
                    Únete a este seminario y forma parte de nuestra comunidad académica
                </p>
                <a href="<?php echo esc_url($preinscripcion_url); ?>" class="btn btn-inscripcion btn-lg px-5 py-3 fw-bold">
                    Inscribirse Ahora <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>
        </section>
    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
endwhile;
?>
<script>
(function() {
    if (typeof window.fbq !== 'function') {
        return;
    }
    try {
        window.fbq('track', 'ViewContent', {
            content_name: <?php echo wp_json_encode(get_the_title($post_id)); ?>,
            content_ids: [<?php echo (int) $post_id; ?>],
            content_type: 'seminario'
        });
    } catch (e) {
        if (window.console && typeof window.console.warn === 'function') {
            console.warn('[Seminario] Error enviando ViewContent:', e);
        }
    }
})();
</script>
<?php

get_footer();
