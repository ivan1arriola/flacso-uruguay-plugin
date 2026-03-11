<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Herramientas administrativas para gestionar los seminarios.
 */
if (class_exists('Flacso_Main_Page_Seminarios')) {
    // Evita redefinir la clase cuando convive con otros plugins FLACSO.
    return;
}

class Flacso_Main_Page_Seminarios {
    public static function init(): void {
        add_action('init', [__CLASS__, 'register_meta_fields'], 15);
        add_action('add_meta_boxes', [__CLASS__, 'register_meta_box']);
        add_action('save_post', [__CLASS__, 'save_post_meta'], 10, 2);

        add_filter('manage_post_posts_columns', [__CLASS__, 'add_admin_columns']);
        add_action('manage_post_posts_custom_column', [__CLASS__, 'render_admin_column'], 10, 2);
        add_filter('manage_seminario_posts_columns', [__CLASS__, 'add_admin_columns']);
        add_action('manage_seminario_posts_custom_column', [__CLASS__, 'render_admin_column'], 10, 2);

        add_action('admin_head', [__CLASS__, 'print_admin_styles']);
    }

    public static function register_meta_fields(): void {
        foreach (self::get_supported_post_types() as $post_type) {
            foreach (self::get_meta_fields() as $meta_key => $config) {
                register_post_meta($post_type, $meta_key, [
                    'type'              => $config['type'],
                    'description'       => $config['description'],
                    'single'            => true,
                    'show_in_rest'      => true,
                    'sanitize_callback' => $config['sanitize'] ?? [__CLASS__, 'sanitize_string_meta'],
                    'auth_callback'     => '__return_true',
                ]);
            }
        }
    }

    public static function register_meta_box(): void {
        foreach (self::get_supported_post_types() as $post_type) {
            add_meta_box(
                'seminario_detalles_completos',
                __('Detalles completos del seminario', 'flacso-main-page'),
                [__CLASS__, 'render_meta_box'],
                $post_type,
                'normal',
                'high'
            );
        }
    }

    public static function render_meta_box(WP_Post $post): void {
        if (!self::is_seminario_post($post)) {
            echo '<div class="notice notice-info"><p>' . esc_html__('Este metabox solo aparece para el tipo Seminario o entradas con la categoría Seminario.', 'flacso-main-page') . '</p></div>';
            return;
        }

        wp_nonce_field('seminario_meta_box_completo', 'seminario_meta_box_nonce');

        $mostrar_en_formulario = get_post_meta($post->ID, '_mostrar_en_formulario', true);
        ?>
        <div style="background:#e8f4fd;padding:15px;margin-bottom:20px;border-left:4px solid #0073aa;">
            <h3 style="margin-top:0;color:#0073aa;"><?php esc_html_e('Visibilidad en formulario público', 'flacso-main-page'); ?></h3>
            <label style="font-weight:bold;display:block;margin-bottom:10px;">
                <input type="checkbox" name="mostrar_en_formulario" value="1" <?php checked((bool) $mostrar_en_formulario); ?>>
                <?php esc_html_e('Mostrar este seminario en el formulario de consulta', 'flacso-main-page'); ?>
            </label>
            <p class="description" style="margin:5px 0 0 0;font-style:italic;">
                <?php esc_html_e('Al activarlo se incluirá en el selector del formulario público.', 'flacso-main-page'); ?>
            </p>
        </div>
        <?php

        $campos_basicos = [
            'fecha_inicio' => [
                'label'   => __('Fecha de inicio', 'flacso-main-page'),
                'type'    => 'date',
                'legacy'  => '_seminario_fecha_inicio',
                'compat'  => '_seminario_periodo_inicio',
            ],
            'fecha_fin' => [
                'label'   => __('Fecha de fin', 'flacso-main-page'),
                'type'    => 'date',
                'legacy'  => '_seminario_fecha_fin',
                'compat'  => '_seminario_periodo_fin',
            ],

            'mail_contacto' => [
                'label'  => __('Correo de contacto', 'flacso-main-page'),
                'type'   => 'email',
                'legacy' => '_seminario_mail_contacto',
            ],
        ];

        $campos_academicos = [
            'acreditacion_academica' => [
                'label'  => __('Acreditación académica', 'flacso-main-page'),
                'type'   => 'text',
                'legacy' => '_seminario_acreditacion',
            ],
            'modalidad_cursada' => [
                'label'  => __('Modalidad de cursada', 'flacso-main-page'),
                'type'   => 'text',
                'legacy' => '_seminario_modalidad',
            ],
            'cantidad_creditos' => [
                'label'  => __('Cantidad de créditos', 'flacso-main-page'),
                'type'   => 'number',
                'legacy' => '_seminario_creditos',
            ],
            'cantidad_horas' => [
                'label'  => __('Cantidad de horas', 'flacso-main-page'),
                'type'   => 'number',
                'legacy' => '_seminario_horas',
                'compat' => '_seminario_carga_horaria',
            ],
            'descripcion_horas' => [
                'label'  => __('Descripción de horas', 'flacso-main-page'),
                'type'   => 'text',
                'legacy' => '_seminario_descripcion_horas',
            ],
            'forma_aprobacion' => [
                'label'  => __('Forma de aprobación', 'flacso-main-page'),
                'type'   => 'text',
                'legacy' => '_seminario_aprobacion',
            ],
        ];

        echo '<h3>' . esc_html__('Información básica', 'flacso-main-page') . '</h3>';
        self::render_fields_group($post->ID, $campos_basicos);

        echo '<h3>' . esc_html__('Información académica', 'flacso-main-page') . '</h3>';
        self::render_fields_group($post->ID, $campos_academicos);

        $json_fields = [
            '_seminario_docentes'   => __('Docentes', 'flacso-main-page'),
            '_seminario_objetivos'  => __('Objetivos', 'flacso-main-page'),
            '_seminario_contenidos' => __('Contenidos', 'flacso-main-page'),
            '_seminario_encuentros' => __('Encuentros sincrónicos', 'flacso-main-page'),
        ];

        echo '<h3>' . esc_html__('Datos estructurados (solo lectura)', 'flacso-main-page') . '</h3>';
        foreach ($json_fields as $meta_key => $label) {
            $valor = get_post_meta($post->ID, $meta_key, true);
            if (!$valor) {
                continue;
            }

            $decoded = json_decode($valor, true);
            $display = $decoded ? print_r($decoded, true) : $valor;
            ?>
            <p>
                <strong><?php echo esc_html($label); ?>:</strong><br>
                <textarea readonly class="large-text" rows="3" style="width:100%;background:#f6f7f7;"><?php echo esc_textarea($display); ?></textarea>
            </p>
            <?php
        }
    }

    public static function save_post_meta(int $post_id, WP_Post $post): void {
        if (!self::is_seminario_post($post)) {
            return;
        }

        if (
            !isset($_POST['seminario_meta_box_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['seminario_meta_box_nonce'])), 'seminario_meta_box_completo') ||
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
            !current_user_can('edit_post', $post_id)
        ) {
            return;
        }

        if (isset($_POST['mostrar_en_formulario'])) {
            update_post_meta($post_id, '_mostrar_en_formulario', '1');
        } else {
            delete_post_meta($post_id, '_mostrar_en_formulario');
        }

        $campos = [
            'fecha_inicio',
            'fecha_fin',
            'mail_contacto',
            'acreditacion_academica',
            'modalidad_cursada',
            'cantidad_creditos',
            'cantidad_horas',
            'descripcion_horas',
            'forma_aprobacion',
        ];

        $mirror_map = [
            'fecha_inicio'        => ['_seminario_fecha_inicio', '_seminario_periodo_inicio'],
            'fecha_fin'           => ['_seminario_fecha_fin', '_seminario_periodo_fin'],
            'mail_contacto'       => ['_seminario_mail_contacto'],
            'acreditacion_academica' => [],
            'modalidad_cursada'   => ['_seminario_modalidad'],
            'cantidad_creditos'   => ['_seminario_creditos'],
            'cantidad_horas'      => ['_seminario_horas', '_seminario_carga_horaria'],
            'descripcion_horas'   => ['_seminario_descripcion_horas'],
            'forma_aprobacion'    => ['_seminario_aprobacion'],
        ];

        foreach ($campos as $campo) {
            if (!isset($_POST[$campo])) {
                continue;
            }

            $valor = wp_unslash($_POST[$campo]);
            if (in_array($campo, ['cantidad_creditos', 'cantidad_horas'], true)) {
                $valor = is_numeric($valor) ? $valor : '';
            } elseif ('mail_contacto' === $campo) {
                $valor = sanitize_email($valor);
            } else {
                $valor = sanitize_text_field($valor);
            }

            update_post_meta($post_id, $campo, $valor);

            foreach ($mirror_map[$campo] ?? [] as $extra_key) {
                update_post_meta($post_id, $extra_key, $valor);
            }
        }
    }

    public static function add_admin_columns(array $columns): array {
        $new_columns = [];
        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
            if ('title' === $key) {
                $new_columns['seminario_visible_formulario'] = __('En formulario', 'flacso-main-page');
                $new_columns['seminario_fecha_inicio'] = __('Fecha inicio', 'flacso-main-page');
                $new_columns['seminario_fecha_fin'] = __('Fecha fin', 'flacso-main-page');
            }
        }

        return $new_columns;
    }

    public static function render_admin_column(string $column, int $post_id): void {
        if (!self::is_seminario_post($post_id)) {
            return;
        }

        switch ($column) {
            case 'seminario_visible_formulario':
                $visible = get_post_meta($post_id, '_mostrar_en_formulario', true);
                echo $visible ? esc_html__('Sí', 'flacso-main-page') : esc_html__('No', 'flacso-main-page');
                break;
            case 'seminario_fecha_inicio':
                $valor = self::get_meta_value($post_id, 'periodo_inicio');
                echo $valor ? esc_html($valor) : '—';
                break;
            case 'seminario_fecha_fin':
                $valor = self::get_meta_value($post_id, 'periodo_fin');
                echo $valor ? esc_html($valor) : '—';
                break;
        }
    }

    public static function print_admin_styles(): void {
        ?>
        <style>
            #seminario_detalles_completos .inside h3 {
                background: #f6f7f7;
                padding: 10px;
                margin: 15px -12px 10px -12px;
                border-bottom: 1px solid #ccd0d4;
            }

            #seminario_detalles_completos .inside > div:first-child {
                margin-top: 0;
            }
        </style>
        <?php
    }

    private static function render_fields_group(int $post_id, array $fields): void {
        foreach ($fields as $key => $config) {
            $possible_keys = [$key];
            if (!empty($config['compat'])) {
                $compat_keys = is_array($config['compat']) ? $config['compat'] : [$config['compat']];
                $possible_keys = array_merge($possible_keys, $compat_keys);
            }
            if (!empty($config['legacy'])) {
                $possible_keys[] = $config['legacy'];
            }

            $valor = '';
            foreach ($possible_keys as $meta_key) {
                $meta_val = get_post_meta($post_id, $meta_key, true);
                if ($meta_val !== '' && $meta_val !== null) {
                    $valor = $meta_val;
                    break;
                }
            }

            printf(
                '<p><label><strong>%1$s:</strong></label><br><input type="%2$s" name="%3$s" value="%4$s" class="regular-text" style="width:100%%;"></p>',
                esc_html($config['label']),
                esc_attr($config['type']),
                esc_attr($key),
                esc_attr($valor)
            );
        }
    }

    private static function is_seminario_post($post): bool {
        $post_id = $post instanceof WP_Post ? $post->ID : (int) $post;
        if (!$post_id) {
            return false;
        }

        $post_type = get_post_type($post_id);
        if ('seminario' === $post_type) {
            return true;
        }

        if ('post' === $post_type) {
            return has_category(['seminario', 'seminarios'], $post_id);
        }

        return false;
    }

    public static function get_supported_post_types(): array {
        $types = [];
        if (post_type_exists('seminario')) {
            $types[] = 'seminario';
        }

        $types[] = 'post';

        return array_values(array_unique($types));
    }

    public static function get_meta_keys_for(string $field): array {
        $map = [
            'nombre'         => ['_seminario_nombre'],
            'periodo_inicio' => ['_seminario_periodo_inicio', '_seminario_fecha_inicio', 'fecha_inicio'],
            'periodo_fin'    => ['_seminario_periodo_fin', '_seminario_fecha_fin', 'fecha_fin'],
            'creditos'       => ['_seminario_creditos', 'cantidad_creditos'],
            'carga_horaria'  => ['_seminario_carga_horaria', '_seminario_horas', 'cantidad_horas'],
            'modalidad'      => ['_seminario_modalidad', 'modalidad_cursada'],
            'visibilidad'    => ['_mostrar_en_formulario'],
        ];

        return $map[$field] ?? [];
    }

    public static function get_meta_value(int $post_id, string $field, $default = ''): string {
        $keys = self::get_meta_keys_for($field);

        foreach ($keys as $meta_key) {
            $value = get_post_meta($post_id, $meta_key, true);
            if ($value !== '' && $value !== null) {
                return is_string($value) ? $value : (string) $value;
            }
        }

        if ('nombre' === $field) {
            $title = get_the_title($post_id);
            if ($title) {
                return (string) $title;
            }
        }

        return (string) $default;
    }

    public static function get_start_date(int $post_id): string {
        return self::normalize_date(self::get_meta_value($post_id, 'periodo_inicio'));
    }

    public static function get_end_date(int $post_id): string {
        return self::normalize_date(self::get_meta_value($post_id, 'periodo_fin'));
    }

    private static function normalize_date($value): string {
        if (empty($value)) {
            return '';
        }

        $timestamp = strtotime((string) $value);
        return $timestamp ? gmdate('Y-m-d', $timestamp) : '';
    }

    private static function get_meta_fields(): array {
        return [
            '_seminario_nombre'           => ['type' => 'string',  'description' => __('Nombre del seminario', 'flacso-main-page')],
            '_seminario_periodo_inicio'   => ['type' => 'string',  'description' => __('Fecha de inicio del seminario (YYYY-MM-DD)', 'flacso-main-page'), 'sanitize' => [__CLASS__, 'sanitize_string_meta']],
            '_seminario_periodo_fin'      => ['type' => 'string',  'description' => __('Fecha de fin del seminario (YYYY-MM-DD)', 'flacso-main-page'), 'sanitize' => [__CLASS__, 'sanitize_string_meta']],
            '_seminario_fecha_inicio'      => ['type' => 'string',  'description' => __('Fecha de inicio del seminario', 'flacso-main-page'), 'sanitize' => [__CLASS__, 'sanitize_string_meta']],
            '_seminario_fecha_fin'         => ['type' => 'string',  'description' => __('Fecha de fin del seminario', 'flacso-main-page')],
            '_seminario_mail_contacto'     => ['type' => 'string',  'description' => __('Correo de contacto del seminario', 'flacso-main-page'), 'sanitize' => 'sanitize_email'],
            '_seminario_acreditacion'      => ['type' => 'string',  'description' => __('Acreditación académica', 'flacso-main-page')],
            '_seminario_modalidad'         => ['type' => 'string',  'description' => __('Modalidad de cursada', 'flacso-main-page')],
            '_seminario_creditos'          => ['type' => 'number',  'description' => __('Cantidad de créditos', 'flacso-main-page'), 'sanitize' => [__CLASS__, 'sanitize_number_meta']],
            '_seminario_horas'             => ['type' => 'number',  'description' => __('Cantidad de horas', 'flacso-main-page'), 'sanitize' => [__CLASS__, 'sanitize_number_meta']],
            '_seminario_carga_horaria'     => ['type' => 'number',  'description' => __('Carga horaria total', 'flacso-main-page'), 'sanitize' => [__CLASS__, 'sanitize_number_meta']],
            '_seminario_descripcion_horas' => ['type' => 'string',  'description' => __('Descripción de horas', 'flacso-main-page')],
            '_seminario_aprobacion'        => ['type' => 'string',  'description' => __('Forma de aprobación', 'flacso-main-page')],
            '_seminario_docentes'          => ['type' => 'string',  'description' => __('Docentes asignados en formato JSON', 'flacso-main-page'), 'sanitize' => [__CLASS__, 'sanitize_json_meta']],
            '_seminario_objetivos'         => ['type' => 'string',  'description' => __('Objetivos del seminario en JSON', 'flacso-main-page'), 'sanitize' => [__CLASS__, 'sanitize_json_meta']],
            '_seminario_contenidos'        => ['type' => 'string',  'description' => __('Contenidos estructurados en JSON', 'flacso-main-page'), 'sanitize' => [__CLASS__, 'sanitize_json_meta']],
            '_seminario_encuentros'        => ['type' => 'string',  'description' => __('Encuentros sincrónicos en JSON', 'flacso-main-page'), 'sanitize' => [__CLASS__, 'sanitize_json_meta']],
            '_seminario_tiene_encuentros'  => ['type' => 'boolean', 'description' => __('Indicador de encuentros sincrónicos', 'flacso-main-page'), 'sanitize' => [__CLASS__, 'sanitize_boolean_meta']],
            'fecha_inicio'                 => ['type' => 'string',  'description' => __('Fecha inicio visible', 'flacso-main-page')],
            'fecha_fin'                    => ['type' => 'string',  'description' => __('Fecha fin visible', 'flacso-main-page')],
            'mail_contacto'                => ['type' => 'string',  'description' => __('Correo electrónico de contacto', 'flacso-main-page'), 'sanitize' => 'sanitize_email'],
            'acreditacion_academica'       => ['type' => 'string',  'description' => __('Acreditación académica visible', 'flacso-main-page')],
            'modalidad_cursada'            => ['type' => 'string',  'description' => __('Modalidad de cursada visible', 'flacso-main-page')],
            'cantidad_creditos'            => ['type' => 'number',  'description' => __('Cantidad de créditos visible', 'flacso-main-page'), 'sanitize' => [__CLASS__, 'sanitize_number_meta']],
            'cantidad_horas'               => ['type' => 'number',  'description' => __('Cantidad de horas visible', 'flacso-main-page'), 'sanitize' => [__CLASS__, 'sanitize_number_meta']],
            'descripcion_horas'            => ['type' => 'string',  'description' => __('Descripción de horas visible', 'flacso-main-page')],
            'forma_aprobacion'             => ['type' => 'string',  'description' => __('Forma de aprobación visible', 'flacso-main-page')],
            '_mostrar_en_formulario'       => ['type' => 'boolean', 'description' => __('Mostrar en formulario público', 'flacso-main-page'), 'sanitize' => [__CLASS__, 'sanitize_boolean_meta']],
        ];
    }

    public static function sanitize_string_meta($value) {
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        return is_string($value) ? sanitize_text_field($value) : '';
    }

    public static function sanitize_number_meta($value) {
        return is_numeric($value) ? 0 + $value : null;
    }

    public static function sanitize_boolean_meta($value) {
        return rest_sanitize_boolean($value);
    }

    public static function sanitize_json_meta($value) {
        if (is_array($value)) {
            $value = wp_json_encode($value);
        }
        return is_string($value) ? wp_kses_post($value) : '';
    }
}

