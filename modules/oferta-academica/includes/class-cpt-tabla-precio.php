<?php

if (!defined('ABSPATH')) {
    exit;
}

class CPT_Tabla_Precio {
    private const NONCE_ACTION = 'flacso_save_price_table';
    private const NONCE_NAME = 'flacso_price_table_nonce';

    public static function init(): void {
        self::register_post_type();

        add_filter('use_block_editor_for_post_type', [self::class, 'disable_block_editor'], 10, 2);
        add_filter('enter_title_here', [self::class, 'title_placeholder'], 10, 2);
        add_action('add_meta_boxes', [self::class, 'register_meta_boxes']);
        add_action('save_post_tabla-precio', [self::class, 'save_price_table'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
        add_filter('manage_tabla-precio_posts_columns', [self::class, 'admin_columns']);
        add_action('manage_tabla-precio_posts_custom_column', [self::class, 'render_admin_column'], 10, 2);
        add_filter('post_row_actions', [self::class, 'row_actions'], 10, 2);
        add_filter('pre_delete_post', [self::class, 'protect_linked_table'], 10, 3);
        add_filter('pre_trash_post', [self::class, 'protect_linked_table'], 10, 3);
    }

    public static function protect_linked_table($delete, $post, $force_delete = false) {
        if (!$post || $post->post_type !== 'tabla-precio' || !class_exists('FLACSO_Price_Table_Repository')) {
            return $delete;
        }

        if (!empty(FLACSO_Price_Table_Repository::linked_uses(absint($post->ID)))) {
            return false;
        }

        return $delete;
    }

    public static function disable_block_editor(bool $use_block_editor, string $post_type): bool {
        return 'tabla-precio' === $post_type ? false : $use_block_editor;
    }

    public static function title_placeholder(string $title, WP_Post $post): string {
        if ('tabla-precio' !== $post->post_type) {
            return $title;
        }

        return 'Ej. Diploma Gestión Educativa · Cohorte 2026';
    }

    public static function register_post_type(): void {
        $labels = [
            'name' => 'Tablas de Precios',
            'singular_name' => 'Tabla de Precio',
            'menu_name' => 'Tablas de Precios',
            'name_admin_bar' => 'Tabla de Precio',
            'add_new' => 'Añadir Nueva',
            'add_new_item' => 'Añadir Nueva Tabla de Precio',
            'new_item' => 'Nueva Tabla de Precio',
            'edit_item' => 'Editar Tabla de Precio',
            'view_item' => 'Ver Tabla de Precio',
            'all_items' => 'Todas las Tablas de Precios',
            'search_items' => 'Buscar Tablas de Precios',
            'not_found' => 'No se encontraron tablas de precios',
            'not_found_in_trash' => 'No hay tablas de precios en la papelera',
        ];

        register_post_type('tabla-precio', [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => FLACSO_Admin_Panel::PAGE_SLUG,
            'show_in_rest' => true,
            'query_var' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_icon' => 'dashicons-money-alt',
            'supports' => ['title', 'revisions'],
            'exclude_from_search' => true,
        ]);
    }

    public static function register_meta_boxes(): void {
        add_meta_box(
            'flacso-price-table-editor',
            'Precios',
            [self::class, 'render_editor_box'],
            'tabla-precio',
            'normal',
            'high'
        );

        add_meta_box(
            'flacso-price-table-usage',
            'Uso de esta tabla',
            [self::class, 'render_usage_box'],
            'tabla-precio',
            'side',
            'default'
        );
    }

    public static function render_editor_box(WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $type = (string) get_post_meta($post->ID, 'tabla_precios_tipo', true);
        $note = (string) get_post_meta($post->ID, 'precios_nota', true);
        $show_usd = self::meta_boolean($post->ID, 'mostrar_precios_dolares', true);
        $rows = self::normalize_rows_for_admin(get_post_meta($post->ID, 'precios_filas', true));

        if (!$rows) {
            $rows = [[
                'concepto' => '',
                'uyu' => '',
                'usd' => '',
                'destacada' => true,
            ]];
        }
        ?>
        <div class="flacso-price-editor" data-price-editor data-show-usd="<?php echo $show_usd ? '1' : '0'; ?>">
            <div class="flacso-price-intro">
                <div>
                    <strong>Configurá la tabla tal como se mostrará en la oferta.</strong>
                    <p>Agregá una fila por modalidad de pago, marcá la principal y decidí si se muestran importes en dólares.</p>
                </div>
                <span class="flacso-price-count" data-row-count></span>
            </div>

            <div class="flacso-price-settings-grid">
                <label class="flacso-price-field">
                    <span>Tipo o categoría <small>(opcional)</small></span>
                    <input
                        type="text"
                        name="flacso_price_type"
                        value="<?php echo esc_attr($type); ?>"
                        placeholder="Ej. General, convenio, beca"
                    >
                    <small>Sirve para identificar el propósito de la tabla internamente.</small>
                </label>

                <label class="flacso-price-toggle-card">
                    <input
                        type="checkbox"
                        name="flacso_price_show_usd"
                        value="1"
                        data-usd-toggle
                        <?php checked($show_usd); ?>
                    >
                    <span class="flacso-price-toggle" aria-hidden="true"></span>
                    <span>
                        <strong>Mostrar precios en USD</strong>
                        <small>Podés ocultar la columna sin borrar los valores cargados.</small>
                    </span>
                </label>
            </div>

            <div class="flacso-price-rows-section">
                <div class="flacso-price-section-heading">
                    <div>
                        <h3>Filas de precios</h3>
                        <p>La fila destacada se usa como referencia principal cuando otra parte del sistema necesita un precio.</p>
                    </div>
                    <button type="button" class="button button-secondary" data-add-price-row>
                        <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                        Añadir fila
                    </button>
                </div>

                <div class="flacso-price-table-head" aria-hidden="true">
                    <span>Concepto</span>
                    <span>Pesos uruguayos</span>
                    <span class="flacso-usd-column">Dólares</span>
                    <span>Principal</span>
                    <span></span>
                </div>

                <div class="flacso-price-rows" data-price-rows>
                    <?php foreach ($rows as $index => $row) : ?>
                        <?php self::render_row((string) $index, $row); ?>
                    <?php endforeach; ?>
                </div>

                <div class="flacso-price-empty" data-empty-state hidden>
                    <span class="dashicons dashicons-money-alt" aria-hidden="true"></span>
                    <strong>La tabla todavía no tiene filas.</strong>
                    <span>Añadí una fila para comenzar.</span>
                </div>
            </div>

            <div class="flacso-price-note-section">
                <label for="flacso_price_note"><strong>Nota al pie</strong></label>
                <p class="description">Texto opcional para aclaraciones sobre cuotas, impuestos, descuentos o condiciones.</p>
                <?php
                wp_editor($note, 'flacso_price_note_editor', [
                    'textarea_name' => 'flacso_price_note',
                    'textarea_rows' => 5,
                    'media_buttons' => false,
                    'teeny' => true,
                    'quicktags' => true,
                ]);
                ?>
            </div>

            <template data-price-row-template>
                <?php self::render_row('__INDEX__', [
                    'concepto' => '',
                    'uyu' => '',
                    'usd' => '',
                    'destacada' => false,
                ]); ?>
            </template>
        </div>
        <?php
    }

    private static function render_row(string $index, array $row): void {
        $is_featured = !empty($row['destacada']);
        ?>
        <div class="flacso-price-row" data-price-row>
            <div class="flacso-price-row-actions-order">
                <button type="button" class="button-link" data-move-row="up" aria-label="Mover fila hacia arriba" title="Mover arriba">
                    <span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
                </button>
                <button type="button" class="button-link" data-move-row="down" aria-label="Mover fila hacia abajo" title="Mover abajo">
                    <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                </button>
            </div>

            <label class="flacso-price-cell flacso-price-concept">
                <span class="screen-reader-text">Concepto</span>
                <input
                    type="text"
                    name="flacso_price_rows[<?php echo esc_attr($index); ?>][concepto]"
                    value="<?php echo esc_attr((string) ($row['concepto'] ?? '')); ?>"
                    placeholder="Ej. Matrícula + 5 cuotas"
                >
            </label>

            <label class="flacso-price-cell">
                <span class="screen-reader-text">Pesos uruguayos</span>
                <div class="flacso-money-input">
                    <span>UYU</span>
                    <input
                        type="text"
                        inputmode="decimal"
                        name="flacso_price_rows[<?php echo esc_attr($index); ?>][uyu]"
                        value="<?php echo esc_attr((string) ($row['uyu'] ?? '')); ?>"
                        placeholder="12.500"
                    >
                </div>
            </label>

            <label class="flacso-price-cell flacso-usd-column">
                <span class="screen-reader-text">Dólares estadounidenses</span>
                <div class="flacso-money-input">
                    <span>USD</span>
                    <input
                        type="text"
                        inputmode="decimal"
                        name="flacso_price_rows[<?php echo esc_attr($index); ?>][usd]"
                        value="<?php echo esc_attr((string) ($row['usd'] ?? '')); ?>"
                        placeholder="320"
                    >
                </div>
            </label>

            <label class="flacso-price-featured" title="Usar esta fila como precio principal">
                <input
                    type="radio"
                    name="flacso_price_featured"
                    value="<?php echo esc_attr($index); ?>"
                    <?php checked($is_featured); ?>
                >
                <span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
                <span>Principal</span>
            </label>

            <button type="button" class="button-link-delete flacso-price-remove" data-remove-price-row aria-label="Eliminar fila">
                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                <span class="flacso-price-remove-label">Eliminar</span>
            </button>
        </div>
        <?php
    }

    public static function render_usage_box(WP_Post $post): void {
        if ('auto-draft' === $post->post_status || !class_exists('FLACSO_Price_Table_Repository')) {
            echo '<p class="description">Guardá la tabla para poder vincularla a cohortes o ediciones.</p>';
            return;
        }

        $uses = FLACSO_Price_Table_Repository::linked_uses((int) $post->ID);
        if (!$uses) {
            echo '<p><span class="dashicons dashicons-info-outline" aria-hidden="true"></span> Esta tabla todavía no está asignada.</p>';
            echo '<p class="description">Podés seleccionarla desde una Cohorte o una Edición de seminario.</p>';
            return;
        }

        echo '<p><strong>' . esc_html(sprintf(_n('%d uso activo', '%d usos activos', count($uses), 'flacso-uruguay'), count($uses))) . '</strong></p>';
        echo '<ul class="flacso-price-usage-list">';
        foreach ($uses as $use) {
            $label = 'cohorte' === $use['entidad'] ? 'Cohorte' : 'Edición';
            $edit_link = get_edit_post_link((int) $use['id']);
            echo '<li>';
            echo '<span class="flacso-price-usage-type">' . esc_html($label) . '</span>';
            if ($edit_link) {
                echo '<a href="' . esc_url($edit_link) . '">' . esc_html((string) $use['nombre']) . '</a>';
            } else {
                echo '<span>' . esc_html((string) $use['nombre']) . '</span>';
            }
            echo '</li>';
        }
        echo '</ul>';
        echo '<p class="description">Mientras esté en uso, WordPress impedirá eliminar esta tabla.</p>';
    }

    public static function save_price_table(int $post_id, WP_Post $post): void {
        if (
            !isset($_POST[self::NONCE_NAME])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || wp_is_post_revision($post_id)
            || !current_user_can('edit_post', $post_id)
        ) {
            return;
        }

        $type = isset($_POST['flacso_price_type'])
            ? sanitize_text_field(wp_unslash($_POST['flacso_price_type']))
            : '';
        $note = isset($_POST['flacso_price_note'])
            ? wp_kses_post(wp_unslash($_POST['flacso_price_note']))
            : '';
        $show_usd = isset($_POST['flacso_price_show_usd']);
        $featured_index = isset($_POST['flacso_price_featured'])
            ? sanitize_text_field(wp_unslash($_POST['flacso_price_featured']))
            : '';
        $raw_rows = isset($_POST['flacso_price_rows']) && is_array($_POST['flacso_price_rows'])
            ? wp_unslash($_POST['flacso_price_rows'])
            : [];

        $rows = [];
        foreach ($raw_rows as $index => $raw_row) {
            if (!is_array($raw_row)) {
                continue;
            }

            $row = [
                'concepto' => sanitize_text_field((string) ($raw_row['concepto'] ?? '')),
                'uyu' => sanitize_text_field((string) ($raw_row['uyu'] ?? '')),
                'usd' => sanitize_text_field((string) ($raw_row['usd'] ?? '')),
                'destacada' => (string) $index === (string) $featured_index,
            ];

            if ($row['concepto'] !== '' || $row['uyu'] !== '' || $row['usd'] !== '') {
                $rows[] = $row;
            }
        }

        if ($rows && !array_filter($rows, static fn(array $row): bool => !empty($row['destacada']))) {
            $rows[0]['destacada'] = true;
        }

        if (class_exists('Tabla_Precio_Schema')) {
            $rows = Tabla_Precio_Schema::sanitize_rows($rows);
        }

        update_post_meta($post_id, 'tabla_precios_tipo', $type);
        update_post_meta($post_id, 'precios_filas', $rows);
        update_post_meta($post_id, 'precios_nota', $note);
        update_post_meta($post_id, 'mostrar_precios_dolares', $show_usd);
    }

    public static function admin_columns(array $columns): array {
        $result = [];
        foreach ($columns as $key => $label) {
            $result[$key] = $label;
            if ('title' === $key) {
                $result['flacso_price_rows'] = 'Filas';
                $result['flacso_price_currency'] = 'Monedas';
                $result['flacso_price_usage'] = 'Uso';
            }
        }

        return $result;
    }

    public static function render_admin_column(string $column, int $post_id): void {
        if ('flacso_price_rows' === $column) {
            $rows = self::normalize_rows_for_admin(get_post_meta($post_id, 'precios_filas', true));
            echo esc_html((string) count($rows));
            return;
        }

        if ('flacso_price_currency' === $column) {
            echo '<span class="flacso-price-list-badge">UYU</span>';
            if (self::meta_boolean($post_id, 'mostrar_precios_dolares', true)) {
                echo ' <span class="flacso-price-list-badge">USD</span>';
            }
            return;
        }

        if ('flacso_price_usage' === $column) {
            if (!class_exists('FLACSO_Price_Table_Repository')) {
                echo '—';
                return;
            }
            $uses = FLACSO_Price_Table_Repository::linked_uses($post_id);
            echo $uses
                ? '<strong>' . esc_html((string) count($uses)) . '</strong>'
                : '<span class="description">Sin asignar</span>';
        }
    }

    public static function row_actions(array $actions, WP_Post $post): array {
        if ('tabla-precio' !== $post->post_type || !class_exists('FLACSO_Price_Table_Repository')) {
            return $actions;
        }

        if (!empty(FLACSO_Price_Table_Repository::linked_uses((int) $post->ID))) {
            unset($actions['trash']);
            $actions['flacso_in_use'] = '<span aria-label="Tabla en uso">En uso</span>';
        }

        return $actions;
    }

    public static function enqueue_admin_assets(string $hook_suffix): void {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen instanceof WP_Screen || 'tabla-precio' !== $screen->post_type) {
            return;
        }

        wp_enqueue_style('dashicons');
        wp_register_style('flacso-price-table-admin', false, [], defined('FLACSO_URUGUAY_VERSION') ? FLACSO_URUGUAY_VERSION : null);
        wp_enqueue_style('flacso-price-table-admin');
        wp_add_inline_style('flacso-price-table-admin', self::admin_css());

        if (in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            wp_enqueue_script('jquery');
            wp_add_inline_script('jquery', self::admin_js());
        }
    }

    private static function normalize_rows_for_admin($value): array {
        if (is_string($value) && '' !== trim($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rows[] = [
                'concepto' => (string) ($row['concepto'] ?? $row['concept'] ?? ''),
                'uyu' => (string) ($row['uyu'] ?? $row['uy'] ?? ''),
                'usd' => (string) ($row['usd'] ?? $row['us'] ?? ''),
                'destacada' => !empty($row['destacada'] ?? $row['highlight'] ?? false),
            ];
        }

        return $rows;
    }

    private static function meta_boolean(int $post_id, string $key, bool $default = false): bool {
        if (!metadata_exists('post', $post_id, $key)) {
            return $default;
        }

        return filter_var(get_post_meta($post_id, $key, true), FILTER_VALIDATE_BOOLEAN);
    }

    private static function admin_css(): string {
        return <<<'CSS'
.post-type-tabla-precio #post-body-content { margin-bottom: 16px; }
.post-type-tabla-precio #titlediv #title { min-height: 48px; font-size: 20px; border-radius: 6px; }
.post-type-tabla-precio #flacso-price-table-editor .inside { margin: 0; padding: 0; }
.post-type-tabla-precio #flacso-price-table-editor { border: 0; box-shadow: 0 1px 3px rgba(15,23,42,.12); }
.post-type-tabla-precio #flacso-price-table-editor > .postbox-header { padding: 0 8px; }
.flacso-price-editor { --fp-border:#dcdcde; --fp-muted:#646970; --fp-bg:#f6f7f7; --fp-accent:#3858e9; }
.flacso-price-intro { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; padding:20px 22px; background:#f8f9ff; border-bottom:1px solid var(--fp-border); }
.flacso-price-intro strong { font-size:15px; color:#1d2327; }
.flacso-price-intro p { margin:6px 0 0; color:var(--fp-muted); }
.flacso-price-count { flex:0 0 auto; background:#fff; border:1px solid #c3c4c7; border-radius:999px; padding:5px 10px; font-size:12px; font-weight:600; }
.flacso-price-settings-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(280px,.8fr); gap:18px; padding:20px 22px; border-bottom:1px solid var(--fp-border); }
.flacso-price-field { display:flex; flex-direction:column; gap:6px; }
.flacso-price-field > span { font-weight:600; }
.flacso-price-field small, .flacso-price-toggle-card small { color:var(--fp-muted); font-weight:400; }
.flacso-price-field input { width:100%; min-height:40px; }
.flacso-price-toggle-card { display:flex; align-items:center; gap:12px; padding:13px 14px; border:1px solid var(--fp-border); border-radius:8px; background:#fff; cursor:pointer; }
.flacso-price-toggle-card > input { position:absolute; opacity:0; pointer-events:none; }
.flacso-price-toggle-card > span:last-child { display:flex; flex-direction:column; gap:3px; }
.flacso-price-toggle { position:relative; flex:0 0 38px; width:38px; height:22px; border-radius:999px; background:#8c8f94; transition:.15s ease; }
.flacso-price-toggle::after { content:""; position:absolute; width:16px; height:16px; left:3px; top:3px; border-radius:50%; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.22); transition:.15s ease; }
.flacso-price-toggle-card input:checked + .flacso-price-toggle { background:var(--fp-accent); }
.flacso-price-toggle-card input:checked + .flacso-price-toggle::after { transform:translateX(16px); }
.flacso-price-toggle-card input:focus-visible + .flacso-price-toggle { box-shadow:0 0 0 2px #fff,0 0 0 4px var(--fp-accent); }
.flacso-price-rows-section { padding:20px 22px 8px; }
.flacso-price-section-heading { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:14px; }
.flacso-price-section-heading h3 { margin:0; font-size:16px; }
.flacso-price-section-heading p { margin:5px 0 0; color:var(--fp-muted); max-width:760px; }
.flacso-price-section-heading .button { display:inline-flex; align-items:center; gap:5px; min-height:36px; }
.flacso-price-table-head { display:grid; grid-template-columns:minmax(210px,1.55fr) minmax(150px,1fr) minmax(150px,1fr) 105px 82px; gap:10px; padding:0 10px 7px 42px; color:#50575e; font-size:12px; font-weight:600; }
.flacso-price-row { position:relative; display:grid; grid-template-columns:minmax(210px,1.55fr) minmax(150px,1fr) minmax(150px,1fr) 105px 82px; gap:10px; align-items:center; padding:10px 10px 10px 42px; margin-bottom:9px; border:1px solid var(--fp-border); border-radius:8px; background:#fff; transition:border-color .15s,box-shadow .15s; }
.flacso-price-row:focus-within { border-color:#8c8f94; box-shadow:0 0 0 1px #8c8f94; }
.flacso-price-row-actions-order { position:absolute; left:8px; top:50%; transform:translateY(-50%); display:flex; flex-direction:column; }
.flacso-price-row-actions-order button { width:26px; height:22px; color:#787c82; cursor:pointer; }
.flacso-price-row-actions-order .dashicons { font-size:16px; width:16px; height:16px; }
.flacso-price-cell input { width:100%; min-height:38px; }
.flacso-money-input { display:flex; align-items:stretch; }
.flacso-money-input > span { display:flex; align-items:center; padding:0 8px; border:1px solid #8c8f94; border-right:0; border-radius:4px 0 0 4px; background:#f0f0f1; color:#50575e; font-size:11px; font-weight:700; }
.flacso-money-input input { border-radius:0 4px 4px 0; }
.flacso-price-featured { display:flex; align-items:center; justify-content:center; gap:5px; min-height:38px; padding:0 7px; border:1px solid var(--fp-border); border-radius:6px; cursor:pointer; font-size:12px; }
.flacso-price-featured input { margin:0; }
.flacso-price-featured .dashicons { color:#dba617; font-size:17px; width:17px; height:17px; }
.flacso-price-remove { display:flex; align-items:center; gap:3px; justify-content:center; cursor:pointer; }
.flacso-price-remove .dashicons { font-size:17px; width:17px; height:17px; }
.flacso-price-editor[data-show-usd="0"] .flacso-usd-column { display:none; }
.flacso-price-editor[data-show-usd="0"] .flacso-price-table-head,
.flacso-price-editor[data-show-usd="0"] .flacso-price-row { grid-template-columns:minmax(240px,1.7fr) minmax(170px,1fr) 105px 82px; }
.flacso-price-empty { margin:8px 0 12px; padding:28px; border:1px dashed #b6b8ba; border-radius:8px; background:var(--fp-bg); text-align:center; color:var(--fp-muted); }
.flacso-price-empty .dashicons { display:block; margin:0 auto 7px; font-size:30px; width:30px; height:30px; }
.flacso-price-empty strong, .flacso-price-empty span:last-child { display:block; }
.flacso-price-note-section { padding:18px 22px 22px; border-top:1px solid var(--fp-border); }
.flacso-price-note-section > label { display:block; margin-bottom:3px; font-size:14px; }
.flacso-price-note-section > .description { margin:0 0 10px; }
.flacso-price-usage-list { margin:0; }
.flacso-price-usage-list li { display:flex; flex-direction:column; gap:2px; padding:8px 0; border-bottom:1px solid #f0f0f1; }
.flacso-price-usage-type { color:#646970; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.03em; }
.flacso-price-list-badge { display:inline-block; padding:2px 6px; border-radius:999px; background:#f0f0f1; font-size:11px; font-weight:600; }
.column-flacso_price_rows { width:70px; }
.column-flacso_price_currency { width:120px; }
.column-flacso_price_usage { width:100px; }
@media (max-width: 1100px) {
  .flacso-price-table-head { display:none; }
  .flacso-price-row, .flacso-price-editor[data-show-usd="0"] .flacso-price-row { grid-template-columns:1fr 1fr; padding:12px 12px 12px 42px; }
  .flacso-price-concept { grid-column:1 / -1; }
  .flacso-price-featured { justify-content:flex-start; }
  .flacso-price-remove { justify-content:flex-end; }
}
@media (max-width: 782px) {
  .flacso-price-intro, .flacso-price-section-heading { flex-direction:column; }
  .flacso-price-settings-grid { grid-template-columns:1fr; }
  .flacso-price-settings-grid, .flacso-price-rows-section, .flacso-price-note-section, .flacso-price-intro { padding-left:14px; padding-right:14px; }
  .flacso-price-section-heading .button { width:100%; justify-content:center; }
  .flacso-price-row, .flacso-price-editor[data-show-usd="0"] .flacso-price-row { grid-template-columns:1fr; padding:42px 10px 10px; }
  .flacso-price-row-actions-order { top:8px; left:10px; transform:none; flex-direction:row; }
  .flacso-price-featured, .flacso-price-remove { justify-content:flex-start; }
  .flacso-price-remove-label { display:inline; }
  .column-flacso_price_rows, .column-flacso_price_currency, .column-flacso_price_usage { width:auto; }
}
CSS;
    }

    private static function admin_js(): string {
        return <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
  const editor = document.querySelector('[data-price-editor]');
  if (!editor) return;

  const rowsContainer = editor.querySelector('[data-price-rows]');
  const template = editor.querySelector('[data-price-row-template]');
  const addButton = editor.querySelector('[data-add-price-row]');
  const usdToggle = editor.querySelector('[data-usd-toggle]');
  const count = editor.querySelector('[data-row-count]');
  const emptyState = editor.querySelector('[data-empty-state]');

  const rows = () => Array.from(rowsContainer.querySelectorAll('[data-price-row]'));

  const renumber = () => {
    rows().forEach((row, index) => {
      row.querySelectorAll('[name]').forEach((field) => {
        field.name = field.name.replace(/flacso_price_rows\[[^\]]+\]/, `flacso_price_rows[${index}]`);
      });
      const radio = row.querySelector('input[name="flacso_price_featured"]');
      if (radio) radio.value = String(index);
    });
  };

  const refresh = () => {
    const currentRows = rows();
    renumber();
    count.textContent = `${currentRows.length} ${currentRows.length === 1 ? 'fila' : 'filas'}`;
    emptyState.hidden = currentRows.length > 0;
    editor.dataset.showUsd = usdToggle && usdToggle.checked ? '1' : '0';

    currentRows.forEach((row, index) => {
      const up = row.querySelector('[data-move-row="up"]');
      const down = row.querySelector('[data-move-row="down"]');
      if (up) up.disabled = index === 0;
      if (down) down.disabled = index === currentRows.length - 1;
    });
  };

  addButton?.addEventListener('click', () => {
    const index = rows().length;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
    const row = wrapper.firstElementChild;
    if (!row) return;
    rowsContainer.appendChild(row);

    if (rows().length === 1) {
      const radio = row.querySelector('input[name="flacso_price_featured"]');
      if (radio) radio.checked = true;
    }

    refresh();
    row.querySelector('input[type="text"]')?.focus();
  });

  rowsContainer.addEventListener('click', (event) => {
    const removeButton = event.target.closest('[data-remove-price-row]');
    if (removeButton) {
      const row = removeButton.closest('[data-price-row]');
      const wasFeatured = row?.querySelector('input[name="flacso_price_featured"]')?.checked;
      row?.remove();
      if (wasFeatured) {
        const firstRadio = rowsContainer.querySelector('input[name="flacso_price_featured"]');
        if (firstRadio) firstRadio.checked = true;
      }
      refresh();
      return;
    }

    const moveButton = event.target.closest('[data-move-row]');
    if (!moveButton) return;
    const row = moveButton.closest('[data-price-row]');
    if (!row) return;

    if (moveButton.dataset.moveRow === 'up' && row.previousElementSibling) {
      rowsContainer.insertBefore(row, row.previousElementSibling);
    }
    if (moveButton.dataset.moveRow === 'down' && row.nextElementSibling) {
      rowsContainer.insertBefore(row.nextElementSibling, row);
    }
    refresh();
  });

  usdToggle?.addEventListener('change', refresh);
  refresh();
});
JS;
    }
}
