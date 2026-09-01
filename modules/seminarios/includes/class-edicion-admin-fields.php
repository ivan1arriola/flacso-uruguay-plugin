<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mejora la edición administrativa de Edicion sin duplicar el modelo.
 *
 * FLACSO_Edicion sigue siendo la fuente de verdad del esquema y de los campos
 * básicos. Esta clase agrega la UI que faltaba para metadatos ya registrados y
 * reemplaza visualmente el checklist de docentes por un selector buscable,
 * ordenable y más usable.
 */
final class FLACSO_Edicion_Admin_Fields {
    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_action('add_meta_boxes_' . FLACSO_Edicion::POST_TYPE, [self::class, 'add_meta_boxes'], 20);
        add_action('save_post_' . FLACSO_Edicion::POST_TYPE, [self::class, 'save'], 20, 2);
    }

    public static function add_meta_boxes($post): void {
        add_meta_box(
            'flacso_edicion_programacion',
            __('Programación, docentes y publicación', 'flacso-uruguay'),
            [self::class, 'render'],
            FLACSO_Edicion::POST_TYPE,
            'normal',
            'high'
        );
    }

    private static function get_selected_ids(int $post_id, string $meta_key): array {
        $value = get_post_meta($post_id, $meta_key, true);
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map('absint', $value)));
    }

    private static function edition_label(int $edition_id): string {
        $seminario_id = absint(get_post_meta($edition_id, FLACSO_Edicion::META_PARENT_ID, true));
        $seminario = $seminario_id ? get_the_title($seminario_id) : '';
        $anio = absint(get_post_meta($edition_id, 'anio', true));
        $semestre = FLACSO_Edicion::sanitize_semester(get_post_meta($edition_id, 'semestre', true));

        $suffix = $anio ? (string) $anio : ('#' . $edition_id);
        if ($semestre) {
            $suffix .= ' · S' . $semestre;
        }
        return trim(($seminario ?: __('Edición', 'flacso-uruguay')) . ' — ' . $suffix);
    }

    public static function render($post): void {
        $docentes = self::get_selected_ids($post->ID, 'docentes');
        $encuentros = get_post_meta($post->ID, 'encuentros_sincronicos', true);
        $encuentros = is_array($encuentros) ? $encuentros : [];
        $dias_cierre = get_post_meta($post->ID, 'dias_cierre_post_inicio', true);
        $dias_cierre = ($dias_cierre === '' || $dias_cierre === false)
            ? FLACSO_Edicion::get_days_after_start_limit($post->ID)
            : absint($dias_cierre);
        $mensaje_abierta = (string) get_post_meta($post->ID, 'mensaje_preinscripcion_abierta', true);
        $mensaje_cerrada = (string) get_post_meta($post->ID, 'mensaje_preinscripcion_cerrada', true);
        $mostrar = metadata_exists('post', $post->ID, 'mostrar_en_formulario')
            ? rest_sanitize_boolean(get_post_meta($post->ID, 'mostrar_en_formulario', true))
            : true;
        $componentes = get_post_meta($post->ID, 'ediciones_componentes', true);
        $componentes = is_array($componentes) ? FLACSO_Edicion::sanitize_components($componentes) : [];

        $all_docentes = get_posts([
            'post_type' => 'docente',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $all_ediciones = get_posts([
            'post_type' => FLACSO_Edicion::POST_TYPE,
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'posts_per_page' => -1,
            'post__not_in' => [$post->ID],
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        wp_nonce_field('save_edicion_admin_fields', 'edicion_admin_fields_nonce');
        wp_enqueue_script('jquery-ui-sortable');
        ?>
        <style>
            .flacso-edicion-admin { display:grid; gap:20px; }
            .flacso-edicion-card { border:1px solid #dcdcde; border-radius:8px; background:#fff; padding:16px; }
            .flacso-edicion-card h3 { margin:0 0 6px; font-size:15px; }
            .flacso-edicion-card > p:first-of-type { margin-top:0; color:#646970; }
            .flacso-edicion-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:12px; }
            .flacso-edicion-field label { display:block; font-weight:600; margin-bottom:5px; }
            .flacso-edicion-field input[type="text"], .flacso-edicion-field input[type="date"], .flacso-edicion-field input[type="time"], .flacso-edicion-field input[type="number"], .flacso-edicion-field select, .flacso-edicion-field textarea { width:100%; }
            .flacso-selector-toolbar { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:8px; margin-bottom:10px; }
            .flacso-selected-list { margin:0; display:grid; gap:6px; }
            .flacso-selected-item { display:grid; grid-template-columns:28px minmax(0,1fr) auto; align-items:center; gap:8px; border:1px solid #dcdcde; border-radius:6px; padding:8px 10px; background:#f6f7f7; }
            .flacso-selected-item .dashicons-menu { cursor:grab; color:#646970; }
            .flacso-selected-item strong { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
            .flacso-empty { color:#646970; margin:8px 0 0; }
            .flacso-meeting-row { display:grid; grid-template-columns:minmax(150px,1fr) 130px 130px minmax(190px,1fr) auto; gap:8px; align-items:end; margin-bottom:8px; }
            .flacso-edicion-switch { display:flex; align-items:center; gap:8px; font-weight:600; }
            @media (max-width: 782px) {
                .flacso-meeting-row { grid-template-columns:1fr 1fr; }
                .flacso-meeting-row .flacso-meeting-timezone { grid-column:1 / -1; }
            }
        </style>

        <div class="flacso-edicion-admin">
            <section class="flacso-edicion-card" id="flacso-edicion-docentes">
                <h3><?php esc_html_e('Docentes de esta edición', 'flacso-uruguay'); ?></h3>
                <p><?php esc_html_e('Buscá y agregá docentes. Podés ordenar la lista arrastrando; ese orden se conserva.', 'flacso-uruguay'); ?></p>
                <div class="flacso-selector-toolbar">
                    <select id="flacso-docente-selector">
                        <option value=""><?php esc_html_e('Buscar o seleccionar docente…', 'flacso-uruguay'); ?></option>
                        <?php foreach ($all_docentes as $docente) : ?>
                            <option value="<?php echo esc_attr((string) $docente->ID); ?>" data-label="<?php echo esc_attr($docente->post_title); ?>">
                                <?php echo esc_html($docente->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button" id="flacso-add-docente"><?php esc_html_e('Agregar docente', 'flacso-uruguay'); ?></button>
                </div>
                <input type="search" id="flacso-docente-search" class="regular-text" placeholder="<?php esc_attr_e('Filtrar docentes por nombre…', 'flacso-uruguay'); ?>" style="width:100%;margin-bottom:10px;">
                <ul class="flacso-selected-list" id="flacso-docentes-seleccionados">
                    <?php foreach ($docentes as $docente_id) :
                        $title = get_the_title($docente_id);
                        if (!$title) { continue; }
                        ?>
                        <li class="flacso-selected-item" data-id="<?php echo esc_attr((string) $docente_id); ?>">
                            <span class="dashicons dashicons-menu" aria-hidden="true"></span>
                            <strong><?php echo esc_html($title); ?></strong>
                            <button type="button" class="button-link-delete flacso-remove-docente"><?php esc_html_e('Quitar', 'flacso-uruguay'); ?></button>
                            <input type="hidden" name="flacso_docentes[]" value="<?php echo esc_attr((string) $docente_id); ?>">
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="flacso-empty" id="flacso-docentes-empty" <?php echo $docentes ? 'hidden' : ''; ?>><?php esc_html_e('Todavía no hay docentes asignados.', 'flacso-uruguay'); ?></p>
            </section>

            <section class="flacso-edicion-card">
                <h3><?php esc_html_e('Encuentros sincrónicos', 'flacso-uruguay'); ?></h3>
                <p><?php esc_html_e('Cada encuentro puede tener fecha, horario y zona horaria. Se usa para mostrar correctamente la programación de la edición.', 'flacso-uruguay'); ?></p>
                <div id="flacso-encuentros-list">
                    <?php foreach ($encuentros as $index => $encuentro) : ?>
                        <?php self::render_meeting_row((int) $index, is_array($encuentro) ? $encuentro : []); ?>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" id="flacso-add-encuentro"><?php esc_html_e('Agregar encuentro', 'flacso-uruguay'); ?></button>
            </section>

            <section class="flacso-edicion-card">
                <h3><?php esc_html_e('Publicación y preinscripción', 'flacso-uruguay'); ?></h3>
                <div class="flacso-edicion-grid">
                    <div class="flacso-edicion-field">
                        <label for="flacso-dias-cierre"><?php esc_html_e('Cerrar preinscripción después del inicio', 'flacso-uruguay'); ?></label>
                        <input id="flacso-dias-cierre" type="number" min="0" max="365" name="dias_cierre_post_inicio" value="<?php echo esc_attr((string) $dias_cierre); ?>">
                        <p class="description"><?php esc_html_e('Cantidad de días después de la fecha de inicio.', 'flacso-uruguay'); ?></p>
                    </div>
                    <div class="flacso-edicion-field">
                        <label class="flacso-edicion-switch">
                            <input type="checkbox" name="mostrar_en_formulario" value="1" <?php checked($mostrar); ?>>
                            <?php esc_html_e('Mostrar esta edición en formularios y selectores públicos', 'flacso-uruguay'); ?>
                        </label>
                    </div>
                </div>
                <div class="flacso-edicion-grid" style="margin-top:12px;">
                    <div class="flacso-edicion-field">
                        <label for="flacso-mensaje-abierta"><?php esc_html_e('Mensaje con preinscripción abierta', 'flacso-uruguay'); ?></label>
                        <textarea id="flacso-mensaje-abierta" rows="4" name="mensaje_preinscripcion_abierta"><?php echo esc_textarea($mensaje_abierta); ?></textarea>
                    </div>
                    <div class="flacso-edicion-field">
                        <label for="flacso-mensaje-cerrada"><?php esc_html_e('Mensaje con preinscripción cerrada', 'flacso-uruguay'); ?></label>
                        <textarea id="flacso-mensaje-cerrada" rows="4" name="mensaje_preinscripcion_cerrada"><?php echo esc_textarea($mensaje_cerrada); ?></textarea>
                    </div>
                </div>
            </section>

            <section class="flacso-edicion-card">
                <h3><?php esc_html_e('Ediciones componentes', 'flacso-uruguay'); ?></h3>
                <p><?php esc_html_e('Usalo únicamente cuando esta edición agrupa otras ediciones. El orden también se conserva.', 'flacso-uruguay'); ?></p>
                <div class="flacso-selector-toolbar">
                    <select id="flacso-componente-selector">
                        <option value=""><?php esc_html_e('Seleccionar edición componente…', 'flacso-uruguay'); ?></option>
                        <?php foreach ($all_ediciones as $edition) : ?>
                            <option value="<?php echo esc_attr((string) $edition->ID); ?>" data-label="<?php echo esc_attr(self::edition_label((int) $edition->ID)); ?>">
                                <?php echo esc_html(self::edition_label((int) $edition->ID)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button" id="flacso-add-componente"><?php esc_html_e('Agregar edición', 'flacso-uruguay'); ?></button>
                </div>
                <ul class="flacso-selected-list" id="flacso-componentes-seleccionados">
                    <?php foreach ($componentes as $index => $item) :
                        $edition_id = absint($item['edicion_id'] ?? 0);
                        if (!$edition_id) { continue; }
                        ?>
                        <li class="flacso-selected-item" data-id="<?php echo esc_attr((string) $edition_id); ?>">
                            <span class="dashicons dashicons-menu" aria-hidden="true"></span>
                            <strong><?php echo esc_html(self::edition_label($edition_id)); ?></strong>
                            <button type="button" class="button-link-delete flacso-remove-componente"><?php esc_html_e('Quitar', 'flacso-uruguay'); ?></button>
                            <input type="hidden" name="ediciones_componentes[<?php echo esc_attr((string) $index); ?>][edicion_id]" value="<?php echo esc_attr((string) $edition_id); ?>">
                            <input type="hidden" class="flacso-component-order" name="ediciones_componentes[<?php echo esc_attr((string) $index); ?>][orden]" value="<?php echo esc_attr((string) ($index + 1)); ?>">
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>

        <script type="text/html" id="tmpl-flacso-encuentro-row">
            <?php self::render_meeting_row('__INDEX__', []); ?>
        </script>
        <script>
        jQuery(function($) {
            var $docList = $('#flacso-docentes-seleccionados');
            var $docSelect = $('#flacso-docente-selector');
            var $docSearch = $('#flacso-docente-search');

            function updateDocEmpty() {
                $('#flacso-docentes-empty').prop('hidden', $docList.children('.flacso-selected-item').length > 0);
            }
            function addDocente(id, label) {
                if (!id || $docList.children('[data-id="' + id + '"]').length) return;
                var $item = $('<li class="flacso-selected-item" data-id="' + id + '"></li>');
                $item.append('<span class="dashicons dashicons-menu" aria-hidden="true"></span>');
                $('<strong></strong>').text(label).appendTo($item);
                $item.append('<button type="button" class="button-link-delete flacso-remove-docente"><?php echo esc_js(__('Quitar', 'flacso-uruguay')); ?></button>');
                $('<input>', {type:'hidden', name:'flacso_docentes[]', value:id}).appendTo($item);
                $docList.append($item);
                updateDocEmpty();
            }
            $('#flacso-add-docente').on('click', function() {
                var $option = $docSelect.find('option:selected');
                addDocente($option.val(), $option.data('label') || $option.text());
                $docSelect.val('');
            });
            $docList.on('click', '.flacso-remove-docente', function() {
                $(this).closest('.flacso-selected-item').remove();
                updateDocEmpty();
            }).sortable({handle:'.dashicons-menu'});
            $docSearch.on('input', function() {
                var q = $(this).val().toLowerCase().trim();
                $docSelect.find('option').each(function() {
                    if (!this.value) return;
                    $(this).prop('hidden', q && $(this).text().toLowerCase().indexOf(q) === -1);
                });
            });

            // Oculta el checklist legado del metabox principal para que exista una sola UI de docentes.
            $('#flacso_edicion_meta').find('label').filter(function() {
                return $(this).text().indexOf('<?php echo esc_js(__('Docentes a cargo de la edición:', 'flacso-uruguay')); ?>') !== -1;
            }).first().parent().hide();

            var meetingIndex = <?php echo (int) count($encuentros); ?>;
            $('#flacso-add-encuentro').on('click', function() {
                var html = $('#tmpl-flacso-encuentro-row').html().replaceAll('__INDEX__', meetingIndex++);
                $('#flacso-encuentros-list').append(html);
            });
            $('#flacso-encuentros-list').on('click', '.flacso-remove-encuentro', function() {
                $(this).closest('.flacso-meeting-row').remove();
            });

            var $componentList = $('#flacso-componentes-seleccionados');
            var $componentSelect = $('#flacso-componente-selector');
            function syncComponentNames() {
                $componentList.children('.flacso-selected-item').each(function(index) {
                    $(this).find('input[type="hidden"]').first().attr('name', 'ediciones_componentes[' + index + '][edicion_id]');
                    $(this).find('.flacso-component-order').attr('name', 'ediciones_componentes[' + index + '][orden]').val(index + 1);
                });
            }
            $('#flacso-add-componente').on('click', function() {
                var $option = $componentSelect.find('option:selected');
                var id = $option.val();
                if (!id || $componentList.children('[data-id="' + id + '"]').length) return;
                var $item = $('<li class="flacso-selected-item" data-id="' + id + '"></li>');
                $item.append('<span class="dashicons dashicons-menu" aria-hidden="true"></span>');
                $('<strong></strong>').text($option.data('label') || $option.text()).appendTo($item);
                $item.append('<button type="button" class="button-link-delete flacso-remove-componente"><?php echo esc_js(__('Quitar', 'flacso-uruguay')); ?></button>');
                $('<input>', {type:'hidden', value:id}).appendTo($item);
                $('<input>', {type:'hidden', class:'flacso-component-order'}).appendTo($item);
                $componentList.append($item);
                $componentSelect.val('');
                syncComponentNames();
            });
            $componentList.on('click', '.flacso-remove-componente', function() {
                $(this).closest('.flacso-selected-item').remove();
                syncComponentNames();
            }).sortable({handle:'.dashicons-menu', update:syncComponentNames});
        });
        </script>
        <?php
    }

    private static function render_meeting_row($index, array $meeting): void {
        $fecha = (string) ($meeting['fecha'] ?? '');
        $inicio = sanitize_text_field((string) ($meeting['hora_inicio'] ?? ''));
        $fin = sanitize_text_field((string) ($meeting['hora_fin'] ?? ''));
        $tz = sanitize_text_field((string) ($meeting['zona_horaria'] ?? 'America/Montevideo')) ?: 'America/Montevideo';
        ?>
        <div class="flacso-meeting-row">
            <div class="flacso-edicion-field"><label><?php esc_html_e('Fecha', 'flacso-uruguay'); ?></label><input type="date" name="encuentros_sincronicos[<?php echo esc_attr((string) $index); ?>][fecha]" value="<?php echo esc_attr($fecha); ?>"></div>
            <div class="flacso-edicion-field"><label><?php esc_html_e('Desde', 'flacso-uruguay'); ?></label><input type="time" name="encuentros_sincronicos[<?php echo esc_attr((string) $index); ?>][hora_inicio]" value="<?php echo esc_attr($inicio); ?>"></div>
            <div class="flacso-edicion-field"><label><?php esc_html_e('Hasta', 'flacso-uruguay'); ?></label><input type="time" name="encuentros_sincronicos[<?php echo esc_attr((string) $index); ?>][hora_fin]" value="<?php echo esc_attr($fin); ?>"></div>
            <div class="flacso-edicion-field flacso-meeting-timezone"><label><?php esc_html_e('Zona horaria', 'flacso-uruguay'); ?></label><input type="text" name="encuentros_sincronicos[<?php echo esc_attr((string) $index); ?>][zona_horaria]" value="<?php echo esc_attr($tz); ?>"></div>
            <button type="button" class="button-link-delete flacso-remove-encuentro" style="margin-bottom:6px;"><?php esc_html_e('Quitar', 'flacso-uruguay'); ?></button>
        </div>
        <?php
    }

    public static function save(int $post_id, $post): void {
        if (!isset($_POST['edicion_admin_fields_nonce']) || !wp_verify_nonce($_POST['edicion_admin_fields_nonce'], 'save_edicion_admin_fields')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $docentes = isset($_POST['flacso_docentes']) && is_array($_POST['flacso_docentes'])
            ? FLACSO_Seminario::sanitize_ids(wp_unslash($_POST['flacso_docentes']))
            : [];
        update_post_meta($post_id, 'docentes', $docentes);

        $encuentros = isset($_POST['encuentros_sincronicos']) && is_array($_POST['encuentros_sincronicos'])
            ? FLACSO_Edicion::sanitize_meetings(wp_unslash($_POST['encuentros_sincronicos']))
            : [];
        update_post_meta($post_id, 'encuentros_sincronicos', $encuentros);

        update_post_meta($post_id, 'dias_cierre_post_inicio', absint($_POST['dias_cierre_post_inicio'] ?? 0));
        update_post_meta($post_id, 'mostrar_en_formulario', isset($_POST['mostrar_en_formulario']));
        update_post_meta($post_id, 'mensaje_preinscripcion_abierta', wp_kses_post(wp_unslash($_POST['mensaje_preinscripcion_abierta'] ?? '')));
        update_post_meta($post_id, 'mensaje_preinscripcion_cerrada', wp_kses_post(wp_unslash($_POST['mensaje_preinscripcion_cerrada'] ?? '')));

        $componentes = isset($_POST['ediciones_componentes']) && is_array($_POST['ediciones_componentes'])
            ? FLACSO_Edicion::sanitize_components(wp_unslash($_POST['ediciones_componentes']))
            : [];
        update_post_meta($post_id, 'ediciones_componentes', $componentes);
    }
}
