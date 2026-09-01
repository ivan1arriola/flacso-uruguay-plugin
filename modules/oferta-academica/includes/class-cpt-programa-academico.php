<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Agrupación institucional de ofertas y seminarios (/programas/ y /programas/{slug}). */
final class FLACSO_Programa_Academico {
    public const POST_TYPE = 'programa-academico';

    public static function init(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'               => __('Programas académicos', 'flacso-uruguay'),
                'singular_name'      => __('Programa académico', 'flacso-uruguay'),
                'add_new'            => __('Añadir nuevo', 'flacso-uruguay'),
                'add_new_item'       => __('Agregar programa académico', 'flacso-uruguay'),
                'edit_item'          => __('Editar programa académico', 'flacso-uruguay'),
                'view_item'          => __('Ver programa', 'flacso-uruguay'),
                'all_items'          => __('Todos los Programas', 'flacso-uruguay'),
                'search_items'       => __('Buscar programas', 'flacso-uruguay'),
                'not_found'          => __('No se encontraron programas académicos', 'flacso-uruguay'),
                'not_found_in_trash' => __('No hay programas académicos en la papelera', 'flacso-uruguay'),
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => FLACSO_Admin_Panel::PAGE_SLUG,
            'show_in_rest'       => true,
            'rest_base'          => 'programas-academicos-wp',
            'supports'           => ['title', 'thumbnail', 'revisions'],
            'has_archive'        => 'programas',
            'rewrite'            => ['slug' => 'programas', 'with_front' => false],
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
        ]);

        add_filter('use_block_editor_for_post_type', [self::class, 'disable_block_editor'], 10, 2);

        $fields = [
            'correo'       => ['type' => 'string', 'sanitize_callback' => 'sanitize_email'],
            'coordinacion' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_coordination']],
            'orden'        => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'presentacion' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
        ];
        foreach ($fields as $key => $definition) {
            register_post_meta(self::POST_TYPE, $key, array_merge([
                'single'        => true,
                'show_in_rest'  => false,
                'auth_callback' => static function (): bool { return current_user_can('edit_posts'); },
            ], $definition));
        }

        if (is_admin()) {
            add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
            add_action('save_post_' . self::POST_TYPE, [self::class, 'save_post_data'], 10, 2);
            add_filter('manage_' . self::POST_TYPE . '_posts_columns', [self::class, 'register_columns']);
            add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [self::class, 'render_column'], 10, 2);
        }
    }

    public static function sanitize_coordination($value): array {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized = [
                'docente_id' => absint($item['docente_id'] ?? 0),
                'nombre'     => sanitize_text_field((string) ($item['nombre'] ?? '')),
                'rol'        => sanitize_text_field((string) ($item['rol'] ?? '')),
            ];
            if ($normalized['docente_id'] > 0 || $normalized['nombre'] !== '') {
                if ($normalized['docente_id'] > 0 && $normalized['nombre'] === '') {
                    $normalized['nombre'] = get_the_title($normalized['docente_id']);
                }
                $result[] = $normalized;
            }
        }
        return $result;
    }

    public static function disable_block_editor(bool $use_block_editor, string $post_type): bool {
        if ($post_type === self::POST_TYPE) {
            return false;
        }
        return $use_block_editor;
    }

    public static function add_meta_boxes(): void {
        add_meta_box(
            'flacso_programa_meta',
            __('Datos del Programa Académico', 'flacso-uruguay'),
            [self::class, 'render_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function render_meta_box($post): void {
        $correo = (string) get_post_meta($post->ID, 'correo', true);
        $orden  = absint(get_post_meta($post->ID, 'orden', true));
        $presentacion = (string) get_post_meta($post->ID, 'presentacion', true);
        $coordinacion = (array) get_post_meta($post->ID, 'coordinacion', true);
        $all_personas = get_posts(['post_type' => 'docente', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);

        wp_nonce_field('save_programa_meta', 'programa_nonce');
        ?>
        <div style="display: flex; flex-direction: column; gap: 16px; padding: 10px 0;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Correo institucional de contacto:', 'flacso-uruguay'); ?></label>
                    <input type="email" name="correo" value="<?php echo esc_attr($correo); ?>" placeholder="programa@flacso.edu.uy" style="width: 100%;">
                </div>
                <div>
                    <label for="programa_orden" style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e('Orden de aparición:', 'flacso-uruguay'); ?></label>
                    <input type="number" id="programa_orden" name="orden" value="<?php echo esc_attr((string) $orden); ?>" min="0" max="999" autocomplete="off" data-lpignore="true" data-1p-ignore="true" data-form-type="other" style="width: 100%;">
                </div>
            </div>

            <div>
                <label style="font-weight: 600; display: block; margin-bottom: 6px;"><?php esc_html_e('Presentación / Descripción del Programa:', 'flacso-uruguay'); ?></label>
                <?php
                wp_editor($presentacion, 'presentacion', [
                    'textarea_name' => 'presentacion',
                    'textarea_rows' => 8,
                    'media_buttons' => false,
                    'teeny'         => false,
                    'tinymce'       => true,
                    'quicktags'     => true,
                ]);
                ?>
            </div>

            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 16px; border-radius: 6px;">
                <label style="font-weight: 700; display: block; margin-bottom: 8px;"><?php esc_html_e('Equipo de Coordinación del Programa:', 'flacso-uruguay'); ?></label>
                <p style="margin: 0 0 12px; color: #64748b; font-size: 13px;">
                    <?php esc_html_e('Seleccione las personas que integran la coordinación académica o dirección de este programa:', 'flacso-uruguay'); ?>
                </p>

                <div id="coordinacion-list" style="display: flex; flex-direction: column; gap: 8px;">
                    <?php
                    $coord_rows = !empty($coordinacion) ? $coordinacion : [['docente_id' => 0, 'nombre' => '', 'rol' => 'Coordinación Académica']];
                    foreach ($coord_rows as $idx => $item) :
                        $doc_id = absint($item['docente_id'] ?? 0);
                        $rol    = (string) ($item['rol'] ?? 'Coordinación Académica');
                    ?>
                        <div class="coord-row" style="display: flex; gap: 10px; align-items: center;">
                            <select name="coordinacion[<?php echo esc_attr((string) $idx); ?>][docente_id]" style="flex: 2;">
                                <option value="0"><?php esc_html_e('— Seleccionar Persona / Docente —', 'flacso-uruguay'); ?></option>
                                <?php foreach ($all_personas as $p) : ?>
                                    <option value="<?php echo esc_attr((string) $p->ID); ?>" <?php selected($doc_id, $p->ID); ?>>
                                        <?php echo esc_html($p->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="coordinacion[<?php echo esc_attr((string) $idx); ?>][rol]" value="<?php echo esc_attr($rol); ?>" placeholder="<?php esc_attr_e('Rol (ej: Coordinación Académica, Dirección)', 'flacso-uruguay'); ?>" style="flex: 2;">
                            <button type="button" class="button delete-coord-row" style="color:#b91c1c;">✕</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 10px;">
                    <button type="button" class="button" id="add-coord-btn">➕ <?php esc_html_e('Agregar coordinador/a', 'flacso-uruguay'); ?></button>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const list = document.getElementById('coordinacion-list');
                const addBtn = document.getElementById('add-coord-btn');
                if (addBtn && list) {
                    addBtn.addEventListener('click', function() {
                        const count = list.querySelectorAll('.coord-row').length;
                        const firstRow = list.querySelector('.coord-row');
                        if (firstRow) {
                            const clone = firstRow.cloneNode(true);
                            const select = clone.querySelector('select');
                            const input = clone.querySelector('input');
                            select.name = `coordinacion[${count}][docente_id]`;
                            select.value = '0';
                            input.name = `coordinacion[${count}][rol]`;
                            input.value = 'Coordinación Académica';
                            list.appendChild(clone);
                        }
                    });
                    list.addEventListener('click', function(e) {
                        if (e.target.classList.contains('delete-coord-row')) {
                            const rows = list.querySelectorAll('.coord-row');
                            if (rows.length > 1) {
                                e.target.closest('.coord-row').remove();
                            }
                        }
                    });
                }
            });
        </script>
        <?php
    }

    public static function save_post_data(int $post_id, $post): void {
        if (!isset($_POST['programa_nonce']) || !wp_verify_nonce($_POST['programa_nonce'], 'save_programa_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (isset($_POST['correo'])) {
            update_post_meta($post_id, 'correo', sanitize_email($_POST['correo']));
        }
        if (isset($_POST['orden'])) {
            update_post_meta($post_id, 'orden', absint($_POST['orden']));
        }
        if (isset($_POST['presentacion'])) {
            update_post_meta($post_id, 'presentacion', wp_kses_post($_POST['presentacion']));
        }
        if (isset($_POST['coordinacion'])) {
            update_post_meta($post_id, 'coordinacion', self::sanitize_coordination($_POST['coordinacion']));
        }
    }

    public static function register_columns(array $columns): array {
        return [
            'cb'           => $columns['cb'] ?? '<input type="checkbox" />',
            'title'        => __('Programa', 'flacso-uruguay'),
            'coordinacion' => __('Coordinación', 'flacso-uruguay'),
            'ofertas'      => __('Ofertas Vinculadas', 'flacso-uruguay'),
            'seminarios'   => __('Seminarios Vinculados', 'flacso-uruguay'),
            'correo'       => __('Correo', 'flacso-uruguay'),
            'orden'        => __('Orden', 'flacso-uruguay'),
        ];
    }

    public static function render_column(string $column, int $post_id): void {
        switch ($column) {
            case 'coordinacion':
                $coordinacion = (array) get_post_meta($post_id, 'coordinacion', true);
                if (!empty($coordinacion)) {
                    $names = [];
                    foreach ($coordinacion as $c) {
                        $name = !empty($c['nombre']) ? $c['nombre'] : ($c['docente_id'] ? get_the_title($c['docente_id']) : '');
                        if ($name) $names[] = esc_html($name);
                    }
                    echo !empty($names) ? implode(', ', $names) : '—';
                } else {
                    echo '<span style="color:#94a3b8;">—</span>';
                }
                break;
            case 'ofertas':
                $count = count(get_posts(['post_type' => 'oferta-academica', 'meta_key' => 'programa_academico_id', 'meta_value' => $post_id, 'posts_per_page' => -1, 'fields' => 'ids']));
                echo sprintf('<strong>%d</strong> ofertas', $count);
                break;
            case 'seminarios':
                $count = count(get_posts(['post_type' => 'seminario', 'meta_key' => 'programa_academico_id', 'meta_value' => $post_id, 'posts_per_page' => -1, 'fields' => 'ids']));
                echo sprintf('<strong>%d</strong> seminarios', $count);
                break;
            case 'correo':
                $correo = (string) get_post_meta($post_id, 'correo', true);
                echo $correo !== '' ? '<a href="mailto:' . esc_attr($correo) . '">' . esc_html($correo) . '</a>' : '<span style="color:#94a3b8;">—</span>';
                break;
            case 'orden':
                echo (int) get_post_meta($post_id, 'orden', true);
                break;
        }
    }
}
