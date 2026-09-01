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
            if (!rest_sanitize_boolean(get_post_meta($cohort_id, 'preinscripcion_habilitada', true))) {
                return false;
            }
        } else {
            $offer_id = absint(get_post_meta($cohort_id, self::META_PARENT_ID, true));
            if (!$offer_id || !rest_sanitize_boolean(get_post_meta($offer_id, 'inscripciones_abiertas', true))) {
                return false;
            }
        }
        $state = self::sanitize_state(get_post_meta($cohort_id, 'estado', true));
        if (!in_array($state, ['planificada', 'en_curso'], true)) {
            return false;
        }
        $timestamp = $timestamp ?? current_time('timestamp', true);
        $from = strtotime((string) get_post_meta($cohort_id, 'preinscripcion_desde', true));
        $until = strtotime((string) get_post_meta($cohort_id, 'preinscripcion_hasta', true));
        return (!$from || $timestamp >= $from) && (!$until || $timestamp < $until);
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
        $fecha_fin = (string) get_post_meta($post_id, 'fecha_fin', true);
        $anio_inicio = absint(get_post_meta($post_id, 'anio_inicio', true));
        $anio_fin = absint(get_post_meta($post_id, 'anio_fin', true));

        $y_start = $anio_inicio > 0 ? $anio_inicio : ($fecha_inicio !== '' ? (int) substr($fecha_inicio, 0, 4) : 0);
        $y_end = $anio_fin > 0 ? $anio_fin : ($fecha_fin !== '' ? (int) substr($fecha_fin, 0, 4) : 0);

        if ($precision === 'anio' || ($fecha_inicio === '' && ($y_start > 0 || $y_end > 0))) {
            if ($y_start > 0 && $y_end > 0 && $y_start !== $y_end) {
                return $y_start . ' – ' . $y_end;
            } elseif ($y_start > 0) {
                return (string) $y_start;
            } elseif ($y_end > 0) {
                return (string) $y_end;
            }
            return '';
        }

        if ($precision === 'mes' && $fecha_inicio !== '') {
            $ts = strtotime($fecha_inicio);
            if ($ts) {
                $m_start = function_exists('wp_date') ? wp_date('F Y', $ts) : date('m/Y', $ts);
                if ($fecha_fin !== '') {
                    $ts_end = strtotime($fecha_fin);
                    if ($ts_end) {
                        $m_end = function_exists('wp_date') ? wp_date('F Y', $ts_end) : date('m/Y', $ts_end);
                        return $m_start . ' – ' . $m_end;
                    }
                }
                return $m_start;
            }
        }

        if ($fecha_inicio !== '') {
            $ts = strtotime($fecha_inicio);
            if ($ts) {
                $d_start = date('d/m/Y', $ts);
                if ($fecha_fin !== '') {
                    $ts_end = strtotime($fecha_fin);
                    if ($ts_end) {
                        return $d_start . ' al ' . date('d/m/Y', $ts_end);
                    }
                }
                return $d_start;
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
        $estado = self::sanitize_state(get_post_meta($post->ID, 'estado', true));
        $fecha_inicio = (string) get_post_meta($post->ID, 'fecha_inicio', true);
        $fecha_fin = (string) get_post_meta($post->ID, 'fecha_fin', true);
        $anio_inicio = absint(get_post_meta($post->ID, 'anio_inicio', true));
        $anio_fin = absint(get_post_meta($post->ID, 'anio_fin', true));
        if ($anio_inicio === 0 && $fecha_inicio !== '') {
            $anio_inicio = (int) substr($fecha_inicio, 0, 4);
        }
        if ($anio_fin === 0 && $fecha_fin !== '') {
            $anio_fin = (int) substr($fecha_fin, 0, 4);
        }

        $precision = (string) get_post_meta($post->ID, 'precision_fecha_inicio', true) ?: 'dia';
        $tabla_precio_id = absint(get_post_meta($post->ID, 'tabla_precio_id', true));
        $link_preinscripcion = (string) get_post_meta($post->ID, 'link_preinscripcion', true);
        $pre_desde = (string) get_post_meta($post->ID, 'preinscripcion_desde', true);
        $pre_hasta = (string) get_post_meta($post->ID, 'preinscripcion_hasta', true);
        $pre_habilitada = metadata_exists('post', $post->ID, 'preinscripcion_habilitada')
            ? rest_sanitize_boolean(get_post_meta($post->ID, 'preinscripcion_habilitada', true))
            : false;
        $modalidad = self::sanitize_modality(get_post_meta($post->ID, 'modalidad', true));
        $modalidad_descripcion = (string) get_post_meta($post->ID, 'modalidad_descripcion', true);
        $calendario_academico = (string) get_post_meta($post->ID, 'calendario_academico', true);
        $calendario_descripcion = (string) get_post_meta($post->ID, 'calendario_descripcion', true);
        $mensaje_abierta = (string) get_post_meta($post->ID, 'mensaje_preinscripcion_abierta', true);
        $mensaje_cerrada = (string) get_post_meta($post->ID, 'mensaje_preinscripcion_cerrada', true);

        $ofertas = get_posts(['post_type' => 'oferta-academica', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $tablas = get_posts(['post_type' => 'tabla-precio', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        wp_nonce_field('save_cohorte_meta', 'cohorte_nonce');
        ?>
        <div style="display: flex; flex-direction: column; gap: 16px; padding: 10px 0;">
            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 12px 16px; border-radius: 6px;">
                <label style="font-weight: 700; display: block; margin-bottom: 6px;">
                    <?php esc_html_e('Oferta Académica (Entidad Padre):', 'flacso-uruguay'); ?> <span style="color:red">*</span>
                </label>
                <select name="oferta_academica_id" style="width: 100%; max-width: 500px;" required>
                    <option value=""><?php esc_html_e('— Seleccionar Oferta Académica —', 'flacso-uruguay'); ?></option>
                    <?php foreach ($ofertas as $oferta) : ?>
                        <option value="<?php echo esc_attr((string) $oferta->ID); ?>" <?php selected($parent_id, $oferta->ID); ?>>
                            <?php echo esc_html($oferta->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($parent_id > 0) : ?>
                    <p style="margin: 6px 0 0; font-size: 12px;">
                        <a href="<?php echo esc_url(get_edit_post_link($parent_id)); ?>" target="_blank">
                            <?php esc_html_e('↗ Ver Oferta Académica padre en WordPress', 'flacso-uruguay'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Número de cohorte:', 'flacso-uruguay'); ?> <span style="color:red">*</span></label>
                    <input type="number" name="numero" value="<?php echo esc_attr((string) $numero); ?>" min="1" max="999" required style="width: 100%;">
                    <small style="color: #64748b;"><?php esc_html_e('Se convertirá a romano automáticamente (ej: 6 -> VI)', 'flacso-uruguay'); ?></small>
                </div>
                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Estado:', 'flacso-uruguay'); ?></label>
                    <select name="estado" style="width: 100%;">
                        <option value="planificada" <?php selected($estado, 'planificada'); ?>><?php esc_html_e('Planificada', 'flacso-uruguay'); ?></option>
                        <option value="en_curso" <?php selected($estado, 'en_curso'); ?>><?php esc_html_e('En curso', 'flacso-uruguay'); ?></option>
                        <option value="finalizada" <?php selected($estado, 'finalizada'); ?>><?php esc_html_e('Finalizada', 'flacso-uruguay'); ?></option>
                        <option value="cancelada" <?php selected($estado, 'cancelada'); ?>><?php esc_html_e('Cancelada', 'flacso-uruguay'); ?></option>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Tabla de Aranceles:', 'flacso-uruguay'); ?></label>
                    <select name="tabla_precio_id" style="width: 100%;">
                        <option value="0"><?php esc_html_e('— Sin tabla asignada —', 'flacso-uruguay'); ?></option>
                        <?php foreach ($tablas as $tabla) : ?>
                            <option value="<?php echo esc_attr((string) $tabla->ID); ?>" <?php selected($tabla_precio_id, $tabla->ID); ?>>
                                <?php echo esc_html($tabla->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Sección de Fechas y Precisión -->
            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 14px 16px; border-radius: 6px;">
                <h4 style="margin: 0 0 10px; color:#1e293b;"><?php esc_html_e('Período y Fechas de la Cohorte', 'flacso-uruguay'); ?></h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 12px;">
                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Precisión de visualización:', 'flacso-uruguay'); ?></label>
                        <select name="precision_fecha_inicio" id="precision_fecha_inicio" style="width: 100%;">
                            <option value="dia" <?php selected($precision, 'dia'); ?>><?php esc_html_e('Día exacto (ej: 15/03/2027)', 'flacso-uruguay'); ?></option>
                            <option value="mes" <?php selected($precision, 'mes'); ?>><?php esc_html_e('Mes y año (ej: Marzo 2027)', 'flacso-uruguay'); ?></option>
                            <option value="anio" <?php selected($precision, 'anio'); ?>><?php esc_html_e('Solo año / Período (ej: 2027 – 2029)', 'flacso-uruguay'); ?></option>
                        </select>
                        <small style="color:#64748b;"><?php esc_html_e('Determina cómo se muestra en la web pública.', 'flacso-uruguay'); ?></small>
                    </div>
                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Año de inicio:', 'flacso-uruguay'); ?></label>
                        <input type="number" name="anio_inicio" id="anio_inicio" value="<?php echo $anio_inicio > 0 ? esc_attr((string) $anio_inicio) : ''; ?>" min="2000" max="2100" placeholder="ej: 2027" style="width: 100%;">
                    </div>
                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Año de fin:', 'flacso-uruguay'); ?></label>
                        <input type="number" name="anio_fin" id="anio_fin" value="<?php echo $anio_fin > 0 ? esc_attr((string) $anio_fin) : ''; ?>" min="2000" max="2100" placeholder="ej: 2029" style="width: 100%;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; border-top: 1px dashed #cbd5e1; padding-top: 10px;">
                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Fecha exacta de inicio (opcional si aún no está fijada):', 'flacso-uruguay'); ?></label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?php echo esc_attr($fecha_inicio); ?>" style="width: 100%;">
                    </div>
                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Fecha exacta de fin (opcional):', 'flacso-uruguay'); ?></label>
                        <input type="date" name="fecha_fin" id="fecha_fin" value="<?php echo esc_attr($fecha_fin); ?>" style="width: 100%;">
                    </div>
                </div>
            </div>

            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 14px 16px; border-radius: 6px;">
                <h4 style="margin: 0 0 10px; color:#1e293b;"><?php esc_html_e('Cursado de esta cohorte', 'flacso-uruguay'); ?></h4>
                <div style="display:grid; grid-template-columns:minmax(180px, .4fr) 1fr; gap:14px;">
                    <div>
                        <label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e('Modalidad:', 'flacso-uruguay'); ?></label>
                        <select name="modalidad" style="width:100%;">
                            <option value=""><?php esc_html_e('— Sin definir —', 'flacso-uruguay'); ?></option>
                            <option value="virtual" <?php selected($modalidad, 'virtual'); ?>><?php esc_html_e('Virtual', 'flacso-uruguay'); ?></option>
                            <option value="presencial" <?php selected($modalidad, 'presencial'); ?>><?php esc_html_e('Presencial', 'flacso-uruguay'); ?></option>
                            <option value="semipresencial" <?php selected($modalidad, 'semipresencial'); ?>><?php esc_html_e('Semipresencial', 'flacso-uruguay'); ?></option>
                            <option value="hibrida" <?php selected($modalidad, 'hibrida'); ?>><?php esc_html_e('Híbrida', 'flacso-uruguay'); ?></option>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e('Descripción de modalidad:', 'flacso-uruguay'); ?></label>
                        <textarea name="modalidad_descripcion" rows="3" style="width:100%;"><?php echo esc_textarea($modalidad_descripcion); ?></textarea>
                    </div>
                    <div style="grid-column:1/-1;">
                        <label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e('URL del calendario académico:', 'flacso-uruguay'); ?></label>
                        <input type="url" name="calendario_academico" value="<?php echo esc_attr($calendario_academico); ?>" style="width:100%;">
                    </div>
                    <div style="grid-column:1/-1;">
                        <label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e('Descripción del calendario:', 'flacso-uruguay'); ?></label>
                        <textarea name="calendario_descripcion" rows="3" style="width:100%;"><?php echo esc_textarea($calendario_descripcion); ?></textarea>
                    </div>
                </div>
            </div>

            <div style="background: #f1f5f9; padding: 12px 16px; border-radius: 6px;">
                <h4 style="margin: 0 0 10px;"><?php esc_html_e('Preinscripción Externa (Portal FLACSO)', 'flacso-uruguay'); ?></h4>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <label style="font-weight:600;">
                        <input type="checkbox" name="preinscripcion_habilitada" value="1" <?php checked($pre_habilitada); ?>>
                        <?php esc_html_e('Habilitar preinscripción para esta cohorte', 'flacso-uruguay'); ?>
                    </label>
                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('URL de preinscripción:', 'flacso-uruguay'); ?></label>
                        <input type="url" name="link_preinscripcion" value="<?php echo esc_attr($link_preinscripcion); ?>" placeholder="https://preinscripciones.flacso.edu.uy/..." style="width: 100%;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Preinscripciones abiertas desde:', 'flacso-uruguay'); ?></label>
                            <input type="datetime-local" name="preinscripcion_desde" value="<?php echo esc_attr($pre_desde); ?>" style="width: 100%;">
                        </div>
                        <div>
                            <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Preinscripciones cierran el:', 'flacso-uruguay'); ?></label>
                            <input type="datetime-local" name="preinscripcion_hasta" value="<?php echo esc_attr($pre_hasta); ?>" style="width: 100%;">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e('Mensaje cuando está abierta:', 'flacso-uruguay'); ?></label>
                            <textarea name="mensaje_preinscripcion_abierta" rows="3" style="width:100%;"><?php echo esc_textarea($mensaje_abierta); ?></textarea>
                        </div>
                        <div>
                            <label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e('Mensaje cuando está cerrada:', 'flacso-uruguay'); ?></label>
                            <textarea name="mensaje_preinscripcion_cerrada" rows="3" style="width:100%;"><?php echo esc_textarea($mensaje_cerrada); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
        if (isset($_POST['numero'])) {
            update_post_meta($post_id, 'numero', absint($_POST['numero']));
        }
        if (isset($_POST['estado'])) {
            update_post_meta($post_id, 'estado', self::sanitize_state($_POST['estado']));
        }
        if (isset($_POST['precision_fecha_inicio'])) {
            update_post_meta($post_id, 'precision_fecha_inicio', self::sanitize_precision($_POST['precision_fecha_inicio']));
        }

        $anio_inicio = isset($_POST['anio_inicio']) ? self::sanitize_year($_POST['anio_inicio']) : 0;
        $anio_fin = isset($_POST['anio_fin']) ? self::sanitize_year($_POST['anio_fin']) : 0;
        $fecha_inicio = isset($_POST['fecha_inicio']) ? self::sanitize_date($_POST['fecha_inicio']) : '';
        $fecha_fin = isset($_POST['fecha_fin']) ? self::sanitize_date($_POST['fecha_fin']) : '';

        if ($anio_inicio === 0 && $fecha_inicio !== '') {
            $anio_inicio = (int) substr($fecha_inicio, 0, 4);
        }
        if ($anio_fin === 0 && $fecha_fin !== '') {
            $anio_fin = (int) substr($fecha_fin, 0, 4);
        }

        self::update_or_delete_meta($post_id, 'anio_inicio', $anio_inicio ?: '');
        self::update_or_delete_meta($post_id, 'anio_fin', $anio_fin ?: '');
        self::update_or_delete_meta($post_id, 'fecha_inicio', $fecha_inicio);
        self::update_or_delete_meta($post_id, 'fecha_fin', $fecha_fin);

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

        update_post_meta($post_id, 'preinscripcion_habilitada', isset($_POST['preinscripcion_habilitada']));
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
