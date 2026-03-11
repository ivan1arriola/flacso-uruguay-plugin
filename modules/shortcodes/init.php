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
