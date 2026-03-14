<?php

if (!defined('ABSPATH')) {
    exit;
}

class Oferta_Data_Migration {
    private const MENU_SLUG = 'flacso-oferta-migracion';
    private const PREVIEW_TRANSIENT = 'flacso_oferta_migracion_preview_v5';
    private const BACKUP_META = '_flacso_oferta_migracion_backup_v5';

    private const META_KEYS = [
        '_thumbnail_id',
        '_oferta_page_id',
        '_oferta_abrev',
        '_oferta_correo',
        'abreviacion',
        'correo',
        'proximo_inicio',
        'proximo_inicio_precision',
        'inscripciones_abiertas',
        'modalidad_html',
        'duracion_html',
        'objetivos_html',
        'perfil_ingreso_html',
        'requisitos_ingreso_html',
        'malla_curricular_html',
        'calendario_html',
        'perfil_egreso_html',
        'requisitos_egreso_html',
        'titulos_certificaciones_html',
        'malla_curricular',
        'calendario',
        'menciones',
        'coordinacion_academica',
        'equipos',
    ];

    private const HTML_LABELS = [
        'modalidad_html' => 'Modalidad',
        'duracion_html' => 'Duracion',
        'objetivos_html' => 'Objetivos',
        'perfil_ingreso_html' => 'Perfil de ingreso',
        'requisitos_ingreso_html' => 'Requisitos de ingreso',
        'malla_curricular_html' => 'Malla curricular',
        'calendario_html' => 'Calendario',
        'perfil_egreso_html' => 'Perfil de egreso',
        'requisitos_egreso_html' => 'Requisitos de egreso',
        'titulos_certificaciones_html' => 'Titulos y certificaciones',
    ];

    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [self::class, 'add_submenu'], 12);
        add_action('admin_post_flacso_oferta_migracion_run', [self::class, 'handle_run']);
        add_action('admin_post_flacso_oferta_migracion_undo', [self::class, 'handle_undo']);
    }

    public static function add_submenu(): void {
        add_submenu_page(
            'edit.php?post_type=oferta-academica',
            __('Migracion desde paginas', 'flacso-oferta-academica'),
            __('Migracion desde paginas', 'flacso-oferta-academica'),
            'manage_options',
            self::MENU_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $preview = get_transient(self::PREVIEW_TRANSIENT);
        $message = isset($_GET['flacso_msg']) ? sanitize_text_field(wp_unslash($_GET['flacso_msg'])) : '';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Migracion de oferta academica', 'flacso-oferta-academica'); ?></h1>
            <p><?php esc_html_e('Migra datos desde las paginas antiguas al CPT oferta-academica. Incluye previsualizacion, aplicacion y deshacer.', 'flacso-oferta-academica'); ?></p>

            <?php if ($message !== '') : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;">
                <?php wp_nonce_field('flacso_oferta_migracion_run', 'flacso_oferta_migracion_nonce'); ?>
                <input type="hidden" name="action" value="flacso_oferta_migracion_run" />
                <input type="hidden" name="mode" value="preview" />
                <?php submit_button(__('Generar previsualizacion', 'flacso-oferta-academica'), 'secondary', 'submit', false); ?>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
                <?php wp_nonce_field('flacso_oferta_migracion_run', 'flacso_oferta_migracion_nonce'); ?>
                <input type="hidden" name="action" value="flacso_oferta_migracion_run" />
                <input type="hidden" name="mode" value="run" />
                <?php submit_button(__('Aplicar migracion', 'flacso-oferta-academica'), 'primary', 'submit', false); ?>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
                <?php wp_nonce_field('flacso_oferta_migracion_undo', 'flacso_oferta_migracion_undo_nonce'); ?>
                <input type="hidden" name="action" value="flacso_oferta_migracion_undo" />
                <?php submit_button(__('Deshacer ultima migracion', 'flacso-oferta-academica'), 'secondary', 'submit', false); ?>
            </form>

            <?php if (is_array($preview) && !empty($preview)) : ?>
                <h2 style="margin-top:24px;"><?php esc_html_e('Previsualizacion', 'flacso-oferta-academica'); ?></h2>
                <?php foreach ($preview as $row) : ?>
                    <div style="background:#fff;border:1px solid #dcdcde;padding:16px;margin:0 0 16px 0;">
                        <h3 style="margin-top:0;"><?php echo esc_html($row['page']); ?> &rarr; <?php echo esc_html($row['cpt']); ?></h3>
                        <p><strong><?php esc_html_e('Estado destino:', 'flacso-oferta-academica'); ?></strong> <?php echo esc_html($row['target_status']); ?></p>
                        <p><strong><?php esc_html_e('Abreviacion:', 'flacso-oferta-academica'); ?></strong> <?php echo esc_html($row['abreviacion']); ?></p>
                        <p><strong><?php esc_html_e('Correo:', 'flacso-oferta-academica'); ?></strong> <?php echo esc_html($row['correo']); ?></p>
                        <p><strong><?php esc_html_e('Proximo inicio:', 'flacso-oferta-academica'); ?></strong> <?php echo esc_html($row['proximo_inicio']); ?> (<?php echo esc_html($row['precision']); ?>)</p>
                        <p><strong><?php esc_html_e('Inscripciones abiertas:', 'flacso-oferta-academica'); ?></strong> <?php echo !empty($row['inscripciones_abiertas']) ? esc_html__('Si', 'flacso-oferta-academica') : esc_html__('No', 'flacso-oferta-academica'); ?></p>
                        <p><strong><?php esc_html_e('Imagen destacada:', 'flacso-oferta-academica'); ?></strong> <?php echo !empty($row['thumbnail_id']) ? esc_html(sprintf('Si (ID %d)', (int) $row['thumbnail_id'])) : esc_html__('No', 'flacso-oferta-academica'); ?></p>
                        <p><strong><?php esc_html_e('Malla curricular (URL):', 'flacso-oferta-academica'); ?></strong> <?php echo esc_html($row['malla_curricular'] ?: '—'); ?></p>
                        <p><strong><?php esc_html_e('Calendario (URL):', 'flacso-oferta-academica'); ?></strong> <?php echo esc_html($row['calendario'] ?: '—'); ?></p>
                        <p><strong><?php esc_html_e('Secciones HTML detectadas:', 'flacso-oferta-academica'); ?></strong> <?php echo esc_html(!empty($row['html_fields']) ? implode(', ', $row['html_fields']) : '—'); ?></p>
                        <p><strong><?php esc_html_e('Menciones:', 'flacso-oferta-academica'); ?></strong> <?php echo esc_html(!empty($row['menciones']) ? implode(' | ', $row['menciones']) : '—'); ?></p>
                        <p><strong><?php esc_html_e('Coordinacion academica:', 'flacso-oferta-academica'); ?></strong> <?php echo esc_html(!empty($row['coordinacion']) ? implode(' || ', $row['coordinacion']) : '—'); ?></p>
                        <p><strong><?php esc_html_e('Equipos:', 'flacso-oferta-academica'); ?></strong> <?php echo esc_html(!empty($row['equipos']) ? implode(' || ', $row['equipos']) : '—'); ?></p>
                        <p><strong><?php esc_html_e('Observaciones:', 'flacso-oferta-academica'); ?></strong> <?php echo esc_html($row['notes']); ?></p>

                        <?php if (!empty($row['html_previews']) && is_array($row['html_previews'])) : ?>
                            <details style="margin-top:12px;">
                                <summary><?php esc_html_e('Ver resumen de contenido extraido', 'flacso-oferta-academica'); ?></summary>
                                <div style="margin-top:12px;">
                                    <?php foreach ($row['html_previews'] as $label => $snippet) : ?>
                                        <div style="margin-bottom:12px;">
                                            <strong><?php echo esc_html($label); ?></strong>
                                            <div style="background:#f6f7f7;border:1px solid #dcdcde;padding:10px;margin-top:6px;max-height:180px;overflow:auto;">
                                                <?php echo wp_kses_post($snippet ?: '<em>Vacio</em>'); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_run(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No autorizado.', 'flacso-oferta-academica'));
        }

        check_admin_referer('flacso_oferta_migracion_run', 'flacso_oferta_migracion_nonce');

        $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'preview';
        if (!in_array($mode, ['preview', 'run'], true)) {
            $mode = 'preview';
        }

        $mapping = self::get_mapping();
        $preview = [];
        $processed = 0;
        $updated = 0;
        $created = 0;

        foreach ($mapping as $row) {
            $page_id = (int) ($row['page_id'] ?? 0);
            $page = $page_id > 0 ? get_post($page_id) : null;

            if (!$page instanceof \WP_Post) {
                $preview[] = [
                    'page' => sprintf('Pagina #%d', $page_id),
                    'cpt' => __('Sin destino', 'flacso-oferta-academica'),
                    'target_status' => __('Error', 'flacso-oferta-academica'),
                    'abreviacion' => (string) ($row['abreviacion'] ?? ''),
                    'correo' => (string) ($row['correo'] ?? ''),
                    'proximo_inicio' => '',
                    'precision' => '',
                    'inscripciones_abiertas' => false,
                    'thumbnail_id' => 0,
                    'malla_curricular' => '',
                    'calendario' => '',
                    'html_fields' => [],
                    'menciones' => [],
                    'coordinacion' => [],
                    'equipos' => [],
                    'html_previews' => [],
                    'notes' => __('La pagina origen no existe.', 'flacso-oferta-academica'),
                ];
                continue;
            }

            $target_id = self::resolve_target_offer_id($row);
            $target_status = $target_id > 0 ? __('Encontrado', 'flacso-oferta-academica') : __('No encontrado', 'flacso-oferta-academica');

            if ($mode === 'run' && $target_id <= 0) {
                $target_id = self::create_target_offer($row);
                if ($target_id > 0) {
                    $target_status = __('Creado', 'flacso-oferta-academica');
                    $created++;
                }
            }

            $target_post = $target_id > 0 ? get_post($target_id) : null;
            if (!$target_post instanceof \WP_Post || $target_post->post_type !== 'oferta-academica') {
                $preview[] = [
                    'page' => $page->post_title . " (#{$page_id})",
                    'cpt' => __('Sin destino valido', 'flacso-oferta-academica'),
                    'target_status' => __('Error', 'flacso-oferta-academica'),
                    'abreviacion' => (string) ($row['abreviacion'] ?? ''),
                    'correo' => (string) ($row['correo'] ?? ''),
                    'proximo_inicio' => '',
                    'precision' => '',
                    'inscripciones_abiertas' => false,
                    'thumbnail_id' => 0,
                    'malla_curricular' => '',
                    'calendario' => '',
                    'html_fields' => [],
                    'menciones' => [],
                    'coordinacion' => [],
                    'equipos' => [],
                    'html_previews' => [],
                    'notes' => __('No se pudo resolver ni crear el CPT destino.', 'flacso-oferta-academica'),
                ];
                continue;
            }

            $data = self::extract_data($page, $row);
            $processed++;

            if ($mode === 'run') {
                self::backup_current_state($target_post->ID);
                self::save_data($target_post->ID, $data, $row);
                $updated++;
            }

            $detected_fields = [];
            $html_previews = [];
            foreach (self::HTML_LABELS as $key => $label) {
                if (empty($data[$key])) {
                    continue;
                }
                $detected_fields[] = $label;
                $html_previews[$label] = $data[$key];
            }

            $coord_strings = [];
            foreach (($data['coordinacion_academica'] ?? []) as $item) {
                $coord_strings[] = ($item['rol'] ?? '—') . ' | ' . implode(', ', array_map('intval', (array) ($item['docentes'] ?? [])));
            }

            $equipos_strings = [];
            foreach (($data['equipos'] ?? []) as $item) {
                $equipos_strings[] = ($item['nombre'] ?? '—') . ' | ' . implode(', ', array_map('intval', (array) ($item['docentes'] ?? [])));
            }

            $notes = [];
            if (empty($detected_fields)) {
                $notes[] = __('Sin secciones HTML', 'flacso-oferta-academica');
            }
            if (empty($data['thumbnail_id'])) {
                $notes[] = __('Sin imagen destacada', 'flacso-oferta-academica');
            }
            if (empty($data['proximo_inicio'])) {
                $notes[] = __('Fecha de inicio invalida', 'flacso-oferta-academica');
            }

            $preview[] = [
                'page' => $page->post_title . " (#{$page_id})",
                'cpt' => $target_post->post_title . " (#{$target_post->ID})",
                'target_status' => $target_status,
                'abreviacion' => (string) ($row['abreviacion'] ?? ''),
                'correo' => (string) ($data['correo'] ?? ''),
                'proximo_inicio' => (string) ($data['proximo_inicio'] ?? ''),
                'precision' => (string) ($data['proximo_inicio_precision'] ?? ''),
                'inscripciones_abiertas' => !empty($data['inscripciones_abiertas']),
                'thumbnail_id' => (int) ($data['thumbnail_id'] ?? 0),
                'malla_curricular' => (string) ($data['malla_curricular'] ?? ''),
                'calendario' => (string) ($data['calendario'] ?? ''),
                'html_fields' => $detected_fields,
                'menciones' => (array) ($data['menciones'] ?? []),
                'coordinacion' => $coord_strings,
                'equipos' => $equipos_strings,
                'html_previews' => $html_previews,
                'notes' => empty($notes) ? 'OK' : implode(' | ', $notes),
            ];
        }

        set_transient(self::PREVIEW_TRANSIENT, $preview, 15 * MINUTE_IN_SECONDS);

        $message = $mode === 'run'
            ? sprintf(__('Procesados: %1$d. Actualizados: %2$d. Creados: %3$d.', 'flacso-oferta-academica'), $processed, $updated, $created)
            : sprintf(__('Previsualizacion generada. Procesados: %d.', 'flacso-oferta-academica'), $processed);

        self::redirect_with_message($message);
    }

    public static function handle_undo(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No autorizado.', 'flacso-oferta-academica'));
        }

        check_admin_referer('flacso_oferta_migracion_undo', 'flacso_oferta_migracion_undo_nonce');

        $ids = get_posts([
            'post_type' => 'oferta-academica',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_key' => self::BACKUP_META,
            'fields' => 'ids',
        ]);

        $restored = 0;
        foreach ((array) $ids as $post_id) {
            $backup = get_post_meta((int) $post_id, self::BACKUP_META, true);
            if (!is_array($backup)) {
                continue;
            }
            self::restore_backup((int) $post_id, $backup);
            delete_post_meta((int) $post_id, self::BACKUP_META);
            $restored++;
        }

        self::redirect_with_message(sprintf(__('Restaurados: %d.', 'flacso-oferta-academica'), $restored));
    }

    private static function redirect_with_message(string $message): void {
        wp_safe_redirect(add_query_arg([
            'post_type' => 'oferta-academica',
            'page' => self::MENU_SLUG,
            'flacso_msg' => rawurlencode($message),
        ], admin_url('edit.php')));
        exit;
    }

    private static function backup_current_state(int $post_id): void {
        $meta = [];
        foreach (self::META_KEYS as $key) {
            $meta[$key] = [
                'exists' => metadata_exists('post', $post_id, $key),
                'value' => get_post_meta($post_id, $key, true),
            ];
        }

        $tipo = wp_get_object_terms($post_id, 'tipo-oferta-academica', ['fields' => 'ids']);
        $area = wp_get_object_terms($post_id, 'area_tematica', ['fields' => 'ids']);

        update_post_meta($post_id, self::BACKUP_META, [
            'post_title' => get_the_title($post_id),
            'meta' => $meta,
            'terms' => [
                'tipo-oferta-academica' => is_wp_error($tipo) ? [] : array_map('intval', (array) $tipo),
                'area_tematica' => is_wp_error($area) ? [] : array_map('intval', (array) $area),
            ],
        ]);
    }

    private static function restore_backup(int $post_id, array $backup): void {
        if (!empty($backup['post_title'])) {
            wp_update_post([
                'ID' => $post_id,
                'post_title' => sanitize_text_field((string) $backup['post_title']),
            ]);
        }

        $stored_meta = is_array($backup['meta'] ?? null) ? $backup['meta'] : [];
        foreach (self::META_KEYS as $key) {
            $row = isset($stored_meta[$key]) && is_array($stored_meta[$key]) ? $stored_meta[$key] : null;
            $exists = is_array($row) ? !empty($row['exists']) : false;
            if (!$exists) {
                delete_post_meta($post_id, $key);
                continue;
            }
            $value = is_array($row) && array_key_exists('value', $row) ? $row['value'] : '';
            update_post_meta($post_id, $key, $value);
        }

        $terms = is_array($backup['terms'] ?? null) ? $backup['terms'] : [];
        wp_set_object_terms($post_id, array_map('intval', (array) ($terms['tipo-oferta-academica'] ?? [])), 'tipo-oferta-academica', false);
        wp_set_object_terms($post_id, array_map('intval', (array) ($terms['area_tematica'] ?? [])), 'area_tematica', false);
    }

    private static function resolve_target_offer_id(array $row): int {
        $explicit = (int) ($row['cpt_id'] ?? 0);
        if ($explicit > 0) {
            $post = get_post($explicit);
            if ($post instanceof \WP_Post && $post->post_type === 'oferta-academica') {
                return $explicit;
            }
        }

        $page_id = (int) ($row['page_id'] ?? 0);
        if ($page_id > 0) {
            $q = get_posts([
                'post_type' => 'oferta-academica',
                'post_status' => 'any',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_key' => '_oferta_page_id',
                'meta_value' => $page_id,
            ]);
            if (!empty($q)) {
                return (int) $q[0];
            }
        }

        $abbr = sanitize_text_field((string) ($row['abreviacion'] ?? ''));
        if ($abbr !== '') {
            foreach (['abreviacion', '_oferta_abrev'] as $meta_key) {
                $q = get_posts([
                    'post_type' => 'oferta-academica',
                    'post_status' => 'any',
                    'posts_per_page' => 1,
                    'fields' => 'ids',
                    'meta_key' => $meta_key,
                    'meta_value' => $abbr,
                ]);
                if (!empty($q)) {
                    return (int) $q[0];
                }
            }
        }

        $name = sanitize_text_field((string) ($row['nombre'] ?? ''));
        if ($name !== '') {
            global $wpdb;
            if ($wpdb instanceof \wpdb) {
                $exact = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_status IN ('publish','draft','pending','private','future') AND post_title=%s LIMIT 1",
                    'oferta-academica',
                    $name
                ));
                if (!empty($exact)) {
                    return (int) $exact;
                }
            }
        }

        return 0;
    }

    private static function create_target_offer(array $row): int {
        $name = sanitize_text_field((string) ($row['nombre'] ?? ''));
        if ($name === '') {
            return 0;
        }

        $post_id = wp_insert_post([
            'post_type' => 'oferta-academica',
            'post_status' => 'publish',
            'post_title' => $name,
        ]);

        if (is_wp_error($post_id) || !$post_id) {
            return 0;
        }

        $abbr = sanitize_text_field((string) ($row['abreviacion'] ?? ''));
        $correo = sanitize_email((string) ($row['correo'] ?? ''));
        if ($abbr !== '') {
            update_post_meta($post_id, 'abreviacion', $abbr);
            update_post_meta($post_id, '_oferta_abrev', $abbr);
        }
        if ($correo !== '') {
            update_post_meta($post_id, 'correo', $correo);
            update_post_meta($post_id, '_oferta_correo', $correo);
        }

        self::assign_tipo_term((int) $post_id, (string) ($row['tipo'] ?? ''));

        return (int) $post_id;
    }

    private static function extract_data(\WP_Post $page, array $row): array {
        $content = (string) $page->post_content;
        $main = self::extract_nth_accordion($content, 1);
        $secondary = self::extract_nth_accordion($content, 2);

        $panes = self::extract_panes($main);
        $team_panes = self::extract_panes($secondary);

        // Fuente de verdad: tabla de mapeo (no desde el contenido de la pagina).
        $precision = sanitize_text_field((string) ($row['precision'] ?? 'day'));
        $correo_tabla = sanitize_email((string) ($row['correo'] ?? ''));
        $proximo_inicio = self::normalize_date_text((string) ($row['proximo_inicio'] ?? ''), $precision);

        $data = [
            'titulo' => sanitize_text_field((string) ($row['nombre'] ?? $page->post_title)),
            'thumbnail_id' => (int) get_post_thumbnail_id($page->ID),
            'correo' => $correo_tabla,
            'proximo_inicio_precision' => $precision,
            'proximo_inicio' => $proximo_inicio,
            'inscripciones_abiertas' => !empty($row['inscripciones_abiertas']) ? '1' : '',
            'modalidad_html' => '',
            'duracion_html' => '',
            'objetivos_html' => '',
            'perfil_ingreso_html' => '',
            'requisitos_ingreso_html' => '',
            'malla_curricular_html' => '',
            'calendario_html' => '',
            'perfil_egreso_html' => '',
            'requisitos_egreso_html' => '',
            'titulos_certificaciones_html' => '',
            'malla_curricular' => '',
            'calendario' => '',
            'menciones' => [],
            'coordinacion_academica' => [],
            'equipos' => [],
        ];

        foreach ($panes as $pane) {
            $field = self::resolve_html_field((string) ($pane['title'] ?? ''));
            if (!$field) {
                continue;
            }
            $data[$field] = wp_kses_post(trim((string) ($pane['content'] ?? '')));
        }

        if (empty($data['perfil_egreso_html'])) {
            $data['perfil_egreso_html'] = self::extract_field_by_keyword($panes, ['PERFIL', 'EGRESO']);
        }
        if (empty($data['requisitos_egreso_html'])) {
            $data['requisitos_egreso_html'] = self::extract_field_by_keyword($panes, ['REQUISITOS', 'EGRESO']);
        }
        if (empty($data['titulos_certificaciones_html'])) {
            $data['titulos_certificaciones_html'] = self::extract_field_by_keyword($panes, ['TITULOS']);
            if (empty($data['titulos_certificaciones_html'])) {
                $data['titulos_certificaciones_html'] = self::extract_field_by_keyword($panes, ['CERTIFICACIONES']);
            }
        }

        if (!empty($data['malla_curricular_html'])) {
            $data['malla_curricular'] = self::extract_first_url($data['malla_curricular_html']);
        }
        if (!empty($data['calendario_html'])) {
            $data['calendario'] = self::extract_first_url($data['calendario_html']);
        }

        // Las menciones se cargan manualmente: no inferir desde contenido HTML.
        $data['menciones'] = [];

        $data['coordinacion_academica'] = self::extract_coordinacion($content);
        $data['equipos'] = self::extract_equipos($team_panes);

        return $data;
    }

    private static function save_data(int $post_id, array $data, array $row): void {
        if (!get_post($post_id) || get_post_type($post_id) !== 'oferta-academica') {
            return;
        }

        // Fuente de verdad: tabla de mapeo (no desde pagina).
        $mapped_precision = self::sanitize_by_schema('proximo_inicio_precision', (string) ($row['precision'] ?? 'day'));
        $mapped_proximo_inicio = self::sanitize_by_schema(
            'proximo_inicio',
            self::normalize_date_text((string) ($row['proximo_inicio'] ?? ''), $mapped_precision ?: 'day')
        );
        $mapped_correo = self::sanitize_by_schema('correo', (string) ($row['correo'] ?? ''));

        $data['proximo_inicio_precision'] = $mapped_precision;
        $data['proximo_inicio'] = $mapped_proximo_inicio;
        $data['correo'] = $mapped_correo;

        wp_update_post([
            'ID' => $post_id,
            'post_title' => sanitize_text_field((string) ($data['titulo'] ?? get_the_title($post_id))),
        ]);

        $page_id = (int) ($row['page_id'] ?? 0);
        if ($page_id > 0) {
            update_post_meta($post_id, '_oferta_page_id', $page_id);
        }

        if (!empty($data['thumbnail_id'])) {
            update_post_meta($post_id, '_thumbnail_id', (int) $data['thumbnail_id']);
        } else {
            delete_post_meta($post_id, '_thumbnail_id');
        }

        $abbr = self::sanitize_by_schema('abreviacion', (string) ($row['abreviacion'] ?? ''));
        if ($abbr !== '') {
            update_post_meta($post_id, 'abreviacion', $abbr);
            update_post_meta($post_id, '_oferta_abrev', $abbr);
        }

        $correo = $mapped_correo;
        if ($correo !== '') {
            update_post_meta($post_id, 'correo', $correo);
            update_post_meta($post_id, '_oferta_correo', $correo);
        } else {
            delete_post_meta($post_id, 'correo');
            delete_post_meta($post_id, '_oferta_correo');
        }

        self::assign_tipo_term($post_id, (string) ($row['tipo'] ?? ''));

        $meta_fields = [
            'proximo_inicio',
            'proximo_inicio_precision',
            'inscripciones_abiertas',
            'modalidad_html',
            'duracion_html',
            'objetivos_html',
            'perfil_ingreso_html',
            'requisitos_ingreso_html',
            'malla_curricular_html',
            'calendario_html',
            'perfil_egreso_html',
            'requisitos_egreso_html',
            'titulos_certificaciones_html',
            'malla_curricular',
            'calendario',
        ];

        foreach ($meta_fields as $meta_key) {
            $value = self::sanitize_by_schema($meta_key, $data[$meta_key] ?? '');
            self::update_meta_value($post_id, $meta_key, $value);
        }

        self::update_meta_value($post_id, 'menciones', self::sanitize_by_schema('menciones', $data['menciones'] ?? []));
        self::update_meta_value($post_id, 'coordinacion_academica', self::sanitize_by_schema('coordinacion_academica', $data['coordinacion_academica'] ?? []));
        self::update_meta_value($post_id, 'equipos', self::sanitize_by_schema('equipos', $data['equipos'] ?? []));
    }

    private static function sanitize_by_schema(string $key, $value) {
        if (!class_exists('Oferta_Data_Schema')) {
            return $value;
        }

        switch ($key) {
            case 'correo':
                return Oferta_Data_Schema::sanitize_email($value);
            case 'proximo_inicio':
                return Oferta_Data_Schema::sanitize_proximo_inicio($value);
            case 'proximo_inicio_precision':
                return Oferta_Data_Schema::sanitize_precision($value);
            case 'inscripciones_abiertas':
                return Oferta_Data_Schema::sanitize_boolean($value);
            case 'malla_curricular':
            case 'calendario':
                return Oferta_Data_Schema::sanitize_url($value);
            case 'abreviacion':
                return Oferta_Data_Schema::sanitize_abreviacion($value);
            case 'menciones':
                return Oferta_Data_Schema::sanitize_string_array(is_array($value) ? $value : []);
            case 'coordinacion_academica':
                return Oferta_Data_Schema::sanitize_personnel_groups_data(is_array($value) ? $value : [], 'rol');
            case 'equipos':
                return Oferta_Data_Schema::sanitize_personnel_groups_data(is_array($value) ? $value : [], 'nombre');
            default:
                return Oferta_Data_Schema::sanitize_html($value);
        }
    }

    private static function update_meta_value(int $post_id, string $key, $value): void {
        if (is_bool($value)) {
            update_post_meta($post_id, $key, $value ? 1 : 0);
            return;
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

    private static function assign_tipo_term(int $post_id, string $tipo): void {
        $n = self::normalize_heading($tipo);
        $slug = '';
        if (strpos($n, 'MAESTRIA') !== false) {
            $slug = 'maestria';
        } elseif (strpos($n, 'ESPECIALIZACION') !== false) {
            $slug = 'especializacion';
        } elseif (strpos($n, 'DIPLOMADO') !== false) {
            $slug = 'diplomado';
        } elseif (strpos($n, 'DIPLOMA') !== false) {
            $slug = 'diploma';
        }

        if ($slug !== '' && term_exists($slug, 'tipo-oferta-academica')) {
            wp_set_object_terms($post_id, [$slug], 'tipo-oferta-academica', false);
        }
    }

    private static function extract_nth_accordion(string $content, int $n = 1): string {
        if (!preg_match_all('/<!--\s*wp:kadence\/accordion\b.*?-->.*?<!--\s*\/wp:kadence\/accordion\s*-->/is', $content, $matches)) {
            return '';
        }
        $idx = max(0, $n - 1);
        return isset($matches[0][$idx]) ? (string) $matches[0][$idx] : '';
    }

    private static function extract_panes(string $accordion): array {
        $result = [];
        if ($accordion === '') {
            return $result;
        }

        if (!preg_match_all('/<!--\s*wp:kadence\/pane\b.*?-->(.*?)<!--\s*\/wp:kadence\/pane\s*-->/is', $accordion, $matches, PREG_SET_ORDER)) {
            return $result;
        }

        foreach ($matches as $m) {
            $full = (string) ($m[0] ?? '');
            $inner = (string) ($m[1] ?? '');
            $title = '';

            if (preg_match('/<span[^>]*class="[^"]*kt-blocks-accordion-title[^"]*"[^>]*>(.*?)<\/span>/is', $full, $t)) {
                $title = trim(wp_strip_all_tags(html_entity_decode((string) $t[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            }

            $panel = trim($inner);
            if (preg_match('/<div[^>]*class="[^"]*kt-accordion-panel-inner[^"]*"[^>]*>(.*?)<\/div>/is', $inner, $c)) {
                $panel = trim((string) $c[1]);
            }

            if ($title !== '') {
                $result[] = ['title' => $title, 'content' => $panel];
            }
        }

        return $result;
    }

    private static function normalize_heading(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = wp_strip_all_tags($text);
        $text = remove_accents($text);
        $text = mb_strtoupper($text, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string) $text);
    }

    private static function resolve_html_field(string $title): ?string {
        $t = self::normalize_heading($title);
        $aliases = [
            'modalidad_html' => ['MODALIDAD'],
            'duracion_html' => ['DURACION'],
            'objetivos_html' => ['OBJETIVOS'],
            'perfil_ingreso_html' => ['PERFIL DE INGRESO'],
            'requisitos_ingreso_html' => ['REQUISITOS DE INGRESO'],
            'malla_curricular_html' => ['MALLA CURRICULAR'],
            'calendario_html' => ['CALENDARIO'],
            'perfil_egreso_html' => ['PERFIL DE EGRESO', 'PERFIL EGRESO', 'PERFIL DEL EGRESO'],
            'requisitos_egreso_html' => ['REQUISITOS DE EGRESO', 'REQUISITOS EGRESO'],
            'titulos_certificaciones_html' => ['TITULOS Y CERTIFICACIONES', 'TITULOS', 'CERTIFICACIONES', 'TITULOS CERTIFICACIONES'],
        ];

        foreach ($aliases as $field => $titles) {
            foreach ($titles as $candidate) {
                if ($t === $candidate) {
                    return $field;
                }
            }
        }

        if (strpos($t, 'PERFIL') !== false && strpos($t, 'EGRESO') !== false) {
            return 'perfil_egreso_html';
        }
        if (strpos($t, 'REQUISITOS') !== false && strpos($t, 'EGRESO') !== false) {
            return 'requisitos_egreso_html';
        }
        if (strpos($t, 'TITULOS') !== false || strpos($t, 'CERTIFICACIONES') !== false) {
            return 'titulos_certificaciones_html';
        }

        return null;
    }

    private static function extract_field_by_keyword(array $panes, array $keywords): string {
        foreach ($panes as $pane) {
            $title = self::normalize_heading((string) ($pane['title'] ?? ''));
            $ok = true;
            foreach ($keywords as $kw) {
                if (strpos($title, self::normalize_heading((string) $kw)) === false) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return wp_kses_post(trim((string) ($pane['content'] ?? '')));
            }
        }
        return '';
    }

    private static function extract_first_url(string $html): string {
        if (preg_match('/https?:\/\/[^\s"\']+/i', $html, $m)) {
            return esc_url_raw((string) $m[0]);
        }
        return '';
    }

    private static function normalize_date_text(string $text, string $precision = 'day'): string {
        $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', $text);

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $text, $m)) {
            $day = str_pad((string) intval($m[1]), 2, '0', STR_PAD_LEFT);
            $month = str_pad((string) intval($m[2]), 2, '0', STR_PAD_LEFT);
            $year = (string) $m[3];
            if ($precision === 'day') {
                return $year . '-' . $month . '-' . $day;
            }
            if ($precision === 'month') {
                return $year . '-' . $month;
            }
            if ($precision === 'year') {
                return $year;
            }
        }

        $text_lc = mb_strtolower($text, 'UTF-8');
        $month_map = [
            'enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04',
            'mayo' => '05', 'junio' => '06', 'julio' => '07', 'agosto' => '08',
            'septiembre' => '09', 'setiembre' => '09', 'octubre' => '10',
            'noviembre' => '11', 'diciembre' => '12',
        ];

        if ($precision === 'day') {
            if (preg_match('/^(\d{1,2})\s+de\s+([[:alpha:]áéíóúñ]+)\s+de\s+(\d{4})$/u', $text_lc, $m)) {
                $day = str_pad((string) intval($m[1]), 2, '0', STR_PAD_LEFT);
                $month_name = (string) $m[2];
                $year = (string) $m[3];
                if (isset($month_map[$month_name])) {
                    return $year . '-' . $month_map[$month_name] . '-' . $day;
                }
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text_lc)) {
                return $text_lc;
            }
        }

        if ($precision === 'month') {
            if (preg_match('/^([[:alpha:]áéíóúñ]+)\s+de\s+(\d{4})$/u', $text_lc, $m)) {
                $month_name = (string) $m[1];
                $year = (string) $m[2];
                if (isset($month_map[$month_name])) {
                    return $year . '-' . $month_map[$month_name];
                }
            }
            if (preg_match('/^\d{4}-\d{2}$/', $text_lc)) {
                return $text_lc;
            }
        }

        if ($precision === 'year' && preg_match('/^\d{4}$/', $text_lc)) {
            return $text_lc;
        }

        return '';
    }

    private static function extract_menciones(string $titulos_html, string $perfil_egreso_html, string $requisitos_egreso_html): array {
        $text = wp_strip_all_tags($titulos_html . "\n" . $perfil_egreso_html . "\n" . $requisitos_egreso_html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        $out = [];
        if (preg_match_all('/menci[oó]n en ([^.;\n]+)/iu', (string) $text, $matches)) {
            foreach ($matches[1] as $match) {
                $parts = preg_split('/\s+y\s+|\s+o\s+/u', trim((string) $match));
                foreach ((array) $parts as $part) {
                    $part = trim(wp_strip_all_tags((string) $part), " \t\n\r\0\x0B,.;:");
                    if ($part !== '') {
                        $out[] = sanitize_text_field($part);
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($out)));
    }

    private static function extract_coordinacion(string $content): array {
        $result = [];
        if (!preg_match_all('/<!--\s*wp:(?:flacso-uruguay|flacso)\/docente-destacado\s+(\{.*?\})\s*\/-->/is', $content, $matches, PREG_SET_ORDER)) {
            return $result;
        }

        foreach ($matches as $m) {
            $json = json_decode((string) $m[1], true);
            if (!is_array($json)) {
                continue;
            }

            $doc_id = isset($json['docId']) ? (int) $json['docId'] : (isset($json['docenteId']) ? (int) $json['docenteId'] : 0);
            $role = isset($json['role']) ? trim((string) $json['role']) : (isset($json['cargo']) ? trim((string) $json['cargo']) : '');
            $role = sanitize_text_field($role);

            if ($doc_id <= 0 || $role === '') {
                continue;
            }

            $found = false;
            foreach ($result as &$item) {
                if (($item['rol'] ?? '') !== $role) {
                    continue;
                }
                $item['docentes'][] = $doc_id;
                $item['docentes'] = array_values(array_unique(array_map('intval', $item['docentes'])));
                $found = true;
                break;
            }
            unset($item);

            if (!$found) {
                $result[] = ['rol' => $role, 'docentes' => [$doc_id]];
            }
        }

        return $result;
    }

    private static function extract_equipos(array $team_panes): array {
        $result = [];

        foreach ($team_panes as $pane) {
            $title = sanitize_text_field(trim((string) ($pane['title'] ?? '')));
            $content = (string) ($pane['content'] ?? '');
            if ($title === '') {
                continue;
            }

            $docentes = [];
            if (preg_match('/<!--\s*wp:(?:flacso-uruguay|flacso)\/docentes-grupo\s+(\{.*?\})\s*\/-->/is', $content, $m)) {
                $json = json_decode((string) $m[1], true);
                if (is_array($json)) {
                    if (!empty($json['docenteIds']) && is_array($json['docenteIds'])) {
                        $docentes = array_values(array_filter(array_map('intval', $json['docenteIds'])));
                    } elseif (!empty($json['docentes']) && is_array($json['docentes'])) {
                        $docentes = array_values(array_filter(array_map('intval', $json['docentes'])));
                    }
                }
            }

            if (!empty($docentes)) {
                $result[] = ['nombre' => $title, 'docentes' => $docentes];
            }
        }

        return $result;
    }

    private static function get_mapping(): array {
        return [
            ['page_id' => 12330, 'cpt_id' => 24160, 'abreviacion' => 'EDUTIC', 'tipo' => 'Maestria', 'nombre' => 'Maestria en Educacion, Innovacion y Tecnologias', 'correo' => 'edutic@flacso.edu.uy', 'proximo_inicio' => '16/04/2026', 'precision' => 'day', 'inscripciones_abiertas' => true],
            ['page_id' => 12336, 'cpt_id' => 24161, 'abreviacion' => 'MESYP', 'tipo' => 'Maestria', 'nombre' => 'Maestria en Educacion, Sociedad y Politica', 'correo' => 'mesyp@flacso.edu.uy', 'proximo_inicio' => '20/03/2026', 'precision' => 'day', 'inscripciones_abiertas' => true],
            ['page_id' => 12343, 'cpt_id' => 24162, 'abreviacion' => 'MG', 'tipo' => 'Maestria', 'nombre' => 'Maestria en Genero', 'correo' => 'maestriagenero@flacso.edu.uy', 'proximo_inicio' => '8/04/2026', 'precision' => 'day', 'inscripciones_abiertas' => true],
            ['page_id' => 12310, 'cpt_id' => 24163, 'abreviacion' => 'EAPET', 'tipo' => 'Especializacion', 'nombre' => 'Especializacion en Analisis, Produccion y Edicion de Textos', 'correo' => 'inscripciones@flacso.edu.uy', 'proximo_inicio' => '15/04/2026', 'precision' => 'day', 'inscripciones_abiertas' => true],
            ['page_id' => 12316, 'cpt_id' => 24164, 'abreviacion' => 'EGCCD', 'tipo' => 'Especializacion', 'nombre' => 'Especializacion en Genero, Cambio Climatico y Desastres', 'correo' => 'genero@flacso.edu.uy', 'proximo_inicio' => '8/04/2026', 'precision' => 'day', 'inscripciones_abiertas' => true],
            ['page_id' => 12278, 'cpt_id' => 24165, 'abreviacion' => 'DEPPI', 'tipo' => 'Diplomado', 'nombre' => 'Diplomado de Especializacion en Genero con Orientacion en Politicas Publicas Integrales', 'correo' => 'genero@flacso.edu.uy', 'proximo_inicio' => '8/04/2026', 'precision' => 'day', 'inscripciones_abiertas' => true],
            ['page_id' => 14444, 'cpt_id' => 24166, 'abreviacion' => 'DESI', 'tipo' => 'Diplomado', 'nombre' => 'Diplomado de Especializacion en Genero con Orientacion en Salud Integral', 'correo' => 'genero@flacso.edu.uy', 'proximo_inicio' => '8/04/2026', 'precision' => 'day', 'inscripciones_abiertas' => true],
            ['page_id' => 12282, 'cpt_id' => 24167, 'abreviacion' => 'DEVBG', 'tipo' => 'Diplomado', 'nombre' => 'Diplomado de Especializacion en Genero con Orientacion en Violencia Basada en Genero', 'correo' => 'genero@flacso.edu.uy', 'proximo_inicio' => '8/04/2026', 'precision' => 'day', 'inscripciones_abiertas' => true],
            ['page_id' => 12288, 'cpt_id' => 24168, 'abreviacion' => 'DEVNNA', 'tipo' => 'Diplomado', 'nombre' => 'Diplomado de Especializacion sobre Violencias Hacia Ninas, Ninos y Adolescentes', 'correo' => 'dsvnna@flacso.edu.uy', 'proximo_inicio' => '8/04/2026', 'precision' => 'day', 'inscripciones_abiertas' => true],
            ['page_id' => 13202, 'cpt_id' => 24169, 'abreviacion' => 'DCCH', 'tipo' => 'Diploma', 'nombre' => 'Diploma Comprendiendo China: Cultura, Filosofia y Construccion Historica del Gigante Asiatico', 'correo' => 'inscripciones@flacso.edu.uy', 'proximo_inicio' => '8/04/2026', 'precision' => 'day', 'inscripciones_abiertas' => true],
            ['page_id' => 12295, 'cpt_id' => 24170, 'abreviacion' => 'DAVIA', 'tipo' => 'Diploma', 'nombre' => 'Diploma en Abordaje de las Violencias Hacia las Infancias y Adolescencias', 'correo' => 'inscripciones@flacso.edu.uy', 'proximo_inicio' => '8/04/2026', 'precision' => 'day', 'inscripciones_abiertas' => true],
            ['page_id' => 12299, 'cpt_id' => 24171, 'abreviacion' => 'DG', 'tipo' => 'Diploma', 'nombre' => 'Diploma en Genero', 'correo' => 'inscripciones@flacso.edu.uy', 'proximo_inicio' => '8/04/2026', 'precision' => 'day', 'inscripciones_abiertas' => true],
            ['page_id' => 20668, 'cpt_id' => 24172, 'abreviacion' => 'IAPE', 'tipo' => 'Diploma', 'nombre' => 'Diploma en IA y Practicas de Ensenanza', 'correo' => 'inscripciones@flacso.edu.uy', 'proximo_inicio' => '6/04/2026', 'precision' => 'day', 'inscripciones_abiertas' => true],
            ['page_id' => 12302, 'cpt_id' => 24173, 'abreviacion' => 'DIDYP', 'tipo' => 'Diploma', 'nombre' => 'Diploma en Infancias, Derechos y Politicas Publicas', 'correo' => 'inscripciones@flacso.edu.uy', 'proximo_inicio' => '8/04/2026', 'precision' => 'day', 'inscripciones_abiertas' => true],
            ['page_id' => 14657, 'cpt_id' => 24174, 'abreviacion' => 'DSMSYT', 'tipo' => 'Diploma', 'nombre' => 'Diploma en Salud Mental, Subjetividad y Trabajo', 'correo' => 'inscripciones@flacso.edu.uy', 'proximo_inicio' => '11/04/2026', 'precision' => 'day', 'inscripciones_abiertas' => true],
        ];
    }
}

