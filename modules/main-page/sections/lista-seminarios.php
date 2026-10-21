<?php

if (!defined('ABSPATH')) {
    exit;
}

if (shortcode_exists('lista_seminarios')) {
    remove_shortcode('lista_seminarios');
}

function flacso_lista_seminarios_render($atts): string {
    $atts = shortcode_atts([
        'posts_per_page' => 6,
        'mostrar_fechas' => true,
        'mostrar_boton' => true,
        'texto_boton' => __('Ver más información', 'flacso-main-page'),
    ], $atts, 'lista_seminarios');

    return '<div class="seminarios-wrapper"><div class="seminarios-container">'
        . flacso_generar_seminarios_combinados_html(0, $atts)
        . '</div></div>';
}

function flacso_generar_seminarios_combinados_html(int $offset = 0, array $atts = []): string {
    if (!class_exists('FLACSO_Academic_Repository') || !class_exists('FLACSO_Academic_Catalog')) {
        return '';
    }
    $limit = max(1, absint($atts['posts_per_page'] ?? 6));
    $show_dates = rest_sanitize_boolean($atts['mostrar_fechas'] ?? true);
    $show_button = rest_sanitize_boolean($atts['mostrar_boton'] ?? true);
    $button_text = sanitize_text_field((string) ($atts['texto_boton'] ?? __('Ver más información', 'flacso-main-page')));

    // Filtrar y deduplicar ANTES de paginar. El estado de cada Edición
    // prevalece sobre su fecha futura y sobre la preinscripción habilitada.
    $eligible = [];
    $seen = [];
    $seminars = [];
    foreach (FLACSO_Academic_Repository::list('ediciones', ['per_page' => 200]) as $edition) {
        if (!FLACSO_Academic_Catalog::is_public_current_edition($edition)) {
            continue;
        }
        $seminar_id = absint($edition['seminario_id'] ?? 0);
        if ($seminar_id < 1 || isset($seen[$seminar_id])) {
            continue;
        }
        if (!isset($seminars[$seminar_id])) {
            $seminars[$seminar_id] = FLACSO_Academic_Catalog::get_seminar($seminar_id);
        }
        $seminar = $seminars[$seminar_id];
        if (!$seminar || absint($seminar['edicion_vigente']['id'] ?? 0) !== absint($edition['id'] ?? 0)) {
            continue;
        }
        $eligible[] = ['edition' => $edition, 'seminar' => $seminar];
        $seen[$seminar_id] = true;
    }

    $cards = [];
    foreach (array_slice($eligible, max(0, $offset), $limit) as $item) {
        $edition = $item['edition'];
        $seminar = $item['seminar'];
        $seminar_id = absint($edition['seminario_id']);
        $date = '';
        if ($show_dates && !empty($edition['fecha_inicio'])) {
            $date = sprintf(
                '<p class="seminario-card__fecha"><time datetime="%1$s">%2$s</time></p>',
                esc_attr($edition['fecha_inicio']),
                esc_html(date_i18n('j \d\e F \d\e Y', strtotime($edition['fecha_inicio'])))
            );
        }
        $button = $show_button
            ? '<a class="btn btn-primary" href="' . esc_url(get_permalink($seminar_id)) . '">' . esc_html($button_text) . '</a>'
            : '';
        $image = !empty($seminar['imagen'])
            ? '<img src="' . esc_url($seminar['imagen']) . '" alt="" loading="lazy">'
            : '';
        $cards[] = '<article class="seminario-card">'
            . $image
            . '<div class="seminario-card__contenido"><h3>' . esc_html($seminar['nombre']) . '</h3>'
            . $date
            . '<p>' . esc_html(wp_trim_words($seminar['resumen'], 24)) . '</p>'
            . $button
            . '</div></article>';
    }
    return $cards ? '<div class="seminarios-grid">' . implode('', $cards) . '</div>' : '';
}

add_shortcode('lista_seminarios', 'flacso_lista_seminarios_render');
