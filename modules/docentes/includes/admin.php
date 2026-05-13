<?php
if (!defined('ABSPATH')) exit;

if(!class_exists('WP_List_Table')){
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Docente_List_Table extends WP_List_Table {

    function __construct(){
        parent::__construct([
            'singular'=>'docente',
            'plural'=>'docentes',
            'ajax'=>false
        ]);
    }

    function get_columns(){
        return [
            'cb'=>'<input type="checkbox">',
            'thumbnail' => 'Foto',
            'prefijo_abrev' => 'Prefijo',
            'nombre_completo'=>'Nombre Completo',
            'actions' => 'Acciones'
        ];
    }

    function column_cb($item){
        return sprintf('<input type="checkbox" name="docente[]" value="%d">', $item->ID);
    }

    function column_default($item, $column_name){
        switch($column_name){
            case 'thumbnail':
                $thumbnail = get_the_post_thumbnail($item->ID, [60, 60]);
                return $thumbnail ?: '<div style="width:60px;height:60px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;border-radius:4px;">&#128100;</div>';
            
            case 'prefijo_abrev': 
                $prefijo = get_post_meta($item->ID, 'prefijo_abrev', true);
                return $prefijo ? esc_html($prefijo) : '<span style="color:#ccc;">&mdash;</span>';
            
            case 'nombre_completo': 
                $nombre_completo = dp_nombre_completo($item->ID);
                $actions = [
                    'edit' => sprintf('<a href="%s">%s</a>', get_edit_post_link($item->ID), 'Editar'),
                    'quick_edit' => sprintf('<a href="%s" class="quick-edit-docente" data-id="%d">%s</a>', '#', $item->ID, 'Edición rápida'),
                    'delete' => sprintf('<a href="%s" style="color:#a00">%s</a>', get_delete_post_link($item->ID), 'Eliminar'),
                    'view' => sprintf('<a href="%s">%s</a>', get_permalink($item->ID), 'Ver')
                ];
                return '<strong>' . esc_html($nombre_completo) . '</strong>' . $this->row_actions($actions);
            
            case 'actions':
                return sprintf(
                    '<button type="button" class="button button-small quick-edit-docente" data-id="%d">Edición Rápida</button>',
                    $item->ID
                );
            
            default: return '';
        }
    }

    function get_sortable_columns(){
        return [
            'nombre_completo' => ['nombre_completo', false],
            'prefijo_abrev' => ['prefijo_abrev', false]
        ];
    }

    function prepare_items(){
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $orderby = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'title';
        $order = isset($_REQUEST['order']) ? $_REQUEST['order'] : 'ASC';

        $args = [
            'post_type' => 'docente',
            'posts_per_page' => $per_page,
            'paged' => $current_page,
            'post_status' => 'publish',
        ];

        if (!empty($_GET['s'])) {
            $args['s'] = $_GET['s'];
        }

        switch($orderby){
            case 'nombre_completo':
                $args['orderby'] = 'meta_value';
                $args['meta_key'] = 'nombre';
                $args['meta_type'] = 'CHAR';
                break;
            case 'prefijo_abrev':
                $args['orderby'] = 'meta_value';
                $args['meta_key'] = 'prefijo_abrev';
                $args['meta_type'] = 'CHAR';
                break;
            default:
                $args['orderby'] = 'title';
                break;
        }
        $args['order'] = $order;

        $query = new WP_Query($args);
        $this->items = $query->posts;
        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page' => $per_page,
            'total_pages' => $query->max_num_pages
        ]);
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
    }
}

function dp_docentes_admin_shared_styles() {
    static $printed = false;
    if ($printed) return;
    $printed = true;
    ?>
    <style>
        .dp-docentes-admin .dp-grid { display: grid; gap: 20px; margin-bottom: 30px; }
        .dp-docentes-admin .dp-stats { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .dp-docentes-admin .dp-two-columns { grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
        .dp-docentes-admin .dp-card { background: #fff; border: 1px solid #dcdcde; border-radius: 12px; padding: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .dp-docentes-admin .stat-card { position: relative; padding: 24px; }
        .dp-docentes-admin .stat-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; }
        .dp-docentes-admin .stat-value { display: block; font-size: 32px; margin: 10px 0 4px; color: #111827; }
        .dp-docentes-admin .dp-form .dp-form-row { margin-bottom: 15px; }
        .dp-docentes-admin .dp-form label { display: block; font-weight: 600; margin-bottom: 6px; }
        .dp-docentes-admin .required { color: #d63638; }
        .dp-shortcut { display: flex; gap: 12px; padding: 16px; border-radius: 12px; background: #f8fafc; text-decoration: none; border: 1px solid #e5e7eb; }
        .dp-shortcut:hover { border-color: #0d6efd; }
        .dp-shortcut .icon { font-size: 24px; color: #0d6efd; }
    </style>
    <?php
}

add_action('admin_menu', function(){
    remove_menu_page('edit.php?post_type=docente');
    add_menu_page('Docentes', 'Docentes', 'edit_posts', 'docentes_panel', 'docentes_dashboard_page', 'dashicons-welcome-learn-more', 5);
    add_submenu_page('docentes_panel', 'Panel', 'Panel', 'edit_posts', 'docentes_panel', 'docentes_dashboard_page');
    add_submenu_page('docentes_panel', 'Docentes', 'Docentes', 'edit_posts', 'docentes_lista', 'docentes_lista_page');
    add_submenu_page('docentes_panel', 'Añadir nuevo', 'Añadir nuevo', 'edit_posts', 'post-new.php?post_type=docente');
    add_submenu_page('docentes_panel', 'Documentacion', 'Documentacion', 'edit_posts', 'docentes_documentacion', 'docentes_documentacion_page');
    add_submenu_page('docentes_panel', 'API REST', 'API REST', 'edit_posts', 'docentes_api', 'docentes_api_page');
    add_submenu_page('docentes_panel', 'Migracion', 'Migracion', 'manage_options', 'docentes_migracion', 'docentes_migracion_page');
}, 9);

function docentes_dashboard_page(){
    if (!current_user_can('edit_posts')) wp_die(__('No tienes permisos suficientes.'));
    dp_docentes_admin_shared_styles();
    $notice_slug = 'dp_docentes_dashboard';

    if (!empty($_POST['dp_quick_add_docente'])) {
        check_admin_referer('dp_quick_add_docente', 'dp_quick_add_docente_nonce');
        $prefijo = sanitize_text_field($_POST['dp_docente_prefijo'] ?? '');
        $nombre = sanitize_text_field($_POST['dp_docente_nombre'] ?? '');
        $apellido = sanitize_text_field($_POST['dp_docente_apellido'] ?? '');
        
        if (!$nombre || !$apellido) {
            add_settings_error($notice_slug, 'missing', 'Nombre y apellido requeridos.', 'error');
        } else {
            $docente_id = wp_insert_post(['post_type' => 'docente', 'post_status' => 'publish', 'post_title' => trim("$prefijo $nombre $apellido")]);
            if (!is_wp_error($docente_id)) {
                update_post_meta($docente_id, 'prefijo_abrev', $prefijo);
                update_post_meta($docente_id, 'nombre', $nombre);
                update_post_meta($docente_id, 'apellido', $apellido);
                add_settings_error($notice_slug, 'success', 'Docente creado.', 'updated');
            }
        }
    }
    $docentes_count = wp_count_posts('docente');
    ?>
    <div class="wrap dp-docentes-admin">
        <h1>Centro de gestión de Docentes</h1>
        <?php settings_errors($notice_slug); ?>
        <div class="dp-grid dp-stats">
            <div class="dp-card stat-card"><span class="stat-label">Publicados</span><strong class="stat-value"><?php echo (int)($docentes_count->publish ?? 0); ?></strong></div>
        </div>
        <div class="dp-grid dp-two-columns">
            <div class="dp-card">
                <h2>Crear docente rápido</h2>
                <form method="post" class="dp-form">
                    <?php wp_nonce_field('dp_quick_add_docente', 'dp_quick_add_docente_nonce'); ?>
                    <input type="hidden" name="dp_quick_add_docente" value="1">
                    <div class="dp-form-row"><label>Prefijo</label><input type="text" name="dp_docente_prefijo"></div>
                    <div class="dp-form-row"><label>Nombre *</label><input type="text" name="dp_docente_nombre" required></div>
                    <div class="dp-form-row"><label>Apellido *</label><input type="text" name="dp_docente_apellido" required></div>
                    <button class="button button-primary">Crear docente</button>
                </form>
            </div>
        </div>
    </div>
    <?php
}

function docentes_lista_page(){
    $table = new Docente_List_Table();
    $table->prepare_items();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Docentes</h1>
        <form method="get">
            <input type="hidden" name="page" value="docentes_lista" />
            <?php $table->search_box('Buscar', 'search'); $table->display(); ?>
        </form>
    </div>
    <?php
}
