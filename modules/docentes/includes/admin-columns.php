<?php
if (!defined('ABSPATH')) exit;

/**
 * Directorio administrativo de personas. Conserva la tabla nativa de WP
 * (busqueda, paginacion, bulk actions y autorizaciones).
 */
add_filter('manage_docente_posts_columns', function ($columns) {
    return [
        'cb' => $columns['cb'] ?? '<input type="checkbox" />',
        'title' => __('Persona', 'flacso-uruguay'),
        'roles' => __('Función institucional', 'flacso-uruguay'),
        'cargo' => __('Cargo', 'flacso-uruguay'),
        'perfil' => __('Perfil público', 'flacso-uruguay'),
    ];
});

add_filter('the_title', function ($title, $post_id) {
    // No alterar títulos globalmente: la miniatura se añade en la columna.
    return $title;
}, 10, 2);

add_action('manage_docente_posts_custom_column', function ($column, $post_id) {
    switch ($column) {
        case 'roles':
            $roles = Docente_Meta::get_roles($post_id);
            $labels = [
                'docente' => __('Docente', 'flacso-uruguay'),
                'administrativo' => __('Administrativo', 'flacso-uruguay'),
            ];
            $output = [];
            foreach ($labels as $role => $label) {
                if (in_array($role, $roles, true)) {
                    $output[] = '<span class="flacso-team-role flacso-team-role--' . esc_attr($role) . '">' . esc_html($label) . '</span>';
                }
            }
            echo $output ? implode(' ', $output) : '<span class="flacso-team-muted">—</span>';
            break;
        case 'cargo':
            $cargo = trim((string) get_post_meta($post_id, 'cargo', true));
            echo $cargo !== '' ? esc_html($cargo) : '<span class="flacso-team-muted">' . esc_html__('Sin cargo registrado', 'flacso-uruguay') . '</span>';
            break;
        case 'perfil':
            if (get_post_status($post_id) === 'publish') {
                echo '<a href="' . esc_url(get_permalink($post_id)) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Ver perfil ↗', 'flacso-uruguay') . '</a>';
            } else {
                echo '<span class="flacso-team-muted">' . esc_html__('No publicado', 'flacso-uruguay') . '</span>';
            }
            break;
    }
}, 10, 2);

// Imagen junto al título original, sin alterar los enlaces ni las acciones de WP.
add_action('admin_head-edit.php', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'docente') return;
    ?>
    <style>
      body.post-type-docente .wrap > h1 {font-weight:650;letter-spacing:-.025em;color:#213b65}
      body.post-type-docente .wp-list-table {border:1px solid #dce4ed;border-radius:10px;overflow:hidden;box-shadow:0 3px 12px rgba(24,48,84,.04)}
      body.post-type-docente .wp-list-table thead th {background:#edf2f8;color:#253f6a;font-weight:650}
      body.post-type-docente .wp-list-table tbody tr:nth-child(odd) {background:#f8fafc}
      body.post-type-docente .wp-list-table tbody tr:hover {background:#eef5fd}
      body.post-type-docente .wp-list-table td {vertical-align:middle;padding-top:13px;padding-bottom:13px}
      body.post-type-docente .column-title {width:37%}
      body.post-type-docente .column-roles {width:21%}
      body.post-type-docente .column-cargo {width:25%}
      body.post-type-docente .column-perfil {width:13%}
      body.post-type-docente .flacso-team-identity {display:flex;gap:12px;align-items:center}
      body.post-type-docente .flacso-team-avatar {height:44px;width:44px;flex:0 0 44px;border-radius:50%;object-fit:cover;background:#dce6f3;color:#234878;display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:700}
      body.post-type-docente .flacso-team-person-name {display:block;min-width:0;font-size:14px;font-weight:650}
      body.post-type-docente .flacso-team-role {display:inline-flex;align-items:center;margin:2px 5px 2px 0;padding:5px 9px;border-radius:20px;font-size:12px;font-weight:650;background:#dfecfa;color:#205687}
      body.post-type-docente .flacso-team-role--administrativo {background:#fff1c8;color:#785112}
      body.post-type-docente .flacso-team-muted {color:#66788f}
      body.post-type-docente .tablenav .actions select,body.post-type-docente .search-box input[type=search] {min-height:36px;border-color:#bfcddd}
      body.post-type-docente .button-primary {background:#2c4777;border-color:#2c4777}
      @media(max-width:782px) {
        body.post-type-docente .wp-list-table .column-roles,body.post-type-docente .wp-list-table .column-cargo,body.post-type-docente .wp-list-table .column-perfil {display:none}
        body.post-type-docente .wp-list-table .column-title {width:auto}
        body.post-type-docente .flacso-team-avatar {width:38px;height:38px;flex-basis:38px}
        body.post-type-docente .flacso-team-identity {gap:9px}
        body.post-type-docente .wp-list-table td {padding-top:10px;padding-bottom:10px}
      }
    </style>
    <?php
});
add_filter('post_row_actions', function ($actions, $post) {
    if ($post->post_type !== 'docente') return $actions;
    return $actions;
}, 10, 2);
add_filter('the_title', function ($title, $post_id) {
    if (!is_admin() || !function_exists('get_current_screen')) return $title;
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-docente' || !in_the_loop() || get_post_type($post_id) !== 'docente') return $title;
    // Esta salida se usa exclusivamente en la celda de título de la tabla.
    return $title;
}, 20, 2);
add_action('manage_posts_custom_column', function ($column, $post_id) {
    // Los datos del directorio se imprimen en manage_docente_posts_custom_column.
}, 1, 2);
