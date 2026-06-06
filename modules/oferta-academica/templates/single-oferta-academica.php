<?php
/**
 * Template responsive para la página individual de una Oferta Académica.
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = get_the_ID();

$data = class_exists('Oferta_Data_Schema')
    ? Oferta_Data_Schema::get_schema($post_id)
    : [];

if (!is_array($data)) {
    $data = [];
}

$thumbnail_url = get_the_post_thumbnail_url($post_id, 'full');
$logo_url = 'https://flacso.edu.uy/wp-content/uploads/2026/05/logo_flacso_uruguay_20anos_blanco.png';

$tipo_oferta = '';
$tipo_terms = get_the_terms($post_id, 'tipo-oferta-academica');

if (!is_wp_error($tipo_terms) && !empty($tipo_terms)) {
    $tipo_oferta = $tipo_terms[0]->name;
}

$raw_content = get_post_field('post_content', $post_id);
$filtered_main_content = trim((string) apply_filters('the_content', $raw_content));
$descripcion_html = !empty($data['descripcion_html']) ? $data['descripcion_html'] : '';
$has_main_content = trim(wp_strip_all_tags($filtered_main_content)) !== '' || trim(wp_strip_all_tags($descripcion_html)) !== '';

$inscripciones_meta = get_post_meta($post_id, 'inscripciones_abiertas', true);

$inscripciones_abiertas = !empty($data['inscripciones_abiertas'])
    || $inscripciones_meta === '1'
    || $inscripciones_meta === 'true'
    || $inscripciones_meta === true
    || $inscripciones_meta === 1;

$preinscripcion_url = trailingslashit(get_permalink($post_id)) . 'preinscripcion/';
$inscripciones_year = class_exists('Oferta_Data_Schema')
    ? Oferta_Data_Schema::resolve_inscripciones_year($data['proximo_inicio'] ?? '', $data['cohorte'] ?? '')
    : '';

if ($inscripciones_year === '') {
    $inscripciones_year = wp_date('Y');
}

$hero_tag = $inscripciones_abiertas
    ? sprintf(
        /* translators: %s: academic year */
        __('Inscripciones %s', 'flacso-uruguay'),
        $inscripciones_year
    )
    : __('Próximamente', 'flacso-uruguay');

$hero_cta_markup = $inscripciones_abiertas
    ? sprintf(
        '%s <a href="%s">%s</a>',
        esc_html__('Descuentos especiales disponibles.', 'flacso-uruguay'),
        esc_url($preinscripcion_url),
        esc_html__('Solicitá información e inscribite hoy.', 'flacso-uruguay')
    )
    : esc_html__('Solicitá información y te avisaremos cuando abra la próxima cohorte.', 'flacso-uruguay');

$hero_primary_label = $inscripciones_abiertas
    ? sprintf(
        /* translators: %s: academic year */
        __('Preinscripción %s', 'flacso-uruguay'),
        $inscripciones_year
    )
    : __('Solicitar información', 'flacso-uruguay');

$hero_primary_url = $inscripciones_abiertas
    ? $preinscripcion_url
    : '#flacso-oa-consulta';

$format_duracion = function($meses_str) {
    if (class_exists('Oferta_Renderer') && method_exists('Oferta_Renderer', 'format_duration_months')) {
        return Oferta_Renderer::format_duration_months((string) $meses_str, 'flacso-uruguay');
    }

    return '';
};

$normalize_duracion_html = function($html, $meses_str = '') {
    if (class_exists('Oferta_Renderer') && method_exists('Oferta_Renderer', 'normalize_duration_html')) {
        return Oferta_Renderer::normalize_duration_html((string) $html, (string) $meses_str, 'flacso-uruguay');
    }

    return (string) $html;
};

$programa_meta = array_filter([
    !empty($data['abreviacion']) ? strtoupper((string) $data['abreviacion']) : '',
    !empty($data['duracion_meses']) ? sprintf(__('Duración: %s', 'flacso-uruguay'), $format_duracion($data['duracion_meses'])) : '',
]);

$render_info_card = static function ($title, $body, $extra_class = '') {
    if (trim((string) $body) === '') {
        return;
    }
    ?>
    <section class="flacso-oa-info-card <?php echo esc_attr($extra_class); ?>">
        <header class="flacso-oa-info-card__header">
            <h3><?php echo esc_html($title); ?></h3>
        </header>
        <div class="flacso-oa-info-card__body">
            <?php echo wp_kses_post($body); ?>
        </div>
    </section>
    <?php
};

$build_main_content_cards = static function ($html) {
    $html = trim((string) $html);

    if ($html === '') {
        return [];
    }

    $make_card = static function ($title, $body) {
        $title = trim((string) $title);
        $body = trim((string) $body);

        if ($body === '') {
            return null;
        }

        $classes = [];

        if ($title === '' || preg_match('/<(table|iframe)\b/i', $body)) {
            $classes[] = 'flacso-oa-content-card--wide';
        }

        return [
            'title' => $title,
            'body' => $body,
            'class' => implode(' ', $classes),
        ];
    };

    if (!class_exists('DOMDocument')) {
        $fallback = $make_card('', $html);
        return $fallback ? [$fallback] : [];
    }

    $internal_errors = libxml_use_internal_errors(true);
    $document = new DOMDocument('1.0', 'UTF-8');
    $loaded = $document->loadHTML(
        '<?xml encoding="utf-8" ?><div id="flacso-oa-content-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($internal_errors);

    if (!$loaded) {
        $fallback = $make_card('', $html);
        return $fallback ? [$fallback] : [];
    }

    $root = $document->getElementById('flacso-oa-content-root');

    if (!$root) {
        $fallback = $make_card('', $html);
        return $fallback ? [$fallback] : [];
    }

    $sections = [];
    $current = [
        'title' => '',
        'body' => '',
    ];
    $nodes = [];

    foreach ($root->childNodes as $child) {
        $nodes[] = $child;
    }

    foreach ($nodes as $node) {
        if ($node->nodeType === XML_COMMENT_NODE) {
            continue;
        }

        if ($node->nodeType === XML_TEXT_NODE && trim((string) $node->textContent) === '') {
            continue;
        }

        $tag = $node instanceof DOMElement ? strtolower($node->tagName) : '';

        if (in_array($tag, ['h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            $card = $make_card($current['title'], $current['body']);

            if ($card) {
                $sections[] = $card;
            }

            $current = [
                'title' => trim(wp_strip_all_tags($document->saveHTML($node))),
                'body' => '',
            ];
            continue;
        }

        $current['body'] .= $document->saveHTML($node);
    }

    $card = $make_card($current['title'], $current['body']);

    if ($card) {
        $sections[] = $card;
    }

    if (empty($sections)) {
        $fallback = $make_card('', $html);
        return $fallback ? [$fallback] : [];
    }

    return $sections;
};

$main_content_html = trim($descripcion_html . $filtered_main_content);
$main_content_cards = $build_main_content_cards($main_content_html);

$render_docente_item = static function ($docente_id, $rol = '', $nivel = '3') {
    $post = get_post($docente_id);
    if (!$post || $post->post_type !== 'docente') {
        return;
    }

    $nivel = in_array((string) $nivel, ['1', '2', '3'], true) ? (string) $nivel : '3';

    $titulo = function_exists('dp_nombre_completo') ? dp_nombre_completo($docente_id) : get_the_title($docente_id);
    $pref_abrev = trim((string) get_post_meta($docente_id, 'prefijo_abrev', true));
    $tit_acad   = trim((string) get_post_meta($docente_id, 'titulo_academico', true));

    $academic_label = $tit_acad ? $tit_acad : $pref_abrev;
    if ($academic_label !== '' && stripos($titulo, $academic_label) !== false) {
        $academic_label = '';
    }

    $cv_raw = (string) get_post_meta($docente_id, 'cv', true);
    $has_cv = trim(wp_strip_all_tags($cv_raw)) !== '';

    $avatar_size = [
        '1' => 260,
        '2' => 120,
        '3' => 88,
    ][$nivel];

    $avatar_class = $nivel === '1'
        ? 'flacso-oa-person-card__avatar'
        : 'flacso-oa-person-card__avatar-round';

    $card_classes = sprintf(
        'flacso-oa-person-card flacso-oa-person-card--nivel-%s%s',
        esc_attr($nivel),
        $has_cv ? ' flacso-oa-person-card--has-cv' : ' flacso-oa-person-card--no-cv'
    );

    echo '<div class="flacso-oa-docente-item flacso-oa-docente-item--nivel-' . esc_attr($nivel) . '">';
    echo '<article class="' . esc_attr($card_classes) . '" aria-label="' . esc_attr(sprintf(__('Docente: %s', 'flacso-uruguay'), $titulo)) . '">';

    if ($nivel === '1') {
        echo '<div class="flacso-oa-person-card__media">';
        echo '<div class="flacso-oa-person-card__avatar-wrap">';
        if (function_exists('dp_avatar_markup')) {
            echo dp_avatar_markup($docente_id, $titulo, $avatar_size, $avatar_class);
        } else {
            echo get_the_post_thumbnail($docente_id, 'medium_large', ['class' => $avatar_class]);
        }
        echo '</div>';
        echo '</div>';

        echo '<div class="flacso-oa-person-card__content">';
        if ($rol) {
            echo '<div class="flacso-oa-person-card__meta-row">';
            echo '<span class="flacso-oa-person-card__role-pill">' . esc_html($rol) . '</span>';
            echo '</div>';
        }

        echo '<h4 class="flacso-oa-person-card__name">' . esc_html($titulo) . '</h4>';

        if ($academic_label) {
            echo '<p class="flacso-oa-person-card__academic">' . esc_html($academic_label) . '</p>';
        }

        if ($has_cv) {
            echo '<div class="flacso-oa-person-card__cv">' . wp_kses_post($cv_raw) . '</div>';
        }

        echo '</div>';
    } else {
        echo '<div class="flacso-oa-person-card__header">';
        echo '<div class="flacso-oa-person-card__avatar-circle">';
        if (function_exists('dp_avatar_markup')) {
            echo dp_avatar_markup($docente_id, $titulo, $avatar_size, $avatar_class);
        } else {
            echo get_the_post_thumbnail($docente_id, 'thumbnail', ['class' => $avatar_class]);
        }
        echo '</div>';

        echo '<div class="flacso-oa-person-card__header-info">';
        if ($rol) {
            echo '<span class="flacso-oa-person-card__role-pill">' . esc_html($rol) . '</span>';
        }
        echo '<h4 class="flacso-oa-person-card__name">' . esc_html($titulo) . '</h4>';
        if ($academic_label) {
            echo '<p class="flacso-oa-person-card__academic">' . esc_html($academic_label) . '</p>';
        }
        echo '</div>';
        echo '</div>';

        if ($nivel === '2' && $has_cv) {
            echo '<div class="flacso-oa-person-card__content">';
            echo '<div class="flacso-oa-person-card__cv">' . wp_kses_post($cv_raw) . '</div>';
            echo '</div>';
        }
    }

    echo '</article>';
    echo '</div>';
};

if (class_exists('Oferta_Renderer')) {
    Oferta_Renderer::enqueue_styles();
}

get_header();
?>

<div id="inner-wrap" class="wrap kt-clear flacso-oferta-responsive">
    <div id="primary" class="content-area">
        <div class="content-container">
            <main id="main" class="site-main">
                <article id="post-<?php echo esc_attr($post_id); ?>" <?php post_class('entry content-bg single-entry flacso-oa-article'); ?>>

                    <?php
                    $banner_featured_url = $thumbnail_url;
                    if (!$banner_featured_url) {
                        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1400" height="788" viewBox="0 0 1400 788">'
                            . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
                            . '<stop offset="0" stop-color="#1d3a72"/><stop offset="1" stop-color="#0f1f3e"/>'
                            . '</linearGradient></defs>'
                            . '<rect width="1400" height="788" fill="url(#g)"/>'
                            . '<circle cx="1080" cy="240" r="210" fill="rgba(254,210,34,0.18)"/>'
                            . '<circle cx="1160" cy="320" r="150" fill="rgba(254,210,34,0.12)"/>'
                            . '<rect x="90" y="560" width="1220" height="140" rx="16" fill="rgba(0,0,0,0.18)"/>'
                            . '<text x="110" y="615" font-family="Inter, Arial" font-size="44" fill="rgba(255,255,255,0.92)">Previsualizacion</text>'
                            . '<text x="110" y="670" font-family="Inter, Arial" font-size="28" fill="rgba(255,255,255,0.78)">Defini una imagen destacada para ver la foto real.</text>'
                            . '</svg>';
                        $banner_featured_url = 'data:image/svg+xml;base64,' . base64_encode($svg);
                    }

                    $custom_mensaje = get_post_meta($post_id, 'inscripciones_mensaje', true);
                    $custom_mensaje_cerrado = get_post_meta($post_id, 'inscripciones_mensaje_cerrado', true);
                    $default_mensaje_abierto = get_option('flacso_inscripciones_mensaje_abierto_default', 'Descuentos especiales disponibles. Solicitá información e inscribite hoy.');
                    $default_mensaje_cerrado = get_option('flacso_inscripciones_mensaje_cerrado_default', 'Mantente atento a nuestras próximas aperturas.');

                    if ($inscripciones_abiertas) {
                        $banner_cta_text = !empty($custom_mensaje) ? $custom_mensaje : $default_mensaje_abierto;
                    } else {
                        $banner_cta_text = !empty($custom_mensaje_cerrado) ? $custom_mensaje_cerrado : $default_mensaje_cerrado;
                    }
                    ?>

                    <div class="flacso-oa-container" style="padding-top: clamp(24px, 4vw, 48px); padding-bottom: clamp(16px, 3vw, 32px);">
                        <section class="flacso-inscripciones-banner" style="margin-bottom: 0; min-height: clamp(400px, 40vw, 600px); position: relative; overflow: hidden; display: flex; flex-direction: column; border-radius: clamp(16px, 3vw, 28px);">
                            <img
                                class="flacso-inscripciones-banner__img"
                                src="<?php echo esc_url($banner_featured_url); ?>"
                                alt="<?php echo esc_attr(get_the_title($post_id)); ?>"
                                style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0;">

                            <div class="flacso-inscripciones-banner__overlay" style="padding: clamp(2rem, 5vw, 4rem); position: relative; z-index: 2; display: flex; flex-direction: column; flex: 1;">
                                <div class="flacso-inscripciones-banner__top" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
                                    <div class="flacso-inscripciones-banner__tag" style="background: var(--flacso-yellow); color: var(--flacso-blue-dark); padding: 4px 12px; font-weight: 800; text-transform: uppercase; border-radius: 4px; flex-shrink: 0;">
                                        <?php echo esc_html($hero_tag); ?>
                                    </div>
                                    <img
                                        src="<?php echo esc_url($logo_url); ?>"
                                        alt="FLACSO Uruguay"
                                        class="flacso-inscripciones-banner__logo" style="flex-shrink: 0; max-width: 250px; height: auto;">
                                </div>

                                <div class="flacso-inscripciones-banner__middle" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: flex-start; max-width: 1100px; margin: clamp(1.5rem, 3vw, 3rem) 0;">
                                    <?php if ($tipo_oferta !== '') : ?>
                                        <p class="flacso-inscripciones-banner__eyebrow" style="margin: 0 0 12px; color: #fcd116; font-size: clamp(0.9rem, 1.2vw, 1.05rem); font-weight: 850; letter-spacing: 0.08em; text-transform: uppercase;">
                                            <?php echo esc_html($tipo_oferta); ?>
                                        </p>
                                    <?php endif; ?>
                                    <h1 class="flacso-inscripciones-banner__title" style="margin: 0; color: #ffffff; font-size: clamp(2rem, 4vw, 3.8rem); font-weight: 900; letter-spacing: -0.025em; line-height: 1.15; text-wrap: balance; text-shadow: 0 4px 16px rgba(0,0,0,0.6);">
                                        <?php the_title(); ?>
                                    </h1>
                                    <?php if (has_excerpt()) : ?>
                                        <div class="flacso-inscripciones-banner__excerpt" style="margin: 20px 0 0 0; color: rgba(255,255,255,0.95); font-size: clamp(1.05rem, 1.4vw, 1.25rem); font-weight: 500; line-height: 1.5; text-wrap: balance; text-shadow: 0 2px 8px rgba(0,0,0,0.5); max-width: 900px;">
                                            <?php the_excerpt(); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($programa_meta)) : ?>
                                        <div class="flacso-inscripciones-banner__meta" style="margin-top: 24px; display: flex; flex-wrap: wrap; gap: 1rem; align-items: center;">
                                            <?php foreach ($programa_meta as $meta_item) : ?>
                                                <span style="background: rgba(255,255,255,0.15); backdrop-filter: blur(8px); padding: 6px 16px; border-radius: 20px; color: white; font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; gap: 0.5rem; border: 1px solid rgba(255,255,255,0.2);">
                                                    <?php echo esc_html($meta_item); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="flacso-inscripciones-banner__bottom" style="margin-top: auto;">
                                    <div class="flacso-inscripciones-banner__cta" style="font-size: clamp(1rem, 1.2vw, 1.15rem); color: white; font-weight: 600;">
                                        <?php echo esc_html($banner_cta_text); ?>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <section id="flacso-oa-contenido" class="flacso-oa-main-section">
                        <div class="flacso-oa-container">
                            <?php
                            $raw_proximo = get_post_meta($post_id, 'proximo_inicio', true);
                            if ($raw_proximo) :
                                $precision = get_post_meta($post_id, 'proximo_inicio_precision', true);

                                $formatted = class_exists('Oferta_Renderer')
                                    ? Oferta_Renderer::format_proximo_inicio_text((string) $raw_proximo, (string) $precision)
                                    : trim((string) $raw_proximo);
                                if ($formatted === '') $formatted = __('A definir', 'flacso-oferta-academica');

                                $cohorte = trim((string) get_post_meta($post_id, 'cohorte', true));
                                $label = esc_html__('Próximo inicio', 'flacso-oferta-academica');
                                if ($cohorte !== '') {
                                    $label .= '<span style="display: block; font-size: 0.88em; opacity: 0.85; font-weight: 500; margin-top: 3px;">(' . esc_html($cohorte) . ')</span>';
                                }
                            ?>
                                <section class="flacso-oa-next-start flacso-oa-next-start--fullwidth" style="margin-top: -1rem; margin-bottom: 2rem;">
                                    <div class="flacso-oferta-proximo-inicio" role="status" aria-live="polite">
                                        <p class="flacso-oferta-proximo-inicio__pill" style="align-items: center;">
                                            <span class="flacso-oferta-proximo-inicio__icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                                            <span class="flacso-oferta-proximo-inicio__content" style="display: flex; flex-direction: column; justify-content: center;">
                                                <span class="flacso-oferta-proximo-inicio__label" style="line-height: 1.2;"><?php echo $label; ?></span>
                                                <strong class="flacso-oferta-proximo-inicio__value"><?php echo esc_html($formatted); ?></strong>
                                            </span>
                                        </p>
                                    </div>
                                </section>
                            <?php endif; ?>
                            
                            <div class="flacso-oa-main-grid">

                                <div class="flacso-oa-main-content">


                                    <?php if ($has_main_content) : ?>
                                        <div class="flacso-oa-content-grid">
                                            <?php foreach ($main_content_cards as $content_card) : ?>
                                                <section class="flacso-oa-content-card <?php echo esc_attr($content_card['class']); ?>">
                                                    <?php if (!empty($content_card['title'])) : ?>
                                                        <header class="flacso-oa-content-card__header">
                                                            <h2><?php echo esc_html($content_card['title']); ?></h2>
                                                        </header>
                                                    <?php endif; ?>

                                                    <div class="flacso-oa-content-card__body">
                                                        <?php echo wp_kses_post($content_card['body']); ?>
                                                    </div>
                                                </section>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                </div>

                                <aside id="flacso-oa-consulta" class="flacso-oa-aside" aria-label="<?php esc_attr_e('Formulario de consulta', 'flacso-uruguay'); ?>">
                                    <div class="flacso-oa-aside__sticky">
                                        <div class="flacso-oa-form-panel">
                                            <div class="flacso-oa-form-panel__header">
                                                <h2><?php esc_html_e('Solicitá información', 'flacso-uruguay'); ?></h2>
                                                <p><?php esc_html_e('Completá el formulario y recibí información sobre cursada, inscripción y financiación.', 'flacso-uruguay'); ?></p>
                                            </div>

                                            <div class="flacso-oa-form-panel__body">
                                                <?php
                                                if (function_exists('flacso_consultas_render_form')) {
                                                    echo flacso_consultas_render_form(['mostrar_preinscripcion' => false]);
                                                } elseif (class_exists('Oferta_Consulta_Form')) {
                                                    echo Oferta_Consulta_Form::render_inline_form($post_id);
                                                }
                                                ?>
                                            </div>
                                        </div>

                                        <?php if ($inscripciones_abiertas && function_exists('flacso_consultas_render_preinscripcion_button')) : ?>
                                            <div class="flacso-oa-preinsc-wrapper" style="margin-top: 0.75rem; padding: 0 clamp(20px, 3vw, 30px);">
                                                <?php echo flacso_consultas_render_preinscripcion_button(); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </aside>

                            </div>

                            <div class="flacso-oa-info-grid" style="margin-top: 3rem;">
                                <?php
                                $modalidad_html = !empty($data['modalidad_html'])
                                    ? $data['modalidad_html']
                                    : '<p>' . esc_html__('Virtual.', 'flacso-uruguay') . '</p>';

                                $render_info_card(
                                    __('Modalidad', 'flacso-uruguay'),
                                    $modalidad_html
                                );

                                $duracion_html = $normalize_duracion_html($data['duracion_html'] ?? '', $data['duracion_meses'] ?? '');
                                if (!empty($duracion_html)) {
                                    $render_info_card(
                                        __('Duración', 'flacso-uruguay'),
                                        $duracion_html
                                    );
                                }

                                if (!empty($data['objetivos_html'])) {
                                    $render_info_card(
                                        __('Objetivos', 'flacso-uruguay'),
                                        $data['objetivos_html']
                                    );
                                }

                                if (!empty($data['perfil_ingreso_html'])) {
                                    $render_info_card(
                                        __('Perfil de ingreso', 'flacso-uruguay'),
                                        $data['perfil_ingreso_html']
                                    );
                                }

                                if (!empty($data['requisitos_ingreso_html'])) {
                                    $render_info_card(
                                        __('Requisitos de ingreso', 'flacso-uruguay'),
                                        $data['requisitos_ingreso_html']
                                    );
                                }

                                if (!empty($data['perfil_egreso_html'])) {
                                    $render_info_card(
                                        __('Perfil de egreso', 'flacso-uruguay'),
                                        $data['perfil_egreso_html']
                                    );
                                }

                                if (!empty($data['requisitos_egreso_html'])) {
                                    $render_info_card(
                                        __('Requisitos de egreso', 'flacso-uruguay'),
                                        $data['requisitos_egreso_html']
                                    );
                                }

                                if (!empty($data['titulos_certificaciones_html'])) {
                                    $render_info_card(
                                        __('Títulos y Certificaciones', 'flacso-uruguay'),
                                        $data['titulos_certificaciones_html']
                                    );
                                }

                                $documentos = !empty($data['documentos']) && is_string($data['documentos']) ? json_decode($data['documentos'], true) : [];
                                
                                $cartamalla_pdf_url = !empty($documentos['cartamalla']['link']) ? trim((string) $documentos['cartamalla']['link']) : '';
                                
                                if ($cartamalla_pdf_url) {
                                    if (function_exists('flacso_get_pdf_proxy_url')) {
                                        $proxied = flacso_get_pdf_proxy_url($cartamalla_pdf_url, 'Calendario y Malla Curricular');
                                        if ($proxied) $cartamalla_pdf_url = $proxied;
                                    }
                                    $combined_body = '<p style="margin-bottom:1rem;">' . esc_html__('Podés descargar el calendario de cursada y la malla curricular.', 'flacso-uruguay') . '</p>' .
                                                     '<a href="' . esc_url($cartamalla_pdf_url) . '" target="_blank" class="flacso-oa-link-btn" style="display:inline-flex; align-items:center; gap:0.5rem;"><i class="bi bi-file-earmark-pdf"></i> ' . esc_html__('Ver Calendario y Malla (PDF)', 'flacso-uruguay') . '</a>';
                                    
                                    $render_info_card(
                                        __('Malla y Calendario Académico', 'flacso-uruguay'),
                                        $combined_body,
                                        'flacso-oa-info-card--wide'
                                    );
                                } else {
                                    $malla_pdf_url = !empty($documentos['malla']['link']) ? trim((string) $documentos['malla']['link']) : (!empty($data['malla_curricular']) ? trim((string) $data['malla_curricular']) : '');
                                    
                                    if ($malla_pdf_url) {
                                        if (function_exists('flacso_get_pdf_proxy_url')) {
                                            $proxied = flacso_get_pdf_proxy_url($malla_pdf_url, 'Malla curricular');
                                            if ($proxied) $malla_pdf_url = $proxied;
                                        }
                                        $malla_body = '<a href="' . esc_url($malla_pdf_url) . '" target="_blank" class="flacso-oa-link-btn" style="display:inline-flex; align-items:center; gap:0.5rem;"><i class="bi bi-file-earmark-pdf"></i> ' . esc_html__('Ver Malla Curricular (PDF)', 'flacso-uruguay') . '</a>';
                                        
                                        $render_info_card(
                                            __('Malla Curricular', 'flacso-uruguay'),
                                            $malla_body,
                                            'flacso-oa-info-card--wide'
                                        );
                                    }
                                    
                                    $calendario_pdf_url = !empty($documentos['calendario']['link']) ? trim((string) $documentos['calendario']['link']) : (!empty($data['calendario']) ? trim((string) $data['calendario']) : '');
                                    
                                    if ($calendario_pdf_url) {
                                        if (function_exists('flacso_get_pdf_proxy_url')) {
                                            $proxied = flacso_get_pdf_proxy_url($calendario_pdf_url, 'Calendario Academico');
                                            if ($proxied) $calendario_pdf_url = $proxied;
                                        }
                                        $calendario_body = '<a href="' . esc_url($calendario_pdf_url) . '" target="_blank" class="flacso-oa-link-btn" style="display:inline-flex; align-items:center; gap:0.5rem;"><i class="bi bi-file-earmark-pdf"></i> ' . esc_html__('Ver Calendario (PDF)', 'flacso-uruguay') . '</a>';
                                        
                                        $render_info_card(
                                            __('Calendario Académico', 'flacso-uruguay'),
                                            $calendario_body,
                                            'flacso-oa-info-card--wide'
                                        );
                                    }
                                }

                                $financiacion_html = !empty($data['financiacion_html'])
                                    ? (string) $data['financiacion_html']
                                    : '';

                                if ($financiacion_html === '') {
                                    $financiacion_html  = '<p>' . esc_html__('FLACSO ofrece financiación flexible, el monto de las cuotas puede variar dependiendo de las promociones que haya aprovechado, o el plan de pagos que se coordine con la Institución. Todos los posgrados pueden abonarse en cuotas, siguiendo un plan mensual de pagos que acompañan la cursada. No obstante, es posible extender los planes de pago en forma flexible, con valores de cuota a su alcance.', 'flacso-uruguay') . '</p>';
                                    $financiacion_html .= '<p>' . esc_html__('Quienes cursen desde fuera de Uruguay pueden pagar de forma segura a través de la plataforma de pago de la institución, mientras las personas que cursan en el país, disponen de otras vías para pagar.', 'flacso-uruguay') . '</p>';
                                    $financiacion_html .= '<p>' . esc_html__('Las becas para cursar en FLACSO Uruguay están sujetas a convenios inter institucionales y son limitadas por cohorte. Para obtener más información sobre las posibles becas disponibles puede comunicarse con la asistente académica.', 'flacso-uruguay') . '</p>';
                                }

                                $render_info_card(
                                    __('Financiación y becas', 'flacso-uruguay'),
                                    $financiacion_html,
                                    'flacso-oa-info-card--wide'
                                );
                                ?>
                            </div>
                        </div>
                    </section>

                    <section class="flacso-oa-cta-section">
                        <div class="flacso-oa-container">
                            <div class="flacso-oa-cta">
                                <p>
                                    <?php esc_html_e('Para realizar las postulaciones puede completar el formulario y en breve el personal de asistencia académica se pondrá en contacto.', 'flacso-uruguay'); ?>
                                </p>
                            </div>
                        </div>
                    </section>

                    <?php
                    // Recolectar y fusionar equipos para mantener retrocompatibilidad
                    $merged_equipos = [];
                    
                    if (!empty($data['coordinacion_academica']) && is_array($data['coordinacion_academica'])) {
                        foreach ($data['coordinacion_academica'] as $coord) {
                            if (!empty($coord['docentes']) && is_array($coord['docentes'])) {
                                $merged_equipos[] = [
                                    'nombre' => !empty($coord['rol']) ? $coord['rol'] : __('Coordinación académica', 'flacso-uruguay'),
                                    'docentes' => $coord['docentes'],
                                    'importancia' => '1' // Por defecto Nivel 1
                                ];
                            }
                        }
                    }

                    if (!empty($data['equipos']) && is_array($data['equipos'])) {
                        foreach ($data['equipos'] as $eq) {
                            if (!empty($eq['docentes']) && is_array($eq['docentes'])) {
                                $merged_equipos[] = [
                                    'nombre' => $eq['nombre'] ?? '',
                                    'docentes' => $eq['docentes'],
                                    'importancia' => strval($eq['importancia'] ?? '3')
                                ];
                            }
                        }
                    }
                    ?>

                    <?php if (!empty($merged_equipos)) : ?>
                        <section class="flacso-oa-team-section">
                            <div class="flacso-oa-container">
                                <header class="flacso-oa-section-header">
                                    <h2><?php esc_html_e('Equipo Académico', 'flacso-uruguay'); ?></h2>
                                </header>

                                <div class="flacso-oa-team-subgroups">
                                    <?php foreach ($merged_equipos as $grupo) : ?>
                                        <?php
                                        $nivel = in_array((string) ($grupo['importancia'] ?? '3'), ['1', '2', '3'], true)
                                            ? (string) ($grupo['importancia'] ?? '3')
                                            : '3';
                                        ?>
                                        <div class="flacso-oa-team-subgroup flacso-oa-team-subgroup--nivel-<?php echo esc_attr($nivel); ?>">
                                            <?php if (!empty($grupo['nombre'])) : ?>
                                                <h4 class="flacso-oa-team-subgroup__title">
                                                    <?php echo esc_html($grupo['nombre']); ?>
                                                </h4>
                                            <?php endif; ?>

                                            <div class="flacso-oa-docentes-grid flacso-oa-docentes-grid--nivel-<?php echo esc_attr($nivel); ?>">
                                                <?php foreach ($grupo['docentes'] as $docente_item) : ?>
                                                    <?php
                                                    $docente_id = is_array($docente_item) ? ($docente_item['id'] ?? 0) : $docente_item;
                                                    $rol_especifico = is_array($docente_item) ? ($docente_item['rol'] ?? '') : '';
                                                    $render_docente_item($docente_id, $rol_especifico, $nivel);
                                                    ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php
                    if (class_exists('Oferta_Seminarios_Integration')) :
                        $seminarios_all = Oferta_Seminarios_Integration::get_programa_seminarios_data($post_id);
                        $seminarios = [];
                        $hoy = new DateTimeImmutable('today', wp_timezone());

                        if (is_array($seminarios_all)) {
                            foreach ($seminarios_all as $seminario) {
                                if (empty($seminario['fecha_inicio'])) {
                                    continue;
                                }

                                $inicio_obj = DateTimeImmutable::createFromFormat('Y-m-d|', $seminario['fecha_inicio'], wp_timezone());

                                if (!$inicio_obj) {
                                    continue;
                                }

                                $dias_hasta_inicio = (int) floor(($inicio_obj->getTimestamp() - $hoy->getTimestamp()) / DAY_IN_SECONDS);
                                $is_upcoming = $dias_hasta_inicio >= 0;
                                $is_started_recent = $dias_hasta_inicio < 0 && $dias_hasta_inicio >= -7;

                                if ($is_upcoming || $is_started_recent) {
                                    $seminarios[] = $seminario;
                                }
                            }
                        }

                        usort($seminarios, static function ($a, $b) {
                            return strcmp($a['fecha_inicio'] ?? '', $b['fecha_inicio'] ?? '');
                        });
                        ?>

                        <?php if (!empty($seminarios)) : ?>
                            <section class="flacso-oa-seminarios-section">
                                <div class="flacso-oa-container">
                                    <div class="flacso-oa-seminarios-heading">
                                        <h2><?php esc_html_e('Seminarios Vinculados', 'flacso-uruguay'); ?></h2>

                                        <a href="<?php echo esc_url(trailingslashit(get_permalink($post_id)) . 'seminarios/'); ?>" class="flacso-oa-link-btn">
                                            <?php esc_html_e('Ver todos', 'flacso-uruguay'); ?>
                                        </a>
                                    </div>

                                    <div class="flacso-oa-seminarios-grid">
                                        <?php foreach (array_slice($seminarios, 0, 3) as $seminario) : ?>
                                            <?php
                                            $seminario_url = !empty($seminario['permalink']) ? $seminario['permalink'] : '#';
                                            $seminario_titulo = !empty($seminario['titulo']) ? $seminario['titulo'] : __('Seminario', 'flacso-uruguay');
                                            $fecha_inicio = !empty($seminario['fecha_inicio']) ? $seminario['fecha_inicio'] : '';
                                            $fecha_legible = $fecha_inicio
                                                ? date_i18n(get_option('date_format'), strtotime($fecha_inicio))
                                                : '';
                                            ?>
                                            <article class="flacso-oa-seminario-card">
                                                <h3>
                                                    <a href="<?php echo esc_url($seminario_url); ?>">
                                                        <?php echo esc_html($seminario_titulo); ?>
                                                    </a>
                                                </h3>

                                                <?php if ($fecha_legible) : ?>
                                                    <p class="flacso-oa-seminario-card__date">
                                                        <?php echo esc_html($fecha_legible); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </section>
                        <?php endif; ?>
                    <?php endif; ?>

                </article>
            </main>
        </div>
    </div>
</div>

<script>
(function () {
    document.addEventListener('click', function (event) {
        const header = event.target.closest('.flacso-oferta-responsive .kt-blocks-accordion-header');

        if (!header) {
            return;
        }

        const pane = header.closest('.kt-accordion-pane');
        const wrapper = header.closest('.kt-accordion-inner-wrap');

        if (!pane || !wrapper) {
            return;
        }

        const panel = pane.querySelector('.kt-accordion-panel');
        const isActive = pane.classList.contains('kt-accordion-pane-active');

        wrapper.querySelectorAll('.kt-accordion-pane').forEach(function (item) {
            item.classList.remove('kt-accordion-pane-active');

            const itemPanel = item.querySelector('.kt-accordion-panel');

            if (itemPanel) {
                itemPanel.style.display = 'none';
            }
        });

        if (!isActive) {
            pane.classList.add('kt-accordion-pane-active');

            if (panel) {
                panel.style.display = 'block';
            }
        }
    });
})();
</script>

<style>
.flacso-oferta-responsive {
    --flacso-blue-dark: #051938;
    --flacso-blue: #163970;
    --flacso-blue-soft: #244f94;
    --flacso-yellow: #fcd116;
    --flacso-yellow-dark: #e1b900;
    --flacso-bg: #f8fafc;
    --flacso-text: #1f2937;
    --flacso-muted: #64748b;
    --flacso-border: rgba(15, 23, 42, 0.10);
    --flacso-shadow-sm: 0 8px 22px rgba(15, 23, 42, 0.07);
    --flacso-shadow-md: 0 18px 45px rgba(15, 23, 42, 0.11);
    --flacso-radius-md: 22px;
    --flacso-radius-lg: 34px;
    background: #ffffff;
    color: var(--flacso-text);
}

.flacso-oferta-responsive,
.flacso-oferta-responsive * {
    box-sizing: border-box;
}

.flacso-oferta-responsive .content-bg,
.flacso-oferta-responsive .single-entry,
.flacso-oferta-responsive .entry-content-wrap,
.flacso-oferta-responsive .entry {
    background: transparent;
    box-shadow: none;
}

.flacso-oferta-responsive h1,
.flacso-oferta-responsive h2,
.flacso-oferta-responsive h3,
.flacso-oferta-responsive h4,
.flacso-oferta-responsive h5,
.flacso-oferta-responsive h6,
.flacso-oferta-responsive p,
.flacso-oferta-responsive span,
.flacso-oferta-responsive div {
    overflow-wrap: break-word;
    word-wrap: break-word;
}

.flacso-oferta-responsive .entry-content-wrap {
    padding: 0;
}

.flacso-oa-container {
    width: 100%;
    max-width: 1180px;
    margin-inline: auto;
    padding-left: clamp(24px, 5vw, 40px);
    padding-right: clamp(24px, 5vw, 40px);
    box-sizing: border-box;
}

.flacso-oa-article {
    overflow: hidden;
}

.flacso-oa-hero-v3 {
    padding: clamp(44px, 7vw, 92px) 0 clamp(38px, 6vw, 74px);
    background:
        radial-gradient(circle at 88% 8%, rgba(252, 209, 22, 0.16), transparent 26%),
        linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}

.flacso-oa-hero-v3__intro {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(290px, 390px);
    gap: clamp(28px, 5vw, 72px);
    align-items: end;
    margin-bottom: clamp(28px, 5vw, 52px);
}

.flacso-oa-hero-v3__main {
    min-width: 0;
}

.flacso-oa-hero-v3__eyebrow {
    margin: 0 0 12px;
    color: var(--flacso-blue);
    font-size: clamp(0.86rem, 1.15vw, 1rem);
    font-weight: 900;
    letter-spacing: 0.08em;
    line-height: 1.25;
    text-transform: uppercase;
}

.flacso-oa-hero-v3__title {
    margin: 0;
    color: var(--flacso-blue-dark);
    font-size: clamp(2.6rem, 6.4vw, 5.8rem);
    font-weight: 950;
    letter-spacing: -0.065em;
    line-height: 0.93;
    text-wrap: balance;
}

.flacso-oa-hero-v3__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: clamp(22px, 3vw, 34px);
}

.flacso-oa-hero-v3__meta span {
    display: inline-flex;
    align-items: center;
    min-height: 38px;
    padding: 8px 14px;
    border: 1px solid var(--flacso-border);
    border-radius: 999px;
    background: #ffffff;
    color: var(--flacso-blue);
    font-size: 0.92rem;
    font-weight: 800;
    line-height: 1.2;
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.05);
}

.flacso-oa-hero-v3__summary {
    min-width: 0;
    padding: clamp(22px, 3vw, 30px);
    border: 1px solid var(--flacso-border);
    border-radius: var(--flacso-radius-md);
    background: #ffffff;
    box-shadow: var(--flacso-shadow-sm);
}

.flacso-oa-hero-v3__tag {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    max-width: 100%;
    margin-bottom: 16px;
    padding: 9px 16px;
    border-radius: 999px;
    background: var(--flacso-blue-dark);
    color: #ffffff;
    font-size: 0.82rem;
    font-weight: 900;
    line-height: 1.2;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.flacso-oa-hero-v3__cta {
    color: #334155;
    font-size: clamp(1rem, 1.25vw, 1.08rem);
    font-weight: 650;
    line-height: 1.55;
}

.flacso-oa-hero-v3__cta a {
    color: var(--flacso-blue);
    font-weight: 850;
    text-decoration: underline;
    text-decoration-thickness: 2px;
    text-underline-offset: 5px;
}

.flacso-oa-hero-v3__actions {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 10px;
    margin-top: 22px;
}

.flacso-oa-hero-v3__button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    padding: 12px 18px;
    border-radius: 999px;
    font-size: 0.9rem;
    font-weight: 900;
    line-height: 1.2;
    letter-spacing: 0.04em;
    text-align: center;
    text-transform: uppercase;
    text-decoration: none;
    transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
}

.flacso-oa-hero-v3__button--primary {
    background: var(--flacso-yellow);
    color: var(--flacso-blue-dark);
    box-shadow: 0 10px 22px rgba(252, 209, 22, 0.26);
}

.flacso-oa-hero-v3__button--primary:hover {
    background: var(--flacso-blue-dark);
    color: #ffffff;
    transform: translateY(-1px);
}

.flacso-oa-hero-v3__button--secondary {
    border: 1px solid var(--flacso-border);
    background: #ffffff;
    color: var(--flacso-blue);
}

.flacso-oa-hero-v3__button--secondary:hover {
    border-color: var(--flacso-blue);
    background: var(--flacso-blue);
    color: #ffffff;
    transform: translateY(-1px);
}

.flacso-oa-hero-v3__media {
    position: relative;
    min-height: clamp(320px, 45vw, 540px);
    border-radius: var(--flacso-radius-lg);
    overflow: hidden;
    background: var(--flacso-blue-dark);
    box-shadow: 0 24px 65px rgba(5, 25, 56, 0.16);
    isolation: isolate;
}

.flacso-oa-hero-v3__image {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: -3;
}

.flacso-oa-hero-v3__image--placeholder {
    background:
        radial-gradient(circle at 76% 24%, rgba(252, 209, 22, 0.28), transparent 28%),
        linear-gradient(135deg, #1d3a72 0%, #0f1f3e 100%);
}

.flacso-oa-hero-v3__media::after {
    content: "";
    position: absolute;
    inset: 0;
    z-index: -2;
    background:
        linear-gradient(90deg, rgba(5, 25, 56, 0.84) 0%, rgba(5, 25, 56, 0.58) 46%, rgba(5, 25, 56, 0.22) 100%),
        linear-gradient(180deg, rgba(5, 25, 56, 0.16) 0%, rgba(5, 25, 56, 0.76) 100%);
}

.flacso-oa-hero-v3__media-overlay {
    position: absolute;
    inset: clamp(22px, 3vw, 34px);
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
    pointer-events: none;
}

.flacso-oa-hero-v3__logo {
    width: clamp(150px, 18vw, 230px);
    height: auto;
    object-fit: contain;
    filter: drop-shadow(0 12px 22px rgba(0, 0, 0, 0.24));
}

.flacso-oa-main-section {
    padding: clamp(40px, 7vw, 88px) 0 clamp(46px, 7vw, 92px);
}

.flacso-oa-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: clamp(26px, 5vw, 56px);
    align-items: start;
}

.flacso-oa-main-content {
    min-width: 0;
}

.flacso-oa-content-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: clamp(18px, 2.4vw, 26px);
    align-items: start;
}

.flacso-oa-content-grid > .flacso-oa-content-card {
    grid-column: span 6;
    min-width: 0;
}

.flacso-oa-content-grid > .flacso-oa-content-card--wide {
    grid-column: 1 / -1;
}

.flacso-oa-content-card {
    border: 1px solid var(--flacso-border);
    border-radius: var(--flacso-radius-md);
    background: #ffffff;
    box-shadow: var(--flacso-shadow-sm);
    overflow: hidden;
}

.flacso-oa-content-card__header {
    position: relative;
    padding: 18px clamp(20px, 3vw, 28px);
    border-bottom: 1px solid var(--flacso-border);
    background:
        linear-gradient(90deg, rgba(252, 209, 22, 0.20), transparent 52%),
        #ffffff;
}

.flacso-oa-content-card__header::before {
    content: "";
    position: absolute;
    inset: 0 auto 0 0;
    width: 6px;
    background: var(--flacso-blue);
}

.flacso-oa-content-card__header h2 {
    margin: 0;
    color: var(--flacso-blue);
    font-size: clamp(1rem, 1.5vw, 1.17rem);
    font-weight: 900;
    letter-spacing: 0.04em;
    line-height: 1.25;
    text-transform: uppercase;
}

.flacso-oa-content-card__body {
    padding: clamp(22px, 4vw, 42px);
    font-size: clamp(1rem, 1.3vw, 1.12rem);
    line-height: 1.72;
    color: #334155;
}

.flacso-oa-content-card__body > *:first-child,
.flacso-oa-info-card__body > *:first-child {
    margin-top: 0;
}

.flacso-oa-content-card__body > *:last-child,
.flacso-oa-info-card__body > *:last-child {
    margin-bottom: 0;
}

.flacso-oa-content-card__body img,
.flacso-oa-info-card__body img,
.flacso-oa-team-section img {
    max-width: 100%;
    height: auto;
}

.flacso-oa-content-card__body table,
.flacso-oa-info-card__body table {
    display: block;
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    border-collapse: collapse;
}

.flacso-oa-content-card__body iframe,
.flacso-oa-info-card__body iframe {
    max-width: 100%;
}

.flacso-oa-next-start {
    margin-top: clamp(20px, 3vw, 34px);
}

.flacso-oa-next-start--first {
    margin-top: 0;
}

.flacso-oa-info-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: clamp(18px, 2.4vw, 26px);
    align-items: start;
    margin-top: clamp(24px, 4vw, 42px);
}

.flacso-oa-info-card {
    min-width: 0;
    height: fit-content;
    border: 1px solid var(--flacso-border);
    border-radius: var(--flacso-radius-md);
    background: #ffffff;
    box-shadow: var(--flacso-shadow-sm);
    overflow: hidden;
}

.flacso-oa-info-card__header {
    position: relative;
    padding: 18px clamp(20px, 3vw, 28px);
    border-bottom: 1px solid var(--flacso-border);
    background:
        linear-gradient(90deg, rgba(20, 56, 114, 0.08), transparent 52%),
        #ffffff;
}

.flacso-oa-info-card__header::before {
    content: "";
    position: absolute;
    inset: 0 auto 0 0;
    width: 6px;
    background: var(--flacso-blue);
}

.flacso-oa-info-card__header h3 {
    margin: 0;
    color: var(--flacso-blue);
    font-size: clamp(1rem, 1.5vw, 1.17rem);
    font-weight: 900;
    letter-spacing: 0.04em;
    line-height: 1.25;
    text-transform: uppercase;
}

.flacso-oa-info-card__body {
    padding: clamp(20px, 3vw, 30px);
    color: #334155;
    font-size: clamp(0.98rem, 1.2vw, 1.06rem);
    line-height: 1.7;
}

.flacso-oa-aside {
    min-width: 0;
    scroll-margin-top: 24px;
}

.flacso-oa-form-panel {
    border-radius: var(--flacso-radius-md);
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.60), transparent 34%),
        var(--flacso-yellow);
    color: var(--flacso-blue-dark);
    box-shadow: var(--flacso-shadow-md);
    overflow: hidden;
}

.flacso-oa-form-panel__header {
    padding: clamp(22px, 3vw, 32px) clamp(20px, 3vw, 30px) 16px;
}

.flacso-oa-form-panel__header h2 {
    margin: 0 0 8px;
    color: var(--flacso-blue-dark);
    font-size: clamp(1.45rem, 2.4vw, 2rem);
    font-weight: 900;
    letter-spacing: -0.035em;
    line-height: 1.05;
}

.flacso-oa-form-panel__header p {
    margin: 0;
    color: rgba(5, 25, 56, 0.78);
    font-size: 0.97rem;
    line-height: 1.45;
}

.flacso-oa-form-panel__body {
    padding: 0 clamp(20px, 3vw, 30px) clamp(22px, 3vw, 30px);
}

.flacso-oa-form-panel__footer {
    padding: 0 clamp(20px, 3vw, 30px) clamp(22px, 3vw, 30px);
}

.flacso-oferta-responsive .flacso-oa-form-panel__body form,
.flacso-oferta-responsive .flacso-oa-form-panel__body .flacso-consultas-formulario,
.flacso-oferta-responsive .flacso-oa-form-panel__body .flacso-consultas-formulario-wrapper {
    border: 0 !important;
    outline: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    border-radius: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
}

.flacso-oferta-responsive .flacso-oa-form-panel__body .flacso-consultas-formulario > h1,
.flacso-oferta-responsive .flacso-oa-form-panel__body .flacso-consultas-formulario > h2,
.flacso-oferta-responsive .flacso-oa-form-panel__body .flacso-consultas-formulario > h3,
.flacso-oferta-responsive .flacso-oa-form-panel__body .flacso-consultas-formulario > p:first-of-type {
    display: none !important;
}

.flacso-oferta-responsive .flacso-consultas-formulario {
    background: transparent !important;
    padding: 0 !important;
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    color: var(--flacso-blue-dark);
}

.flacso-oferta-responsive .flacso-oa-consulta__grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 14px;
}

.flacso-oferta-responsive .flacso-oa-consulta__field {
    margin-bottom: 14px;
    text-align: left;
}

.flacso-oferta-responsive .flacso-oa-consulta__field label {
    display: block;
    margin-bottom: 7px;
    color: rgba(5, 25, 56, 0.86);
    font-size: 0.84rem;
    font-weight: 800;
    line-height: 1.25;
}

.flacso-oferta-responsive .flacso-oa-consulta__field input,
.flacso-oferta-responsive .flacso-oa-consulta__field textarea,
.flacso-oferta-responsive .flacso-oa-consulta__field select,
.flacso-oferta-responsive .flacso-consultas-formulario input,
.flacso-oferta-responsive .flacso-consultas-formulario textarea,
.flacso-oferta-responsive .flacso-consultas-formulario select {
    width: 100%;
    min-height: 46px;
    padding: 12px 14px;
    border: 1px solid rgba(5, 25, 56, 0.12);
    border-radius: 12px;
    background: #ffffff;
    color: var(--flacso-text);
    font-size: 1rem;
    line-height: 1.35;
    box-shadow: 0 5px 14px rgba(5, 25, 56, 0.08);
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
}

.flacso-oferta-responsive .flacso-oa-consulta__field textarea,
.flacso-oferta-responsive .flacso-consultas-formulario textarea {
    min-height: 120px;
    resize: vertical;
}

.flacso-oferta-responsive .flacso-oa-consulta__field input:focus,
.flacso-oferta-responsive .flacso-oa-consulta__field textarea:focus,
.flacso-oferta-responsive .flacso-oa-consulta__field select:focus,
.flacso-oferta-responsive .flacso-consultas-formulario input:focus,
.flacso-oferta-responsive .flacso-consultas-formulario textarea:focus,
.flacso-oferta-responsive .flacso-consultas-formulario select:focus {
    outline: none;
    border-color: var(--flacso-blue);
    box-shadow: 0 0 0 4px rgba(22, 57, 112, 0.15), 0 8px 20px rgba(5, 25, 56, 0.10);
}

.flacso-oferta-responsive .flacso-oa-consulta__submit,
.flacso-oferta-responsive .flacso-consultas-formulario button[type="submit"],
.flacso-oferta-responsive .flacso-consultas-formulario input[type="submit"] {
    width: 100%;
    min-height: 50px;
    border: 0;
    border-radius: 999px;
    background: var(--flacso-blue);
    color: #ffffff;
    font-size: 0.95rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    line-height: 1.2;
    text-transform: uppercase;
    box-shadow: 0 10px 24px rgba(5, 25, 56, 0.18);
    cursor: pointer;
    transition: transform 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
}

.flacso-oferta-responsive .flacso-oa-consulta__submit:hover,
.flacso-oferta-responsive .flacso-consultas-formulario button[type="submit"]:hover,
.flacso-oferta-responsive .flacso-consultas-formulario input[type="submit"]:hover {
    background: var(--flacso-blue-dark);
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 14px 30px rgba(5, 25, 56, 0.24);
}

.flacso-oa-preinscripcion-btn,
.flacso-oa-link-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 12px 20px;
    border-radius: 999px;
    text-decoration: none;
    font-size: 0.92rem;
    font-weight: 900;
    line-height: 1.2;
    letter-spacing: 0.035em;
    text-transform: uppercase;
    transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
}

.flacso-oa-preinscripcion-btn {
    width: 100%;
    background: var(--flacso-blue-dark);
    color: #ffffff;
    box-shadow: 0 10px 24px rgba(5, 25, 56, 0.18);
}

.flacso-oa-preinscripcion-btn:hover {
    background: var(--flacso-blue);
    color: #ffffff;
    transform: translateY(-1px);
}

.flacso-oa-cta-section {
    padding: clamp(38px, 5vw, 68px) 0;
    background: var(--flacso-blue);
    color: #ffffff;
}

.flacso-oa-cta {
    width: min(100%, 900px);
    margin-inline: auto;
    text-align: center;
}

.flacso-oa-cta p {
    margin: 0;
    color: #ffffff;
    font-size: clamp(1.06rem, 2vw, 1.35rem);
    font-weight: 650;
    line-height: 1.55;
    text-wrap: balance;
}

.flacso-oa-team-section {
    position: relative;
    padding: clamp(54px, 7vw, 96px) 0;
    background:
        radial-gradient(circle at 8% 0%, rgba(252, 209, 22, 0.16), transparent 30%),
        linear-gradient(180deg, #f8fafc 0%, #eef3f8 100%);
}

.flacso-oa-section-header {
    width: min(100%, 760px);
    margin: 0 auto clamp(30px, 5vw, 58px);
    text-align: center;
}

.flacso-oa-section-header h2 {
    margin: 0;
    color: var(--flacso-blue);
    font-size: clamp(1.75rem, 4vw, 3rem);
    font-weight: 920;
    letter-spacing: -0.04em;
    line-height: 1.05;
    text-transform: uppercase;
}

.flacso-oa-team-grid,
.flacso-oa-team-subgroups {
    display: flex;
    flex-direction: column;
    gap: clamp(38px, 5vw, 58px);
}

.flacso-oa-team-group-card {
    min-width: 0;
    height: fit-content;
}

.flacso-oa-team-group-card__title {
    margin: 0 0 clamp(18px, 3vw, 28px);
    color: var(--flacso-blue);
    font-size: clamp(1.18rem, 2vw, 1.55rem);
    font-weight: 900;
    letter-spacing: -0.025em;
    line-height: 1.15;
}

.flacso-oa-team-subgroup {
    min-width: 0;
}

.flacso-oa-team-subgroup__title {
    position: relative;
    display: flex;
    align-items: center;
    gap: 14px;
    margin: 0 0 clamp(18px, 2.6vw, 28px);
    color: var(--flacso-blue-dark);
    font-size: clamp(1.2rem, 3vw, 1.45rem);
    font-weight: 900;
    letter-spacing: -0.02em;
    line-height: 1.18;
}

.flacso-oa-team-subgroup__title::before {
    content: "";
    width: 42px;
    height: 5px;
    border-radius: 999px;
    background: var(--flacso-yellow);
    flex: 0 0 auto;
}

.flacso-oa-docentes-grid {
    display: grid;
    gap: clamp(18px, 2.4vw, 30px);
    align-items: stretch;
}

.flacso-oa-docentes-grid--nivel-1 {
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 520px), 1fr));
}

.flacso-oa-docentes-grid--nivel-2 {
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 420px), 1fr));
}

.flacso-oa-docentes-grid--nivel-3 {
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
    gap: clamp(14px, 2vw, 22px);
}

.flacso-oa-docente-item {
    min-width: 0;
}

.flacso-oa-docente-item > * {
    height: 100%;
}

.flacso-oa-docente-item .flacso-docente-card,
.flacso-oa-docente-item .dp-docente-card,
.flacso-oa-docente-item article,
.flacso-oa-docente-item .card {
    height: 100%;
    max-width: 100%;
    min-width: 0;
    word-break: normal !important;
    overflow-wrap: break-word !important;
}

.flacso-oa-person-card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    height: 100%;
    border: 1px solid rgba(15, 23, 42, 0.10);
    border-radius: 24px;
    background: #ffffff;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.08);
    overflow: hidden;
    isolation: isolate;
    transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
}

.flacso-oa-person-card::before {
    content: "";
    position: absolute;
    inset: 0;
    z-index: -1;
    pointer-events: none;
    background:
        radial-gradient(circle at 100% 0%, rgba(252, 209, 22, 0.16), transparent 34%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96));
}

.flacso-oa-person-card:hover {
    transform: translateY(-3px);
    border-color: rgba(22, 57, 112, 0.22);
    box-shadow: 0 22px 48px rgba(15, 23, 42, 0.13);
}

.flacso-oa-person-card__media,
.flacso-oa-person-card__header,
.flacso-oa-person-card__content,
.flacso-oa-person-card__header-info {
    min-width: 0;
}

.flacso-oa-person-card__header {
    display: flex;
    align-items: center;
    gap: clamp(14px, 2vw, 20px);
}

.flacso-oa-person-card__avatar-wrap,
.flacso-oa-person-card__avatar-circle {
    position: relative;
    flex: 0 0 auto;
    overflow: hidden;
    background:
        radial-gradient(circle at 30% 25%, rgba(252, 209, 22, 0.22), transparent 38%),
        #e8eef7;
}

.flacso-oa-person-card__avatar-wrap > div,
.flacso-oa-person-card__avatar-wrap .dp-docente-avatar,
.flacso-oa-person-card__avatar-wrap img,
.flacso-oa-person-card__avatar-circle > div,
.flacso-oa-person-card__avatar-circle .dp-docente-avatar,
.flacso-oa-person-card__avatar-circle img {
    width: 100% !important;
    height: 100% !important;
    display: block;
    margin: 0 !important;
    object-fit: cover !important;
}

.flacso-oa-person-card__avatar-wrap .flacso-docente-card__initials,
.flacso-oa-person-card__avatar-circle .flacso-docente-card__initials {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    background: var(--flacso-blue);
    font-weight: 900;
    letter-spacing: -0.04em;
}

.flacso-oa-person-card__role-pill {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    max-width: 100%;
    min-height: 24px;
    margin: 0 0 8px;
    padding: 5px 10px;
    border-radius: 999px;
    background: rgba(252, 209, 22, 0.26);
    color: var(--flacso-blue-dark);
    font-size: 0.72rem;
    font-weight: 900;
    line-height: 1.15;
    letter-spacing: 0.055em;
    text-transform: uppercase;
}

.flacso-oa-person-card__name {
    margin: 0;
    color: var(--flacso-blue-dark);
    font-weight: 900;
    line-height: 1.13;
    letter-spacing: -0.025em;
    text-wrap: balance;
}

.flacso-oa-person-card__academic {
    margin: 8px 0 0;
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 700;
    line-height: 1.35;
}

.flacso-oa-person-card__cv {
    color: #475569;
    font-size: 0.94rem;
    line-height: 1.62;
}

.flacso-oa-person-card__cv > *:first-child {
    margin-top: 0;
}

.flacso-oa-person-card__cv > *:last-child {
    margin-bottom: 0;
}

.flacso-oa-person-card__cv a {
    color: var(--flacso-blue);
    font-weight: 800;
    text-decoration-thickness: 2px;
    text-underline-offset: 4px;
}

.flacso-oa-person-card--nivel-1 {
    display: grid;
    grid-template-columns: minmax(180px, 230px) minmax(0, 1fr);
    min-height: 310px;
    border-radius: 28px;
    border-left: 7px solid var(--flacso-yellow);
}

.flacso-oa-person-card--nivel-1 .flacso-oa-person-card__media {
    padding: 20px 0 20px 20px;
}

.flacso-oa-person-card--nivel-1 .flacso-oa-person-card__avatar-wrap {
    width: 100%;
    aspect-ratio: 1 / 1;
    border-radius: 22px;
    box-shadow: 0 14px 30px rgba(5, 25, 56, 0.15);
}

.flacso-oa-person-card--nivel-1 .flacso-oa-person-card__avatar-wrap > div,
.flacso-oa-person-card--nivel-1 .flacso-oa-person-card__avatar-wrap .dp-docente-avatar,
.flacso-oa-person-card--nivel-1 .flacso-oa-person-card__avatar-wrap img {
    border-radius: 22px !important;
}

.flacso-oa-person-card--nivel-1 .flacso-oa-person-card__content {
    display: flex;
    flex-direction: column;
    padding: clamp(22px, 3vw, 34px);
}

.flacso-oa-person-card--nivel-1 .flacso-oa-person-card__name {
    font-size: clamp(1.32rem, 2vw, 1.75rem);
}

.flacso-oa-person-card--nivel-1 .flacso-oa-person-card__cv {
    margin-top: 18px;
    max-height: 250px;
    overflow-y: auto;
    padding-right: 10px;
}

.flacso-oa-person-card--nivel-2 {
    padding: clamp(20px, 2.4vw, 26px);
}

.flacso-oa-person-card--nivel-2 .flacso-oa-person-card__header {
    align-items: flex-start;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
}

.flacso-oa-person-card--nivel-2 .flacso-oa-person-card__avatar-circle {
    width: 86px;
    height: 86px;
    border: 4px solid #ffffff;
    border-radius: 22px;
    box-shadow: 0 10px 24px rgba(5, 25, 56, 0.12);
}

.flacso-oa-person-card--nivel-2 .flacso-oa-person-card__avatar-circle > div,
.flacso-oa-person-card--nivel-2 .flacso-oa-person-card__avatar-circle .dp-docente-avatar,
.flacso-oa-person-card--nivel-2 .flacso-oa-person-card__avatar-circle img {
    border-radius: 18px !important;
}

.flacso-oa-person-card--nivel-2 .flacso-oa-person-card__name {
    font-size: clamp(1.08rem, 1.35vw, 1.24rem);
}

.flacso-oa-person-card--nivel-2 .flacso-oa-person-card__content {
    margin-top: 16px;
    flex: 1;
}

.flacso-oa-person-card--nivel-2 .flacso-oa-person-card__cv {
    max-height: 165px;
    overflow-y: auto;
    padding-right: 8px;
    font-size: 0.91rem;
}

.flacso-oa-person-card--nivel-3 {
    justify-content: center;
    padding: clamp(16px, 2vw, 20px);
    border-radius: 20px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.065);
}

.flacso-oa-person-card--nivel-3::before {
    background:
        radial-gradient(circle at 100% 0%, rgba(22, 57, 112, 0.07), transparent 34%),
        #ffffff;
}

.flacso-oa-person-card--nivel-3 .flacso-oa-person-card__header {
    gap: 14px;
}

.flacso-oa-person-card--nivel-3 .flacso-oa-person-card__avatar-circle {
    width: 62px;
    height: 62px;
    border: 3px solid #ffffff;
    border-radius: 18px;
    box-shadow: 0 8px 18px rgba(5, 25, 56, 0.10);
}

.flacso-oa-person-card--nivel-3 .flacso-oa-person-card__avatar-circle > div,
.flacso-oa-person-card--nivel-3 .flacso-oa-person-card__avatar-circle .dp-docente-avatar,
.flacso-oa-person-card--nivel-3 .flacso-oa-person-card__avatar-circle img {
    border-radius: 15px !important;
}

.flacso-oa-person-card--nivel-3 .flacso-oa-person-card__name {
    font-size: clamp(1rem, 1.2vw, 1.12rem);
}

.flacso-oa-person-card--nivel-3 .flacso-oa-person-card__academic {
    font-size: 0.82rem;
}

.flacso-oa-person-card--nivel-3 .flacso-oa-person-card__role-pill {
    margin-bottom: 6px;
    padding: 4px 8px;
    font-size: 0.66rem;
}

.flacso-oa-person-card__cv::-webkit-scrollbar {
    width: 6px;
}

.flacso-oa-person-card__cv::-webkit-scrollbar-track {
    background: rgba(226, 232, 240, 0.78);
    border-radius: 999px;
}

.flacso-oa-person-card__cv::-webkit-scrollbar-thumb {
    background: rgba(100, 116, 139, 0.48);
    border-radius: 999px;
}

@media (max-width: 991.98px) {
    .flacso-oa-docentes-grid--nivel-1,
    .flacso-oa-docentes-grid--nivel-2 {
        grid-template-columns: minmax(0, 1fr);
    }

    .flacso-oa-docentes-grid--nivel-3 {
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr));
    }
}

@media (max-width: 675.98px) {
    .flacso-oa-docentes-grid,
    .flacso-oa-docentes-grid--nivel-1,
    .flacso-oa-docentes-grid--nivel-2,
    .flacso-oa-docentes-grid--nivel-3 {
        grid-template-columns: minmax(0, 1fr);
    }

    .flacso-oa-team-subgroup__title {
        align-items: flex-start;
        flex-direction: column;
        gap: 10px;
    }

    .flacso-oa-person-card--nivel-1 {
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    .flacso-oa-person-card--nivel-1 .flacso-oa-person-card__media {
        padding: 24px 24px 0;
    }

    .flacso-oa-person-card--nivel-1 .flacso-oa-person-card__avatar-wrap {
        aspect-ratio: 1 / 1;
        width: 100%;
        min-height: 0;
    }

    .flacso-oa-person-card--nivel-1 .flacso-oa-person-card__cv,
    .flacso-oa-person-card--nivel-2 .flacso-oa-person-card__cv {
        max-height: none;
        overflow: visible;
        padding-right: 0;
    }
}


.flacso-oa-seminarios-section {
    padding: clamp(52px, 7vw, 90px) 0;
    background: #ffffff;
}

.flacso-oa-seminarios-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: clamp(22px, 4vw, 34px);
}

.flacso-oa-seminarios-heading h2 {
    margin: 0;
    color: var(--flacso-blue);
    font-size: clamp(1.65rem, 3.5vw, 2.6rem);
    font-weight: 900;
    letter-spacing: -0.035em;
    line-height: 1.1;
}

.flacso-oa-link-btn {
    flex: 0 0 auto;
    background: var(--flacso-blue);
    color: #ffffff;
    box-shadow: 0 10px 24px rgba(22, 57, 112, 0.18);
}

.flacso-oa-link-btn:hover {
    background: var(--flacso-blue-dark);
    color: #ffffff;
    transform: translateY(-1px);
}

.flacso-oa-seminarios-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: clamp(18px, 2.6vw, 28px);
}

.flacso-oa-seminario-card {
    display: flex;
    min-height: 190px;
    flex-direction: column;
    justify-content: space-between;
    border: 1px solid var(--flacso-border);
    border-left: 6px solid var(--flacso-yellow);
    border-radius: var(--flacso-radius-md);
    background: #ffffff;
    padding: clamp(20px, 3vw, 28px);
    box-shadow: var(--flacso-shadow-sm);
}

.flacso-oa-seminario-card h3 {
    margin: 0 0 18px;
    font-size: clamp(1.05rem, 1.7vw, 1.25rem);
    font-weight: 850;
    line-height: 1.35;
}

.flacso-oa-seminario-card h3 a {
    color: var(--flacso-blue-dark);
    text-decoration: none;
}

.flacso-oa-seminario-card h3 a:hover {
    color: var(--flacso-blue);
    text-decoration: underline;
    text-underline-offset: 4px;
}

.flacso-oa-seminario-card__date {
    margin: auto 0 0;
    color: var(--flacso-muted);
    font-size: 0.94rem;
    font-weight: 700;
}

.flacso-oferta-responsive .kt-accordion-pane {
    border: 1px solid var(--flacso-border);
    margin-bottom: 12px;
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
    overflow: hidden;
}

.flacso-oferta-responsive .kt-blocks-accordion-header {
    width: 100%;
    border: 0;
    background: var(--flacso-blue);
    color: #ffffff;
    padding: 18px 22px;
    font-size: 1rem;
    font-weight: 850;
    line-height: 1.25;
    text-align: left;
}

.flacso-oferta-responsive .kt-accordion-pane-active .kt-blocks-accordion-header {
    background: var(--flacso-yellow);
    color: var(--flacso-blue-dark);
}

@media (min-width: 700px) {
    .flacso-oferta-responsive .flacso-oa-consulta__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (min-width: 992px) {
    .flacso-oa-main-grid {
        grid-template-columns: minmax(0, 1fr) minmax(320px, 390px);
    }

    .flacso-oa-aside__sticky {
        position: sticky;
        top: calc(var(--wp-admin--admin-bar--height, 0px) + 24px);
    }
}

@media (min-width: 1200px) {
    .flacso-oa-main-grid {
        grid-template-columns: minmax(0, 1fr) minmax(360px, 420px);
    }
}

@media (max-width: 1199.98px) {
    .flacso-oa-team-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 991.98px) {
    .flacso-oa-hero-v3__intro {
        grid-template-columns: minmax(0, 1fr);
        align-items: start;
    }

    .flacso-oa-content-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .flacso-oa-content-grid > .flacso-oa-content-card,
    .flacso-oa-content-grid > .flacso-oa-content-card--wide {
        grid-column: 1 / -1;
    }

    .flacso-oa-hero-v3__summary {
        width: min(100%, 680px);
    }

    .flacso-oa-aside {
        order: -1;
    }

    .flacso-oa-form-panel {
        max-width: 760px;
        margin-inline: auto;
    }

    .flacso-oa-seminarios-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {

    .flacso-oa-hero-v3 {
        padding-top: 34px;
    }

    .flacso-oa-hero-v3__title {
        letter-spacing: -0.045em;
    }

    .flacso-oa-hero-v3__media {
        min-height: 430px;
    }

    .flacso-oa-hero-v3__media::after {
        background:
            linear-gradient(180deg, rgba(5, 25, 56, 0.62) 0%, rgba(5, 25, 56, 0.84) 100%);
    }

    .flacso-oa-seminarios-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .flacso-oa-link-btn {
        width: 100%;
    }

    .flacso-oa-seminarios-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 675.98px) {
    .flacso-oa-docentes-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 575.98px) {
    .flacso-oa-hero-v3__meta {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
    }

    .flacso-oa-hero-v3__meta span {
        justify-content: center;
        text-align: center;
    }

    .flacso-oa-hero-v3__media,
    .flacso-oa-hero-v3__summary,
    .flacso-oa-content-card,
    .flacso-oa-info-card,
    .flacso-oa-form-panel,
    .flacso-oa-seminario-card,
    .flacso-oa-team-group-card {
        border-radius: 18px;
    }
}

    .flacso-inscripciones-banner {
        position: relative;
        width: 100%;
        aspect-ratio: auto;
        overflow: hidden;
        border-radius: var(--flacso-radius-md);
        font-family: "Inter", "Helvetica Neue", Helvetica, Arial, sans-serif;
        margin-bottom: clamp(26px, 5vw, 56px);
    }

    .flacso-inscripciones-banner::before {
        content: "";
        position: absolute;
        inset: 0;
        z-index: 1;
        background: linear-gradient(
            to right,
            rgba(5, 25, 56, 0.85) 0%,
            rgba(5, 25, 56, 0.35) 60%,
            rgba(5, 25, 56, 0.2) 100%
        );
        pointer-events: none;
    }

    .flacso-inscripciones-banner__img {
        position: relative;
        z-index: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        filter: brightness(0.9);
    }

    .flacso-inscripciones-banner__overlay {
        position: absolute;
        inset: 0;
        z-index: 2;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 1.4rem 1.8rem;
        color: #fff;
        text-shadow: 0 3px 8px rgba(0, 0, 0, 0.6);
        gap: 0.8rem;
    }

    .flacso-inscripciones-banner__top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.9rem;
    }

    .flacso-inscripciones-banner__tag {
        font-weight: 700;
        font-size: clamp(1.1rem, 2.1vw, 1.6rem);
        line-height: 1.1;
    }

    .flacso-inscripciones-banner__logo {
        max-width: clamp(180px, 22vw, 260px);
        height: auto;
        flex: 0 0 auto;
    }

    .flacso-inscripciones-banner__bottom {
        display: flex;
        justify-content: center;
        align-items: center;
        text-align: center;
        margin-bottom: 1.1rem;
    }

    .flacso-inscripciones-banner__cta {
        font-size: clamp(1.05rem, 2.2vw, 1.45rem);
        font-weight: 700;
        line-height: 1.25;
        white-space: nowrap;
    }

    @media (max-width: 900px) {
        .flacso-inscripciones-banner__overlay {
            padding: 1.05rem 1.25rem;
        }

        .flacso-inscripciones-banner__bottom {
            margin-bottom: 0.8rem;
        }

        .flacso-inscripciones-banner__cta {
            font-size: 1rem;
            white-space: normal;
        }
    }

    @media (max-width: 600px) {
        .flacso-inscripciones-banner {
            aspect-ratio: auto;
        }

        .flacso-inscripciones-banner__overlay {
            padding: 0.9rem 1rem;
            gap: 0.7rem;
        }

        .flacso-inscripciones-banner__top {
            align-items: center;
            flex-wrap: wrap;
        }

        .flacso-inscripciones-banner__tag {
            font-size: 1.05rem;
        }

        .flacso-inscripciones-banner__logo {
            max-width: 140px;
        }

        .flacso-inscripciones-banner__bottom {
            margin-bottom: 0.5rem;
        }

        .flacso-inscripciones-banner__cta {
            font-size: 0.95rem;
            line-height: 1.2;
        }
    }

    @media (max-width: 420px) {
        .flacso-inscripciones-banner {
            aspect-ratio: auto;
        }

        .flacso-inscripciones-banner__logo {
            max-width: 120px;
        }

        .flacso-inscripciones-banner__cta {
            font-size: 0.9rem;
        }
    }


/* Ajustes mínimos para mejorar conversión y lectura en teléfonos */
.flacso-inscripciones-banner__button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: fit-content;
    min-height: 48px;
    max-width: 100%;
    padding: 12px 20px;
    border-radius: 999px;
    background: var(--flacso-yellow);
    color: var(--flacso-blue-dark);
    font-size: 0.92rem;
    font-weight: 900;
    line-height: 1.15;
    letter-spacing: 0.035em;
    text-align: center;
    text-decoration: none;
    text-shadow: none;
    text-transform: uppercase;
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.24);
    transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease;
}

.flacso-inscripciones-banner__button:hover,
.flacso-inscripciones-banner__button:focus {
    background: #ffffff;
    color: var(--flacso-blue-dark);
    transform: translateY(-1px);
}

@media (max-width: 600px) {
    .flacso-oferta-responsive .flacso-oa-container[style] {
        padding-top: 14px !important;
        padding-bottom: 12px !important;
    }

    .flacso-inscripciones-banner {
        min-height: min(540px, 86svh) !important;
        border-radius: 18px !important;
    }

    .flacso-inscripciones-banner::before {
        background:
            linear-gradient(180deg, rgba(5, 25, 56, 0.58) 0%, rgba(5, 25, 56, 0.88) 100%) !important;
    }

    .flacso-inscripciones-banner__overlay {
        padding: 1rem !important;
        gap: 0.85rem !important;
    }

    .flacso-inscripciones-banner__top {
        align-items: center !important;
        gap: 0.65rem !important;
    }

    .flacso-inscripciones-banner__tag {
        padding: 4px 9px !important;
        font-size: 0.72rem !important;
        line-height: 1.1 !important;
        letter-spacing: 0.045em !important;
    }

    .flacso-inscripciones-banner__logo {
        max-width: 128px !important;
    }

    .flacso-inscripciones-banner__middle {
        justify-content: flex-end !important;
        margin: 0.9rem 0 !important;
    }

    .flacso-inscripciones-banner__eyebrow {
        margin-bottom: 8px !important;
        font-size: 0.78rem !important;
        line-height: 1.2 !important;
        letter-spacing: 0.055em !important;
    }

    .flacso-inscripciones-banner__title {
        font-size: clamp(1.55rem, 8vw, 2.15rem) !important;
        line-height: 1.08 !important;
        letter-spacing: -0.03em !important;
    }

    .flacso-inscripciones-banner__excerpt {
        display: none !important;
    }

    .flacso-inscripciones-banner__meta {
        width: 100%;
        gap: 0.5rem !important;
        margin-top: 14px !important;
    }

    .flacso-inscripciones-banner__meta span {
        width: 100%;
        justify-content: center;
        padding: 5px 10px !important;
        font-size: 0.82rem !important;
        line-height: 1.2 !important;
        text-align: center;
    }

    .flacso-inscripciones-banner__bottom {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr);
        gap: 0.75rem !important;
        align-items: stretch !important;
        margin-bottom: 0 !important;
        text-align: left !important;
    }

    .flacso-inscripciones-banner__cta {
        font-size: 0.92rem !important;
        line-height: 1.25 !important;
        white-space: normal !important;
    }

    .flacso-inscripciones-banner__button {
        width: 100%;
        min-height: 50px;
        padding-inline: 14px;
        font-size: 0.88rem;
    }

    .flacso-oa-main-section {
        padding-top: 24px;
        padding-bottom: 42px;
    }

    .flacso-oa-next-start--fullwidth {
        margin-top: 0 !important;
        margin-bottom: 1.25rem !important;
    }

    .flacso-oa-form-panel__header {
        padding: 20px 16px 12px;
    }

    .flacso-oa-form-panel__body {
        padding: 0 16px 18px;
    }

    .flacso-oferta-responsive .flacso-oa-consulta__field,
    .flacso-oferta-responsive .flacso-consultas-formulario .form-group {
        margin-bottom: 12px;
    }

    .flacso-oferta-responsive .flacso-oa-consulta__field input,
    .flacso-oferta-responsive .flacso-oa-consulta__field textarea,
    .flacso-oferta-responsive .flacso-oa-consulta__field select,
    .flacso-oferta-responsive .flacso-consultas-formulario input,
    .flacso-oferta-responsive .flacso-consultas-formulario textarea,
    .flacso-oferta-responsive .flacso-consultas-formulario select {
        min-height: 48px;
        font-size: 16px;
    }

    .flacso-oa-content-card__body,
    .flacso-oa-info-card__body {
        padding: 18px 16px;
        line-height: 1.62;
    }

    .flacso-oa-team-section,
    .flacso-oa-seminarios-section {
        padding: 40px 0;
    }
}

@media (max-width: 420px) {
    .flacso-inscripciones-banner {
        min-height: min(510px, 88svh) !important;
    }

    .flacso-inscripciones-banner__logo {
        max-width: 116px !important;
    }

    .flacso-inscripciones-banner__title {
        font-size: clamp(1.42rem, 8.5vw, 1.95rem) !important;
    }
}


@media (prefers-reduced-motion: reduce) {
    .flacso-oferta-responsive *,
    .flacso-oferta-responsive *::before,
    .flacso-oferta-responsive *::after {
        scroll-behavior: auto !important;
        transition-duration: 0.01ms !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
    }
}
</style>

<?php
get_footer();
