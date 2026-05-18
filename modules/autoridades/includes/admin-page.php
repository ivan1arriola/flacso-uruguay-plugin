<?php
/**
 * Página de administración para Autoridades FLACSO
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_menu_page(
        __('Autoridades FLACSO', 'flacso-uruguay'),
        __('Autoridades FLACSO', 'flacso-uruguay'),
        'manage_options',
        'flacso-autoridades',
        'flacso_autoridades_admin_page',
        'dashicons-groups',
        30
    );
});

function flacso_autoridades_default_data() {
    return [
        'imagen_fondo' => 'https://flacso.edu.uy/wp-content/uploads/2024/12/IMG_20220914_151738883-scaled-e1733249869591.jpg',
        'direccion' => [
            'docente_id'    => 0,
            'nombre_manual' => 'Dra. Ana Gabriela Fernández',
            'cargo'         => 'Directora',
            'enlace'        => '',
        ],
        'secciones' => [
            [
                'titulo' => 'Comisión Académica',
                'incluir_direccion' => true,
                'personas' => [
                    ['cargo' => 'Coordinadora Académica', 'nombre_manual' => 'Dra. Silvana Darré', 'docente_id' => 0, 'programa' => '', 'enlace' => ''],
                    ['cargo' => 'Secretaria Académica', 'nombre_manual' => 'Mag. Lena Fontela', 'docente_id' => 0, 'programa' => '', 'enlace' => ''],
                    ['cargo' => 'Coordinador Académico', 'nombre_manual' => 'Mag. José Miguel García', 'docente_id' => 0, 'programa' => 'Programa Educación, Ciencia y Tecnología', 'enlace' => ''],
                    ['cargo' => 'Coordinadora Académica', 'nombre_manual' => 'Dra. María Laura Osta Vázquez', 'docente_id' => 0, 'programa' => 'Programa Infancias y Adolescencias', 'enlace' => 'https://flacso.edu.uy/programa-infancias-y-adolescencia/'],
                    ['cargo' => 'Coordinadora Académica', 'nombre_manual' => 'Mag. Carla Rosso', 'docente_id' => 0, 'programa' => 'Programa Comprendiendo China', 'enlace' => ''],
                ],
            ],
            [
                'titulo' => 'Comisión Administrativa',
                'incluir_direccion' => true,
                'personas' => [
                    ['cargo' => 'Gestión Administrativa y Financiera', 'nombre_manual' => 'Cra. Gianella Gómez', 'docente_id' => 0, 'programa' => '', 'enlace' => ''],
                    ['cargo' => 'Secretaria Académica', 'nombre_manual' => 'Mag. Lena Fontela', 'docente_id' => 0, 'programa' => '', 'enlace' => ''],
                    ['cargo' => 'Secretaria Administrativa', 'nombre_manual' => 'María Inglese', 'docente_id' => 0, 'programa' => '', 'enlace' => ''],
                ],
            ],
        ],
    ];
}

function flacso_autoridades_get_data() {
    $data = get_option('flacso_autoridades_data');

    if (!$data || !is_array($data)) {
        $data = flacso_autoridades_default_data();
        update_option('flacso_autoridades_data', $data);
        return $data;
    }

    if (!isset($data['imagen_fondo'])) {
        $data['imagen_fondo'] = 'https://flacso.edu.uy/wp-content/uploads/2024/12/IMG_20220914_151738883-scaled-e1733249869591.jpg';
    }

    // Normalización y eliminación de redundancia si provienen de datos antiguos
    if (!isset($data['direccion']) || !is_array($data['direccion'])) {
        $dir_encontrada = false;
        if (!empty($data['secciones']) && is_array($data['secciones'])) {
            foreach ($data['secciones'] as $k => $sec) {
                $titulo_sec = strtolower(trim($sec['titulo'] ?? ''));
                if ($titulo_sec === 'dirección' || $titulo_sec === 'direccion') {
                    if (!empty($sec['personas'][0]) && is_array($sec['personas'][0])) {
                        $p = $sec['personas'][0];
                        $data['direccion'] = [
                            'docente_id'    => intval($p['docente_id'] ?? 0),
                            'nombre_manual' => trim($p['nombre_manual'] ?? ($p['nombre'] ?? 'Dra. Ana Gabriela Fernández')),
                            'cargo'         => trim($p['cargo'] ?? 'Directora'),
                            'enlace'        => trim($p['enlace'] ?? ''),
                        ];
                        $dir_encontrada = true;
                    }
                    unset($data['secciones'][$k]);
                    break;
                }
            }
        }
        if (!$dir_encontrada) {
            $data['direccion'] = [
                'docente_id'    => 0,
                'nombre_manual' => 'Dra. Ana Gabriela Fernández',
                'cargo'         => 'Directora',
                'enlace'        => '',
            ];
        }
        if (isset($data['secciones']) && is_array($data['secciones'])) {
            $data['secciones'] = array_values($data['secciones']);
        } else {
            $data['secciones'] = [];
        }
    }

    $dir_nombre = trim(strtolower($data['direccion']['nombre_manual']));

    if (!empty($data['secciones']) && is_array($data['secciones'])) {
        foreach ($data['secciones'] as &$seccion) {
            if (!isset($seccion['titulo'])) $seccion['titulo'] = '';
            if (!isset($seccion['incluir_direccion'])) $seccion['incluir_direccion'] = true;
            if (!isset($seccion['personas']) || !is_array($seccion['personas'])) $seccion['personas'] = [];

            foreach ($seccion['personas'] as $k => &$persona) {
                if (!isset($persona['docente_id'])) $persona['docente_id'] = 0;
                if (!isset($persona['cargo'])) $persona['cargo'] = '';
                if (!isset($persona['programa'])) $persona['programa'] = '';
                if (!isset($persona['enlace'])) $persona['enlace'] = '';

                if (!isset($persona['nombre_manual'])) {
                    $nombre_antiguo = trim($persona['nombre'] ?? '');
                    if ($nombre_antiguo !== '') {
                        if (strpos($nombre_antiguo, "\n") !== false && empty($persona['programa'])) {
                            $lineas = array_map('trim', explode("\n", $nombre_antiguo));
                            $persona['nombre_manual'] = $lineas[0];
                            if (isset($lineas[1])) {
                                $persona['programa'] = $lineas[1];
                            }
                        } else {
                            $persona['nombre_manual'] = $nombre_antiguo;
                        }
                    } else {
                        $persona['nombre_manual'] = '';
                    }
                }
                unset($persona['nombre']);

                // Eliminar redundancia si la Directora seguía repetida en la comisión
                if ($dir_nombre !== '' && trim(strtolower($persona['nombre_manual'])) === $dir_nombre) {
                    unset($seccion['personas'][$k]);
                }
            }
            $seccion['personas'] = array_values($seccion['personas']);
        }
    }

    return $data;
}

function flacso_autoridades_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (
        isset($_POST['flacso_autoridades_nonce']) &&
        wp_verify_nonce($_POST['flacso_autoridades_nonce'], 'guardar_flacso_autoridades')
    ) {
        $data = [
            'imagen_fondo' => esc_url_raw($_POST['imagen_fondo'] ?? ''),
            'direccion' => [
                'docente_id'    => intval($_POST['direccion']['docente_id'] ?? 0),
                'nombre_manual' => sanitize_text_field($_POST['direccion']['nombre_manual'] ?? ''),
                'cargo'         => sanitize_text_field($_POST['direccion']['cargo'] ?? 'Directora'),
                'enlace'        => esc_url_raw($_POST['direccion']['enlace'] ?? ''),
            ],
            'secciones' => [],
        ];

        if (!empty($_POST['secciones']) && is_array($_POST['secciones'])) {
            foreach ($_POST['secciones'] as $seccion) {
                $nueva_seccion = [
                    'titulo'            => sanitize_text_field($seccion['titulo'] ?? ''),
                    'incluir_direccion' => !empty($seccion['incluir_direccion']),
                    'personas'          => [],
                ];

                if (!empty($seccion['personas']) && is_array($seccion['personas'])) {
                    foreach ($seccion['personas'] as $persona) {
                        $docente_id    = intval($persona['docente_id'] ?? 0);
                        $nombre_manual = sanitize_text_field($persona['nombre_manual'] ?? '');
                        $cargo         = sanitize_text_field($persona['cargo'] ?? '');
                        $programa      = sanitize_text_field($persona['programa'] ?? '');
                        $enlace        = esc_url_raw($persona['enlace'] ?? '');

                        if ($docente_id > 0 || $nombre_manual !== '' || $cargo !== '') {
                            $nueva_seccion['personas'][] = [
                                'docente_id'    => $docente_id,
                                'nombre_manual' => $nombre_manual,
                                'cargo'         => $cargo,
                                'programa'      => $programa,
                                'enlace'        => $enlace,
                            ];
                        }
                    }
                }

                if ($nueva_seccion['titulo'] !== '' || !empty($nueva_seccion['personas'])) {
                    $data['secciones'][] = $nueva_seccion;
                }
            }
        }

        update_option('flacso_autoridades_data', $data);
        echo '<div class="updated notice is-dismissible"><p><strong>' . esc_html__('Autoridades guardadas correctamente sin redundancias.', 'flacso-uruguay') . '</strong></p></div>';
    }

    $data = flacso_autoridades_get_data();

    $docentes_query = get_posts([
        'post_type'      => 'docente',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    $opciones_docentes = [];
    foreach ($docentes_query as $doc) {
        $nombre = function_exists('dp_nombre_completo') ? dp_nombre_completo($doc->ID) : get_the_title($doc->ID);
        if (!$nombre || $nombre === (string)$doc->ID) {
            $nombre = get_the_title($doc->ID);
        }
        $opciones_docentes[$doc->ID] = $nombre;
    }
    ?>

    <div class="wrap flacso-admin-wrap">
        <div class="flacso-admin-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1 class="wp-heading-inline"><?php esc_html_e('Gestión de Autoridades FLACSO', 'flacso-uruguay'); ?></h1>
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=docente')); ?>" target="_blank" class="page-title-action button button-primary">
                <span class="dashicons dashicons-plus-alt2" style="vertical-align: middle; margin-top: 3px;"></span>
                <?php esc_html_e('Crear nuevo perfil de Docente / Autoridad', 'flacso-uruguay'); ?>
            </a>
        </div>

        <div class="notice notice-info" style="margin-left:0;">
            <p>
                <?php esc_html_e('Usa este shortcode para mostrar la grilla interactiva de autoridades en cualquier página o entrada:', 'flacso-uruguay'); ?>
                <code style="font-size:14px; padding:4px 8px; font-weight:bold;">[flacso_autoridades]</code>
            </p>
            <p class="description">
                <?php esc_html_e('Arquitectura Sin Redundancias: Configura a la persona a cargo de la Dirección de FLACSO Uruguay en la caja principal una única vez. Ella encabezará automáticamente la pestaña de Dirección y las comisiones que selecciones.', 'flacso-uruguay'); ?>
            </p>
        </div>

        <form method="post" id="flacso-autoridades-form">
            <?php wp_nonce_field('guardar_flacso_autoridades', 'flacso_autoridades_nonce'); ?>

            <!-- Apariencia -->
            <div class="postbox" style="padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px;">
                <h2 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 12px; font-size: 18px; color: #1d3a72;">
                    <span class="dashicons dashicons-format-image" style="vertical-align: middle;"></span>
                    <?php esc_html_e('Apariencia Global', 'flacso-uruguay'); ?>
                </h2>
                <table class="form-table">
                    <tr>
                        <th scope="row" style="width: 220px;">
                            <label for="imagen_fondo"><strong><?php esc_html_e('URL de Imagen de Fondo (Cabecera)', 'flacso-uruguay'); ?></strong></label>
                        </th>
                        <td>
                            <input
                                type="url"
                                id="imagen_fondo"
                                name="imagen_fondo"
                                value="<?php echo esc_attr($data['imagen_fondo']); ?>"
                                class="large-text code"
                                placeholder="https://flacso.edu.uy/wp-content/uploads/..."
                                style="width: 100%; border-radius: 4px;"
                            >
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Dirección de FLACSO -->
            <div class="postbox" style="padding: 24px; background: #fff; border-radius: 8px; border-left: 5px solid #fed222; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px;">
                <h2 style="margin-top:0; border-bottom: 2px solid #fed222; padding-bottom: 12px; font-size: 20px; color: #1d3a72;">
                    <span class="dashicons dashicons-awards" style="vertical-align: middle; color: #1d3a72;"></span>
                    <?php esc_html_e('Dirección de FLACSO Uruguay', 'flacso-uruguay'); ?>
                </h2>
                <p class="description" style="margin-bottom: 20px;">
                    <?php esc_html_e('Esta persona ocupa la titularidad de la Dirección y se mostrará en su propia pestaña dedicada, además de encabezar de forma automática las comisiones.', 'flacso-uruguay'); ?>
                </p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; padding: 16px; background: #fcfcfc; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <div>
                        <label><strong><?php esc_html_e('Vincular a Perfil Docente', 'flacso-uruguay'); ?></strong></label><br>
                        <div style="display: flex; gap: 8px; margin-top: 4px;">
                            <select name="direccion[docente_id]" class="docente-select" style="width: 100%; border-radius: 4px;">
                                <option value="0"><?php esc_html_e('--- Ninguno / Ingreso Manual ---', 'flacso-uruguay'); ?></option>
                                <?php foreach ($opciones_docentes as $id => $nombre_doc): ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected($data['direccion']['docente_id'], $id); ?>>
                                        <?php echo esc_html($nombre_doc); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($data['direccion']['docente_id'] > 0): ?>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $data['direccion']['docente_id'] . '&action=edit')); ?>" target="_blank" class="button button-small" title="<?php esc_attr_e('Editar perfil en nueva pestaña', 'flacso-uruguay'); ?>">
                                    <span class="dashicons dashicons-edit" style="margin-top: 4px;"></span>
                                </a>
                            <?php endif; ?>
                        </div>
                        <span class="description small"><?php esc_html_e('Toma foto, título y CV de la ficha del docente.', 'flacso-uruguay'); ?></span>
                    </div>

                    <div>
                        <label><strong><?php esc_html_e('Nombre / Detalle Manual', 'flacso-uruguay'); ?></strong></label><br>
                        <input
                            type="text"
                            name="direccion[nombre_manual]"
                            value="<?php echo esc_attr($data['direccion']['nombre_manual']); ?>"
                            class="regular-text nombre-manual-input"
                            style="width: 100%; margin-top: 4px; border-radius: 4px;"
                            placeholder="<?php esc_attr_e('Ej: Dra. Ana Gabriela Fernández', 'flacso-uruguay'); ?>"
                        >
                        <span class="description small"><?php esc_html_e('Se usa si no vinculas un perfil o para sobrescribir el nombre.', 'flacso-uruguay'); ?></span>
                    </div>

                    <div>
                        <label><strong><?php esc_html_e('Cargo Institucional', 'flacso-uruguay'); ?> <span style="color:#d63638;">*</span></strong></label><br>
                        <input
                            type="text"
                            name="direccion[cargo]"
                            value="<?php echo esc_attr($data['direccion']['cargo']); ?>"
                            class="regular-text"
                            style="width: 100%; margin-top: 4px; border-radius: 4px; border-color: #1d3a72; font-weight: bold;"
                            placeholder="<?php esc_attr_e('Ej: Directora', 'flacso-uruguay'); ?>"
                        >
                        <span class="description small"><?php esc_html_e('Aparece destacado en el kicker de la tarjeta.', 'flacso-uruguay'); ?></span>
                    </div>

                    <div>
                        <label><strong><?php esc_html_e('Enlace Opcional de Bio Externa', 'flacso-uruguay'); ?></strong></label><br>
                        <input
                            type="url"
                            name="direccion[enlace]"
                            value="<?php echo esc_attr($data['direccion']['enlace']); ?>"
                            class="regular-text code"
                            style="width: 100%; margin-top: 4px; border-radius: 4px;"
                            placeholder="https://..."
                        >
                        <span class="description small"><?php esc_html_e('Opcional si tiene un sitio o página específica.', 'flacso-uruguay'); ?></span>
                    </div>
                </div>
            </div>

            <h2 style="font-size: 22px; color: #1d3a72; margin: 30px 0 15px;">
                <span class="dashicons dashicons-networking" style="vertical-align: middle;"></span>
                <?php esc_html_e('Comisiones y Equipos de Trabajo', 'flacso-uruguay'); ?>
            </h2>

            <div id="secciones-container">
                <?php foreach ($data['secciones'] as $i => $seccion): ?>
                    <div class="postbox seccion-box" data-index="<?php echo esc_attr($i); ?>" style="padding: 20px; margin-bottom: 24px; border-radius: 8px; border-left: 4px solid #1d3a72; background: #fbfbfb;">
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd; padding-bottom: 16px; margin-bottom: 16px;">
                            <div style="display: flex; align-items: center; gap: 24px; width: 75%;">
                                <div style="display: flex; align-items: center;">
                                    <label style="font-size: 16px; font-weight: bold; margin-right: 12px; color: #1d3a72; white-space: nowrap;"><?php esc_html_e('Nombre de Comisión:', 'flacso-uruguay'); ?></label>
                                    <input
                                        type="text"
                                        name="secciones[<?php echo esc_attr($i); ?>][titulo]"
                                        value="<?php echo esc_attr($seccion['titulo']); ?>"
                                        class="regular-text"
                                        style="font-size: 16px; font-weight: bold; width: 280px; border-radius: 4px;"
                                        placeholder="Ej: Comisión Académica"
                                    >
                                </div>
                                <div style="display: flex; align-items: center; background: #eef2f8; padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e1;">
                                    <label style="cursor: pointer; display: flex; align-items: center; font-weight: 600; color: #1d3a72; font-size: 14px;">
                                        <input
                                            type="checkbox"
                                            name="secciones[<?php echo esc_attr($i); ?>][incluir_direccion]"
                                            value="1"
                                            <?php checked($seccion['incluir_direccion']); ?>
                                            style="margin-right: 8px;"
                                        >
                                        <?php esc_html_e('Encabezar esta comisión con la Dirección', 'flacso-uruguay'); ?>
                                    </label>
                                </div>
                            </div>
                            <button type="button" class="button button-link-delete delete-seccion-btn" style="color: #d63638; text-decoration: none;">
                                <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span> <?php esc_html_e('Eliminar Comisión', 'flacso-uruguay'); ?>
                            </button>
                        </div>

                        <div class="personas-list">
                            <h3 style="font-size: 14px; text-transform: uppercase; color: #555; margin-bottom: 12px;"><?php esc_html_e('Integrantes específicos de esta comisión', 'flacso-uruguay'); ?></h3>

                            <?php if (empty($seccion['personas'])): ?>
                                <p class="no-personas-msg description" style="font-style: italic; color: #888;"><?php esc_html_e('No hay integrantes en esta comisión. Añade uno abajo.', 'flacso-uruguay'); ?></p>
                            <?php endif; ?>

                            <?php foreach ($seccion['personas'] as $j => $persona): ?>
                                <div class="persona-item" data-person-index="<?php echo esc_attr($j); ?>" style="border: 1px solid #ccd0d4; border-radius: 6px; padding: 16px; margin-bottom: 16px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.04); position: relative;">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                                        
                                        <!-- Selector Docente -->
                                        <div>
                                            <label><strong><?php esc_html_e('Vincular a Perfil Docente', 'flacso-uruguay'); ?></strong></label><br>
                                            <div style="display: flex; gap: 8px; margin-top: 4px;">
                                                <select name="secciones[<?php echo esc_attr($i); ?>][personas][<?php echo esc_attr($j); ?>][docente_id]" class="docente-select" style="width: 100%; border-radius: 4px;">
                                                    <option value="0"><?php esc_html_e('--- Ninguno / Ingreso Manual ---', 'flacso-uruguay'); ?></option>
                                                    <?php foreach ($opciones_docentes as $id => $nombre_doc): ?>
                                                        <option value="<?php echo esc_attr($id); ?>" <?php selected($persona['docente_id'], $id); ?>>
                                                            <?php echo esc_html($nombre_doc); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php if ($persona['docente_id'] > 0): ?>
                                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $persona['docente_id'] . '&action=edit')); ?>" target="_blank" class="button button-small" title="<?php esc_attr_e('Editar perfil en nueva pestaña', 'flacso-uruguay'); ?>">
                                                        <span class="dashicons dashicons-edit" style="margin-top: 4px;"></span>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <span class="description small"><?php esc_html_e('Toma foto, título y CV.', 'flacso-uruguay'); ?></span>
                                        </div>

                                        <!-- Nombre Manual -->
                                        <div>
                                            <label><strong><?php esc_html_e('Nombre / Detalle Manual', 'flacso-uruguay'); ?></strong></label><br>
                                            <input
                                                type="text"
                                                name="secciones[<?php echo esc_attr($i); ?>][personas][<?php echo esc_attr($j); ?>][nombre_manual]"
                                                value="<?php echo esc_attr($persona['nombre_manual']); ?>"
                                                class="regular-text nombre-manual-input"
                                                style="width: 100%; margin-top: 4px; border-radius: 4px;"
                                                placeholder="<?php esc_attr_e('Ej: Mag. Juan Pérez', 'flacso-uruguay'); ?>"
                                            >
                                            <span class="description small"><?php esc_html_e('Se usa si no vinculas un perfil o para sobrescribir.', 'flacso-uruguay'); ?></span>
                                        </div>

                                        <!-- Cargo -->
                                        <div>
                                            <label><strong><?php esc_html_e('Cargo en la Comisión', 'flacso-uruguay'); ?> <span style="color:#d63638;">*</span></strong></label><br>
                                            <input
                                                type="text"
                                                name="secciones[<?php echo esc_attr($i); ?>][personas][<?php echo esc_attr($j); ?>][cargo]"
                                                value="<?php echo esc_attr($persona['cargo']); ?>"
                                                class="regular-text"
                                                style="width: 100%; margin-top: 4px; border-radius: 4px; border-color: #1d3a72;"
                                                placeholder="<?php esc_attr_e('Ej: Coordinadora Académica', 'flacso-uruguay'); ?>"
                                            >
                                            <span class="description small"><?php esc_html_e('Destacado en el kicker.', 'flacso-uruguay'); ?></span>
                                        </div>

                                        <!-- Programa -->
                                        <div>
                                            <label><strong><?php esc_html_e('Programa Asociado (Opcional)', 'flacso-uruguay'); ?></strong></label><br>
                                            <input
                                                type="text"
                                                name="secciones[<?php echo esc_attr($i); ?>][personas][<?php echo esc_attr($j); ?>][programa]"
                                                value="<?php echo esc_attr($persona['programa']); ?>"
                                                class="regular-text"
                                                style="width: 100%; margin-top: 4px; border-radius: 4px;"
                                                placeholder="<?php esc_attr_e('Ej: Programa Educación, Ciencia y Tecnología', 'flacso-uruguay'); ?>"
                                            >
                                            <span class="description small"><?php esc_html_e('Especial para áreas académicas.', 'flacso-uruguay'); ?></span>
                                        </div>

                                        <!-- Enlace Opcional -->
                                        <div>
                                            <label><strong><?php esc_html_e('Enlace Opcional del Programa', 'flacso-uruguay'); ?></strong></label><br>
                                            <input
                                                type="url"
                                                name="secciones[<?php echo esc_attr($i); ?>][personas][<?php echo esc_attr($j); ?>][enlace]"
                                                value="<?php echo esc_attr($persona['enlace']); ?>"
                                                class="regular-text code"
                                                style="width: 100%; margin-top: 4px; border-radius: 4px;"
                                                placeholder="https://..."
                                            >
                                            <span class="description small"><?php esc_html_e('Convierte el programa en enlace.', 'flacso-uruguay'); ?></span>
                                        </div>
                                    </div>

                                    <!-- Eliminar persona -->
                                    <div style="text-align: right; margin-top: 12px; border-top: 1px dashed #eee; padding-top: 12px;">
                                        <button type="button" class="button button-link-delete delete-persona-btn" style="color: #d63638;">
                                            <span class="dashicons dashicons-no-alt" style="vertical-align: middle;"></span> <?php esc_html_e('Eliminar integrante', 'flacso-uruguay'); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Botón Añadir Persona -->
                        <div style="margin-top: 16px;">
                            <button type="button" class="button add-persona-btn" style="border-color: #1d3a72; color: #1d3a72; background: #fff;">
                                <span class="dashicons dashicons-plus" style="vertical-align: middle;"></span>
                                <strong><?php esc_html_e('Añadir integrante a esta comisión', 'flacso-uruguay'); ?></strong>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Botón Añadir Sección -->
            <div style="margin-bottom: 30px; background: #eef2f8; padding: 20px; border-radius: 8px; text-align: center; border: 2px dashed #1d3a72;">
                <button type="button" id="add-seccion-btn" class="button button-primary button-large" style="background: #1d3a72; border-color: #1d3a72; font-size: 16px; padding: 6px 24px;">
                    <span class="dashicons dashicons-plus-alt2" style="vertical-align: middle; margin-top: 3px;"></span>
                    <?php esc_html_e('Añadir Nueva Comisión / Equipo', 'flacso-uruguay'); ?>
                </button>
                <p class="description" style="margin-top: 8px; color: #555;">
                    <?php esc_html_e('Crea una nueva división en las pestañas de autoridades.', 'flacso-uruguay'); ?>
                </p>
            </div>

            <div class="flacso-form-actions" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); position: sticky; bottom: 20px; z-index: 10; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span class="description"><?php esc_html_e('Asegúrate de guardar los cambios antes de salir de la página.', 'flacso-uruguay'); ?></span>
                </div>
                <?php submit_button(__('Guardar todas las autoridades', 'flacso-uruguay'), 'primary large', 'submit', false, ['style' => 'background: #fed222; border-color: #e5bc1b; color: #1d3a72; font-weight: bold; font-size: 16px; padding: 8px 32px; box-shadow: 0 4px 10px rgba(254, 210, 34, 0.3);']); ?>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById('secciones-container');
        const addSeccionBtn = document.getElementById('add-seccion-btn');

        const opcionesDocentesHtml = `
            <option value="0"><?php esc_html_e('--- Ninguno / Ingreso Manual ---', 'flacso-uruguay'); ?></option>
            <?php foreach ($opciones_docentes as $id => $nombre_doc): ?>
                <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html(addslashes($nombre_doc)); ?></option>
            <?php endforeach; ?>
        `;

        function crearPlantillaPersona(seccionIndex, personaIndex) {
            return `
                <div class="persona-item" data-person-index="${personaIndex}" style="border: 1px solid #ccd0d4; border-radius: 6px; padding: 16px; margin-bottom: 16px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.04); position: relative; animation: fadeIn 0.25s ease;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                        <div>
                            <label><strong><?php esc_html_e('Vincular a Perfil Docente', 'flacso-uruguay'); ?></strong></label><br>
                            <div style="display: flex; gap: 8px; margin-top: 4px;">
                                <select name="secciones[${seccionIndex}][personas][${personaIndex}][docente_id]" class="docente-select" style="width: 100%; border-radius: 4px;">
                                    ${opcionesDocentesHtml}
                                </select>
                            </div>
                            <span class="description small"><?php esc_html_e('Toma foto, título y CV.', 'flacso-uruguay'); ?></span>
                        </div>
                        <div>
                            <label><strong><?php esc_html_e('Nombre / Detalle Manual', 'flacso-uruguay'); ?></strong></label><br>
                            <input
                                type="text"
                                name="secciones[${seccionIndex}][personas][${personaIndex}][nombre_manual]"
                                class="regular-text nombre-manual-input"
                                style="width: 100%; margin-top: 4px; border-radius: 4px;"
                                placeholder="<?php esc_attr_e('Ej: Mag. Juan Pérez', 'flacso-uruguay'); ?>"
                            >
                            <span class="description small"><?php esc_html_e('Se usa si no vinculas un perfil o para sobrescribir.', 'flacso-uruguay'); ?></span>
                        </div>
                        <div>
                            <label><strong><?php esc_html_e('Cargo en la Comisión', 'flacso-uruguay'); ?> <span style="color:#d63638;">*</span></strong></label><br>
                            <input
                                type="text"
                                name="secciones[${seccionIndex}][personas][${personaIndex}][cargo]"
                                class="regular-text"
                                style="width: 100%; margin-top: 4px; border-radius: 4px; border-color: #1d3a72;"
                                placeholder="<?php esc_attr_e('Ej: Coordinadora Académica', 'flacso-uruguay'); ?>"
                            >
                            <span class="description small"><?php esc_html_e('Destacado en el kicker.', 'flacso-uruguay'); ?></span>
                        </div>
                        <div>
                            <label><strong><?php esc_html_e('Programa Asociado (Opcional)', 'flacso-uruguay'); ?></strong></label><br>
                            <input
                                type="text"
                                name="secciones[${seccionIndex}][personas][${personaIndex}][programa]"
                                class="regular-text"
                                style="width: 100%; margin-top: 4px; border-radius: 4px;"
                                placeholder="<?php esc_attr_e('Ej: Programa Educación, Ciencia y Tecnología', 'flacso-uruguay'); ?>"
                            >
                            <span class="description small"><?php esc_html_e('Especial para áreas académicas.', 'flacso-uruguay'); ?></span>
                        </div>
                        <div>
                            <label><strong><?php esc_html_e('Enlace Opcional del Programa', 'flacso-uruguay'); ?></strong></label><br>
                            <input
                                type="url"
                                name="secciones[${seccionIndex}][personas][${personaIndex}][enlace]"
                                class="regular-text code"
                                style="width: 100%; margin-top: 4px; border-radius: 4px;"
                                placeholder="https://..."
                            >
                            <span class="description small"><?php esc_html_e('Convierte el programa en enlace.', 'flacso-uruguay'); ?></span>
                        </div>
                    </div>
                    <div style="text-align: right; margin-top: 12px; border-top: 1px dashed #eee; padding-top: 12px;">
                        <button type="button" class="button button-link-delete delete-persona-btn" style="color: #d63638;">
                            <span class="dashicons dashicons-no-alt" style="vertical-align: middle;"></span> <?php esc_html_e('Eliminar integrante', 'flacso-uruguay'); ?>
                        </button>
                    </div>
                </div>
            `;
        }

        function crearPlantillaSeccion(seccionIndex) {
            return `
                <div class="postbox seccion-box" data-index="${seccionIndex}" style="padding: 20px; margin-bottom: 24px; border-radius: 8px; border-left: 4px solid #1d3a72; background: #fbfbfb; animation: fadeIn 0.3s ease;">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd; padding-bottom: 16px; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 24px; width: 75%;">
                            <div style="display: flex; align-items: center;">
                                <label style="font-size: 16px; font-weight: bold; margin-right: 12px; color: #1d3a72; white-space: nowrap;"><?php esc_html_e('Nombre de Comisión:', 'flacso-uruguay'); ?></label>
                                <input
                                    type="text"
                                    name="secciones[${seccionIndex}][titulo]"
                                    class="regular-text"
                                    style="font-size: 16px; font-weight: bold; width: 280px; border-radius: 4px;"
                                    placeholder="Ej: Comisión Académica"
                                >
                            </div>
                            <div style="display: flex; align-items: center; background: #eef2f8; padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e1;">
                                <label style="cursor: pointer; display: flex; align-items: center; font-weight: 600; color: #1d3a72; font-size: 14px;">
                                    <input type="checkbox" name="secciones[${seccionIndex}][incluir_direccion]" value="1" checked style="margin-right: 8px;">
                                    <?php esc_html_e('Encabezar esta comisión con la Dirección', 'flacso-uruguay'); ?>
                                </label>
                            </div>
                        </div>
                        <button type="button" class="button button-link-delete delete-seccion-btn" style="color: #d63638; text-decoration: none;">
                            <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span> <?php esc_html_e('Eliminar Comisión', 'flacso-uruguay'); ?>
                        </button>
                    </div>
                    <div class="personas-list">
                        <h3 style="font-size: 14px; text-transform: uppercase; color: #555; margin-bottom: 12px;"><?php esc_html_e('Integrantes específicos de esta comisión', 'flacso-uruguay'); ?></h3>
                        <p class="no-personas-msg description" style="font-style: italic; color: #888;"><?php esc_html_e('No hay integrantes en esta comisión. Añade uno abajo.', 'flacso-uruguay'); ?></p>
                    </div>
                    <div style="margin-top: 16px;">
                        <button type="button" class="button add-persona-btn" style="border-color: #1d3a72; color: #1d3a72; background: #fff;">
                            <span class="dashicons dashicons-plus" style="vertical-align: middle;"></span>
                            <strong><?php esc_html_e('Añadir integrante a esta comisión', 'flacso-uruguay'); ?></strong>
                        </button>
                    </div>
                </div>
            `;
        }

        container.addEventListener('click', function (e) {
            const addPersonaBtn = e.target.closest('.add-persona-btn');
            if (addPersonaBtn) {
                const seccionBox = addPersonaBtn.closest('.seccion-box');
                const seccionIndex = seccionBox.getAttribute('data-index');
                const personasList = seccionBox.querySelector('.personas-list');
                const noMsg = personasList.querySelector('.no-personas-msg');
                if (noMsg) noMsg.remove();

                const uniquePersonIndex = 'new_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
                personasList.insertAdjacentHTML('beforeend', crearPlantillaPersona(seccionIndex, uniquePersonIndex));
                return;
            }

            const deletePersonaBtn = e.target.closest('.delete-persona-btn');
            if (deletePersonaBtn) {
                const personaItem = deletePersonaBtn.closest('.persona-item');
                const personasList = personaItem.closest('.personas-list');
                personaItem.remove();
                if (personasList.children.length === 1 && personasList.querySelector('h3')) {
                    personasList.insertAdjacentHTML('beforeend', '<p class="no-personas-msg description" style="font-style: italic; color: #888;"><?php esc_html_e('No hay integrantes en esta comisión. Añade uno abajo.', 'flacso-uruguay'); ?></p>');
                }
                return;
            }

            const deleteSeccionBtn = e.target.closest('.delete-seccion-btn');
            if (deleteSeccionBtn) {
                if (confirm('<?php esc_html_e('¿Estás seguro de que deseas eliminar esta comisión completa con todos sus integrantes?', 'flacso-uruguay'); ?>')) {
                    deleteSeccionBtn.closest('.seccion-box').remove();
                }
                return;
            }
        });

        addSeccionBtn.addEventListener('click', function () {
            const uniqueSeccionIndex = 'sec_' + Date.now();
            container.insertAdjacentHTML('beforeend', crearPlantillaSeccion(uniqueSeccionIndex));
        });

        container.addEventListener('change', function (e) {
            if (e.target.classList.contains('docente-select')) {
                const select = e.target;
                const personaItem = select.closest('.persona-item');
                const inputNombre = personaItem.querySelector('.nombre-manual-input');
                const selectedText = select.options[select.selectedIndex].text;
                const selectedValue = select.value;

                if (selectedValue !== '0') {
                    if (inputNombre && inputNombre.value.trim() === '') {
                        inputNombre.placeholder = 'Automático: ' + selectedText;
                    }
                } else {
                    if (inputNombre) {
                        inputNombre.placeholder = '<?php esc_attr_e('Ej: Mag. Juan Pérez', 'flacso-uruguay'); ?>';
                    }
                }
            }
        });
    });
    </script>

    <style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .flacso-admin-wrap select:focus,
    .flacso-admin-wrap input:focus {
        border-color: #1d3a72 !important;
        box-shadow: 0 0 0 1px #1d3a72 !important;
    }
    .flacso-admin-wrap .button:hover {
        transform: translateY(-1px);
        transition: all 0.2s ease;
    }
    </style>

    <?php
}
