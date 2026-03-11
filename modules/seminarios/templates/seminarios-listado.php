<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

if (function_exists('flacso_global_styles')) {
    flacso_global_styles();
}

// Hero fijo para la página de seminarios.
$hero_image_url = 'https://flacso.edu.uy/wp-content/uploads/2026/02/seminarios-artwork.png';

$posgrado_filtro = isset($_GET['posgrado']) ? intval($_GET['posgrado']) : 0;
if ($posgrado_filtro <= 0 && isset($_GET['programa'])) {
    $posgrado_filtro = intval($_GET['programa']);
}

// Mapa: seminario_id => [oferta-academica IDs] usando relacion Oferta Academica -> Seminarios.
$seminarios_por_posgrado = array();

if (post_type_exists('oferta-academica')) {
    $ofertas_ids = get_posts(array(
        'post_type'      => 'oferta-academica',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'     => '_oferta_seminarios_ids',
                'compare' => 'EXISTS',
            ),
        ),
    ));

    foreach ($ofertas_ids as $oferta_id) {
        $seminarios_ids = get_post_meta($oferta_id, '_oferta_seminarios_ids', true);
        if (!is_array($seminarios_ids) || empty($seminarios_ids)) {
            continue;
        }

        foreach ($seminarios_ids as $seminario_id) {
            $seminario_id = (int) $seminario_id;
            if ($seminario_id <= 0) {
                continue;
            }
            if (!isset($seminarios_por_posgrado[$seminario_id])) {
                $seminarios_por_posgrado[$seminario_id] = array();
            }
            $seminarios_por_posgrado[$seminario_id] = array_values(array_unique(array_merge(
                $seminarios_por_posgrado[$seminario_id],
                array((int) $oferta_id)
            )));
        }
    }
}
?>

<div class="content-area flacso-seminarios-page">
    <main id="main" class="site-main">
        <header class="hero" role="banner">
            <?php if (!empty($hero_image_url)) : ?>
                <img
                    class="hero__bg"
                    src="<?php echo esc_url($hero_image_url); ?>"
                    alt=""
                    loading="eager"
                    decoding="async"
                    aria-hidden="true"
                >
            <?php endif; ?>

            <div class="hero__overlay" aria-hidden="true"></div>

            <div class="site-container hero__inner">
                <p class="hero__kicker"><?php esc_html_e('Formacion de posgrado FLACSO', 'flacso-uruguay'); ?></p>
                <h1 id="seminarios-page-title" class="hero__title"><?php esc_html_e('Seminarios FLACSO', 'flacso-uruguay'); ?></h1>
                <p class="hero__subtitle">
                    <?php esc_html_e('Formacion intensiva, flexible y especializada en temas clave de las ciencias sociales.', 'flacso-uruguay'); ?>
                </p>
            </div>
        </header>

        <section class="descripcion" aria-labelledby="descripcion-seminarios-titulo">
            <div class="site-container">
                <div class="descripcion__panel">
                    <h2 id="descripcion-seminarios-titulo" class="screen-reader-text"><?php esc_html_e('Descripcion de los seminarios', 'flacso-uruguay'); ?></h2>
                    <div class="descripcion__text">
                        <p>
                            <?php esc_html_e('Los seminarios de FLACSO son materias independientes que ofrecen formacion especializada en diferentes areas de las ciencias sociales. Estan diseniados para brindar una experiencia intensiva y enfocada en un tema especifico, permitiendo a las personas participantes profundizar en sus conocimientos.', 'flacso-uruguay'); ?>
                        </p>
                        <p>
                            <?php esc_html_e('Podes cursar un seminario de forma independiente, mas alla de estar o no inscripto/a en una maestria o diploma. La estructura y la duracion estan pensadas para compatibilizar estudio y trabajo.', 'flacso-uruguay'); ?>
                        </p>
                        <p>
                            <?php esc_html_e('Los seminarios abarcan areas como teoria politica, estudios de genero y culturales, comunicacion, educacion, infancia y adolescencia, salud mental y otros campos de las ciencias sociales, y son impartidos por equipos docentes con amplia experiencia.', 'flacso-uruguay'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="listado" aria-labelledby="proximos-seminarios-titulo">
            <div class="site-container">
                <div class="listado__wrapper">
                    <header class="listado__header">
                        <h2 id="proximos-seminarios-titulo" class="listado__title"><?php esc_html_e('Proximos seminarios', 'flacso-uruguay'); ?></h2>
                        <p class="listado__subtitle"><?php esc_html_e('Consulta el detalle de fechas, modalidades y requisitos de inscripcion.', 'flacso-uruguay'); ?></p>
                    </header>

                    <div class="listado__content">
                        <?php
                        $seminarios_agrupados = array();
                        $hoy = new DateTime('today');

                        $args = array(
                            'post_type'      => 'seminario',
                            'posts_per_page' => -1,
                            'meta_key'       => '_seminario_periodo_inicio',
                            'orderby'        => 'meta_value',
                            'order'          => 'ASC',
                            'post_status'    => 'publish',
                        );

                        $seminarios_query = new WP_Query($args);

                        if ($seminarios_query->have_posts()) {
                            while ($seminarios_query->have_posts()) {
                                $seminarios_query->the_post();

                                $seminario_id = get_the_ID();
                                $meta = class_exists('Seminario_Meta') ? Seminario_Meta::get_meta($seminario_id) : array();
                                $fecha_inicio = isset($meta['periodo_inicio']) ? $meta['periodo_inicio'] : '';

                                if (empty($fecha_inicio)) {
                                    continue;
                                }

                                $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_inicio);

                                if (!$fecha_obj) {
                                    continue;
                                }

                                $fecha_obj->setTime(0, 0, 0);
                                $fecha_limite = clone $fecha_obj;
                                $fecha_limite->add(new DateInterval('P10D'));

                                if ($fecha_limite < $hoy) {
                                    continue;
                                }

                                $posgrado_ids = isset($seminarios_por_posgrado[$seminario_id]) ? $seminarios_por_posgrado[$seminario_id] : array();

                                if ($posgrado_filtro > 0 && !in_array($posgrado_filtro, $posgrado_ids, true)) {
                                    continue;
                                }

                                $clave_mes = $fecha_obj->format('Y-m');

                                if (!isset($seminarios_agrupados[$clave_mes])) {
                                    $meses_espanol = array('', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre');
                                    $mes_nombre = $meses_espanol[(int) $fecha_obj->format('n')] . ' ' . $fecha_obj->format('Y');

                                    $seminarios_agrupados[$clave_mes] = array(
                                        'mes_nombre' => $mes_nombre,
                                        'seminarios' => array(),
                                    );
                                }

                                $seminarios_agrupados[$clave_mes]['seminarios'][] = array(
                                    'post'      => get_post(),
                                    'meta'      => $meta,
                                    'fecha_obj' => $fecha_obj,
                                );
                            }
                            wp_reset_postdata();
                        }

                        if (!empty($seminarios_agrupados)) {
                            $dias_semana = array('Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado');
                            $meses = array('', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre');

                            foreach ($seminarios_agrupados as $grupo_mes) {
                                ?>
                                <section class="listado__month" aria-label="<?php echo esc_attr($grupo_mes['mes_nombre']); ?>">
                                    <h3 class="listado__month-title"><?php echo esc_html($grupo_mes['mes_nombre']); ?></h3>

                                    <div class="listado__grid">
                                        <?php foreach ($grupo_mes['seminarios'] as $item) : ?>
                                            <?php
                                            $seminario_id = $item['post']->ID;
                                            $meta = $item['meta'];
                                            $fecha_obj = $item['fecha_obj'];

                                            $dias_faltantes = (int) $hoy->diff($fecha_obj)->format('%a');
                                            $es_futuro = $fecha_obj > $hoy;

                                            $dia_semana = $dias_semana[(int) $fecha_obj->format('w')];
                                            $dia = $fecha_obj->format('j');
                                            $mes = $meses[(int) $fecha_obj->format('n')];
                                            $ano = $fecha_obj->format('Y');
                                            $fecha_display = $dia_semana . ' ' . $dia . ' de ' . $mes . ' ' . $ano;

                                            $modalidad = isset($meta['modalidad']) ? $meta['modalidad'] : '';
                                            $creditos = isset($meta['creditos']) ? $meta['creditos'] : '';
                                            ?>
                                            <article class="seminario-card">
                                                <a class="seminario-card__media-link" href="<?php echo esc_url(get_permalink($seminario_id)); ?>">
                                                    <?php if (has_post_thumbnail($seminario_id)) : ?>
                                                        <?php
                                                        echo get_the_post_thumbnail($seminario_id, 'medium_large', array(
                                                            'class'   => 'seminario-card__image',
                                                            'loading' => 'lazy',
                                                            'alt'     => esc_attr(get_the_title($seminario_id)),
                                                        ));
                                                        ?>
                                                    <?php else : ?>
                                                        <div class="seminario-card__fallback" aria-hidden="true">
                                                            <span>S</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </a>

                                                <div class="seminario-card__body">
                                                    <h4 class="seminario-card__title">
                                                        <a href="<?php echo esc_url(get_permalink($seminario_id)); ?>"><?php echo esc_html(get_the_title($seminario_id)); ?></a>
                                                    </h4>

                                                    <ul class="seminario-card__meta" role="list">
                                                        <li>
                                                            <i class="bi bi-calendar3" aria-hidden="true"></i>
                                                            <span><?php echo esc_html($fecha_display); ?></span>
                                                        </li>

                                                        <?php if ($es_futuro) : ?>
                                                            <li>
                                                                <i class="bi bi-clock" aria-hidden="true"></i>
                                                                <span><?php echo esc_html(sprintf(__('Faltan %d dias', 'flacso-uruguay'), $dias_faltantes)); ?></span>
                                                            </li>
                                                        <?php endif; ?>

                                                        <?php if (!empty($modalidad)) : ?>
                                                            <li>
                                                                <i class="bi bi-laptop" aria-hidden="true"></i>
                                                                <span><?php echo wp_kses_post($modalidad); ?></span>
                                                            </li>
                                                        <?php endif; ?>

                                                        <?php if (!empty($creditos)) : ?>
                                                            <li>
                                                                <i class="bi bi-award" aria-hidden="true"></i>
                                                                <span><?php echo esc_html(sprintf(__('Creditos: %s', 'flacso-uruguay'), $creditos)); ?></span>
                                                            </li>
                                                        <?php endif; ?>
                                                    </ul>

                                                    <div class="seminario-card__footer">
                                                        <span class="seminario-card__cta">
                                                            <?php esc_html_e('Ver detalles', 'flacso-uruguay'); ?>
                                                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                                <?php
                            }
                        } else {
                            ?>
                            <div class="estado-vacio" role="status">
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                                <p>
                                    <?php if ($posgrado_filtro > 0) : ?>
                                        <?php esc_html_e('No hay seminarios disponibles para este posgrado en este momento.', 'flacso-uruguay'); ?>
                                    <?php else : ?>
                                        <?php esc_html_e('No hay seminarios disponibles en este momento.', 'flacso-uruguay'); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="contacto" aria-labelledby="faq-seminarios-titulo">
            <div class="site-container">
                <div class="contacto__panel">
                    <h2 id="faq-seminarios-titulo" class="contacto__title"><?php esc_html_e('Contacta con nosotros', 'flacso-uruguay'); ?></h2>
                    <p class="contacto__text"><?php esc_html_e('Estamos disponibles para responder tus preguntas sobre contenidos, fechas y modalidades.', 'flacso-uruguay'); ?></p>
                    <div class="contacto__actions">
                        <a href="mailto:inscripciones@flacso.edu.uy" class="contacto__btn contacto__btn--primary">
                            <i class="bi bi-envelope" aria-hidden="true"></i>
                            <span><?php esc_html_e('Escribinos a inscripciones@flacso.edu.uy', 'flacso-uruguay'); ?></span>
                        </a>
                        <a
                            href="https://flacso.edu.uy/preguntas-frecuentes/"
                            class="contacto__btn contacto__btn--ghost"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <i class="bi bi-question-circle" aria-hidden="true"></i>
                            <span><?php esc_html_e('Ver preguntas frecuentes', 'flacso-uruguay'); ?></span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<?php
get_footer();
