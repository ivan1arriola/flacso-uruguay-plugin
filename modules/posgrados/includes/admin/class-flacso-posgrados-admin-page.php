<?php

if (!class_exists('FLACSO_Posgrados_Admin_Page')) {
    class FLACSO_Posgrados_Admin_Page {
        private const NONCE_ACTION = 'flacso_pos_save';
        private const NONCE_FIELD  = 'flacso_pos_nonce';
        private const UNSPECIFIED_TIPO = '__flacso_pos_untyped__';

        private static $submenu_tipo_map = [];

        public static function register_menu(): void {
            self::ensure_submenu_map();

            add_menu_page(
                __('Gestor de Posgrados', 'flacso-posgrados-docentes'),
                __('Posgrados', 'flacso-posgrados-docentes'),
                FLACSO_Posgrados_Fields::CAPABILITY,
                FLACSO_POSGRADOS_SLUG,
                [__CLASS__, 'render_admin_page'],
                'dashicons-welcome-learn-more',
                26
            );

            foreach (self::$submenu_tipo_map as $slug => $tipo) {
                if ($slug === FLACSO_POSGRADOS_SLUG || $tipo === '') {
                    continue;
                }

                add_submenu_page(
                    FLACSO_POSGRADOS_SLUG,
                    sprintf(__('Posgrados: %s', 'flacso-posgrados-docentes'), esc_html($tipo)),
                    self::format_tipo_menu_label($tipo),
                    FLACSO_Posgrados_Fields::CAPABILITY,
                    $slug,
                    [__CLASS__, 'render_admin_page']
                );
            }
        }

        public static function enqueue_assets(string $hook): void {
            if (strpos($hook, FLACSO_POSGRADOS_SLUG) === false) {
                return;
            }

            wp_enqueue_media();

            wp_enqueue_style(
                'flacso-posgrados-docentes-bootstrap',
                'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
                [],
                '5.3.3'
            );

            wp_enqueue_script(
                'flacso-posgrados-docentes-bootstrap',
                'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
                [],
                '5.3.3',
                true
            );

            $custom_css = <<<CSS
.flacso-pos-card-grid .flacso-pos-card {
    border-radius: 0.75rem;
    border: 1px solid #e9ecef;
}
.flacso-pos-card-media {
    overflow: hidden;
    border-radius: 0.75rem 0.75rem 0 0;
}
.flacso-pos-card-media img {
    width: 100%;
    height: 220px;
    object-fit: cover;
    display: block;
}
.flacso-pos-card .card-header {
    background-color: #f8f9fa;
}
.flacso-pos-card .card-header .badge {
    font-size: 0.75rem;
    padding: 0.35rem 0.75rem;
}
.flacso-pos-abreviacion {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.flacso-pos-field .form-label {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6c757d;
}
.flacso-pos-media-preview {
    min-height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.flacso-pos-media-placeholder {
    color: #6c757d;
    font-size: 0.85rem;
}
.flacso-pos-fieldset {
    border: 1px dashed #ced4da;
    background-color: #fff;
    border-radius: 0.85rem;
    padding: 1rem 1.25rem;
}
.flacso-pos-fieldset-title {
    font-size: 0.75rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #6c757d;
    font-weight: 600;
    margin-bottom: 0.75rem;
}
.flacso-pos-fieldset .flacso-pos-field {
    margin-bottom: 0;
}
.flacso-pos-pagination .pagination .page-link {
    border: none;
    border-radius: 999px;
    min-width: 38px;
    text-align: center;
    color: #495057;
}
.flacso-pos-pagination .pagination .page-item.active .page-link {
    background-color: #0d6efd;
    color: #fff;
}
.flacso-pos-type-section .badge {
    font-weight: 500;
}
.flacso-pos-dashboard {
    margin-top: 1.5rem;
    margin-bottom: 2rem;
    padding: 1.5rem 1.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 1rem;
    background: #fff;
    box-shadow: 0 15px 30px rgba(15, 23, 42, 0.04);
}
.flacso-pos-dashboard__header {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.flacso-pos-dashboard__header p {
    color: #6c757d;
    margin: 0;
}
.flacso-pos-dashboard__stats {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    margin-top: 1.25rem;
}
.flacso-pos-card {
    border: 1px solid #e9ecef;
    border-radius: 0.85rem;
    padding: 1rem 1.25rem;
    background: #fff;
}
.flacso-pos-card__label {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6c757d;
}
.flacso-pos-card__value {
    display: block;
    font-size: 2rem;
    font-weight: 600;
    color: #0d1b2a;
}
.flacso-pos-card__meta {
    color: #6c757d;
    font-size: 0.9rem;
}
.flacso-pos-dashboard__grid {
    display: grid;
    gap: 1.25rem;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    margin-top: 1.25rem;
}
.flacso-pos-list {
    list-style: none;
    padding: 0;
    margin: 0.75rem 0 0;
}
.flacso-pos-list li {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.65rem 0;
    border-bottom: 1px solid #f1f3f5;
}
.flacso-pos-list li:last-child {
    border-bottom: 0;
}
.flacso-pos-list strong {
    display: block;
    font-size: 0.98rem;
}
.flacso-pos-muted {
    color: #6c757d;
    font-size: 0.85rem;
}
.flacso-pos-shortcuts {
    display: grid;
    gap: 0.75rem;
    margin-top: 0.75rem;
}
.flacso-pos-shortcut {
    display: flex;
    gap: 0.8rem;
    padding: 0.9rem 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.85rem;
    background: #f8fafc;
    text-decoration: none;
    color: #0d1b2a;
    transition: border-color 0.2s ease, background 0.2s ease;
}
.flacso-pos-shortcut:hover {
    border-color: #0d6efd;
    background: #fff;
}
.flacso-pos-shortcut .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
    color: #0d6efd;
}
.flacso-pos-shortcut__body {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.flacso-pos-shortcut__body span {
    color: #6c757d;
    font-size: 0.85rem;
}
.flacso-pos-section-heading {
    margin-top: 2rem;
}
.flacso-pos-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    border-radius: 999px;
    padding: 0.15rem 0.75rem;
    background: #f1f3f5;
    margin-right: 0.35rem;
    margin-bottom: 0.35rem;
}
.flacso-pos-dashboard__empty {
    margin: 1rem 0 0;
    font-style: italic;
    color: #6c757d;
}
.flacso-pos-dashboard__highlight {
    margin-top: 0.5rem;
    font-size: 0.9rem;
    color: #495057;
}
.flacso-pos-dashboard__list-actions .button-link {
    padding: 0;
    height: auto;
    line-height: 1.3;
}
.flacso-pos-dashboard__list-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.flacso-pos-dashboard__grid .flacso-pos-card h3 {
    margin-top: 0;
}
@media (max-width: 782px) {
    .flacso-pos-dashboard {
        padding: 1rem;
    }
}
CSS;

            wp_add_inline_style('flacso-posgrados-docentes-bootstrap', $custom_css);

            $relative = 'assets/js/admin-media.js';
            $path     = FLACSO_POSGRADOS_PLUGIN_PATH . $relative;
            $url      = FLACSO_POSGRADOS_PLUGIN_URL . $relative;
            $version  = file_exists($path) ? filemtime($path) : time();

            wp_enqueue_script(
                'flacso-posgrados-docentes-admin-media',
                $url,
                ['jquery'],
                $version,
                true
            );
        }

        public static function handle_bulk_save(): void {
            if (!self::is_valid_bulk_request()) {
                return;
            }

            $fields = FLACSO_Posgrados_Fields::get_fields();

            foreach (self::get_posted_ids() as $post_id) {
                if (!current_user_can('edit_post', $post_id)) {
                    continue;
                }
                self::persist_fields_for_post($post_id, $fields);
            }

            wp_safe_redirect(self::build_redirect_url());
            exit;
        }

        private static function is_valid_bulk_request(): bool {
            if (!is_admin() || !current_user_can(FLACSO_Posgrados_Fields::CAPABILITY)) {
                return false;
            }

            if (empty($_POST[self::NONCE_FIELD])) {
                return false;
            }

            $nonce = wp_unslash($_POST[self::NONCE_FIELD]);

            return (bool) wp_verify_nonce($nonce, self::NONCE_ACTION);
        }

        private static function get_posted_ids(): array {
            if (empty($_POST['post_id']) || !is_array($_POST['post_id'])) {
                return [];
            }

            $ids = array_map('intval', wp_unslash($_POST['post_id']));
            $ids = array_filter($ids);

            return array_unique($ids);
        }

        private static function persist_fields_for_post(int $post_id, array $fields): void {
            foreach ($fields as $key => $config) {
                if (!empty($config['readonly'])) {
                    continue;
                }

                $value = self::sanitize_submitted_value(
                    self::get_submitted_value($post_id, $key, $config),
                    $config['sanitize'] ?? 'sanitize_text_field'
                );

                if (($config['source'] ?? 'meta') === 'post' && $key === 'post_excerpt') {
                    wp_update_post(['ID' => $post_id, 'post_excerpt' => $value]);
                    continue;
                }

                if ($key === 'posgrado_activo') {
                    update_post_meta($post_id, $key, (bool) $value);
                } else {
                    update_post_meta($post_id, $key, $value);
                }
            }
        }

        private static function sanitize_submitted_value($value, $callback) {
            return is_callable($callback) ? call_user_func($callback, $value) : $value;
        }

        private static function get_submitted_value(int $post_id, string $key, array $config) {
            if (($config['type'] ?? 'text') === 'checkbox') {
                return isset($_POST[$key][$post_id]) ? '1' : '0';
            }

            if (!isset($_POST[$key][$post_id])) {
                return '';
            }

            $value = $_POST[$key][$post_id];

            if (is_array($value)) {
                return '';
            }

            return wp_unslash($value);
        }

        private static function build_redirect_url(): string {
            $referer = wp_get_referer() ?: admin_url('admin.php?page=' . FLACSO_POSGRADOS_SLUG);
            $referer = remove_query_arg(['updated'], $referer);

            return add_query_arg('updated', '1', $referer);
        }

        private static function ensure_submenu_map(): void {
            if (!empty(self::$submenu_tipo_map)) {
                return;
            }

            self::$submenu_tipo_map = [
                FLACSO_POSGRADOS_SLUG => '',
            ];

            foreach (FLACSO_Posgrados_Fields::allowed_tipos() as $tipo) {
                $slug = FLACSO_POSGRADOS_SLUG . '_' . sanitize_title($tipo);
                self::$submenu_tipo_map[$slug] = $tipo;
            }
        }

        private static function get_current_page_slug(): string {
            return sanitize_key($_GET['page'] ?? FLACSO_POSGRADOS_SLUG);
        }

        private static function resolve_tipo_from_page_slug(string $slug): string {
            self::ensure_submenu_map();
            return self::$submenu_tipo_map[$slug] ?? '';
        }

        private static function format_tipo_menu_label(string $tipo): string {
            $map = [
                'Maestria'        => __('Maestrías', 'flacso-posgrados-docentes'),
                'Especializacion' => __('Especializaciones', 'flacso-posgrados-docentes'),
                'Diplomado'       => __('Diplomados', 'flacso-posgrados-docentes'),
                'Diploma'         => __('Diplomas', 'flacso-posgrados-docentes'),
            ];

            return $map[$tipo] ?? $tipo;
        }

        public static function render_admin_page(): void {
            if (!current_user_can(FLACSO_Posgrados_Fields::CAPABILITY)) {
                wp_die(__('No tenes permisos suficientes.', 'flacso-posgrados-docentes'));
            }

            $fields      = FLACSO_Posgrados_Fields::get_fields();
            $filters     = self::get_admin_filters();
            $allowed_ids = FLACSO_Posgrados_Pages::get_allowed_page_ids();
            $dashboard   = self::collect_dashboard_data($allowed_ids);
            $query_args  = self::build_query_args($filters, $allowed_ids);
            $query       = new WP_Query($query_args);
            ?>
            <div class="wrap flacso-pos-admin-wrap">
                <h1 class="wp-heading-inline"><?php esc_html_e('Posgrados', 'flacso-posgrados-docentes'); ?></h1>
                <?php self::render_update_notice(); ?>
                <?php self::render_dashboard_panel($dashboard); ?>
                <h2 class="flacso-pos-section-heading"><?php esc_html_e('Gestor de Posgrados', 'flacso-posgrados-docentes'); ?></h2>
                <?php self::render_filter_form($filters); ?>
                <?php self::render_cards_form($query, $fields, $filters, $allowed_ids); ?>
            </div>
            <?php
            wp_reset_postdata();
        }

        private static function collect_dashboard_data(array $allowed_ids): array {
            $defaults = [
                'total'    => 0,
                'publish'  => 0,
                'draft'    => 0,
                'active'   => 0,
                'inactive' => 0,
                'types'    => [],
                'upcoming' => [],
                'recent'   => [],
                'last_updated' => '',
                'missing'  => [
                    'abreviacion'    => 0,
                    'proximo_inicio' => 0,
                    'sin_tipo'       => 0,
                ],
            ];

            if (empty($allowed_ids)) {
                return $defaults;
            }

            $query = new WP_Query([
                'post_type'      => FLACSO_Posgrados_Fields::POST_TYPE,
                'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
                'posts_per_page' => -1,
                'post__in'       => $allowed_ids,
                'orderby'        => ['menu_order' => 'ASC', 'title' => 'ASC'],
                'order'          => 'ASC',
                'no_found_rows'  => true,
            ]);

            if (!$query->have_posts()) {
                return $defaults;
            }

            $data     = $defaults;
            $upcoming = [];
            $recent   = [];

            foreach ($query->posts as $post) {
                $post_id = $post->ID;
                $data['total']++;

                if ($post->post_status === 'publish') {
                    $data['publish']++;
                } else {
                    $data['draft']++;
                }

                $meta      = get_post_meta($post_id);
                $is_active = !empty($meta['posgrado_activo'][0]);
                $tipo      = FLACSO_Posgrados_Fields::sanitize_tipo($meta['tipo_posgrado'][0] ?? '');

                if ($is_active) {
                    $data['active']++;
                } else {
                    $data['inactive']++;
                }

                if ($tipo === '') {
                    $tipo = __('Sin tipo', 'flacso-posgrados-docentes');
                    $data['missing']['sin_tipo']++;
                }

                if (!isset($data['types'][$tipo])) {
                    $data['types'][$tipo] = 0;
                }
                $data['types'][$tipo]++;

                $abreviacion = trim((string) ($meta['abreviacion'][0] ?? ''));
                if ($abreviacion === '') {
                    $data['missing']['abreviacion']++;
                }

                $proximo = trim((string) ($meta['proximo_inicio'][0] ?? ''));
                if ($proximo === '') {
                    $data['missing']['proximo_inicio']++;
                } else {
                    $timestamp = strtotime($proximo . ' 00:00:00');
                    if ($timestamp) {
                        $upcoming[] = [
                            'post_id'      => $post_id,
                            'title'        => get_the_title($post_id),
                            'timestamp'    => $timestamp,
                            'date_display' => date_i18n(get_option('date_format'), $timestamp),
                            'tipo'         => $tipo,
                            'is_active'    => $is_active,
                            'edit_link'    => get_edit_post_link($post_id, ''),
                            'permalink'    => get_permalink($post_id),
                        ];
                    }
                }

                $recent[] = [
                    'post_id'   => $post_id,
                    'title'     => get_the_title($post_id),
                    'modified'  => $post->post_modified_gmt ?: $post->post_date_gmt,
                    'status'    => $post->post_status,
                    'edit_link' => get_edit_post_link($post_id, ''),
                ];
            }

            wp_reset_postdata();

            if ($upcoming) {
                usort($upcoming, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
                $data['upcoming'] = array_slice($upcoming, 0, 5);
            }

            if ($recent) {
                usort($recent, fn($a, $b) => strcmp($b['modified'], $a['modified']));
                $data['recent'] = array_slice($recent, 0, 5);
                $data['last_updated'] = $data['recent'][0]['modified'] ?? '';
            }

            if ($data['types']) {
                arsort($data['types']);
            }

            return $data;
        }

        private static function render_dashboard_panel(array $dashboard): void {
            $shortcuts = self::get_dashboard_shortcuts();
            $last_update = $dashboard['last_updated']
                ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $dashboard['last_updated'], true)
                : __('Sin registros', 'flacso-posgrados-docentes');
            ?>
            <section class="flacso-pos-dashboard" aria-labelledby="flacso-pos-dashboard-title">
                <div class="flacso-pos-dashboard__header">
                    <h2 id="flacso-pos-dashboard-title"><?php esc_html_e('Panel de Posgrados', 'flacso-posgrados-docentes'); ?></h2>
                    <p><?php esc_html_e('Resumen rápido de programas, fechas y accesos recurrentes.', 'flacso-posgrados-docentes'); ?></p>
                </div>

                <div class="flacso-pos-dashboard__stats">
                    <article class="flacso-pos-card flacso-pos-card--stat">
                        <span class="flacso-pos-card__label"><?php esc_html_e('Programas publicados', 'flacso-posgrados-docentes'); ?></span>
                        <span class="flacso-pos-card__value"><?php echo esc_html(number_format_i18n($dashboard['publish'])); ?></span>
                        <span class="flacso-pos-card__meta">
                            <?php printf(esc_html__('Total registrados: %s', 'flacso-posgrados-docentes'), esc_html(number_format_i18n($dashboard['total']))); ?>
                        </span>
                    </article>
                    <article class="flacso-pos-card flacso-pos-card--stat">
                        <span class="flacso-pos-card__label"><?php esc_html_e('Programas activos', 'flacso-posgrados-docentes'); ?></span>
                        <span class="flacso-pos-card__value"><?php echo esc_html(number_format_i18n($dashboard['active'])); ?></span>
                        <span class="flacso-pos-card__meta">
                            <?php printf(esc_html__('Inactivos: %s', 'flacso-posgrados-docentes'), esc_html(number_format_i18n($dashboard['inactive']))); ?>
                        </span>
                    </article>
                    <article class="flacso-pos-card flacso-pos-card--stat">
                        <span class="flacso-pos-card__label"><?php esc_html_e('Última actualización', 'flacso-posgrados-docentes'); ?></span>
                        <span class="flacso-pos-card__value" style="font-size:1.3rem;"><?php echo esc_html($last_update); ?></span>
                        <span class="flacso-pos-card__meta">
                            <?php esc_html_e('Basado en el programa modificado más recientemente.', 'flacso-posgrados-docentes'); ?>
                        </span>
                    </article>
                </div>

                <div class="flacso-pos-dashboard__grid">
                    <article class="flacso-pos-card">
                        <h3><?php esc_html_e('Próximos inicios', 'flacso-posgrados-docentes'); ?></h3>
                        <?php if (empty($dashboard['upcoming'])): ?>
                            <p class="flacso-pos-dashboard__empty"><?php esc_html_e('Aún no hay fechas registradas para los programas.', 'flacso-posgrados-docentes'); ?></p>
                        <?php else: ?>
                            <ul class="flacso-pos-list">
                                <?php foreach ($dashboard['upcoming'] as $item): ?>
                                    <li>
                                        <div>
                                            <strong><?php echo esc_html($item['title']); ?></strong>
                                            <span class="flacso-pos-muted">
                                                <?php echo esc_html($item['date_display']); ?> · <?php echo esc_html($item['tipo']); ?>
                                            </span>
                                        </div>
                                        <div class="flacso-pos-dashboard__list-actions">
                                            <?php if (!empty($item['edit_link'])): ?>
                                                <a class="button button-link" href="<?php echo esc_url($item['edit_link']); ?>"><?php esc_html_e('Editar', 'flacso-posgrados-docentes'); ?></a>
                                            <?php endif; ?>
                                            <?php if (!empty($item['permalink'])): ?>
                                                <a class="button button-link" href="<?php echo esc_url($item['permalink']); ?>" target="_blank" rel="noopener noreferrer">
                                                    <?php esc_html_e('Ver', 'flacso-posgrados-docentes'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>

                    <article class="flacso-pos-card">
                        <h3><?php esc_html_e('Datos pendientes', 'flacso-posgrados-docentes'); ?></h3>
                        <ul class="flacso-pos-list">
                            <li>
                                <div>
                                    <strong><?php echo esc_html(number_format_i18n($dashboard['missing']['abreviacion'])); ?></strong>
                                    <span class="flacso-pos-muted"><?php esc_html_e('programas sin abreviación definida', 'flacso-posgrados-docentes'); ?></span>
                                </div>
                            </li>
                            <li>
                                <div>
                                    <strong><?php echo esc_html(number_format_i18n($dashboard['missing']['proximo_inicio'])); ?></strong>
                                    <span class="flacso-pos-muted"><?php esc_html_e('programas sin próxima fecha de inicio', 'flacso-posgrados-docentes'); ?></span>
                                </div>
                            </li>
                            <li>
                                <div>
                                    <strong><?php echo esc_html(number_format_i18n($dashboard['missing']['sin_tipo'])); ?></strong>
                                    <span class="flacso-pos-muted"><?php esc_html_e('programas sin tipo asignado', 'flacso-posgrados-docentes'); ?></span>
                                </div>
                            </li>
                        </ul>
                        <?php if (!empty($dashboard['types'])): ?>
                            <p class="flacso-pos-dashboard__highlight"><?php esc_html_e('Distribución por tipo', 'flacso-posgrados-docentes'); ?></p>
                            <div>
                                <?php foreach ($dashboard['types'] as $tipo => $count): ?>
                                    <span class="flacso-pos-pill"><?php echo esc_html($tipo); ?> · <?php echo esc_html(number_format_i18n($count)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                </div>

                <div class="flacso-pos-dashboard__grid">
                    <article class="flacso-pos-card">
                        <h3><?php esc_html_e('Actualizaciones recientes', 'flacso-posgrados-docentes'); ?></h3>
                        <?php if (empty($dashboard['recent'])): ?>
                            <p class="flacso-pos-dashboard__empty"><?php esc_html_e('Sin cambios registrados en los últimos días.', 'flacso-posgrados-docentes'); ?></p>
                        <?php else: ?>
                            <ul class="flacso-pos-list">
                                <?php foreach ($dashboard['recent'] as $item): ?>
                                    <?php
                                        $status_obj   = get_post_status_object($item['status']);
                                        $status_label = $status_obj ? $status_obj->label : ucfirst($item['status']);
                                        $modified     = $item['modified']
                                            ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $item['modified'], true)
                                            : '';
                                    ?>
                                    <li>
                                        <div>
                                            <strong><?php echo esc_html($item['title']); ?></strong>
                                            <span class="flacso-pos-muted"><?php echo esc_html($modified); ?> · <?php echo esc_html($status_label); ?></span>
                                        </div>
                                        <div class="flacso-pos-dashboard__list-actions">
                                            <?php if (!empty($item['edit_link'])): ?>
                                                <a class="button button-link" href="<?php echo esc_url($item['edit_link']); ?>"><?php esc_html_e('Editar', 'flacso-posgrados-docentes'); ?></a>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>

                    <article class="flacso-pos-card">
                        <h3><?php esc_html_e('Accesos rápidos', 'flacso-posgrados-docentes'); ?></h3>
                        <?php self::render_dashboard_shortcuts($shortcuts); ?>
                    </article>
                </div>
            </section>
            <?php
        }

        private static function get_dashboard_shortcuts(): array {
            $shortcuts = [
                [
                    'label'       => __('Abrir gestor masivo', 'flacso-posgrados-docentes'),
                    'description' => __('Editar campos, calendarios e imágenes', 'flacso-posgrados-docentes'),
                    'url'         => admin_url('admin.php?page=' . FLACSO_POSGRADOS_SLUG),
                    'icon'        => 'dashicons-screenoptions',
                ],
                [
                    'label'       => __('Crear nueva página de posgrado', 'flacso-posgrados-docentes'),
                    'description' => __('Abre el editor estándar y asigna la página debajo de la raíz de posgrados', 'flacso-posgrados-docentes'),
                    'url'         => admin_url('post-new.php?post_type=page'),
                    'icon'        => 'dashicons-welcome-add-page',
                ],
                [
                    'label'       => __('Sincronizar abreviaciones', 'flacso-posgrados-docentes'),
                    'description' => __('Completa tipo y abreviación desde el mapa semilla', 'flacso-posgrados-docentes'),
                    'url'         => admin_url('admin.php?page=' . FLACSO_POSGRADOS_SLUG . '&flacso_sync_posgrados=1'),
                    'icon'        => 'dashicons-update',
                ],
                [
                    'label'       => __('Panel de Docentes', 'flacso-posgrados-docentes'),
                    'description' => __('Abrir el panel para equipos sincronizados', 'flacso-posgrados-docentes'),
                    'url'         => admin_url('admin.php?page=docentes_panel'),
                    'icon'        => 'dashicons-groups',
                ],
            ];

            $root_link = get_permalink(FLACSO_Posgrados_Pages::ROOT_PAGE_ID);
            if ($root_link) {
                $shortcuts[] = [
                    'label'       => __('Ver página pública de Posgrados', 'flacso-posgrados-docentes'),
                    'description' => __('Abre la página raíz en una nueva pestaña', 'flacso-posgrados-docentes'),
                    'url'         => $root_link,
                    'icon'        => 'dashicons-admin-site-alt3',
                    'external'    => true,
                ];
            }

            return apply_filters('flacso_pos_dashboard_shortcuts', $shortcuts);
        }

        private static function render_dashboard_shortcuts(array $shortcuts): void {
            if (empty($shortcuts)) {
                echo '<p class="flacso-pos-dashboard__empty">' .
                    esc_html__('Sin accesos rápidos configurados.', 'flacso-posgrados-docentes') .
                    '</p>';
                return;
            }

            echo '<div class="flacso-pos-shortcuts">';

            foreach ($shortcuts as $shortcut) {
                $icon   = $shortcut['icon'] ?? 'dashicons-admin-links';
                $target = !empty($shortcut['external']) ? ' target="_blank" rel="noopener noreferrer"' : '';
                ?>
                <a class="flacso-pos-shortcut" href="<?php echo esc_url($shortcut['url']); ?>"<?php echo $target; ?>>
                    <span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                    <span class="flacso-pos-shortcut__body">
                        <strong><?php echo esc_html($shortcut['label']); ?></strong>
                        <?php if (!empty($shortcut['description'])): ?>
                            <span><?php echo esc_html($shortcut['description']); ?></span>
                        <?php endif; ?>
                    </span>
                </a>
                <?php
            }

            echo '</div>';
        }

        private static function get_admin_filters(): array {
            $current_page = self::get_current_page_slug();
            $search       = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
            $tipo         = isset($_GET['tipo']) ? FLACSO_Posgrados_Fields::sanitize_tipo(sanitize_text_field(wp_unslash($_GET['tipo']))) : '';
            $activo       = isset($_GET['activo']) ? self::sanitize_activo_filter(wp_unslash($_GET['activo'])) : '';
            $paged        = max(1, intval($_GET['paged'] ?? 1));
            $per_page     = min(100, max(10, intval($_GET['per_page'] ?? 20)));

            $locked_tipo = self::resolve_tipo_from_page_slug($current_page);
            if ($locked_tipo) {
                $tipo = $locked_tipo;
            }

            return [
                'search'       => $search,
                'tipo'         => $tipo,
                'activo'       => $activo,
                'paged'        => $paged,
                'per_page'     => $per_page,
                'locked_tipo'  => $locked_tipo,
                'current_page' => $current_page,
            ];
        }

        private static function sanitize_activo_filter($value): string {
            $value = (string) $value;
            return in_array($value, ['0', '1'], true) ? $value : '';
        }

        private static function build_query_args(array $filters, array $allowed_ids): array {
            $meta_query = ['relation' => 'AND'];

            if ($filters['tipo']) {
                $meta_query[] = [
                    'key'     => 'tipo_posgrado',
                    'value'   => $filters['tipo'],
                    'compare' => '=',
                ];
            }

            if ($filters['activo'] !== '') {
                $meta_query[] = [
                    'key'     => 'posgrado_activo',
                    'value'   => $filters['activo'] === '1' ? '1' : '0',
                    'compare' => '=',
                ];
            }

            if (!$filters['tipo'] && $filters['activo'] === '') {
                $meta_query[] = ['key' => 'tipo_posgrado', 'compare' => 'EXISTS'];
            }

            $post__in = $allowed_ids ?: [0];

            $args = [
                'post_type'      => FLACSO_Posgrados_Fields::POST_TYPE,
                'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
                's'              => $filters['search'],
                'posts_per_page' => $filters['per_page'],
                'paged'          => $filters['paged'],
                'orderby'        => 'menu_order title',
                'order'          => 'ASC',
                'meta_query'     => $meta_query,
                'post__in'       => $post__in,
            ];

            return apply_filters('flacso_pos_query_args', $args, $_GET);
        }

        private static function render_update_notice(): void {
            if (!isset($_GET['updated'])) {
                return;
            }

            echo '<div class="notice notice-success is-dismissible"><p>' .
                esc_html__('Listo. Cambios guardados.', 'flacso-posgrados-docentes') .
                '</p></div>';
        }

        private static function render_filter_form(array $filters): void {
            $current_page = $filters['current_page'] ?? FLACSO_POSGRADOS_SLUG;
            $tipo_locked  = !empty($filters['locked_tipo']);
            ?>
            <form method="get" class="flacso-pos-filter-form mb-4">
                <input type="hidden" name="page" value="<?php echo esc_attr($current_page); ?>">
                <?php if ($tipo_locked): ?>
                    <input type="hidden" name="tipo" value="<?php echo esc_attr($filters['tipo']); ?>">
                <?php endif; ?>
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-4 col-lg-3">
                                <label class="form-label fw-semibold" for="flacso-pos-search"><?php esc_html_e('Buscar por titulo', 'flacso-posgrados-docentes'); ?></label>
                                <input type="search" id="flacso-pos-search" name="s" value="<?php echo esc_attr($filters['search']); ?>" class="form-control" placeholder="<?php esc_attr_e('Maestria en...', 'flacso-posgrados-docentes'); ?>">
                            </div>
                            <div class="col-12 col-md-3 col-lg-2">
                                <label class="form-label fw-semibold" for="flacso-pos-tipo"><?php esc_html_e('Tipo', 'flacso-posgrados-docentes'); ?></label>
                                <?php if ($tipo_locked): ?>
                                    <div class="form-control-plaintext fw-semibold"><?php echo esc_html($filters['tipo']); ?></div>
                                <?php else: ?>
                                    <select id="flacso-pos-tipo" name="tipo" class="form-select">
                                        <option value=""><?php esc_html_e('Todos', 'flacso-posgrados-docentes'); ?></option>
                                        <?php foreach (FLACSO_Posgrados_Fields::allowed_tipos() as $opt): ?>
                                            <option value="<?php echo esc_attr($opt); ?>" <?php selected($filters['tipo'], $opt); ?>><?php echo esc_html($opt); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 col-md-3 col-lg-2">
                                <label class="form-label fw-semibold" for="flacso-pos-activo"><?php esc_html_e('Estado', 'flacso-posgrados-docentes'); ?></label>
                                <select id="flacso-pos-activo" name="activo" class="form-select">
                                    <option value=""><?php esc_html_e('Todos', 'flacso-posgrados-docentes'); ?></option>
                                    <option value="1" <?php selected($filters['activo'], '1'); ?>><?php esc_html_e('Activos', 'flacso-posgrados-docentes'); ?></option>
                                    <option value="0" <?php selected($filters['activo'], '0'); ?>><?php esc_html_e('Inactivos', 'flacso-posgrados-docentes'); ?></option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2 col-lg-1">
                                <label class="form-label fw-semibold" for="flacso-pos-per-page"><?php esc_html_e('Por pagina', 'flacso-posgrados-docentes'); ?></label>
                                <input type="number" id="flacso-pos-per-page" name="per_page" value="<?php echo esc_attr($filters['per_page']); ?>" min="10" max="100" step="10" class="form-control">
                            </div>
                            <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary"><?php esc_html_e('Filtrar', 'flacso-posgrados-docentes'); ?></button>
                                <a class="btn btn-outline-secondary" href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page)); ?>"><?php esc_html_e('Limpiar', 'flacso-posgrados-docentes'); ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <?php
        }

        private static function render_cards_form(WP_Query $query, array $fields, array $filters, array $allowed_ids): void {
            if (empty($allowed_ids)) {
                echo '<div class="alert alert-warning" role="alert">' .
                    esc_html__('No hay paginas nietas configuradas para el ID 12261 (excepto las excluidas).', 'flacso-posgrados-docentes') .
                    '</div>';
                return;
            }
            ?>
            <form method="post" class="flacso-pos-cards-form">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>

                <div class="d-flex justify-content-end gap-2 mb-3">
                    <button type="submit" class="btn btn-primary">
                        <?php esc_html_e('Guardar cambios', 'flacso-posgrados-docentes'); ?>
                    </button>
                </div>

                <?php self::render_grouped_cards($query, $fields); ?>

                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <?php esc_html_e('Guardar cambios', 'flacso-posgrados-docentes'); ?>
                    </button>
                    <div class="flacso-pos-pagination text-end flex-grow-1 w-100">
                        <?php self::render_pagination($query, $filters); ?>
                    </div>
                </div>
            </form>
            <?php
        }

        private static function render_grouped_cards(WP_Query $query, array $fields): void {
            $posts = $query->posts;

            if (empty($posts)) {
                echo '<div class="alert alert-info" role="alert">' .
                    esc_html__('No se encontraron paginas con esos criterios.', 'flacso-posgrados-docentes') .
                    '</div>';
                return;
            }

            $grouped = self::group_posts_by_tipo($posts);
            ?>
            <div class="flacso-pos-card-grid">
                <?php foreach ($grouped as $tipo_key => $items): ?>
                    <?php self::render_group_section($tipo_key, $items, $fields); ?>
                <?php endforeach; ?>
            </div>
            <?php
        }

        private static function group_posts_by_tipo(array $posts): array {
            $grouped = [];

            foreach ($posts as $post) {
                $post_id = $post->ID;
                $tipo_raw = get_post_meta($post_id, 'tipo_posgrado', true);
                $tipo     = FLACSO_Posgrados_Fields::sanitize_tipo($tipo_raw);
                $key      = $tipo !== '' ? $tipo : self::UNSPECIFIED_TIPO;

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [];
                }

                $grouped[$key][] = [
                    'ID'    => $post_id,
                    'title' => get_the_title($post_id),
                ];
            }

            $ordered = [];

            foreach (FLACSO_Posgrados_Fields::allowed_tipos() as $tipo_label) {
                if (!empty($grouped[$tipo_label])) {
                    $ordered[$tipo_label] = $grouped[$tipo_label];
                    unset($grouped[$tipo_label]);
                }
            }

            if (!empty($grouped[self::UNSPECIFIED_TIPO])) {
                $ordered[self::UNSPECIFIED_TIPO] = $grouped[self::UNSPECIFIED_TIPO];
                unset($grouped[self::UNSPECIFIED_TIPO]);
            }

            foreach ($grouped as $tipo_key => $items) {
                $ordered[$tipo_key] = $items;
            }

            return $ordered;
        }

        private static function render_group_section(string $tipo_key, array $items, array $fields): void {
            $label = $tipo_key === self::UNSPECIFIED_TIPO
                ? esc_html__('Sin tipo asignado', 'flacso-posgrados-docentes')
                : esc_html($tipo_key);
            ?>
            <section class="flacso-pos-type-section mb-5">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <h2 class="h4 mb-0"><?php echo $label; ?></h2>
                    <span class="badge bg-secondary"><?php echo intval(count($items)); ?></span>
                </div>
                <div class="row g-4">
                    <?php foreach ($items as $item): ?>
                        <div class="col-12 col-lg-6">
                            <?php self::render_posgrado_card($item['ID'], $item['title'], $fields); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php
        }

        private static function render_posgrado_card(int $post_id, string $title, array $fields): void {
            $status       = get_post_status($post_id);
            $status_class = $status === 'publish' ? 'bg-success' : 'bg-secondary';
            $status_label = $status === 'publish'
                ? __('Publicado', 'flacso-posgrados-docentes')
                : __('No publicado', 'flacso-posgrados-docentes');
            $image_id     = (int) get_post_meta($post_id, 'imagen_promocional', true);
            $abreviacion  = get_post_meta($post_id, 'abreviacion', true);
            ?>
            <div class="card shadow-sm h-100 flacso-pos-card">
                <?php if ($image_id): ?>
                    <div class="flacso-pos-card-media">
                        <?php echo wp_get_attachment_image($image_id, 'large', false, ['class' => 'img-fluid']); ?>
                    </div>
                <?php endif; ?>
                <div class="card-header d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="text-muted small"><?php printf(esc_html__('ID %d', 'flacso-posgrados-docentes'), $post_id); ?></div>
                        <a class="fw-semibold text-decoration-none d-inline-flex align-items-center gap-1" href="<?php echo esc_url(get_edit_post_link($post_id)); ?>">
                            <?php echo esc_html($title); ?>
                        </a>
                        <?php if ($abreviacion): ?>
                            <div class="mt-1">
                                <span class="badge bg-warning text-dark flacso-pos-abreviacion"><?php echo esc_html($abreviacion); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="small mt-1 d-flex gap-2 flex-wrap">
                            <a href="<?php echo esc_url(get_permalink($post_id)); ?>" target="_blank" rel="noopener noreferrer" class="link-secondary">
                                <?php esc_html_e('Ver en el sitio', 'flacso-posgrados-docentes'); ?>
                            </a>
                        </div>
                    </div>
                    <span class="badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                </div>
                <div class="card-body">
                    <input type="hidden" name="post_id[]" value="<?php echo intval($post_id); ?>">
                    <div class="row g-3">
                        <?php self::render_field_groups($post_id, $fields); ?>
                    </div>
                </div>
            </div>
            <?php
        }

        private static function render_field_groups(int $post_id, array $fields): void {
            foreach (self::prepare_render_groups($fields) as $group) {
                if ($group['grouped'] && count($group['fields']) > 1) {
                    echo '<div class="col-12">';
                    echo '<div class="flacso-pos-fieldset">';
                    if (!empty($group['label'])) {
                        echo '<div class="flacso-pos-fieldset-title">' . esc_html($group['label']) . '</div>';
                    }
                    echo '<div class="row g-3">';
                    foreach ($group['fields'] as $key => $config) {
                        $column_class = self::get_field_column_class($config, $key);
                        echo '<div class="' . esc_attr($column_class) . '">';
                        self::render_field_group($post_id, $key, $config);
                        echo '</div>';
                    }
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                    continue;
                }

                foreach ($group['fields'] as $key => $config) {
                    $column_class = self::get_field_column_class($config, $key);
                    echo '<div class="' . esc_attr($column_class) . '">';
                    self::render_field_group($post_id, $key, $config);
                    echo '</div>';
                }
            }
        }

        private static function prepare_render_groups(array $fields): array {
            $result = [];

            foreach ($fields as $key => $config) {
                $group_key = $config['group'] ?? null;

                if ($group_key) {
                    if (!isset($result[$group_key])) {
                        $result[$group_key] = [
                            'grouped' => true,
                            'label'   => $config['group_label'] ?? '',
                            'fields'  => [],
                        ];
                    }
                    $result[$group_key]['fields'][$key] = $config;
                    continue;
                }

                $result[$key] = [
                    'grouped' => false,
                    'label'   => '',
                    'fields'  => [$key => $config],
                ];
            }

            return $result;
        }

        private static function render_field_group(int $post_id, string $key, array $config): void {
            $type     = $config['type'] ?? 'text';
            $label    = $config['label'] ?? '';
            $field_id = sprintf('%s_%d', $key, $post_id);

            if ($type === 'checkbox') {
                echo '<div class="form-check form-switch flacso-pos-field">';
                self::render_field_input($post_id, $key, $config, $field_id);
                if ($label) {
                    echo '<label class="form-check-label" for="' . esc_attr($field_id) . '">' . esc_html($label) . '</label>';
                }
                echo '</div>';
                return;
            }

            echo '<div class="flacso-pos-field">';
            if ($label) {
                echo '<label class="form-label fw-semibold" for="' . esc_attr($field_id) . '">' . esc_html($label) . '</label>';
            }
            self::render_field_input($post_id, $key, $config, $field_id);
            echo '</div>';
        }

        private static function get_field_column_class(array $config, string $key): string {
            $type = $config['type'] ?? 'text';

            if (in_array($type, ['textarea', 'media'], true) || $key === 'post_excerpt') {
                return 'col-12';
            }

            return 'col-12 col-lg-6';
        }

        private static function render_field_input(int $post_id, string $key, array $config, string $field_id): void {
            $type        = $config['type'] ?? 'text';
            $placeholder = $config['placeholder'] ?? '';
            $value       = self::get_existing_value($post_id, $key, $config);
            $name        = sprintf('%s[%d]', $key, $post_id);

            if (!empty($config['readonly'])) {
                if ($value === '' || $value === null) {
                    $display = '<span class="text-muted">' . esc_html__('Automático', 'flacso-posgrados-docentes') . '</span>';
                } else {
                    $display = esc_html($value);
                }
                echo '<div class="form-control-plaintext fw-semibold">' . $display . '</div>';
                return;
            }

            switch ($type) {
                case 'checkbox':
                    $checked = checked((bool) $value, true, false);
                    echo '<input type="checkbox" class="form-check-input" id="' . esc_attr($field_id) . '" name="' . esc_attr($name) . '" value="1" ' . $checked . '>';
                    break;

                case 'select':
                    ?>
                    <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($field_id); ?>" class="form-select">
                        <?php foreach (($config['options'] ?? []) as $option_value => $option_label): ?>
                            <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>>
                                <?php echo esc_html($option_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php
                    break;

                case 'date':
                    ?>
                    <input type="date" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($field_id); ?>" value="<?php echo esc_attr($value); ?>" class="form-control">
                    <?php
                    break;

                case 'number':
                    ?>
                    <input type="number" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($field_id); ?>" value="<?php echo esc_attr($value); ?>" class="form-control" placeholder="<?php echo esc_attr($placeholder); ?>">
                    <?php
                    break;

                case 'url':
                    ?>
                    <input type="url" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($field_id); ?>" value="<?php echo esc_attr($value); ?>" class="form-control" placeholder="<?php echo esc_attr($placeholder); ?>">
                    <?php
                    break;

                case 'textarea':
                    ?>
                    <textarea name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($field_id); ?>" rows="3" class="form-control" placeholder="<?php echo esc_attr($placeholder); ?>"><?php echo esc_textarea($value); ?></textarea>
                    <?php
                    break;

                case 'media':
                    $attachment_id = $value ? intval($value) : 0;
                    $image_url     = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'thumbnail') : '';
                    ?>
                    <div class="flacso-pos-media-field" data-flacso-media="1">
                        <div class="flacso-pos-media-preview border rounded p-3 text-center bg-light">
                            <?php if ($image_url): ?>
                                <img src="<?php echo esc_url($image_url); ?>" alt="" class="img-fluid rounded">
                            <?php else: ?>
                                <span class="flacso-pos-media-placeholder text-muted"><?php esc_html_e('Sin imagen', 'flacso-posgrados-docentes'); ?></span>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($attachment_id); ?>" class="flacso-pos-media-input">
                        <div class="flacso-pos-media-actions d-flex flex-wrap gap-2 mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm flacso-pos-media-select" data-label="<?php echo esc_attr($config['label']); ?>"><?php esc_html_e('Seleccionar', 'flacso-posgrados-docentes'); ?></button>
                            <button type="button" class="btn btn-outline-danger btn-sm flacso-pos-media-clear"><?php esc_html_e('Quitar', 'flacso-posgrados-docentes'); ?></button>
                        </div>
                    </div>
                    <?php
                    break;

                default:
                    ?>
                    <input type="text" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($field_id); ?>" value="<?php echo esc_attr($value); ?>" class="form-control" placeholder="<?php echo esc_attr($placeholder); ?>">
                    <?php
                    break;
            }
        }

        private static function get_existing_value(int $post_id, string $key, array $config) {
            if (($config['source'] ?? 'meta') === 'post' && $key === 'post_excerpt') {
                return get_post_field('post_excerpt', $post_id);
            }

            return get_post_meta($post_id, $key, true);
        }

        private static function render_pagination(WP_Query $query, array $filters): void {
            $total_pages = max(1, (int) $query->max_num_pages);

            if ($total_pages <= 1) {
                return;
            }

            $base = remove_query_arg(['paged', 'updated']);
            $base = add_query_arg(['page' => FLACSO_POSGRADOS_SLUG], $base);

            $links = paginate_links([
                'base'      => add_query_arg('paged', '%#%', $base),
                'format'    => '',
                'current'   => $filters['paged'],
                'total'     => $total_pages,
                'prev_text' => '&lsaquo;',
                'next_text' => '&rsaquo;',
                'type'      => 'array',
            ]);

            if (empty($links)) {
                return;
            }

            echo '<nav aria-label="' . esc_attr__('Paginacion', 'flacso-posgrados-docentes') . '">';
            echo '<ul class="pagination justify-content-end flex-wrap mb-0">';
            foreach ($links as $link) {
                $is_active = strpos($link, 'current') !== false;
                $link      = str_replace('page-numbers', 'page-link', $link);
                $classes   = 'page-item' . ($is_active ? ' active' : '');
                echo '<li class="' . esc_attr($classes) . '">' . $link . '</li>';
            }
            echo '</ul></nav>';
        }
    }
}
