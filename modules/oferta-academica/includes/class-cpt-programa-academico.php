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
        $orden = absint(get_post_meta($post->ID, 'orden', true));
        $presentacion = (string) get_post_meta($post->ID, 'presentacion', true);
        $coordinacion = (array) get_post_meta($post->ID, 'coordinacion', true);
        $all_personas = get_posts([
            'post_type' => 'docente',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $ofertas = get_posts([
            'post_type' => FLACSO_Oferta_Academica::POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_key' => FLACSO_Oferta_Academica::META_PROGRAM_ID,
            'meta_value' => $post->ID,
        ]);

        wp_nonce_field('save_programa_meta', 'programa_nonce');

        $identity_summary = [];
        if ($correo !== '') {
            $identity_summary[] = $correo;
        }
        if ($orden > 0) {
            $identity_summary[] = sprintf(__('orden %d', 'flacso-uruguay'), $orden);
        }
        $coord_count = count(array_filter($coordinacion, static function ($row): bool {
            return is_array($row) && (absint($row['docente_id'] ?? 0) > 0 || trim((string) ($row['nombre'] ?? '')) !== '');
        }));
        ?>
        <div class="flacso-academic-editor flacso-program-editor">
            <?php FLACSO_Academic_Admin_UI::render_header(
                $post,
                __('Programa Académico', 'flacso-uruguay'),
                $post->post_title !== '' ? $post->post_title : __('Nuevo programa académico', 'flacso-uruguay'),
                __('Agrupa y presenta sus ofertas. Podés editar cualquier sección en el orden que necesites y guardar con el botón nativo de WordPress.', 'flacso-uruguay'),
                [
                    ['icon' => 'dashicons-welcome-learn-more', 'label' => sprintf(_n('%d oferta', '%d ofertas', count($ofertas), 'flacso-uruguay'), count($ofertas))],
                    ['icon' => 'dashicons-groups', 'label' => sprintf(_n('%d integrante', '%d integrantes', $coord_count, 'flacso-uruguay'), $coord_count)],
                ],
                true
            ); ?>

            <?php FLACSO_Academic_Admin_UI::section_start(
                'flacso-programa-identidad',
                __('Identidad y contacto', 'flacso-uruguay'),
                __('El título se edita arriba; aquí se administra el contacto y el orden de aparición.', 'flacso-uruguay'),
                'dashicons-admin-home',
                $identity_summary ? implode(' · ', $identity_summary) : __('Sin completar', 'flacso-uruguay'),
                true
            ); ?>
                <div class="flacso-academic-grid">
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Correo institucional de contacto', 'flacso-uruguay'); ?></span>
                        <input type="email" name="correo" value="<?php echo esc_attr($correo); ?>" placeholder="programa@flacso.edu.uy">
                    </label>
                    <label class="flacso-academic-field">
                        <span><?php esc_html_e('Orden de aparición', 'flacso-uruguay'); ?></span>
                        <input type="number" id="programa_orden" name="orden" value="<?php echo esc_attr((string) $orden); ?>" min="0" max="999" autocomplete="off" data-lpignore="true" data-1p-ignore="true" data-form-type="other">
                    </label>
                </div>
            <?php FLACSO_Academic_Admin_UI::section_end(); ?>

            <?php FLACSO_Academic_Admin_UI::section_start(
                'flacso-programa-presentacion',
                __('Presentación', 'flacso-uruguay'),
                __('Descripción rica destinada a la página pública del programa.', 'flacso-uruguay'),
                'dashicons-text-page',
                FLACSO_Academic_Admin_UI::display_value($presentacion)
            ); ?>
                <?php
                wp_editor($presentacion, 'presentacion', [
                    'textarea_name' => 'presentacion',
                    'textarea_rows' => 8,
                    'media_buttons' => false,
                    'teeny' => false,
                    'tinymce' => true,
                    'quicktags' => true,
                ]);
                ?>
            <?php FLACSO_Academic_Admin_UI::section_end(); ?>

            <?php FLACSO_Academic_Admin_UI::section_start(
                'flacso-programa-coordinacion',
                __('Coordinación', 'flacso-uruguay'),
                __('Integrantes, rol y acciones para agregar o quitar filas.', 'flacso-uruguay'),
                'dashicons-groups',
                $coord_count > 0 ? sprintf(_n('%d integrante', '%d integrantes', $coord_count, 'flacso-uruguay'), $coord_count) : __('Sin completar', 'flacso-uruguay')
            ); ?>
                <p class="flacso-academic-help"><?php esc_html_e('Seleccioná las personas que integran la coordinación académica o dirección de este programa.', 'flacso-uruguay'); ?></p>
                <div id="coordinacion-list" class="flacso-program-coordination-list">
                    <?php
                    $coord_rows = !empty($coordinacion) ? $coordinacion : [['docente_id' => 0, 'nombre' => '', 'rol' => 'Coordinación Académica']];
                    foreach ($coord_rows as $idx => $item) :
                        $doc_id = absint($item['docente_id'] ?? 0);
                        $rol = (string) ($item['rol'] ?? 'Coordinación Académica');
                    ?>
                        <div class="coord-row flacso-program-coordination-row">
                            <label class="flacso-academic-field">
                                <span class="screen-reader-text"><?php esc_html_e('Persona o docente', 'flacso-uruguay'); ?></span>
                                <select name="coordinacion[<?php echo esc_attr((string) $idx); ?>][docente_id]">
                                    <option value="0"><?php esc_html_e('— Seleccionar persona / docente —', 'flacso-uruguay'); ?></option>
                                    <?php foreach ($all_personas as $p) : ?>
                                        <option value="<?php echo esc_attr((string) $p->ID); ?>" <?php selected($doc_id, $p->ID); ?>><?php echo esc_html($p->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="flacso-academic-field">
                                <span class="screen-reader-text"><?php esc_html_e('Rol', 'flacso-uruguay'); ?></span>
                                <input type="text" name="coordinacion[<?php echo esc_attr((string) $idx); ?>][rol]" value="<?php echo esc_attr($rol); ?>" placeholder="<?php esc_attr_e('Rol (ej.: Coordinación Académica)', 'flacso-uruguay'); ?>">
                            </label>
                            <button type="button" class="button delete-coord-row"><?php esc_html_e('Quitar', 'flacso-uruguay'); ?></button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p><button type="button" class="button" id="add-coord-btn"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e('Agregar integrante', 'flacso-uruguay'); ?></button></p>
            <?php FLACSO_Academic_Admin_UI::section_end(); ?>

            <?php FLACSO_Academic_Admin_UI::section_start(
                'flacso-programa-ofertas',
                __('Ofertas vinculadas', 'flacso-uruguay'),
                __('Listado operativo de ofertas del programa y estado de sus cohortes.', 'flacso-uruguay'),
                'dashicons-welcome-learn-more',
                count($ofertas) > 0 ? sprintf(_n('%d oferta', '%d ofertas', count($ofertas), 'flacso-uruguay'), count($ofertas)) : __('Sin ofertas', 'flacso-uruguay')
            ); ?>
                <?php if ($ofertas) : ?>
                    <ul class="flacso-academic-list">
                        <?php foreach ($ofertas as $oferta) :
                            $cohort_ids = get_posts([
                                'post_type' => FLACSO_Cohorte::POST_TYPE,
                                'post_status' => ['publish', 'draft', 'pending', 'private'],
                                'posts_per_page' => -1,
                                'fields' => 'ids',
                                'no_found_rows' => true,
                                'meta_key' => FLACSO_Cohorte::META_PARENT_ID,
                                'meta_value' => $oferta->ID,
                            ]);
                            $states = [];
                            foreach ($cohort_ids as $cohort_id) {
                                $state = FLACSO_Cohorte::sanitize_state(get_post_meta($cohort_id, 'estado', true));
                                $states[$state] = ($states[$state] ?? 0) + 1;
                            }
                            $state_labels = [
                                'planificada' => __('planificadas', 'flacso-uruguay'),
                                'en_curso' => __('en curso', 'flacso-uruguay'),
                                'finalizada' => __('finalizadas', 'flacso-uruguay'),
                                'cancelada' => __('canceladas', 'flacso-uruguay'),
                            ];
                            $state_summary = [];
                            foreach ($states as $state => $count) {
                                $state_summary[] = $count . ' ' . ($state_labels[$state] ?? $state);
                            }
                            $edit_link = get_edit_post_link($oferta->ID);
                            $public_link = get_permalink($oferta->ID);
                        ?>
                            <li>
                                <span class="flacso-academic-list__copy">
                                    <strong><?php echo esc_html(get_the_title($oferta)); ?></strong>
                                    <small>
                                        <?php
                                        echo esc_html(sprintf(_n('%d cohorte', '%d cohortes', count($cohort_ids), 'flacso-uruguay'), count($cohort_ids)));
                                        if ($state_summary) {
                                            echo ' · ' . esc_html(implode(', ', $state_summary));
                                        }
                                        ?>
                                    </small>
                                </span>
                                <span class="flacso-academic-actions">
                                    <?php if ($public_link) : ?><a class="button button-small" href="<?php echo esc_url($public_link); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver pública', 'flacso-uruguay'); ?></a><?php endif; ?>
                                    <?php if ($edit_link) : ?><a class="button button-small" href="<?php echo esc_url($edit_link); ?>"><?php esc_html_e('Editar', 'flacso-uruguay'); ?></a><?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <?php FLACSO_Academic_Admin_UI::render_empty(__('Este programa todavía no tiene ofertas vinculadas.', 'flacso-uruguay')); ?>
                <?php endif; ?>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url(add_query_arg([
                        'post_type' => FLACSO_Oferta_Academica::POST_TYPE,
                        'programa_academico_id' => $post->ID,
                    ], admin_url('post-new.php'))); ?>">
                        <?php esc_html_e('Agregar nueva oferta', 'flacso-uruguay'); ?>
                    </a>
                </p>
            <?php FLACSO_Academic_Admin_UI::section_end(); ?>
        </div>

        <style>
            .flacso-program-coordination-list{display:grid;gap:8px}
            .flacso-program-coordination-row{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,2fr) auto;gap:10px;align-items:center}
            .flacso-program-coordination-row .button{min-height:38px}
            #add-coord-btn .dashicons{margin-top:3px}
            @media(max-width:782px){.flacso-program-coordination-row{grid-template-columns:1fr}.flacso-program-coordination-row .button{justify-self:start}}
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var list = document.getElementById('coordinacion-list');
            var addBtn = document.getElementById('add-coord-btn');
            if (!addBtn || !list) return;

            function renumber() {
                list.querySelectorAll('.coord-row').forEach(function (row, index) {
                    var select = row.querySelector('select');
                    var input = row.querySelector('input[type="text"]');
                    if (select) select.name = 'coordinacion[' + index + '][docente_id]';
                    if (input) input.name = 'coordinacion[' + index + '][rol]';
                });
            }

            addBtn.addEventListener('click', function () {
                var firstRow = list.querySelector('.coord-row');
                if (!firstRow) return;
                var clone = firstRow.cloneNode(true);
                var select = clone.querySelector('select');
                var input = clone.querySelector('input[type="text"]');
                if (select) select.value = '0';
                if (input) input.value = 'Coordinación Académica';
                list.appendChild(clone);
                renumber();
                if (select) select.focus();
            });

            list.addEventListener('click', function (event) {
                var button = event.target.closest('.delete-coord-row');
                if (!button) return;
                var rows = list.querySelectorAll('.coord-row');
                if (rows.length <= 1) return;
                button.closest('.coord-row').remove();
                renumber();
            });
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
