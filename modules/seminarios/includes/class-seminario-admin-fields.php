<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Editor wp-admin de los datos académicos estables de Seminario. */
final class FLACSO_Seminario_Admin_Fields {
    private const NONCE_ACTION = 'flacso_save_seminario_academico';
    private const NONCE_NAME = 'flacso_seminario_academico_nonce';

    /** Proyección de lectura durante la transición desde el esquema _seminario_*. */
    private const LEGACY_KEYS = [
        'correo'                    => '_seminario_mail_contacto',
        'presentacion'              => '_seminario_presentacion_seminario',
        'objetivo_general'          => '_seminario_objetivo_general',
        'objetivos_especificos'     => '_seminario_objetivos_especificos',
        'composicion_academica'     => '_seminario_unidades_academicas',
        'forma_aprobacion'          => '_seminario_forma_aprobacion',
        'carga_horaria'             => '_seminario_carga_horaria',
        'carga_horaria_descripcion' => '_seminario_descripcion_horas',
        'creditos'                  => '_seminario_creditos',
        'acreditacion'              => '_seminario_acreditacion',
        'acredita_maestria'         => '_seminario_acredita_maestria',
        'acredita_doctorado'        => '_seminario_acredita_doctorado',
        'componentes'               => '_seminario_seminarios_componentes',
    ];

    private static $initialized = false;

    public static function init(): void {
        if (self::$initialized || !is_admin()) {
            return;
        }
        self::$initialized = true;
        add_action('add_meta_boxes', [self::class, 'add_meta_box']);
        add_action('save_post_' . FLACSO_Seminario::POST_TYPE, [self::class, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('admin_head-post.php', [self::class, 'render_styles']);
        add_action('admin_head-post-new.php', [self::class, 'render_styles']);
        add_action('admin_footer-post.php', [self::class, 'render_script']);
        add_action('admin_footer-post-new.php', [self::class, 'render_script']);
    }

    public static function add_meta_box(): void {
        add_meta_box(
            'flacso_seminario_academico_box',
            __('Información académica del Seminario', 'flacso-uruguay'),
            [self::class, 'render_meta_box'],
            FLACSO_Seminario::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function enqueue_assets(string $hook): void {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        $screen = get_current_screen();
        if ($screen && $screen->post_type === FLACSO_Seminario::POST_TYPE) {
            wp_enqueue_script('jquery-ui-sortable');
        }
    }

    private static function value(int $post_id, string $field, bool &$from_legacy = false) {
        if (metadata_exists('post', $post_id, $field)) {
            return get_post_meta($post_id, $field, true);
        }
        $legacy_key = self::LEGACY_KEYS[$field] ?? '';
        if ($legacy_key !== '' && metadata_exists('post', $post_id, $legacy_key)) {
            $from_legacy = true;
            $value = get_post_meta($post_id, $legacy_key, true);
            if ($field === 'componentes' && is_array($value)) {
                return array_values(array_map(static function ($seminar_id, $index): array {
                    return [
                        'seminario_id' => absint($seminar_id),
                        'orden'        => $index + 1,
                    ];
                }, $value, array_keys($value)));
            }
            return $value;
        }
        return in_array($field, ['objetivos_especificos', 'composicion_academica', 'componentes'], true) ? [] : '';
    }

    public static function render_meta_box($post): void {
        $legacy_fields = [];
        $get = static function (string $field) use ($post, &$legacy_fields) {
            $from_legacy = false;
            $value = self::value((int) $post->ID, $field, $from_legacy);
            if ($from_legacy) {
                $legacy_fields[] = $field;
            }
            return $value;
        };

        $program_id = absint(get_post_meta($post->ID, FLACSO_Seminario::META_PROGRAM_ID, true));
        $correo = (string) $get('correo');
        $presentacion = (string) $get('presentacion');
        $objetivo_general = (string) $get('objetivo_general');
        $objetivos = $get('objetivos_especificos');
        $objetivos = is_array($objetivos) ? $objetivos : [];
        $composicion = $get('composicion_academica');
        $composicion = is_array($composicion) ? $composicion : [];
        $forma_aprobacion = (string) $get('forma_aprobacion');
        $carga_horaria = $get('carga_horaria');
        $carga_descripcion = (string) $get('carga_horaria_descripcion');
        $creditos = $get('creditos');
        $acreditacion = (string) $get('acreditacion');
        $acredita_maestria = rest_sanitize_boolean($get('acredita_maestria'));
        $acredita_doctorado = rest_sanitize_boolean($get('acredita_doctorado'));
        $componentes = FLACSO_Seminario::sanitize_components($get('componentes'));

        $programs = get_posts([
            'post_type'      => 'programa-academico',
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        $seminars = get_posts([
            'post_type'      => FLACSO_Seminario::POST_TYPE,
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'post__not_in'   => [(int) $post->ID],
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        $component_ids = array_values(array_filter(array_map(static function (array $item): int {
            return absint($item['seminario_id'] ?? 0);
        }, $componentes)));
        $seminars_by_id = [];
        foreach ($seminars as $seminar) {
            $seminars_by_id[(int) $seminar->ID] = $seminar;
        }

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        ?>
        <div class="flacso-seminar-fields">
            <?php if (!empty($legacy_fields)) : ?>
                <div class="notice notice-warning inline flacso-seminar-legacy-notice">
                    <p><strong><?php esc_html_e('Datos históricos detectados.', 'flacso-uruguay'); ?></strong>
                        <?php esc_html_e('Se muestran para que pueda editarlos. Al actualizar el Seminario quedarán guardados en el modelo académico vigente, sin borrar los valores históricos.', 'flacso-uruguay'); ?></p>
                </div>
            <?php endif; ?>

            <section class="flacso-seminar-section">
                <div class="flacso-seminar-section__heading">
                    <h3><?php esc_html_e('Datos generales', 'flacso-uruguay'); ?></h3>
                    <p><?php esc_html_e('Información estable del Seminario. Las fechas, modalidad, docentes, precios y preinscripción se editan dentro de cada Edición.', 'flacso-uruguay'); ?></p>
                </div>
                <div class="flacso-seminar-grid">
                    <label class="flacso-seminar-field flacso-seminar-field--wide">
                        <span><?php esc_html_e('Programa académico', 'flacso-uruguay'); ?></span>
                        <select name="flacso_seminario[programa_academico_id]">
                            <option value=""><?php esc_html_e('— Sin asignar —', 'flacso-uruguay'); ?></option>
                            <?php foreach ($programs as $program) : ?>
                                <option value="<?php echo esc_attr((string) $program->ID); ?>" <?php selected($program_id, $program->ID); ?>><?php echo esc_html(get_the_title($program)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="flacso-seminar-field flacso-seminar-field--wide">
                        <span><?php esc_html_e('Correo de contacto', 'flacso-uruguay'); ?></span>
                        <input type="email" name="flacso_seminario[correo]" value="<?php echo esc_attr($correo); ?>" autocomplete="off">
                    </label>
                    <label class="flacso-seminar-field">
                        <span><?php esc_html_e('Créditos', 'flacso-uruguay'); ?></span>
                        <input type="number" name="flacso_seminario[creditos]" value="<?php echo esc_attr((string) $creditos); ?>" min="0" step="0.1" inputmode="decimal" autocomplete="off">
                    </label>
                    <label class="flacso-seminar-field">
                        <span><?php esc_html_e('Carga horaria', 'flacso-uruguay'); ?></span>
                        <input type="number" name="flacso_seminario[carga_horaria]" value="<?php echo esc_attr((string) $carga_horaria); ?>" min="0" step="0.1" inputmode="decimal" autocomplete="off">
                    </label>
                    <label class="flacso-seminar-field flacso-seminar-field--full">
                        <span><?php esc_html_e('Descripción de la carga horaria', 'flacso-uruguay'); ?></span>
                        <input type="text" name="flacso_seminario[carga_horaria_descripcion]" value="<?php echo esc_attr($carga_descripcion); ?>">
                    </label>
                    <div class="flacso-seminar-checks flacso-seminar-field--full">
                        <label><input type="checkbox" name="flacso_seminario[acredita_maestria]" value="1" <?php checked($acredita_maestria); ?>> <?php esc_html_e('Acredita para Maestría', 'flacso-uruguay'); ?></label>
                        <label><input type="checkbox" name="flacso_seminario[acredita_doctorado]" value="1" <?php checked($acredita_doctorado); ?>> <?php esc_html_e('Acredita para Doctorado', 'flacso-uruguay'); ?></label>
                    </div>
                </div>
            </section>

            <section class="flacso-seminar-section">
                <div class="flacso-seminar-section__heading"><h3><?php esc_html_e('Presentación y objetivos', 'flacso-uruguay'); ?></h3></div>
                <div class="flacso-seminar-editor-field">
                    <label for="flacso_seminario_presentacion"><strong><?php esc_html_e('Presentación del seminario', 'flacso-uruguay'); ?></strong></label>
                    <?php wp_editor($presentacion, 'flacso_seminario_presentacion', [
                        'textarea_name' => 'flacso_seminario[presentacion]',
                        'textarea_rows' => 8,
                        'media_buttons' => false,
                        'teeny'         => true,
                    ]); ?>
                </div>
                <div class="flacso-seminar-editor-field">
                    <label for="flacso_seminario_objetivo_general"><strong><?php esc_html_e('Objetivo general', 'flacso-uruguay'); ?></strong></label>
                    <?php wp_editor($objetivo_general, 'flacso_seminario_objetivo_general', [
                        'textarea_name' => 'flacso_seminario[objetivo_general]',
                        'textarea_rows' => 5,
                        'media_buttons' => false,
                        'teeny'         => true,
                    ]); ?>
                </div>

                <div class="flacso-repeatable" id="flacso-seminar-objectives">
                    <div class="flacso-repeatable__heading"><strong><?php esc_html_e('Objetivos específicos', 'flacso-uruguay'); ?></strong><button type="button" class="button flacso-add-objective"><?php esc_html_e('Agregar objetivo', 'flacso-uruguay'); ?></button></div>
                    <div class="flacso-repeatable__rows">
                        <?php foreach (($objetivos ?: ['']) as $objective) : ?>
                            <div class="flacso-repeatable-row"><span class="dashicons dashicons-menu" aria-hidden="true"></span><textarea name="flacso_seminario[objetivos_especificos][]" rows="2"><?php echo esc_textarea((string) $objective); ?></textarea><button type="button" class="button-link-delete flacso-remove-row"><?php esc_html_e('Eliminar', 'flacso-uruguay'); ?></button></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="flacso-seminar-section">
                <div class="flacso-seminar-section__heading"><h3><?php esc_html_e('Composición académica y aprobación', 'flacso-uruguay'); ?></h3></div>
                <div class="flacso-repeatable" id="flacso-seminar-composition">
                    <div class="flacso-repeatable__heading"><strong><?php esc_html_e('Unidades o secciones académicas', 'flacso-uruguay'); ?></strong><button type="button" class="button flacso-add-section"><?php esc_html_e('Agregar sección', 'flacso-uruguay'); ?></button></div>
                    <div class="flacso-repeatable__rows">
                        <?php foreach (($composicion ?: [['titulo' => '', 'contenido' => '']]) as $index => $section) : ?>
                            <div class="flacso-repeatable-row flacso-composition-row"><span class="dashicons dashicons-menu" aria-hidden="true"></span><div><input type="text" data-field="titulo" name="flacso_seminario[composicion_academica][<?php echo esc_attr((string) $index); ?>][titulo]" value="<?php echo esc_attr((string) ($section['titulo'] ?? '')); ?>" placeholder="<?php esc_attr_e('Título de la sección', 'flacso-uruguay'); ?>"><textarea data-field="contenido" name="flacso_seminario[composicion_academica][<?php echo esc_attr((string) $index); ?>][contenido]" rows="3" placeholder="<?php esc_attr_e('Contenido', 'flacso-uruguay'); ?>"><?php echo esc_textarea((string) ($section['contenido'] ?? '')); ?></textarea></div><button type="button" class="button-link-delete flacso-remove-row"><?php esc_html_e('Eliminar', 'flacso-uruguay'); ?></button></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flacso-seminar-editor-field">
                    <label for="flacso_seminario_forma_aprobacion"><strong><?php esc_html_e('Forma de aprobación', 'flacso-uruguay'); ?></strong></label>
                    <?php wp_editor($forma_aprobacion, 'flacso_seminario_forma_aprobacion', [
                        'textarea_name' => 'flacso_seminario[forma_aprobacion]',
                        'textarea_rows' => 5,
                        'media_buttons' => false,
                        'teeny'         => true,
                    ]); ?>
                </div>
                <div class="flacso-seminar-editor-field">
                    <label for="flacso_seminario_acreditacion"><strong><?php esc_html_e('Acreditación', 'flacso-uruguay'); ?></strong></label>
                    <?php wp_editor($acreditacion, 'flacso_seminario_acreditacion', [
                        'textarea_name' => 'flacso_seminario[acreditacion]',
                        'textarea_rows' => 4,
                        'media_buttons' => false,
                        'teeny'         => true,
                    ]); ?>
                </div>
            </section>

            <section class="flacso-seminar-section">
                <div class="flacso-seminar-section__heading">
                    <h3><?php esc_html_e('Seminario integrado', 'flacso-uruguay'); ?></h3>
                    <p><?php esc_html_e('Seleccione y ordene los seminarios que componen esta propuesta. Déjelo vacío para un seminario independiente.', 'flacso-uruguay'); ?></p>
                </div>
                <div id="flacso-seminar-components" class="flacso-components">
                    <div class="flacso-components__selected">
                        <?php foreach ($component_ids as $index => $component_id) :
                            if (!isset($seminars_by_id[$component_id])) {
                                continue;
                            }
                        ?>
                            <div class="flacso-component-row" data-id="<?php echo esc_attr((string) $component_id); ?>">
                                <span class="dashicons dashicons-menu" aria-hidden="true"></span>
                                <strong><?php echo esc_html(get_the_title($seminars_by_id[$component_id])); ?></strong>
                                <input type="hidden" data-field="seminario_id" name="flacso_seminario[componentes][<?php echo esc_attr((string) $index); ?>][seminario_id]" value="<?php echo esc_attr((string) $component_id); ?>">
                                <input type="hidden" data-field="orden" name="flacso_seminario[componentes][<?php echo esc_attr((string) $index); ?>][orden]" value="<?php echo esc_attr((string) ($index + 1)); ?>">
                                <button type="button" class="button-link-delete flacso-remove-component"><?php esc_html_e('Quitar', 'flacso-uruguay'); ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="flacso-components__add">
                        <select class="flacso-component-picker">
                            <option value=""><?php esc_html_e('— Seleccionar seminario —', 'flacso-uruguay'); ?></option>
                            <?php foreach ($seminars as $seminar) : ?>
                                <option value="<?php echo esc_attr((string) $seminar->ID); ?>" <?php disabled(in_array((int) $seminar->ID, $component_ids, true)); ?>><?php echo esc_html(get_the_title($seminar)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button flacso-add-component"><?php esc_html_e('Agregar componente', 'flacso-uruguay'); ?></button>
                    </div>
                </div>
            </section>
        </div>
        <?php
    }

    public static function save(int $post_id, $post): void {
        if (
            !isset($_POST[self::NONCE_NAME])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || wp_is_post_revision($post_id)
            || !$post
            || $post->post_type !== FLACSO_Seminario::POST_TYPE
            || !current_user_can('edit_post', $post_id)
        ) {
            return;
        }

        $raw = isset($_POST['flacso_seminario']) && is_array($_POST['flacso_seminario'])
            ? wp_unslash($_POST['flacso_seminario'])
            : [];

        $program_id = absint($raw['programa_academico_id'] ?? 0);
        if ($program_id > 0 && get_post_type($program_id) === 'programa-academico') {
            update_post_meta($post_id, FLACSO_Seminario::META_PROGRAM_ID, $program_id);
        } else {
            delete_post_meta($post_id, FLACSO_Seminario::META_PROGRAM_ID);
        }

        self::save_scalar($post_id, 'correo', sanitize_email((string) ($raw['correo'] ?? '')));
        foreach (['presentacion', 'objetivo_general', 'forma_aprobacion', 'acreditacion'] as $field) {
            self::save_scalar($post_id, $field, wp_kses_post((string) ($raw[$field] ?? '')));
        }
        self::save_scalar($post_id, 'carga_horaria_descripcion', sanitize_text_field((string) ($raw['carga_horaria_descripcion'] ?? '')));

        foreach (['carga_horaria', 'creditos'] as $field) {
            $input = trim((string) ($raw[$field] ?? ''));
            if ($input === '') {
                delete_post_meta($post_id, $field);
            } else {
                update_post_meta($post_id, $field, FLACSO_Seminario::sanitize_number($input));
            }
        }

        update_post_meta($post_id, 'acredita_maestria', isset($raw['acredita_maestria']));
        update_post_meta($post_id, 'acredita_doctorado', isset($raw['acredita_doctorado']));

        $objectives = FLACSO_Seminario::sanitize_html_list($raw['objetivos_especificos'] ?? []);
        self::save_collection($post_id, 'objetivos_especificos', $objectives);

        $composition = FLACSO_Seminario::sanitize_sections($raw['composicion_academica'] ?? []);
        self::save_collection($post_id, 'composicion_academica', $composition);

        $components = FLACSO_Seminario::sanitize_components($raw['componentes'] ?? []);
        $components = array_values(array_filter($components, static function (array $component) use ($post_id): bool {
            $component_id = absint($component['seminario_id'] ?? 0);
            return $component_id !== $post_id && get_post_type($component_id) === FLACSO_Seminario::POST_TYPE;
        }));
        foreach ($components as $index => &$component) {
            $component['orden'] = $index + 1;
        }
        unset($component);
        self::save_collection($post_id, FLACSO_Seminario::META_COMPONENTES, $components);
    }

    private static function save_scalar(int $post_id, string $field, $value): void {
        if (trim(wp_strip_all_tags((string) $value)) === '') {
            delete_post_meta($post_id, $field);
        } else {
            update_post_meta($post_id, $field, $value);
        }
    }

    private static function save_collection(int $post_id, string $field, array $value): void {
        if (empty($value)) {
            delete_post_meta($post_id, $field);
        } else {
            update_post_meta($post_id, $field, $value);
        }
    }

    private static function is_seminar_screen(): bool {
        $screen = get_current_screen();
        return $screen && $screen->post_type === FLACSO_Seminario::POST_TYPE;
    }

    public static function render_styles(): void {
        if (!self::is_seminar_screen()) {
            return;
        }
        ?>
        <style>
            .flacso-seminar-fields { margin: -6px -12px -12px; background: #f6f7f7; }
            .flacso-seminar-legacy-notice { margin: 12px !important; }
            .flacso-seminar-section { padding: 20px; border-top: 1px solid #dcdcde; background: #fff; }
            .flacso-seminar-section:first-of-type { border-top: 0; }
            .flacso-seminar-section__heading { margin-bottom: 16px; }
            .flacso-seminar-section__heading h3 { margin: 0 0 4px; font-size: 15px; }
            .flacso-seminar-section__heading p { margin: 0; color: #646970; }
            .flacso-seminar-grid { display: grid; grid-template-columns: repeat(4, minmax(120px, 1fr)); gap: 14px; }
            .flacso-seminar-field { display: grid; gap: 5px; }
            .flacso-seminar-field > span { font-weight: 600; }
            .flacso-seminar-field input,
            .flacso-seminar-field select { width: 100%; }
            .flacso-seminar-field--wide { grid-column: span 2; }
            .flacso-seminar-field--full { grid-column: 1 / -1; }
            .flacso-seminar-checks { display: flex; flex-wrap: wrap; gap: 18px; padding-top: 2px; }
            .flacso-seminar-editor-field + .flacso-seminar-editor-field,
            .flacso-seminar-editor-field + .flacso-repeatable,
            .flacso-repeatable + .flacso-seminar-editor-field { margin-top: 18px; }
            .flacso-seminar-editor-field > label { display: inline-block; margin-bottom: 7px; }
            .flacso-repeatable { padding: 14px; border: 1px solid #dcdcde; border-radius: 5px; background: #f9f9f9; }
            .flacso-repeatable__heading { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 10px; }
            .flacso-repeatable__rows { display: grid; gap: 8px; }
            .flacso-repeatable-row,
            .flacso-component-row { display: grid; grid-template-columns: 24px minmax(0, 1fr) auto; align-items: center; gap: 8px; padding: 9px; border: 1px solid #dcdcde; border-radius: 4px; background: #fff; }
            .flacso-repeatable-row textarea,
            .flacso-composition-row input { width: 100%; }
            .flacso-composition-row > div { display: grid; gap: 7px; }
            .flacso-repeatable-row .dashicons,
            .flacso-component-row .dashicons { color: #8c8f94; cursor: move; }
            .flacso-components__selected { display: grid; gap: 8px; margin-bottom: 10px; }
            .flacso-components__add { display: flex; gap: 8px; }
            .flacso-component-picker { width: min(100%, 620px); }
            @media (max-width: 900px) {
                .flacso-seminar-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }
            @media (max-width: 600px) {
                .flacso-seminar-grid { grid-template-columns: 1fr; }
                .flacso-seminar-field--wide { grid-column: auto; }
                .flacso-components__add { flex-direction: column; }
            }
        </style>
        <?php
    }

    public static function render_script(): void {
        if (!self::is_seminar_screen()) {
            return;
        }
        ?>
        <script>
        (function($) {
            function reindexComposition() {
                $('#flacso-seminar-composition .flacso-composition-row').each(function(index) {
                    $(this).find('[data-field]').each(function() {
                        $(this).attr('name', 'flacso_seminario[composicion_academica][' + index + '][' + $(this).data('field') + ']');
                    });
                });
            }

            function reindexComponents() {
                $('#flacso-seminar-components .flacso-component-row').each(function(index) {
                    $(this).find('[data-field="seminario_id"]').attr('name', 'flacso_seminario[componentes][' + index + '][seminario_id]');
                    $(this).find('[data-field="orden"]').attr('name', 'flacso_seminario[componentes][' + index + '][orden]').val(index + 1);
                });
            }

            $('#flacso-seminar-objectives .flacso-repeatable__rows, #flacso-seminar-composition .flacso-repeatable__rows, .flacso-components__selected').sortable({
                handle: '.dashicons-menu',
                update: function() { reindexComposition(); reindexComponents(); }
            });

            $(document).on('click', '.flacso-add-objective', function() {
                $('#flacso-seminar-objectives .flacso-repeatable__rows').append('<div class="flacso-repeatable-row"><span class="dashicons dashicons-menu" aria-hidden="true"></span><textarea name="flacso_seminario[objetivos_especificos][]" rows="2"></textarea><button type="button" class="button-link-delete flacso-remove-row"><?php echo esc_js(__('Eliminar', 'flacso-uruguay')); ?></button></div>');
            });

            $(document).on('click', '.flacso-add-section', function() {
                $('#flacso-seminar-composition .flacso-repeatable__rows').append('<div class="flacso-repeatable-row flacso-composition-row"><span class="dashicons dashicons-menu" aria-hidden="true"></span><div><input type="text" data-field="titulo" placeholder="<?php echo esc_js(__('Título de la sección', 'flacso-uruguay')); ?>"><textarea data-field="contenido" rows="3" placeholder="<?php echo esc_js(__('Contenido', 'flacso-uruguay')); ?>"></textarea></div><button type="button" class="button-link-delete flacso-remove-row"><?php echo esc_js(__('Eliminar', 'flacso-uruguay')); ?></button></div>');
                reindexComposition();
            });

            $(document).on('click', '.flacso-remove-row', function() {
                $(this).closest('.flacso-repeatable-row').remove();
                reindexComposition();
            });

            $(document).on('click', '.flacso-add-component', function() {
                var picker = $('.flacso-component-picker');
                var option = picker.find('option:selected');
                var id = String(option.val() || '');
                if (!id) return;
                var row = $('<div class="flacso-component-row" data-id="' + id + '"><span class="dashicons dashicons-menu" aria-hidden="true"></span><strong></strong><input type="hidden" data-field="seminario_id" value="' + id + '"><input type="hidden" data-field="orden"><button type="button" class="button-link-delete flacso-remove-component"><?php echo esc_js(__('Quitar', 'flacso-uruguay')); ?></button></div>');
                row.find('strong').text(option.text());
                $('.flacso-components__selected').append(row);
                option.prop('disabled', true);
                picker.val('');
                reindexComponents();
            });

            $(document).on('click', '.flacso-remove-component', function() {
                var row = $(this).closest('.flacso-component-row');
                $('.flacso-component-picker option[value="' + row.data('id') + '"]').prop('disabled', false);
                row.remove();
                reindexComponents();
            });
        })(jQuery);
        </script>
        <?php
    }
}
