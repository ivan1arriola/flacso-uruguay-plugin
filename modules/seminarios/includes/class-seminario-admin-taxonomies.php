<?php
if (!defined('ABSPATH')) {
    exit;
}

class Seminario_Admin_Taxonomies
{
    public static function init()
    {
        add_action('programa_edit_form_fields', array(__CLASS__, 'edit_programa_color_field'), 10, 2);
        add_action('programa_add_form_fields', array(__CLASS__, 'add_programa_color_field'), 10, 2);
        add_action('edited_programa', array(__CLASS__, 'save_programa_color'), 10, 2);
        add_action('create_programa', array(__CLASS__, 'save_programa_color'), 10, 2);
    }

    public static function add_programa_color_field()
    {
        ?>
        <div class="form-field">
            <label for="programa_color"><?php _e('Color del Botón', 'cpt-seminario'); ?></label>
            <input type="color" id="programa_color" name="programa_color" value="#1d3a72" />
            <p class="description">
                <?php _e('Elige el color que se mostrará en el botón de este programa.', 'cpt-seminario'); ?>
            </p>
        </div>
        <?php
    }

    public static function edit_programa_color_field($term, $taxonomy)
    {
        $color = Seminario_Taxonomies::get_programa_color($term->term_id);
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="programa_color"><?php _e('Color del Botón', 'cpt-seminario'); ?></label>
            </th>
            <td>
                <input type="color" id="programa_color" name="programa_color" value="<?php echo esc_attr($color); ?>" />
                <p class="description">
                    <?php _e('Elige el color que se mostrará en el botón de este programa.', 'cpt-seminario'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    public static function save_programa_color($term_id, $tt_id = null)
    {
        if (isset($_POST['programa_color'])) {
            Seminario_Taxonomies::update_programa_color($term_id, sanitize_text_field($_POST['programa_color']));
        }
    }
}

// Inicializar si estamos en el admin
if (is_admin()) {
    Seminario_Admin_Taxonomies::init();
}
