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
        add_action('admin_footer-post.php', [self::class, 'render_scripts']);
        add_action('admin_footer-post-new.php', [self::class, 'render_scripts']);
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
            'post_type' => FLACSO_Programa_Academico::POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $sections = [
            'general' => [FLACSO_Oferta_Academica::META_PROGRAM_ID, 'abreviacion', 'correo'],
            'presentation' => ['presentacion', 'objetivo_general', 'objetivos_especificos'],
            'course' => ['duracion_meses', 'duracion_html', 'carga_horaria', 'carga_horaria_descripcion', 'creditos', 'forma_aprobacion'],
            'profiles' => ['perfil_ingreso_html', 'requisitos_ingreso_html', 'perfil_egreso_html', 'requisitos_egreso_html'],
            'certification' => ['titulos_certificaciones_html', 'acreditacion', 'financiacion_html'],
            'curriculum' => ['malla_curricular', 'malla_curricular_html', 'menciones', 'orientaciones', 'titulos_intermedios'],
        ];
        [$completed, $total] = self::completion($post->ID, array_merge(...array_values($sections)));

        $type_terms = wp_get_post_terms($post->ID, FLACSO_Oferta_Academica::TYPE_TAXONOMY, ['fields' => 'names']);
        $type_label = !is_wp_error($type_terms) && !empty($type_terms) ? (string) $type_terms[0] : __('Sin tipo', 'flacso-uruguay');
        $cohort_ids = get_posts([
            'post_type' => FLACSO_Cohorte::POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_key' => FLACSO_Cohorte::META_PARENT_ID,
            'meta_value' => $post->ID,
        ]);
        ?>
        <div class="flacso-oferta-fields">
            <header class="flacso-oferta-workspace">
                <div>
                    <p class="flacso-oferta-eyebrow"><?php esc_html_e('Contenido estable de la propuesta', 'flacso-uruguay'); ?></p>
                    <h3><?php esc_html_e('Editá la oferta por secciones', 'flacso-uruguay'); ?></h3>
                    <p><?php esc_html_e('Acá se administra lo que identifica y describe la propuesta. Las fechas, el estado, los precios y la preinscripción pertenecen a cada cohorte.', 'flacso-uruguay'); ?></p>
                </div>
                <div class="flacso-oferta-workspace__aside">
                    <div class="flacso-oferta-pills" aria-label="<?php esc_attr_e('Resumen de la oferta', 'flacso-uruguay'); ?>">
                        <span class="flacso-oferta-pill"><span class="dashicons dashicons-category" aria-hidden="true"></span><?php echo esc_html($type_label); ?></span>
                        <span class="flacso-oferta-pill"><span class="dashicons dashicons-groups" aria-hidden="true"></span><?php echo esc_html(sprintf(_n('%d cohorte', '%d cohortes', count($cohort_ids), 'flacso-uruguay'), count($cohort_ids))); ?></span>
                        <span class="flacso-oferta-pill flacso-oferta-pill--progress"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php echo esc_html(sprintf(__('%1$d de %2$d campos con contenido', 'flacso-uruguay'), $completed, $total)); ?></span>
                    </div>
                    <div class="flacso-oferta-toolbar" role="group" aria-label="<?php esc_attr_e('Controles de secciones', 'flacso-uruguay'); ?>">
                        <button type="button" class="button button-small" data-flacso-sections="open"><?php esc_html_e('Abrir todas', 'flacso-uruguay'); ?></button>
                        <button type="button" class="button button-small" data-flacso-sections="close"><?php esc_html_e('Cerrar todas', 'flacso-uruguay'); ?></button>
                    </div>
                </div>
            </header>

            <?php self::section_start($post->ID, 'datos-generales', __('Datos generales', 'flacso-uruguay'), __('Vínculo institucional y datos breves que se reutilizan en el sitio y las comunicaciones.', 'flacso-uruguay'), $sections['general'], 'dashicons-admin-home', true); ?>
            <div class="flacso-oferta-grid">
                <label class="flacso-oferta-field flacso-oferta-field--full">
                    <span><?php esc_html_e('Programa académico', 'flacso-uruguay'); ?></span>
                    <select name="flacso_oferta[programa_academico_id]">
                        <option value=""><?php esc_html_e('— Sin asignar —', 'flacso-uruguay'); ?></option>
                        <?php foreach ($programs as $program) : ?>
                            <option value="<?php echo esc_attr((string) $program->ID); ?>" <?php selected($program_id, $program->ID); ?>><?php echo esc_html(get_the_title($program)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small><?php esc_html_e('Agrupa ofertas que pertenecen a una misma línea o programa institucional.', 'flacso-uruguay'); ?></small>
                </label>
                <?php self::text_field($post->ID, 'abreviacion', __('Abreviación', 'flacso-uruguay')); ?>
                <?php self::text_field($post->ID, 'correo', __('Correo de contacto', 'flacso-uruguay'), 'email'); ?>
            </div>
            <?php self::section_end(); ?>

            <?php self::section_start($post->ID, 'presentacion-objetivos', __('Presentación y objetivos', 'flacso-uruguay'), __('Explica de qué trata la propuesta, por qué es relevante y qué busca lograr.', 'flacso-uruguay'), $sections['presentation'], 'dashicons-text-page'); ?>
            <?php self::rich_editor($post->ID, 'presentacion', __('Presentación', 'flacso-uruguay'), __('Texto introductorio que se muestra al comienzo de la página pública.', 'flacso-uruguay'), 8); ?>
            <?php self::rich_editor($post->ID, 'objetivo_general', __('Objetivo general', 'flacso-uruguay')); ?>
            <?php self::list_field($post->ID, 'objetivos_especificos', __('Objetivos específicos', 'flacso-uruguay'), __('Escribí un objetivo por línea.', 'flacso-uruguay'), true); ?>
            <?php self::section_end(); ?>

            <?php self::section_start($post->ID, 'cursado-aprobacion', __('Cursado y aprobación', 'flacso-uruguay'), __('Duración, dedicación, créditos y condiciones académicas de aprobación.', 'flacso-uruguay'), $sections['course'], 'dashicons-clock'); ?>
            <div class="flacso-oferta-grid flacso-oferta-grid--three">
                <?php self::text_field($post->ID, 'duracion_meses', __('Duración (meses)', 'flacso-uruguay'), 'number'); ?>
                <?php self::text_field($post->ID, 'carga_horaria', __('Carga horaria', 'flacso-uruguay'), 'number'); ?>
                <?php self::text_field($post->ID, 'creditos', __('Créditos', 'flacso-uruguay'), 'number'); ?>
                <?php self::text_field($post->ID, 'carga_horaria_descripcion', __('Descripción breve de carga horaria', 'flacso-uruguay'), 'text', true); ?>
            </div>
            <?php self::rich_editor($post->ID, 'duracion_html', __('Descripción de duración', 'flacso-uruguay'), __('Usala cuando meses, semanas, encuentros u horas necesiten una explicación más detallada.', 'flacso-uruguay')); ?>
            <?php self::rich_editor($post->ID, 'forma_aprobacion', __('Forma de aprobación', 'flacso-uruguay')); ?>
            <?php self::section_end(); ?>

            <?php self::section_start($post->ID, 'perfiles-requisitos', __('Perfiles y requisitos', 'flacso-uruguay'), __('Diferencia claramente a quién está dirigida la propuesta y qué debe cumplir para ingresar y egresar.', 'flacso-uruguay'), $sections['profiles'], 'dashicons-id-alt'); ?>
            <div class="flacso-oferta-editor-grid">
                <?php self::rich_editor($post->ID, 'perfil_ingreso_html', __('Perfil de ingreso', 'flacso-uruguay')); ?>
                <?php self::rich_editor($post->ID, 'requisitos_ingreso_html', __('Requisitos de ingreso', 'flacso-uruguay')); ?>
                <?php self::rich_editor($post->ID, 'perfil_egreso_html', __('Perfil de egreso', 'flacso-uruguay')); ?>
                <?php self::rich_editor($post->ID, 'requisitos_egreso_html', __('Requisitos de egreso', 'flacso-uruguay')); ?>
            </div>
            <?php self::section_end(); ?>

            <?php self::section_start($post->ID, 'titulacion-financiacion', __('Titulación, acreditación y financiación', 'flacso-uruguay'), __('Resultados formales de la formación y condiciones institucionales asociadas.', 'flacso-uruguay'), $sections['certification'], 'dashicons-awards'); ?>
            <?php self::rich_editor($post->ID, 'titulos_certificaciones_html', __('Títulos y certificaciones', 'flacso-uruguay')); ?>
            <?php self::rich_editor($post->ID, 'acreditacion', __('Acreditación', 'flacso-uruguay')); ?>
            <?php self::rich_editor($post->ID, 'financiacion_html', __('Financiación', 'flacso-uruguay')); ?>
            <?php self::section_end(); ?>

            <?php self::section_start($post->ID, 'plan-estudios', __('Plan de estudios', 'flacso-uruguay'), __('Malla curricular y variantes académicas de la propuesta.', 'flacso-uruguay'), $sections['curriculum'], 'dashicons-welcome-learn-more'); ?>
            <div class="flacso-oferta-grid">
                <?php self::text_field($post->ID, 'malla_curricular', __('URL de malla curricular', 'flacso-uruguay'), 'url', true, __('Admite un PDF o un documento público accesible por enlace.', 'flacso-uruguay')); ?>
            </div>
            <?php self::rich_editor($post->ID, 'malla_curricular_html', __('Descripción de malla curricular', 'flacso-uruguay'), __('Se utiliza como contenido complementario o alternativa al documento enlazado.', 'flacso-uruguay'), 8); ?>
            <div class="flacso-oferta-grid">
                <?php self::list_field($post->ID, 'menciones', __('Menciones', 'flacso-uruguay')); ?>
                <?php self::list_field($post->ID, 'orientaciones', __('Orientaciones', 'flacso-uruguay')); ?>
                <?php self::list_field($post->ID, 'titulos_intermedios', __('Títulos intermedios', 'flacso-uruguay'), '', true); ?>
            </div>
            <?php self::section_end(); ?>

            <details class="flacso-oferta-section">
                <summary class="flacso-oferta-section__summary">
                    <span class="flacso-oferta-section__icon dashicons dashicons-visibility" aria-hidden="true"></span>
                    <span class="flacso-oferta-section__copy"><strong><?php esc_html_e('Reconocimientos y visualización', 'flacso-uruguay'); ?></strong><small><?php esc_html_e('Indicadores públicos y opciones específicas de presentación.', 'flacso-uruguay'); ?></small></span>
                </summary>
                <div class="flacso-oferta-section__body">
                    <div class="flacso-oferta-checks">
                        <?php foreach ([
                            'reconocido_mec' => __('Reconocido por MEC', 'flacso-uruguay'),
                            'reconocimiento_internacional' => __('Reconocimiento internacional', 'flacso-uruguay'),
                            'convenio_iin_oea' => __('Convenio IIN/OEA', 'flacso-uruguay'),
                            'mostrar_costos_envio' => __('Mostrar costos de envío', 'flacso-uruguay'),
                            'mostrar_expedicion_titulo' => __('Mostrar expedición de título', 'flacso-uruguay'),
                        ] as $key => $label) : ?>
                            <label><input type="checkbox" name="flacso_oferta[<?php echo esc_attr($key); ?>]" value="1" <?php checked(rest_sanitize_boolean(self::meta($post->ID, $key, false))); ?>><span><?php echo esc_html($label); ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
        </div>
        <?php
    }

    /** @return array{0:int,1:int} */
    private static function completion(int $post_id, array $keys): array {
        $completed = 0;
        foreach ($keys as $key) {
            $value = self::meta($post_id, (string) $key, '');
            $has_value = $key === FLACSO_Oferta_Academica::META_PROGRAM_ID
                ? absint($value) > 0
                : (is_array($value) ? !empty($value) : trim(wp_strip_all_tags((string) $value)) !== '');
            if ($has_value) {
                $completed++;
            }
        }
        return [$completed, count($keys)];
    }

    private static function section_start(int $post_id, string $id, string $title, string $description, array $keys, string $icon, bool $open = false): void {
        [$completed, $total] = self::completion($post_id, $keys);
        ?>
        <details class="flacso-oferta-section" id="flacso-oferta-<?php echo esc_attr($id); ?>" <?php echo $open ? 'open' : ''; ?>>
            <summary class="flacso-oferta-section__summary">
                <span class="flacso-oferta-section__icon dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                <span class="flacso-oferta-section__copy"><strong><?php echo esc_html($title); ?></strong><small><?php echo esc_html($description); ?></small></span>
                <span class="flacso-oferta-section__status <?php echo $completed === $total ? 'is-complete' : ''; ?>"><?php echo esc_html(sprintf(__('%1$d/%2$d con contenido', 'flacso-uruguay'), $completed, $total)); ?></span>
            </summary>
            <div class="flacso-oferta-section__body">
        <?php
    }

    private static function section_end(): void {
        echo '</div></details>';
    }

    private static function text_field(int $post_id, string $key, string $label, string $type = 'text', bool $full = false, string $help = ''): void {
        ?>
        <label class="flacso-oferta-field <?php echo $full ? 'flacso-oferta-field--full' : ''; ?>">
            <span><?php echo esc_html($label); ?></span>
            <input type="<?php echo esc_attr($type); ?>"<?php echo $type === 'number' ? ' min="0" step="0.1"' : ''; ?> name="flacso_oferta[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) self::meta($post_id, $key)); ?>">
            <?php if ($help !== '') : ?><small><?php echo esc_html($help); ?></small><?php endif; ?>
        </label>
        <?php
    }

    private static function rich_editor(int $post_id, string $key, string $label, string $help = '', int $rows = 5): void {
        ?>
        <div class="flacso-oferta-editor">
            <label for="flacso_oferta_<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($label); ?></strong></label>
            <?php if ($help !== '') : ?><p class="description"><?php echo esc_html($help); ?></p><?php endif; ?>
            <?php wp_editor((string) self::meta($post_id, $key), 'flacso_oferta_' . $key, [
                'textarea_name' => 'flacso_oferta[' . $key . ']',
                'textarea_rows' => $rows,
                'media_buttons' => false,
                'teeny' => true,
            ]); ?>
        </div>
        <?php
    }

    private static function list_field(int $post_id, string $key, string $label, string $help = '', bool $full = false): void {
        $value = self::meta($post_id, $key, []);
        $value = is_array($value) ? $value : [];
        $lines = array_map(static function ($item): string {
            return is_array($item) ? wp_strip_all_tags((string) ($item['contenido'] ?? $item['titulo'] ?? '')) : wp_strip_all_tags((string) $item);
        }, $value);
        ?>
        <label class="flacso-oferta-field <?php echo $full ? 'flacso-oferta-field--full' : ''; ?>">
            <span><?php echo esc_html($label); ?></span>
            <?php if ($help !== '') : ?><small><?php echo esc_html($help); ?></small><?php endif; ?>
            <textarea rows="5" name="flacso_oferta_lists[<?php echo esc_attr($key); ?>]"><?php echo esc_textarea(implode("\n", array_filter($lines))); ?></textarea>
            <?php if ($help === '') : ?><small><?php esc_html_e('Un elemento por línea.', 'flacso-uruguay'); ?></small><?php endif; ?>
        </label>
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
            #flacso_oferta_academica_fields .inside { padding:0; margin:0; background:#f6f7f7; }
            .flacso-oferta-fields { display:grid; gap:14px; padding:18px; color:#1d2327; }
            .flacso-oferta-workspace { display:flex; justify-content:space-between; gap:24px; padding:22px; border-radius:10px; color:#fff; background:linear-gradient(135deg,#172554 0%,#1d4ed8 100%); box-shadow:0 8px 24px rgba(23,37,84,.14); }
            .flacso-oferta-workspace h3 { margin:2px 0 7px; color:#fff; font-size:20px; }
            .flacso-oferta-workspace p { max-width:720px; margin:0; color:#dbeafe; font-size:13px; line-height:1.5; }
            .flacso-oferta-eyebrow { color:#bfdbfe!important; font-size:11px!important; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
            .flacso-oferta-workspace__aside { display:flex; min-width:260px; flex-direction:column; align-items:flex-end; justify-content:space-between; gap:14px; }
            .flacso-oferta-pills { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:7px; }
            .flacso-oferta-pill { display:inline-flex; align-items:center; gap:5px; padding:5px 9px; border:1px solid rgba(255,255,255,.2); border-radius:999px; color:#eff6ff; background:rgba(255,255,255,.1); font-size:12px; font-weight:600; white-space:nowrap; }
            .flacso-oferta-pill .dashicons { width:15px; height:15px; font-size:15px; }
            .flacso-oferta-pill--progress { background:rgba(22,163,74,.25); }
            .flacso-oferta-toolbar { display:flex; gap:7px; }
            .flacso-oferta-toolbar .button { border-color:rgba(255,255,255,.45); color:#fff; background:rgba(255,255,255,.08); }
            .flacso-oferta-toolbar .button:hover,.flacso-oferta-toolbar .button:focus { border-color:#fff; color:#172554; background:#fff; }
            .flacso-oferta-section { border:1px solid #dcdcde; border-radius:9px; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.03); overflow:hidden; }
            .flacso-oferta-section[open] { border-color:#b8c2cc; box-shadow:0 3px 12px rgba(0,0,0,.055); }
            .flacso-oferta-section__summary { display:flex; align-items:center; gap:12px; padding:16px 18px; cursor:pointer; list-style:none; user-select:none; }
            .flacso-oferta-section__summary::-webkit-details-marker { display:none; }
            .flacso-oferta-section__summary::after { content:"\f347"; width:20px; height:20px; margin-left:2px; font:normal 20px/1 dashicons; color:#646970; transition:transform .16s ease; }
            .flacso-oferta-section[open] > .flacso-oferta-section__summary::after { transform:rotate(180deg); }
            .flacso-oferta-section__summary:hover { background:#f8fafc; }
            .flacso-oferta-section__summary:focus-visible { outline:2px solid #2271b1; outline-offset:-2px; }
            .flacso-oferta-section__icon { display:grid; width:36px; height:36px; flex:0 0 36px; place-items:center; border-radius:8px; color:#1d4ed8; background:#eff6ff; font-size:20px; line-height:36px; }
            .flacso-oferta-section__copy { display:grid; flex:1; gap:3px; }
            .flacso-oferta-section__copy strong { font-size:14px; }
            .flacso-oferta-section__copy small { color:#646970; font-size:12px; font-weight:400; }
            .flacso-oferta-section__status { padding:4px 8px; border-radius:999px; color:#8a4b00; background:#fcf0c9; font-size:11px; font-weight:700; white-space:nowrap; }
            .flacso-oferta-section__status.is-complete { color:#116329; background:#edfaef; }
            .flacso-oferta-section__body { padding:4px 18px 20px; border-top:1px solid #f0f0f1; }
            .flacso-oferta-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-top:16px; }
            .flacso-oferta-grid--three { grid-template-columns:repeat(3,minmax(0,1fr)); }
            .flacso-oferta-field { display:grid; gap:6px; }
            .flacso-oferta-field > span { font-weight:600; }
            .flacso-oferta-field small { color:#646970; line-height:1.4; }
            .flacso-oferta-field input,.flacso-oferta-field select,.flacso-oferta-field textarea { width:100%; max-width:none; }
            .flacso-oferta-field input,.flacso-oferta-field select { min-height:38px; }
            .flacso-oferta-field--full { grid-column:1/-1; }
            .flacso-oferta-editor-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; }
            .flacso-oferta-editor { min-width:0; margin-top:18px; }
            .flacso-oferta-editor-grid .flacso-oferta-editor { margin-top:16px; }
            .flacso-oferta-editor > label { display:block; margin-bottom:6px; }
            .flacso-oferta-editor > .description { margin:0 0 8px; }
            .flacso-oferta-editor .wp-editor-wrap { border-radius:4px; overflow:hidden; }
            .flacso-oferta-checks { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-top:16px; }
            .flacso-oferta-checks label { display:flex; align-items:flex-start; gap:8px; padding:10px 12px; border:1px solid #dcdcde; border-radius:6px; background:#fafafa; }
            .flacso-oferta-checks input { margin-top:1px; }
            @media(max-width:1100px){ .flacso-oferta-workspace{flex-direction:column;} .flacso-oferta-workspace__aside{min-width:0;align-items:flex-start;} .flacso-oferta-pills{justify-content:flex-start;} .flacso-oferta-grid--three,.flacso-oferta-editor-grid{grid-template-columns:1fr;} }
            @media(max-width:782px){ .flacso-oferta-fields{padding:12px;} .flacso-oferta-workspace{padding:18px;} .flacso-oferta-grid,.flacso-oferta-checks{grid-template-columns:1fr;} .flacso-oferta-field--full{grid-column:auto;} .flacso-oferta-section__summary{align-items:flex-start;padding:14px;} .flacso-oferta-section__copy small{display:none;} .flacso-oferta-section__status{margin-left:auto;} .flacso-oferta-section__body{padding:2px 14px 16px;} }
        </style>
        <?php
    }

    public static function render_scripts(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== FLACSO_Oferta_Academica::POST_TYPE) {
            return;
        }
        ?>
        <script>
        (function () {
            var root = document.querySelector('.flacso-oferta-fields');
            if (!root) return;

            root.querySelectorAll('[data-flacso-sections]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var open = button.getAttribute('data-flacso-sections') === 'open';
                    root.querySelectorAll('details.flacso-oferta-section').forEach(function (section) {
                        section.open = open;
                    });
                    window.dispatchEvent(new Event('resize'));
                });
            });

            root.querySelectorAll('details.flacso-oferta-section').forEach(function (section) {
                section.addEventListener('toggle', function () {
                    if (section.open) window.dispatchEvent(new Event('resize'));
                });
            });
        })();
        </script>
        <?php
    }
}
