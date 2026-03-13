<?php
if (!defined('ABSPATH')) exit;

/**
 * Campos personalizados para la taxonomía equipo-docente
 * - Selector de color y página asociada (posgrado)
 * - Columnas personalizadas en el admin
 * - Compatibilidad con edición rápida
 */

if (!function_exists('dp_equipo_docente_pages_dropdown')) {
    function dp_equipo_docente_pages_dropdown($selected = 0, $name = 'equipo_docente_page_id', $id = 'equipo-docente-page', $extra = []) {
        $selected = (int) $selected;
        $tree = dp_get_posgrado_program_tree();

        $extra = wp_parse_args($extra, [
            'class' => '',
            'show_option_none' => 'Sin pagina asociada',
            'no_results_label' => 'No hay paginas disponibles',
        ]);

        $class_attr = $extra['class'] ? ' class="' . esc_attr($extra['class']) . '"' : '';

        ob_start();
        ?>
        <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>"<?php echo $class_attr; ?>>
            <?php if ($extra['show_option_none'] !== false): ?>
                <option value="0"<?php echo selected(0, $selected, false); ?>><?php echo esc_html($extra['show_option_none']); ?></option>
            <?php endif; ?>

            <?php if (!empty($tree)): ?>
                <?php foreach ($tree as $branch): ?>
                    <?php
                    $category = isset($branch['category']) ? $branch['category'] : null;
                    $pages = isset($branch['pages']) ? $branch['pages'] : [];
                    if (!$category || !$pages) {
                        continue;
                    }
                    ?>
                    <optgroup label="<?php echo esc_attr($category->post_title); ?>">
                        <?php foreach ($pages as $page): ?>
                            <option value="<?php echo esc_attr($page->ID); ?>"<?php echo selected($selected, $page->ID, false); ?>>
                                <?php echo esc_html($page->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            <?php else: ?>
                <option value="" disabled><?php echo esc_html($extra['no_results_label']); ?></option>
            <?php endif; ?>

            <?php if ($selected && !dp_posgrado_tree_contains_page($tree, $selected)): ?>
                <?php $fallback_title = get_the_title($selected); ?>
                <?php if ($fallback_title): ?>
                    <optgroup label="Seleccion actual">
                        <option value="<?php echo esc_attr($selected); ?>" selected><?php echo esc_html($fallback_title); ?></option>
                    </optgroup>
                <?php endif; ?>
            <?php endif; ?>
        </select>
        <?php
        return ob_get_clean();
    }
}


// Campos al crear un nuevo término
add_action('equipo-docente_add_form_fields', function () {
    ?>
    <div class="form-field">
        <label for="equipo-docente-color"><?php _e('Color'); ?></label>
        <input type="color" name="equipo_docente_color" id="equipo-docente-color" value="#0d6efd">
        <p class="description"><?php _e('Selecciona un color para identificar al posgrado.' ); ?></p>
    </div>
    <div class="form-field">
        <label for="equipo-docente-relacion"><?php _e('Nombre de la relacion'); ?></label>
        <input type="text" name="equipo_docente_relacion_nombre" id="equipo-docente-relacion" value="">
        <p class="description"><?php _e('Etiqueta visible para distinguir equipos asociados a una misma pagina de posgrado.' ); ?></p>
    </div>
    <div class="form-field">
        <label for="equipo-docente-page"><?php _e('Página de posgrado relacionada'); ?></label>
        <?php echo dp_equipo_docente_pages_dropdown(0, 'equipo_docente_page_id', 'equipo-docente-page'); ?>
        <p class="description"><?php _e('Relaciona este equipo con una página publicada que represente el posgrado.' ); ?></p>
    </div>
    <?php
});

// Campos al editar término existente
add_action('equipo-docente_edit_form_fields', function ($term) {
    $color = get_term_meta($term->term_id, 'equipo_docente_color', true) ?: '#0d6efd';
    $page_id = (int) get_term_meta($term->term_id, 'equipo_docente_page_id', true);
    $relacion_nombre = get_term_meta($term->term_id, 'equipo_docente_relacion_nombre', true);
?>
    <tr class="form-field">
        <th scope="row"><label for="equipo-docente-color"><?php _e('Color'); ?></label></th>
        <td>
            <input type="color" name="equipo_docente_color" id="equipo-docente-color" value="<?php echo esc_attr($color); ?>">
            <p class="description"><?php _e('Actualiza el color distintivo de este posgrado.' ); ?></p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="equipo-docente-relacion"><?php _e('Nombre de la relacion'); ?></label></th>
        <td>
            <input type="text" name="equipo_docente_relacion_nombre" id="equipo-docente-relacion" value="<?php echo esc_attr($relacion_nombre); ?>">
            <p class="description"><?php _e('Etiqueta visible para distinguir equipos asociados a una misma pagina de posgrado.' ); ?></p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="equipo-docente-page"><?php _e('Página de posgrado'); ?></label></th>
        <td>
            <?php echo dp_equipo_docente_pages_dropdown($page_id, 'equipo_docente_page_id', 'equipo-docente-page'); ?>
            <p class="description"><?php _e('Selecciona la página que describe este posgrado (opcional).' ); ?></p>
        </td>
    </tr>
    <?php
});

function dp_equipo_docente_save_meta($term_id) {
    if (isset($_POST['equipo_docente_color'])) {
        update_term_meta($term_id, 'equipo_docente_color', sanitize_hex_color($_POST['equipo_docente_color']));
    }

    if (isset($_POST['equipo_docente_relacion_nombre'])) {
        $relacion_nombre = sanitize_text_field(wp_unslash($_POST['equipo_docente_relacion_nombre']));
        if ($relacion_nombre !== '') {
            update_term_meta($term_id, 'equipo_docente_relacion_nombre', $relacion_nombre);
        } else {
            delete_term_meta($term_id, 'equipo_docente_relacion_nombre');
        }
    }

    if (isset($_POST['equipo_docente_page_id'])) {
        $page_id = absint($_POST['equipo_docente_page_id']);
        if ($page_id) {
            update_term_meta($term_id, 'equipo_docente_page_id', $page_id);
        } else {
            delete_term_meta($term_id, 'equipo_docente_page_id');
        }
    }
}
add_action('created_equipo-docente', 'dp_equipo_docente_save_meta', 10, 1);
add_action('edited_equipo-docente', 'dp_equipo_docente_save_meta', 10, 1);

// Columnas personalizadas
add_filter('manage_edit-equipo-docente_columns', function ($columns) {
    $columns['equipo_docente_color'] = __('Color');
    $columns['equipo_docente_relacion'] = __('Relacion');
    $columns['equipo_docente_page'] = __('Página vinculada');
    return $columns;
});

add_filter('manage_equipo-docente_custom_column', function ($content, $column, $term_id) {
    if ($column === 'equipo_docente_color') {
        $color = get_term_meta($term_id, 'equipo_docente_color', true);
        $content = $color ? '<span style="display:inline-block;width:18px;height:18px;border-radius:50%;border:1px solid #ccc;background:' . esc_attr($color) . '"></span> ' . esc_html($color) : __('Sin color');
    }

    if ($column === 'equipo_docente_relacion') {
        $relacion = get_term_meta($term_id, 'equipo_docente_relacion_nombre', true);
        $content = $relacion ? esc_html($relacion) : __('Sin nombre');
    }

    if ($column === 'equipo_docente_page') {
        $page_id = (int) get_term_meta($term_id, 'equipo_docente_page_id', true);
        $content = $page_id ? sprintf('<a href="%s">%s</a>', esc_url(get_edit_post_link($page_id)), esc_html(get_the_title($page_id))) : __('Sin página');
    }

    return $content;
}, 10, 3);

// Edición rápida
add_action('quick_edit_custom_box', function ($column_name, $screen, $taxonomy) {
    if ($taxonomy !== 'equipo-docente' || $column_name !== 'equipo_docente_color') {
        return;
    }
    ?>
    <fieldset>
        <div class="inline-edit-col">
            <label>
                <span class="title"><?php _e('Color'); ?></span>
                <span class="input-text-wrap">
                    <input type="color" name="equipo_docente_color" value="#0d6efd">
                </span>
            </label>
        </div>
        <div class="inline-edit-col">
            <label>
                <span class="title"><?php _e('Nombre de la relacion'); ?></span>
                <span class="input-text-wrap">
                    <input type="text" name="equipo_docente_relacion_nombre" value="">
                </span>
            </label>
        </div>
        <div class="inline-edit-col">
            <label>
                <span class="title"><?php _e('Página de posgrado'); ?></span>
                <span class="input-text-wrap">
                    <?php echo dp_equipo_docente_pages_dropdown(0, 'equipo_docente_page_id', 'equipo-docente-page-quick'); ?>
                </span>
            </label>
        </div>
    </fieldset>
    <?php
}, 10, 3);

add_action('admin_footer-edit-tags.php', function () {
    $screen = get_current_screen();
    if ($screen->taxonomy !== 'equipo-docente') return;
    ?>
    <script>
    jQuery(function ($) {
        function setQuickFields(tagId, color, pageId, relacion) {
            var $row = $('tr#tag-' + tagId);
            $row.find('.editinline').on('click', function () {
                $('input[name="equipo_docente_color"]').val(color);
                $('select[name="equipo_docente_page_id"]').val(pageId);
                $('input[name="equipo_docente_relacion_nombre"]').val(relacion);
            });
        }
        <?php
        $terms = get_terms(['taxonomy' => 'equipo-docente', 'hide_empty' => false]);
        foreach ($terms as $term) {
            $color = get_term_meta($term->term_id, 'equipo_docente_color', true) ?: '#0d6efd';
            $page_id = (int) get_term_meta($term->term_id, 'equipo_docente_page_id', true);
            $relacion = (string) get_term_meta($term->term_id, 'equipo_docente_relacion_nombre', true);
            echo "setQuickFields(" . intval($term->term_id) . ", '" . esc_js($color) . "', '" . esc_js($page_id) . "', '" . esc_js($relacion) . "');\n";
        }
        ?>
    });
    </script>
    <?php
});

add_filter('get_terms', function ($terms, $taxonomies, $args) {
    if (empty($terms)) {
        return $terms;
    }

    $taxonomies = (array) $taxonomies;
    if (!in_array('equipo-docente', $taxonomies, true)) {
        return $terms;
    }

    foreach ($terms as $term) {
        if (!is_object($term)) {
            continue;
        }
        $relacion = get_term_meta($term->term_id, 'equipo_docente_relacion_nombre', true);
        if ($relacion) {
            $term->name = $relacion;
        }
    }

    return $terms;
}, 10, 3);
