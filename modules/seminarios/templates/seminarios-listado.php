<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

if (function_exists('flacso_global_styles')) {
    flacso_global_styles();
}

$hero_image_url = 'https://flacso.edu.uy/wp-content/uploads/2026/02/seminarios-artwork.png';
$hoy = new DateTimeImmutable('today', wp_timezone());

$posgrado_filtro = isset($_GET['posgrado']) ? intval($_GET['posgrado']) : 0;
if ($posgrado_filtro <= 0 && isset($_GET['programa'])) {
    $posgrado_filtro = intval($_GET['programa']);
}

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
            $seminarios_por_posgrado[$seminario_id][] = (int) $oferta_id;
        }
    }

    foreach ($seminarios_por_posgrado as $seminario_id => $ofertas_relacionadas) {
        $seminarios_por_posgrado[$seminario_id] = array_values(array_unique(array_map('intval', $ofertas_relacionadas)));
    }
}

$meses_es = array('', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre');
$dias_semana_es = array('Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado');
$fallback_variants = array('a', 'b', 'c', 'd');

$parse_date = static function ($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d|', $value, wp_timezone());
    if (!$date) {
        return null;
    }

    return $date->setTime(0, 0, 0);
};

$format_month = static function (DateTimeImmutable $date) use ($meses_es) {
    return $meses_es[(int) $date->format('n')] . ' ' . $date->format('Y');
};

$format_date_long = static function (DateTimeImmutable $date) use ($meses_es, $dias_semana_es) {
    $dia_semana = $dias_semana_es[(int) $date->format('w')];
    $dia = (int) $date->format('j');
    $mes = $meses_es[(int) $date->format('n')];
    $ano = $date->format('Y');

    return sprintf('%s %d de %s de %s', $dia_semana, $dia, $mes, $ano);
};

$days_diff = static function (DateTimeImmutable $from, DateTimeImmutable $to) {
    return (int) floor(($to->getTimestamp() - $from->getTimestamp()) / DAY_IN_SECONDS);
};

$get_docentes_label = static function ($docentes_meta) {
    if (!is_array($docentes_meta) || empty($docentes_meta)) {
        return 'Docentes a confirmar';
    }

    $docentes_count = count(array_values(array_filter(array_map('intval', $docentes_meta))));
    if ($docentes_count <= 0) {
        return 'Docentes a confirmar';
    }

    return $docentes_count === 1 ? '1 docente' : sprintf('%d docentes', $docentes_count);
};

$get_description = static function ($post_id, $meta) {
    $description = '';

    if (!empty($meta['presentacion_seminario'])) {
        $description = wp_strip_all_tags((string) $meta['presentacion_seminario']);
    }

    if ($description === '' && !empty($meta['objetivo_general'])) {
        $description = wp_strip_all_tags((string) $meta['objetivo_general']);
    }

    if ($description === '') {
        $description = wp_strip_all_tags((string) get_the_excerpt($post_id));
    }

    if ($description === '') {
        $description = wp_strip_all_tags((string) get_post_field('post_content', $post_id));
    }

    if ($description === '') {
        $description = 'Informacion disponible proximamente.';
    }

    return wp_trim_words($description, 34, '...');
};

$get_creditos_value = static function ($value) {
    if ($value === '' || $value === null) {
        return '';
    }

    if (!is_numeric($value)) {
        return '';
    }

    $number = (float) $value;
    if ((float) (int) $number === $number) {
        return (string) (int) $number;
    }

    return rtrim(rtrim(number_format($number, 1, '.', ''), '0'), '.');
};

$seminarios_catalogo = array();

$seminarios_query = new WP_Query(array(
    'post_type'      => 'seminario',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_key'       => '_seminario_periodo_inicio',
    'orderby'        => 'meta_value',
    'order'          => 'ASC',
));

if ($seminarios_query->have_posts()) {
    while ($seminarios_query->have_posts()) {
        $seminarios_query->the_post();

        $seminario_id = get_the_ID();
        $meta = class_exists('Seminario_Meta') ? Seminario_Meta::get_meta($seminario_id) : array();

        $fecha_inicio = isset($meta['periodo_inicio']) ? (string) $meta['periodo_inicio'] : '';
        $fecha_fin = isset($meta['periodo_fin']) ? (string) $meta['periodo_fin'] : '';

        $inicio_obj = $parse_date($fecha_inicio);
        if (!$inicio_obj) {
            continue;
        }

        $fin_obj = $parse_date($fecha_fin);
        if (!$fin_obj) {
            $fin_obj = $inicio_obj;
        }

        $dias_hasta_inicio = $days_diff($hoy, $inicio_obj);
        $dias_hasta_fin = $days_diff($hoy, $fin_obj);

        $is_upcoming = $dias_hasta_inicio >= 0;
        $is_past_recent = $dias_hasta_fin < 0 && $dias_hasta_fin >= -10;
        $is_visible = $is_upcoming || $is_past_recent;

        if (!$is_visible) {
            continue;
        }

        $posgrado_ids = isset($seminarios_por_posgrado[$seminario_id]) ? $seminarios_por_posgrado[$seminario_id] : array();
        if ($posgrado_filtro > 0 && !in_array($posgrado_filtro, $posgrado_ids, true)) {
            continue;
        }

        $title = trim((string) ($meta['nombre'] ?? ''));
        if ($title === '') {
            $title = get_the_title($seminario_id);
        }
        if ($title === '') {
            $title = 'Seminario sin titulo';
        }

        $modalidad = trim(wp_strip_all_tags((string) ($meta['modalidad'] ?? '')));
        if ($modalidad === '') {
            $modalidad = 'No especificado';
        }

        $creditos = $get_creditos_value($meta['creditos'] ?? '');
        $carga_horaria = '';
        if (isset($meta['carga_horaria']) && is_numeric($meta['carga_horaria']) && (int) $meta['carga_horaria'] > 0) {
            $carga_horaria = (string) (int) $meta['carga_horaria'];
        }

        $month_key = $inicio_obj->format('Y-m');
        $month_label = $format_month($inicio_obj);
        $fecha_larga = $format_date_long($inicio_obj);

        $image_url = get_the_post_thumbnail_url($seminario_id, 'large');
        $permalink = get_permalink($seminario_id);

        $seminarios_catalogo[] = array(
            'id'             => $seminario_id,
            'title'          => $title,
            'description'    => $get_description($seminario_id, $meta),
            'month_key'      => $month_key,
            'month_label'    => $month_label,
            'date_long'      => $fecha_larga,
            'days_left'      => $dias_hasta_inicio,
            'days_to_end'    => $dias_hasta_fin,
            'is_upcoming'    => $is_upcoming,
            'is_past_recent' => $is_past_recent,
            'modality'       => $modalidad,
            'credits'        => $creditos,
            'carga_horaria'  => $carga_horaria,
            'docentes_label' => $get_docentes_label($meta['docentes'] ?? array()),
            'image_url'      => $image_url ? $image_url : '',
            'permalink'      => $permalink ? $permalink : '#',
            'timestamp'      => $inicio_obj->getTimestamp(),
            'fallback'       => $fallback_variants[$seminario_id % count($fallback_variants)],
        );
    }
}
wp_reset_postdata();

usort($seminarios_catalogo, static function ($a, $b) {
    if ($a['timestamp'] === $b['timestamp']) {
        return $a['id'] <=> $b['id'];
    }

    return $a['timestamp'] <=> $b['timestamp'];
});

$seminario_destacado = null;
foreach ($seminarios_catalogo as $item) {
    if ($item['is_upcoming']) {
        $seminario_destacado = $item;
        break;
    }
}
if (!$seminario_destacado && !empty($seminarios_catalogo)) {
    $seminario_destacado = $seminarios_catalogo[0];
}

$seminarios_agrupados = array();
foreach ($seminarios_catalogo as $item) {
    $key = $item['month_key'];
    if (!isset($seminarios_agrupados[$key])) {
        $seminarios_agrupados[$key] = array(
            'month_label' => $item['month_label'],
            'items'       => array(),
        );
    }
    $seminarios_agrupados[$key]['items'][] = $item;
}

$modalidades_unicas = array();
foreach ($seminarios_catalogo as $seminario) {
    $modalidades_unicas[$seminario['modality']] = true;
}

$seminarios_total = count($seminarios_catalogo);
$meses_total = count($seminarios_agrupados);
$modalidades_total = count($modalidades_unicas);
?>

<div class="content-area flacso-seminarios-page">
    <main id="main" class="site-main">
        <header class="seminarios-hero" role="banner">
            <?php if (!empty($hero_image_url)) : ?>
                <img
                    class="seminarios-hero__bg"
                    src="<?php echo esc_url($hero_image_url); ?>"
                    alt=""
                    loading="eager"
                    decoding="async"
                    aria-hidden="true"
                >
            <?php endif; ?>

            <div class="seminarios-hero__overlay" aria-hidden="true"></div>
            <div class="seminarios-hero__grid" aria-hidden="true"></div>

            <div class="site-container seminarios-hero__inner">
                <div class="seminarios-hero__content">
                    <p class="seminarios-hero__kicker"><?php esc_html_e('Formacion de posgrado FLACSO', 'flacso-uruguay'); ?></p>
                    <h1 id="seminarios-page-title" class="seminarios-hero__title"><?php esc_html_e('Seminarios | FLACSO Uruguay', 'flacso-uruguay'); ?></h1>
                    <p class="seminarios-hero__subtitle">
                        <?php esc_html_e('Explora la oferta de seminarios y encontra fechas, modalidades, creditos y detalle de cada propuesta.', 'flacso-uruguay'); ?>
                    </p>

                    <div class="seminarios-hero__actions">
                        <a class="seminarios-btn seminarios-btn--primary" href="#seminarios-destacados">
                            <?php esc_html_e('Explorar seminarios', 'flacso-uruguay'); ?>
                        </a>
                        <a class="seminarios-btn seminarios-btn--ghost" href="https://flacso.edu.uy/preguntas-frecuentes/" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Ver preguntas frecuentes', 'flacso-uruguay'); ?>
                        </a>
                    </div>
                </div>

                <div class="seminarios-hero__stats" aria-hidden="true">
                    <article class="seminarios-stat">
                        <span class="seminarios-stat__label"><?php esc_html_e('Seminarios', 'flacso-uruguay'); ?></span>
                        <strong class="seminarios-stat__value"><?php echo esc_html($seminarios_total); ?>+</strong>
                        <span class="seminarios-stat__meta"><?php esc_html_e('disponibles', 'flacso-uruguay'); ?></span>
                    </article>
                    <article class="seminarios-stat">
                        <span class="seminarios-stat__label"><?php esc_html_e('Meses activos', 'flacso-uruguay'); ?></span>
                        <strong class="seminarios-stat__value"><?php echo esc_html($meses_total); ?></strong>
                        <span class="seminarios-stat__meta"><?php esc_html_e('con oferta vigente', 'flacso-uruguay'); ?></span>
                    </article>
                    <article class="seminarios-stat">
                        <span class="seminarios-stat__label"><?php esc_html_e('Modalidades', 'flacso-uruguay'); ?></span>
                        <strong class="seminarios-stat__value"><?php echo esc_html(max(1, $modalidades_total)); ?></strong>
                        <span class="seminarios-stat__meta"><?php esc_html_e('datos en tiempo real', 'flacso-uruguay'); ?></span>
                    </article>
                </div>
            </div>
        </header>

        <section id="seminarios-destacados" class="seminarios-main" aria-labelledby="seminarios-destacados-titulo">
            <div class="site-container">
                <?php if ($seminario_destacado) : ?>
                    <?php
                    $featured_badge = 'Fecha a confirmar';
                    if ($seminario_destacado['is_upcoming']) {
                        $featured_badge = $seminario_destacado['days_left'] === 0
                            ? 'Comienza hoy'
                            : sprintf('Faltan %d dias', $seminario_destacado['days_left']);
                    } elseif ($seminario_destacado['is_past_recent']) {
                        $featured_badge = sprintf('Finalizo hace %d dias', abs($seminario_destacado['days_to_end']));
                    }
                    ?>
                    <article class="seminarios-featured" aria-labelledby="seminario-destacado-titulo">
                        <a class="seminarios-featured__link" href="<?php echo esc_url($seminario_destacado['permalink']); ?>">
                            <div class="seminarios-featured__media">
                                <?php if (!empty($seminario_destacado['image_url'])) : ?>
                                    <img
                                        src="<?php echo esc_url($seminario_destacado['image_url']); ?>"
                                        alt="<?php echo esc_attr($seminario_destacado['title']); ?>"
                                        class="seminarios-featured__image"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                <?php else : ?>
                                    <div class="seminarios-featured__fallback seminarios-featured__fallback--<?php echo esc_attr($seminario_destacado['fallback']); ?>" aria-hidden="true">
                                        <span>S</span>
                                    </div>
                                <?php endif; ?>

                                <div class="seminarios-featured__media-overlay" aria-hidden="true"></div>

                                <div class="seminarios-featured__chips" aria-hidden="true">
                                    <span class="seminarios-chip seminarios-chip--light"><?php esc_html_e('Proximo destacado', 'flacso-uruguay'); ?></span>
                                    <span class="seminarios-chip seminarios-chip--warning"><?php echo esc_html($featured_badge); ?></span>
                                </div>

                                <div class="seminarios-featured__heading">
                                    <span class="seminarios-featured__kicker"><?php esc_html_e('Seminarios FLACSO', 'flacso-uruguay'); ?></span>
                                    <h2 id="seminario-destacado-titulo"><?php echo esc_html($seminario_destacado['title']); ?></h2>
                                </div>
                            </div>

                            <div class="seminarios-featured__content">
                                <p class="seminarios-featured__description"><?php echo esc_html($seminario_destacado['description']); ?></p>

                                <ul class="seminarios-featured__meta" role="list">
                                    <li>
                                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                                        <span><?php echo esc_html($seminario_destacado['date_long']); ?></span>
                                    </li>
                                    <li>
                                        <i class="bi bi-laptop" aria-hidden="true"></i>
                                        <span><?php echo esc_html($seminario_destacado['modality']); ?></span>
                                    </li>
                                    <li>
                                        <i class="bi bi-people" aria-hidden="true"></i>
                                        <span><?php echo esc_html($seminario_destacado['docentes_label']); ?></span>
                                    </li>
                                    <?php if (!empty($seminario_destacado['credits'])) : ?>
                                        <li>
                                            <i class="bi bi-award" aria-hidden="true"></i>
                                            <span><?php echo esc_html($seminario_destacado['credits'] . ' creditos'); ?></span>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (!empty($seminario_destacado['carga_horaria'])) : ?>
                                        <li>
                                            <i class="bi bi-clock" aria-hidden="true"></i>
                                            <span><?php echo esc_html($seminario_destacado['carga_horaria'] . ' horas'); ?></span>
                                        </li>
                                    <?php endif; ?>
                                </ul>

                                <div class="seminarios-featured__footer">
                                    <span class="seminarios-featured__cta-text"><?php esc_html_e('Ver detalle del seminario', 'flacso-uruguay'); ?></span>
                                    <span class="seminarios-featured__cta-icon" aria-hidden="true">
                                        <i class="bi bi-arrow-right"></i>
                                    </span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endif; ?>

                <div class="seminarios-listado" aria-labelledby="seminarios-destacados-titulo">
                    <header class="seminarios-listado__header">
                        <div class="seminarios-listado__heading">
                            <h2 id="seminarios-destacados-titulo"><?php esc_html_e('Seminarios por mes', 'flacso-uruguay'); ?></h2>
                            <p><?php esc_html_e('Visualiza rapidamente fechas, modalidad y docentes de cada propuesta vigente.', 'flacso-uruguay'); ?></p>
                        </div>
                        <span class="seminarios-listado__counter">
                            <?php
                            echo esc_html(
                                sprintf(
                                    _n('%d seminario', '%d seminarios', $seminarios_total, 'flacso-uruguay'),
                                    $seminarios_total
                                )
                            );
                            ?>
                        </span>
                    </header>

                    <?php if (!empty($seminarios_agrupados)) : ?>
                        <div class="seminarios-listado__months">
                            <?php foreach ($seminarios_agrupados as $grupo) : ?>
                                <section class="seminarios-month" aria-label="<?php echo esc_attr($grupo['month_label']); ?>">
                                    <div class="seminarios-month__title-wrap">
                                        <span class="seminarios-month__dot" aria-hidden="true"></span>
                                        <h3 class="seminarios-month__title"><?php echo esc_html($grupo['month_label']); ?></h3>
                                    </div>

                                    <div class="seminarios-grid">
                                        <?php foreach ($grupo['items'] as $seminario_item) : ?>
                                            <article class="seminario-card<?php echo $seminario_item['is_past_recent'] ? ' is-recent' : ''; ?>">
                                                <a class="seminario-card__media-link" href="<?php echo esc_url($seminario_item['permalink']); ?>">
                                                    <?php if (!empty($seminario_item['image_url'])) : ?>
                                                        <img
                                                            src="<?php echo esc_url($seminario_item['image_url']); ?>"
                                                            alt="<?php echo esc_attr($seminario_item['title']); ?>"
                                                            class="seminario-card__image"
                                                            loading="lazy"
                                                            decoding="async"
                                                        >
                                                    <?php else : ?>
                                                        <div class="seminario-card__fallback seminario-card__fallback--<?php echo esc_attr($seminario_item['fallback']); ?>" aria-hidden="true">
                                                            <span>S</span>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="seminario-card__overlay" aria-hidden="true"></div>

                                                    <div class="seminario-card__badges" aria-hidden="true">
                                                        <span class="seminarios-chip seminarios-chip--light"><?php echo esc_html($seminario_item['month_label']); ?></span>
                                                        <?php if ($seminario_item['is_past_recent']) : ?>
                                                            <span class="seminarios-chip seminarios-chip--dark"><?php esc_html_e('Finalizado recientemente', 'flacso-uruguay'); ?></span>
                                                        <?php elseif ($seminario_item['is_upcoming']) : ?>
                                                            <span class="seminarios-chip seminarios-chip--sky"><?php esc_html_e('Proximamente', 'flacso-uruguay'); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </a>

                                                <div class="seminario-card__content">
                                                    <h4 class="seminario-card__title">
                                                        <a href="<?php echo esc_url($seminario_item['permalink']); ?>"><?php echo esc_html($seminario_item['title']); ?></a>
                                                    </h4>

                                                    <p class="seminario-card__description"><?php echo esc_html($seminario_item['description']); ?></p>

                                                    <ul class="seminario-card__meta" role="list">
                                                        <li>
                                                            <i class="bi bi-calendar3" aria-hidden="true"></i>
                                                            <span><?php echo esc_html($seminario_item['date_long']); ?></span>
                                                        </li>
                                                        <?php if ($seminario_item['is_upcoming']) : ?>
                                                            <li>
                                                                <i class="bi bi-clock" aria-hidden="true"></i>
                                                                <span>
                                                                    <?php
                                                                    echo esc_html(
                                                                        $seminario_item['days_left'] === 0
                                                                            ? __('Comienza hoy', 'flacso-uruguay')
                                                                            : sprintf(__('Faltan %d dias', 'flacso-uruguay'), $seminario_item['days_left'])
                                                                    );
                                                                    ?>
                                                                </span>
                                                            </li>
                                                        <?php elseif ($seminario_item['is_past_recent']) : ?>
                                                            <li>
                                                                <i class="bi bi-clock" aria-hidden="true"></i>
                                                                <span><?php echo esc_html(sprintf(__('Finalizo hace %d dias', 'flacso-uruguay'), abs($seminario_item['days_to_end']))); ?></span>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <i class="bi bi-laptop" aria-hidden="true"></i>
                                                            <span><?php echo esc_html($seminario_item['modality']); ?></span>
                                                        </li>
                                                        <li>
                                                            <i class="bi bi-people" aria-hidden="true"></i>
                                                            <span><?php echo esc_html($seminario_item['docentes_label']); ?></span>
                                                        </li>
                                                    </ul>

                                                    <div class="seminario-card__footer">
                                                        <span class="seminario-card__credits">
                                                            <?php
                                                            if (!empty($seminario_item['credits'])) {
                                                                echo esc_html($seminario_item['credits'] . ' creditos');
                                                            } else {
                                                                esc_html_e('Creditos a confirmar', 'flacso-uruguay');
                                                            }
                                                            ?>
                                                        </span>
                                                        <a class="seminario-card__cta" href="<?php echo esc_url($seminario_item['permalink']); ?>">
                                                            <?php esc_html_e('Ver detalle', 'flacso-uruguay'); ?>
                                                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div class="seminarios-empty" role="status">
                            <span class="seminarios-empty__icon" aria-hidden="true"><i class="bi bi-search"></i></span>
                            <h3><?php esc_html_e('No hay seminarios disponibles', 'flacso-uruguay'); ?></h3>
                            <p>
                                <?php if ($posgrado_filtro > 0) : ?>
                                    <?php esc_html_e('No se encontraron seminarios para el posgrado seleccionado.', 'flacso-uruguay'); ?>
                                <?php else : ?>
                                    <?php esc_html_e('No se encontraron seminarios para mostrar en este momento.', 'flacso-uruguay'); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="seminarios-contacto" aria-labelledby="seminarios-contacto-titulo">
            <div class="site-container">
                <div class="seminarios-contacto__panel">
                    <div class="seminarios-contacto__text">
                        <p class="seminarios-contacto__kicker"><?php esc_html_e('Contacto', 'flacso-uruguay'); ?></p>
                        <h2 id="seminarios-contacto-titulo"><?php esc_html_e('¿Necesitás orientación para elegir un seminario?', 'flacso-uruguay'); ?></h2>
                        <p><?php esc_html_e('Nuestro equipo responde consultas sobre contenidos, fechas, modalidad e inscripción.', 'flacso-uruguay'); ?></p>
                    </div>
                    <div class="seminarios-contacto__actions">
                        <a class="seminarios-btn seminarios-btn--primary" href="mailto:inscripciones@flacso.edu.uy">
                            <?php esc_html_e('Escribínos a inscripciones@flacso.edu.uy', 'flacso-uruguay'); ?>
                        </a>
                        <a class="seminarios-btn seminarios-btn--ghost" href="https://flacso.edu.uy/preguntas-frecuentes/" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Ver preguntas frecuentes', 'flacso-uruguay'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<?php
get_footer();
