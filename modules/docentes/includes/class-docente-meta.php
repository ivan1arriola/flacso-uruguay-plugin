<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestiona los metadatos de docentes e integrantes del equipo FLACSO.
 */
class Docente_Meta {
    public const ROLE_DOCENTE = 'docente';
    public const ROLE_ADMINISTRATIVO = 'administrativo';

    public static function roles_disponibles(): array {
        return [
            self::ROLE_DOCENTE        => __('Docente / Académico', 'flacso-uruguay'),
            self::ROLE_ADMINISTRATIVO => __('Equipo Administrativo / Gestión', 'flacso-uruguay'),
        ];
    }

    public static function init(): void {
        self::register_meta_fields();
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_docente', [__CLASS__, 'save_meta'], 10, 1);
        add_action('admin_notices', [__CLASS__, 'admin_notices']);
    }

    public static function register_meta_fields(): void {
        $campos = ['prefijo_abrev', 'titulo_academico', 'nombre', 'apellido', 'cv', 'cargo'];
        foreach ($campos as $campo) {
            register_post_meta('docente', $campo, [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
            ]);
        }

        register_post_meta('docente', 'roles', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
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
    }

    public static function add_meta_boxes(): void {
        add_meta_box(
            'docente_info',
            __('Información del Integrante / Perfil', 'flacso-uruguay'),
            [__CLASS__, 'render_info_meta_box'],
            'docente',
            'normal',
            'default'
        );
    }

    public static function render_info_meta_box($post): void {
        $prefijo_abrev    = (string) get_post_meta($post->ID, 'prefijo_abrev', true);
        $titulo_academico = (string) get_post_meta($post->ID, 'titulo_academico', true);
        $cargo            = (string) get_post_meta($post->ID, 'cargo', true);
        $nombre           = (string) get_post_meta($post->ID, 'nombre', true);
        $apellido         = (string) get_post_meta($post->ID, 'apellido', true);
        $cv               = (string) get_post_meta($post->ID, 'cv', true);
        $roles            = self::get_roles($post->ID);
        $redes            = get_post_meta($post->ID, 'docente_redes', true);
        if (!is_array($redes)) $redes = [];

        ?>
        <div style="margin-bottom: 20px; padding: 12px 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
            <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                <?php esc_html_e('Roles en FLACSO Uruguay:', 'flacso-uruguay'); ?>
            </label>
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="checkbox" name="roles[]" value="docente" <?php checked(in_array('docente', $roles, true)); ?>>
                    <span><strong><?php esc_html_e('Docente / Académico', 'flacso-uruguay'); ?></strong> <small style="color: #64748b;">(imparte cursos, seminarios o posgrados)</small></span>
                </label>
                <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="checkbox" name="roles[]" value="administrativo" <?php checked(in_array('administrativo', $roles, true)); ?>>
                    <span><strong><?php esc_html_e('Equipo Administrativo / Gestión', 'flacso-uruguay'); ?></strong> <small style="color: #64748b;">(coordinación, administración, soporte)</small></span>
                </label>
            </div>
        </div>

        <p><label><strong><?php esc_html_e('Cargo / Rol Institucional (opcional)', 'flacso-uruguay'); ?></strong></label><br>
        <input type="text" name="cargo" value="<?php echo esc_attr($cargo); ?>" placeholder="<?php esc_attr_e('Ej: Secretaría Académica, Coordinación de Posgrados, Asistente de Gestión', 'flacso-uruguay'); ?>" style="width:100%"></p>

        <p><label><?php esc_html_e('Prefijo Académico (abreviado)', 'flacso-uruguay'); ?></label><br>
        <input type="text" name="prefijo_abrev" value="<?php echo esc_attr($prefijo_abrev); ?>" placeholder="Ing., Dra., Dr." style="width:100%"></p>

        <p><label><?php esc_html_e('Título Académico', 'flacso-uruguay'); ?></label><br>
        <input type="text" name="titulo_academico" value="<?php echo esc_attr($titulo_academico); ?>" placeholder="<?php esc_attr_e('Ingeniero, Doctora, Doctor, Licenciado', 'flacso-uruguay'); ?>" style="width:100%"></p>

        <p><label><?php esc_html_e('Nombre', 'flacso-uruguay'); ?> <span style="color:red">*</span></label><br>
        <input type="text" name="nombre" value="<?php echo esc_attr($nombre); ?>" style="width:100%" required></p>

        <p><label><?php esc_html_e('Apellido', 'flacso-uruguay'); ?> <span style="color:red">*</span></label><br>
        <input type="text" name="apellido" value="<?php echo esc_attr($apellido); ?>" style="width:100%" required></p>

        <p><label><?php esc_html_e('Trayectoria Profesional / Resumen (CV)', 'flacso-uruguay'); ?> <span style="color:red">*</span></label><br>
        <?php
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
        echo '<h3>' . esc_html__('Redes sociales y enlaces', 'flacso-uruguay') . '</h3>';
        echo '<p class="description">' . esc_html__('Agrega enlaces a perfiles sociales o profesionales relevantes.', 'flacso-uruguay') . '</p>';

        if (empty($redes)) {
            $redes = [
                ['label' => '', 'url' => '']
            ];
        }

        $red_count = count($redes);
        echo '<div id="docente-redes" class="docente-repeatable" data-next-index="' . esc_attr((string) $red_count) . '">';
        echo '<div class="red-list">';
        foreach ($redes as $index => $red) {
            $label = isset($red['label']) ? $red['label'] : '';
            $url = isset($red['url']) ? $red['url'] : '';
            ?>
            <div class="red-row" data-row="<?php echo esc_attr((string) $index); ?>">
                <div class="red-field">
                    <label class="screen-reader-text" for="red-label-<?php echo esc_attr((string) $index); ?>"><?php esc_html_e('Nombre de la red', 'flacso-uruguay'); ?></label>
                    <input type="text" id="red-label-<?php echo esc_attr((string) $index); ?>" name="docente_redes[<?php echo esc_attr((string) $index); ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="LinkedIn, Twitter, Sitio personal" style="width:100%;">
                </div>
                <div class="red-field">
                    <label class="screen-reader-text" for="red-url-<?php echo esc_attr((string) $index); ?>"><?php esc_html_e('URL', 'flacso-uruguay'); ?></label>
                    <input type="url" id="red-url-<?php echo esc_attr((string) $index); ?>" name="docente_redes[<?php echo esc_attr((string) $index); ?>][url]" value="<?php echo esc_attr($url); ?>" placeholder="https://..." style="width:100%;">
                </div>
                <button type="button" class="button button-link-delete delete-red" aria-label="<?php esc_attr_e('Eliminar red social', 'flacso-uruguay'); ?>"><?php esc_html_e('Eliminar', 'flacso-uruguay'); ?></button>
            </div>
            <?php
        }
        echo '</div>';
        echo '<button type="button" class="button add-red">' . esc_html__('Añadir enlace / red', 'flacso-uruguay') . '</button>';
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

        foreach (['prefijo_abrev', 'titulo_academico', 'cargo', 'nombre', 'apellido', 'cv'] as $campo) {
            if (isset($_POST[$campo])) {
                if ($campo === 'cv') {
                    update_post_meta($post_id, $campo, wp_kses_post($_POST[$campo]));
                } else {
                    update_post_meta($post_id, $campo, sanitize_text_field($_POST[$campo]));
                }
            }
        }

        // Roles asignables
        $raw_roles = isset($_POST['roles']) && is_array($_POST['roles']) ? $_POST['roles'] : [];
        $clean_roles = [];
        foreach ($raw_roles as $r) {
            $r_key = sanitize_key((string) $r);
            if (in_array($r_key, [self::ROLE_DOCENTE, self::ROLE_ADMINISTRATIVO], true)) {
                $clean_roles[] = $r_key;
            }
        }
        if (empty($clean_roles)) {
            $clean_roles = [self::ROLE_DOCENTE];
        }
        update_post_meta($post_id, 'roles', array_values(array_unique($clean_roles)));

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
        $campos = ['nombre', 'apellido', 'cv'];
        foreach ($campos as $campo) {
            if (isset($_GET['missing_'.$campo])) {
                echo '<div class="notice notice-error is-dismissible">
                        <p>❌ El campo <strong>'.$campo.'</strong> es obligatorio.</p>
                      </div>';
            }
        }
    }

    /**
     * Obtener los roles de una persona. Por compatibilidad, devuelve ['docente'] si no tiene metadatos.
     *
     * @param int $post_id
     * @return string[]
     */
    public static function get_roles(int $post_id): array {
        $roles = get_post_meta($post_id, 'roles', true);
        if (is_array($roles) && !empty($roles)) {
            return array_values(array_map('sanitize_key', $roles));
        }
        return [self::ROLE_DOCENTE];
    }

    public static function is_docente(int $post_id): bool {
        return in_array(self::ROLE_DOCENTE, self::get_roles($post_id), true);
    }

    public static function is_administrativo(int $post_id): bool {
        return in_array(self::ROLE_ADMINISTRATIVO, self::get_roles($post_id), true);
    }

    /**
     * Obtener metadatos de un docente / integrante
     */
    public static function get_docente_data($post_id): array {
        return [
            'prefijo_abrev'    => get_post_meta($post_id, 'prefijo_abrev', true),
            'titulo_academico' => get_post_meta($post_id, 'titulo_academico', true),
            'cargo'            => get_post_meta($post_id, 'cargo', true),
            'nombre'           => get_post_meta($post_id, 'nombre', true),
            'apellido'         => get_post_meta($post_id, 'apellido', true),
            'cv'               => get_post_meta($post_id, 'cv', true),
            'roles'            => self::get_roles((int) $post_id),
            'redes'            => get_post_meta($post_id, 'docente_redes', true),
        ];
    }
}
