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
        'secciones' => [
            [
                'titulo' => 'Dirección',
                'personas' => [
                    ['cargo' => 'Directora', 'nombre_manual' => 'Dra. Ana Gabriela Fernández', 'docente_id' => 0, 'programa' => '', 'enlace' => ''],
                ],
            ],
            [
                'titulo' => 'Comisión Académica',
                'personas' => [
                    ['cargo' => 'Directora', 'nombre_manual' => 'Dra. Ana Gabriela Fernández', 'docente_id' => 0, 'programa' => '', 'enlace' => ''],
                    ['cargo' => 'Coordinadora Académica', 'nombre_manual' => 'Dra. Silvana Darré', 'docente_id' => 0, 'programa' => '', 'enlace' => ''],
                    ['cargo' => 'Secretaria Académica', 'nombre_manual' => 'Mag. Lena Fontela', 'docente_id' => 0, 'programa' => '', 'enlace' => ''],
                    ['cargo' => 'Coordinador Académico', 'nombre_manual' => 'Mag. José Miguel García', 'docente_id' => 0, 'programa' => 'Programa Educación, Ciencia y Tecnología', 'enlace' => ''],
                    ['cargo' => 'Coordinadora Académica', 'nombre_manual' => 'Dra. María Laura Osta Vázquez', 'docente_id' => 0, 'programa' => 'Programa Infancias y Adolescencias', 'enlace' => 'https://flacso.edu.uy/programa-infancias-y-adolescencia/'],
                    ['cargo' => 'Coordinadora Académica', 'nombre_manual' => 'Mag. Carla Rosso', 'docente_id' => 0, 'programa' => 'Programa Comprendiendo China', 'enlace' => ''],
                ],
            ],
            [
                'titulo' => 'Comisión Administrativa',
                'personas' => [
                    ['cargo' => 'Directora', 'nombre_manual' => 'Dra. Ana Gabriela Fernández', 'docente_id' => 0, 'programa' => '', 'enlace' => ''],
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

    if (!$data || !is_array($data) || empty($data['secciones'])) {
        $data = flacso_autoridades_default_data();
        update_option('flacso_autoridades_data', $data);
        return $data;
    }

    if (!isset($data['imagen_fondo'])) {
        $data['imagen_fondo'] = 'https://flacso.edu.uy/wp-content/uploads/2024/12/IMG_20220914_151738883-scaled-e1733249869591.jpg';
    }

    foreach ($data['secciones'] as &$seccion) {
        if (!isset($seccion['titulo'])) $seccion['titulo'] = '';
        if (!isset($seccion['personas']) || !is_array($seccion['personas'])) $seccion['personas'] = [];

        foreach ($seccion['personas'] as &$persona) {
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
            'secciones' => [],
        ];

        if (!empty($_POST['secciones']) && is_array($_POST['secciones'])) {
            foreach ($_POST['secciones'] as $seccion) {
                $nueva_seccion = [
                    'titulo' => sanitize_text_field($seccion['titulo'] ?? ''),
                    'personas' => [],
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
        echo '<div class="updated notice is-dismissible"><p><strong>' . esc_html__('Autoridades guardadas correctamente.', 'flacso-uruguay') . '</strong></p></div>';
    }

    $data = flacso_autoridades_get_data();

    // Obtener lista de docentes disponibles
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
                <?php esc_html_e('Nota: Puedes vincular cada autoridad a un perfil existente de la sección Docentes para que tome automáticamente su foto, CV y título. Si la persona no tiene un perfil creado (ej. secretarios o personal administrativo), selecciona "Ingreso Manual" y escribe su nombre.', 'flacso-uruguay'); ?>
            </p>
        </div>

        <form method="post" id="flacso-autoridades-form">
            <?php wp_nonce_field('guardar_flacso_autoridades', 'flacso_autoridades_nonce'); ?>

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
                            <p class="description">
                                <?php esc_html_e('Imagen de fondo que se mostrará en el banner superior del componente de autoridades.', 'flacso-uruguay'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="secciones-container">
                <?php foreach ($data['secciones'] as $i => $seccion): ?>
                    <div class="postbox seccion-box" data-index="<?php echo esc_attr($i); ?>" style="padding: 20px; margin-bottom: 24px; border-radius: 8px; border-left: 4px solid #1d3a72; background: #fbfbfb;">
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd; padding-bottom: 16px; margin-bottom: 16px;">
                            <div style="display: flex; align-items: center; width: 60%;">
                                <label style="font-size: 16px; font-weight: bold; margin-right: 12px; color: #1d3a72;"><?php esc_html_e('Pestaña / Sección:', 'flacso-uruguay'); ?></label>
                                <input
                                    type="text"
                                    name="secciones[<?php echo esc_attr($i); ?>][titulo]"
                                    value="<?php echo esc_attr($seccion['titulo']); ?>"
                                    class="regular-text"
                                    style="font-size: 16px; font-weight: bold; width: 300px; border-radius: 4px;"
                                    placeholder="Ej: Dirección, Comisión Académica"
                                >
                            </div>
                            <button type="button" class="button button-link-delete delete-seccion-btn" style="color: #d63638; text-decoration: none;">
                                <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span> <?php esc_html_e('Eliminar Sección', 'flacso-uruguay'); ?>
                            </button>
                        </div>

                        <div class="personas-list">
                            <h3 style="font-size: 14px; text-transform: uppercase; color: #555; margin-bottom: 12px;"><?php esc_html_e('Integrantes de esta sección', 'flacso-uruguay'); ?></h3>

                            <?php if (empty($seccion['personas'])): ?>
                                <p class="no-personas-msg description" style="font-style: italic; color: #888;"><?php esc_html_e('No hay integrantes en esta sección. Añade uno abajo.', 'flacso-uruguay'); ?></p>
                            <?php endif; ?>

                            <?php foreach ($seccion['personas'] as $j => $persona): ?>
                                <div class="persona-item" data-person-index="<?php echo esc_attr($j); ?>" style="border: 1px solid #ccd0d4; border-radius: 6px; padding: 16px; margin-bottom: 16px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.04); position: relative;">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">
                                        
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
                                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $persona['docente_id'] . '&action=edit')); ?>" target="_blank" class="button button-small edit-doc-link" title="<?php esc_attr_e('Editar perfil en nueva pestaña', 'flacso-uruguay'); ?>">
                                                        <span class="dashicons dashicons-edit" style="margin-top: 4px;"></span>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <span class="description small"><?php esc_html_e('Toma foto, título y CV de la ficha del docente.', 'flacso-uruguay'); ?></span>
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
                                                <?php echo ($persona['docente_id'] > 0) ? 'placeholder="Automático desde el perfil"' : ''; ?>
                                            >
                                            <span class="description small"><?php esc_html_e('Se usa si no vinculas un perfil o para sobrescribir el nombre.', 'flacso-uruguay'); ?></span>
                                        </div>

                                        <!-- Cargo -->
                                        <div>
                                            <label><strong><?php esc_html_e('Cargo / Rol en la Sección', 'flacso-uruguay'); ?> <span style="color:#d63638;">*</span></strong></label><br>
                                            <input
                                                type="text"
                                                name="secciones[<?php echo esc_attr($i); ?>][personas][<?php echo esc_attr($j); ?>][cargo]"
                                                value="<?php echo esc_attr($persona['cargo']); ?>"
                                                class="regular-text"
                                                style="width: 100%; margin-top: 4px; border-radius: 4px; border-color: #1d3a72;"
                                                placeholder="<?php esc_attr_e('Ej: Directora, Coordinador Académico', 'flacso-uruguay'); ?>"
                                            >
                                            <span class="description small"><?php esc_html_e('Aparece destacado en la tarjeta.', 'flacso-uruguay'); ?></span>
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
                                            <span class="description small"><?php esc_html_e('Especial para Comisión Académica.', 'flacso-uruguay'); ?></span>
                                        </div>

                                        <!-- Enlace Opcional -->
                                        <div>
                                            <label><strong><?php esc_html_e('Enlace Opcional del Programa / Bio', 'flacso-uruguay'); ?></strong></label><br>
                                            <input
                                                type="url"
                                                name="secciones[<?php echo esc_attr($i); ?>][personas][<?php echo esc_attr($j); ?>][enlace]"
                                                value="<?php echo esc_attr($persona['enlace']); ?>"
                                                class="regular-text code"
                                                style="width: 100%; margin-top: 4px; border-radius: 4px;"
                                                placeholder="https://..."
                                            >
                                            <span class="description small"><?php esc_html_e('Convierte el bloque del programa en enlace.', 'flacso-uruguay'); ?></span>
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
                                <strong><?php esc_html_e('Añadir integrante a esta sección', 'flacso-uruguay'); ?></strong>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Botón Añadir Sección -->
            <div style="margin-bottom: 30px; background: #eef2f8; padding: 20px; border-radius: 8px; text-align: center; border: 2px dashed #1d3a72;">
                <button type="button" id="add-seccion-btn" class="button button-primary button-large" style="background: #1d3a72; border-color: #1d3a72; font-size: 16px; padding: 6px 24px;">
                    <span class="dashicons dashicons-plus-alt2" style="vertical-align: middle; margin-top: 3px;"></span>
                    <?php esc_html_e('Añadir Nueva Pestaña / Sección', 'flacso-uruguay'); ?>
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

    <!-- Script para dinamismo del formulario en JS puro -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById('secciones-container');
        const addSeccionBtn = document.getElementById('add-seccion-btn');

        // Plantilla de opciones de docentes en formato string para inyectar rápido
        const opcionesDocentesHtml = `
            <option value="0"><?php esc_html_e('--- Ninguno / Ingreso Manual ---', 'flacso-uruguay'); ?></option>
            <?php foreach ($opciones_docentes as $id => $nombre_doc): ?>
                <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html(addslashes($nombre_doc)); ?></option>
            <?php endforeach; ?>
        `;

        function crearPlantillaPersona(seccionIndex, personaIndex) {
            return `
                <div class="persona-item" data-person-index="${personaIndex}" style="border: 1px solid #ccd0d4; border-radius: 6px; padding: 16px; margin-bottom: 16px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.04); position: relative; animation: fadeIn 0.25s ease;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">
                        <div>
                            <label><strong><?php esc_html_e('Vincular a Perfil Docente', 'flacso-uruguay'); ?></strong></label><br>
                            <div style="display: flex; gap: 8px; margin-top: 4px;">
                                <select name="secciones[${seccionIndex}][personas][${personaIndex}][docente_id]" class="docente-select" style="width: 100%; border-radius: 4px;">
                                    ${opcionesDocentesHtml}
                                </select>
                            </div>
                            <span class="description small"><?php esc_html_e('Toma foto, título y CV de la ficha del docente.', 'flacso-uruguay'); ?></span>
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
                            <span class="description small"><?php esc_html_e('Se usa si no vinculas un perfil o para sobrescribir el nombre.', 'flacso-uruguay'); ?></span>
                        </div>
                        <div>
                            <label><strong><?php esc_html_e('Cargo / Rol en la Sección', 'flacso-uruguay'); ?> <span style="color:#d63638;">*</span></strong></label><br>
                            <input
                                type="text"
                                name="secciones[${seccionIndex}][personas][${personaIndex}][cargo]"
                                class="regular-text"
                                style="width: 100%; margin-top: 4px; border-radius: 4px; border-color: #1d3a72;"
                                placeholder="<?php esc_attr_e('Ej: Directora, Coordinador Académico', 'flacso-uruguay'); ?>"
                            >
                            <span class="description small"><?php esc_html_e('Aparece destacado en la tarjeta.', 'flacso-uruguay'); ?></span>
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
                            <span class="description small"><?php esc_html_e('Especial para Comisión Académica.', 'flacso-uruguay'); ?></span>
                        </div>
                        <div>
                            <label><strong><?php esc_html_e('Enlace Opcional del Programa / Bio', 'flacso-uruguay'); ?></strong></label><br>
                            <input
                                type="url"
                                name="secciones[${seccionIndex}][personas][${personaIndex}][enlace]"
                                class="regular-text code"
                                style="width: 100%; margin-top: 4px; border-radius: 4px;"
                                placeholder="https://..."
                            >
                            <span class="description small"><?php esc_html_e('Convierte el bloque del programa en enlace.', 'flacso-uruguay'); ?></span>
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
                        <div style="display: flex; align-items: center; width: 60%;">
                            <label style="font-size: 16px; font-weight: bold; margin-right: 12px; color: #1d3a72;"><?php esc_html_e('Pestaña / Sección:', 'flacso-uruguay'); ?></label>
                            <input
                                type="text"
                                name="secciones[${seccionIndex}][titulo]"
                                class="regular-text"
                                style="font-size: 16px; font-weight: bold; width: 300px; border-radius: 4px;"
                                placeholder="Ej: Dirección, Comisión Académica"
                            >
                        </div>
                        <button type="button" class="button button-link-delete delete-seccion-btn" style="color: #d63638; text-decoration: none;">
                            <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span> <?php esc_html_e('Eliminar Sección', 'flacso-uruguay'); ?>
                        </button>
                    </div>
                    <div class="personas-list">
                        <h3 style="font-size: 14px; text-transform: uppercase; color: #555; margin-bottom: 12px;"><?php esc_html_e('Integrantes de esta sección', 'flacso-uruguay'); ?></h3>
                        <p class="no-personas-msg description" style="font-style: italic; color: #888;"><?php esc_html_e('No hay integrantes en esta sección. Añade uno abajo.', 'flacso-uruguay'); ?></p>
                    </div>
                    <div style="margin-top: 16px;">
                        <button type="button" class="button add-persona-btn" style="border-color: #1d3a72; color: #1d3a72; background: #fff;">
                            <span class="dashicons dashicons-plus" style="vertical-align: middle;"></span>
                            <strong><?php esc_html_e('Añadir integrante a esta sección', 'flacso-uruguay'); ?></strong>
                        </button>
                    </div>
                </div>
            `;
        }

        // Delegación de eventos global para el contenedor
        container.addEventListener('click', function (e) {
            // Añadir Persona
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

            // Eliminar Persona
            const deletePersonaBtn = e.target.closest('.delete-persona-btn');
            if (deletePersonaBtn) {
                const personaItem = deletePersonaBtn.closest('.persona-item');
                const personasList = personaItem.closest('.personas-list');
                personaItem.remove();
                if (personasList.children.length === 1 && personasList.querySelector('h3')) {
                    personasList.insertAdjacentHTML('beforeend', '<p class="no-personas-msg description" style="font-style: italic; color: #888;"><?php esc_html_e('No hay integrantes en esta sección. Añade uno abajo.', 'flacso-uruguay'); ?></p>');
                }
                return;
            }

            // Eliminar Sección
            const deleteSeccionBtn = e.target.closest('.delete-seccion-btn');
            if (deleteSeccionBtn) {
                if (confirm('<?php esc_html_e('¿Estás seguro de que deseas eliminar esta sección completa con todos sus integrantes?', 'flacso-uruguay'); ?>')) {
                    deleteSeccionBtn.closest('.seccion-box').remove();
                }
                return;
            }
        });

        // Evento para añadir nueva sección
        addSeccionBtn.addEventListener('click', function () {
            const uniqueSeccionIndex = 'sec_' + Date.now();
            container.insertAdjacentHTML('beforeend', crearPlantillaSeccion(uniqueSeccionIndex));
        });

        // Dinamismo del select de docente
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
