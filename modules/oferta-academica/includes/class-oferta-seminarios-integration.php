<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestiona la relacion entre ofertas academicas y seminarios.
 */
class Oferta_Seminarios_Integration {

    public static function init(): void {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post_oferta-academica', [__CLASS__, 'save_meta'], 10, 2);
    }

    public static function add_meta_box(): void {
        if (!post_type_exists('seminario')) {
            return;
        }

        add_meta_box(
            'oferta_seminarios',
            'Seminarios Asociados',
            [__CLASS__, 'render_meta_box'],
            'oferta-academica',
            'normal',
            'default'
        );
    }

    public static function render_meta_box($post): void {
        wp_nonce_field('oferta_seminarios_nonce', 'oferta_seminarios_nonce');

        $selected_ids = array_map('intval', self::get_programa_seminarios($post->ID));
        $selected_map = array_fill_keys($selected_ids, true);

        $all_seminarios = get_posts([
            'post_type' => 'seminario',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        self::render_picker_assets();

        echo '<p><label for="oferta_seminarios_search">Selecciona los seminarios de la oferta academica:</label></p>';
        echo '<div class="flacso-association-picker" data-picker="seminarios">';
        echo '<div class="flacso-association-picker__toolbar">';
        echo '<input type="search" id="oferta_seminarios_search" class="flacso-association-picker__search" placeholder="Buscar seminario..." autocomplete="off" />';
        echo '<button type="button" class="button button-small" data-action="select-visible">Seleccionar visibles</button>';
        echo '<button type="button" class="button button-small" data-action="clear-selection">Limpiar seleccion</button>';
        echo '<span class="flacso-association-picker__count" aria-live="polite"></span>';
        echo '</div>';

        echo '<div class="flacso-association-picker__list" role="group" aria-label="Seminarios disponibles">';

        if (!empty($all_seminarios)) {
            foreach ($all_seminarios as $seminario) {
                $seminario_id = (int) $seminario->ID;
                $title = (string) $seminario->post_title;
                $search_value = strtolower(remove_accents($title));
                $checked = isset($selected_map[$seminario_id]) ? ' checked' : '';

                echo '<label class="flacso-association-picker__item" data-search="' . esc_attr($search_value) . '">';
                echo '<input type="checkbox" name="oferta_seminarios_ids[]" value="' . esc_attr((string) $seminario_id) . '"' . $checked . ' />';
                echo '<span>' . esc_html($title) . '</span>';
                echo '</label>';
            }
        } else {
            echo '<p class="description">No hay seminarios disponibles.</p>';
        }

        echo '</div>';
        echo '<p class="description">Usa el buscador y marca las opciones que quieras asociar.</p>';
        echo '</div>';
    }

    public static function save_meta($post_id, $post): void {
        if (!isset($_POST['oferta_seminarios_nonce']) ||
            !wp_verify_nonce($_POST['oferta_seminarios_nonce'], 'oferta_seminarios_nonce')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (isset($_POST['oferta_seminarios_ids']) && is_array($_POST['oferta_seminarios_ids'])) {
            $seminarios_ids = array_values(array_unique(array_map('intval', $_POST['oferta_seminarios_ids'])));
            update_post_meta($post_id, '_oferta_seminarios_ids', $seminarios_ids);
            return;
        }

        delete_post_meta($post_id, '_oferta_seminarios_ids');
    }

    /**
     * Obtener seminarios asociados a un programa.
     */
    public static function get_programa_seminarios($programa_id): array {
        $value = get_post_meta($programa_id, '_oferta_seminarios_ids', true);
        if (!is_array($value)) {
            return [];
        }
        return $value;
    }

    /**
     * Obtener data completa de seminarios de un programa.
     */
    public static function get_programa_seminarios_data($programa_id): array {
        $seminarios_ids = self::get_programa_seminarios($programa_id);
        $seminarios = [];

        foreach ($seminarios_ids as $seminario_id) {
            $seminario = get_post($seminario_id);
            if ($seminario && $seminario->post_status === 'publish') {
                $seminarios[] = [
                    'id' => $seminario->ID,
                    'titulo' => $seminario->post_title,
                    'excerpt' => $seminario->post_excerpt,
                    'contenido' => $seminario->post_content,
                    'thumbnail' => get_post_thumbnail_id($seminario->ID),
                    'permalink' => get_permalink($seminario->ID),
                    'fecha_inicio' => get_post_meta($seminario->ID, '_seminario_fecha_inicio', true),
                    'fecha_fin' => get_post_meta($seminario->ID, '_seminario_fecha_fin', true),
                    'modalidad' => get_post_meta($seminario->ID, '_seminario_modalidad', true),
                    'costo' => get_post_meta($seminario->ID, '_seminario_costo', true),
                ];
            }
        }

        return $seminarios;
    }

    private static function render_picker_assets(): void {
        if (!empty($GLOBALS['flacso_oferta_association_picker_assets_printed'])) {
            return;
        }

        $GLOBALS['flacso_oferta_association_picker_assets_printed'] = true;

        echo '<style id="flacso-oferta-association-picker-styles">'
            . '.flacso-association-picker{margin-top:6px}'
            . '.flacso-association-picker__toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px}'
            . '.flacso-association-picker__search{min-width:260px;max-width:100%;flex:1}'
            . '.flacso-association-picker__count{margin-left:auto;font-size:12px;color:#50575e}'
            . '.flacso-association-picker__list{max-height:280px;overflow:auto;border:1px solid #8c8f94;border-radius:4px;background:#fff;padding:8px}'
            . '.flacso-association-picker__item{display:flex;align-items:flex-start;gap:8px;padding:6px;border-radius:4px;line-height:1.3}'
            . '.flacso-association-picker__item:hover{background:#f0f6fc}'
            . '.flacso-association-picker__item input{margin-top:2px}'
            . '</style>';

        echo '<script id="flacso-oferta-association-picker-script">'
            . '(function(){'
            . 'if(window.flacsoOfertaAssociationPickerInitialized){return;}'
            . 'window.flacsoOfertaAssociationPickerInitialized=true;'
            . 'function normalizeText(value){if(!value){return "";}var text=String(value).toLowerCase();if(text.normalize){text=text.normalize("NFD").replace(/[\\u0300-\\u036f]/g,"");}return text;}'
            . 'function updateCount(picker){var checked=picker.querySelectorAll(".flacso-association-picker__item input:checked").length;var countNode=picker.querySelector(".flacso-association-picker__count");if(countNode){countNode.textContent=checked+" seleccionados";}}'
            . 'function filterItems(picker){var input=picker.querySelector(".flacso-association-picker__search");var query=normalizeText(input?input.value:"");var items=picker.querySelectorAll(".flacso-association-picker__item");items.forEach(function(item){var source=normalizeText(item.getAttribute("data-search")||item.textContent||"");item.style.display=source.indexOf(query)!==-1?"flex":"none";});}'
            . 'function initPicker(picker){if(picker.dataset.enhanced==="1"){return;}picker.dataset.enhanced="1";var list=picker.querySelector(".flacso-association-picker__list");var search=picker.querySelector(".flacso-association-picker__search");var selectVisibleButton=picker.querySelector("[data-action=\"select-visible\"]");var clearSelectionButton=picker.querySelector("[data-action=\"clear-selection\"]");if(search){search.addEventListener("input",function(){filterItems(picker);});}if(selectVisibleButton){selectVisibleButton.addEventListener("click",function(){var visibleItems=picker.querySelectorAll(".flacso-association-picker__item");visibleItems.forEach(function(item){if(item.style.display==="none"){return;}var input=item.querySelector("input[type=\"checkbox\"]");if(input){input.checked=true;}});updateCount(picker);});}if(clearSelectionButton){clearSelectionButton.addEventListener("click",function(){var selected=picker.querySelectorAll(".flacso-association-picker__item input:checked");selected.forEach(function(input){input.checked=false;});updateCount(picker);});}if(list){list.addEventListener("change",function(event){if(event.target&&event.target.matches("input[type=\"checkbox\"]")){updateCount(picker);}});}filterItems(picker);updateCount(picker);}'
            . 'function bootstrapPickers(){var pickers=document.querySelectorAll(".flacso-association-picker");pickers.forEach(initPicker);}'
            . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",bootstrapPickers);}else{bootstrapPickers();}'
            . '})();'
            . '</script>';
    }
}
