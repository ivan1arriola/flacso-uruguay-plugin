<?php

function flacso_shortcodes_get_default_price_tables() {
    $descuento_texto = '<strong>Descuento acumulable</strong><br><small>(25% de descuento)</small><br><small>Pago contado + Convenio o Comunidad FLACSO</small>';
    return array(
        'maestria' => array(
            'title' => 'Inversión, beneficios y descuentos acumulables',
            'rows'  => array(
                array('concept' => '<strong>Precio básico</strong>', 'uy' => '30 cuotas de $ 15.540', 'us' => '30 cuotas de U$S 320', 'highlight' => 0),
                array('concept' => '<strong>Pago contado</strong><br><small>(10% de descuento)</small>', 'uy' => '1 cuota de $ 419.580', 'us' => '1 cuota de U$S 8.700', 'highlight' => 0),
                array('concept' => '<strong>Convenio / Comunidad FLACSO Uruguay</strong><br><small>(15% de descuento)</small>', 'uy' => '30 cuotas de $ 13.209', 'us' => '30 cuotas de U$S 270', 'highlight' => 0),
                array('concept' => $descuento_texto, 'uy' => '1 cuota de $ 349.650', 'us' => '1 cuota de U$S 7.200', 'highlight' => 1),
            ),
            'note'  => 'Si no puedes adherir a ninguno de los beneficios descritos, podrás solicitar una beca FLACSO Uruguay de hasta un 20% escribiendo a: <a href="mailto:inscripciones@flacso.edu.uy">inscripciones@flacso.edu.uy</a><br><small>*Sujeto a aprobación por parte de la Comisión Académica FLACSO Uruguay</small>',
        ),
        'egccyd' => array(
            'title' => 'Inversión, beneficios y descuentos acumulables',
            'rows'  => array(
                array('concept' => '<strong>Precio básico</strong>', 'uy' => '14 cuotas de $ 13.786', 'us' => '14 cuotas de U$S 395', 'highlight' => 0),
                array('concept' => '<strong>Pago contado</strong><br><small>(10% de descuento)</small>', 'uy' => '1 cuota de $ 173.700', 'us' => '1 cuota de U$S 4.920', 'highlight' => 0),
                array('concept' => '<strong>Convenio / Comunidad FLACSO Uruguay</strong><br><small>(15% de descuento)</small>', 'uy' => '14 cuotas de $ 11.718', 'us' => '14 cuotas de U$S 336', 'highlight' => 0),
                array('concept' => $descuento_texto, 'uy' => '1 cuota de $ 144.600', 'us' => '1 cuota de U$S 4.152', 'highlight' => 1),
            ),
            'note'  => 'Si no puedes adherir a ninguno de los beneficios descritos, podrás solicitar una beca FLACSO Uruguay de hasta un 20% escribiendo a: <a href="mailto:inscripciones@flacso.edu.uy">inscripciones@flacso.edu.uy</a><br><small>*Sujeto a aprobación por parte de la Comisión Académica FLACSO Uruguay</small>',
        ),
        'eapet' => array(
            'title' => 'Inversión, beneficios y descuentos acumulables',
            'rows'  => array(
                array('concept' => '<strong>Precio básico</strong>', 'uy' => '18 cuotas de $ 8.544', 'us' => '18 cuotas de U$S 215', 'highlight' => 0),
                array('concept' => '<strong>Pago contado</strong><br><small>(10% de descuento)</small>', 'uy' => '1 cuota de $ 138.240', 'us' => '1 cuota de U$S 3.420', 'highlight' => 0),
                array('concept' => '<strong>Convenio / Comunidad FLACSO Uruguay</strong><br><small>(15% de descuento)</small>', 'uy' => '18 cuotas de $ 7.262', 'us' => '18 cuotas de U$S 180', 'highlight' => 0),
                array('concept' => $descuento_texto, 'uy' => '1 cuota de $ 115.200', 'us' => '1 cuota de U$S 2.880', 'highlight' => 1),
            ),
            'note'  => 'Si no puedes adherir a ninguno de los beneficios descritos, podrás solicitar una beca FLACSO Uruguay de hasta un 20% escribiendo a: <a href="mailto:inscripciones@flacso.edu.uy">inscripciones@flacso.edu.uy</a><br><small>*Sujeto a aprobación por parte de la Comisión Académica FLACSO Uruguay</small>',
        ),
        'diplomas' => array(
            'title' => 'Inversión, beneficios y descuentos acumulables',
            'rows'  => array(
                array('concept' => '<strong>Precio básico</strong>', 'uy' => '4 cuotas de $ 13.250', 'us' => '4 cuotas de U$S 375', 'highlight' => 0),
                array('concept' => '<strong>Pago contado</strong><br><small>(10% de descuento)</small>', 'uy' => '1 cuota de $ 47.700', 'us' => '1 cuota de U$S 1.320', 'highlight' => 0),
                array('concept' => '<strong>Convenio / Comunidad FLACSO Uruguay</strong><br><small>(15% de descuento)</small>', 'uy' => '4 cuotas de $ 11.265', 'us' => '4 cuotas de U$S 318', 'highlight' => 0),
                array('concept' => $descuento_texto, 'uy' => '1 cuota de $ 39.800', 'us' => '1 cuota de U$S 1.120', 'highlight' => 1),
            ),
            'note'  => 'Si no puedes adherir a ninguno de los beneficios descritos, podrás solicitar una beca FLACSO Uruguay de hasta un 20% escribiendo a: <a href="mailto:inscripciones@flacso.edu.uy">inscripciones@flacso.edu.uy</a><br><small>*Sujeto a aprobación por parte de la Comisión Académica FLACSO Uruguay</small>',
        ),
        'iape' => array(
            'title' => 'Inversión, beneficios y descuentos acumulables',
            'rows'  => array(
                array('concept' => '<strong>Precio básico</strong>', 'uy' => '5 cuotas de $ 9.600', 'us' => '5 cuotas de U$S 240', 'highlight' => 0),
                array('concept' => '<strong>Pago contado</strong><br><small>(10% de descuento)</small>', 'uy' => '1 cuota de $ 43.200', 'us' => '1 cuota de U$S 1.080', 'highlight' => 0),
                array('concept' => '<strong>Convenio / Comunidad FLACSO Uruguay</strong><br><small>(15% de descuento)</small>', 'uy' => '5 cuotas de $ 8.160', 'us' => '5 cuotas de U$S 204', 'highlight' => 0),
                array('concept' => $descuento_texto, 'uy' => '1 cuota de $ 36.000', 'us' => '1 cuota de U$S 900', 'highlight' => 1),
            ),
            'note'  => 'Si no puedes adherir a ninguno de los beneficios descritos, podrás solicitar una beca FLACSO Uruguay de hasta un 20% escribiendo a: <a href="mailto:inscripciones@flacso.edu.uy">inscripciones@flacso.edu.uy</a><br><small>*Sujeto a aprobación por parte de la Comisión Académica FLACSO Uruguay</small>',
        ),
        'subjetividad' => array(
            'title' => 'Inversión, beneficios y descuentos acumulables',
            'rows'  => array(
                array('concept' => '<strong>Precio básico</strong>', 'uy' => '5 cuotas de $ 13.172', 'us' => '5 cuotas de U$S 340', 'highlight' => 0),
                array('concept' => '<strong>Pago contado</strong><br><small>(10% de descuento)</small>', 'uy' => '1 cuota de $ 59.250', 'us' => '1 cuota de U$S 1.530', 'highlight' => 0),
                array('concept' => '<strong>Convenio / Comunidad FLACSO Uruguay</strong><br><small>(15% de descuento)</small>', 'uy' => '5 cuotas de $ 11.196', 'us' => '5 cuotas de U$S 289', 'highlight' => 0),
                array('concept' => $descuento_texto, 'uy' => '1 cuota de $ 49.400', 'us' => '1 cuota de U$S 1.275', 'highlight' => 1),
            ),
            'note'  => 'Si no puedes adherir a ninguno de los beneficios descritos, podrás solicitar una beca FLACSO Uruguay de hasta un 20% escribiendo a: <a href="mailto:inscripciones@flacso.edu.uy">inscripciones@flacso.edu.uy</a><br><small>*Sujeto a aprobación por parte de la Comisión Académica FLACSO Uruguay</small>',
        ),
    );
}

function flacso_shortcodes_get_price_tables() {
    $defaults = flacso_shortcodes_get_default_price_tables();
    $stored = get_option('flacso_price_tables', array());
    if (!is_array($stored)) {
        $stored = array();
    }

    $tables = array();
    foreach ($defaults as $key => $default) {
        $saved = isset($stored[$key]) && is_array($stored[$key]) ? $stored[$key] : array();
        $rows = null;
        if (isset($saved['rows']) && is_array($saved['rows'])) {
            $rows = $saved['rows'];
        } elseif (!empty($saved['rows_raw'])) {
            $rows = flacso_shortcodes_parse_price_rows($saved['rows_raw']);
        }
        if ($rows === null) {
            $rows = $default['rows'];
        }
        $tables[$key] = array(
            'title'    => isset($saved['title']) ? $saved['title'] : $default['title'],
            'rows'     => $rows,
            'rows_raw' => isset($saved['rows_raw']) ? $saved['rows_raw'] : null,
            'note'     => isset($saved['note']) ? $saved['note'] : $default['note'],
        );
    }

    return $tables;
}

function flacso_shortcodes_get_price_table($key) {
    $tables = flacso_shortcodes_get_price_tables();
    return isset($tables[$key]) ? $tables[$key] : null;
}

function flacso_shortcodes_parse_price_rows($rows_raw) {
    $rows = array();
    $lines = preg_split('/\r\n|\r|\n/', trim((string) $rows_raw));
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $concept = $parts[0] ?? '';
        $uy = $parts[1] ?? '';
        $us = $parts[2] ?? '';
        $highlight_flag = strtolower($parts[3] ?? '');
        $highlight = in_array($highlight_flag, array('1', 'true', 'si', 'sí', 'yes', 'highlight'), true);
        $rows[] = array(
            'concept'   => $concept,
            'uy'        => $uy,
            'us'        => $us,
            'highlight' => $highlight,
        );
    }

    return $rows;
}

function flacso_shortcodes_normalize_price_rows($table) {
    if (isset($table['rows']) && is_array($table['rows'])) {
        return $table['rows'];
    }
    if (!empty($table['rows_raw'])) {
        return flacso_shortcodes_parse_price_rows($table['rows_raw']);
    }
    return array();
}

function flacso_shortcodes_get_price_table_key_by_page_id($page_id) {
    $map = array(
        12330 => 'maestria', // EDUTIC
        12336 => 'maestria', // MESYP
        12343 => 'maestria', // MG
        12310 => 'eapet', // EAPET
        12316 => 'egccyd', // EGCCD
        12278 => 'egccyd', // DEPPI
        14444 => 'egccyd', // DESI
        12282 => 'egccyd', // DEVBG
        12288 => 'egccyd', // DEVNNA
        13202 => 'diplomas', // DCCH
        12295 => 'diplomas', // DAVIA
        12299 => 'diplomas', // DG
        20668 => 'iape', // IAPE
        12302 => 'diplomas', // DIDYP
        14657 => 'subjetividad', // DSMSYT
        12304 => 'diplomas', // DMIC
        13185 => 'diplomas', // DIAMHU
    );

    return isset($map[$page_id]) ? $map[$page_id] : null;
}

function flacso_shortcodes_resolve_price_table_key($fallback_key, $atts = array()) {
    $page_id = 0;
    if (!empty($atts['id'])) {
        $page_id = intval($atts['id']);
    } else {
        $current_id = get_the_ID();
        if (!empty($current_id)) {
            $parent_id = intval(wp_get_post_parent_id($current_id));
            $page_id = $parent_id > 0 ? $parent_id : $current_id;
        }
    }

    if ($page_id > 0) {
        $mapped = flacso_shortcodes_get_price_table_key_by_page_id($page_id);
        if (!empty($mapped)) {
            return $mapped;
        }
    }

    return $fallback_key;
}

function flacso_shortcodes_render_price_table($key, $shortcode_id) {
    $table = flacso_shortcodes_get_price_table($key);
    if (!$table) {
        return '';
    }
    $rows = flacso_shortcodes_normalize_price_rows($table);

    ob_start(); ?>
    <div id="<?php echo esc_attr($shortcode_id); ?>" class="gc-precios-wrapper">
        <div class="gc-content-card">
            <h2 class="gc-precios-titulo"><?php echo esc_html($table['title']); ?></h2>
            <table class="gc-pricing-table">
                <thead>
                    <tr>
                        <th>Concepto</th>
                        <th>Valor en $ (residentes en Uruguay)</th>
                        <th>Valor en U$S (residentes en el exterior)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <tr<?php echo $row['highlight'] ? ' class="highlighted"' : ''; ?>>
                            <td><?php echo wp_kses_post($row['concept']); ?></td>
                            <td><?php echo wp_kses_post($row['uy']); ?></td>
                            <td><?php echo wp_kses_post($row['us']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (!empty($table['note'])) : ?>
                <p class="gc-precios-beca"><?php echo wp_kses_post($table['note']); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <style>
    #<?php echo esc_attr($shortcode_id); ?> .gc-precios-titulo {
        color: var(--global-palette1, #1d3a72);
        font-weight: 700;
        margin-bottom: 1.25rem;
        font-size: 1.7rem;
    }
    #<?php echo esc_attr($shortcode_id); ?> .gc-pricing-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1.2rem;
        background: #ffffff;
        border-radius: 10px;
        overflow: hidden;
    }
    #<?php echo esc_attr($shortcode_id); ?> th {
        background: var(--global-palette1, #1d3a72);
        color: #fff;
        text-align: center;
        padding: 14px;
        font-weight: 600;
    }
    #<?php echo esc_attr($shortcode_id); ?> td {
        border: 1px solid #e1e1e1;
        padding: 14px;
        text-align: center;
        font-size: 1rem;
        color: #0f1a2d;
        vertical-align: middle;
    }
    #<?php echo esc_attr($shortcode_id); ?> td strong { color: #1d3a72; }
    #<?php echo esc_attr($shortcode_id); ?> small { color: #555; font-size: 0.85rem; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-fecha-limite { color: #d63384; font-weight: 600; font-style: italic; }
    #<?php echo esc_attr($shortcode_id); ?> tr:nth-child(even) { background-color: #f8f9fa; }
    #<?php echo esc_attr($shortcode_id); ?> .highlighted td {
        background-color: #e3f6e3;
        font-weight: 700;
        border-color: #b4e3b4;
    }
    #<?php echo esc_attr($shortcode_id); ?> .gc-precios-beca {
        font-size: .95rem; line-height: 1.4; margin-top: 1rem; color: #444; text-align: left;
    }
    </style>

    <?php
    return ob_get_clean();
}

function flacso_shortcodes_sanitize_price_tables($input) {
    $defaults = flacso_shortcodes_get_default_price_tables();
    $clean = array();

    if (!is_array($input)) {
        return $defaults;
    }

    foreach ($defaults as $key => $default) {
        $table = isset($input[$key]) && is_array($input[$key]) ? $input[$key] : array();
        $rows = array();
        if (isset($table['rows']) && is_array($table['rows'])) {
            foreach ($table['rows'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $concept = isset($row['concept']) ? wp_kses_post($row['concept']) : '';
                $uy = isset($row['uy']) ? wp_kses_post($row['uy']) : '';
                $us = isset($row['us']) ? wp_kses_post($row['us']) : '';
                $highlight = !empty($row['highlight']) ? 1 : 0;
                if ($concept === '' && $uy === '' && $us === '') {
                    continue;
                }
                $rows[] = array(
                    'concept' => $concept,
                    'uy' => $uy,
                    'us' => $us,
                    'highlight' => $highlight,
                );
            }
        } elseif (!empty($table['rows_raw'])) {
            $rows = flacso_shortcodes_parse_price_rows($table['rows_raw']);
        }
        if (empty($rows)) {
            $rows = $default['rows'];
        }
        $clean[$key] = array(
            'title'    => isset($table['title']) ? sanitize_text_field($table['title']) : $default['title'],
            'rows'     => $rows,
            'rows_raw' => isset($table['rows_raw']) ? wp_kses_post($table['rows_raw']) : null,
            'note'     => isset($table['note']) ? wp_kses_post($table['note']) : $default['note'],
        );
    }

    return $clean;
}

// ==================== SHORTCODE PRECIOS MAESTRÍAS  VERSIÓN FINAL ? ===================/**
 
 /* USO: [maestria_precios]
 */
add_shortcode('maestria_precios', 'flacso_maestria_precios_shortcode');
function flacso_maestria_precios_shortcode($atts = array()) {
    $atts = shortcode_atts(array('id' => ''), $atts, 'maestria_precios');

    $shortcode_id = 'maestria-precios-' . wp_rand(1000, 9999);
    $key = flacso_shortcodes_resolve_price_table_key('maestria', $atts);
    return flacso_shortcodes_render_price_table($key, $shortcode_id);
}
// ==================== SHORTCODE PRECIOS DIPLOMADOS DE ESPECIALIZACIÓN 
/**
 * USO: [egccyd_precios] o [diplomado_especializacion_precios]
 */
add_shortcode('egccyd_precios', 'flacso_precios_generico_shortcode');
add_shortcode('diplomado_especializacion_precios', 'flacso_precios_generico_shortcode');

function flacso_precios_generico_shortcode($atts = array()) {
    $atts = shortcode_atts(array('id' => ''), $atts, 'egccyd_precios');
    $called = current_filter();
    $slug   = str_replace('_', '-', $called);
    $shortcode_id = $slug . '-' . wp_rand(1000, 9999);
    $key = flacso_shortcodes_resolve_price_table_key('egccyd', $atts);
    return flacso_shortcodes_render_price_table($key, $shortcode_id);
}
/**
 * USO: [eapet_precios]
 */
add_shortcode('eapet_precios', 'flacso_eapet_precios_shortcode');
function flacso_eapet_precios_shortcode($atts = array()) {
    $atts = shortcode_atts(array('id' => ''), $atts, 'eapet_precios');

    $shortcode_id = 'eapet-precios-' . wp_rand(1000, 9999);
    $key = flacso_shortcodes_resolve_price_table_key('eapet', $atts);
    return flacso_shortcodes_render_price_table($key, $shortcode_id);
}
/**
 * USO: 
 * [diplomas_precios]
 */
add_shortcode('diplomas_precios', 'diploma_precios_shortcode');
function diploma_precios_shortcode($atts = array()) {
    $atts = shortcode_atts(array('id' => ''), $atts, 'diplomas_precios');

    $shortcode_id = 'comchina-precios-' . wp_rand(1000, 9999);
    $key = flacso_shortcodes_resolve_price_table_key('diplomas', $atts);
    return flacso_shortcodes_render_price_table($key, $shortcode_id);
}
/**
 * USO: 
 * [iape_precios]
 */
add_shortcode('iape_precios', 'iape_precios_shortcode');
function iape_precios_shortcode($atts = array()) {
    $atts = shortcode_atts(array('id' => ''), $atts, 'iape_precios');

    $shortcode_id = 'iape-precios-' . wp_rand(1000, 9999);
    $key = flacso_shortcodes_resolve_price_table_key('iape', $atts);
    return flacso_shortcodes_render_price_table($key, $shortcode_id);
}
/**
 * USO: 
 * [subjetividad_precios]
 */
add_shortcode('subjetividad_precios', 'subjetividad_precios_shortcode');
function subjetividad_precios_shortcode($atts = array()) {
    $atts = shortcode_atts(array('id' => ''), $atts, 'subjetividad_precios');

    $shortcode_id = 'subjetividad-precios-' . wp_rand(1000, 9999);
    $key = flacso_shortcodes_resolve_price_table_key('subjetividad', $atts);
    return flacso_shortcodes_render_price_table($key, $shortcode_id);
}
