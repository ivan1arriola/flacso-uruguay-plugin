<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Ocurrencia temporal de un Seminario (Entidad débil subordinada). */
final class FLACSO_Edicion {
    public const POST_TYPE = 'edicion';
    public const META_PARENT_ID = 'seminario_id';
    public const ESTADOS = ['planificada', 'en_curso', 'finalizada', 'cancelada'];

    public static function register(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'          => __('Ediciones de seminarios', 'flacso-uruguay'),
                'singular_name' => __('Edición de seminario', 'flacso-uruguay'),
                'add_new_item'  => __('Agregar edición', 'flacso-uruguay'),
                'edit_item'     => __('Editar edición', 'flacso-uruguay'),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => false, // Entidad débil: gestionada dentro de Seminarios
            'show_in_rest' => false,
            'supports'     => ['revisions'],
            'rewrite'      => false,
            'query_var'    => false,
            'map_meta_cap' => true,
        ]);

        $definitions = [
            self::META_PARENT_ID             => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'anio'                           => ['type' => 'integer', 'sanitize_callback' => [self::class, 'sanitize_year']],
            'fecha_inicio'                   => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_date']],
            'fecha_fin'                      => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_date']],
            'estado'                         => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_state']],
            'modalidad'                      => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'encuentros_sincronicos'         => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_meetings']],
            'docentes'                       => ['type' => 'array', 'sanitize_callback' => [FLACSO_Seminario::class, 'sanitize_ids']],
            'tabla_precio_id'                => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'link_preinscripcion'            => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_registration_url']],
            'preinscripcion_desde'           => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_datetime']],
            'preinscripcion_hasta'           => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_datetime']],
            'dias_cierre_post_inicio'        => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'mensaje_preinscripcion_abierta' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'mensaje_preinscripcion_cerrada' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'mostrar_en_formulario'          => ['type' => 'boolean', 'sanitize_callback' => [FLACSO_Seminario::class, 'sanitize_boolean']],
            'ediciones_componentes'          => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_components']],
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

        if (is_admin()) {
            add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
            add_filter('manage_' . self::POST_TYPE . '_posts_columns', [self::class, 'register_columns']);
            add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [self::class, 'render_column'], 10, 2);
            add_action('restrict_manage_posts', [self::class, 'render_admin_filters']);
            add_filter('parse_query', [self::class, 'filter_query_by_parent']);
        }
    }

    public static function sanitize_year($value): int {
        $year = absint($value);
        return $year >= 2000 && $year <= 2200 ? $year : (int) date('Y');
    }

    public static function sanitize_state($value): string {
        $state = sanitize_key((string) $value);
        return in_array($state, self::ESTADOS, true) ? $state : 'planificada';
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

    public static function sanitize_meetings($value): array {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $meeting = [
                'fecha'        => self::sanitize_date($item['fecha'] ?? ''),
                'hora_inicio' => sanitize_text_field((string) ($item['hora_inicio'] ?? '')),
                'hora_fin'    => sanitize_text_field((string) ($item['hora_fin'] ?? '')),
                'zona_horaria' => sanitize_text_field((string) ($item['zona_horaria'] ?? 'America/Montevideo')),
            ];
            if ($meeting['fecha'] !== '') {
                $result[] = $meeting;
            }
        }
        return $result;
    }

    public static function sanitize_components($value): array {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        $seen = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = absint($item['edicion_id'] ?? 0);
            if ($id < 1 || isset($seen[$id])) {
                continue;
            }
            $result[] = ['edicion_id' => $id, 'orden' => absint($item['orden'] ?? count($result) + 1)];
            $seen[$id] = true;
        }
        usort($result, static function (array $a, array $b): int { return $a['orden'] <=> $b['orden']; });
        return $result;
    }

    public static function get_days_after_start_limit(int $edition_id = 0): int {
        if ($edition_id > 0) {
            $custom = get_post_meta($edition_id, 'dias_cierre_post_inicio', true);
            if ($custom !== '' && $custom !== false && is_numeric($custom)) {
                return (int) $custom;
            }
        }
        $global = get_option('flacso_seminarios_dias_cierre_post_inicio', 10);
        return is_numeric($global) ? (int) $global : 10;
    }

    public static function accepts_registration(int $edition_id, ?int $timestamp = null): bool {
        $state = self::sanitize_state(get_post_meta($edition_id, 'estado', true));
        if (in_array($state, ['cancelada', 'finalizada'], true)) {
            return false;
        }
        $timestamp = $timestamp ?? current_time('timestamp', true);

        $from_str = (string) get_post_meta($edition_id, 'preinscripcion_desde', true);
        if ($from_str !== '') {
            $from = strtotime($from_str);
            if ($from && $timestamp < $from) {
                return false;
            }
        }

        $until_str = (string) get_post_meta($edition_id, 'preinscripcion_hasta', true);
        if ($until_str !== '') {
            $until = strtotime($until_str);
            if ($until && $timestamp > $until) {
                return false;
            }
        } else {
            $fecha_inicio = (string) get_post_meta($edition_id, 'fecha_inicio', true);
            if ($fecha_inicio !== '') {
                $days = self::get_days_after_start_limit($edition_id);
                $closing_time = strtotime($fecha_inicio . ' +' . $days . ' days 23:59:59');
                if ($closing_time && $timestamp > $closing_time) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function sync_title(int $post_id): void {
        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }
        $anio = absint(get_post_meta($post_id, 'anio', true)) ?: (int) date('Y');
        $parent_id = absint(get_post_meta($post_id, self::META_PARENT_ID, true));

        $edicion_name = sprintf('Edición %d', $anio);
        if ($parent_id > 0) {
            $parent_title = get_the_title($parent_id);
            $full_title = $parent_title ? sprintf('%s — %s', $parent_title, $edicion_name) : $edicion_name;
        } else {
            $full_title = $edicion_name;
        }

        if ($full_title !== '' && get_the_title($post_id) !== $full_title) {
            wp_update_post(['ID' => $post_id, 'post_title' => $full_title]);
        }
    }

    public static function on_meta_change($meta_id, $post_id, $meta_key, $meta_value): void {
        if (($meta_key === 'anio' || $meta_key === self::META_PARENT_ID) && get_post_type($post_id) === self::POST_TYPE) {
            self::sync_title((int) $post_id);
        }
    }

    public static function add_meta_boxes(): void {
        add_meta_box(
            'flacso_edicion_meta',
            __('Configuración de la Edición de Seminario', 'flacso-uruguay'),
            [self::class, 'render_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function render_meta_box($post): void {
        $parent_id = absint(get_post_meta($post->ID, self::META_PARENT_ID, true));
        if ($parent_id === 0 && isset($_GET['seminario_id'])) {
            $parent_id = absint($_GET['seminario_id']);
        }

        $anio = absint(get_post_meta($post->ID, 'anio', true)) ?: (int) date('Y');
        $estado = self::sanitize_state(get_post_meta($post->ID, 'estado', true));
        $modalidad = (string) get_post_meta($post->ID, 'modalidad', true);
        $fecha_inicio = (string) get_post_meta($post->ID, 'fecha_inicio', true);
        $fecha_fin = (string) get_post_meta($post->ID, 'fecha_fin', true);
        $tabla_precio_id = absint(get_post_meta($post->ID, 'tabla_precio_id', true));
        $docentes = (array) get_post_meta($post->ID, 'docentes', true);
        $link_preinscripcion = (string) get_post_meta($post->ID, 'link_preinscripcion', true);
        $pre_desde = (string) get_post_meta($post->ID, 'preinscripcion_desde', true);
        $pre_hasta = (string) get_post_meta($post->ID, 'preinscripcion_hasta', true);

        $seminarios = get_posts(['post_type' => 'seminario', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $tablas = get_posts(['post_type' => 'tabla-precio', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $all_docentes = get_posts(['post_type' => 'docente', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);

        wp_nonce_field('save_edicion_meta', 'edicion_nonce');
        ?>
        <div style="display: flex; flex-direction: column; gap: 16px; padding: 10px 0;">
            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 12px 16px; border-radius: 6px;">
                <label style="font-weight: 700; display: block; margin-bottom: 6px;">
                    <?php esc_html_e('Seminario (Entidad Padre):', 'flacso-uruguay'); ?> <span style="color:red">*</span>
                </label>
                <select name="seminario_id" style="width: 100%; max-width: 500px;" required>
                    <option value=""><?php esc_html_e('— Seleccionar Seminario —', 'flacso-uruguay'); ?></option>
                    <?php foreach ($seminarios as $sem) : ?>
                        <option value="<?php echo esc_attr((string) $sem->ID); ?>" <?php selected($parent_id, $sem->ID); ?>>
                            <?php echo esc_html($sem->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($parent_id > 0) : ?>
                    <p style="margin: 6px 0 0; font-size: 12px;">
                        <a href="<?php echo esc_url(get_edit_post_link($parent_id)); ?>" target="_blank">
                            <?php esc_html_e('↗ Ver Seminario padre en WordPress', 'flacso-uruguay'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Año de la edición:', 'flacso-uruguay'); ?> <span style="color:red">*</span></label>
                    <input type="number" name="anio" value="<?php echo esc_attr((string) $anio); ?>" min="2000" max="2100" required style="width: 100%;">
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
                    <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Modalidad:', 'flacso-uruguay'); ?></label>
                    <input type="text" name="modalidad" value="<?php echo esc_attr($modalidad); ?>" placeholder="Virtual sincrónica, Híbrida..." style="width: 100%;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Fecha de inicio:', 'flacso-uruguay'); ?></label>
                    <input type="date" name="fecha_inicio" value="<?php echo esc_attr($fecha_inicio); ?>" style="width: 100%;">
                </div>
                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Fecha de fin:', 'flacso-uruguay'); ?></label>
                    <input type="date" name="fecha_fin" value="<?php echo esc_attr($fecha_fin); ?>" style="width: 100%;">
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

            <div>
                <label style="font-weight: 600; display: block; margin-bottom: 6px;"><?php esc_html_e('Docentes a cargo de la edición:', 'flacso-uruguay'); ?></label>
                <div style="max-height: 160px; overflow-y: auto; border: 1px solid #cbd5e1; padding: 8px 12px; border-radius: 4px; background: #fff;">
                    <?php foreach ($all_docentes as $doc) : ?>
                        <label style="display: block; margin-bottom: 4px;">
                            <input type="checkbox" name="docentes[]" value="<?php echo esc_attr((string) $doc->ID); ?>" <?php checked(in_array($doc->ID, $docentes, true)); ?>>
                            <?php echo esc_html($doc->post_title); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="background: #f1f5f9; padding: 12px 16px; border-radius: 6px;">
                <h4 style="margin: 0 0 10px;"><?php esc_html_e('Preinscripción Externa (Portal FLACSO)', 'flacso-uruguay'); ?></h4>
                <div style="display: flex; flex-direction: column; gap: 10px;">
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
                            <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Preinscripciones cierran el (manual):', 'flacso-uruguay'); ?></label>
                            <input type="datetime-local" name="preinscripcion_hasta" value="<?php echo esc_attr($pre_hasta); ?>" style="width: 100%;">
                        </div>
                    </div>
                    <div>
                        <?php $dias_cierre = get_post_meta($post->ID, 'dias_cierre_post_inicio', true); ?>
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Días de cierre automático tras el inicio:', 'flacso-uruguay'); ?></label>
                        <input type="number" name="dias_cierre_post_inicio" value="<?php echo esc_attr((string) $dias_cierre); ?>" min="0" max="365" placeholder="10 (por defecto)" style="width: 100%; max-width: 220px;">
                        <p style="margin: 4px 0 0; font-size: 12px; color: #64748b;">
                            <?php esc_html_e('Si no se fija una fecha de cierre manual, las inscripciones permanecerán abiertas hasta N días posteriores a la fecha de inicio de la edición.', 'flacso-uruguay'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function save_post_data(int $post_id, $post): void {
        if (!isset($_POST['edicion_nonce']) || !wp_verify_nonce($_POST['edicion_nonce'], 'save_edicion_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (isset($_POST['seminario_id'])) {
            update_post_meta($post_id, self::META_PARENT_ID, absint($_POST['seminario_id']));
        }
        if (isset($_POST['anio'])) {
            update_post_meta($post_id, 'anio', self::sanitize_year($_POST['anio']));
        }
        if (isset($_POST['estado'])) {
            update_post_meta($post_id, 'estado', self::sanitize_state($_POST['estado']));
        }
        if (isset($_POST['modalidad'])) {
            update_post_meta($post_id, 'modalidad', sanitize_text_field($_POST['modalidad']));
        }
        if (isset($_POST['tabla_precio_id'])) {
            update_post_meta($post_id, 'tabla_precio_id', absint($_POST['tabla_precio_id']));
        }
        if (isset($_POST['fecha_inicio'])) {
            update_post_meta($post_id, 'fecha_inicio', self::sanitize_date($_POST['fecha_inicio']));
        }
        if (isset($_POST['fecha_fin'])) {
            update_post_meta($post_id, 'fecha_fin', self::sanitize_date($_POST['fecha_fin']));
        }
        if (isset($_POST['docentes']) && is_array($_POST['docentes'])) {
            update_post_meta($post_id, 'docentes', array_map('absint', $_POST['docentes']));
        } else {
            update_post_meta($post_id, 'docentes', []);
        }
        if (isset($_POST['link_preinscripcion'])) {
            update_post_meta($post_id, 'link_preinscripcion', self::sanitize_registration_url($_POST['link_preinscripcion']));
        }
        if (isset($_POST['preinscripcion_desde'])) {
            update_post_meta($post_id, 'preinscripcion_desde', self::sanitize_datetime($_POST['preinscripcion_desde']));
        }
        if (isset($_POST['preinscripcion_hasta'])) {
            update_post_meta($post_id, 'preinscripcion_hasta', self::sanitize_datetime($_POST['preinscripcion_hasta']));
        }
        if (isset($_POST['dias_cierre_post_inicio'])) {
            $raw_dias = trim((string) $_POST['dias_cierre_post_inicio']);
            if ($raw_dias === '') {
                delete_post_meta($post_id, 'dias_cierre_post_inicio');
            } else {
                update_post_meta($post_id, 'dias_cierre_post_inicio', absint($raw_dias));
            }
        }

        self::sync_title($post_id);
    }

    public static function register_columns(array $columns): array {
        return [
            'cb'             => $columns['cb'] ?? '<input type="checkbox" />',
            'title'          => __('Edición', 'flacso-uruguay'),
            'seminario'      => __('Seminario (Padre)', 'flacso-uruguay'),
            'estado'         => __('Estado', 'flacso-uruguay'),
            'modalidad'      => __('Modalidad', 'flacso-uruguay'),
            'fechas'         => __('Fechas', 'flacso-uruguay'),
            'preinscripcion' => __('Preinscripción', 'flacso-uruguay'),
            'date'           => __('Publicada', 'flacso-uruguay'),
        ];
    }

    public static function render_column(string $column, int $post_id): void {
        switch ($column) {
            case 'seminario':
                $parent_id = absint(get_post_meta($post_id, self::META_PARENT_ID, true));
                if ($parent_id > 0) {
                    $parent_title = get_the_title($parent_id);
                    $edit_url = get_edit_post_link($parent_id);
                    $filter_url = admin_url('edit.php?post_type=' . self::POST_TYPE . '&seminario_id=' . $parent_id);
                    echo '<strong><a href="' . esc_url($edit_url) . '" title="Editar Seminario">💾 ' . esc_html($parent_title) . '</a></strong><br>';
                    echo '<small><a href="' . esc_url($filter_url) . '" style="color:#0284c7;">🔍 Filtrar sólo este seminario</a></small>';
                } else {
                    echo '<span style="color:#ef4444;font-weight:600;">⚠️ Sin Seminario asignado</span>';
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
            case 'modalidad':
                $mod = (string) get_post_meta($post_id, 'modalidad', true);
                echo $mod !== '' ? esc_html($mod) : '<span style="color:#94a3b8;">—</span>';
                break;
            case 'fechas':
                $inicio = (string) get_post_meta($post_id, 'fecha_inicio', true);
                $fin = (string) get_post_meta($post_id, 'fecha_fin', true);
                echo $inicio !== '' ? esc_html($inicio) : '—';
                if ($fin !== '') {
                    echo ' al ' . esc_html($fin);
                }
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

        $current_parent = isset($_GET['seminario_id']) ? absint($_GET['seminario_id']) : 0;
        $seminarios = get_posts(['post_type' => 'seminario', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        ?>
        <select name="seminario_id">
            <option value=""><?php esc_html_e('Todos los Seminarios', 'flacso-uruguay'); ?></option>
            <?php foreach ($seminarios as $sem) : ?>
                <option value="<?php echo esc_attr((string) $sem->ID); ?>" <?php selected($current_parent, $sem->ID); ?>>
                    <?php echo esc_html($sem->post_title); ?>
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
        if (!empty($_GET['seminario_id'])) {
            $parent_id = absint($_GET['seminario_id']);
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

class_alias('FLACSO_Edicion', 'FLACSO_Edicion_Seminario');

