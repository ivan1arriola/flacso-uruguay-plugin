<?php
if (!defined('ABSPATH')) exit;

add_filter('manage_docente_posts_columns', function($columns) {
    return [
        'cb'        => $columns['cb'] ?? '<input type="checkbox" />',
        'title'     => __('Nombre / Título', 'flacso-uruguay'),
        'roles'     => __('Roles', 'flacso-uruguay'),
        'cargo'     => __('Cargo / Rol', 'flacso-uruguay'),
        'date'      => $columns['date'] ?? __('Fecha', 'flacso-uruguay'),
    ];
});

add_action('manage_docente_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        case 'roles':
            $roles = Docente_Meta::get_roles($post_id);
            $badges = [];
            if (in_array('docente', $roles, true)) {
                $badges[] = '<span class="badge" style="background:#e0f2fe;color:#0369a1;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:600;">Docente</span>';
            }
            if (in_array('administrativo', $roles, true)) {
                $badges[] = '<span class="badge" style="background:#fef3c7;color:#92400e;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:600;">Administrativo</span>';
            }
            echo !empty($badges) ? implode(' ', $badges) : '—';
            break;
        case 'cargo':
            $cargo = (string) get_post_meta($post_id, 'cargo', true);
            echo $cargo !== '' ? esc_html($cargo) : '<span style="color:#94a3b8;">—</span>';
            break;
    }
}, 10, 2);
