<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Editor wp-admin de los datos académicos estables de Oferta Académica. */
final class FLACSO_Oferta_Admin_Fields {
    private const NONCE_ACTION = 'flacso_save_oferta_academica';
    private const NONCE_NAME = 'flacso_oferta_academica_nonce';

    private static bool $initialized = false;

    public static function init(): void {
        if (self::$initialized || !is_admin()) {
            return;
        }
        self::$initialized = true;
        add_action('add_meta_boxes', [self::class, 'add_meta_box']);
        add_action('save_post_' . FLACSO_Oferta_Academica::POST_TYPE, [self::class, 'save'], 10, 2);
        add_action('admin_head-post.php', [self::class, 'render_styles']);
        add_action('admin_head-post-new.php', [self::class, 'render_styles']);
    }

    public static function add_meta_box(): void {
        add_meta_box(
            'flacso_oferta_academica_fields',
            __('Información académica de la Oferta', 'flacso-uruguay'),
            [self::class, 'render'],
            FLACSO_Oferta_Academica::POST_TYPE,
            'normal',
            'high'
        );
    }

    private static function meta(int $post_id, string $key, $default = '') {
        return metadata_exists('post', $post_id, $key) ? get_post_meta($post_id, $key, true) : $default;
    }

    public static function render(WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $program_id = absint(self::meta($post->ID, FLACSO_Oferta_Academica::META_PROGRAM_ID, 0));
        $programs = get_posts([
            'post_type' => 'programa-academico',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $text_fields = [
            'abreviacion' => __('Abreviación', 'flacso-uruguay'),
            'correo' => __('Correo de contacto', 'flacso-uruguay'),
            'carga_horaria_descripcion' => __('Descripción de carga horaria', 'flacso-uruguay'),
            'malla_curricular' => __('URL de malla curricular', 'flacso-uruguay'),
        ];
        $number_fields = [
            'duracion_meses' => __('Duración (meses)', 'flacso-uruguay'),
            'carga_horaria' => __('Carga horaria', 'flacso-uruguay'),
            'creditos' => __('Créditos', 'flacso-uruguay'),
        ];
        $rich_fields = [
            'presentacion' => __('Presentación', 'flacso-uruguay'),
            'objetivo_general' => __('Objetivo general', 'flacso-uruguay'),
            'forma_aprobacion' => __('Forma de aprobación', 'flacso-uruguay'),
            'duracion_html' => __('Descripción de duración', 'flacso-uruguay'),
            'acreditacion' => __('Acreditación', 'flacso-uruguay'),
            'perfil_ingreso_html' => __('Perfil de ingreso', 'flacso-uruguay'),
            'requisitos_ingreso_html' => __('Requisitos de ingreso', 'flacso-uruguay'),
            'perfil_egreso_html' => __('Perfil de egreso', 'flacso-uruguay'),
            'requisitos_egreso_html' => __('Requisitos de egreso', 'flacso-uruguay'),
            'titulos_certificaciones_html' => __('Títulos y certificaciones', 'flacso-uruguay'),
            'financiacion_html' => __('Financiación', 'flacso-uruguay'),
            'malla_curricular_html' => __('Descripción de malla curricular', 'flacso-uruguay'),
        ];
        $list_fields = [
            'objetivos_especificos' => __('Objetivos específicos', 'flacso-uruguay'),
            'menciones' => __('Menciones', 'flacso-uruguay'),
            'orientaciones' => __('Orientaciones', 'flacso-uruguay'),
            'titulos_intermedios' => __('Títulos intermedios', 'flacso-uruguay'),
        ];
        $boolean_fields = [
            'reconocido_mec' => __('Reconocido por MEC', 'flacso-uruguay'),
            'reconocimiento_internacional' => __('Reconocimiento internacional', 'flacso-uruguay'),
            'convenio_iin_oea' => __('Convenio IIN/OEA', 'flacso-uruguay'),
            'mostrar_costos_envio' => __('Mostrar costos de envío', 'flacso-uruguay'),
            'mostrar_expedicion_titulo' => __('Mostrar expedición de título', 'flacso-uruguay'),
        ];
        ?>
        <div class="flacso-oferta-fields">
            <section class="flacso-oferta-section">
                <h3><?php esc_html_e('Datos generales', 'flacso-uruguay'); ?></h3>
                <p class="description"><?php esc_html_e('Información estable de la propuesta. Las fechas, estado académico, precios y preinscripción se gestionan en sus Cohortes.', 'flacso-uruguay'); ?></p>
                <div class="flacso-oferta-grid">
                    <label class="flacso-oferta-field flacso-oferta-field--full">
                        <span><?php esc_html_e('Programa académico', 'flacso-uruguay'); ?></span>
                        <select name="flacso_oferta[programa_academico_id]">
                            <option value=""><?php esc_html_e('— Sin asignar —', 'flacso-uruguay'); ?></option>
                            <?php foreach ($programs as $program) : ?>
                                <option value="<?php echo esc_attr((string) $program->ID); ?>" <?php selected($program_id, $program->ID); ?>><?php echo esc_html(get_the_title($program)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php foreach ($text_fields as $key => $label) : ?>
                        <label class="flacso-oferta-field <?php echo $key === 'malla_curricular' ? 'flacso-oferta-field--full' : ''; ?>">
                            <span><?php echo esc_html($label); ?></span>
                            <input type="<?php echo $key === 'correo' ? 'email' : ($key === 'malla_curricular' ? 'url' : 'text'); ?>" name="flacso_oferta[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) self::meta($post->ID, $key)); ?>">
                        </label>
                    <?php endforeach; ?>
                    <?php foreach ($number_fields as $key => $label) : ?>
                        <label class="flacso-oferta-field">
                            <span><?php echo esc_html($label); ?></span>
                            <input type="number" min="0" step="0.1" name="flacso_oferta[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) self::meta($post->ID, $key)); ?>">
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="flacso-oferta-section">
                <h3><?php esc_html_e('Presentación y contenido académico', 'flacso-uruguay'); ?></h3>
                <?php foreach ($rich_fields as $key => $label) : ?>
                    <div class="flacso-oferta-editor">
                        <label for="flacso_oferta_<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($label); ?></strong></label>
                        <?php wp_editor((string) self::meta($post->ID, $key), 'flacso_oferta_' . $key, [
                            'textarea_name' => 'flacso_oferta[' . $key . ']',
                            'textarea_rows' => in_array($key, ['presentacion', 'malla_curricular_html'], true) ? 8 : 5,
                            'media_buttons' => false,
                            'teeny' => true,
                        ]); ?>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="flacso-oferta-section">
                <h3><?php esc_html_e('Listas académicas', 'flacso-uruguay'); ?></h3>
                <p class="description"><?php esc_html_e('Un elemento por línea.', 'flacso-uruguay'); ?></p>
                <div class="flacso-oferta-grid">
                    <?php foreach ($list_fields as $key => $label) :
                        $value = self::meta($post->ID, $key, []);
                        $value = is_array($value) ? $value : [];
                        $lines = array_map(static function ($item): string {
                            return is_array($item) ? wp_strip_all_tags((string) ($item['contenido'] ?? $item['titulo'] ?? '')) : wp_strip_all_tags((string) $item);
                        }, $value);
                    ?>
                        <label class="flacso-oferta-field flacso-oferta-field--full">
                            <span><?php echo esc_html($label); ?></span>
                            <textarea rows="5" name="flacso_oferta_lists[<?php echo esc_attr($key); ?>]"><?php echo esc_textarea(implode("\n", array_filter($lines))); ?></textarea>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="flacso-oferta-section">
                <h3><?php esc_html_e('Opciones de visualización y reconocimiento', 'flacso-uruguay'); ?></h3>
                <div class="flacso-oferta-checks">
                    <?php foreach ($boolean_fields as $key => $label) : ?>
                        <label><input type="checkbox" name="flacso_oferta[<?php echo esc_attr($key); ?>]" value="1" <?php checked(rest_sanitize_boolean(self::meta($post->ID, $key, false))); ?>> <?php echo esc_html($label); ?></label>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
        <?php
    }

    public static function save(int $post_id, WP_Post $post): void {
        if (
            !isset($_POST[self::NONCE_NAME])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || wp_is_post_revision($post_id)
            || !current_user_can('edit_post', $post_id)
        ) {
            return;
        }

        $data = isset($_POST['flacso_oferta']) && is_array($_POST['flacso_oferta']) ? wp_unslash($_POST['flacso_oferta']) : [];
        $lists = isset($_POST['flacso_oferta_lists']) && is_array($_POST['flacso_oferta_lists']) ? wp_unslash($_POST['flacso_oferta_lists']) : [];

        $sanitizers = [
            FLACSO_Oferta_Academica::META_PROGRAM_ID => 'absint',
            'abreviacion' => 'sanitize_text_field',
            'correo' => 'sanitize_email',
            'presentacion' => 'wp_kses_post',
            'objetivo_general' => 'wp_kses_post',
            'forma_aprobacion' => 'wp_kses_post',
            'duracion_meses' => [FLACSO_Oferta_Academica::class, 'sanitize_number'],
            'duracion_html' => 'wp_kses_post',
            'carga_horaria' => [FLACSO_Oferta_Academica::class, 'sanitize_number'],
            'carga_horaria_descripcion' => 'sanitize_text_field',
            'creditos' => [FLACSO_Oferta_Academica::class, 'sanitize_number'],
            'acreditacion' => 'wp_kses_post',
            'perfil_ingreso_html' => 'wp_kses_post',
            'requisitos_ingreso_html' => 'wp_kses_post',
            'perfil_egreso_html' => 'wp_kses_post',
            'requisitos_egreso_html' => 'wp_kses_post',
            'titulos_certificaciones_html' => 'wp_kses_post',
            'financiacion_html' => 'wp_kses_post',
            'malla_curricular' => 'esc_url_raw',
            'malla_curricular_html' => 'wp_kses_post',
        ];

        foreach ($sanitizers as $key => $sanitizer) {
            $raw = $data[$key] ?? '';
            $value = is_array($sanitizer) ? call_user_func($sanitizer, $raw) : $sanitizer($raw);
            if ($value === '' || ($key === FLACSO_Oferta_Academica::META_PROGRAM_ID && !$value)) {
                delete_post_meta($post_id, $key);
            } else {
                update_post_meta($post_id, $key, $value);
            }
        }

        foreach (['objetivos_especificos', 'menciones', 'orientaciones', 'titulos_intermedios'] as $key) {
            $raw = (string) ($lists[$key] ?? '');
            $items = preg_split('/\R/u', $raw) ?: [];
            $items = array_values(array_filter(array_map('sanitize_text_field', $items), static fn(string $item): bool => $item !== ''));
            if ($key === 'objetivos_especificos') {
                $items = FLACSO_Oferta_Academica::sanitize_html_list($items);
            } else {
                $items = FLACSO_Oferta_Academica::sanitize_text_list($items);
            }
            $items ? update_post_meta($post_id, $key, $items) : delete_post_meta($post_id, $key);
        }

        foreach (['reconocido_mec', 'reconocimiento_internacional', 'convenio_iin_oea', 'mostrar_costos_envio', 'mostrar_expedicion_titulo'] as $key) {
            update_post_meta($post_id, $key, !empty($data[$key]));
        }
    }

    public static function render_styles(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== FLACSO_Oferta_Academica::POST_TYPE) {
            return;
        }
        ?>
        <style>
            .flacso-oferta-fields { display:grid; gap:18px; }
            .flacso-oferta-section { border:1px solid #dcdcde; border-radius:8px; padding:18px; background:#fff; }
            .flacso-oferta-section h3 { margin:0 0 8px; }
            .flacso-oferta-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-top:14px; }
            .flacso-oferta-field { display:grid; gap:6px; }
            .flacso-oferta-field > span { font-weight:600; }
            .flacso-oferta-field input,.flacso-oferta-field select,.flacso-oferta-field textarea { width:100%; }
            .flacso-oferta-field--full { grid-column:1/-1; }
            .flacso-oferta-editor { margin-top:16px; }
            .flacso-oferta-editor > label { display:block; margin-bottom:6px; }
            .flacso-oferta-checks { display:flex; flex-wrap:wrap; gap:10px 20px; margin-top:12px; }
            @media(max-width:782px){ .flacso-oferta-grid{grid-template-columns:1fr;} .flacso-oferta-field--full{grid-column:auto;} }
        </style>
        <?php
    }
}
