<?php

if (!defined('ABSPATH')) {
    exit;
}

class CPT_Tabla_Precio {
    private const NONCE_ACTION = 'flacso_save_price_table';
    private const NONCE_NAME = 'flacso_price_table_nonce';
    private const SCOPE_QUERY_VAR = 'flacso_ambito';

    public static function init(): void {
        self::register_post_type();

        add_filter('use_block_editor_for_post_type', [self::class, 'disable_block_editor'], 10, 2);
        add_filter('enter_title_here', [self::class, 'title_placeholder'], 10, 2);
        add_action('add_meta_boxes', [self::class, 'register_meta_boxes']);
        add_action('save_post_tabla-precio', [self::class, 'save_price_table'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
        add_filter('manage_tabla-precio_posts_columns', [self::class, 'admin_columns']);
        add_action('manage_tabla-precio_posts_custom_column', [self::class, 'render_admin_column'], 10, 2);
        add_filter('views_edit-tabla-precio', [self::class, 'scope_views']);
        add_action('pre_get_posts', [self::class, 'filter_by_scope']);
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
    }

    public static function render_editor_box(WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $post_id = (int) $post->ID;
        $type = (string) get_post_meta($post_id, 'tabla_precios_tipo', true);
        $show_usd = self::meta_boolean($post_id, 'mostrar_precios_dolares', true);
        $columns = Tabla_Precio_Schema::sanitize_columns(get_post_meta($post_id, 'precios_columnas', true));
        $rows = Tabla_Precio_Schema::sanitize_rows(get_post_meta($post_id, 'precios_filas', true));
        [$note_text, $note_fine_print] = Tabla_Precio_Schema::note_parts($post_id);
        $is_saved = 'auto-draft' !== $post->post_status;
        $uses = $is_saved ? FLACSO_Price_Table_Repository::linked_uses($post_id) : [];
        $offers = $is_saved ? FLACSO_Price_Table_Repository::linked_offers($post_id) : [];
        $editions = array_values(array_filter($uses, static fn(array $use): bool => FLACSO_Edicion::POST_TYPE === $use['entidad']));
        $stored_scope = Tabla_Precio_Schema::sanitize_ambito(get_post_meta($post_id, 'tabla_precios_ambito', true));
        $inferred_scope = $is_saved ? FLACSO_Price_Table_Repository::inferred_ambito($post_id) : '';
        $scope = $stored_scope !== '' ? $stored_scope : $inferred_scope;
        $row_count = count($rows);

        if (!$rows) {
            $rows = [self::empty_row(true)];
        }
        ?>
        <div class="flacso-price-editor flacso-academic-editor" data-price-editor data-show-usd="<?php echo $show_usd ? '1' : '0'; ?>">
            <?php FLACSO_Academic_Admin_UI::render_header(
                $post,
                __('Tabla de precios', 'flacso-uruguay'),
                $post->post_title !== '' ? $post->post_title : __('Nueva tabla de precios', 'flacso-uruguay'),
                __('Es reutilizable y se vincula desde Cohortes o Ediciones de seminario. El título se edita en el campo nativo de WordPress.', 'flacso-uruguay'),
                [
                    ['icon' => 'oferta' === $scope ? 'dashicons-welcome-learn-more' : 'dashicons-groups', 'label' => FLACSO_Price_Table_Repository::ambito_label($scope)],
                    ['icon' => 'dashicons-list-view', 'label' => sprintf(_n('%d fila', '%d filas', $row_count, 'flacso-uruguay'), $row_count)],
                    ['icon' => 'dashicons-admin-site-alt3', 'label' => $show_usd ? __('UYU + USD', 'flacso-uruguay') : __('Solo UYU', 'flacso-uruguay')],
                    ['icon' => 'dashicons-admin-links', 'label' => sprintf(_n('%d uso', '%d usos', count($uses), 'flacso-uruguay'), count($uses))],
                ],
                false
            ); ?>

            <?php
            $identification_summary = array_filter([FLACSO_Price_Table_Repository::ambito_label($scope), $type]);
            FLACSO_Academic_Admin_UI::section_start(
                'flacso-price-identification',
                __('Identificación', 'flacso-uruguay'),
                __('Ámbito de uso y categoría interna.', 'flacso-uruguay'),
                'dashicons-tag',
                implode(' · ', $identification_summary),
                true
            );
            ?>
                <fieldset class="flacso-price-scope">
                    <legend><?php esc_html_e('¿Para qué se usa esta tabla?', 'flacso-uruguay'); ?></legend>
                    <?php foreach (Tabla_Precio_Schema::AMBITOS as $option) : ?>
                        <label class="flacso-price-scope__option is-<?php echo esc_attr($option); ?>">
                            <input type="radio" name="flacso_price_ambito" value="<?php echo esc_attr($option); ?>" <?php checked($scope, $option); ?>>
                            <span class="dashicons <?php echo 'oferta' === $option ? 'dashicons-welcome-learn-more' : 'dashicons-groups'; ?>" aria-hidden="true"></span>
                            <span>
                                <strong><?php echo esc_html(FLACSO_Price_Table_Repository::ambito_label($option)); ?></strong>
                                <small><?php echo 'oferta' === $option ? esc_html__('Maestrías, diplomas y especializaciones (Cohortes).', 'flacso-uruguay') : esc_html__('Ediciones de seminario.', 'flacso-uruguay'); ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    <?php if ($stored_scope === '' && $inferred_scope !== '') : ?>
                        <p class="description"><?php esc_html_e('Preseleccionado según dónde se usa la tabla. Se fija al guardar.', 'flacso-uruguay'); ?></p>
                    <?php endif; ?>
                </fieldset>
                <label class="flacso-academic-field flacso-price-type">
                    <span><?php esc_html_e('Tipo o categoría interna', 'flacso-uruguay'); ?></span>
                    <input type="text" name="flacso_price_type" value="<?php echo esc_attr($type); ?>" placeholder="<?php esc_attr_e('Sin completar', 'flacso-uruguay'); ?>">
                    <small><?php esc_html_e('Ej.: general, convenio o beca. No se publica.', 'flacso-uruguay'); ?></small>
                </label>
            <?php FLACSO_Academic_Admin_UI::section_end(); ?>

            <?php FLACSO_Academic_Admin_UI::section_start(
                'flacso-price-columns-section',
                __('Columnas', 'flacso-uruguay'),
                __('Concepto y pesos son fijos. Dólares se puede ocultar y podés sumar columnas propias.', 'flacso-uruguay'),
                'dashicons-columns',
                sprintf(_n('%d columna', '%d columnas', 2 + ($show_usd ? 1 : 0) + count($columns), 'flacso-uruguay'), 2 + ($show_usd ? 1 : 0) + count($columns))
            ); ?>
                <div class="flacso-price-columns">
                    <span class="flacso-price-column is-fixed"><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e('Concepto', 'flacso-uruguay'); ?></span>
                    <span class="flacso-price-column is-fixed"><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e('Pesos (UYU)', 'flacso-uruguay'); ?></span>
                    <label class="flacso-price-column is-toggle">
                        <input type="checkbox" name="flacso_price_show_usd" value="1" data-usd-toggle <?php checked($show_usd); ?>>
                        <span class="flacso-price-toggle" aria-hidden="true"></span>
                        <?php esc_html_e('Dólares (USD)', 'flacso-uruguay'); ?>
                    </label>
                    <span class="flacso-price-columns__extra" data-extra-columns>
                        <?php foreach ($columns as $index => $column) : ?>
                            <?php self::render_column((string) $index, $column); ?>
                        <?php endforeach; ?>
                    </span>
                    <button type="button" class="button" data-add-column>
                        <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                        <?php esc_html_e('Añadir columna', 'flacso-uruguay'); ?>
                    </button>
                </div>
                <p class="description"><?php esc_html_e('Ej.: Vigencia, Adhiere. Ocultar USD no borra sus valores; quitar una columna propia sí borra sus valores al guardar.', 'flacso-uruguay'); ?></p>
            <?php FLACSO_Academic_Admin_UI::section_end(); ?>

            <?php FLACSO_Academic_Admin_UI::section_start(
                'flacso-price-rows-section',
                __('Filas de precios', 'flacso-uruguay'),
                __('Cada fila tiene un concepto en negrita, una letra chica opcional y un valor por columna.', 'flacso-uruguay'),
                'dashicons-money-alt',
                $row_count ? sprintf(_n('%d fila con contenido', '%d filas con contenido', $row_count, 'flacso-uruguay'), $row_count) : __('Sin completar', 'flacso-uruguay')
            ); ?>
                <div class="flacso-price-section-heading">
                    <p class="description"><?php esc_html_e('La fila principal se resalta en la web y se usa como referencia cuando otra parte del sistema necesita un precio.', 'flacso-uruguay'); ?></p>
                    <div class="flacso-academic-actions">
                        <span class="flacso-price-count" data-row-count></span>
                        <button type="button" class="button button-secondary" data-add-price-row>
                            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                            <?php esc_html_e('Añadir fila', 'flacso-uruguay'); ?>
                        </button>
                    </div>
                </div>

                <div class="flacso-price-rows" data-price-rows>
                    <?php foreach ($rows as $index => $row) : ?>
                        <?php self::render_row((string) $index, $row, $columns); ?>
                    <?php endforeach; ?>
                </div>

                <div class="flacso-price-empty" data-empty-state hidden>
                    <span class="dashicons dashicons-money-alt" aria-hidden="true"></span>
                    <strong><?php esc_html_e('La tabla todavía no tiene filas.', 'flacso-uruguay'); ?></strong>
                    <span><?php esc_html_e('Añadí una fila para comenzar.', 'flacso-uruguay'); ?></span>
                </div>
            <?php FLACSO_Academic_Admin_UI::section_end(); ?>

            <?php FLACSO_Academic_Admin_UI::section_start(
                'flacso-price-note-section',
                __('Nota', 'flacso-uruguay'),
                __('Aclaraciones publicables debajo de la tabla: becas, impuestos, descuentos o condiciones.', 'flacso-uruguay'),
                'dashicons-editor-help',
                FLACSO_Academic_Admin_UI::display_value($note_text !== '' ? $note_text : $note_fine_print)
            ); ?>
                <div class="flacso-academic-grid">
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Texto de la nota', 'flacso-uruguay'); ?></span>
                        <textarea name="flacso_price_note_text" rows="4" data-preview-source placeholder="<?php esc_attr_e('Ej.: Si no podés adherir a ningún beneficio, podés solicitar una beca parcial escribiendo a inscripciones@flacso.edu.uy', 'flacso-uruguay'); ?>"><?php echo esc_textarea($note_text); ?></textarea>
                    </label>
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Letra chica', 'flacso-uruguay'); ?></span>
                        <textarea name="flacso_price_note_fine_print" rows="4" class="flacso-price-input-small" data-preview-source placeholder="<?php esc_attr_e('Ej.: Las becas están sujetas a aprobación por parte de la Comisión Académica.', 'flacso-uruguay'); ?>"><?php echo esc_textarea($note_fine_print); ?></textarea>
                    </label>
                </div>
                <p class="description"><?php esc_html_e('Sin HTML: los correos y enlaces se convierten en links y los saltos de línea se respetan.', 'flacso-uruguay'); ?></p>
            <?php FLACSO_Academic_Admin_UI::section_end(); ?>

            <?php FLACSO_Academic_Admin_UI::section_start(
                'flacso-price-preview-section',
                __('Vista previa', 'flacso-uruguay'),
                __('Así se verá la tabla en la página pública. En celulares, cada fila se muestra como una tarjeta.', 'flacso-uruguay'),
                'dashicons-visibility',
                __('Se actualiza al escribir', 'flacso-uruguay'),
                true
            ); ?>
                <div class="flacso-price-preview" data-price-preview aria-live="polite"></div>
            <?php FLACSO_Academic_Admin_UI::section_end(); ?>

            <?php FLACSO_Academic_Admin_UI::section_start(
                'flacso-price-linked-uses',
                __('Usos vinculados', 'flacso-uruguay'),
                __('Ofertas académicas y Ediciones de seminario que utilizan esta tabla.', 'flacso-uruguay'),
                'dashicons-admin-links',
                $uses ? sprintf(_n('%d uso activo', '%d usos activos', count($uses), 'flacso-uruguay'), count($uses)) : __('Sin usos', 'flacso-uruguay')
            ); ?>
                <?php if ($uses) : ?>
                    <?php if ($offers) : ?>
                        <ul class="flacso-academic-list">
                            <?php foreach ($offers as $offer) :
                                $offer_link = $offer['id'] > 0 ? get_edit_post_link($offer['id']) : '';
                            ?>
                                <li>
                                    <span class="flacso-academic-list__copy">
                                        <strong><?php echo esc_html($offer['nombre']); ?></strong>
                                        <small class="flacso-price-cohort-links">
                                            <?php foreach ($offer['cohortes'] as $cohort) :
                                                $cohort_link = get_edit_post_link($cohort['id']);
                                                $cohort_label = self::short_cohort_label($cohort['nombre']);
                                            ?>
                                                <?php if ($cohort_link) : ?><a href="<?php echo esc_url($cohort_link); ?>"><?php echo esc_html($cohort_label); ?></a><?php else : echo esc_html($cohort_label); endif; ?>
                                            <?php endforeach; ?>
                                        </small>
                                    </span>
                                    <?php if ($offer_link) : ?><a class="button button-small" href="<?php echo esc_url($offer_link); ?>"><?php esc_html_e('Editar oferta', 'flacso-uruguay'); ?></a><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($editions) : ?>
                        <details class="flacso-price-editions">
                            <summary><?php echo esc_html(sprintf(_n('%d edición de seminario', '%d ediciones de seminario', count($editions), 'flacso-uruguay'), count($editions))); ?></summary>
                            <ul>
                                <?php foreach ($editions as $edition) :
                                    $edition_link = get_edit_post_link($edition['id']);
                                ?>
                                    <li><?php if ($edition_link) : ?><a href="<?php echo esc_url($edition_link); ?>"><?php echo esc_html($edition['nombre']); ?></a><?php else : echo esc_html($edition['nombre']); endif; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                    <p class="description"><?php esc_html_e('Mientras tenga usos vinculados, WordPress impedirá eliminar esta tabla.', 'flacso-uruguay'); ?></p>
                <?php else : ?>
                    <?php FLACSO_Academic_Admin_UI::render_empty(__('Esta tabla todavía no está asignada. Podés seleccionarla desde una Cohorte o una Edición de seminario.', 'flacso-uruguay')); ?>
                <?php endif; ?>
            <?php FLACSO_Academic_Admin_UI::section_end(); ?>

            <template data-price-row-template>
                <?php self::render_row('__INDEX__', self::empty_row(false), []); ?>
            </template>
            <template data-column-template>
                <?php self::render_column('__CINDEX__', ['id' => '__COL__', 'titulo' => '']); ?>
            </template>
            <template data-extra-cell-template>
                <?php self::render_extra_cell('__INDEX__', ['id' => '__COL__', 'titulo' => ''], ''); ?>
            </template>
        </div>
        <?php
    }

    /** Opciones de <select> agrupadas por ámbito, para Cohortes ('oferta') y Ediciones ('seminario'). */
    public static function render_select_options(string $ambito, int $selected): void {
        foreach (FLACSO_Price_Table_Repository::grouped_options($ambito) as $group) : ?>
            <optgroup label="<?php echo esc_attr($group['label']); ?>">
                <?php foreach ($group['tables'] as $table) : ?>
                    <option value="<?php echo esc_attr((string) $table->ID); ?>" <?php selected($selected, (int) $table->ID); ?>><?php echo esc_html($table->post_title); ?></option>
                <?php endforeach; ?>
            </optgroup>
        <?php endforeach;
    }

    private static function empty_row(bool $featured): array {
        return ['concepto' => '', 'detalle' => '', 'uyu' => '', 'usd' => '', 'extras' => [], 'destacada' => $featured];
    }

    private static function render_column(string $index, array $column): void {
        ?>
        <span class="flacso-price-column is-extra" data-extra-column="<?php echo esc_attr($column['id']); ?>">
            <input type="hidden" name="flacso_price_columns[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($column['id']); ?>">
            <input
                type="text"
                name="flacso_price_columns[<?php echo esc_attr($index); ?>][titulo]"
                value="<?php echo esc_attr($column['titulo']); ?>"
                placeholder="<?php esc_attr_e('Nombre de la columna', 'flacso-uruguay'); ?>"
                aria-label="<?php esc_attr_e('Nombre de la columna', 'flacso-uruguay'); ?>"
                data-column-title
            >
            <button type="button" class="button-link" data-remove-column aria-label="<?php esc_attr_e('Quitar columna', 'flacso-uruguay'); ?>" title="<?php esc_attr_e('Quitar columna', 'flacso-uruguay'); ?>">
                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
            </button>
        </span>
        <?php
    }

    private static function render_extra_cell(string $index, array $column, string $value): void {
        ?>
        <label class="flacso-price-value" data-extra-cell="<?php echo esc_attr($column['id']); ?>">
            <span data-extra-label><?php echo esc_html($column['titulo']); ?></span>
            <input
                type="text"
                name="flacso_price_rows[<?php echo esc_attr($index); ?>][extras][<?php echo esc_attr($column['id']); ?>]"
                value="<?php echo esc_attr($value); ?>"
            >
        </label>
        <?php
    }

    private static function render_row(string $index, array $row, array $columns): void {
        $is_featured = !empty($row['destacada']);
        ?>
        <div class="flacso-price-row<?php echo $is_featured ? ' is-featured' : ''; ?>" data-price-row>
            <div class="flacso-price-row__top">
                <div class="flacso-price-row__order">
                    <button type="button" class="button-link" data-move-row="up" aria-label="Mover fila hacia arriba" title="Mover arriba">
                        <span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="button-link" data-move-row="down" aria-label="Mover fila hacia abajo" title="Mover abajo">
                        <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                    </button>
                </div>

                <label class="flacso-price-row__concept">
                    <span class="screen-reader-text">Concepto</span>
                    <input
                        type="text"
                        class="flacso-price-input-strong"
                        name="flacso_price_rows[<?php echo esc_attr($index); ?>][concepto]"
                        value="<?php echo esc_attr((string) $row['concepto']); ?>"
                        placeholder="Concepto · Ej. 35% de descuento"
                    >
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

                <button type="button" class="button-link-delete flacso-price-remove" data-remove-price-row aria-label="Eliminar fila" title="Eliminar fila">
                    <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                </button>
            </div>

            <label class="flacso-price-row__detail">
                <span class="screen-reader-text">Letra chica</span>
                <input
                    type="text"
                    class="flacso-price-input-small"
                    name="flacso_price_rows[<?php echo esc_attr($index); ?>][detalle]"
                    value="<?php echo esc_attr((string) $row['detalle']); ?>"
                    placeholder="Letra chica opcional · Ej. Promoción lanzamiento"
                >
            </label>

            <div class="flacso-price-row__values" data-row-values>
                <label class="flacso-price-value">
                    <span>Pesos (UYU)</span>
                    <input
                        type="text"
                        name="flacso_price_rows[<?php echo esc_attr($index); ?>][uyu]"
                        value="<?php echo esc_attr((string) $row['uyu']); ?>"
                        placeholder="4 cuotas de $ 9.904"
                    >
                </label>
                <label class="flacso-price-value flacso-usd-column">
                    <span>Dólares (USD)</span>
                    <input
                        type="text"
                        name="flacso_price_rows[<?php echo esc_attr($index); ?>][usd]"
                        value="<?php echo esc_attr((string) $row['usd']); ?>"
                        placeholder="4 cuotas de U$S 268"
                    >
                </label>
                <?php foreach ($columns as $column) : ?>
                    <?php self::render_extra_cell($index, $column, (string) ($row['extras'][$column['id']] ?? '')); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
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
        $scope = Tabla_Precio_Schema::sanitize_ambito(wp_unslash($_POST['flacso_price_ambito'] ?? ''));
        $note_text = isset($_POST['flacso_price_note_text'])
            ? sanitize_textarea_field(wp_unslash($_POST['flacso_price_note_text']))
            : '';
        $note_fine_print = isset($_POST['flacso_price_note_fine_print'])
            ? sanitize_textarea_field(wp_unslash($_POST['flacso_price_note_fine_print']))
            : '';
        $show_usd = isset($_POST['flacso_price_show_usd']);
        $featured_index = isset($_POST['flacso_price_featured'])
            ? sanitize_text_field(wp_unslash($_POST['flacso_price_featured']))
            : '';
        $columns = Tabla_Precio_Schema::sanitize_columns(
            isset($_POST['flacso_price_columns']) && is_array($_POST['flacso_price_columns']) ? wp_unslash($_POST['flacso_price_columns']) : []
        );
        $column_ids = array_flip(array_column($columns, 'id'));
        $raw_rows = isset($_POST['flacso_price_rows']) && is_array($_POST['flacso_price_rows'])
            ? wp_unslash($_POST['flacso_price_rows'])
            : [];

        $rows = [];
        foreach ($raw_rows as $index => $raw_row) {
            if (!is_array($raw_row)) {
                continue;
            }

            $row = Tabla_Precio_Schema::normalize_row([
                'concepto' => (string) ($raw_row['concepto'] ?? ''),
                'detalle' => (string) ($raw_row['detalle'] ?? ''),
                'uyu' => (string) ($raw_row['uyu'] ?? ''),
                'usd' => (string) ($raw_row['usd'] ?? ''),
                'extras' => is_array($raw_row['extras'] ?? null) ? $raw_row['extras'] : [],
                'destacada' => (string) $index === (string) $featured_index,
            ]);
            $row['extras'] = array_intersect_key($row['extras'], $column_ids);

            if (Tabla_Precio_Schema::row_has_content($row)) {
                $rows[] = $row;
            }
        }

        if ($rows && !array_filter($rows, static fn(array $row): bool => !empty($row['destacada']))) {
            $rows[0]['destacada'] = true;
        }

        update_post_meta($post_id, 'tabla_precios_tipo', $type);
        update_post_meta($post_id, 'tabla_precios_ambito', $scope);
        update_post_meta($post_id, 'precios_columnas', $columns);
        update_post_meta($post_id, 'precios_filas', $rows);
        update_post_meta($post_id, 'precios_nota_texto', $note_text);
        update_post_meta($post_id, 'precios_nota_letra_chica', $note_fine_print);
        update_post_meta($post_id, 'precios_nota', Tabla_Precio_Schema::note_html($note_text, $note_fine_print));
        update_post_meta($post_id, 'mostrar_precios_dolares', $show_usd);
    }

    public static function admin_columns(array $columns): array {
        $result = [];
        foreach ($columns as $key => $label) {
            $result[$key] = $label;
            if ('title' === $key) {
                $result['flacso_price_scope'] = 'Ámbito';
                $result['flacso_price_offers'] = 'Vinculada a';
                $result['flacso_price_main'] = 'Precio principal';
            }
        }

        return $result;
    }

    public static function render_admin_column(string $column, int $post_id): void {
        if ('flacso_price_scope' === $column) {
            $scope = FLACSO_Price_Table_Repository::ambito($post_id);
            $is_inferred = '' === Tabla_Precio_Schema::sanitize_ambito(get_post_meta($post_id, 'tabla_precios_ambito', true)) && '' !== $scope;
            printf(
                '<span class="flacso-price-flag is-%1$s"%3$s>%2$s</span>',
                esc_attr($scope !== '' ? $scope : 'none'),
                esc_html(FLACSO_Price_Table_Repository::ambito_label($scope)),
                $is_inferred ? ' title="' . esc_attr__('Deducido de sus usos', 'flacso-uruguay') . '"' : ''
            );
            return;
        }

        if ('flacso_price_offers' === $column) {
            self::render_offers_column($post_id);
            return;
        }

        if ('flacso_price_main' === $column) {
            $rows = Tabla_Precio_Schema::sanitize_rows(get_post_meta($post_id, 'precios_filas', true));
            if (!$rows) {
                echo '<span class="description">Sin filas</span>';
                return;
            }
            $main = $rows[0];
            foreach ($rows as $row) {
                if (!empty($row['destacada'])) {
                    $main = $row;
                    break;
                }
            }
            $show_usd = self::meta_boolean($post_id, 'mostrar_precios_dolares', true);
            $values = array_filter([$main['uyu'], $show_usd ? $main['usd'] : '']);
            $extra_count = count(Tabla_Precio_Schema::sanitize_columns(get_post_meta($post_id, 'precios_columnas', true)));

            echo '<span class="flacso-price-main">';
            echo '<strong>' . esc_html($main['concepto'] !== '' ? $main['concepto'] : '—') . '</strong>';
            if ($values) {
                echo '<span>' . esc_html(implode(' · ', $values)) . '</span>';
            }
            $meta = [sprintf(_n('%d fila', '%d filas', count($rows), 'flacso-uruguay'), count($rows)), $show_usd ? 'UYU + USD' : 'Solo UYU'];
            if ($extra_count) {
                $meta[] = sprintf(_n('%d columna extra', '%d columnas extra', $extra_count, 'flacso-uruguay'), $extra_count);
            }
            echo '<small>' . esc_html(implode(' · ', $meta)) . '</small>';
            echo '</span>';
        }
    }

    private static function render_offers_column(int $post_id): void {
        $offers = FLACSO_Price_Table_Repository::linked_offers($post_id);
        $edition_count = count(array_filter(
            FLACSO_Price_Table_Repository::linked_uses($post_id),
            static fn(array $use): bool => FLACSO_Edicion::POST_TYPE === $use['entidad']
        ));

        if (!$offers && !$edition_count) {
            echo '<span class="description">Sin uso</span>';
            return;
        }

        if ($offers) {
            echo '<ul class="flacso-price-offer-list">';
            foreach ($offers as $offer) {
                $link = $offer['id'] > 0 ? get_edit_post_link($offer['id']) : '';
                $cohorts = array_map(static fn(array $cohort): string => self::short_cohort_label($cohort['nombre']), $offer['cohortes']);
                echo '<li>';
                echo $link
                    ? '<a href="' . esc_url($link) . '">' . esc_html($offer['nombre']) . '</a>'
                    : '<span>' . esc_html($offer['nombre']) . '</span>';
                echo ' <small>' . esc_html(implode(', ', $cohorts)) . '</small>';
                echo '</li>';
            }
            echo '</ul>';
        }

        if ($edition_count) {
            echo '<span class="description">' . esc_html(sprintf(_n('%d edición de seminario', '%d ediciones de seminario', $edition_count, 'flacso-uruguay'), $edition_count)) . '</span>';
        }
    }

    /** "Maestría en Género — Cohorte VI" → "Cohorte VI". */
    private static function short_cohort_label(string $title): string {
        $parts = preg_split('/\s+[—–-]\s+/u', $title);
        return trim((string) end($parts)) ?: $title;
    }

    /** Vistas Oferta académica / Seminario / Sin uso sobre el listado. */
    public static function scope_views(array $views): array {
        $groups = self::tables_by_scope();
        $current = sanitize_key(wp_unslash($_GET[self::SCOPE_QUERY_VAR] ?? ''));
        $labels = [
            'oferta' => __('Oferta académica', 'flacso-uruguay'),
            'seminario' => __('Seminarios', 'flacso-uruguay'),
            'sin-uso' => __('Sin uso', 'flacso-uruguay'),
        ];

        if ($current !== '' && isset($views['all'])) {
            $views['all'] = str_replace(['class="current"', ' aria-current="page"'], '', $views['all']);
        }

        foreach ($labels as $key => $label) {
            $url = add_query_arg([
                'post_type' => 'tabla-precio',
                self::SCOPE_QUERY_VAR => $key,
            ], admin_url('edit.php'));
            $views['flacso_' . $key] = sprintf(
                '<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
                esc_url($url),
                $current === $key ? ' class="current" aria-current="page"' : '',
                esc_html($label),
                count($groups[$key])
            );
        }

        return $views;
    }

    public static function filter_by_scope(WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query() || 'tabla-precio' !== $query->get('post_type')) {
            return;
        }

        $scope = sanitize_key(wp_unslash($_GET[self::SCOPE_QUERY_VAR] ?? ''));
        $groups = self::tables_by_scope();
        if (isset($groups[$scope])) {
            $query->set('post__in', $groups[$scope] ?: [0]);
        }
    }

    private static function tables_by_scope(): array {
        static $groups = null;
        if (null !== $groups) {
            return $groups;
        }

        $ids = get_posts([
            'post_type' => 'tabla-precio',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'suppress_filters' => true,
        ]);
        update_meta_cache('post', $ids);

        $groups = ['oferta' => [], 'seminario' => [], 'sin-uso' => []];
        foreach ($ids as $id) {
            $scope = FLACSO_Price_Table_Repository::ambito((int) $id);
            if (isset($groups[$scope])) {
                $groups[$scope][] = (int) $id;
            }
            if (!FLACSO_Price_Table_Repository::linked_uses((int) $id)) {
                $groups['sin-uso'][] = (int) $id;
            }
        }

        return $groups;
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

        // FLACSO_OFERTA_ACADEMICA_URL apunta a la raíz del plugin; los assets viven en el módulo.
        $module_path = dirname(__DIR__) . '/';
        $module_url = plugin_dir_url(__DIR__);
        $css = 'assets/css/tabla-precio-admin.css';
        wp_enqueue_style('dashicons');
        wp_enqueue_style(
            'flacso-price-table-admin',
            $module_url . $css,
            ['dashicons'],
            (string) filemtime($module_path . $css)
        );

        if (in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            $js = 'assets/js/tabla-precio-admin.js';
            wp_enqueue_script(
                'flacso-price-table-admin',
                $module_url . $js,
                [],
                (string) filemtime($module_path . $js),
                true
            );
        }
    }

    private static function meta_boolean(int $post_id, string $key, bool $default = false): bool {
        if (!metadata_exists('post', $post_id, $key)) {
            return $default;
        }

        return filter_var(get_post_meta($post_id, $key, true), FILTER_VALIDATE_BOOLEAN);
    }
}
