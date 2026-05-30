<?php
/**
 * Template para mostrar los seminarios asociados a una oferta académica.
 * Sigue el formato visual de la página general de seminarios.
 */

if (!defined('ABSPATH')) {
    exit;
}

$oferta_id = get_the_ID();

// Resiliencia extrema para obtener el ID de la oferta
if (get_post_type($oferta_id) !== 'oferta-academica') {
    $associated_ofertas = get_posts([
        'post_type' => 'oferta-academica',
        'meta_key' => '_oferta_page_id',
        'meta_value' => $oferta_id,
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);
    if (!empty($associated_ofertas)) {
        $oferta_id = $associated_ofertas[0];
    }
}

$seminarios_raw = [];
if (class_exists('Oferta_Seminarios_Integration')) {
    $seminarios_raw = Oferta_Seminarios_Integration::get_programa_seminarios_data($oferta_id);
}

// Procesar seminarios para el formato de catálogo
$hoy = new DateTimeImmutable('today', wp_timezone());
$seminarios_catalogo = [];
$fallback_variants = ['a', 'b', 'c', 'd'];

foreach ($seminarios_raw as $raw) {
    $seminario_id = $raw['id'];
    $inicio_obj = null;
    if (!empty($raw['fecha_inicio'])) {
        $inicio_obj = DateTimeImmutable::createFromFormat('Y-m-d|', $raw['fecha_inicio'], wp_timezone());
    }

    if (!$inicio_obj) continue;

    $dias_hasta_inicio = (int) floor(($inicio_obj->getTimestamp() - $hoy->getTimestamp()) / DAY_IN_SECONDS);
    $dias_desde_inicio = $dias_hasta_inicio < 0 ? abs($dias_hasta_inicio) : 0;

    $is_upcoming = $dias_hasta_inicio >= 0;
    $is_started_recent = $dias_hasta_inicio < 0 && $dias_hasta_inicio >= -7;

    // Solo mostrar vigentes o iniciados recientemente
    if (!$is_upcoming && !$is_started_recent) continue;

    $meta = class_exists('Seminario_Meta') ? Seminario_Meta::get_meta($seminario_id) : [];
    
    // Formatear fecha larga
    $meses_es = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $dias_semana_es = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $fecha_larga = sprintf('%s %d de %s de %s', 
        $dias_semana_es[(int) $inicio_obj->format('w')],
        (int) $inicio_obj->format('j'),
        $meses_es[(int) $inicio_obj->format('n')],
        $inicio_obj->format('Y')
    );

    $seminarios_catalogo[] = [
        'id'                => $seminario_id,
        'title'             => $raw['titulo'],
        'description'       => wp_trim_words($raw['excerpt'] ? $raw['excerpt'] : $raw['contenido'], 30),
        'date_long'         => $fecha_larga,
        'days_left'         => $dias_hasta_inicio,
        'days_since_start'  => $dias_desde_inicio,
        'is_upcoming'       => $is_upcoming,
        'is_started_recent' => $is_started_recent,
        'modality'          => $raw['modalidad'] ? $raw['modalidad'] : 'No especificado',
        'credits'           => get_post_meta($seminario_id, '_seminario_creditos', true),
        'docentes_label'    => 'Docentes a confirmar', // Simplificado o extraer de meta
        'image_url'         => $raw['thumbnail'] ? wp_get_attachment_image_url($raw['thumbnail'], 'large') : '',
        'permalink'         => $raw['permalink'],
        'fallback'          => $fallback_variants[$seminario_id % count($fallback_variants)],
        'timestamp'         => $inicio_obj->getTimestamp(),
    ];
}

// Ordenar por fecha
usort($seminarios_catalogo, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

get_header();

if (function_exists('flacso_global_styles')) {
    flacso_global_styles();
}
?>

<div class="content-area flacso-seminarios-page">
    <main id="main" class="site-main">
        <header class="seminarios-hero" role="banner">
            <div class="seminarios-hero__overlay" aria-hidden="true"></div>
            <div class="seminarios-hero__grid" aria-hidden="true"></div>

            <div class="site-container seminarios-hero__inner">
                <div class="seminarios-hero__content">
                    <p class="seminarios-hero__kicker"><?php _e('Seminarios Exclusivos', 'flacso-uruguay'); ?></p>
                    <h1 class="seminarios-hero__title" style="font-size: 2.2rem; line-height: 1.3; margin-bottom: 1rem;"><?php echo esc_html(get_the_title($oferta_id)); ?></h1>
                    
                    <div style="background: rgba(255, 255, 255, 0.1); padding: 1.25rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(255,255,255,0.2);">
                        <p class="seminarios-hero__subtitle" style="margin-bottom: 0; font-size: 1.05rem;">
                            <i class="bi bi-info-circle me-2" style="opacity: 0.9;"></i>
                            <?php _e('Estás viendo los seminarios <strong>específicos de esta propuesta académica</strong>. Si buscás la oferta abierta al público, podés visitar el catálogo general.', 'flacso-uruguay'); ?>
                        </p>
                    </div>

                    <?php 
                    $volver_url = get_permalink($oferta_id);
                    if (class_exists('Oferta_Page_Adapter')) {
                        $associated_page_id = Oferta_Page_Adapter::get_page_id($oferta_id);
                        if ($associated_page_id) {
                            $volver_url = get_permalink($associated_page_id);
                        }
                    }
                    ?>
                    <div class="seminarios-hero__actions d-flex flex-wrap gap-3">
                        <a class="seminarios-btn seminarios-btn--primary" href="#listado">
                            <i class="bi bi-card-list me-2"></i> <?php _e('Ver seminarios del programa', 'flacso-uruguay'); ?>
                        </a>
                        <a class="seminarios-btn seminarios-btn--ghost" href="<?php echo esc_url(home_url('/formacion/seminarios/')); ?>" style="background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.3);">
                            <i class="bi bi-grid me-2"></i> <?php _e('Ver todos los seminarios', 'flacso-uruguay'); ?>
                        </a>
                        <a class="seminarios-btn seminarios-btn--ghost text-white-50" href="<?php echo esc_url($volver_url); ?>" style="border: none; padding-left: 0;">
                            <i class="bi bi-arrow-left me-2"></i> <?php _e('Volver al programa', 'flacso-uruguay'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <section id="listado" class="seminarios-main py-5">
            <div class="site-container">
                <?php if (!empty($seminarios_catalogo)) : ?>
                    <div class="seminarios-listado">
                        <header class="seminarios-listado__header mb-4">
                            <div class="seminarios-listado__heading">
                                <h2><?php _e('Propuestas Disponibles', 'flacso-uruguay'); ?></h2>
                                <p><?php echo sprintf(__('Actualmente hay %d seminarios vigentes para este programa.', 'flacso-uruguay'), count($seminarios_catalogo)); ?></p>
                            </div>
                        </header>

                        <div class="seminarios-grid">
                            <?php foreach ($seminarios_catalogo as $item) : ?>
                                <article class="seminario-card<?php echo $item['is_started_recent'] ? ' is-recent' : ''; ?>">
                                    <a class="seminario-card__media-link" href="<?php echo esc_url($item['permalink']); ?>">
                                        <?php if (!empty($item['image_url'])) : ?>
                                            <img src="<?php echo esc_url($item['image_url']); ?>" alt="<?php echo esc_attr($item['title']); ?>" class="seminario-card__image">
                                        <?php else : ?>
                                            <div class="seminario-card__fallback seminario-card__fallback--<?php echo esc_attr($item['fallback']); ?>" aria-hidden="true">
                                                <span>S</span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="seminario-card__overlay" aria-hidden="true"></div>

                                        <div class="seminario-card__badges" aria-hidden="true">
                                            <?php if ($item['is_started_recent']) : ?>
                                                <span class="seminarios-chip seminarios-chip--dark"><?php _e('Iniciado recientemente', 'flacso-uruguay'); ?></span>
                                            <?php elseif ($item['is_upcoming']) : ?>
                                                <span class="seminarios-chip seminarios-chip--sky"><?php _e('Próximamente', 'flacso-uruguay'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </a>

                                    <div class="seminario-card__content">
                                        <h4 class="seminario-card__title">
                                            <a href="<?php echo esc_url($item['permalink']); ?>"><?php echo esc_html($item['title']); ?></a>
                                        </h4>

                                        <p class="seminario-card__description"><?php echo esc_html($item['description']); ?></p>

                                        <ul class="seminario-card__meta" role="list">
                                            <li>
                                                <i class="bi bi-calendar3" aria-hidden="true"></i>
                                                <span><?php echo esc_html($item['date_long']); ?></span>
                                            </li>
                                            <?php if ($item['is_upcoming']) : ?>
                                                <li>
                                                    <i class="bi bi-clock" aria-hidden="true"></i>
                                                    <span>
                                                        <?php
                                                        echo $item['days_left'] === 0
                                                            ? __('Comienza hoy', 'flacso-uruguay')
                                                            : sprintf(__('Faltan %d días', 'flacso-uruguay'), $item['days_left']);
                                                        ?>
                                                    </span>
                                                </li>
                                            <?php endif; ?>
                                            <li>
                                                <i class="bi bi-laptop" aria-hidden="true"></i>
                                                <span><?php echo esc_html($item['modality']); ?></span>
                                            </li>
                                        </ul>

                                        <div class="seminario-card__footer">
                                            <span class="seminario-card__credits">
                                                <?php
                                                if (!empty($item['credits'])) {
                                                    echo esc_html($item['credits'] . ' créditos');
                                                } else {
                                                    _e('Créditos a confirmar', 'flacso-uruguay');
                                                }
                                                ?>
                                            </span>
                                            <a class="seminario-card__cta" href="<?php echo esc_url($item['permalink']); ?>">
                                                <?php _e('Ver detalle', 'flacso-uruguay'); ?>
                                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="seminarios-empty py-5" role="status">
                        <span class="seminarios-empty__icon" aria-hidden="true"><i class="bi bi-search"></i></span>
                        <h3><?php _e('No hay seminarios disponibles', 'flacso-uruguay'); ?></h3>
                        <p><?php _e('No se encontraron seminarios vigentes para este programa en este momento. Te invitamos a consultar más adelante o contactarnos directamente.', 'flacso-uruguay'); ?></p>
                        <div class="mt-4">
                            <a href="<?php echo esc_url($volver_url); ?>" class="btn btn-primary">
                                <?php _e('Volver al programa', 'flacso-uruguay'); ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="seminarios-contacto mb-5" aria-labelledby="seminarios-contacto-titulo">
            <div class="site-container">
                <div class="seminarios-contacto__panel">
                    <div class="seminarios-contacto__text">
                        <p class="seminarios-contacto__kicker"><?php _e('Consultas', 'flacso-uruguay'); ?></p>
                        <h2 id="seminarios-contacto-titulo"><?php _e('¿Deseas inscribirte o tienes dudas?', 'flacso-uruguay'); ?></h2>
                        <p><?php _e('Nuestro equipo responde consultas sobre contenidos, fechas, modalidad e inscripción específica para estos seminarios.', 'flacso-uruguay'); ?></p>
                    </div>
                    <div class="seminarios-contacto__actions">
                        <a class="seminarios-btn seminarios-btn--primary" href="mailto:inscripciones@flacso.edu.uy">
                            <?php _e('Escribínos a inscripciones@flacso.edu.uy', 'flacso-uruguay'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<?php
get_footer();
