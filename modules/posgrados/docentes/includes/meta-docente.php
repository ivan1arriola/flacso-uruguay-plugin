<?php
if (!defined('ABSPATH')) exit;

// ==================================================
// Registrar campos meta del CPT "docente"
// ==================================================
add_action('init', function() {
    $campos = ['prefijo_abrev', 'prefijo_full', 'nombre', 'apellido', 'cv'];
    foreach ($campos as $campo) {
        register_post_meta('docente', $campo, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);
    }

    register_post_meta('docente', 'docente_correos', [
        'type' => 'array',
        'single' => true,
        'show_in_rest' => [
            'schema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'email' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'principal' => ['type' => 'boolean'],
                    ],
                ],
            ],
        ],
    ]);

    register_post_meta('docente', 'docente_redes', [
        'type' => 'array',
        'single' => true,
        'show_in_rest' => [
            'schema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'label' => ['type' => 'string'],
                        'url' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ]);
});

// ==================================================
// Metabox información de docente
// ==================================================
add_action('add_meta_boxes', function() {
    add_meta_box(
        'docente_info',
        __('Información del Docente'),
        function($post) {
            $prefijo_abrev = get_post_meta($post->ID, 'prefijo_abrev', true);
            $prefijo_full  = get_post_meta($post->ID, 'prefijo_full', true);
            $nombre        = get_post_meta($post->ID, 'nombre', true);
            $apellido      = get_post_meta($post->ID, 'apellido', true);
            $cv            = get_post_meta($post->ID, 'cv', true);
            $correos       = get_post_meta($post->ID, 'docente_correos', true);
            $redes         = get_post_meta($post->ID, 'docente_redes', true);

            if (!is_array($correos)) $correos = [];
            if (!is_array($redes)) $redes = [];

            $principal_index = null;
            foreach ($correos as $idx => $correo) {
                if (!empty($correo['principal'])) {
                    $principal_index = $idx;
                    break;
                }
            }

            if (empty($correos)) {
                $correos = [
                    ['email' => '', 'label' => '', 'principal' => true]
                ];
                $principal_index = 0;
            } elseif ($principal_index === null) {
                $principal_index = 0;
            }

            echo '<p><label>Prefijo Académico (abreviado)</label><br>
                  <input type="text" name="prefijo_abrev" value="'.esc_attr($prefijo_abrev).'" placeholder="Ing., Dra., Dr." style="width:100%"></p>';

            echo '<p><label>Prefijo Académico (completo)</label><br>
                  <input type="text" name="prefijo_full" value="'.esc_attr($prefijo_full).'" placeholder="Ingeniero, Doctora, Doctor" style="width:100%"></p>';

            echo '<p><label>Nombre <span style="color:red">*</span></label><br>
                  <input type="text" name="nombre" value="'.esc_attr($nombre).'" style="width:100%" required></p>';

            echo '<p><label>Apellido <span style="color:red">*</span></label><br>
                  <input type="text" name="apellido" value="'.esc_attr($apellido).'" style="width:100%" required></p>';

            echo '<p><label>CV <span style="color:red">*</span></label><br>';
            wp_editor($cv, 'cv', [
                'textarea_name' => 'cv',
                'media_buttons' => false,
                'textarea_rows' => 10,
                'teeny'         => false,
                'tinymce'       => true,
            ]);
            echo '</p>';

            echo '<hr>';
            echo '<h3>Correos electrónicos</h3>';
            echo '<p class="description">Puedes agregar varios correos. Si hay más de uno, marca cuál será el principal que se mostrará al público.</p>';

            $correo_count = count($correos);
            echo '<div id="docente-correos" class="docente-repeatable" data-next-index="'.esc_attr($correo_count).'">';
            echo '<div class="correo-list">';
            foreach ($correos as $index => $correo) {
                $email = isset($correo['email']) ? $correo['email'] : '';
                $label = isset($correo['label']) ? $correo['label'] : '';
                $is_principal = ($principal_index === $index);
                ?>
                <div class="correo-row" data-row="<?php echo esc_attr($index); ?>">
                    <div class="correo-field">
                        <label class="screen-reader-text" for="correo-<?php echo esc_attr($index); ?>">Correo electrónico</label>
                        <input type="email" id="correo-<?php echo esc_attr($index); ?>" name="docente_correos[<?php echo esc_attr($index); ?>][email]" value="<?php echo esc_attr($email); ?>" placeholder="correo@ejemplo.com" style="width:100%;">
                    </div>
                    <div class="correo-field">
                        <label class="screen-reader-text" for="correo-label-<?php echo esc_attr($index); ?>">Etiqueta</label>
                        <input type="text" id="correo-label-<?php echo esc_attr($index); ?>" name="docente_correos[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="Institucional, Personal, etc." style="width:100%;">
                    </div>
                    <div class="correo-principal-option">
                        <label>
                            <input type="radio" name="docente_correo_principal" value="<?php echo esc_attr($index); ?>" <?php checked($is_principal); ?>>
                            Principal
                        </label>
                    </div>
                    <button type="button" class="button button-link-delete delete-correo" aria-label="Eliminar correo">Eliminar</button>
                </div>
                <?php
            }
            echo '</div>';
            echo '<button type="button" class="button add-correo">Añadir correo</button>';
            echo '</div>';

            echo '<hr>';
            echo '<h3>Redes sociales</h3>';
            echo '<p class="description">Agrega enlaces a perfiles sociales o profesionales relevantes. Se mostrará un listado accesible en el frontend.</p>';

            if (empty($redes)) {
                $redes = [
                    ['label' => '', 'url' => '']
                ];
            }

            $red_count = count($redes);
            echo '<div id="docente-redes" class="docente-repeatable" data-next-index="'.esc_attr($red_count).'">';
            echo '<div class="red-list">';
            foreach ($redes as $index => $red) {
                $label = isset($red['label']) ? $red['label'] : '';
                $url = isset($red['url']) ? $red['url'] : '';
                ?>
                <div class="red-row" data-row="<?php echo esc_attr($index); ?>">
                    <div class="red-field">
                        <label class="screen-reader-text" for="red-label-<?php echo esc_attr($index); ?>">Nombre de la red</label>
                        <input type="text" id="red-label-<?php echo esc_attr($index); ?>" name="docente_redes[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="LinkedIn, Twitter, Sitio personal" style="width:100%;">
                    </div>
                    <div class="red-field">
                        <label class="screen-reader-text" for="red-url-<?php echo esc_attr($index); ?>">URL</label>
                        <input type="url" id="red-url-<?php echo esc_attr($index); ?>" name="docente_redes[<?php echo esc_attr($index); ?>][url]" value="<?php echo esc_attr($url); ?>" placeholder="https://..." style="width:100%;">
                    </div>
                    <button type="button" class="button button-link-delete delete-red" aria-label="Eliminar red social">Eliminar</button>
                </div>
                <?php
            }
            echo '</div>';
            echo '<button type="button" class="button add-red">Añadir red social</button>';
            echo '</div>';

            ?>
            <style>
                .docente-repeatable {
                    border: 1px solid #e2e4e7;
                    padding: 15px;
                    border-radius: 6px;
                    margin-bottom: 20px;
                }
                .correo-row,
                .red-row {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                    gap: 10px;
                    padding: 10px;
                    border-bottom: 1px solid #f0f0f0;
                }
                .correo-row:last-child,
                .red-row:last-child {
                    border-bottom: 0;
                }
                .button-link-delete {
                    color: #d63638;
                    padding: 0;
                    height: auto;
                }
                .correo-principal-option {
                    display: flex;
                    align-items: center;
                }
                .docente-repeatable .description {
                    margin-bottom: 10px;
                }
            </style>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const correoWrapper = document.getElementById('docente-correos');
                    const redWrapper = document.getElementById('docente-redes');

                    function correoTemplate(index) {
                        return `
                            <div class="correo-row" data-row="${index}">
                                <div class="correo-field">
                                    <label class="screen-reader-text" for="correo-${index}">Correo electrónico</label>
                                    <input type="email" id="correo-${index}" name="docente_correos[${index}][email]" placeholder="correo@ejemplo.com" style="width:100%;">
                                </div>
                                <div class="correo-field">
                                    <label class="screen-reader-text" for="correo-label-${index}">Etiqueta</label>
                                    <input type="text" id="correo-label-${index}" name="docente_correos[${index}][label]" placeholder="Institucional, Personal, etc." style="width:100%;">
                                </div>
                                <div class="correo-principal-option">
                                    <label>
                                        <input type="radio" name="docente_correo_principal" value="${index}">
                                        Principal
                                    </label>
                                </div>
                                <button type="button" class="button button-link-delete delete-correo" aria-label="Eliminar correo">Eliminar</button>
                            </div>
                        `;
                    }

                    function redTemplate(index) {
                        return `
                            <div class="red-row" data-row="${index}">
                                <div class="red-field">
                                    <label class="screen-reader-text" for="red-label-${index}">Nombre de la red</label>
                                    <input type="text" id="red-label-${index}" name="docente_redes[${index}][label]" placeholder="LinkedIn, Twitter, Sitio personal" style="width:100%;">
                                </div>
                                <div class="red-field">
                                    <label class="screen-reader-text" for="red-url-${index}">URL</label>
                                    <input type="url" id="red-url-${index}" name="docente_redes[${index}][url]" placeholder="https://..." style="width:100%;">
                                </div>
                                <button type="button" class="button button-link-delete delete-red" aria-label="Eliminar red social">Eliminar</button>
                            </div>
                        `;
                    }

                    if (correoWrapper) {
                        const list = correoWrapper.querySelector('.correo-list');
                        let nextIndex = parseInt(correoWrapper.getAttribute('data-next-index'), 10) || list.children.length;
                        correoWrapper.querySelector('.add-correo').addEventListener('click', function() {
                            list.insertAdjacentHTML('beforeend', correoTemplate(nextIndex));
                            nextIndex++;
                        });
                        list.addEventListener('click', function(event) {
                            if (event.target.classList.contains('delete-correo')) {
                                event.preventDefault();
                                const row = event.target.closest('.correo-row');
                                if (row) {
                                    row.remove();
                                }
                            }
                        });
                    }

                    if (redWrapper) {
                        const list = redWrapper.querySelector('.red-list');
                        let nextIndex = parseInt(redWrapper.getAttribute('data-next-index'), 10) || list.children.length;
                        redWrapper.querySelector('.add-red').addEventListener('click', function() {
                            list.insertAdjacentHTML('beforeend', redTemplate(nextIndex));
                            nextIndex++;
                        });
                        list.addEventListener('click', function(event) {
                            if (event.target.classList.contains('delete-red')) {
                                event.preventDefault();
                                const row = event.target.closest('.red-row');
                                if (row) {
                                    row.remove();
                                }
                            }
                        });
                    }
                });
            </script>
            <?php
        },
        'docente'
    );
});

// ==================================================
// Guardar metabox docente
// ==================================================
add_action('save_post_docente', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // Campos obligatorios
    $obligatorios = ['nombre', 'apellido', 'cv'];
    foreach ($obligatorios as $campo) {
        if (empty($_POST[$campo])) {
            add_filter('redirect_post_location', function($location) use ($campo) {
                return add_query_arg('missing_'.$campo, 1, $location);
            });
            return;
        }
    }

    foreach (['prefijo_abrev', 'prefijo_full', 'nombre', 'apellido', 'cv'] as $campo) {
        if (isset($_POST[$campo])) {
            if ($campo === 'cv') {
                update_post_meta($post_id, $campo, wp_kses_post($_POST[$campo]));
            } else {
                update_post_meta($post_id, $campo, sanitize_text_field($_POST[$campo]));
            }
        }
    }

    // Correos electrónicos
    if (array_key_exists('docente_correos', $_POST)) {
        $correos_raw = (array) wp_unslash($_POST['docente_correos']);
        $principal_key = isset($_POST['docente_correo_principal']) ? sanitize_text_field(wp_unslash($_POST['docente_correo_principal'])) : '';
        $correos_clean = [];
        foreach ($correos_raw as $key => $correo) {
            if (!is_array($correo)) continue;
            $email = isset($correo['email']) ? sanitize_email($correo['email']) : '';
            if (!$email) continue;
            $label = isset($correo['label']) ? sanitize_text_field($correo['label']) : '';
            $correos_clean[] = [
                'email' => $email,
                'label' => $label,
                'principal' => ((string) $key === (string) $principal_key),
            ];
        }

        if (!empty($correos_clean)) {
            $has_principal = false;
            foreach ($correos_clean as $item) {
                if (!empty($item['principal'])) {
                    $has_principal = true;
                    break;
                }
            }
            if (!$has_principal) {
                $correos_clean[0]['principal'] = true;
            }
            update_post_meta($post_id, 'docente_correos', $correos_clean);
        } else {
            delete_post_meta($post_id, 'docente_correos');
        }
    }

    // Redes sociales
    if (array_key_exists('docente_redes', $_POST)) {
        $redes_raw = (array) wp_unslash($_POST['docente_redes']);
        $redes_clean = [];
        foreach ($redes_raw as $red) {
            if (!is_array($red)) continue;
            $label = isset($red['label']) ? sanitize_text_field($red['label']) : '';
            $url = isset($red['url']) ? esc_url_raw($red['url']) : '';
            if (!$url) continue;
            $redes_clean[] = [
                'label' => $label,
                'url' => $url,
            ];
        }

        if (!empty($redes_clean)) {
            update_post_meta($post_id, 'docente_redes', $redes_clean);
        } else {
            delete_post_meta($post_id, 'docente_redes');
        }
    }
});

// ==================================================
// Avisos si faltan campos obligatorios
// ==================================================
add_action('admin_notices', function() {
    $campos = ['nombre','apellido','cv'];
    foreach ($campos as $campo) {
        if (isset($_GET['missing_'.$campo])) {
            echo '<div class="notice notice-error is-dismissible">
                    <p>❌ El campo <strong>'.$campo.'</strong> es obligatorio.</p>
                  </div>';
        }
    }
});
