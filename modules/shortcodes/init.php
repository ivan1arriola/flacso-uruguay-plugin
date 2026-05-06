<?php
/**
 * Módulo de Shortcodes - FLACSO Uruguay
 * Integración de FLACSO Shortcodes Cartas
 */

if (!defined('ABSPATH')) {
    exit;
}

// Cargar archivos del módulo
flacso_safe_require('modules/shortcodes/includes/pdf-proxy.php');
flacso_safe_require('modules/shortcodes/includes/shortcodes-documentos.php');
flacso_safe_require('modules/shortcodes/includes/shortcodes-programa.php');
flacso_safe_require('modules/shortcodes/includes/shortcodes-precios.php');
flacso_safe_require('modules/shortcodes/includes/shortcode-calendario-maestriagenero.php');

// ============================================
// Funciones del módulo
// ============================================

function flacso_shortcodes_get_catalog() {
    return array(
        array(
            'tag'   => 'programa_calendario_malla',
            'desc'  => __('Genera botones directos para calendario y malla curricular.', 'flacso-uruguay'),
            'attrs' => array(
                'url_calendario'   => __('URL del calendario (obligatorio para mostrar botón).', 'flacso-uruguay'),
                'url_malla'        => __('URL de la malla curricular.', 'flacso-uruguay'),
                'texto_calendario' => __('Texto del botón de calendario.', 'flacso-uruguay'),
                'texto_malla'      => __('Texto del botón de malla.', 'flacso-uruguay'),
                'clase'            => __('Clases CSS opcionales.', 'flacso-uruguay'),
            ),
        ),
        array(
            'tag'   => 'programa_iape_calendario_malla',
            'desc'  => __('Bloque especial para IAPE con PDF externo y tabla interna.', 'flacso-uruguay'),
            'attrs' => array(
                'url_calendario'   => __('URL para el calendario.', 'flacso-uruguay'),
                'texto_calendario' => __('Texto del botón.', 'flacso-uruguay'),
            ),
        ),
        array(
            'tag'   => 'programa_hero',
            'desc'  => __('Hero responsivo con breadcrumbs y datos del programa.', 'flacso-uruguay'),
            'attrs' => array(
                'id'                           => __('ID de la página del programa (obligatorio).', 'flacso-uruguay'),
                'abreviacion'                  => __('Abreviatura del programa.', 'flacso-uruguay'),
                'cohorte'                      => __('Nombre de cohorte.', 'flacso-uruguay'),
                'anio'                         => __('Año mostrado junto a la abreviatura.', 'flacso-uruguay'),
                'menciones_en'                 => __('Lista separada por "|" para las menciones.', 'flacso-uruguay'),
                'orientaciones'                => __('Lista separada por "|" para las orientaciones.', 'flacso-uruguay'),
                'mensaje_bienvenida'           => __('Texto de bienvenida superior.', 'flacso-uruguay'),
                'reconocido_mec'               => __('true/false para mostrar insignia MEC.', 'flacso-uruguay'),
                'reconocimiento_internacional' => __('true/false para la titulación apostillada.', 'flacso-uruguay'),
            ),
        ),
        array(
            'tag'   => 'programa_info_clave',
            'desc'  => __('Tarjetas con inicio, duración y modalidad.', 'flacso-uruguay'),
            'attrs' => array(
                'proximo_inicio' => __('Texto del próximo inicio.', 'flacso-uruguay'),
                'duracion'       => __('Texto de duración.', 'flacso-uruguay'),
            ),
        ),
        array(
            'tag'   => 'programa_info_importante',
            'desc'  => __('Lista de información financiera y logística.', 'flacso-uruguay'),
            'attrs' => array(),
        ),
        array(
            'tag'   => 'requisitos_admision',
            'desc'  => __('Requisitos dinámicos según la página padre.', 'flacso-uruguay'),
            'attrs' => array(),
        ),
        array(
            'tag'   => 'mas_info_flacso',
            'desc'  => __('Bloque "Más Información" con tres columnas.', 'flacso-uruguay'),
            'attrs' => array(),
        ),
        array(
            'tag'   => 'asistente_academico',
            'desc'  => __('Ficha del asistente académico asignado.', 'flacso-uruguay'),
            'attrs' => array(
                'slug'   => __('Slug del CPT docente (obligatorio).', 'flacso-uruguay'),
                'correo' => __('Correo de contacto (default inscripciones@flacso.edu.uy).', 'flacso-uruguay'),
                'titulo' => __('Rol a mostrar (ej. Asistente Académico/a).', 'flacso-uruguay'),
            ),
        ),
        array(
            'tag'   => 'programa_preinscripciones',
            'desc'  => __('CTA hacia /preinscripcion del programa padre.', 'flacso-uruguay'),
            'attrs' => array(),
        ),
        array(
            'tag'   => 'programa_volver_pagina_principal',
            'desc'  => __('Breadcrumb reemplazando el botón "Volver".', 'flacso-uruguay'),
            'attrs' => array(),
        ),
        array(
            'tag'   => 'maestria_precios',
            'desc'  => __('Tabla de precios para maestrías.', 'flacso-uruguay'),
            'attrs' => array(
                'id' => __('ID de página para resolver la tabla automáticamente (opcional).', 'flacso-uruguay'),
            ),
        ),
        array(
            'tag'   => 'egccyd_precios / diplomado_especializacion_precios',
            'desc'  => __('Tabla genérica de diplomados/Especialización.', 'flacso-uruguay'),
            'attrs' => array(
                'id' => __('ID de página para resolver la tabla automáticamente (opcional).', 'flacso-uruguay'),
            ),
        ),
        array(
            'tag'   => 'eapet_precios, diplomas_precios, iape_precios, subjetividad_precios',
            'desc'  => __('Tablas específicas para cada programa.', 'flacso-uruguay'),
            'attrs' => array(
                'id' => __('ID de página para resolver la tabla automáticamente (opcional).', 'flacso-uruguay'),
            ),
        ),
        array(
            'tag'   => 'calendario_maestriagenero',
            'desc'  => __('Acordeón con calendario de maestría en género (3 ciclos).', 'flacso-uruguay'),
            'attrs' => array(
                'c1'      => __('Mostrar Ciclo 1 (true/false). Default: true.', 'flacso-uruguay'),
                'c2'      => __('Mostrar Ciclo 2 (true/false). Default: true.', 'flacso-uruguay'),
                'c3'      => __('Mostrar Ciclo 3 (true/false). Default: true.', 'flacso-uruguay'),
                'abierto' => __('Abrir acordeón al cargar (true/false). Default: false.', 'flacso-uruguay'),
                'clase'   => __('Clases CSS adicionales.', 'flacso-uruguay'),
            ),
        ),
    );
}

function flacso_shortcodes_register_settings() {
    register_setting(
        'flacso_shortcodes_prices',
        'flacso_price_tables',
        array(
            'sanitize_callback' => 'flacso_shortcodes_sanitize_price_tables',
        )
    );

    register_setting(
        'flacso_shortcodes_calendar',
        'flacso_cal_maestriagenero_data',
        array(
            'sanitize_callback' => 'flacso_shortcodes_sanitize_cal_maestriagenero_data',
        )
    );
}

function flacso_shortcodes_get_default_cal_maestriagenero_data() {
    $default_items = flacso_shortcodes_get_default_cal_maestriagenero_items();

    return array(
        'c1_title' => 'Diploma en Género – Cohorte VII',
        'c2_title' => 'Ciclo de Especialización en Género',
        'c3_title' => 'Maestría en Género',
        'c1_items' => $default_items['c1_items'],
        'c2_docs' => array(
            array('titulo' => 'Diplomado de Especialización en Género – Políticas Públicas Integrales', 'cohorte' => 'Cohorte IV', 'id' => '1lDjCXUbXyYDD54csNS5Zg2e00olbIceX', 'url' => ''),
            array('titulo' => 'Diplomado de Especialización en Género – Violencia basada en Género', 'cohorte' => 'Cohorte IV', 'id' => '1gCf9xZsHI4VX9oiEeUOCpQRhZEqVnbP1', 'url' => ''),
            array('titulo' => 'Diplomado de Especialización en Género – Salud Integral', 'cohorte' => 'Cohorte III', 'id' => '1IngR8XqRwm9MX2JpBhNvtqMWfMxfUOwz', 'url' => ''),
            array('titulo' => 'Especialización en Género, Cambio Climático y Desastres', 'cohorte' => 'Cohorte V', 'id' => '1s39lb_-yB4lUPPEP7dlqSvsJNuPW7nrm', 'url' => ''),
        ),
        'c3_items' => $default_items['c3_items'],
    );
}

function flacso_shortcodes_get_default_cal_maestriagenero_items() {
    return array(
        'c1_items' => array(
            array('eje' => 'Temático Común', 'nota' => 'Lo cursa todo el grupo', 'semana' => '0', 'nombre' => 'Bienvenida y presentación', 'periodo' => '08 al 14 de abril', 'sesiones' => array(array('fecha' => 'sábado  11 de abril', 'hora' => '10:00 a 12:00 horas (Uruguay)'))),
            array('eje' => 'Temático Común', 'nota' => 'Lo cursa todo el grupo', 'semana' => '1, 2, 3, 4', 'nombre' => 'Género, interseccionalidad, igualdad y no discriminación', 'periodo' => '15 de abril al 12 de mayo', 'sesiones' => array(array('fecha' => 'sábado 25 de abril', 'hora' => '10:00 a 12:00 horas (Uruguay)'))),
            array('eje' => '*Temático Específico — Políticas Públicas Integrales', 'nota' => 'Lo cursa quien ha elegido este eje', 'semana' => '5,6,7,8,9', 'nombre' => 'Políticas Públicas con enfoque de Género e Interseccionalidad', 'periodo' => '13 de mayo al 16 de junio', 'sesiones' => array(array('fecha' => 'sábados  16 de mayo y  13 de junio', 'hora' => '10:00 a 12:00 horas (Uruguay)'))),
            array('eje' => '*Temático Específico — Violencia basada en Género', 'nota' => 'Lo cursa quien ha elegido este eje', 'semana' => '5,6,7', 'nombre' => 'Aproximación a la Violencia Basada en Género', 'periodo' => '13 de mayo al 02 de junio', 'sesiones' => array(array('fecha' => 'sábado  23 de mayo', 'hora' => '10:00 a 12:00 horas (Uruguay)'))),
            array('eje' => '*Temático Específico — Violencia basada en Género', 'nota' => '(continuación)', 'semana' => '8,9', 'nombre' => 'Violencia en las relaciones de pareja. Principios rectores para la intervención', 'periodo' => '03 de junio al 16 de junio', 'sesiones' => array(array('fecha' => 'sábado 13 de junio', 'hora' => '10:00 a 12:00 horas (Uruguay)'))),
            array('eje' => '*Temático Específico — Cambio Climático y Desastres', 'nota' => 'Lo cursa quien ha elegido este eje', 'semana' => '5 y 6', 'nombre' => 'Género y Desarrollo', 'periodo' => '13 al 26 de mayo', 'sesiones' => array(array('fecha' => 'sábado 23 de mayo', 'hora' => '10:00 a 12:00 horas (Uruguay)'))),
            array('eje' => '*Temático Específico — Cambio Climático y Desastres', 'nota' => '(continuación)', 'semana' => '7,8,9', 'nombre' => 'De las palabras a la acción: implementación género responsiva en la agenda climática', 'periodo' => '27 de mayo al 16 de junio', 'sesiones' => array(array('fecha' => 'sábado 06 de junio', 'hora' => '10:00 a 12:00 horas (Uruguay)'))),
            array('eje' => '*Salud Integral', 'nota' => 'Lo cursa quien ha elegido este eje', 'semana' => '5,6', 'nombre' => 'Introducción a género, interseccionalidad y salud', 'periodo' => '13 al 26 de mayo', 'sesiones' => array(array('fecha' => 'sábado 23 de mayo', 'hora' => '10:00 a 12:00 horas (Uruguay)'))),
            array('eje' => '*Salud Integral', 'nota' => '(continuación)', 'semana' => '7,8,9', 'nombre' => 'Género, interseccionalidad y determinación social de la salud', 'periodo' => '27 de mayo al 16 de junio', 'sesiones' => array(array('fecha' => 'sábado 06 de junio', 'hora' => '10:00 a 12:00 horas (Uruguay)'), array('fecha' => 'jueves 11 de junio', 'hora' => '18:00 a 20:00 horas (Uruguay)'))),
            array('eje' => 'Metodológico', 'nota' => 'Lo cursa todo el grupo', 'semana' => '9,10', 'nombre' => 'Seminario de metodología', 'periodo' => '17 al 30 de junio', 'sesiones' => array(array('fecha' => 'sábado 27 de junio', 'hora' => '10:00 a 12:00 horas (Uruguay)'))),
            array('eje' => 'Metodológico', 'nota' => 'Lo cursa todo el grupo', 'semana' => '11,12', 'nombre' => 'Talleres de Metodología', 'periodo' => '1 al 14 de julio', 'sesiones' => array(array('fecha' => 'sábado 4 y sábado 11 de julio', 'hora' => '10:00 a 12:00 horas (Uruguay)'))),
            array('eje' => 'Entrega del Trabajo Final', 'nota' => '', 'semana' => '13', 'nombre' => '21 de julio', 'periodo' => '', 'sesiones' => array()),
        ),
        'c3_items' => array(
            array('eje' => 'Común', 'nota' => 'Lo cursa todo el grupo', 'semana' => '—', 'nombre' => 'Clase de bienvenida', 'periodo' => 'Abril 2027', 'sesiones' => array(array('fecha' => 'a confirmar', 'hora' => 'a confirmar'))),
            array('eje' => 'Metodológico', 'nota' => 'Lo cursa todo el grupo', 'semana' => '—', 'nombre' => 'Seminario Metodológico III', 'periodo' => 'Abril y Mayo 2027', 'sesiones' => array(array('fecha' => 'a confirmar', 'hora' => 'a confirmar'))),
            array('eje' => '*Temático Específico — Políticas Públicas Integrales', 'nota' => 'Lo cursa quien ha elegido este eje', 'semana' => '—', 'nombre' => 'Construcción e interpretación de indicadores con PEG e interseccionalidad', 'periodo' => 'Junio 2027', 'sesiones' => array(array('fecha' => 'a confirmar', 'hora' => 'a confirmar'))),
            array('eje' => '*Temático Específico — Violencia basada en Género', 'nota' => 'Lo cursa quien ha elegido este eje', 'semana' => '—', 'nombre' => 'Políticas Públicas sobre violencia basada en género I', 'periodo' => 'Junio 2027', 'sesiones' => array(array('fecha' => 'a confirmar', 'hora' => 'a confirmar'))),
            array('eje' => '*Temático Específico — Cambio Climático y Desastres', 'nota' => 'Lo cursa quien ha elegido este eje', 'semana' => '—', 'nombre' => 'Género en el marco de los acuerdos multilaterales de cambio climático y gestión del riesgo de desastres', 'periodo' => 'Junio 2027', 'sesiones' => array(array('fecha' => 'a confirmar', 'hora' => 'a confirmar'))),
            array('eje' => '*Salud Integral', 'nota' => 'Lo cursa quien ha elegido este eje', 'semana' => '—', 'nombre' => 'Ciencia y medicina como tecnología de género', 'periodo' => 'Junio 2027', 'sesiones' => array(array('fecha' => 'a confirmar', 'hora' => 'a confirmar'))),
            array('eje' => 'Metodológico', 'nota' => 'Lo cursa todo el grupo', 'semana' => '—', 'nombre' => 'Seminario Metodológico IV', 'periodo' => 'Julio 2027', 'sesiones' => array(array('fecha' => 'a confirmar', 'hora' => 'a confirmar'))),
            array('eje' => 'Metodológico', 'nota' => '', 'semana' => '—', 'nombre' => 'Taller de Tesis I', 'periodo' => 'Agosto a Noviembre 2027', 'sesiones' => array(array('fecha' => 'a confirmar', 'hora' => 'a confirmar'))),
            array('eje' => 'Libre elección', 'nota' => 'Lo cursa todo el grupo', 'semana' => '—', 'nombre' => 'Un seminario a elección', 'periodo' => 'Septiembre 2027', 'sesiones' => array(array('fecha' => 'a confirmar', 'hora' => 'a confirmar'))),
            array('eje' => 'Metodológico', 'nota' => '', 'semana' => '—', 'nombre' => 'Un seminario a elección', 'periodo' => 'Noviembre 2027', 'sesiones' => array(array('fecha' => 'a confirmar', 'hora' => 'a confirmar'))),
            array('eje' => 'Metodológico', 'nota' => 'Lo cursa todo el grupo', 'semana' => '—', 'nombre' => 'Taller de Tesis II', 'periodo' => 'Febrero a abril 2028', 'sesiones' => array(array('fecha' => 'a confirmar', 'hora' => 'a confirmar'))),
            array('eje' => 'Entrega del borrador final de tesis', 'nota' => '', 'semana' => 'ABRIL 2028', 'nombre' => 'Entrega del borrador final de tesis', 'periodo' => 'ABRIL 2028', 'sesiones' => array()),
        ),
    );
}

function flacso_shortcodes_get_cal_maestriagenero_data() {
    $defaults = flacso_shortcodes_get_default_cal_maestriagenero_data();
    $saved = get_option('flacso_cal_maestriagenero_data', array());

    if (!is_array($saved)) {
        return $defaults;
    }

    $out = wp_parse_args($saved, $defaults);

    if (!isset($out['c2_docs']) || !is_array($out['c2_docs'])) {
        $out['c2_docs'] = $defaults['c2_docs'];
    }
    if (!isset($out['c1_items']) || !is_array($out['c1_items']) || empty($out['c1_items'])) {
        $out['c1_items'] = $defaults['c1_items'];
    }
    if (!isset($out['c3_items']) || !is_array($out['c3_items']) || empty($out['c3_items'])) {
        $out['c3_items'] = $defaults['c3_items'];
    }

    return $out;
}

function flacso_shortcodes_sanitize_calendar_items($items) {
    $output = array();

    if (empty($items) || !is_array($items)) {
        return $output;
    }

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $eje = isset($item['eje']) ? sanitize_text_field($item['eje']) : '';
        $nota = isset($item['nota']) ? sanitize_text_field($item['nota']) : '';
        $semana = isset($item['semana']) ? sanitize_text_field($item['semana']) : '';
        $nombre = isset($item['nombre']) ? sanitize_text_field($item['nombre']) : '';
        $periodo = isset($item['periodo']) ? sanitize_text_field($item['periodo']) : '';

        $sesiones_out = array();
        if (!empty($item['sesiones']) && is_array($item['sesiones'])) {
            foreach ($item['sesiones'] as $sesion) {
                if (!is_array($sesion)) {
                    continue;
                }

                $fecha = isset($sesion['fecha']) ? sanitize_text_field($sesion['fecha']) : '';
                $hora = isset($sesion['hora']) ? sanitize_text_field($sesion['hora']) : '';

                if ($fecha === '' && $hora === '') {
                    continue;
                }

                $sesiones_out[] = array(
                    'fecha' => $fecha,
                    'hora' => $hora,
                );
            }
        }

        if ($eje === '' && $nota === '' && $semana === '' && $nombre === '' && $periodo === '' && empty($sesiones_out)) {
            continue;
        }

        $output[] = array(
            'eje' => $eje,
            'nota' => $nota,
            'semana' => $semana,
            'nombre' => $nombre,
            'periodo' => $periodo,
            'sesiones' => $sesiones_out,
        );
    }

    return $output;
}

function flacso_shortcodes_sanitize_cal_maestriagenero_data($input) {
    $current = flacso_shortcodes_get_cal_maestriagenero_data();
    $output  = $current;

    $output['c1_title'] = isset($input['c1_title']) ? sanitize_text_field($input['c1_title']) : $current['c1_title'];
    $output['c2_title'] = isset($input['c2_title']) ? sanitize_text_field($input['c2_title']) : $current['c2_title'];
    $output['c3_title'] = isset($input['c3_title']) ? sanitize_text_field($input['c3_title']) : $current['c3_title'];

    $output['c2_docs'] = array();
    if (!empty($input['c2_docs']) && is_array($input['c2_docs'])) {
        foreach ($input['c2_docs'] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $titulo = isset($row['titulo']) ? sanitize_text_field($row['titulo']) : '';
            $cohorte = isset($row['cohorte']) ? sanitize_text_field($row['cohorte']) : '';
            $id = isset($row['id']) ? preg_replace('~[^a-zA-Z0-9_-]~', '', (string) $row['id']) : '';
            $url = isset($row['url']) ? esc_url_raw($row['url']) : '';

            if ($titulo === '' && $cohorte === '' && $id === '' && $url === '') {
                continue;
            }

            $output['c2_docs'][] = array(
                'titulo' => $titulo,
                'cohorte' => $cohorte,
                'id' => $id,
                'url' => $url,
            );
        }
    }

    if (empty($output['c2_docs'])) {
        $output['c2_docs'] = $current['c2_docs'];
    }

    if (array_key_exists('c1_items', $input) && is_array($input['c1_items'])) {
        $output['c1_items'] = flacso_shortcodes_sanitize_calendar_items($input['c1_items']);
    }

    if (array_key_exists('c3_items', $input) && is_array($input['c3_items'])) {
        $output['c3_items'] = flacso_shortcodes_sanitize_calendar_items($input['c3_items']);
    }

    // Compatibilidad legacy: si aún llega JSON desde un formulario viejo.
    $json_fields = array('c1_items_json' => 'c1_items', 'c3_items_json' => 'c3_items');
    foreach ($json_fields as $json_key => $target_key) {
        if (!array_key_exists($json_key, $input)) {
            continue;
        }

        $raw = trim((string) $input[$json_key]);
        if ($raw === '') {
            continue;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $output[$target_key] = flacso_shortcodes_sanitize_calendar_items($decoded);
        }
    }

    return $output;
}

function flacso_shortcodes_cal_doc_preview_url($row) {
    if (!is_array($row)) {
        return '';
    }

    $source = '';

    if (!empty($row['url'])) {
        $source = esc_url_raw((string) $row['url']);
    } elseif (!empty($row['id'])) {
        $id = preg_replace('~[^a-zA-Z0-9_-]~', '', (string) $row['id']);
        if ($id !== '') {
            $source = sprintf('https://drive.google.com/file/d/%s/view', $id);
        }
    }

    if ($source === '') {
        return '';
    }

    if (function_exists('flacso_get_pdf_proxy_url')) {
        $proxy = flacso_get_pdf_proxy_url($source, 'documento');
        if (!empty($proxy)) {
            return $proxy;
        }
    }

    if (!empty($row['id'])) {
        $id = preg_replace('~[^a-zA-Z0-9_-]~', '', (string) $row['id']);
        if ($id !== '') {
            return add_query_arg(array('flacso_pdf_proxy' => 1, 'doc_id' => $id), site_url('/'));
        }
    }

    return $source;
}

function flacso_shortcodes_cal_doc_source_url($row) {
    if (!is_array($row)) {
        return '';
    }

    if (!empty($row['url'])) {
        return esc_url_raw((string) $row['url']);
    }

    if (!empty($row['id'])) {
        $id = preg_replace('~[^a-zA-Z0-9_-]~', '', (string) $row['id']);
        if ($id !== '') {
            return sprintf('https://drive.google.com/file/d/%s/view', $id);
        }
    }

    return '';
}

function flacso_shortcodes_register_docs_page() {
    add_menu_page(
        __('FLACSO Shortcodes', 'flacso-uruguay'),
        __('FLACSO Shortcodes', 'flacso-uruguay'),
        'manage_options',
        'flacso-shortcodes-docs',
        'flacso_shortcodes_render_docs_page',
        'dashicons-media-document',
        58
    );

    add_submenu_page(
        'flacso-shortcodes-docs',
        __('Calendario Maestría en Género', 'flacso-uruguay'),
        __('Calendario Maestría', 'flacso-uruguay'),
        'manage_options',
        'flacso-shortcodes-cal-maestria-genero',
        'flacso_shortcodes_render_cal_maestriagenero_page'
    );
}

function flacso_shortcodes_render_cal_maestriagenero_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $data = flacso_shortcodes_get_cal_maestriagenero_data();
    $c2_docs = isset($data['c2_docs']) && is_array($data['c2_docs']) ? $data['c2_docs'] : array();
    $max_rows = max(6, count($c2_docs));
    $c1_items = isset($data['c1_items']) && is_array($data['c1_items']) ? $data['c1_items'] : array();
    $c3_items = isset($data['c3_items']) && is_array($data['c3_items']) ? $data['c3_items'] : array();

    if (empty($c1_items)) {
        $c1_items = array(
            array('eje' => '', 'nota' => '', 'semana' => '', 'nombre' => '', 'periodo' => '', 'sesiones' => array()),
        );
    }

    if (empty($c3_items)) {
        $c3_items = array(
            array('eje' => '', 'nota' => '', 'semana' => '', 'nombre' => '', 'periodo' => '', 'sesiones' => array()),
        );
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Calendario: Maestría en Género', 'flacso-uruguay'); ?></h1>
        <p><?php esc_html_e('Edita títulos, links del Ciclo 2 y el contenido de Ciclo 1 y Ciclo 3 con bloques anidados.', 'flacso-uruguay'); ?></p>
        <?php settings_errors('flacso_cal_maestriagenero_data'); ?>

        <form method="post" action="options.php">
            <?php settings_fields('flacso_shortcodes_calendar'); ?>

            <div class="postbox" style="margin:1rem 0;">
                <h2 style="margin:0;padding:1rem;border-bottom:1px solid #e2e2e2;"><?php esc_html_e('Títulos de cada ciclo', 'flacso-uruguay'); ?></h2>
                <div style="padding:1rem;">
                    <p>
                        <label><strong><?php esc_html_e('Título Ciclo 1', 'flacso-uruguay'); ?></strong><br>
                            <input type="text" class="regular-text" style="width:100%;max-width:700px;" name="flacso_cal_maestriagenero_data[c1_title]" value="<?php echo esc_attr($data['c1_title']); ?>">
                        </label>
                    </p>
                    <p>
                        <label><strong><?php esc_html_e('Título Ciclo 2', 'flacso-uruguay'); ?></strong><br>
                            <input type="text" class="regular-text" style="width:100%;max-width:700px;" name="flacso_cal_maestriagenero_data[c2_title]" value="<?php echo esc_attr($data['c2_title']); ?>">
                        </label>
                    </p>
                    <p>
                        <label><strong><?php esc_html_e('Título Ciclo 3', 'flacso-uruguay'); ?></strong><br>
                            <input type="text" class="regular-text" style="width:100%;max-width:700px;" name="flacso_cal_maestriagenero_data[c3_title]" value="<?php echo esc_attr($data['c3_title']); ?>">
                        </label>
                    </p>
                </div>
            </div>

            <div class="postbox" style="margin:1rem 0;">
                <h2 style="margin:0;padding:1rem;border-bottom:1px solid #e2e2e2;"><?php esc_html_e('Links del Ciclo 2', 'flacso-uruguay'); ?></h2>
                <div style="padding:1rem;">
                    <p class="description"><?php esc_html_e('Puedes usar ID de Drive o URL completa. Si completas ambos, se prioriza URL.', 'flacso-uruguay'); ?></p>
                    <style>
                        .flacso-cal-c2-table { table-layout: fixed; }
                        .flacso-cal-c2-table th:nth-child(1) { width: 26%; }
                        .flacso-cal-c2-table th:nth-child(2) { width: 16%; }
                        .flacso-cal-c2-table th:nth-child(3) { width: 18%; }
                        .flacso-cal-c2-table th:nth-child(4) { width: 40%; }
                        .flacso-cal-link-row { margin-top: .4rem; display: flex; gap: .5rem; align-items: center; }
                        .flacso-cal-doc-preview-value { width: 100%; font-family: monospace; font-size: 12px; }
                    </style>
                    <table class="widefat striped flacso-cal-c2-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Título', 'flacso-uruguay'); ?></th>
                                <th><?php esc_html_e('Cohorte', 'flacso-uruguay'); ?></th>
                                <th><?php esc_html_e('Drive ID', 'flacso-uruguay'); ?></th>
                                <th><?php esc_html_e('URL', 'flacso-uruguay'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php for ($i = 0; $i < $max_rows; $i++) :
                            $row = isset($c2_docs[$i]) && is_array($c2_docs[$i]) ? $c2_docs[$i] : array();
                            $preview_link = flacso_shortcodes_cal_doc_preview_url($row);
                            $source_link = flacso_shortcodes_cal_doc_source_url($row);
                            ?>
                            <tr>
                                <td><input type="text" style="width:100%;" name="flacso_cal_maestriagenero_data[c2_docs][<?php echo esc_attr($i); ?>][titulo]" value="<?php echo esc_attr(isset($row['titulo']) ? $row['titulo'] : ''); ?>"></td>
                                <td><input type="text" style="width:100%;" name="flacso_cal_maestriagenero_data[c2_docs][<?php echo esc_attr($i); ?>][cohorte]" value="<?php echo esc_attr(isset($row['cohorte']) ? $row['cohorte'] : ''); ?>"></td>
                                <td>
                                    <input class="flacso-cal-doc-id" type="text" style="width:100%;" name="flacso_cal_maestriagenero_data[c2_docs][<?php echo esc_attr($i); ?>][id]" value="<?php echo esc_attr(isset($row['id']) ? $row['id'] : ''); ?>">
                                </td>
                                <td>
                                    <input class="flacso-cal-doc-url" type="url" style="width:100%;" name="flacso_cal_maestriagenero_data[c2_docs][<?php echo esc_attr($i); ?>][url]" value="<?php echo esc_attr(isset($row['url']) ? $row['url'] : ''); ?>">
                                    <div class="flacso-cal-link-row">
                                        <a class="button button-small flacso-cal-doc-preview" data-base-site="<?php echo esc_attr(site_url('/')); ?>" data-ajax-url="<?php echo esc_attr(admin_url('admin-ajax.php')); ?>" target="_blank" rel="noopener" href="<?php echo esc_url($preview_link); ?>">
                                            <?php esc_html_e('Abrir proxy', 'flacso-uruguay'); ?>
                                        </a>
                                        <input type="text" readonly class="flacso-cal-doc-preview-value" value="<?php echo esc_attr($preview_link); ?>" placeholder="<?php esc_attr_e('Sin link', 'flacso-uruguay'); ?>">
                                    </div>
                                    <div class="flacso-cal-link-row">
                                        <a class="button button-small flacso-cal-doc-source" target="_blank" rel="noopener" href="<?php echo esc_url($source_link); ?>">
                                            <?php esc_html_e('Abrir Drive/Docs', 'flacso-uruguay'); ?>
                                        </a>
                                        <input type="text" readonly class="flacso-cal-doc-source-value" value="<?php echo esc_attr($source_link); ?>" placeholder="<?php esc_attr_e('Sin link directo', 'flacso-uruguay'); ?>">
                                    </div>
                                </td>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            var rows = document.querySelectorAll('.widefat tbody tr');

                            rows.forEach(function (row) {
                                var idInput = row.querySelector('.flacso-cal-doc-id');
                                var urlInput = row.querySelector('.flacso-cal-doc-url');
                                var preview = row.querySelector('.flacso-cal-doc-preview');
                                var previewValue = row.querySelector('.flacso-cal-doc-preview-value');
                                var sourceLink = row.querySelector('.flacso-cal-doc-source');
                                var sourceValue = row.querySelector('.flacso-cal-doc-source-value');

                                if (!idInput || !urlInput || !preview || !previewValue || !sourceLink || !sourceValue) {
                                    return;
                                }

                                var ajaxUrl = preview.getAttribute('data-ajax-url') || '';
                                var siteUrl = preview.getAttribute('data-base-site') || '';

                                var sanitizeId = function (value) {
                                    return (value || '').replace(/[^a-zA-Z0-9_-]/g, '');
                                };

                                var toBase64 = function (value) {
                                    try {
                                        return btoa(unescape(encodeURIComponent(value)));
                                    } catch (e) {
                                        return '';
                                    }
                                };

                                var setPreview = function () {
                                    var id = sanitizeId(idInput.value);
                                    var rawUrl = (urlInput.value || '').trim();
                                    var sourceUrl = rawUrl !== '' ? rawUrl : (id ? ('https://drive.google.com/file/d/' + id + '/view') : '');

                                    if (!sourceUrl) {
                                        preview.removeAttribute('href');
                                        previewValue.value = '';
                                        sourceLink.removeAttribute('href');
                                        sourceValue.value = '';
                                        return;
                                    }

                                    sourceLink.setAttribute('href', sourceUrl);
                                    sourceValue.value = sourceUrl;

                                    var srcB64 = toBase64(sourceUrl);
                                    if (ajaxUrl && srcB64) {
                                        var built = ajaxUrl + '?action=flacso_view_pdf&src=' + encodeURIComponent(srcB64) + '&fn=' + encodeURIComponent(toBase64('documento'));
                                        preview.setAttribute('href', built);
                                        previewValue.value = built;
                                        return;
                                    }

                                    if (id && siteUrl) {
                                        var fallback = siteUrl + (siteUrl.indexOf('?') === -1 ? '?' : '&') + 'flacso_pdf_proxy=1&doc_id=' + encodeURIComponent(id);
                                        preview.setAttribute('href', fallback);
                                        previewValue.value = fallback;
                                        return;
                                    }

                                    preview.removeAttribute('href');
                                    previewValue.value = sourceUrl;
                                };

                                idInput.addEventListener('input', setPreview);
                                urlInput.addEventListener('input', setPreview);
                                setPreview();
                            });
                        });
                    </script>
                </div>
            </div>

            <style>
                .flacso-cal-items { display: grid; gap: 12px; }
                .flacso-cal-item-card { border: 1px solid #dcdcde; border-radius: 8px; background: #fff; }
                .flacso-cal-item-head { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; border-bottom: 1px solid #eee; background: #f8f9fa; }
                .flacso-cal-item-body { padding: 12px; display: grid; gap: 10px; }
                .flacso-cal-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
                .flacso-cal-grid-1 { display: grid; gap: 10px; }
                .flacso-cal-sesiones { border: 1px dashed #c3c4c7; border-radius: 6px; padding: 10px; background: #fbfbfc; display: grid; gap: 8px; }
                .flacso-cal-sesion-row { display: grid; grid-template-columns: 1fr 1fr auto; gap: 8px; align-items: end; }
                @media (max-width: 900px) {
                    .flacso-cal-grid-2, .flacso-cal-sesion-row { grid-template-columns: 1fr; }
                }
            </style>

            <div class="postbox" style="margin:1rem 0;">
                <h2 style="margin:0;padding:1rem;border-bottom:1px solid #e2e2e2;"><?php esc_html_e('Calendario Ciclo 1', 'flacso-uruguay'); ?></h2>
                <div style="padding:1rem;">
                    <p class="description"><?php esc_html_e('Edita ítems del ciclo y sus sesiones anidadas.', 'flacso-uruguay'); ?></p>
                    <div id="flacso-c1-items" class="flacso-cal-items" data-cycle="c1" data-next-index="<?php echo esc_attr(count($c1_items)); ?>">
                        <?php foreach ($c1_items as $i => $item) :
                            $sesiones = (isset($item['sesiones']) && is_array($item['sesiones'])) ? $item['sesiones'] : array();
                            ?>
                            <div class="flacso-cal-item-card" data-item-index="<?php echo esc_attr($i); ?>">
                                <div class="flacso-cal-item-head">
                                    <strong><?php esc_html_e('Ítem', 'flacso-uruguay'); ?> #<?php echo esc_html($i + 1); ?></strong>
                                    <button type="button" class="button button-link-delete flacso-remove-item"><?php esc_html_e('Eliminar ítem', 'flacso-uruguay'); ?></button>
                                </div>
                                <div class="flacso-cal-item-body">
                                    <div class="flacso-cal-grid-2">
                                        <input type="text" placeholder="Eje" name="flacso_cal_maestriagenero_data[c1_items][<?php echo esc_attr($i); ?>][eje]" value="<?php echo esc_attr(isset($item['eje']) ? $item['eje'] : ''); ?>">
                                        <input type="text" placeholder="Nota" name="flacso_cal_maestriagenero_data[c1_items][<?php echo esc_attr($i); ?>][nota]" value="<?php echo esc_attr(isset($item['nota']) ? $item['nota'] : ''); ?>">
                                    </div>
                                    <div class="flacso-cal-grid-2">
                                        <input type="text" placeholder="Semana" name="flacso_cal_maestriagenero_data[c1_items][<?php echo esc_attr($i); ?>][semana]" value="<?php echo esc_attr(isset($item['semana']) ? $item['semana'] : ''); ?>">
                                        <input type="text" placeholder="Período" name="flacso_cal_maestriagenero_data[c1_items][<?php echo esc_attr($i); ?>][periodo]" value="<?php echo esc_attr(isset($item['periodo']) ? $item['periodo'] : ''); ?>">
                                    </div>
                                    <div class="flacso-cal-grid-1">
                                        <input type="text" placeholder="Nombre del seminario/taller" name="flacso_cal_maestriagenero_data[c1_items][<?php echo esc_attr($i); ?>][nombre]" value="<?php echo esc_attr(isset($item['nombre']) ? $item['nombre'] : ''); ?>">
                                    </div>

                                    <div class="flacso-cal-sesiones" data-sessions-for="c1-<?php echo esc_attr($i); ?>" data-next-session-index="<?php echo esc_attr(count($sesiones)); ?>">
                                        <strong><?php esc_html_e('Sesiones', 'flacso-uruguay'); ?></strong>
                                        <?php foreach ($sesiones as $s => $sesion) : ?>
                                            <div class="flacso-cal-sesion-row" data-session-index="<?php echo esc_attr($s); ?>">
                                                <input type="text" placeholder="Fecha" name="flacso_cal_maestriagenero_data[c1_items][<?php echo esc_attr($i); ?>][sesiones][<?php echo esc_attr($s); ?>][fecha]" value="<?php echo esc_attr(isset($sesion['fecha']) ? $sesion['fecha'] : ''); ?>">
                                                <input type="text" placeholder="Horario" name="flacso_cal_maestriagenero_data[c1_items][<?php echo esc_attr($i); ?>][sesiones][<?php echo esc_attr($s); ?>][hora]" value="<?php echo esc_attr(isset($sesion['hora']) ? $sesion['hora'] : ''); ?>">
                                                <button type="button" class="button button-link-delete flacso-remove-session"><?php esc_html_e('Quitar', 'flacso-uruguay'); ?></button>
                                            </div>
                                        <?php endforeach; ?>
                                        <button type="button" class="button flacso-add-session"><?php esc_html_e('Agregar sesión', 'flacso-uruguay'); ?></button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p><button type="button" class="button button-primary flacso-add-item" data-target="flacso-c1-items"><?php esc_html_e('Agregar ítem Ciclo 1', 'flacso-uruguay'); ?></button></p>
                </div>
            </div>

            <div class="postbox" style="margin:1rem 0;">
                <h2 style="margin:0;padding:1rem;border-bottom:1px solid #e2e2e2;"><?php esc_html_e('Calendario Ciclo 3', 'flacso-uruguay'); ?></h2>
                <div style="padding:1rem;">
                    <p class="description"><?php esc_html_e('Edita ítems del ciclo y sus sesiones anidadas.', 'flacso-uruguay'); ?></p>
                    <div id="flacso-c3-items" class="flacso-cal-items" data-cycle="c3" data-next-index="<?php echo esc_attr(count($c3_items)); ?>">
                        <?php foreach ($c3_items as $i => $item) :
                            $sesiones = (isset($item['sesiones']) && is_array($item['sesiones'])) ? $item['sesiones'] : array();
                            ?>
                            <div class="flacso-cal-item-card" data-item-index="<?php echo esc_attr($i); ?>">
                                <div class="flacso-cal-item-head">
                                    <strong><?php esc_html_e('Ítem', 'flacso-uruguay'); ?> #<?php echo esc_html($i + 1); ?></strong>
                                    <button type="button" class="button button-link-delete flacso-remove-item"><?php esc_html_e('Eliminar ítem', 'flacso-uruguay'); ?></button>
                                </div>
                                <div class="flacso-cal-item-body">
                                    <div class="flacso-cal-grid-2">
                                        <input type="text" placeholder="Eje" name="flacso_cal_maestriagenero_data[c3_items][<?php echo esc_attr($i); ?>][eje]" value="<?php echo esc_attr(isset($item['eje']) ? $item['eje'] : ''); ?>">
                                        <input type="text" placeholder="Nota" name="flacso_cal_maestriagenero_data[c3_items][<?php echo esc_attr($i); ?>][nota]" value="<?php echo esc_attr(isset($item['nota']) ? $item['nota'] : ''); ?>">
                                    </div>
                                    <div class="flacso-cal-grid-2">
                                        <input type="text" placeholder="Semana" name="flacso_cal_maestriagenero_data[c3_items][<?php echo esc_attr($i); ?>][semana]" value="<?php echo esc_attr(isset($item['semana']) ? $item['semana'] : ''); ?>">
                                        <input type="text" placeholder="Período" name="flacso_cal_maestriagenero_data[c3_items][<?php echo esc_attr($i); ?>][periodo]" value="<?php echo esc_attr(isset($item['periodo']) ? $item['periodo'] : ''); ?>">
                                    </div>
                                    <div class="flacso-cal-grid-1">
                                        <input type="text" placeholder="Nombre del seminario/taller" name="flacso_cal_maestriagenero_data[c3_items][<?php echo esc_attr($i); ?>][nombre]" value="<?php echo esc_attr(isset($item['nombre']) ? $item['nombre'] : ''); ?>">
                                    </div>

                                    <div class="flacso-cal-sesiones" data-sessions-for="c3-<?php echo esc_attr($i); ?>" data-next-session-index="<?php echo esc_attr(count($sesiones)); ?>">
                                        <strong><?php esc_html_e('Sesiones', 'flacso-uruguay'); ?></strong>
                                        <?php foreach ($sesiones as $s => $sesion) : ?>
                                            <div class="flacso-cal-sesion-row" data-session-index="<?php echo esc_attr($s); ?>">
                                                <input type="text" placeholder="Fecha" name="flacso_cal_maestriagenero_data[c3_items][<?php echo esc_attr($i); ?>][sesiones][<?php echo esc_attr($s); ?>][fecha]" value="<?php echo esc_attr(isset($sesion['fecha']) ? $sesion['fecha'] : ''); ?>">
                                                <input type="text" placeholder="Horario" name="flacso_cal_maestriagenero_data[c3_items][<?php echo esc_attr($i); ?>][sesiones][<?php echo esc_attr($s); ?>][hora]" value="<?php echo esc_attr(isset($sesion['hora']) ? $sesion['hora'] : ''); ?>">
                                                <button type="button" class="button button-link-delete flacso-remove-session"><?php esc_html_e('Quitar', 'flacso-uruguay'); ?></button>
                                            </div>
                                        <?php endforeach; ?>
                                        <button type="button" class="button flacso-add-session"><?php esc_html_e('Agregar sesión', 'flacso-uruguay'); ?></button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p><button type="button" class="button button-primary flacso-add-item" data-target="flacso-c3-items"><?php esc_html_e('Agregar ítem Ciclo 3', 'flacso-uruguay'); ?></button></p>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var buildItem = function (cycle, itemIndex) {
                        var wrapper = document.createElement('div');
                        wrapper.className = 'flacso-cal-item-card';
                        wrapper.setAttribute('data-item-index', itemIndex);
                        wrapper.innerHTML = '' +
                            '<div class="flacso-cal-item-head">' +
                                '<strong>Ítem #' + (itemIndex + 1) + '</strong>' +
                                '<button type="button" class="button button-link-delete flacso-remove-item">Eliminar ítem</button>' +
                            '</div>' +
                            '<div class="flacso-cal-item-body">' +
                                '<div class="flacso-cal-grid-2">' +
                                    '<input type="text" placeholder="Eje" name="flacso_cal_maestriagenero_data[' + cycle + '_items][' + itemIndex + '][eje]">' +
                                    '<input type="text" placeholder="Nota" name="flacso_cal_maestriagenero_data[' + cycle + '_items][' + itemIndex + '][nota]">' +
                                '</div>' +
                                '<div class="flacso-cal-grid-2">' +
                                    '<input type="text" placeholder="Semana" name="flacso_cal_maestriagenero_data[' + cycle + '_items][' + itemIndex + '][semana]">' +
                                    '<input type="text" placeholder="Período" name="flacso_cal_maestriagenero_data[' + cycle + '_items][' + itemIndex + '][periodo]">' +
                                '</div>' +
                                '<div class="flacso-cal-grid-1">' +
                                    '<input type="text" placeholder="Nombre del seminario/taller" name="flacso_cal_maestriagenero_data[' + cycle + '_items][' + itemIndex + '][nombre]">' +
                                '</div>' +
                                '<div class="flacso-cal-sesiones" data-sessions-for="' + cycle + '-' + itemIndex + '" data-next-session-index="0">' +
                                    '<strong>Sesiones</strong>' +
                                    '<button type="button" class="button flacso-add-session">Agregar sesión</button>' +
                                '</div>' +
                            '</div>';
                        return wrapper;
                    };

                    var buildSession = function (cycle, itemIndex, sessionIndex) {
                        var row = document.createElement('div');
                        row.className = 'flacso-cal-sesion-row';
                        row.setAttribute('data-session-index', sessionIndex);
                        row.innerHTML = '' +
                            '<input type="text" placeholder="Fecha" name="flacso_cal_maestriagenero_data[' + cycle + '_items][' + itemIndex + '][sesiones][' + sessionIndex + '][fecha]">' +
                            '<input type="text" placeholder="Horario" name="flacso_cal_maestriagenero_data[' + cycle + '_items][' + itemIndex + '][sesiones][' + sessionIndex + '][hora]">' +
                            '<button type="button" class="button button-link-delete flacso-remove-session">Quitar</button>';
                        return row;
                    };

                    document.querySelectorAll('.flacso-add-item').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var target = document.getElementById(btn.getAttribute('data-target'));
                            if (!target) return;
                            var cycle = target.getAttribute('data-cycle');
                            var nextIndex = parseInt(target.getAttribute('data-next-index') || '0', 10);
                            target.appendChild(buildItem(cycle, nextIndex));
                            target.setAttribute('data-next-index', String(nextIndex + 1));
                        });
                    });

                    document.addEventListener('click', function (e) {
                        if (e.target.classList.contains('flacso-remove-item')) {
                            e.preventDefault();
                            var card = e.target.closest('.flacso-cal-item-card');
                            if (card) card.remove();
                            return;
                        }

                        if (e.target.classList.contains('flacso-add-session')) {
                            e.preventDefault();
                            var card = e.target.closest('.flacso-cal-item-card');
                            var list = e.target.closest('.flacso-cal-sesiones');
                            if (!card || !list) return;

                            var itemIndex = parseInt(card.getAttribute('data-item-index') || '0', 10);
                            var cycle = (card.closest('.flacso-cal-items') || {}).getAttribute('data-cycle');
                            if (!cycle) return;

                            var nextSession = parseInt(list.getAttribute('data-next-session-index') || '0', 10);
                            var row = buildSession(cycle, itemIndex, nextSession);
                            list.insertBefore(row, e.target);
                            list.setAttribute('data-next-session-index', String(nextSession + 1));
                            return;
                        }

                        if (e.target.classList.contains('flacso-remove-session')) {
                            e.preventDefault();
                            var sessionRow = e.target.closest('.flacso-cal-sesion-row');
                            if (sessionRow) sessionRow.remove();
                        }
                    });
                });
            </script>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function flacso_shortcodes_render_docs_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $catalog = flacso_shortcodes_get_catalog();
    $price_table_labels = array(
        'maestria'     => __('Tabla de Maestrías', 'flacso-uruguay'),
        'egccyd'       => __('Diplomado EGCCyD', 'flacso-uruguay'),
        'eapet'        => __('EAPET', 'flacso-uruguay'),
        'diplomas'     => __('Diplomas', 'flacso-uruguay'),
        'iape'         => __('IAPE', 'flacso-uruguay'),
        'subjetividad' => __('Subjetividad y Psicoanálisis', 'flacso-uruguay'),
    );
    $price_tables = flacso_shortcodes_get_price_tables();
    ?>
    <div class="wrap flacso-shortcodes-docs">
        <h1><?php esc_html_e('Guía de Shortcodes - FLACSO', 'flacso-uruguay'); ?></h1>
        <p><?php esc_html_e('Utiliza esta tabla como referencia rápida de los shortcodes disponibles.', 'flacso-uruguay'); ?></p>

        <h2 style="margin-top:2rem;"><?php esc_html_e('Editar tablas de precios', 'flacso-uruguay'); ?></h2>
        <p><?php esc_html_e('Edita cada fila por separado. Puedes usar HTML básico en el concepto (por ejemplo, &lt;strong&gt;, &lt;small&gt;, &lt;br&gt;).', 'flacso-uruguay'); ?></p>
        <form method="post" action="options.php">
            <?php settings_fields('flacso_shortcodes_prices'); ?>
            <?php foreach ($price_table_labels as $key => $label) : ?>
                <?php
                $table = isset($price_tables[$key]) ? $price_tables[$key] : array('title' => '', 'rows' => array(), 'note' => '');
                $rows = isset($table['rows']) && is_array($table['rows']) ? $table['rows'] : array();
                $max_rows = max(6, count($rows));
                ?>
                <div class="postbox" style="margin:1.5rem 0;">
                    <h3 style="margin:0;padding:1rem;border-bottom:1px solid #e2e2e2;"><?php echo esc_html($label); ?></h3>
                    <div style="padding:1rem;">
                        <p>
                            <label>
                                <strong><?php esc_html_e('Título', 'flacso-uruguay'); ?></strong><br>
                                <input type="text" name="flacso_price_tables[<?php echo esc_attr($key); ?>][title]" value="<?php echo esc_attr($table['title']); ?>" class="regular-text" />
                            </label>
                        </p>
                        <p><strong><?php esc_html_e('Filas de la tabla', 'flacso-uruguay'); ?></strong></p>
                        <table class="widefat striped" style="margin-bottom:1rem;">
                            <thead>
                                <tr>
                                    <th style="width:40%;"><?php esc_html_e('Concepto', 'flacso-uruguay'); ?></th>
                                    <th><?php esc_html_e('Pesos', 'flacso-uruguay'); ?></th>
                                    <th><?php esc_html_e('Dólares', 'flacso-uruguay'); ?></th>
                                    <th style="width:80px;"><?php esc_html_e('Destacar', 'flacso-uruguay'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 0; $i < $max_rows; $i++) :
                                    $row = isset($rows[$i]) ? $rows[$i] : array('concept' => '', 'uy' => '', 'us' => '', 'highlight' => 0);
                                    ?>
                                    <tr>
                                        <td><textarea name="flacso_price_tables[<?php echo esc_attr($key); ?>][rows][<?php echo esc_attr($i); ?>][concept]" style="width:100%;height:60px;"><?php echo esc_textarea($row['concept']); ?></textarea></td>
                                        <td><input type="text" name="flacso_price_tables[<?php echo esc_attr($key); ?>][rows][<?php echo esc_attr($i); ?>][uy]" value="<?php echo esc_attr($row['uy']); ?>" class="regular-text" style="width:100%;" /></td>
                                        <td><input type="text" name="flacso_price_tables[<?php echo esc_attr($key); ?>][rows][<?php echo esc_attr($i); ?>][us]" value="<?php echo esc_attr($row['us']); ?>" class="regular-text" style="width:100%;" /></td>
                                        <td><input type="checkbox" name="flacso_price_tables[<?php echo esc_attr($key); ?>][rows][<?php echo esc_attr($i); ?>][highlight]" value="1" <?php checked($row['highlight'], 1); ?> /></td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                        <p>
                            <label>
                                <strong><?php esc_html_e('Nota', 'flacso-uruguay'); ?></strong><br>
                                <textarea name="flacso_price_tables[<?php echo esc_attr($key); ?>][note]" style="width:100%;height:80px;"><?php echo esc_textarea($table['note']); ?></textarea>
                            </label>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php submit_button(); ?>
        </form>

        <h2 style="margin-top:2rem;"><?php esc_html_e('Referencia de Shortcodes', 'flacso-uruguay'); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Shortcode', 'flacso-uruguay'); ?></th>
                    <th><?php esc_html_e('Descripción', 'flacso-uruguay'); ?></th>
                    <th><?php esc_html_e('Atributos', 'flacso-uruguay'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($catalog as $item) : ?>
                    <tr>
                        <td><code>[<?php echo esc_html($item['tag']); ?>]</code></td>
                        <td><?php echo esc_html($item['desc']); ?></td>
                        <td>
                            <?php
                            if (empty($item['attrs'])) {
                                echo '<em>' . esc_html__('Sin atributos', 'flacso-uruguay') . '</em>';
                            } else {
                                echo '<ul style="margin:0;padding-left:1.2em;">';
                                foreach ($item['attrs'] as $attr => $desc) {
                                    printf('<li><strong>%s</strong>: %s</li>', esc_html($attr), esc_html($desc));
                                }
                                echo '</ul>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Inicializar hooks
add_action('admin_menu', 'flacso_shortcodes_register_docs_page');
add_action('admin_init', 'flacso_shortcodes_register_settings');
