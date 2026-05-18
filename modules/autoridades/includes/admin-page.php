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
            'docente_id'       => 0,
            'prefijo'          => 'Dra.',
            'nombre_manual'    => 'Ana Gabriela Fernández Saavedra',
            'titulo_academico' => 'Doctora en Género y Diversidad',
            'cargo'            => 'Directora',
            'enlace'           => '',
            'cv'               => '',
        ],
        'secciones' => [
            [
                'titulo'            => 'Comisión Académica',
                'incluir_direccion' => true,
                'personas' => [
                    ['docente_id' => 0, 'prefijo' => 'Dra.', 'nombre_manual' => 'Silvana Darré', 'titulo_academico' => 'Doctora en Ciencias Sociales', 'cargo' => 'Coordinadora Académica', 'programa' => '', 'enlace' => '', 'cv' => ''],
                    ['docente_id' => 0, 'prefijo' => 'Mag.', 'nombre_manual' => 'Lena Fontela', 'titulo_academico' => 'Magíster en Políticas Públicas', 'cargo' => 'Secretaria Académica', 'programa' => '', 'enlace' => '', 'cv' => ''],
                    ['docente_id' => 0, 'prefijo' => 'Mag.', 'nombre_manual' => 'José Miguel García', 'titulo_academico' => 'Magíster en Educación', 'cargo' => 'Coordinador Académico', 'programa' => 'Programa Educación, Ciencia y Tecnología', 'enlace' => '', 'cv' => ''],
                    ['docente_id' => 0, 'prefijo' => 'Dra.', 'nombre_manual' => 'María Laura Osta Vázquez', 'titulo_academico' => 'Doctora en Historia', 'cargo' => 'Coordinadora Académica', 'programa' => 'Programa Infancias y Adolescencias', 'enlace' => 'https://flacso.edu.uy/programa-infancias-y-adolescencia/', 'cv' => ''],
                    ['docente_id' => 0, 'prefijo' => 'Mag.', 'nombre_manual' => 'Carla Rosso', 'titulo_academico' => 'Magíster en Estudios Internacionales', 'cargo' => 'Coordinadora Académica', 'programa' => 'Programa Comprendiendo China', 'enlace' => '', 'cv' => ''],
                ],
            ],
            [
                'titulo'            => 'Comisión Administrativa',
                'incluir_direccion' => true,
                'personas' => [
                    ['docente_id' => 0, 'prefijo' => 'Cra.', 'nombre_manual' => 'Gianella Gómez', 'titulo_academico' => 'Contadora Pública', 'cargo' => 'Gestión Administrativa y Financiera', 'programa' => '', 'enlace' => '', 'cv' => ''],
                    ['docente_id' => 0, 'prefijo' => 'Mag.', 'nombre_manual' => 'Lena Fontela', 'titulo_academico' => 'Magíster en Políticas Públicas', 'cargo' => 'Secretaria Académica', 'programa' => '', 'enlace' => '', 'cv' => ''],
                    ['docente_id' => 0, 'prefijo' => '', 'nombre_manual' => 'María Inglese', 'titulo_academico' => 'Administración y Gestión', 'cargo' => 'Secretaria Administrativa', 'programa' => '', 'enlace' => '', 'cv' => ''],
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

    // Normalización si faltan campos de dirección o secciones
    if (!isset($data['direccion']) || !is_array($data['direccion'])) {
        $dir_encontrada = false;
        if (!empty($data['secciones']) && is_array($data['secciones'])) {
            foreach ($data['secciones'] as $k => $sec) {
                $titulo_sec = strtolower(trim($sec['titulo'] ?? ''));
                if ($titulo_sec === 'dirección' || $titulo_sec === 'direccion') {
                    if (!empty($sec['personas'][0]) && is_array($sec['personas'][0])) {
                        $p = $sec['personas'][0];
                        $data['direccion'] = [
                            'docente_id'       => intval($p['docente_id'] ?? 0),
                            'prefijo'          => trim($p['prefijo'] ?? 'Dra.'),
                            'nombre_manual'    => trim($p['nombre_manual'] ?? ($p['nombre'] ?? 'Ana Gabriela Fernández Saavedra')),
                            'titulo_academico' => trim($p['titulo_academico'] ?? 'Doctora en Género y Diversidad'),
                            'cargo'            => trim($p['cargo'] ?? 'Directora'),
                            'enlace'           => trim($p['enlace'] ?? ''),
                            'cv'               => trim($p['cv'] ?? ''),
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
                'docente_id'       => 0,
                'prefijo'          => 'Dra.',
                'nombre_manual'    => 'Ana Gabriela Fernández Saavedra',
                'titulo_academico' => 'Doctora en Género y Diversidad',
                'cargo'            => 'Directora',
                'enlace'           => '',
                'cv'               => '',
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
                if (!isset($persona['prefijo'])) $persona['prefijo'] = '';
                if (!isset($persona['titulo_academico'])) $persona['titulo_academico'] = '';
                if (!isset($persona['cargo'])) $persona['cargo'] = '';
                if (!isset($persona['programa'])) $persona['programa'] = '';
                if (!isset($persona['enlace'])) $persona['enlace'] = '';
                if (!isset($persona['cv'])) $persona['cv'] = '';

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
                'docente_id'       => intval($_POST['direccion']['docente_id'] ?? 0),
                'prefijo'          => sanitize_text_field($_POST['direccion']['prefijo'] ?? ''),
                'nombre_manual'    => sanitize_text_field($_POST['direccion']['nombre_manual'] ?? ''),
                'titulo_academico' => sanitize_text_field($_POST['direccion']['titulo_academico'] ?? ''),
                'cargo'            => sanitize_text_field($_POST['direccion']['cargo'] ?? 'Directora'),
                'enlace'           => esc_url_raw($_POST['direccion']['enlace'] ?? ''),
                'cv'               => wp_kses_post($_POST['direccion']['cv'] ?? ''),
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
                        $docente_id       = intval($persona['docente_id'] ?? 0);
                        $prefijo          = sanitize_text_field($persona['prefijo'] ?? '');
                        $nombre_manual    = sanitize_text_field($persona['nombre_manual'] ?? '');
                        $titulo_academico = sanitize_text_field($persona['titulo_academico'] ?? '');
                        $cargo            = sanitize_text_field($persona['cargo'] ?? '');
                        $programa         = sanitize_text_field($persona['programa'] ?? '');
                        $enlace           = esc_url_raw($persona['enlace'] ?? '');
                        $cv               = wp_kses_post($persona['cv'] ?? '');

                        if ($docente_id > 0 || $nombre_manual !== '' || $cargo !== '') {
                            $nueva_seccion['personas'][] = [
                                'docente_id'       => $docente_id,
                                'prefijo'          => $prefijo,
                                'nombre_manual'    => $nombre_manual,
                                'titulo_academico' => $titulo_academico,
                                'cargo'            => $cargo,
                                'programa'         => $programa,
                                'enlace'           => $enlace,
                                'cv'               => $cv,
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

    <!-- Cargar Select2 desde CDN para una experiencia de búsqueda instantánea -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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
                <?php esc_html_e('Búsqueda Instantánea con Select2 y soporte completo de Prefijos (Dra., Mag., Cra.) tanto automáticos desde el perfil como para personal ingresado manualmente.', 'flacso-uruguay'); ?>
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
            <div class="postbox persona-item" style="padding: 24px; background: #fff; border-radius: 8px; border-left: 5px solid #fed222; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px;">
                <h2 style="margin-top:0; border-bottom: 2px solid #fed222; padding-bottom: 12px; font-size: 20px; color: #1d3a72;">
                    <span class="dashicons dashicons-awards" style="vertical-align: middle; color: #1d3a72;"></span>
                    <?php esc_html_e('Dirección de FLACSO Uruguay', 'flacso-uruguay'); ?>
                </h2>
                <p class="description" style="margin-bottom: 20px;">
                    <?php esc_html_e('Esta persona ocupa la titularidad de la Dirección y se mostrará en su propia pestaña dedicada, además de encabezar de forma automática las comisiones.', 'flacso-uruguay'); ?>
                </p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; padding: 20px; background: #fcfcfc; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <div>
                        <label><strong><?php esc_html_e('Buscar y Vincular a Perfil Docente', 'flacso-uruguay'); ?></strong></label><br>
                        <div style="display: flex; gap: 8px; margin-top: 4px;">
                            <div style="flex-grow: 1;">
                                <select name="direccion[docente_id]" class="docente-select" style="width: 100%;">
                                    <option value="0"><?php esc_html_e('--- Ninguno / Ingreso Manual ---', 'flacso-uruguay'); ?></option>
                                    <?php foreach ($opciones_docentes as $id => $nombre_doc): ?>
                                        <option value="<?php echo esc_attr($id); ?>" <?php selected($data['direccion']['docente_id'], $id); ?>>
                                            <?php echo esc_html($nombre_doc); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($data['direccion']['docente_id'] > 0): ?>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $data['direccion']['docente_id'] . '&action=edit')); ?>" target="_blank" class="button button-small edit-doc-link" title="<?php esc_attr_e('Editar perfil en nueva pestaña', 'flacso-uruguay'); ?>" style="display: flex; align-items: center; justify-content: center; height: 36px; width: 36px;">
                                    <span class="dashicons dashicons-edit"></span>
                                </a>
                            <?php endif; ?>
                        </div>
                        <span class="description small"><?php esc_html_e('Toma foto, prefijo, título y CV.', 'flacso-uruguay'); ?></span>
                    </div>

                    <div>
                        <label><strong><?php esc_html_e('Prefijo (Ej: Dra., Mag., Cra.)', 'flacso-uruguay'); ?></strong></label><br>
                        <input
                            type="text"
                            name="direccion[prefijo]"
                            value="<?php echo esc_attr($data['direccion']['prefijo']); ?>"
                            class="regular-text"
                            style="width: 100%; margin-top: 4px; border-radius: 4px; height: 36px;"
                            placeholder="<?php esc_attr_e('Ej: Dra.', 'flacso-uruguay'); ?>"
                        >
                        <span class="description small"><?php esc_html_e('Aparece justo antes del nombre.', 'flacso-uruguay'); ?></span>
                    </div>

                    <div>
                        <label><strong><?php esc_html_e('Nombre / Apellido', 'flacso-uruguay'); ?></strong></label><br>
                        <input
                            type="text"
                            name="direccion[nombre_manual]"
                            value="<?php echo esc_attr($data['direccion']['nombre_manual']); ?>"
                            class="regular-text nombre-manual-input"
                            style="width: 100%; margin-top: 4px; border-radius: 4px; height: 36px;"
                            placeholder="<?php esc_attr_e('Ej: Ana Gabriela Fernández', 'flacso-uruguay'); ?>"
                        >
                        <span class="description small"><?php esc_html_e('Se usa si es manual o para sobrescribir.', 'flacso-uruguay'); ?></span>
                    </div>

                    <div>
                        <label><strong><?php esc_html_e('Título Académico', 'flacso-uruguay'); ?></strong></label><br>
                        <input
                            type="text"
                            name="direccion[titulo_academico]"
                            value="<?php echo esc_attr($data['direccion']['titulo_academico']); ?>"
                            class="regular-text"
                            style="width: 100%; margin-top: 4px; border-radius: 4px; height: 36px;"
                            placeholder="<?php esc_attr_e('Ej: Doctora en Género y Diversidad', 'flacso-uruguay'); ?>"
                        >
                        <span class="description small"><?php esc_html_e('Aparece en gris bajo el nombre.', 'flacso-uruguay'); ?></span>
                    </div>

                    <div>
                        <label><strong><?php esc_html_e('Cargo Institucional', 'flacso-uruguay'); ?> <span style="color:#d63638;">*</span></strong></label><br>
                        <input
                            type="text"
                            name="direccion[cargo]"
                            value="<?php echo esc_attr($data['direccion']['cargo']); ?>"
                            class="regular-text"
                            style="width: 100%; margin-top: 4px; border-radius: 4px; border-color: #1d3a72; font-weight: bold; height: 36px;"
                            placeholder="<?php esc_attr_e('Ej: Directora', 'flacso-uruguay'); ?>"
                        >
                        <span class="description small"><?php esc_html_e('Destacado en el kicker amarillo.', 'flacso-uruguay'); ?></span>
                    </div>

                    <div>
                        <label><strong><?php esc_html_e('Enlace Opcional Externa', 'flacso-uruguay'); ?></strong></label><br>
                        <input
                            type="url"
                            name="direccion[enlace]"
                            value="<?php echo esc_attr($data['direccion']['enlace']); ?>"
                            class="regular-text code"
                            style="width: 100%; margin-top: 4px; border-radius: 4px; height: 36px;"
                            placeholder="https://..."
                        >
                        <span class="description small"><?php esc_html_e('Opcional si tiene un sitio externo.', 'flacso-uruguay'); ?></span>
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label><strong><?php esc_html_e('Biografía / CV (Para Ingreso Manual)', 'flacso-uruguay'); ?></strong></label><br>
                        <textarea
                            name="direccion[cv]"
                            class="large-text"
                            rows="2"
                            style="width: 100%; margin-top: 4px; border-radius: 4px;"
                            placeholder="<?php esc_attr_e('Escribe la biografía o texto del currículum para mostrar en la tarjeta si no tiene perfil vinculado...', 'flacso-uruguay'); ?>"
                        ><?php echo esc_textarea($data['direccion']['cv']); ?></textarea>
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
                                        style="font-size: 16px; font-weight: bold; width: 280px; border-radius: 4px; height: 36px;"
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
                                <div class="persona-item" data-person-index="<?php echo esc_attr($j); ?>" style="border: 1px solid #ccd0d4; border-radius: 6px; padding: 20px; margin-bottom: 16px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative;">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                                        
                                        <!-- Selector Docente con Select2 -->
                                        <div>
                                            <label><strong><?php esc_html_e('Buscar y Vincular a Perfil', 'flacso-uruguay'); ?></strong></label><br>
                                            <div style="display: flex; gap: 8px; margin-top: 4px;">
                                                <div style="flex-grow: 1;">
                                                    <select name="secciones[<?php echo esc_attr($i); ?>][personas][<?php echo esc_attr($j); ?>][docente_id]" class="docente-select" style="width: 100%;">
                                                        <option value="0"><?php esc_html_e('--- Ninguno / Ingreso Manual ---', 'flacso-uruguay'); ?></option>
                                                        <?php foreach ($opciones_docentes as $id => $nombre_doc): ?>
                                                            <option value="<?php echo esc_attr($id); ?>" <?php selected($persona['docente_id'], $id); ?>>
                                                                <?php echo esc_html($nombre_doc); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <?php if ($persona['docente_id'] > 0): ?>
                                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $persona['docente_id'] . '&action=edit')); ?>" target="_blank" class="button button-small edit-doc-link" title="<?php esc_attr_e('Editar perfil en nueva pestaña', 'flacso-uruguay'); ?>" style="display: flex; align-items: center; justify-content: center; height: 36px; width: 36px;">
                                                        <span class="dashicons dashicons-edit"></span>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <span class="description small"><?php esc_html_e('Toma foto, prefijo y CV.', 'flacso-uruguay'); ?></span>
                                        </div>

                                        <!-- Prefijo -->
                                        <div>
                                            <label><strong><?php esc_html_e('Prefijo (Ej: Dra., Mag., Cra.)', 'flacso-uruguay'); ?></strong></label><br>
                                            <input
                                                type="text"
                                                name="secciones[<?php echo esc_attr($i); ?>][personas][<?php echo esc_attr($j); ?>][prefijo]"
                                                value="<?php echo esc_attr($persona['prefijo']); ?>"
                                                class="regular-text"
                                                style="width: 100%; margin-top: 4px; border-radius: 4px; height: 36px;"
                                                placeholder="<?php esc_attr_e('Ej: Mag.', 'flacso-uruguay'); ?>"
                                            >
                                            <span class="description small"><?php esc_html_e('Aparece antes del nombre.', 'flacso-uruguay'); ?></span>
                                        </div>

                                        <!-- Nombre Manual -->
                                        <div>
                                            <label><strong><?php esc_html_e('Nombre / Apellido', 'flacso-uruguay'); ?></strong></label><br>
                                            <input
                                                type="text"
                                                name="secciones[<?php echo esc_attr($i); ?>][personas][<?php echo esc_attr($j); ?>][nombre_manual]"
                                                value="<?php echo esc_attr($persona['nombre_manual']); ?>"
                                                class="regular-text nombre-manual-input"
                                                style="width: 100%; margin-top: 4px; border-radius: 4px; height: 36px;"
                                                placeholder="<?php esc_attr_e('Ej: Juan Pérez', 'flacso-uruguay'); ?>"
                                            >
                                            <span class="description small"><?php esc_html_e('Se usa si es manual o para sobrescribir.', 'flacso-uruguay'); ?></span>
                                        </div>

                                        <!-- Título Académico -->
                                        <div>
                                            <label><strong><?php esc_html_e('Título Académico', 'flacso-uruguay'); ?></strong></label><br>
                                            <input
                                                type="text"
                                                name="secciones[<?php echo esc_attr($i); ?>][personas][<?php echo esc_attr($j); ?>][titulo_academico]"
                                                value="<?php echo esc_attr($persona['titulo_academico']); ?>"
                                                class="regular-text"
                                                style="width: 100%; margin-top: 4px; border-radius: 4px; height: 36px;"
                                                placeholder="<?php esc_attr_e('Ej: Doctor en Sociología', 'flacso-uruguay'); ?>"
                                            >
                                            <span class="description small"><?php esc_html_e('Aparece bajo el nombre.', 'flacso-uruguay'); ?></span>
                                        </div>

                                        <!-- Cargo -->
                                        <div>
                                            <label><strong><?php esc_html_e('Cargo en la Comisión', 'flacso-uruguay'); ?> <span style="color:#d63638;">*</span></strong></label><br>
                                            <input
                                                type="text"
                                                name="secciones[<?php echo esc_attr($i); ?>][personas][<?php echo esc_attr($j); ?>][cargo]"
                                                value="<?php echo esc_attr($persona['cargo']); ?>"
                                                class="regular-text"
                                                style="width: 100%; margin-top: 4px; border-radius: 4px; border-color: #1d3a72; height: 36px;"
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
                                                style="width: 100%; margin-top: 4px; border-radius: 4px; height: 36px;"
                                                placeholder="<?php esc_attr_e('Ej: Programa Educación', 'flacso-uruguay'); ?>"
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
                                                style="width: 100%; margin-top: 4px; border-radius: 4px; height: 36px;"
                                                placeholder="https://..."
                                            >
                                            <span class="description small"><?php esc_html_e('Convierte el programa en enlace.', 'flacso-uruguay'); ?></span>
                                        </div>

                                        <!-- CV / Bio manual -->
                                        <div style="grid-column: 1 / -1;">
                                            <label><strong><?php esc_html_e('Biografía / CV (Para Ingreso Manual)', 'flacso-uruguay'); ?></strong></label><br>
                                            <textarea
                                                name="secciones[<?php echo esc_attr($i); ?>][personas][<?php echo esc_attr($j); ?>][cv]"
                                                class="large-text"
                                                rows="2"
                                                style="width: 100%; margin-top: 4px; border-radius: 4px;"
                                                placeholder="<?php esc_attr_e('Escribe la biografía o texto del currículum para mostrar en la tarjeta si no tiene perfil vinculado...', 'flacso-uruguay'); ?>"
                                            ><?php echo esc_textarea($persona['cv']); ?></textarea>
                                        </div>
                                    </div>

                                    <!-- Eliminar persona -->
                                    <div style="text-align: right; margin-top: 16px; border-top: 1px dashed #eee; padding-top: 16px;">
                                        <button type="button" class="button button-link-delete delete-persona-btn" style="color: #d63638;">
                                            <span class="dashicons dashicons-no-alt" style="vertical-align: middle;"></span> <?php esc_html_e('Eliminar integrante', 'flacso-uruguay'); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Botón Añadir Persona -->
                        <div style="margin-top: 16px;">
                            <button type="button" class="button add-persona-btn" style="border-color: #1d3a72; color: #1d3a72; background: #fff; height: 38px;">
                                <span class="dashicons dashicons-plus" style="vertical-align: middle;"></span>
                                <strong><?php esc_html_e('Añadir integrante a esta comisión', 'flacso-uruguay'); ?></strong>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Botón Añadir Sección -->
            <div style="margin-bottom: 30px; background: #eef2f8; padding: 20px; border-radius: 8px; text-align: center; border: 2px dashed #1d3a72;">
                <button type="button" id="add-seccion-btn" class="button button-primary button-large" style="background: #1d3a72; border-color: #1d3a72; font-size: 16px; padding: 6px 24px; height: 44px;">
                    <span class="dashicons dashicons-plus-alt2" style="vertical-align: middle; margin-top: 3px;"></span>
                    <?php esc_html_e('Añadir Nueva Comisión / Equipo', 'flacso-uruguay'); ?>
                </button>
                <p class="description" style="margin-top: 8px; color: #555;">
                    <?php esc_html_e('Crea una nueva división en las pestañas de autoridades.', 'flacso-uruguay'); ?>
                </p>
            </div>

            <div class="flacso-form-actions" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); position: sticky; bottom: 20px; z-index: 100; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span class="description"><?php esc_html_e('Asegúrate de guardar los cambios antes de salir de la página.', 'flacso-uruguay'); ?></span>
                </div>
                <?php submit_button(__('Guardar todas las autoridades', 'flacso-uruguay'), 'primary large', 'submit', false, ['style' => 'background: #fed222; border-color: #e5bc1b; color: #1d3a72; font-weight: bold; font-size: 16px; padding: 8px 32px; height: 44px; box-shadow: 0 4px 10px rgba(254, 210, 34, 0.3);']); ?>
            </div>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        const container = $('#secciones-container');
        const addSeccionBtn = $('#add-seccion-btn');

        function initSelect2(elem) {
            elem.select2({
                width: '100%',
                placeholder: 'Escribe para buscar un docente...',
                allowClear: false
            });
        }

        initSelect2($('.docente-select'));

        const opcionesDocentesHtml = `
            <option value="0"><?php esc_html_e('--- Ninguno / Ingreso Manual ---', 'flacso-uruguay'); ?></option>
            <?php foreach ($opciones_docentes as $id => $nombre_doc): ?>
                <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html(addslashes($nombre_doc)); ?></option>
            <?php endforeach; ?>
        `;

        function crearPlantillaPersona(seccionIndex, personaIndex) {
            return `
                <div class="persona-item" data-person-index="${personaIndex}" style="border: 1px solid #ccd0d4; border-radius: 6px; padding: 20px; margin-bottom: 16px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative; animation: fadeIn 0.25s ease;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                        <div>
                            <label><strong><?php esc_html_e('Buscar y Vincular a Perfil', 'flacso-uruguay'); ?></strong></label><br>
                            <div style="display: flex; gap: 8px; margin-top: 4px;">
                                <div style="flex-grow: 1;">
                                    <select name="secciones[${seccionIndex}][personas][${personaIndex}][docente_id]" class="docente-select" style="width: 100%;">
                                        ${opcionesDocentesHtml}
                                    </select>
                                </div>
                            </div>
                            <span class="description small"><?php esc_html_e('Toma foto, prefijo y CV.', 'flacso-uruguay'); ?></span>
                        </div>
                        <div>
                            <label><strong><?php esc_html_e('Prefijo (Ej: Dra., Mag., Cra.)', 'flacso-uruguay'); ?></strong></label><br>
                            <input
                                type="text"
                                name="secciones[${seccionIndex}][personas][${personaIndex}][prefijo]"
                                class="regular-text"
                                style="width: 100%; margin-top: 4px; border-radius: 4px; height: 36px;"
                                placeholder="<?php esc_attr_e('Ej: Mag.', 'flacso-uruguay'); ?>"
                            >
                            <span class="description small"><?php esc_html_e('Aparece antes del nombre.', 'flacso-uruguay'); ?></span>
                        </div>
                        <div>
                            <label><strong><?php esc_html_e('Nombre / Apellido', 'flacso-uruguay'); ?></strong></label><br>
                            <input
                                type="text"
                                name="secciones[${seccionIndex}][personas][${personaIndex}][nombre_manual]"
                                class="regular-text nombre-manual-input"
                                style="width: 100%; margin-top: 4px; border-radius: 4px; height: 36px;"
                                placeholder="<?php esc_attr_e('Ej: Juan Pérez', 'flacso-uruguay'); ?>"
                            >
                            <span class="description small"><?php esc_html_e('Se usa si es manual o para sobrescribir.', 'flacso-uruguay'); ?></span>
                        </div>
                        <div>
                            <label><strong><?php esc_html_e('Título Académico', 'flacso-uruguay'); ?></strong></label><br>
                            <input
                                type="text"
                                name="secciones[${seccionIndex}][personas][${personaIndex}][titulo_academico]"
                                class="regular-text"
                                style="width: 100%; margin-top: 4px; border-radius: 4px; height: 36px;"
                                placeholder="<?php esc_attr_e('Ej: Doctor en Sociología', 'flacso-uruguay'); ?>"
                            >
                            <span class="description small"><?php esc_html_e('Aparece bajo el nombre.', 'flacso-uruguay'); ?></span>
                        </div>
                        <div>
                            <label><strong><?php esc_html_e('Cargo en la Comisión', 'flacso-uruguay'); ?> <span style="color:#d63638;">*</span></strong></label><br>
                            <input
                                type="text"
                                name="secciones[${seccionIndex}][personas][${personaIndex}][cargo]"
                                class="regular-text"
                                style="width: 100%; margin-top: 4px; border-radius: 4px; border-color: #1d3a72; height: 36px;"
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
                                style="width: 100%; margin-top: 4px; border-radius: 4px; height: 36px;"
                                placeholder="<?php esc_attr_e('Ej: Programa Educación', 'flacso-uruguay'); ?>"
                            >
                            <span class="description small"><?php esc_html_e('Especial para áreas académicas.', 'flacso-uruguay'); ?></span>
                        </div>
                        <div>
                            <label><strong><?php esc_html_e('Enlace Opcional del Programa', 'flacso-uruguay'); ?></strong></label><br>
                            <input
                                type="url"
                                name="secciones[${seccionIndex}][personas][${personaIndex}][enlace]"
                                class="regular-text code"
                                style="width: 100%; margin-top: 4px; border-radius: 4px; height: 36px;"
                                placeholder="https://..."
                            >
                            <span class="description small"><?php esc_html_e('Convierte el programa en enlace.', 'flacso-uruguay'); ?></span>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label><strong><?php esc_html_e('Biografía / CV (Para Ingreso Manual)', 'flacso-uruguay'); ?></strong></label><br>
                            <textarea
                                name="secciones[${seccionIndex}][personas][${personaIndex}][cv]"
                                class="large-text"
                                rows="2"
                                style="width: 100%; margin-top: 4px; border-radius: 4px;"
                                placeholder="<?php esc_attr_e('Escribe la biografía o texto del currículum para mostrar en la tarjeta si no tiene perfil vinculado...', 'flacso-uruguay'); ?>"
                            ></textarea>
                        </div>
                    </div>
                    <div style="text-align: right; margin-top: 16px; border-top: 1px dashed #eee; padding-top: 16px;">
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
                                    style="font-size: 16px; font-weight: bold; width: 280px; border-radius: 4px; height: 36px;"
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
                        <button type="button" class="button add-persona-btn" style="border-color: #1d3a72; color: #1d3a72; background: #fff; height: 38px;">
                            <span class="dashicons dashicons-plus" style="vertical-align: middle;"></span>
                            <strong><?php esc_html_e('Añadir integrante a esta comisión', 'flacso-uruguay'); ?></strong>
                        </button>
                    </div>
                </div>
            `;
        }

        container.on('click', '.add-persona-btn', function() {
            const seccionBox = $(this).closest('.seccion-box');
            const seccionIndex = seccionBox.attr('data-index');
            const personasList = seccionBox.find('.personas-list');
            personasList.find('.no-personas-msg').remove();

            const uniquePersonIndex = 'new_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
            const newElem = $(crearPlantillaPersona(seccionIndex, uniquePersonIndex));
            personasList.append(newElem);

            initSelect2(newElem.find('.docente-select'));
        });

        container.on('click', '.delete-persona-btn', function() {
            const personaItem = $(this).closest('.persona-item');
            const personasList = personaItem.closest('.personas-list');
            personaItem.remove();
            if (personasList.children('.persona-item').length === 0) {
                personasList.append('<p class="no-personas-msg description" style="font-style: italic; color: #888;"><?php esc_html_e('No hay integrantes en esta comisión. Añade uno abajo.', 'flacso-uruguay'); ?></p>');
            }
        });

        container.on('click', '.delete-seccion-btn', function() {
            if (confirm('<?php esc_html_e('¿Estás seguro de que deseas eliminar esta comisión completa con todos sus integrantes?', 'flacso-uruguay'); ?>')) {
                $(this).closest('.seccion-box').remove();
            }
        });

        addSeccionBtn.on('click', function() {
            const uniqueSeccionIndex = 'sec_' + Date.now();
            container.append(crearPlantillaSeccion(uniqueSeccionIndex));
        });

        $(document).on('change', '.docente-select', function() {
            const select = $(this);
            const personaItem = select.closest('.persona-item');
            const inputNombre = personaItem.find('.nombre-manual-input');
            const selectedText = select.find('option:selected').text().trim();
            const selectedValue = select.val();

            if (selectedValue !== '0') {
                if (inputNombre.length && inputNombre.val().trim() === '') {
                    inputNombre.attr('placeholder', 'Automático: ' + selectedText);
                }
                let editBtn = personaItem.find('.edit-doc-link');
                const editUrl = '<?php echo esc_url(admin_url('post.php?action=edit&post=')); ?>' + selectedValue;
                if (editBtn.length === 0) {
                    select.parent().append(`<a href="${editUrl}" target="_blank" class="button button-small edit-doc-link" title="Editar perfil en nueva pestaña" style="display: flex; align-items: center; justify-content: center; height: 36px; width: 36px; margin-left: 8px;"><span class="dashicons dashicons-edit"></span></a>`);
                } else {
                    editBtn.attr('href', editUrl);
                }
            } else {
                if (inputNombre.length) {
                    inputNombre.attr('placeholder', '<?php esc_attr_e('Ej: Juan Pérez', 'flacso-uruguay'); ?>');
                }
                personaItem.find('.edit-doc-link').remove();
            }
        });
    });
    </script>

    <style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .select2-container--default .select2-selection--single {
        height: 36px !important;
        border: 1px solid #ccd0d4 !important;
        border-radius: 4px !important;
        line-height: 34px !important;
        background-color: #fff !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 34px !important;
        padding-left: 12px !important;
        color: #2c3338 !important;
        font-size: 14px !important;
        font-weight: 500 !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 34px !important;
        right: 8px !important;
    }
    .select2-dropdown {
        border-color: #1d3a72 !important;
        border-radius: 4px !important;
        box-shadow: 0 8px 24px rgba(29, 58, 114, 0.15) !important;
    }
    .select2-search__field {
        border-radius: 4px !important;
        padding: 6px 12px !important;
        border: 1px solid #ccd0d4 !important;
    }
    .select2-search__field:focus {
        border-color: #1d3a72 !important;
        outline: none !important;
    }
    .select2-results__option--highlighted[aria-selected] {
        background-color: #1d3a72 !important;
        color: #fed222 !important;
        font-weight: bold !important;
    }
    
    .flacso-admin-wrap input:focus,
    .flacso-admin-wrap textarea:focus {
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
