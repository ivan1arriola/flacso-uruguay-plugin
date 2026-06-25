<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Meta box para editar los campos estructurados de cada oferta académica.
 */
class Oferta_Data_MetaBox {
    private const HTML_FIELDS = [
        'modalidad_html',
        'duracion_html',
        'objetivos_html',
        'perfil_ingreso_html',
        'requisitos_ingreso_html',
        'perfil_egreso_html',
        'requisitos_egreso_html',
        'financiacion_html',
        'titulos_certificaciones_html',
    ];

    private const STRING_ARRAYS = [
        'menciones',
        'orientaciones',
    ];

    private const PERSONNEL_GROUPS = [
        'coordinacion_academica' => 'Rol',
        'equipos' => 'Nombre de equipo',
    ];

    public static function init(): void {
        add_action('add_meta_boxes', [self::class, 'add_meta_box']);
        add_action('save_post_oferta-academica', [self::class, 'save_meta'], 10, 2);
    }

    public static function add_meta_box(): void {
        /* 
        add_meta_box(
            'oferta_data_meta',
            __('Datos estructurados de la oferta', 'flacso-oferta-academica'),
            [self::class, 'render_meta_box'],
            'oferta-academica',
            'normal',
            'high'
        );
        */
        add_meta_box(
            'oferta_react_editor_link',
            __('Gestor FLACSO (App Externa)', 'flacso-oferta-academica'),
            [self::class, 'render_react_editor_link_meta_box'],
            'oferta-academica',
            'normal',
            'high'
        );
    }

    public static function render_react_editor_link_meta_box(\WP_Post $post): void {
        $base_url = get_option('flacso_external_editor_url', 'https://editor-flacso-uy.vercel.app');
        if (empty($base_url) || strpos($base_url, 'flacso.edu.uy') !== false) {
            $base_url = 'https://editor-flacso-uy.vercel.app';
        }
        $base_url = rtrim($base_url, '/');
        $edit_url = esc_url($base_url . '/ofertas/' . $post->ID);
        
        ?>
        <p><?php esc_html_e('También puedes administrar los módulos de esta oferta desde la aplicación moderna.', 'flacso-oferta-academica'); ?></p>
        <p>
            <a href="<?php echo $edit_url; ?>" target="_blank" class="button button-primary button-large" style="width: 100%; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 5px;">
                <?php esc_html_e('Abrir Gestor FLACSO', 'flacso-oferta-academica'); ?>
                <span class="dashicons dashicons-external" style="font-size: 16px; width: 16px; height: 16px;"></span>
            </a>
        </p>
        <?php
    }

    public static function render_meta_box(\WP_Post $post): void {
        wp_nonce_field('oferta_data_meta', 'oferta_data_meta_nonce');

        $values = self::get_saved_values($post->ID);
        ?>
        <div class="oferta-data-meta">
            <p class="description"><?php esc_html_e('Edita la duración, los próximos inicios y el contenido HTML que alimenta el JSON público.', 'flacso-oferta-academica'); ?></p>

            <table class="form-table">
                <tbody>
                    <tr>
                        <th><label for="oferta_data_abreviacion"><?php esc_html_e('Abreviación', 'flacso-oferta-academica'); ?></label></th>
                        <td><input type="text" id="oferta_data_abreviacion" name="oferta_data[abreviacion]" value="<?php echo esc_attr($values['abreviacion']); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="oferta_data_correo"><?php esc_html_e('Correo', 'flacso-oferta-academica'); ?></label></th>
                        <td><input type="email" id="oferta_data_correo" name="oferta_data[correo]" value="<?php echo esc_attr($values['correo']); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="oferta_data_duracion_meses"><?php esc_html_e('Duración (meses)', 'flacso-oferta-academica'); ?></label></th>
                        <td><input type="text" id="oferta_data_duracion_meses" name="oferta_data[duracion_meses]" value="<?php echo esc_attr($values['duracion_meses']); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="oferta_data_proximo_inicio"><?php esc_html_e('Próximo inicio', 'flacso-oferta-academica'); ?></label></th>
                        <td>
                            <?php
                            $precision = $values['precision'];
                            $type = 'date';
                            if ($precision === 'month') {
                                $type = 'month';
                            } elseif ($precision === 'year') {
                                $type = 'number';
                            }
                            ?>
                            <input type="<?php echo esc_attr($type); ?>" id="oferta_data_proximo_inicio" name="oferta_data[proximo_inicio]" value="<?php echo esc_attr($values['proximo_inicio']); ?>" class="regular-text" <?php echo $type === 'number' ? 'min="2000" max="2100"' : ''; ?> />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="oferta_data_cohorte"><?php esc_html_e('Cohorte', 'flacso-oferta-academica'); ?></label></th>
                        <td><input type="text" id="oferta_data_cohorte" name="oferta_data[cohorte]" value="<?php echo esc_attr($values['cohorte']); ?>" class="regular-text" placeholder="Ej. Cohorte 15 o Cohorte 2026" /></td>
                    </tr>
                    <tr>
                        <th><label for="oferta_data_calendario"><?php esc_html_e('Calendario (PDF URL)', 'flacso-oferta-academica'); ?></label></th>
                        <td>
                            <input type="url" id="oferta_data_calendario" name="oferta_data[calendario]" value="<?php echo esc_attr($values['calendario']); ?>" class="regular-text" readonly style="background: #f0f0f1; color: #8c8f94; pointer-events: none;" />
                            <p class="description" style="color: #d63638; font-weight: 600; margin-top: 5px;">
                                <span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                                <?php esc_html_e('Los documentos académicos ahora deben gestionarse exclusivamente desde el Gestor FLACSO.', 'flacso-oferta-academica'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="oferta_data_malla_curricular"><?php esc_html_e('Malla curricular (PDF URL)', 'flacso-oferta-academica'); ?></label></th>
                        <td>
                            <input type="url" id="oferta_data_malla_curricular" name="oferta_data[malla_curricular]" value="<?php echo esc_attr($values['malla_curricular']); ?>" class="regular-text" readonly style="background: #f0f0f1; color: #8c8f94; pointer-events: none;" />
                            <p class="description" style="color: #d63638; font-weight: 600; margin-top: 5px;">
                                <span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                                <?php esc_html_e('Los documentos académicos ahora deben gestionarse exclusivamente desde el Gestor FLACSO.', 'flacso-oferta-academica'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="oferta_data_precision"><?php esc_html_e('Precisión', 'flacso-oferta-academica'); ?></label></th>
                        <td>
                            <select id="oferta_data_precision" name="oferta_data[proximo_inicio_precision]">
                                <?php foreach (['day' => 'Day (YYYY-MM-DD)', 'month' => 'Month (YYYY-MM)', 'year' => 'Year (YYYY)'] as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($values['precision'], $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="oferta_data_inscripciones"><?php esc_html_e('Inscripciones abiertas', 'flacso-oferta-academica'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="oferta_data_inscripciones" name="oferta_data[inscripciones_abiertas]" value="1" <?php checked($values['inscripciones_abiertas'], '1'); ?> />
                                <?php esc_html_e('Activar cuando el programa reciba inscripciones', 'flacso-oferta-academica'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="oferta_data_tabla_precio_id"><?php esc_html_e('Tabla de Precios Vinculada', 'flacso-oferta-academica'); ?></label></th>
                        <td>
                            <select id="oferta_data_tabla_precio_id" name="oferta_data[tabla_precio_id]">
                                <option value="0"><?php esc_html_e('Ninguna', 'flacso-oferta-academica'); ?></option>
                                <?php
                                $tablas = get_posts([
                                    'post_type' => 'tabla-precio',
                                    'posts_per_page' => -1,
                                    'post_status' => ['publish', 'draft', 'private'],
                                    'orderby' => 'title',
                                    'order' => 'ASC',
                                ]);
                                foreach ($tablas as $tabla) {
                                    $selected = selected($values['tabla_precio_id'], $tabla->ID, false);
                                    echo '<option value="' . esc_attr($tabla->ID) . '" ' . $selected . '>' . esc_html($tabla->post_title) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description"><?php esc_html_e('Selecciona la tabla de precios que se mostrará en esta oferta.', 'flacso-oferta-academica'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h4><?php esc_html_e('Secciones HTML', 'flacso-oferta-academica'); ?></h4>
            <?php
            foreach (self::HTML_FIELDS as $field) :
                $label = ucwords(str_replace('_', ' ', str_replace('_html', '', $field)));
                $editor_id = "oferta_data_{$field}";
                $settings = [
                    'textarea_name' => "oferta_data[{$field}]",
                    'textarea_rows' => 4,
                    'media_buttons' => false,
                    'tinymce' => ['toolbar1' => 'bold,italic,link', 'toolbar2' => ''],
                    'quicktags' => true,
                ];
                ?>
                <p><strong><?php echo esc_html($label); ?></strong></p>
                <?php wp_editor($values[$field], $editor_id, $settings); ?>
            <?php endforeach; ?>

            <h4><?php esc_html_e('Personas y equipos', 'flacso-oferta-academica'); ?></h4>
            <p class="description"><?php esc_html_e('Escribe cada línea usando "etiqueta|descripcion opcional|doc_id1,doc_id2". El editor externo sigue siendo la mejor opción para textos largos.', 'flacso-oferta-academica'); ?></p>
            <?php foreach (self::PERSONNEL_GROUPS as $key => $label) : ?>
                <p><strong><?php echo esc_html($label); ?></strong></p>
                <textarea name="oferta_data[<?php echo esc_attr($key); ?>]" rows="4" class="large-text"><?php echo esc_textarea($values[$key]); ?></textarea>
            <?php endforeach; ?>

            <h4><?php esc_html_e('Menciones y orientaciones', 'flacso-oferta-academica'); ?></h4>
            <?php foreach (self::STRING_ARRAYS as $key) : ?>
                <p><strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?></strong></p>
                <textarea name="oferta_data[<?php echo esc_attr($key); ?>]" rows="3" class="large-text"><?php echo esc_textarea($values[$key]); ?></textarea>
                <p class="description"><?php esc_html_e('Una entrada por línea.', 'flacso-oferta-academica'); ?></p>
            <?php endforeach; ?>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var precisionSelect = document.getElementById('oferta_data_precision');
                var proximoInicioInput = document.getElementById('oferta_data_proximo_inicio');
                if (precisionSelect && proximoInicioInput) {
                    precisionSelect.addEventListener('change', function() {
                        var val = this.value;
                        if (val === 'day') {
                            proximoInicioInput.type = 'date';
                            proximoInicioInput.removeAttribute('min');
                            proximoInicioInput.removeAttribute('max');
                        } else if (val === 'month') {
                            proximoInicioInput.type = 'month';
                            proximoInicioInput.removeAttribute('min');
                            proximoInicioInput.removeAttribute('max');
                        } else {
                            proximoInicioInput.type = 'number';
                            proximoInicioInput.min = '2000';
                            proximoInicioInput.max = '2100';
                        }
                    });
                }
            });
        </script>
        <?php
    }

    public static function save_meta(int $post_id, \WP_Post $post): void {
        if (!isset($_POST['oferta_data_meta_nonce']) || !wp_verify_nonce($_POST['oferta_data_meta_nonce'], 'oferta_data_meta')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $data = $_POST['oferta_data'] ?? [];

        self::save_simple_field($post_id, 'abreviacion', $data['abreviacion'] ?? '');
        self::save_simple_field($post_id, 'correo', $data['correo'] ?? '');
        self::save_simple_field($post_id, 'duracion_meses', $data['duracion_meses'] ?? '');
        self::save_simple_field($post_id, 'proximo_inicio', $data['proximo_inicio'] ?? '');
        self::save_simple_field($post_id, 'cohorte', $data['cohorte'] ?? '');
        self::save_simple_field($post_id, 'calendario', $data['calendario'] ?? '');
        self::save_simple_field($post_id, 'malla_curricular', $data['malla_curricular'] ?? '');
        self::save_simple_field($post_id, 'proximo_inicio_precision', $data['proximo_inicio_precision'] ?? '');
        self::save_simple_field($post_id, 'inscripciones_abiertas', $data['inscripciones_abiertas'] ?? '', 'boolean');
        self::save_simple_field($post_id, 'visibilidad_carta', $data['inscripciones_abiertas'] ?? '', 'boolean');
        self::save_simple_field($post_id, 'tabla_precio_id', $data['tabla_precio_id'] ?? '', 'integer');
        foreach (self::HTML_FIELDS as $field) {
            self::save_simple_field($post_id, $field, $data[$field] ?? '', 'html');
        }

        foreach (self::STRING_ARRAYS as $key) {
            self::save_simple_field($post_id, $key, $data[$key] ?? '', 'string_array');
        }

        foreach (self::PERSONNEL_GROUPS as $key => $label) {
            $label_key = $label === 'Rol' ? 'rol' : 'nombre';
            $parsed = self::parse_personnel_input($data[$key] ?? '', $label_key);
            $sanitized = Oferta_Data_Schema::sanitize_personnel_groups_data($parsed, $label_key);
            update_post_meta($post_id, $key, $sanitized);
        }

        if (class_exists('Oferta_Data_Schema') && method_exists('Oferta_Data_Schema', 'sync_documentos_and_legacy_meta')) {
            Oferta_Data_Schema::sync_documentos_and_legacy_meta($post_id, 'legacy');
        }
    }

    private static function save_simple_field(int $post_id, string $key, $raw_value, string $type = 'string'): void {
        $value = '';
        switch ($key) {
            case 'duracion_meses':
                $value = Oferta_Data_Schema::sanitize_duration($raw_value);
                break;
            case 'proximo_inicio':
                $value = Oferta_Data_Schema::sanitize_proximo_inicio($raw_value);
                break;
            case 'calendario':
            case 'malla_curricular':
                $value = Oferta_Data_Schema::sanitize_url($raw_value);
                break;
            case 'proximo_inicio_precision':
                $value = Oferta_Data_Schema::sanitize_precision($raw_value);
                break;
            case 'abreviacion':
                $value = Oferta_Data_Schema::sanitize_abreviacion($raw_value);
                break;
            case 'correo':
                $value = Oferta_Data_Schema::sanitize_email($raw_value);
                break;
            case 'cohorte':
            case 'carta_cta_titulo':
                $value = sanitize_text_field($raw_value);
                break;
            case 'inscripciones_abiertas':
                $value = Oferta_Data_Schema::sanitize_boolean($raw_value);
                break;
            case 'tabla_precio_id':
                $value = Oferta_Data_Schema::sanitize_integer($raw_value);
                break;
            default:
                if ($type === 'html') {
                    $value = Oferta_Data_Schema::sanitize_html($raw_value);
                } elseif ($type === 'string_array') {
                    $value = Oferta_Data_Schema::sanitize_string_array(is_array($raw_value) ? $raw_value : explode("\n", (string) $raw_value));
                }
        }

        if (is_array($value)) {
            if (empty($value)) {
                delete_post_meta($post_id, $key);
                return;
            }
            update_post_meta($post_id, $key, $value);
            return;
        }

        $value = (string) $value;
        if ($value === '') {
            delete_post_meta($post_id, $key);
            return;
        }

        update_post_meta($post_id, $key, $value);
    }

    private static function parse_personnel_input(string $raw, string $label_key): array {
        $lines = preg_split('/\r?\n/', $raw);
        $rows = [];
        if (empty($lines)) {
            return [];
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $segments = array_map('trim', explode('|', $line));
            $label = array_shift($segments) ?: '';
            if ($label === '') {
                continue;
            }

            $ids = '';
            if (!empty($segments) && self::looks_like_docente_list((string) end($segments))) {
                $ids = (string) array_pop($segments);
            }

            $descripcion = Oferta_Data_Schema::sanitize_html(trim(implode(' | ', array_filter($segments, static function ($segment) {
                return $segment !== '';
            }))));

            $docentes = [];
            if ($ids !== '') {
                $parts = preg_split('/[,\s]+/', $ids);
                foreach ($parts as $id) {
                    $docente_id = intval($id);
                    if ($docente_id > 0) {
                        $docentes[] = $docente_id;
                    }
                }
            }
            $rows[] = [$label_key => $label, 'descripcion' => $descripcion, 'docentes' => $docentes];
        }
        return $rows;
    }

    private static function looks_like_docente_list(string $value): bool {
        return (bool) preg_match('/^\d+(?:\s*,\s*\d+)*$/', trim($value));
    }

    private static function get_saved_values(int $post_id): array {
        $values = [
            'abreviacion' => get_post_meta($post_id, 'abreviacion', true),
            'correo' => get_post_meta($post_id, 'correo', true),
            'duracion_meses' => get_post_meta($post_id, 'duracion_meses', true),
            'proximo_inicio' => get_post_meta($post_id, 'proximo_inicio', true),
            'cohorte' => get_post_meta($post_id, 'cohorte', true),
            'calendario' => get_post_meta($post_id, 'calendario', true),
            'malla_curricular' => get_post_meta($post_id, 'malla_curricular', true),
            'precision' => get_post_meta($post_id, 'proximo_inicio_precision', true),
            'inscripciones_abiertas' => get_post_meta($post_id, 'inscripciones_abiertas', true) ? '1' : '',
            'tabla_precio_id' => get_post_meta($post_id, 'tabla_precio_id', true),
            'carta_cta_titulo' => get_post_meta($post_id, 'carta_cta_titulo', true),
        ];

        foreach (self::HTML_FIELDS as $field) {
            $values[$field] = get_post_meta($post_id, $field, true);
        }

        foreach (self::STRING_ARRAYS as $key) {
            $values[$key] = implode("\n", (array) get_post_meta($post_id, $key, true));
        }

        foreach (self::PERSONNEL_GROUPS as $key => $label) {
            $stored = get_post_meta($post_id, $key, true);
            $values[$key] = self::format_personnel_for_display($stored, $label);
        }

        return array_map(fn($value) => $value ?? '', $values);
    }

    private static function format_personnel_for_display($stored, string $label): string {
        if (!is_array($stored)) {
            return '';
        }
        $lines = [];
        foreach ($stored as $item) {
            if (empty($item[$label === 'Rol' ? 'rol' : 'nombre'])) {
                continue;
            }
            $descripcion = isset($item['descripcion'])
                ? preg_replace('/\s*\r?\n\s*/', ' / ', (string) $item['descripcion'])
                : '';
            $docentes = array_map('intval', $item['docentes'] ?? []);
            $docentes = array_filter($docentes, fn($id) => $id > 0);
            $parts = [$item[$label === 'Rol' ? 'rol' : 'nombre']];
            if (!empty($descripcion)) {
                $parts[] = trim((string) $descripcion);
            }
            if (!empty($docentes)) {
                $parts[] = implode(', ', $docentes);
            }
            $lines[] = implode(' | ', $parts);
        }
        return implode("\n", $lines);
    }
}
