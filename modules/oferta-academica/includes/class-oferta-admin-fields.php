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
        add_action('admin_notices', [self::class, 'render_admin_notices']);
        add_action('admin_head-post.php', [self::class, 'render_styles']);
        add_action('admin_head-post-new.php', [self::class, 'render_styles']);
        add_action('admin_footer-post.php', [self::class, 'render_scripts']);
        add_action('admin_footer-post-new.php', [self::class, 'render_scripts']);
        add_action('add_meta_boxes', [self::class, 'remove_theme_settings'], 100);
    }

    public static function remove_theme_settings(): void {
        foreach (['normal', 'side', 'advanced'] as $context) {
            remove_meta_box('_kad_classic_meta_control', FLACSO_Oferta_Academica::POST_TYPE, $context);
        }
    }

    public static function render_admin_notices(): void {
        global $post;
        $post_id = 0;
        if ($post instanceof WP_Post) {
            $post_id = $post->ID;
        } elseif (isset($_GET['post'])) {
            $post_id = absint($_GET['post']);
        }
        if ($post_id <= 0) {
            return;
        }

        $transient_key = 'flacso_oferta_abbr_error_' . $post_id;
        $message = get_transient($transient_key);
        if ($message) {
            delete_transient($transient_key);
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
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
        if ($program_id === 0 && isset($_GET['programa_academico_id'])) {
            $program_id = absint($_GET['programa_academico_id']);
        }
        $current_type = FLACSO_Oferta_Academica::get_tipo($post->ID);
        $offer_types = [
            FLACSO_Oferta_Academica::TIPO_DOCTORADO => __('Doctorado', 'flacso-uruguay'),
            FLACSO_Oferta_Academica::TIPO_MAESTRIA => __('Maestría', 'flacso-uruguay'),
            FLACSO_Oferta_Academica::TIPO_ESPECIALIZACION => __('Especialización', 'flacso-uruguay'),
            FLACSO_Oferta_Academica::TIPO_DIPLOMADO => __('Diplomado', 'flacso-uruguay'),
            FLACSO_Oferta_Academica::TIPO_DIPLOMA => __('Diploma', 'flacso-uruguay'),
        ];
        $colors = FLACSO_Oferta_Academica::sanitize_presentation_colors(self::meta($post->ID, FLACSO_Oferta_Academica::META_PRESENTATION_COLORS, []));
        $principal_color = (string) ($colors['principal'] ?? '');
        $programs = get_posts([
            'post_type' => FLACSO_Programa_Academico::POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $sections = [
            'general' => [FLACSO_Oferta_Academica::META_PROGRAM_ID, 'abreviacion', 'correo', FLACSO_Oferta_Academica::META_PRESENTATION_COLORS],
            'presentation' => ['presentacion'],
            'course' => ['duracion_meses', 'carga_horaria', 'creditos'],
            'profiles' => ['perfil_ingreso_html', 'requisitos_ingreso_html', 'perfil_egreso_html', 'requisitos_egreso_html'],
            'certification' => ['titulos_certificaciones_html'],
            'curriculum' => ['malla_curricular', 'menciones', 'orientaciones', 'titulos_intermedios'],
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
                    <?php if ('auto-draft' !== $post->post_status && get_permalink($post)) : ?>
                        <p class="flacso-oferta-public-link"><a href="<?php echo esc_url(get_permalink($post)); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-external" aria-hidden="true"></span><?php esc_html_e('Ver página pública', 'flacso-uruguay'); ?></a></p>
                    <?php endif; ?>
                </div>
                <div class="flacso-oferta-workspace__aside">
                    <div class="flacso-oferta-pills" aria-label="<?php esc_attr_e('Resumen de la oferta', 'flacso-uruguay'); ?>">
                        <span class="flacso-oferta-pill"><span class="dashicons dashicons-category" aria-hidden="true"></span><?php echo esc_html($type_label); ?></span>
                        <span class="flacso-oferta-pill"><span class="dashicons dashicons-groups" aria-hidden="true"></span><?php echo esc_html(sprintf(_n('%d cohorte', '%d cohortes', count($cohort_ids), 'flacso-uruguay'), count($cohort_ids))); ?></span>
                        <span class="flacso-oferta-pill flacso-oferta-pill--progress"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php echo esc_html(sprintf(__('%1$d de %2$d campos con contenido', 'flacso-uruguay'), $completed, $total)); ?></span>
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
                <label class="flacso-oferta-field">
                    <span><?php esc_html_e('Tipo de oferta', 'flacso-uruguay'); ?></span>
                    <select name="flacso_oferta_tipo">
                        <option value=""><?php esc_html_e('— Sin tipo —', 'flacso-uruguay'); ?></option>
                        <?php foreach ($offer_types as $type_key => $type_name) : ?>
                            <option value="<?php echo esc_attr($type_key); ?>" <?php selected($current_type, $type_key); ?>><?php echo esc_html($type_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php self::text_field($post->ID, 'abreviacion', __('Abreviación', 'flacso-uruguay')); ?>
                <?php self::text_field($post->ID, 'correo', __('Correo de contacto', 'flacso-uruguay'), 'email'); ?>
                <label class="flacso-oferta-field flacso-oferta-field--full">
                    <span><?php esc_html_e('Color principal', 'flacso-uruguay'); ?></span>
                    <span class="flacso-oferta-color-control">
                        <input type="color" id="flacso_oferta_color_picker" value="<?php echo esc_attr($principal_color !== '' ? $principal_color : '#ffffff'); ?>" aria-label="<?php esc_attr_e('Elegir color principal', 'flacso-uruguay'); ?>">
                        <input type="text" id="flacso_oferta_color_principal" name="flacso_oferta_color_principal" value="<?php echo esc_attr($principal_color); ?>" placeholder="#0057a8" maxlength="7" aria-describedby="flacso-oferta-color-help">
                        <span class="flacso-oferta-color-preview <?php echo $principal_color === '' ? 'is-empty' : ''; ?>" data-color-preview<?php echo $principal_color !== '' ? ' style="background:' . esc_attr($principal_color) . ';"' : ''; ?>>
                            <?php echo $principal_color !== '' ? esc_html($principal_color) : esc_html__('Sin completar', 'flacso-uruguay'); ?>
                        </span>
                        <button type="button" class="button" data-clear-offer-color><?php esc_html_e('Limpiar', 'flacso-uruguay'); ?></button>
                    </span>
                    <small id="flacso-oferta-color-help"><?php esc_html_e('Se guarda únicamente un hexadecimal de seis dígitos. Por ahora no modifica la página pública.', 'flacso-uruguay'); ?></small>
                </label>
            </div>
            <?php self::section_end(); ?>

            <?php self::section_start($post->ID, 'presentacion-objetivos', __('Presentación', 'flacso-uruguay'), __('Explica de qué trata la propuesta, por qué es relevante y qué busca lograr.', 'flacso-uruguay'), $sections['presentation'], 'dashicons-text-page'); ?>
            <?php self::rich_editor($post->ID, 'presentacion', __('Presentación', 'flacso-uruguay'), __('Texto introductorio que se muestra al comienzo de la página pública.', 'flacso-uruguay'), 8); ?>
            <?php self::section_end(); ?>

            <?php self::section_start($post->ID, 'cursado-aprobacion', __('Cursado', 'flacso-uruguay'), __('Duración, dedicación y créditos de la oferta.', 'flacso-uruguay'), $sections['course'], 'dashicons-clock'); ?>
            <div class="flacso-oferta-grid flacso-oferta-grid--three">
                <?php self::text_field($post->ID, 'duracion_meses', __('Duración (meses)', 'flacso-uruguay'), 'number'); ?>
                <?php self::text_field($post->ID, 'carga_horaria', __('Carga horaria', 'flacso-uruguay'), 'number'); ?>
                <?php self::text_field($post->ID, 'creditos', __('Créditos', 'flacso-uruguay'), 'number'); ?>
            </div>
            <?php self::section_end(); ?>

            <?php self::section_start($post->ID, 'perfiles-requisitos', __('Perfiles y requisitos', 'flacso-uruguay'), __('Diferencia claramente a quién está dirigida la propuesta y qué debe cumplir para ingresar y egresar.', 'flacso-uruguay'), $sections['profiles'], 'dashicons-id-alt'); ?>
            <div class="flacso-oferta-editor-grid">
                <?php self::rich_editor($post->ID, 'perfil_ingreso_html', __('Perfil de ingreso', 'flacso-uruguay')); ?>
                <?php self::rich_editor($post->ID, 'requisitos_ingreso_html', __('Requisitos de ingreso', 'flacso-uruguay')); ?>
                <?php self::rich_editor($post->ID, 'perfil_egreso_html', __('Perfil de egreso', 'flacso-uruguay')); ?>
                <?php self::rich_editor($post->ID, 'requisitos_egreso_html', __('Requisitos de egreso y titulación.', 'flacso-uruguay')); ?>
            </div>
            <?php self::section_end(); ?>

            <?php self::section_start($post->ID, 'titulacion-financiacion', __('Titulación', 'flacso-uruguay'), __('Resultados formales de la formación y condiciones institucionales asociadas.', 'flacso-uruguay'), $sections['certification'], 'dashicons-awards'); ?>
            <?php self::rich_editor($post->ID, 'titulos_certificaciones_html', __('Títulos y certificaciones', 'flacso-uruguay')); ?>
            <?php self::section_end(); ?>

            <?php self::section_start($post->ID, 'plan-estudios', __('Plan de estudios', 'flacso-uruguay'), __('Malla curricular y variantes académicas de la propuesta.', 'flacso-uruguay'), $sections['curriculum'], 'dashicons-welcome-learn-more'); ?>
            <div class="flacso-oferta-grid">
                <?php self::text_field($post->ID, 'malla_curricular', __('URL de malla curricular', 'flacso-uruguay'), 'url', true, __('Admite un PDF o un documento público accesible por enlace.', 'flacso-uruguay')); ?>
            </div>
            <div class="flacso-oferta-grid">
                <?php self::list_field($post->ID, 'menciones', __('Menciones', 'flacso-uruguay')); ?>
                <?php self::list_field($post->ID, 'orientaciones', __('Orientaciones', 'flacso-uruguay')); ?>
                <?php self::intermediate_titles_field($post->ID); ?>
            </div>
            <?php self::section_end(); ?>

            <?php FLACSO_Academic_Team_Editor::render_offer_section($post); ?>
            <?php FLACSO_Offer_Carta_Contact_Admin::render_section($post); ?>

            <section class="flacso-oferta-section">
                <header class="flacso-oferta-section__summary">
                    <span class="flacso-oferta-section__icon dashicons dashicons-visibility" aria-hidden="true"></span>
                    <span class="flacso-oferta-section__copy"><strong><?php esc_html_e('Reconocimientos y visualización', 'flacso-uruguay'); ?></strong><small><?php esc_html_e('Indicadores públicos y opciones específicas de presentación.', 'flacso-uruguay'); ?></small></span>
                </header>
                <div class="flacso-oferta-section__body">
                    <div class="flacso-oferta-checks">
                        <?php $flag_descriptions = ['reconocido_mec' => 'Muestra el reconocimiento oficial del MEC en la página de la oferta y en la carta.','reconocimiento_internacional' => 'Muestra el distintivo de titulación apostillada de La Haya en la página y en la carta.','reconocimiento_red_flacso' => 'Muestra el reconocimiento del título en la red del Sistema Internacional FLACSO en la página y en la carta.','convenio_iin_oea' => 'Muestra el distintivo del convenio IIN/OEA en la página y en la carta.']; ?>
                        <?php foreach ([
                            'reconocido_mec' => __('Programa con Reconocimiento Oficial del Ministerio de Educación y Cultura (MEC)', 'flacso-uruguay'),
                            'reconocimiento_internacional' => __('Apostillado de La Haya', 'flacso-uruguay'),
                            'reconocimiento_red_flacso' => __('Título con validez y reconocimiento en la red del Sistema Internacional FLACSO', 'flacso-uruguay'),
                            'convenio_iin_oea' => __('Convenio IIN/OEA', 'flacso-uruguay'),
                        ] as $key => $label) : ?>
                            <label><input type="checkbox" name="flacso_oferta[<?php echo esc_attr($key); ?>]" value="1" <?php checked(rest_sanitize_boolean(self::meta($post->ID, $key, false))); ?>><span><?php echo esc_html($label); ?><small><?php echo esc_html($flag_descriptions[$key]); ?></small></span></label>
                        <?php endforeach; ?>
                    </div>
                    <p class="flacso-oferta-required-carta-info">
                        <strong><?php esc_html_e('Información incluida siempre en la carta:', 'flacso-uruguay'); ?></strong>
                        <?php esc_html_e('expedición del título y costos de envío. No requieren activación individual.', 'flacso-uruguay'); ?>
                    </p>
                </div>
            </section>

            <section class="flacso-oferta-section" id="flacso-oferta-cohortes">
                <header class="flacso-oferta-section__summary">
                    <span class="flacso-oferta-section__icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                    <span class="flacso-oferta-section__copy"><strong><?php esc_html_e('Cohortes', 'flacso-uruguay'); ?></strong><small><?php esc_html_e('Aperturas temporales de esta oferta: comienzo, estado, cursado, preinscripción y aranceles.', 'flacso-uruguay'); ?></small></span>
                    <span class="flacso-oferta-section__status <?php echo $cohort_ids ? 'is-complete' : ''; ?>"><?php echo esc_html($cohort_ids ? sprintf(_n('%d cohorte', '%d cohortes', count($cohort_ids), 'flacso-uruguay'), count($cohort_ids)) : __('Sin cohortes', 'flacso-uruguay')); ?></span>
                </header>
                <div class="flacso-oferta-section__body">
                    <?php if ($cohort_ids) : ?>
                        <ul class="flacso-oferta-cohort-list">
                            <?php foreach ($cohort_ids as $cohort_id) :
                                $state = FLACSO_Cohorte::sanitize_state(get_post_meta($cohort_id, 'estado', true));
                                $state_labels = [
                                    'planificada' => __('Planificada', 'flacso-uruguay'),
                                    'en_curso' => __('En curso', 'flacso-uruguay'),
                                    'finalizada' => __('Finalizada', 'flacso-uruguay'),
                                    'cancelada' => __('Cancelada', 'flacso-uruguay'),
                                ];
                                $start_label = FLACSO_Cohorte::format_dates((int) $cohort_id);
                                $edit_link = get_edit_post_link($cohort_id);
                            ?>
                                <li>
                                    <span>
                                        <strong><?php echo esc_html(get_the_title($cohort_id)); ?></strong>
                                        <small><?php echo esc_html(($state_labels[$state] ?? $state) . ($start_label !== '' ? ' · ' . $start_label : ' · ' . __('Sin comienzo', 'flacso-uruguay'))); ?></small>
                                    </span>
                                    <?php if ($edit_link) : ?><a class="button button-small" href="<?php echo esc_url($edit_link); ?>"><?php esc_html_e('Editar cohorte', 'flacso-uruguay'); ?></a><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="flacso-oferta-empty"><?php esc_html_e('Esta oferta todavía no tiene cohortes.', 'flacso-uruguay'); ?></p>
                    <?php endif; ?>
                    <p>
                        <a class="button button-primary" href="<?php echo esc_url(add_query_arg([
                            'post_type' => FLACSO_Cohorte::POST_TYPE,
                            'oferta_academica_id' => $post->ID,
                        ], admin_url('post-new.php'))); ?>"><?php esc_html_e('Agregar cohorte', 'flacso-uruguay'); ?></a>
                    </p>
                </div>
            </section>
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
        <section class="flacso-oferta-section" id="flacso-oferta-<?php echo esc_attr($id); ?>">
            <header class="flacso-oferta-section__summary">
                <span class="flacso-oferta-section__icon dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                <span class="flacso-oferta-section__copy"><strong><?php echo esc_html($title); ?></strong><small><?php echo esc_html($description); ?></small></span>
                <span class="flacso-oferta-section__status <?php echo $completed === $total ? 'is-complete' : ''; ?>"><?php echo esc_html(sprintf(__('%1$d/%2$d con contenido', 'flacso-uruguay'), $completed, $total)); ?></span>
            </header>
            <div class="flacso-oferta-section__body">
        <?php
    }

    private static function section_end(): void {
        echo '</div></section>';
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
                'teeny' => false,
                'editor_height' => 240,
                'tinymce' => [
                    'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo,wp_fullscreen',
                    'toolbar2' => 'pastetext,removeformat,charmap,outdent,indent,wp_help',
                    'block_formats' => 'Párrafo=p;Subtítulo=h3;Subtítulo menor=h4',
                    'content_style' => 'body { font: 16px/1.65 system-ui, sans-serif; padding: 16px; max-width: 760px; margin: auto; }',
                ],
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
        <div class="flacso-oferta-field <?php echo $full ? 'flacso-oferta-field--full' : ''; ?>" data-offer-list>
            <strong><?php echo esc_html($label); ?></strong>
            <div data-list-items>
                <?php foreach (array_filter($lines) as $line) : ?>
                    <div class="flacso-list-row"><input type="text" aria-label="<?php echo esc_attr($label); ?>" name="flacso_oferta_lists[<?php echo esc_attr($key); ?>][]" value="<?php echo esc_attr($line); ?>"><button type="button" class="button" data-list-remove aria-label="Eliminar elemento">−</button></div>
                <?php endforeach; ?>
            </div>
            <template><div class="flacso-list-row"><input type="text" aria-label="<?php echo esc_attr($label); ?>" name="flacso_oferta_lists[<?php echo esc_attr($key); ?>][]" value=""><button type="button" class="button" data-list-remove aria-label="Eliminar elemento">−</button></div></template>
            <button type="button" class="button" data-list-add>+ Agregar elemento</button>
        </div>
        <?php
    }

    /**
     * Un título intermedio es siempre otra Oferta de un nivel inferior.
     * Sus menciones son opcionales y pertenecen a la Oferta vinculada.
     */
    private static function intermediate_titles_field(int $post_id): void {
        $raw = self::meta($post_id, 'titulos_intermedios', []);
        $items = FLACSO_Oferta_Academica::sanitize_intermediate_titles($raw, $post_id);
        $offers = get_posts([
            'post_type' => FLACSO_Oferta_Academica::POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        ?>
        <div class="flacso-oferta-intermediate flacso-oferta-field--full" data-intermediate-titles>
            <strong><?php esc_html_e('Títulos intermedios', 'flacso-uruguay'); ?></strong>
            <p class="description"><?php esc_html_e('Vinculá otra Oferta Académica de menor denominación. Jerarquía: Maestría → Especialización → Diplomado → Diploma. Las menciones son opcionales.', 'flacso-uruguay'); ?></p>
            <input type="hidden" name="flacso_oferta_titulos_intermedios_present" value="1">
            <div data-intermediate-list>
                <?php foreach ($items as $index => $item) : ?>
                    <?php self::intermediate_title_row((string) $index, $item, $offers, $post_id); ?>
                <?php endforeach; ?>
            </div>
            <template data-intermediate-template>
                <?php self::intermediate_title_row('__INDEX__', [], $offers, $post_id); ?>
            </template>
            <p class="flacso-oferta-intermediate__notice" data-intermediate-notice aria-live="polite"></p>
            <button type="button" class="button button-secondary" data-intermediate-add><?php esc_html_e('Agregar título intermedio', 'flacso-uruguay'); ?></button>
        </div>
        <?php
    }

    private static function intermediate_title_row(string $index, array $item, array $offers, int $parent_id): void {
        $selected = absint($item['oferta_id'] ?? 0);
        $selected_mentions = FLACSO_Oferta_Academica::sanitize_text_list($item['menciones'] ?? []);
        $available_mentions = $selected ? FLACSO_Oferta_Academica::sanitize_text_list(get_post_meta($selected, 'menciones', true)) : [];
        ?>
        <div class="flacso-oferta-intermediate__row" data-intermediate-row data-index="<?php echo esc_attr($index); ?>">
            <label class="flacso-oferta-field">
                <span><?php esc_html_e('Oferta Académica que otorga el título', 'flacso-uruguay'); ?></span>
                <select data-intermediate-select name="flacso_oferta_titulos_intermedios[<?php echo esc_attr($index); ?>][oferta_id]">
                    <option value=""><?php esc_html_e('— Seleccionar Oferta —', 'flacso-uruguay'); ?></option>
                    <?php foreach ($offers as $offer) :
                        $id = (int) $offer->ID;
                        if ($id === $parent_id) {
                            continue;
                        }
                        $type = FLACSO_Oferta_Academica::get_tipo($id);
                        $rank = FLACSO_Oferta_Academica::intermediate_level($type);
                        $mentions = FLACSO_Oferta_Academica::sanitize_text_list(get_post_meta($id, 'menciones', true));
                        ?>
                        <option value="<?php echo esc_attr((string) $id); ?>"
                            data-level="<?php echo esc_attr((string) $rank); ?>"
                            data-mentions="<?php echo esc_attr(wp_json_encode($mentions)); ?>"
                            <?php selected($selected, $id); ?>>
                            <?php echo esc_html($offer->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="flacso-oferta-intermediate__mentions">
                <strong><?php esc_html_e('Menciones específicas (opcional)', 'flacso-uruguay'); ?></strong>
                <div data-intermediate-mentions>
                    <?php foreach ($available_mentions as $mention) : ?>
                        <label>
                            <input type="checkbox"
                                name="flacso_oferta_titulos_intermedios[<?php echo esc_attr($index); ?>][menciones][]"
                                value="<?php echo esc_attr($mention); ?>"
                                <?php checked(in_array($mention, $selected_mentions, true)); ?>>
                            <?php echo esc_html($mention); ?>
                        </label>
                    <?php endforeach; ?>
                    <?php if (!$available_mentions) : ?>
                        <small><?php esc_html_e('Sin menciones específicas.', 'flacso-uruguay'); ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <button type="button" class="button button-secondary" data-intermediate-remove><?php esc_html_e('Quitar título intermedio', 'flacso-uruguay'); ?></button>
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
            'correo' => 'sanitize_email',
            'presentacion' => 'wp_kses_post',
            'duracion_meses' => [FLACSO_Oferta_Academica::class, 'sanitize_number'],
            'carga_horaria' => [FLACSO_Oferta_Academica::class, 'sanitize_number'],
            'creditos' => [FLACSO_Oferta_Academica::class, 'sanitize_number'],
            'perfil_ingreso_html' => 'wp_kses_post',
            'requisitos_ingreso_html' => 'wp_kses_post',
            'perfil_egreso_html' => 'wp_kses_post',
            'requisitos_egreso_html' => 'wp_kses_post',
            'titulos_certificaciones_html' => 'wp_kses_post',
            'malla_curricular' => 'esc_url_raw',
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

        $raw_abbr = $data['abreviacion'] ?? '';
        $normalized_abbr = FLACSO_Oferta_Academica::normalize_abbreviation((string) $raw_abbr);
        if ($normalized_abbr !== '') {
            if (FLACSO_Oferta_Academica::is_abbreviation_available($normalized_abbr, $post_id)) {
                update_post_meta($post_id, 'abreviacion', $normalized_abbr);
            } else {
                // No sobrescribir con valor colisionado; registrar aviso
                set_transient('flacso_oferta_abbr_error_' . $post_id, sprintf(__('La abreviación "%s" ya está en uso por otra oferta académica.', 'flacso-uruguay'), esc_html($normalized_abbr)), 45);
            }
        } else {
            delete_post_meta($post_id, 'abreviacion');
        }

        foreach (['menciones', 'orientaciones'] as $key) {
            $raw = $lists[$key] ?? [];
            $items = is_array($raw) ? $raw : (preg_split('/\R/u', (string) $raw) ?: []);
            $items = array_values(array_filter(array_map('sanitize_text_field', $items), static fn(string $item): bool => $item !== ''));
            $items = FLACSO_Oferta_Academica::sanitize_text_list($items);
            $items ? update_post_meta($post_id, $key, $items) : delete_post_meta($post_id, $key);
        }

        if (isset($_POST['flacso_oferta_tipo'])) {
            $type = sanitize_key(wp_unslash($_POST['flacso_oferta_tipo']));
            if ($type === '') {
                wp_set_object_terms($post_id, [], FLACSO_Oferta_Academica::TYPE_TAXONOMY);
            } elseif (FLACSO_Oferta_Academica::tipo_valido($type)) {
                wp_set_object_terms($post_id, $type, FLACSO_Oferta_Academica::TYPE_TAXONOMY);
            }
        }

        // El nuevo tipo ya quedó actualizado: validar destinos contra su nivel real.
        // Sin campo enviado (p. ej. integraciones previas), no borrar la relación.
        if (isset($_POST['flacso_oferta_titulos_intermedios']) || isset($_POST['flacso_oferta_titulos_intermedios_present'])) {
            $raw_titles = isset($_POST['flacso_oferta_titulos_intermedios']) && is_array($_POST['flacso_oferta_titulos_intermedios'])
                ? wp_unslash($_POST['flacso_oferta_titulos_intermedios'])
                : [];
            $intermediate = FLACSO_Oferta_Academica::sanitize_intermediate_titles($raw_titles, $post_id);
            $intermediate
                ? update_post_meta($post_id, 'titulos_intermedios', $intermediate)
                : delete_post_meta($post_id, 'titulos_intermedios');
        }

        if (isset($_POST['flacso_oferta_color_principal'])) {
            $colors = FLACSO_Oferta_Academica::sanitize_presentation_colors([
                'principal' => sanitize_text_field(wp_unslash($_POST['flacso_oferta_color_principal'])),
                'secundarios' => [],
            ]);
            if ($colors) {
                update_post_meta($post_id, FLACSO_Oferta_Academica::META_PRESENTATION_COLORS, $colors);
            } else {
                delete_post_meta($post_id, FLACSO_Oferta_Academica::META_PRESENTATION_COLORS);
            }
        }

        foreach (['reconocido_mec', 'reconocimiento_internacional', 'reconocimiento_red_flacso', 'convenio_iin_oea'] as $key) {
            update_post_meta($post_id, $key, !empty($data[$key]));
        }

        // Compatibilidad con consumidores anteriores: ambos bloques son obligatorios.
        // No depender de las antiguas casillas, ausentes desde este editor.
        update_post_meta($post_id, 'mostrar_costos_envio', true);
        update_post_meta($post_id, 'mostrar_expedicion_titulo', true);
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
            .flacso-oferta-section__summary { display:flex; align-items:center; gap:12px; padding:16px 18px; cursor:default; }
            .flacso-oferta-section__icon { display:grid; width:36px; height:36px; flex:0 0 36px; place-items:center; border-radius:8px; color:#1d4ed8; background:#eff6ff; font-size:20px; line-height:36px; }
            .flacso-oferta-section__copy { display:grid; flex:1; gap:3px; }
            .flacso-oferta-section__copy strong { font-size:14px; }
            .flacso-oferta-section__copy small { color:#646970; font-size:12px; font-weight:400; }
            .flacso-oferta-section__status { padding:4px 8px; border-radius:999px; color:#8a4b00; background:#fcf0c9; font-size:11px; font-weight:700; white-space:nowrap; }
            .flacso-oferta-section__status.is-complete { color:#116329; background:#edfaef; }
            .flacso-oferta-section__body { padding:4px 18px 20px; border-top:1px solid #f0f0f1; }
            .flacso-oferta-required-carta-info { margin:16px 0 0; padding:12px 14px; color:#31445d; background:#f2f6fc; border-left:3px solid #2c4777; border-radius:4px; line-height:1.5; }
            .flacso-oferta-required-carta-info strong { display:block; margin-bottom:3px; }
            .flacso-oferta-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-top:16px; }
            .flacso-oferta-grid--three { grid-template-columns:repeat(3,minmax(0,1fr)); }
            .flacso-oferta-field { display:grid; gap:6px; }
            .flacso-oferta-field > span { font-weight:600; }
            .flacso-oferta-field small { color:#646970; line-height:1.4; }
            .flacso-oferta-field input,.flacso-oferta-field select,.flacso-oferta-field textarea { width:100%; max-width:none; }
            .flacso-oferta-field input,.flacso-oferta-field select { min-height:38px; }
            .flacso-oferta-field--full { grid-column:1/-1; }
            .flacso-list-row { display:flex; gap:8px; margin:8px 0; }
            .flacso-oferta-checks small { display:block; margin-top:5px; color:#646970; }
            .flacso-oferta-intermediate{display:grid;gap:10px;min-width:0;margin-top:16px}
            .flacso-oferta-intermediate>strong{font-size:14px}
            .flacso-oferta-intermediate>.description{margin:0;color:#526174}
            .flacso-oferta-intermediate [data-intermediate-list]{display:grid;gap:12px;min-width:0}
            .flacso-oferta-intermediate__row{display:grid;gap:12px;min-width:0;padding:14px;border:1px solid #d6e0ec;border-radius:9px;background:#f8fafd}
            .flacso-oferta-intermediate__row .flacso-oferta-field{min-width:0}
            .flacso-oferta-intermediate__row select{width:100%;min-width:0;max-width:100%}
            .flacso-oferta-intermediate__mentions{display:grid;gap:8px}
            .flacso-oferta-intermediate__mentions>div{display:flex;flex-wrap:wrap;gap:8px}
            .flacso-oferta-intermediate__mentions label{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border:1px solid #d6e0ec;border-radius:6px;background:#fff}
            .flacso-oferta-intermediate__mentions small,.flacso-oferta-intermediate__notice{color:#64748b}
            .flacso-oferta-intermediate button{max-width:100%;height:auto;min-height:38px;white-space:normal}
            @media(max-width:782px){.flacso-oferta-intermediate__row{padding:12px}.flacso-oferta-intermediate button{width:100%}}
            .flacso-oferta-editor-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; }
            .flacso-oferta-editor { min-width:0; margin-top:18px; }
            .flacso-oferta-editor-grid .flacso-oferta-editor { margin-top:16px; }
            .flacso-oferta-editor > label { display:block; margin-bottom:6px; }
            .flacso-oferta-editor > .description { margin:0 0 8px; }
            .flacso-oferta-editor .wp-editor-wrap { border-radius:4px; overflow:hidden; }
            .flacso-oferta-checks { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-top:16px; }
            .flacso-oferta-checks label { display:flex; align-items:flex-start; gap:8px; padding:10px 12px; border:1px solid #dcdcde; border-radius:6px; background:#fafafa; }
            .flacso-oferta-checks input { margin-top:1px; }
            .flacso-oferta-public-link { margin-top:10px!important; }
            .flacso-oferta-public-link a { display:inline-flex;align-items:center;gap:5px;color:#fff;text-decoration:none;font-weight:600; }
            .flacso-oferta-public-link .dashicons { width:16px;height:16px;font-size:16px; }
            .flacso-oferta-color-control { display:grid;grid-template-columns:50px minmax(140px,220px) auto auto;gap:8px;align-items:center; }
            .flacso-oferta-color-control input[type=color] { width:50px;min-height:38px;padding:2px; }
            .flacso-oferta-color-preview { display:inline-flex;min-height:34px;align-items:center;padding:0 10px;border:1px solid #c3c4c7;border-radius:4px;font-size:12px;font-weight:700;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.55); }
            .flacso-oferta-color-preview.is-empty { color:#646970;background:#f6f7f7!important;text-shadow:none; }
            .flacso-oferta-cohort-list { display:grid;gap:8px;margin:16px 0 0; }
            .flacso-oferta-cohort-list li { display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0;padding:10px 12px;border:1px solid #dcdcde;border-radius:6px;background:#fafafa; }
            .flacso-oferta-cohort-list li>span { display:grid;gap:3px; }
            .flacso-oferta-cohort-list small { color:#646970; }
            .flacso-oferta-empty { padding:12px;border:1px dashed #c3c4c7;border-radius:6px;color:#646970;background:#f6f7f7; }
            @media(max-width:1100px){ .flacso-oferta-workspace{flex-direction:column;} .flacso-oferta-workspace__aside{min-width:0;align-items:flex-start;} .flacso-oferta-pills{justify-content:flex-start;} .flacso-oferta-grid--three,.flacso-oferta-editor-grid{grid-template-columns:1fr;} }
            @media(max-width:782px){ .flacso-oferta-color-control{grid-template-columns:50px 1fr;} .flacso-oferta-color-preview,.flacso-oferta-color-control .button{grid-column:1/-1;} .flacso-oferta-cohort-list li{align-items:flex-start;flex-direction:column;} .flacso-oferta-fields{padding:12px;} .flacso-oferta-workspace{padding:18px;} .flacso-oferta-grid,.flacso-oferta-checks{grid-template-columns:1fr;} .flacso-oferta-field--full{grid-column:auto;} .flacso-oferta-section__summary{align-items:flex-start;padding:14px;} .flacso-oferta-section__copy small{display:none;} .flacso-oferta-section__status{margin-left:auto;} .flacso-oferta-section__body{padding:2px 14px 16px;} }
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
            root.addEventListener('click', function (event) {
                var add = event.target.closest('[data-list-add]');
                var remove = event.target.closest('[data-list-remove]');
                if (add) {
                    var list = add.closest('[data-offer-list]');
                    var items = list.querySelector('[data-list-items]');
                    items.appendChild(list.querySelector('template').content.cloneNode(true));
                    items.lastElementChild.querySelector('input').focus();
                }
                if (remove) {
                    var list = remove.closest('[data-offer-list]');
                    remove.closest('.flacso-list-row').remove();
                    list.querySelector('[data-list-add]').focus();
                }
            });

            var intermediate = root.querySelector('[data-intermediate-titles]');
            if (intermediate) {
                var titleList = intermediate.querySelector('[data-intermediate-list]');
                var titleTemplate = intermediate.querySelector('[data-intermediate-template]');
                var titleAdd = intermediate.querySelector('[data-intermediate-add]');
                var titleNotice = intermediate.querySelector('[data-intermediate-notice]');
                var typeSelector = root.querySelector('select[name="flacso_oferta_tipo"]');
                var levels = {maestria:4, especializacion:3, diplomado:2, diploma:1};
                var nextTitleIndex = titleList.querySelectorAll('[data-intermediate-row]').length;

                function fillMentions(row) {
                    var target = row.querySelector('[data-intermediate-mentions]');
                    var select = row.querySelector('[data-intermediate-select]');
                    var option = select.options[select.selectedIndex];
                    var values = [];
                    if (option && option.dataset.mentions) {
                        try { values = JSON.parse(option.dataset.mentions); } catch (_) { values = []; }
                    }
                    target.replaceChildren();
                    if (!Array.isArray(values) || !values.length) {
                        var empty = document.createElement('small');
                        empty.textContent = 'Sin menciones específicas.';
                        target.appendChild(empty);
                        return;
                    }
                    values.forEach(function (value) {
                        var label = document.createElement('label');
                        var input = document.createElement('input');
                        input.type = 'checkbox';
                        input.name = 'flacso_oferta_titulos_intermedios[' + row.dataset.index + '][menciones][]';
                        input.value = String(value);
                        label.appendChild(input);
                        label.appendChild(document.createTextNode(String(value)));
                        target.appendChild(label);
                    });
                }

                function refreshTitleOptions() {
                    var current = levels[typeSelector && typeSelector.value] || 0;
                    var rows = Array.from(titleList.querySelectorAll('[data-intermediate-row]'));
                    var selected = rows.map(function(row){return row.querySelector('[data-intermediate-select]').value;}).filter(Boolean);
                    rows.forEach(function (row) {
                        var select = row.querySelector('[data-intermediate-select]');
                        var changed = false;
                        Array.from(select.options).forEach(function (option) {
                            if (!option.value) return;
                            var level = Number(option.dataset.level) || 0;
                            var available = level > 0 && level < current &&
                                (!selected.includes(option.value) || select.value === option.value);
                            option.disabled = !available;
                            option.hidden = !available;
                            if (!available && select.value === option.value) changed = true;
                        });
                        if (changed) {
                            select.value = '';
                            fillMentions(row);
                        }
                    });
                    var possible = Array.from((rows[0] || titleTemplate.content).querySelectorAll('option[data-level]'))
                        .some(function(opt){return Number(opt.dataset.level) > 0 && Number(opt.dataset.level) < current && !selected.includes(opt.value);});
                    titleAdd.disabled = !possible;
                    titleNotice.textContent = current <= 1
                        ? 'Solo Maestría, Especialización y Diplomado pueden otorgar títulos intermedios dentro de esta jerarquía.'
                        : (!possible ? 'No quedan ofertas de menor nivel disponibles.' : '');
                }

                intermediate.addEventListener('click', function(event){
                    if (event.target.closest('[data-intermediate-add]')) {
                        titleList.insertAdjacentHTML('beforeend',titleTemplate.innerHTML.split('__INDEX__').join(String(nextTitleIndex++)));
                        var newRow = titleList.lastElementChild;
                        refreshTitleOptions();
                        newRow.querySelector('select').focus();
                    }
                    if (event.target.closest('[data-intermediate-remove]')) {
                        event.target.closest('[data-intermediate-row]').remove();
                        refreshTitleOptions();
                    }
                });
                intermediate.addEventListener('change', function(event){
                    if (event.target.matches('[data-intermediate-select]')) {
                        fillMentions(event.target.closest('[data-intermediate-row]'));
                        refreshTitleOptions();
                    }
                });
                if (typeSelector) typeSelector.addEventListener('change',refreshTitleOptions);
                refreshTitleOptions();
            }

            var colorPicker = document.getElementById('flacso_oferta_color_picker');
            var colorText = document.getElementById('flacso_oferta_color_principal');
            var colorPreview = root.querySelector('[data-color-preview]');
            var clearColor = root.querySelector('[data-clear-offer-color]');
            var validColor = /^#[0-9a-fA-F]{6}$/;

            function renderColor(value) {
                var valid = validColor.test(value);
                if (colorPreview) {
                    colorPreview.classList.toggle('is-empty', !valid);
                    colorPreview.style.background = valid ? value : '';
                    colorPreview.textContent = valid ? value.toLowerCase() : '<?php echo esc_js(__('Sin completar', 'flacso-uruguay')); ?>';
                }
                if (valid && colorPicker && colorPicker.value.toLowerCase() !== value.toLowerCase()) {
                    colorPicker.value = value;
                }
            }

            colorPicker?.addEventListener('input', function () {
                colorText.value = colorPicker.value.toLowerCase();
                renderColor(colorText.value);
            });
            colorText?.addEventListener('input', function () {
                renderColor(colorText.value.trim());
            });
            clearColor?.addEventListener('click', function () {
                colorText.value = '';
                renderColor('');
                colorText.focus();
            });
            if (colorText) renderColor(colorText.value.trim());
        })();
        </script>
        <?php
    }
}
