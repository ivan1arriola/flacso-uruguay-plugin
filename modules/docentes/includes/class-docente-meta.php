<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestiona los metadatos de docentes (igual al original flacso-posgrados-docentes)
 */
class Docente_Meta {
    
    public static function init(): void {
        self::register_meta_fields();
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_docente', [__CLASS__, 'save_meta'], 10, 1);
        add_action('admin_notices', [__CLASS__, 'admin_notices']);
    }

    public static function register_meta_fields(): void {
        $campos = ['prefijo_abrev', 'titulo_academico', 'nombre', 'apellido', 'cv'];
        foreach ($campos as $campo) {
            register_post_meta('docente', $campo, [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
            ]);
        }

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
    }

    public static function add_meta_boxes(): void {
        add_meta_box(
            'docente_info',
            __('Información del Docente'),
            [__CLASS__, 'render_info_meta_box'],
            'docente',
            'normal',
            'default'
        );
    }

    public static function render_info_meta_box($post): void {
        $prefijo_abrev = get_post_meta($post->ID, 'prefijo_abrev', true);
        $titulo_academico = get_post_meta($post->ID, 'titulo_academico', true);
        $nombre        = get_post_meta($post->ID, 'nombre', true);
        $apellido      = get_post_meta($post->ID, 'apellido', true);
        $cv            = get_post_meta($post->ID, 'cv', true);
        $redes = get_post_meta($post->ID, 'docente_redes', true);
        if (!is_array($redes)) $redes = [];

        echo '<p><label>Prefijo Académico (abreviado)</label><br>
              <input type="text" name="prefijo_abrev" value="'.esc_attr($prefijo_abrev).'" placeholder="Ing., Dra., Dr." style="width:100%"></p>';

        echo '<p><label>Título</label><br>
              <input type="text" name="titulo_academico" value="'.esc_attr($titulo_academico).'" placeholder="Ingeniero, Doctora, Doctor, Licenciado" style="width:100%"></p>';

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
            'quicktags'     => true,
        ]);
        echo '</p>';

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

        self::render_inline_styles_and_scripts();
    }

    private static function render_inline_styles_and_scripts(): void {
        ?>
        <style>
            .docente-repeatable {
                border: 1px solid #e2e4e7;
                padding: 15px;
                border-radius: 6px;
                margin-bottom: 20px;
            }
            .red-row {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 10px;
                padding: 10px;
                border-bottom: 1px solid #f0f0f0;
            }
            .red-row:last-child {
                border-bottom: 0;
            }
            .button-link-delete {
                color: #d63638;
                padding: 0;
                height: auto;
            }
            .docente-repeatable .description {
                margin-bottom: 10px;
            }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const redWrapper = document.getElementById('docente-redes');

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
    }

    public static function save_meta($post_id): void {
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

        foreach (['prefijo_abrev', 'titulo_academico', 'nombre', 'apellido', 'cv'] as $campo) {
            if (isset($_POST[$campo])) {
                if ($campo === 'cv') {
                    update_post_meta($post_id, $campo, wp_kses_post($_POST[$campo]));
                } else {
                    update_post_meta($post_id, $campo, sanitize_text_field($_POST[$campo]));
                }
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
    }

    public static function admin_notices(): void {
        $campos = ['nombre','apellido','cv'];
        foreach ($campos as $campo) {
            if (isset($_GET['missing_'.$campo])) {
                echo '<div class="notice notice-error is-dismissible">
                        <p>❌ El campo <strong>'.$campo.'</strong> es obligatorio.</p>
                      </div>';
            }
        }
    }

    /**
     * Obtener metadatos de un docente
     */
    public static function get_docente_data($post_id): array {
        return [
            'prefijo_abrev' => get_post_meta($post_id, 'prefijo_abrev', true),
            'titulo_academico' => get_post_meta($post_id, 'titulo_academico', true),
            'nombre' => get_post_meta($post_id, 'nombre', true),
            'apellido' => get_post_meta($post_id, 'apellido', true),
            'cv' => get_post_meta($post_id, 'cv', true),
            'redes' => get_post_meta($post_id, 'docente_redes', true),
        ];
    }
}
