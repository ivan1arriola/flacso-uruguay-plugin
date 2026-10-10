<?php
if (!defined('ABSPATH')) exit;

add_action('restrict_manage_posts', function() {
    global $typenow;
    if ($typenow !== 'docente') {
        return;
    }

    $current_role = isset($_GET['flacso_role']) ? sanitize_key($_GET['flacso_role']) : '';
    ?>
    <label class="screen-reader-text" for="flacso-team-role-filter"><?php esc_html_e('Filtrar por función institucional', 'flacso-uruguay'); ?></label>
    <select name="flacso_role" id="flacso-team-role-filter">
        <option value=""><?php esc_html_e('Todos los roles', 'flacso-uruguay'); ?></option>
        <option value="docente" <?php selected($current_role, 'docente'); ?>><?php esc_html_e('Solo Docentes', 'flacso-uruguay'); ?></option>
        <option value="administrativo" <?php selected($current_role, 'administrativo'); ?>><?php esc_html_e('Solo Administrativos / Gestión', 'flacso-uruguay'); ?></option>
    </select>
    <?php
});

add_filter('parse_query', function($query) {
    global $pagenow, $typenow;
    if (!is_admin() || $pagenow !== 'edit.php' || $typenow !== 'docente') {
        return;
    }

    if (!empty($_GET['flacso_role'])) {
        $role = sanitize_key($_GET['flacso_role']);
        if (in_array($role, ['docente', 'administrativo'], true)) {
            $meta_query = $query->get('meta_query') ?: [];
            $meta_query[] = [
                'key'     => 'roles',
                'value'   => '"' . $role . '"',
                'compare' => 'LIKE',
            ];
            $query->set('meta_query', $meta_query);
        }
    }
});
