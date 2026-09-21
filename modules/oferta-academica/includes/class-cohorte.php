<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Ocurrencia temporal de una OfertaAcademica (Entidad débil subordinada). */
final class FLACSO_Cohorte {
    public const POST_TYPE = 'cohorte';
    public const META_PARENT_ID = 'oferta_academica_id';
    public const ESTADOS = ['planificada', 'en_curso', 'finalizada', 'cancelada'];

    public static function register(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'          => __('Cohortes', 'flacso-uruguay'),
                'singular_name' => __('Cohorte', 'flacso-uruguay'),
                'add_new_item'  => __('Agregar cohorte', 'flacso-uruguay'),
                'edit_item'     => __('Editar cohorte', 'flacso-uruguay'),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => false, // Entidad débil: gestionada dentro de Ofertas Académicas
            'show_in_rest' => false,
            'supports'     => ['revisions'],
            'rewrite'      => false,
            'query_var'    => false,
            'map_meta_cap' => true,
        ]);

        $definitions = [
            self::META_PARENT_ID     => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'numero'                 => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'fecha_inicio'           => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_date']],
            'fecha_fin'              => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_date']],
            'anio_inicio'            => ['type' => 'integer', 'sanitize_callback' => [self::class, 'sanitize_year']],
            'anio_fin'               => ['type' => 'integer', 'sanitize_callback' => [self::class, 'sanitize_year']],
            'precision_fecha_inicio' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_precision']],
            'estado'                 => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_state']],
            'calendario_academico'   => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'calendario_descripcion' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'modalidad'              => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_modality']],
            'modalidad_descripcion'  => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'tabla_precio_id'        => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'link_preinscripcion'    => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_registration_url']],
            'preinscripcion_desde'   => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_datetime']],
            'preinscripcion_hasta'   => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_datetime']],
            'preinscripcion_habilitada' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
            'mensaje_preinscripcion_abierta' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'mensaje_preinscripcion_cerrada' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'presentacion_preinscripcion' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'etiqueta_preinscripcion' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'cta_preinscripcion' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'instancias_presenciales' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
        ];
        foreach ($definitions as $key => $definition) {
            register_post_meta(self::POST_TYPE, $key, array_merge([
                'single'        => true,
                'show_in_rest'  => false,
                'auth_callback' => static function (): bool { return current_user_can('edit_posts'); },
            ], $definition));
        }

        add_action('added_post_meta', [self::class, 'on_meta_change'], 10, 4);
        add_action('updated_post_meta', [self::class, 'on_meta_change'], 10, 4);
        add_action('save_post_' . self::POST_TYPE, [self::class, 'save_post_data'], 10, 2);

        // UI Administrativa contextual
        if (is_admin()) {
            add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
            add_filter('manage_' . self::POST_TYPE . '_posts_columns', [self::class, 'register_columns']);
            add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [self::class, 'render_column'], 10, 2);
            add_action('restrict_manage_posts', [self::class, 'render_admin_filters']);
            add_filter('parse_query', [self::class, 'filter_query_by_parent']);
            add_action('admin_footer-post.php', [self::class, 'render_start_date_preview_script']);
            add_action('admin_footer-post-new.php', [self::class, 'render_start_date_preview_script']);
        }
    }

    public static function sanitize_state($value): string {
        $state = sanitize_key((string) $value);
        return in_array($state, self::ESTADOS, true) ? $state : 'planificada';
    }

    public static function sanitize_precision($value): string {
        $precision = sanitize_key((string) $value);
        return in_array($precision, ['dia', 'mes', 'anio'], true) ? $precision : 'dia';
    }

    public static function sanitize_modality($value): string {
        $modality = sanitize_key((string) $value);
        return in_array($modality, ['virtual', 'presencial', 'semipresencial', 'hibrida'], true) ? $modality : '';
    }

    public static function sanitize_year($value): int {
        $year = absint($value);
        return $year >= 2000 && $year <= 2100 ? $year : 0;
    }

    public static function sanitize_date($value): string {
        $value = sanitize_text_field((string) $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    public static function sanitize_datetime($value): string {
        $value = sanitize_text_field((string) $value);
        return $value !== '' && strtotime($value) !== false ? $value : '';
    }

    public static function sanitize_registration_url($value): string {
        $url = esc_url_raw((string) $value, ['https']);
        return wp_parse_url($url, PHP_URL_HOST) === 'preinscripciones.flacso.edu.uy' ? $url : '';
    }

    public static function accepts_registration(int $cohort_id, ?int $timestamp = null): bool {
        if (metadata_exists('post', $cohort_id, 'preinscripcion_habilitada')) {
            return rest_sanitize_boolean(
                get_post_meta($cohort_id, 'preinscripcion_habilitada', true)
            );
        }

        // Compatibilidad de rollout: hasta que un administrador confirme Abrir
        // o Cerrar, conservar la decisión legacy para no cortar inscripciones.
        $state = self::sanitize_state(get_post_meta($cohort_id, 'estado', true));
        if (in_array($state, ['cancelada', 'finalizada'], true)) {
            return false;
        }
        $offer_id = absint(get_post_meta($cohort_id, self::META_PARENT_ID, true));
        if ($offer_id && metadata_exists('post', $offer_id, 'inscripciones_abiertas')) {
            if (!rest_sanitize_boolean(get_post_meta($offer_id, 'inscripciones_abiertas', true))) {
                return false;
            }
        }
        $timestamp = $timestamp ?? current_time('timestamp', true);
        $from = strtotime((string) get_post_meta($cohort_id, 'preinscripcion_desde', true));
        $until = strtotime((string) get_post_meta($cohort_id, 'preinscripcion_hasta', true));
        return (!$from || $timestamp >= $from) && (!$until || $timestamp <= $until);
    }

    public static function to_roman(int $number): string {
        if ($number < 1) {
            return '';
        }
        $table = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
        ];
        $result = '';
        foreach ($table as $value => $symbol) {
            while ($number >= $value) {
                $result .= $symbol;
                $number -= $value;
            }
        }
        return $result;
    }

    public static function display_name(int $number): string {
        $roman = self::to_roman($number);
        return $roman !== '' ? sprintf('Cohorte %s', $roman) : 'Cohorte';
    }

    public static function format_dates(int $post_id): string {
        $precision = sanitize_key((string) get_post_meta($post_id, 'precision_fecha_inicio', true)) ?: 'dia';
        $fecha_inicio = (string) get_post_meta($post_id, 'fecha_inicio', true);
        $anio_inicio = absint(get_post_meta($post_id, 'anio_inicio', true));

        $y_start = $anio_inicio > 0 ? $anio_inicio : ($fecha_inicio !== '' ? (int) substr($fecha_inicio, 0, 4) : 0);

        if ($precision === 'anio' || ($fecha_inicio === '' && $y_start > 0)) {
            if ($y_start > 0) {
                return (string) $y_start;
            }
            return '';
        }

        if ($precision === 'mes' && $fecha_inicio !== '') {
            try {
                $dt = new \DateTime($fecha_inicio, wp_timezone());
                $m_start = function_exists('wp_date') ? wp_date('F Y', $dt->getTimestamp()) : date('m/Y', $dt->getTimestamp());
                return $m_start;
            } catch (\Exception $e) {
                // silencioso
            }
        }

        if ($fecha_inicio !== '') {
            try {
                $dt = new \DateTime($fecha_inicio, wp_timezone());
                $f_start = function_exists('wp_date') ? wp_date('j \d\e F \d\e Y', $dt->getTimestamp()) : date('d/m/Y', $dt->getTimestamp());
                return $f_start;
            } catch (\Exception $e) {
                // silencioso
            }
        }

        return '';
    }

    public static function sync_title(int $post_id): void {
        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }
        $number = absint(get_post_meta($post_id, 'numero', true));
        $parent_id = absint(get_post_meta($post_id, self::META_PARENT_ID, true));

        $cohort_name = self::display_name($number);
        if ($parent_id > 0) {
            $parent_title = get_the_title($parent_id);
            $full_title = $parent_title ? sprintf('%s — %s', $parent_title, $cohort_name) : $cohort_name;
        } else {
            $full_title = $cohort_name;
        }

        if ($full_title !== '' && get_the_title($post_id) !== $full_title) {
            wp_update_post(['ID' => $post_id, 'post_title' => $full_title]);
        }
    }

    public static function on_meta_change($meta_id, $post_id, $meta_key, $meta_value): void {
        if (($meta_key === 'numero' || $meta_key === self::META_PARENT_ID) && get_post_type($post_id) === self::POST_TYPE) {
            self::sync_title((int) $post_id);
        }
    }

    public static function add_meta_boxes(): void {
        add_meta_box(
            'flacso_cohorte_meta',
            __('Configuración de la Cohorte', 'flacso-uruguay'),
            [self::class, 'render_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function render_meta_box($post): void {
        $parent_id = absint(get_post_meta($post->ID, self::META_PARENT_ID, true));
        if ($parent_id === 0 && isset($_GET['oferta_academica_id'])) {
            $parent_id = absint($_GET['oferta_academica_id']);
        }

        $numero = absint(get_post_meta($post->ID, 'numero', true));
        $has_state = metadata_exists('post', $post->ID, 'estado');
        $estado = $has_state ? self::sanitize_state(get_post_meta($post->ID, 'estado', true)) : '';
        $fecha_inicio = (string) get_post_meta($post->ID, 'fecha_inicio', true);
        $anio_inicio = absint(get_post_meta($post->ID, 'anio_inicio', true));
        if ($anio_inicio === 0 && $fecha_inicio !== '') {
            $anio_inicio = (int) substr($fecha_inicio, 0, 4);
        }

        $has_precision = metadata_exists('post', $post->ID, 'precision_fecha_inicio');
        $precision = $has_precision ? (string) get_post_meta($post->ID, 'precision_fecha_inicio', true) : '';
        $tabla_precio_id = absint(get_post_meta($post->ID, 'tabla_precio_id', true));
        $link_preinscripcion = (string) get_post_meta($post->ID, 'link_preinscripcion', true);
        $pre_desde = (string) get_post_meta($post->ID, 'preinscripcion_desde', true);
        $pre_hasta = (string) get_post_meta($post->ID, 'preinscripcion_hasta', true);
        $pre_habilitada = metadata_exists('post', $post->ID, 'preinscripcion_habilitada')
            ? rest_sanitize_boolean(get_post_meta($post->ID, 'preinscripcion_habilitada', true))
            : false;
        $pre_configurada = metadata_exists('post', $post->ID, 'preinscripcion_habilitada')
            || $link_preinscripcion !== '';

        $modalidad = self::sanitize_modality(get_post_meta($post->ID, 'modalidad', true));
        $modalidad_descripcion = (string) get_post_meta($post->ID, 'modalidad_descripcion', true);
        $calendario_academico = (string) get_post_meta($post->ID, 'calendario_academico', true);
        $calendario_descripcion = (string) get_post_meta($post->ID, 'calendario_descripcion', true);
        $mensaje_abierta = (string) get_post_meta($post->ID, 'mensaje_preinscripcion_abierta', true);
        $mensaje_cerrada = (string) get_post_meta($post->ID, 'mensaje_preinscripcion_cerrada', true);
        $presentacion_preinscripcion = (string) get_post_meta($post->ID, 'presentacion_preinscripcion', true);
        $etiqueta_preinscripcion = (string) get_post_meta($post->ID, 'etiqueta_preinscripcion', true);
        $cta_preinscripcion = (string) get_post_meta($post->ID, 'cta_preinscripcion', true);
        $instancias_presenciales = metadata_exists('post', $post->ID, 'instancias_presenciales')
            ? rest_sanitize_boolean(get_post_meta($post->ID, 'instancias_presenciales', true))
            : false;

        $ofertas = get_posts([
            'post_type' => FLACSO_Oferta_Academica::POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $tablas = get_posts([
            'post_type' => 'tabla-precio',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $parent_title = $parent_id > 0 ? get_the_title($parent_id) : '';
        $table_title = $tabla_precio_id > 0 ? get_the_title($tabla_precio_id) : '';
        $state_labels = [
            'planificada' => __('Planificada', 'flacso-uruguay'),
            'en_curso' => __('En curso', 'flacso-uruguay'),
            'finalizada' => __('Finalizada', 'flacso-uruguay'),
            'cancelada' => __('Cancelada', 'flacso-uruguay'),
        ];
        $start_preview = ($fecha_inicio !== '' || $anio_inicio > 0) ? self::format_dates((int) $post->ID) : '';

        wp_nonce_field('save_cohorte_meta', 'cohorte_nonce');
        ?>
        <div class="flacso-academic-editor flacso-cohort-editor">
            <?php FLACSO_Academic_Admin_UI::render_header(
                $post,
                __('Cohorte', 'flacso-uruguay'),
                get_the_title($post) !== '' ? get_the_title($post) : __('Nueva cohorte', 'flacso-uruguay'),
                __('Concentra los datos temporales de una oferta: comienzo, estado, cursado, preinscripción y la tabla de aranceles elegida.', 'flacso-uruguay'),
                [
                    ['icon' => 'dashicons-welcome-learn-more', 'label' => $parent_title !== '' ? $parent_title : __('Sin oferta padre', 'flacso-uruguay')],
                    ['icon' => 'dashicons-flag', 'label' => $estado !== '' ? ($state_labels[$estado] ?? $estado) : __('Sin estado', 'flacso-uruguay')],
                    ['icon' => 'dashicons-calendar-alt', 'label' => $start_preview !== '' ? $start_preview : __('Sin comienzo', 'flacso-uruguay')],
                ],
                false
            ); ?>

            <?php FLACSO_Academic_Admin_UI::section_start(
                'flacso-cohorte-oferta-padre',
                __('Oferta padre', 'flacso-uruguay'),
                __('Seleccioná la Oferta Académica a la que pertenece esta cohorte.', 'flacso-uruguay'),
                'dashicons-welcome-learn-more',
                $parent_title !== '' ? $parent_title : __('Sin completar', 'flacso-uruguay'),
                true
            ); ?>
                <label class="flacso-academic-field">
                    <span><?php esc_html_e('Oferta Académica', 'flacso-uruguay'); ?></span>
                    <select name="oferta_academica_id">
                        <option value=""><?php esc_html_e('— Sin completar —', 'flacso-uruguay'); ?></option>
                        <?php foreach ($ofertas as $oferta) : ?>
                            <option value="<?php echo esc_attr((string) $oferta->ID); ?>" <?php selected($parent_id, $oferta->ID); ?>><?php echo esc_html($oferta->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($parent_id > 0) : ?>
                    <p class="flacso-academic-actions">
                        <?php $public_parent = get_permalink($parent_id); ?>
                        <?php $edit_parent = get_edit_post_link($parent_id); ?>
                        <?php if ($public_parent) : ?><a class="button" href="<?php echo esc_url($public_parent); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver página pública de la Oferta', 'flacso-uruguay'); ?></a><?php endif; ?>
                        <?php if ($edit_parent) : ?><a class="button" href="<?php echo esc_url($edit_parent); ?>"><?php esc_html_e('Editar Oferta padre', 'flacso-uruguay'); ?></a><?php endif; ?>
                    </p>
                <?php else : ?>
                    <?php FLACSO_Academic_Admin_UI::render_empty(__('No hay una Oferta padre seleccionada. La cohorte sigue siendo editable.', 'flacso-uruguay')); ?>
                <?php endif; ?>
            <?php FLACSO_Academic_Admin_UI::section_end(); ?>

            <?php
            $state_price_summary = [];
            if ($estado !== '') {
                $state_price_summary[] = $state_labels[$estado] ?? $estado;
            }
            if ($table_title !== '') {
                $state_price_summary[] = $table_title;
            }
            FLACSO_Academic_Admin_UI::section_start(
                'flacso-cohorte-estado-aranceles',
                __('Estado y aranceles', 'flacso-uruguay'),
                __('Estado académico de la cohorte y Tabla de Aranceles reutilizable asignada.', 'flacso-uruguay'),
                'dashicons-money-alt',
                $state_price_summary ? implode(' · ', $state_price_summary) : __('Sin completar', 'flacso-uruguay')
            );
            ?>
                <div class="flacso-academic-grid flacso-academic-grid--three">
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Número de cohorte', 'flacso-uruguay'); ?></span>
                        <input type="number" id="cohorte_numero" name="numero_cohorte" value="<?php echo $numero > 0 ? esc_attr((string) $numero) : ''; ?>" min="1" max="999" autocomplete="off" data-lpignore="true" data-1p-ignore="true" data-form-type="other" placeholder="<?php esc_attr_e('Sin completar', 'flacso-uruguay'); ?>">
                        <small><?php esc_html_e('Se convierte a romano automáticamente en el nombre de la cohorte.', 'flacso-uruguay'); ?></small>
                    </label>
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Estado académico', 'flacso-uruguay'); ?></span>
                        <select name="estado">
                            <option value=""><?php esc_html_e('— Sin completar —', 'flacso-uruguay'); ?></option>
                            <option value="planificada" <?php selected($estado, 'planificada'); ?>><?php esc_html_e('Planificada', 'flacso-uruguay'); ?></option>
                            <option value="en_curso" <?php selected($estado, 'en_curso'); ?>><?php esc_html_e('En curso', 'flacso-uruguay'); ?></option>
                            <option value="finalizada" <?php selected($estado, 'finalizada'); ?>><?php esc_html_e('Finalizada', 'flacso-uruguay'); ?></option>
                            <option value="cancelada" <?php selected($estado, 'cancelada'); ?>><?php esc_html_e('Cancelada', 'flacso-uruguay'); ?></option>
                        </select>
                    </label>
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Tabla de Aranceles', 'flacso-uruguay'); ?></span>
                        <select name="tabla_precio_id">
                            <option value="0"><?php esc_html_e('— Sin completar —', 'flacso-uruguay'); ?></option>
                            <?php foreach ($tablas as $tabla) : ?>
                                <option value="<?php echo esc_attr((string) $tabla->ID); ?>" <?php selected($tabla_precio_id, $tabla->ID); ?>><?php echo esc_html($tabla->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($tabla_precio_id > 0 && get_edit_post_link($tabla_precio_id)) : ?>
                            <small><a href="<?php echo esc_url(get_edit_post_link($tabla_precio_id)); ?>"><?php esc_html_e('Editar la tabla asignada', 'flacso-uruguay'); ?></a></small>
                        <?php endif; ?>
                    </label>
                </div>
            <?php FLACSO_Academic_Admin_UI::section_end(); ?>

            <?php FLACSO_Academic_Admin_UI::section_start(
                'flacso-cohorte-comienzo',
                __('Comienzo', 'flacso-uruguay'),
                __('Definí la precisión pública del comienzo. No se solicita ni muestra una fecha de fin.', 'flacso-uruguay'),
                'dashicons-calendar-alt',
                $start_preview !== '' ? $start_preview : __('Sin completar', 'flacso-uruguay')
            ); ?>
                <div class="flacso-academic-grid flacso-academic-grid--three">
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Precisión de visualización', 'flacso-uruguay'); ?></span>
                        <select name="precision_fecha_inicio" id="precision_fecha_inicio">
                            <option value=""><?php esc_html_e('— Sin completar —', 'flacso-uruguay'); ?></option>
                            <option value="dia" <?php selected($precision, 'dia'); ?>><?php esc_html_e('Día exacto', 'flacso-uruguay'); ?></option>
                            <option value="mes" <?php selected($precision, 'mes'); ?>><?php esc_html_e('Mes y año', 'flacso-uruguay'); ?></option>
                            <option value="anio" <?php selected($precision, 'anio'); ?>><?php esc_html_e('Solo año', 'flacso-uruguay'); ?></option>
                        </select>
                    </label>
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Año de comienzo', 'flacso-uruguay'); ?></span>
                        <input type="number" name="anio_inicio" id="anio_inicio" value="<?php echo $anio_inicio > 0 ? esc_attr((string) $anio_inicio) : ''; ?>" min="2000" max="2100" placeholder="<?php esc_attr_e('Sin completar', 'flacso-uruguay'); ?>" autocomplete="off" data-lpignore="true" data-1p-ignore="true" data-form-type="other">
                    </label>
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Fecha exacta de comienzo', 'flacso-uruguay'); ?></span>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?php echo esc_attr($fecha_inicio); ?>">
                        <small><?php esc_html_e('Puede quedar vacía si solo se conoce el año.', 'flacso-uruguay'); ?></small>
                    </label>
                </div>
                <p id="flacso-cohorte-start-preview" class="flacso-academic-preview" aria-live="polite">
                    <strong><?php esc_html_e('Vista previa pública:', 'flacso-uruguay'); ?></strong><span></span>
                </p>
            <?php FLACSO_Academic_Admin_UI::section_end(); ?>

            <?php
            $course_summary = $modalidad !== '' ? ucfirst($modalidad) : '';
            FLACSO_Academic_Admin_UI::section_start(
                'flacso-cohorte-cursado',
                __('Cursado', 'flacso-uruguay'),
                __('Modalidad, instancias presenciales y calendario propio de esta cohorte.', 'flacso-uruguay'),
                'dashicons-clock',
                $course_summary !== '' ? $course_summary : __('Sin completar', 'flacso-uruguay')
            );
            ?>
                <div class="flacso-academic-grid">
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Modalidad', 'flacso-uruguay'); ?></span>
                        <select name="modalidad">
                            <option value=""><?php esc_html_e('— Sin completar —', 'flacso-uruguay'); ?></option>
                            <option value="virtual" <?php selected($modalidad, 'virtual'); ?>><?php esc_html_e('Virtual', 'flacso-uruguay'); ?></option>
                            <option value="presencial" <?php selected($modalidad, 'presencial'); ?>><?php esc_html_e('Presencial', 'flacso-uruguay'); ?></option>
                            <option value="semipresencial" <?php selected($modalidad, 'semipresencial'); ?>><?php esc_html_e('Semipresencial', 'flacso-uruguay'); ?></option>
                            <option value="hibrida" <?php selected($modalidad, 'hibrida'); ?>><?php esc_html_e('Híbrida', 'flacso-uruguay'); ?></option>
                        </select>
                    </label>
                    <label class="flacso-cohort-checkbox">
                        <input type="hidden" name="instancias_presenciales" value="0">
                        <input type="checkbox" name="instancias_presenciales" value="1" <?php checked($instancias_presenciales); ?>>
                        <span><strong><?php esc_html_e('Tiene instancias presenciales', 'flacso-uruguay'); ?></strong><small><?php esc_html_e('Marcá esta opción cuando el cursado incluya encuentros presenciales.', 'flacso-uruguay'); ?></small></span>
                    </label>
                    <label class="flacso-academic-field flacso-academic-field--full">
                        <span><?php esc_html_e('Descripción de modalidad', 'flacso-uruguay'); ?></span>
                        <textarea name="modalidad_descripcion" rows="3"><?php echo esc_textarea($modalidad_descripcion); ?></textarea>
                    </label>
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('URL del calendario académico', 'flacso-uruguay'); ?></span>
                        <input type="url" name="calendario_academico" value="<?php echo esc_attr($calendario_academico); ?>" placeholder="<?php esc_attr_e('Sin completar', 'flacso-uruguay'); ?>">
                    </label>
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Descripción del calendario', 'flacso-uruguay'); ?></span>
                        <textarea name="calendario_descripcion" rows="3"><?php echo esc_textarea($calendario_descripcion); ?></textarea>
                    </label>
                </div>
            <?php FLACSO_Academic_Admin_UI::section_end(); ?>

            <?php
            $pre_status = !$pre_configurada
                ? __('Sin completar', 'flacso-uruguay')
                : ($pre_habilitada ? __('Abierta', 'flacso-uruguay') : __('Cerrada', 'flacso-uruguay'));
            FLACSO_Academic_Admin_UI::section_start(
                'flacso-cohorte-preinscripcion',
                __('Preinscripción', 'flacso-uruguay'),
                __('Apertura, cierre, enlace, mensajes y fechas propias de la inscripción.', 'flacso-uruguay'),
                'dashicons-forms',
                $pre_status
            );
            $url_preinscripcion = FLACSO_Django_API_Client::url_preinscripcion_oferta($parent_id);
            $nonce = wp_create_nonce('flacso_preinscripcion_nonce');
            ?>
                <div class="flacso-cohort-registration-status <?php echo $pre_habilitada ? 'is-open' : ''; ?>">
                    <span class="flacso-cohort-registration-dot" aria-hidden="true"></span>
                    <strong><?php echo esc_html($pre_status); ?></strong>
                    <span class="flacso-academic-actions">
                        <?php if ($pre_habilitada) : ?>
                            <button type="button" id="flacso-cerrar-preinscripcion" class="button" data-cohorte-id="<?php echo esc_attr((string) $post->ID); ?>" data-nonce="<?php echo esc_attr($nonce); ?>"><?php esc_html_e('Cerrar preinscripción', 'flacso-uruguay'); ?></button>
                        <?php else : ?>
                            <button type="button" id="flacso-abrir-preinscripcion" class="button button-primary" data-cohorte-id="<?php echo esc_attr((string) $post->ID); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" <?php disabled($parent_id < 1); ?>><?php esc_html_e('Abrir preinscripción', 'flacso-uruguay'); ?></button>
                        <?php endif; ?>
                    </span>
                </div>
                <div id="flacso-preinscripcion-notice" class="flacso-cohort-registration-notice" aria-live="polite"></div>

                <div class="flacso-academic-grid">
                    <label class="flacso-academic-field flacso-academic-field--full">
                        <span><?php esc_html_e('URL de preinscripción', 'flacso-uruguay'); ?></span>
                        <input type="url" name="link_preinscripcion" value="<?php echo esc_attr($link_preinscripcion); ?>" placeholder="https://preinscripciones.flacso.edu.uy/…">
                        <small><?php esc_html_e('Solo se guardan URLs HTTPS del portal preinscripciones.flacso.edu.uy.', 'flacso-uruguay'); ?></small>
                    </label>
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Apertura programada', 'flacso-uruguay'); ?></span>
                        <input type="text" name="preinscripcion_desde" value="<?php echo esc_attr($pre_desde); ?>" placeholder="<?php esc_attr_e('Sin completar', 'flacso-uruguay'); ?>">
                    </label>
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Cierre programado', 'flacso-uruguay'); ?></span>
                        <input type="text" name="preinscripcion_hasta" value="<?php echo esc_attr($pre_hasta); ?>" placeholder="<?php esc_attr_e('Sin completar', 'flacso-uruguay'); ?>">
                    </label>
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Etiqueta', 'flacso-uruguay'); ?></span>
                        <input type="text" name="etiqueta_preinscripcion" value="<?php echo esc_attr($etiqueta_preinscripcion); ?>">
                    </label>
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Texto del CTA', 'flacso-uruguay'); ?></span>
                        <input type="text" name="cta_preinscripcion" value="<?php echo esc_attr($cta_preinscripcion); ?>">
                    </label>
                    <label class="flacso-academic-field flacso-academic-field--full">
                        <span><?php esc_html_e('Presentación de la preinscripción', 'flacso-uruguay'); ?></span>
                        <textarea name="presentacion_preinscripcion" rows="4"><?php echo esc_textarea($presentacion_preinscripcion); ?></textarea>
                    </label>
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Mensaje cuando está abierta', 'flacso-uruguay'); ?></span>
                        <textarea name="mensaje_preinscripcion_abierta" rows="4"><?php echo esc_textarea($mensaje_abierta); ?></textarea>
                    </label>
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Mensaje cuando está cerrada', 'flacso-uruguay'); ?></span>
                        <textarea name="mensaje_preinscripcion_cerrada" rows="4"><?php echo esc_textarea($mensaje_cerrada); ?></textarea>
                    </label>
                </div>

                <script>
                (function ($) {
                    function preinscripcionAction(action, btn) {
                        btn.prop('disabled', true);
                        var original = btn.text();
                        btn.text('<?php echo esc_js(__('Procesando…', 'flacso-uruguay')); ?>');
                        $.post(ajaxurl, {
                            action: action,
                            cohorte_id: btn.data('cohorte-id'),
                            _wpnonce: btn.data('nonce')
                        }, function (res) {
                            var notice = $('#flacso-preinscripcion-notice');
                            if (res.success) {
                                notice.removeClass('is-error').addClass('is-success').text(res.data.message).show();
                                setTimeout(function () { location.reload(); }, 1200);
                            } else {
                                notice.removeClass('is-success').addClass('is-error').text((res.data && res.data.message) || '<?php echo esc_js(__('Error al comunicarse con el sistema de preinscripciones.', 'flacso-uruguay')); ?>').show();
                                btn.prop('disabled', false).text(original);
                            }
                        }).fail(function () {
                            $('#flacso-preinscripcion-notice').removeClass('is-success').addClass('is-error').text('<?php echo esc_js(__('Error de red. Inténtelo nuevamente.', 'flacso-uruguay')); ?>').show();
                            btn.prop('disabled', false).text(original);
                        });
                    }

                    $(document).on('click', '#flacso-abrir-preinscripcion', function () {
                        preinscripcionAction('flacso_abrir_preinscripcion_cohorte', $(this));
                    });
                    $(document).on('click', '#flacso-cerrar-preinscripcion', function () {
                        if (!confirm('<?php echo esc_js(__('¿Cerrar la preinscripción para esta cohorte?', 'flacso-uruguay')); ?>')) return;
                        preinscripcionAction('flacso_cerrar_preinscripcion_cohorte', $(this));
                    });
                }(jQuery));
                </script>
            <?php FLACSO_Academic_Admin_UI::section_end(); ?>

            <?php FLACSO_Academic_Admin_UI::section_start(
                'flacso-cohorte-enlaces',
                __('Enlaces útiles', 'flacso-uruguay'),
                __('Accesos relacionados con la oferta y su preinscripción.', 'flacso-uruguay'),
                'dashicons-admin-links',
                ($url_preinscripcion || $parent_id > 0) ? __('Disponible', 'flacso-uruguay') : __('Sin completar', 'flacso-uruguay')
            ); ?>
                <ul class="flacso-academic-list">
                    <?php if ($url_preinscripcion) : ?>
                        <li>
                            <span class="flacso-academic-list__copy"><strong><?php esc_html_e('Portal de preinscripción', 'flacso-uruguay'); ?></strong><small><?php echo esc_html($url_preinscripcion); ?></small></span>
                            <a class="button button-small" href="<?php echo esc_url($url_preinscripcion); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Abrir', 'flacso-uruguay'); ?></a>
                        </li>
                    <?php endif; ?>
                    <?php if ($parent_id > 0 && get_permalink($parent_id)) : ?>
                        <li>
                            <span class="flacso-academic-list__copy"><strong><?php esc_html_e('Página pública de la Oferta', 'flacso-uruguay'); ?></strong><small><?php echo esc_html($parent_title); ?></small></span>
                            <a class="button button-small" href="<?php echo esc_url(get_permalink($parent_id)); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Abrir', 'flacso-uruguay'); ?></a>
                        </li>
                    <?php endif; ?>
                </ul>
                <?php if (!$url_preinscripcion && $parent_id < 1) : ?>
                    <?php FLACSO_Academic_Admin_UI::render_empty(__('No hay enlaces disponibles hasta seleccionar una Oferta padre.', 'flacso-uruguay')); ?>
                <?php endif; ?>
            <?php FLACSO_Academic_Admin_UI::section_end(); ?>
        </div>

        <style>
            .flacso-cohort-checkbox{display:flex;align-items:flex-start;gap:9px;padding:12px;border:1px solid #dcdcde;border-radius:6px;background:#fafafa}
            .flacso-cohort-checkbox>span{display:grid;gap:3px}
            .flacso-cohort-checkbox small{color:#646970}
            .flacso-cohort-registration-status{display:flex;align-items:center;gap:9px;margin-bottom:14px;padding:12px;border:1px solid #dcdcde;border-radius:6px;background:#f6f7f7}
            .flacso-cohort-registration-status .flacso-academic-actions{margin-left:auto}
            .flacso-cohort-registration-dot{width:10px;height:10px;border-radius:50%;background:#94a3b8}
            .flacso-cohort-registration-status.is-open .flacso-cohort-registration-dot{background:#22c55e}
            .flacso-cohort-registration-notice{display:none;margin:0 0 14px;padding:9px 11px;border-radius:4px}
            .flacso-cohort-registration-notice.is-success{color:#166534;background:#dcfce7}
            .flacso-cohort-registration-notice.is-error{color:#991b1b;background:#fee2e2}
            @media(max-width:782px){.flacso-cohort-registration-status{align-items:flex-start;flex-wrap:wrap}.flacso-cohort-registration-status .flacso-academic-actions{width:100%;margin-left:19px}}
        </style>
        <?php
    }

    public static function render_start_date_preview_script(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== self::POST_TYPE) {
            return;
        }
        ?>
        <script>
        (function () {
            var precision = document.getElementById('precision_fecha_inicio');
            var year = document.getElementById('anio_inicio');
            var date = document.getElementById('fecha_inicio');
            var preview = document.getElementById('flacso-cohorte-start-preview');
            if (!precision || !year || !date || !preview) {
                return;
            }

            var output = preview.querySelector('span');
            var locale = 'es-UY';
            function selectedDate() {
                return date.value ? new Date(date.value + 'T00:00:00') : null;
            }
            function updatePreview() {
                var selected = selectedDate();
                var value = '';
                if (precision.value === 'anio') {
                    value = year.value || (selected ? String(selected.getFullYear()) : 'Completá el año de comienzo.');
                } else if (precision.value === 'mes') {
                    value = selected
                        ? selected.toLocaleDateString(locale, { month: 'long', year: 'numeric' })
                        : 'Elegí una fecha para mostrar el mes y año.';
                } else {
                    value = selected
                        ? selected.toLocaleDateString(locale, { day: 'numeric', month: 'long', year: 'numeric' })
                        : 'Elegí la fecha exacta de comienzo.';
                }
                output.textContent = ' ' + value;
            }

            [precision, year, date].forEach(function (field) {
                field.addEventListener('change', updatePreview);
                field.addEventListener('input', updatePreview);
            });
            updatePreview();
        }());
        </script>
        <?php
    }

    public static function save_post_data(int $post_id, $post): void {
        if (!isset($_POST['cohorte_nonce']) || !wp_verify_nonce($_POST['cohorte_nonce'], 'save_cohorte_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (isset($_POST['oferta_academica_id'])) {
            update_post_meta($post_id, self::META_PARENT_ID, absint($_POST['oferta_academica_id']));
        }
        if (isset($_POST['numero_cohorte'])) {
            update_post_meta($post_id, 'numero', absint($_POST['numero_cohorte']));
        } elseif (isset($_POST['numero'])) {
            update_post_meta($post_id, 'numero', absint($_POST['numero']));
        }
        if (isset($_POST['estado'])) {
            update_post_meta($post_id, 'estado', self::sanitize_state($_POST['estado']));
        }
        if (isset($_POST['precision_fecha_inicio'])) {
            update_post_meta($post_id, 'precision_fecha_inicio', self::sanitize_precision($_POST['precision_fecha_inicio']));
        }

        $anio_inicio = isset($_POST['anio_inicio']) ? self::sanitize_year($_POST['anio_inicio']) : 0;
        $fecha_inicio = isset($_POST['fecha_inicio']) ? self::sanitize_date($_POST['fecha_inicio']) : '';

        if ($anio_inicio === 0 && $fecha_inicio !== '') {
            $anio_inicio = (int) substr($fecha_inicio, 0, 4);
        }

        self::update_or_delete_meta($post_id, 'anio_inicio', $anio_inicio ?: '');
        self::update_or_delete_meta($post_id, 'fecha_inicio', $fecha_inicio);

        if (isset($_POST['tabla_precio_id'])) {
            self::update_or_delete_meta($post_id, 'tabla_precio_id', absint($_POST['tabla_precio_id']) ?: '');
        }
        if (isset($_POST['link_preinscripcion'])) {
            self::update_or_delete_meta($post_id, 'link_preinscripcion', self::sanitize_registration_url($_POST['link_preinscripcion']));
        }
        if (isset($_POST['preinscripcion_desde'])) {
            self::update_or_delete_meta($post_id, 'preinscripcion_desde', self::sanitize_datetime($_POST['preinscripcion_desde']));
        }
        if (isset($_POST['preinscripcion_hasta'])) {
            self::update_or_delete_meta($post_id, 'preinscripcion_hasta', self::sanitize_datetime($_POST['preinscripcion_hasta']));
        }

        $typed_fields = [
            'modalidad' => [self::class, 'sanitize_modality'],
            'modalidad_descripcion' => 'wp_kses_post',
            'calendario_academico' => 'esc_url_raw',
            'calendario_descripcion' => 'wp_kses_post',
            'mensaje_preinscripcion_abierta' => 'wp_kses_post',
            'mensaje_preinscripcion_cerrada' => 'wp_kses_post',
        ];
        foreach ($typed_fields as $key => $sanitizer) {
            if (isset($_POST[$key])) {
                self::update_or_delete_meta($post_id, $key, call_user_func($sanitizer, wp_unslash($_POST[$key])));
            }
        }

        self::sync_title($post_id);
    }

    private static function update_or_delete_meta(int $post_id, string $key, $value): void {
        if ($value === '' || $value === null || $value === []) {
            delete_post_meta($post_id, $key);
            return;
        }
        update_post_meta($post_id, $key, $value);
    }

    public static function register_columns(array $columns): array {
        return [
            'cb'             => $columns['cb'] ?? '<input type="checkbox" />',
            'title'          => __('Cohorte', 'flacso-uruguay'),
            'oferta'         => __('Oferta Académica (Padre)', 'flacso-uruguay'),
            'estado'         => __('Estado', 'flacso-uruguay'),
            'fechas'         => __('Período / Fechas', 'flacso-uruguay'),
            'preinscripcion' => __('Preinscripción', 'flacso-uruguay'),
            'date'           => __('Publicada', 'flacso-uruguay'),
        ];
    }

    public static function render_column(string $column, int $post_id): void {
        switch ($column) {
            case 'oferta':
                $parent_id = absint(get_post_meta($post_id, self::META_PARENT_ID, true));
                if ($parent_id > 0) {
                    $parent_title = get_the_title($parent_id);
                    $edit_url = get_edit_post_link($parent_id);
                    $filter_url = admin_url('edit.php?post_type=' . self::POST_TYPE . '&oferta_academica_id=' . $parent_id);
                    echo '<strong><a href="' . esc_url($edit_url) . '" title="Editar Oferta">💾 ' . esc_html($parent_title) . '</a></strong><br>';
                    echo '<small><a href="' . esc_url($filter_url) . '" style="color:#0284c7;">🔍 Filtrar sólo esta oferta</a></small>';
                } else {
                    echo '<span style="color:#ef4444;font-weight:600;">⚠️ Sin Oferta asignada</span>';
                }
                break;
            case 'estado':
                $estado = self::sanitize_state(get_post_meta($post_id, 'estado', true));
                $colors = [
                    'planificada' => ['bg' => '#e0f2fe', 'color' => '#0369a1', 'label' => 'Planificada'],
                    'en_curso'    => ['bg' => '#dcfce7', 'color' => '#15803d', 'label' => 'En curso'],
                    'finalizada'  => ['bg' => '#f1f5f9', 'color' => '#475569', 'label' => 'Finalizada'],
                    'cancelada'   => ['bg' => '#fee2e2', 'color' => '#b91c1c', 'label' => 'Cancelada'],
                ];
                $conf = $colors[$estado] ?? $colors['planificada'];
                echo '<span style="background:' . esc_attr($conf['bg']) . ';color:' . esc_attr($conf['color']) . ';padding:3px 8px;border-radius:4px;font-size:11px;font-weight:700;">' . esc_html($conf['label']) . '</span>';
                break;
            case 'fechas':
                $formatted = self::format_dates($post_id);
                echo $formatted !== '' ? esc_html($formatted) : '<span style="color:#94a3b8;">—</span>';
                break;
            case 'preinscripcion':
                $url = (string) get_post_meta($post_id, 'link_preinscripcion', true);
                $abierta = self::accepts_registration($post_id);
                if ($url) {
                    if ($abierta) {
                        echo '<span style="color:#16a34a;font-weight:700;">🟢 Abierta</span><br>';
                    } else {
                        echo '<span style="color:#94a3b8;">⚪ Cerrada</span><br>';
                    }
                    echo '<small><a href="' . esc_url($url) . '" target="_blank">Portal externo ↗</a></small>';
                } else {
                    echo '<span style="color:#94a3b8;">—</span>';
                }
                break;
        }
    }

    public static function render_admin_filters(): void {
        global $typenow;
        if ($typenow !== self::POST_TYPE) {
            return;
        }

        $current_parent = isset($_GET['oferta_academica_id']) ? absint($_GET['oferta_academica_id']) : 0;
        $ofertas = get_posts(['post_type' => 'oferta-academica', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        ?>
        <select name="oferta_academica_id">
            <option value=""><?php esc_html_e('Todas las Ofertas Académicas', 'flacso-uruguay'); ?></option>
            <?php foreach ($ofertas as $of) : ?>
                <option value="<?php echo esc_attr((string) $of->ID); ?>" <?php selected($current_parent, $of->ID); ?>>
                    <?php echo esc_html($of->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public static function filter_query_by_parent($query): void {
        global $pagenow, $typenow;
        if (!is_admin() || $pagenow !== 'edit.php' || $typenow !== self::POST_TYPE) {
            return;
        }
        if (!empty($_GET['oferta_academica_id'])) {
            $parent_id = absint($_GET['oferta_academica_id']);
            if ($parent_id > 0) {
                $meta_query = $query->get('meta_query') ?: [];
                $meta_query[] = [
                    'key'   => self::META_PARENT_ID,
                    'value' => $parent_id,
                ];
                $query->set('meta_query', $meta_query);
            }
        }
    }
}
