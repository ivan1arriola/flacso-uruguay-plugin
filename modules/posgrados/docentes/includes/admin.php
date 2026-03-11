<?php
if (!defined('ABSPATH')) exit;

if(!class_exists('WP_List_Table')){
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Docente_List_Table extends WP_List_Table {

    private $team_colors = [];

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
            'equipo'=>'Equipo académico',
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
                return $thumbnail ?: '<div style="width:60px;height:60px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;border-radius:4px;">📷</div>';
            
            case 'prefijo_abrev': 
                $prefijo = get_post_meta($item->ID, 'prefijo_abrev', true);
                return $prefijo ? esc_html($prefijo) : '<span style="color:#ccc;">—</span>';
            
            case 'nombre_completo': 
                $nombre_completo = dp_nombre_completo($item->ID);
                $actions = [
                    'edit' => sprintf('<a href="%s">%s</a>', get_edit_post_link($item->ID), 'Editar'),
                    'quick_edit' => sprintf('<a href="%s" class="quick-edit-docente" data-id="%d">%s</a>', '#', $item->ID, 'Edición rápida'),
                    'delete' => sprintf('<a href="%s" style="color:#a00">%s</a>', get_delete_post_link($item->ID), 'Eliminar'),
                    'view' => sprintf('<a href="%s">%s</a>', get_permalink($item->ID), 'Ver')
                ];
                return '<strong>' . esc_html($nombre_completo) . '</strong>' . $this->row_actions($actions);
            
            case 'equipo':
                $terms = get_the_terms($item->ID, 'equipo-docente');
                if($terms && !is_wp_error($terms)){
                    $output = '';
                    foreach($terms as $term){
                        $color = $this->get_team_color($term->term_id);
                        $output .= '<span style="display:inline-block;background:'.$color.';color:#fff;padding:2px 8px;border-radius:12px;margin:1px;font-size:11px;line-height:1.3;">'.esc_html($term->name).'</span>';
                    }
                    return $output;
                }
                return '<span style="color:#ccc;">Sin equipo</span>';
            
            case 'actions':
                return sprintf(
                    '<button type="button" class="button button-small quick-edit-docente" data-id="%d">Edición Rápida</button>',
                    $item->ID
                );
            
            default: return '';
        }
    }

    // =============================
// FUNCIONES DE COLORES
// =============================

    // Obtener el color de un equipo
    private function get_team_color($term_id){
        if(isset($this->team_colors[$term_id])){
            return $this->team_colors[$term_id];
        }

        // Buscar si el término tiene un color asignado en la meta
        $color = get_term_meta($term_id, 'color_equipo', true);

        if(!$color){
            // Si no existe, asignar uno aleatorio de la paleta y guardarlo
            $colors = ['#0073aa','#46b450','#d54e21','#ffb900','#7928a1','#dd9933','#00a0d2','#e91e63','#009688'];
            $color = $colors[array_rand($colors)];
            update_term_meta($term_id, 'color_equipo', $color);
        }

        $this->team_colors[$term_id] = $color;
        return $color;
    }


    function get_sortable_columns(){
        return [
            'nombre_completo' => ['nombre_completo', false],
            'prefijo_abrev' => ['prefijo_abrev', false]
        ];
    }

    function extra_tablenav($which) {
        if ($which === "top") {
            $selected_team = isset($_GET['equipo_filter']) ? $_GET['equipo_filter'] : '';
            $terms = get_terms(['taxonomy' => 'equipo-docente', 'hide_empty' => true]);
            ?>
            <div class="alignleft actions">
                <label for="equipo-filter" class="screen-reader-text">Filtrar por equipo</label>
                <select name="equipo_filter" id="equipo-filter">
                    <option value="">Todos los equipos</option>
                    <?php foreach ($terms as $term): ?>
                        <option value="<?php echo $term->slug; ?>" <?php selected($selected_team, $term->slug); ?>>
                            <?php echo $term->name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button('Filtrar', '', 'filter_action', false); ?>
                <?php if (isset($_GET['equipo_filter'])): ?>
                    <a href="<?php echo remove_query_arg(['equipo_filter', 'paged']); ?>" class="button">Limpiar filtro</a>
                <?php endif; ?>
            </div>
            <?php
        }
    }

    function prepare_items(){
        global $wpdb;
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $orderby = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'title';
        $order = isset($_REQUEST['order']) ? $_REQUEST['order'] : 'ASC';

        $args = [
            'post_type' => 'docente',
            'posts_per_page' => $per_page,
            'paged' => $current_page,
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'nombre',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => 'nombre',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ];

        // Filtro por equipo - CORREGIDO
        if (!empty($_GET['equipo_filter'])) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'equipo-docente',
                    'field' => 'slug',
                    'terms' => sanitize_text_field($_GET['equipo_filter'])
                ]
            ];
        }

        // Búsqueda
        if (!empty($_GET['s'])) {
            $args['s'] = $_GET['s'];
        }

        // Ordenamiento CORREGIDO - maneja campos vacíos
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

function dp_admin_handle_quick_equipo_submission($notice_slug, $form_key = 'dp_quick_add_equipo') {
    if (empty($_POST[$form_key])) {
        return false;
    }

    $nonce_field = $form_key . '_nonce';
    if (empty($_POST[$nonce_field]) || !wp_verify_nonce($_POST[$nonce_field], $form_key)) {
        add_settings_error($notice_slug, $form_key . '_nonce', 'No se pudo validar el formulario.', 'error');
        return true;
    }

    $nombre = isset($_POST['dp_equipo_nombre']) ? sanitize_text_field(wp_unslash($_POST['dp_equipo_nombre'])) : '';
    $slug = isset($_POST['dp_equipo_slug']) ? sanitize_title(wp_unslash($_POST['dp_equipo_slug'])) : '';
    $color = isset($_POST['dp_equipo_color']) ? sanitize_hex_color(wp_unslash($_POST['dp_equipo_color'])) : '';
    $relacion_nombre = isset($_POST['dp_equipo_relacion_nombre']) ? sanitize_text_field(wp_unslash($_POST['dp_equipo_relacion_nombre'])) : '';
    $page_id = isset($_POST['dp_equipo_page_id']) ? absint($_POST['dp_equipo_page_id']) : 0;
    $description = isset($_POST['dp_equipo_description']) ? sanitize_textarea_field(wp_unslash($_POST['dp_equipo_description'])) : '';

    if (!$nombre) {
        add_settings_error($notice_slug, $form_key . '_missing', 'Completa al menos el nombre del equipo.', 'error');
        return true;
    }

    $args = [];
    if ($slug) {
        $args['slug'] = $slug;
    }
    if ($description) {
        $args['description'] = $description;
    }

    $term = wp_insert_term($nombre, 'equipo-docente', $args);
    if (is_wp_error($term)) {
        add_settings_error($notice_slug, $form_key . '_error', $term->get_error_message(), 'error');
        return true;
    }

    $term_id = isset($term['term_id']) ? (int) $term['term_id'] : 0;
    if ($term_id) {
        update_term_meta($term_id, 'equipo_docente_color', $color ?: '#0d6efd');
        if ($relacion_nombre !== '') {
            update_term_meta($term_id, 'equipo_docente_relacion_nombre', $relacion_nombre);
        } else {
            delete_term_meta($term_id, 'equipo_docente_relacion_nombre');
        }
        if ($page_id) {
            update_term_meta($term_id, 'equipo_docente_page_id', $page_id);
        } else {
            delete_term_meta($term_id, 'equipo_docente_page_id');
        }
    }

    $edit_link = $term_id ? get_edit_term_link($term_id, 'equipo-docente') : '';
    $message = $edit_link
        ? sprintf('Equipo creado correctamente. <a href="%s">Editar equipo</a>', esc_url($edit_link))
        : 'Equipo creado correctamente.';

    add_settings_error($notice_slug, $form_key . '_success', $message, 'updated');
    return true;
}

function dp_docentes_admin_shared_styles() {
    static $printed = false;
    if ($printed) {
        return;
    }
    $printed = true;
    ?>
    <style>
        .dp-docentes-admin .dp-grid {
            display: grid;
            gap: 20px;
            margin-bottom: 30px;
        }
        .dp-docentes-admin .dp-stats {
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .dp-docentes-admin .dp-two-columns {
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }
        .dp-docentes-admin .dp-shortcuts {
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        }
        .dp-docentes-admin .dp-card {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .dp-docentes-admin .stat-card {
            position: relative;
            padding: 24px;
        }
        .dp-docentes-admin .stat-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
        }
        .dp-docentes-admin .stat-value {
            display: block;
            font-size: 32px;
            margin: 10px 0 4px;
            color: #111827;
        }
        .dp-docentes-admin .stat-meta {
            font-size: 13px;
            color: #6b7280;
        }
        .dp-docentes-admin .dp-form .dp-form-row {
            margin-bottom: 15px;
        }
        .dp-docentes-admin .dp-form label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .dp-docentes-admin .required {
            color: #d63638;
        }
        .dp-docentes-admin .dp-mini-table .description {
            font-size: 12px;
            color: #6b7280;
        }
        .dp-docentes-admin .color-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            margin-right: 6px;
            border: 1px solid rgba(0,0,0,0.1);
            vertical-align: middle;
        }
        .dp-docentes-admin .dp-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .dp-shortcut {
            display: flex;
            gap: 12px;
            padding: 16px;
            border-radius: 12px;
            background: #f8fafc;
            text-decoration: none;
            border: 1px solid #e5e7eb;
        }
        .dp-shortcut:hover {
            border-color: #0d6efd;
        }
        .dp-shortcut .icon {
            font-size: 24px;
            color: #0d6efd;
        }
        @media (max-width: 782px) {
            .dp-docentes-admin .dp-card {
                padding: 16px;
            }
        }
    </style>
    <?php
}

// Reemplazar página admin de docentes
add_action('admin_menu', function(){
    remove_menu_page('edit.php?post_type=docente');
    
    add_menu_page(
        'Docentes',
        'Docentes',
        'edit_posts',
        'docentes_panel',
        'docentes_dashboard_page',
        'dashicons-welcome-learn-more',
        5
    );

    add_submenu_page(
        'docentes_panel',
        'Panel de Docentes',
        'Panel',
        'edit_posts',
        'docentes_panel',
        'docentes_dashboard_page'
    );

    add_submenu_page(
        'docentes_panel',
        'Docentes',
        'Docentes',
        'edit_posts',
        'docentes_lista',
        'docentes_lista_page'
    );

    add_submenu_page(
        'docentes_panel',
        'Equipos Docentes',
        'Equipos',
        'do_not_allow',
        'docentes_equipos',
        'docentes_equipos_page'
    );

    add_submenu_page(
        'docentes_panel',
        'Añadir nuevo docente',
        'Añadir nuevo',
        'edit_posts',
        'post-new.php?post_type=docente'
    );

    add_submenu_page(
        'docentes_panel',
        'Documentacion de Docentes',
        'Documentacion',
        'edit_posts',
        'docentes_documentacion',
        'docentes_documentacion_page'
    );

    add_submenu_page(
        'docentes_panel',
        'API REST de Docentes',
        'API REST',
        'edit_posts',
        'docentes_api',
        'docentes_api_page'
    );
}, 9);

add_action('admin_init', function() {
    $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
    if ($page === 'docentes_equipos') {
        wp_die(__('Esta pagina ya no esta disponible.'), 403);
    }
});

function docentes_dashboard_page(){
    if (!current_user_can('edit_posts')) {
        wp_die(__('No tienes permisos suficientes para acceder a esta página.'));
    }

    dp_docentes_admin_shared_styles();

    $notice_slug = 'dp_docentes_dashboard';

    if (!empty($_POST['dp_quick_add_docente'])) {
        check_admin_referer('dp_quick_add_docente', 'dp_quick_add_docente_nonce');

        $prefijo = isset($_POST['dp_docente_prefijo']) ? sanitize_text_field(wp_unslash($_POST['dp_docente_prefijo'])) : '';
        $nombre = isset($_POST['dp_docente_nombre']) ? sanitize_text_field(wp_unslash($_POST['dp_docente_nombre'])) : '';
        $apellido = isset($_POST['dp_docente_apellido']) ? sanitize_text_field(wp_unslash($_POST['dp_docente_apellido'])) : '';
        $equipo = isset($_POST['dp_docente_equipo']) ? absint($_POST['dp_docente_equipo']) : 0;
        $estado = isset($_POST['dp_docente_estado']) && $_POST['dp_docente_estado'] === 'draft' ? 'draft' : 'publish';

        if (!$nombre || !$apellido) {
            add_settings_error($notice_slug, 'dp_quick_add_docente_missing', 'Ingresá al menos nombre y apellido para crear el docente.', 'error');
        } else {
            $titulo = trim($prefijo . ' ' . $nombre . ' ' . $apellido);
            if ($titulo === '') {
                $titulo = 'Docente sin nombre';
            }

            $docente_id = wp_insert_post([
                'post_type' => 'docente',
                'post_status' => $estado,
                'post_title' => $titulo,
            ], true);

            if (is_wp_error($docente_id)) {
                add_settings_error($notice_slug, 'dp_quick_add_docente_error', $docente_id->get_error_message(), 'error');
            } else {
                update_post_meta($docente_id, 'prefijo_abrev', $prefijo);
                update_post_meta($docente_id, 'nombre', $nombre);
                update_post_meta($docente_id, 'apellido', $apellido);
                if ($equipo) {
                    wp_set_object_terms($docente_id, [$equipo], 'equipo-docente');
                }

                $edit_link = get_edit_post_link($docente_id);
                $message = $edit_link
                    ? sprintf('Docente creado correctamente. <a href="%s">Ir a la edición</a>.', esc_url($edit_link))
                    : 'Docente creado correctamente.';
                add_settings_error($notice_slug, 'dp_quick_add_docente_success', $message, 'updated');
            }
        }
    }

    dp_admin_handle_quick_equipo_submission($notice_slug);

    $docentes_count = wp_count_posts('docente');
    $docentes_publicados = isset($docentes_count->publish) ? (int) $docentes_count->publish : 0;
    $docentes_borrador = isset($docentes_count->draft) ? (int) $docentes_count->draft : 0;

    $equipos_total = wp_count_terms('equipo-docente', ['hide_empty' => false]);
    $equipos_total = is_wp_error($equipos_total) ? 0 : (int) $equipos_total;

    $sin_equipo_query = new WP_Query([
        'post_type' => 'docente',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'no_found_rows' => false,
        'tax_query' => [
            [
                'taxonomy' => 'equipo-docente',
                'operator' => 'NOT EXISTS',
            ]
        ]
    ]);
    $docentes_sin_equipo = (int) $sin_equipo_query->found_posts;
    wp_reset_postdata();

    $ultimo_docente = get_posts([
        'post_type' => 'docente',
        'posts_per_page' => 1,
        'orderby' => 'modified',
        'order' => 'DESC'
    ]);
    $ultima_actualizacion = $ultimo_docente ? get_post_modified_time(get_option('date_format'), false, $ultimo_docente[0]->ID, true) : __('Sin registros', 'docentes-plugin');

    $equipos = get_terms([
        'taxonomy' => 'equipo-docente',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ]);

    $docentes_recientes = get_posts([
        'post_type' => 'docente',
        'posts_per_page' => 5,
        'orderby' => 'modified',
        'order' => 'DESC'
    ]);

    $equipos_destacados = get_terms([
        'taxonomy' => 'equipo-docente',
        'hide_empty' => false,
        'orderby' => 'count',
        'order' => 'DESC',
        'number' => 5
    ]);

    $documentacion_url = admin_url('admin.php?page=docentes_documentacion');
    $tabla_docentes_url = admin_url('admin.php?page=docentes_lista');
    $equipos_url = admin_url('admin.php?page=docentes_equipos');
    $nuevo_docente_url = admin_url('post-new.php?post_type=docente');

    ?>
    <div class="wrap dp-docentes-admin">
        <h1>Centro de gestión de Docentes</h1>
        <p class="description">Panel principal para administrar docentes, equipos y acceder rápidamente a las acciones más frecuentes.</p>
        <?php settings_errors($notice_slug); ?>

        <div class="dp-grid dp-stats">
            <div class="dp-card stat-card">
                <span class="stat-label">Perfiles publicados</span>
                <strong class="stat-value"><?php echo esc_html($docentes_publicados); ?></strong>
                <span class="stat-meta">Borradores: <?php echo esc_html($docentes_borrador); ?></span>
            </div>
            <div class="dp-card stat-card">
                <span class="stat-label">Equipos</span>
                <strong class="stat-value"><?php echo esc_html($equipos_total); ?></strong>
                <span class="stat-meta"><?php echo esc_html($docentes_sin_equipo); ?> docentes sin equipo</span>
            </div>
            <div class="dp-card stat-card">
                <span class="stat-label">Última actualización</span>
                <strong class="stat-value"><?php echo esc_html($ultima_actualizacion); ?></strong>
                <span class="stat-meta">Basado en el último CV actualizado</span>
            </div>
        </div>

        <div class="dp-grid dp-two-columns">
            <div class="dp-card">
                <h2>Crear docente rápido</h2>
                <p class="description">Registra un nuevo docente con los datos básicos. Luego podrás completar el resto desde la edición completa.</p>
                <form method="post" class="dp-form">
                    <?php wp_nonce_field('dp_quick_add_docente', 'dp_quick_add_docente_nonce'); ?>
                    <input type="hidden" name="dp_quick_add_docente" value="1">
                    <div class="dp-form-row">
                        <label for="dp_docente_prefijo">Prefijo</label>
                        <input type="text" id="dp_docente_prefijo" name="dp_docente_prefijo" placeholder="Ing., Dra., etc.">
                    </div>
                    <div class="dp-form-row">
                        <label for="dp_docente_nombre">Nombre <span class="required">*</span></label>
                        <input type="text" id="dp_docente_nombre" name="dp_docente_nombre" required>
                    </div>
                    <div class="dp-form-row">
                        <label for="dp_docente_apellido">Apellido <span class="required">*</span></label>
                        <input type="text" id="dp_docente_apellido" name="dp_docente_apellido" required>
                    </div>
                    <div class="dp-form-row">
                        <label for="dp_docente_equipo">Equipo</label>
                        <select id="dp_docente_equipo" name="dp_docente_equipo">
                            <option value="">Sin equipo por ahora</option>
                            <?php if ($equipos && !is_wp_error($equipos)): ?>
                                <?php foreach ($equipos as $equipo): ?>
                                    <option value="<?php echo esc_attr($equipo->term_id); ?>"><?php echo esc_html($equipo->name); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="dp-form-row">
                        <label for="dp_docente_estado">Estado</label>
                        <select id="dp_docente_estado" name="dp_docente_estado">
                            <option value="publish">Publicar ahora</option>
                            <option value="draft">Guardar como borrador</option>
                        </select>
                    </div>
                    <button class="button button-primary button-large">Crear docente</button>
                </form>
            </div>

            <div class="dp-card">
                <h2>Nuevo equipo docente</h2>
                <p class="description">Agrupa docentes en ofertas o cohorts y dales un color identificatorio.</p>
                <form method="post" class="dp-form">
                    <?php wp_nonce_field('dp_quick_add_equipo', 'dp_quick_add_equipo_nonce'); ?>
                    <input type="hidden" name="dp_quick_add_equipo" value="1">
                    <div class="dp-form-row">
                        <label for="dp_equipo_nombre">Nombre del equipo <span class="required">*</span></label>
                        <input type="text" id="dp_equipo_nombre" name="dp_equipo_nombre" required>
                    </div>
                    <div class="dp-form-row">
                        <label for="dp_equipo_slug">Slug</label>
                        <input type="text" id="dp_equipo_slug" name="dp_equipo_slug" placeholder="opcional">
                    </div>
                    <div class="dp-form-row">
                        <label for="dp_equipo_color">Color</label>
                        <input type="color" id="dp_equipo_color" name="dp_equipo_color" value="#0d6efd">
                    </div>
                    <div class="dp-form-row">
                        <label for="dp_equipo_page_id">Página de posgrado</label>
                        <?php echo dp_equipo_docente_pages_dropdown(0, 'dp_equipo_page_id', 'dp_equipo_page_id'); ?>
                    </div>
                    <div class="dp-form-row">
                        <label for="dp_equipo_description">Descripción</label>
                        <textarea id="dp_equipo_description" name="dp_equipo_description" rows="3"></textarea>
                    </div>
                    <button class="button button-secondary button-large">Crear equipo</button>
                </form>
            </div>
        </div>

        <div class="dp-grid dp-two-columns">
            <div class="dp-card">
                <div class="dp-card-header">
                    <h2>Docentes recientes</h2>
                    <a class="button button-link" href="<?php echo esc_url($tabla_docentes_url); ?>">Ver todo</a>
                </div>
                <table class="widefat dp-mini-table">
                    <thead>
                        <tr>
                            <th>Docente</th>
                            <th>Equipo</th>
                            <th>Actualizado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($docentes_recientes): ?>
                            <?php foreach ($docentes_recientes as $docente): ?>
                                <?php
                                    $equipos_doc = get_the_terms($docente->ID, 'equipo-docente');
                                    $equipo_nombre = $equipos_doc && !is_wp_error($equipos_doc) ? $equipos_doc[0]->name : 'Sin equipo';
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html(get_the_title($docente->ID)); ?></strong>
                                        <div class="description"><a href="<?php echo esc_url(get_edit_post_link($docente->ID)); ?>">Editar</a> · <a href="<?php echo esc_url(get_permalink($docente->ID)); ?>" target="_blank">Ver</a></div>
                                    </td>
                                    <td><?php echo esc_html($equipo_nombre); ?></td>
                                    <td><?php echo esc_html(get_post_modified_time(get_option('date_format'), false, $docente->ID, true)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3">Aún no hay docentes creados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="dp-card">
                <div class="dp-card-header">
                    <h2>Equipos destacados</h2>
                    <a class="button button-link" href="<?php echo esc_url($equipos_url); ?>">Gestionar equipos</a>
                </div>
                <table class="widefat dp-mini-table">
                    <thead>
                        <tr>
                            <th>Equipo</th>
                            <th>Color</th>
                            <th>Docentes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($equipos_destacados && !is_wp_error($equipos_destacados)): ?>
                            <?php foreach ($equipos_destacados as $equipo): ?>
                                <?php $color = get_term_meta($equipo->term_id, 'equipo_docente_color', true); ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($equipo->name); ?></strong>
                                        <div class="description"><a href="<?php echo esc_url(get_edit_term_link($equipo)); ?>">Editar</a></div>
                                    </td>
                                    <td>
                                        <span class="color-dot" style="background: <?php echo esc_attr($color ?: '#0d6efd'); ?>"></span>
                                        <?php echo esc_html($color ?: '#0d6efd'); ?>
                                    </td>
                                    <td><?php echo esc_html($equipo->count); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3">Crea tu primer equipo para empezar.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dp-grid dp-shortcuts">
            <a class="dp-shortcut" href="<?php echo esc_url($nuevo_docente_url); ?>">
                <span class="icon dashicons dashicons-plus-alt2"></span>
                <div>
                    <strong>Agregar docente</strong>
                    <p>Formulario completo con todos los campos disponibles.</p>
                </div>
            </a>
            <a class="dp-shortcut" href="<?php echo esc_url($tabla_docentes_url); ?>">
                <span class="icon dashicons dashicons-list-view"></span>
                <div>
                    <strong>Listado avanzado</strong>
                    <p>Búsquedas, filtros y edición rápida del CPT.</p>
                </div>
            </a>
            <a class="dp-shortcut" href="<?php echo esc_url($documentacion_url); ?>">
                <span class="icon dashicons dashicons-media-text"></span>
                <div>
                    <strong>Documentación</strong>
                    <p>Guía para el equipo de contenidos.</p>
                </div>
            </a>
        </div>
    </div>
    <?php
}

function docentes_lista_page(){
    $table = new Docente_List_Table();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Docentes</h1>
        <a href="<?php echo admin_url('post-new.php?post_type=docente'); ?>" class="page-title-action">Añadir nuevo docente</a>
        
        <?php
        // Mostrar avisos
        if (isset($_GET['quick_edit_success'])) {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Docente actualizado correctamente.</p></div>';
        }
        if (isset($_GET['quick_edit_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>❌ Error al actualizar el docente.</p></div>';
        }
        ?>
        
        <hr class="wp-header-end">
        
        <?php
        $table->prepare_items();
        echo '<form method="get">';
        echo '<input type="hidden" name="post_type" value="docente" />';
        echo '<input type="hidden" name="page" value="docentes_lista" />';
        $table->search_box('Buscar docentes', 'search');
        echo '</form>';
        
        echo '<form method="post">';
        $table->display();
        echo '</form>';
        ?>

        <!-- Modal de Edición Rápida -->
        <div id="quick-edit-modal" style="display:none;">
            <div class="quick-edit-modal-content">
                <div class="quick-edit-modal-header">
                    <h2>Edición Rápida del Docente</h2>
                    <span class="close">&times;</span>
                </div>
                <form id="quick-edit-form" method="post">
                    <?php wp_nonce_field('docente_quick_edit', 'docente_nonce'); ?>
                    <input type="hidden" name="action" value="update_docente_quick">
                    <input type="hidden" name="docente_id" id="docente_id" value="">
                    
                    <div class="quick-edit-fields">
                        <div class="field-group">
                            <label for="prefijo_abrev">Prefijo (abreviado):</label>
                            <input type="text" id="prefijo_abrev" name="prefijo_abrev" placeholder="Ing., Dra., Dr.">
                        </div>
                        
                        <div class="field-group">
                            <label for="prefijo_full">Prefijo (completo):</label>
                            <input type="text" id="prefijo_full" name="prefijo_full" placeholder="Ingeniero, Doctora, Doctor">
                        </div>
                        
                        <div class="field-group">
                            <label for="nombre">Nombre *:</label>
                            <input type="text" id="nombre" name="nombre" required>
                        </div>
                        
                        <div class="field-group">
                            <label for="apellido">Apellido *:</label>
                            <input type="text" id="apellido" name="apellido" required>
                        </div>
                        
                        <div class="field-group">
                            <label>Equipo Docente:</label>
                            <div class="equipo-checkboxes">
                                <?php 
                                $terms = get_terms(['taxonomy' => 'equipo-docente', 'hide_empty' => false]);
                                foreach($terms as $term): ?>
                                <label style="display:block; margin:5px 0;">
                                    <input type="checkbox" name="equipo_docente[]" value="<?php echo $term->term_id; ?>">
                                    <?php echo $term->name; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="quick-edit-modal-footer">
                        <button type="submit" class="button button-primary">Guardar Cambios</button>
                        <button type="button" class="button cancel">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
    .column-thumbnail { width: 80px; text-align: center; }
    .column-prefijo_abrev { width: 100px; }
    .column-nombre_completo { width: 300px; }
    .column-equipo { width: 250px; }
    .column-actions { width: 150px; }
    
    #quick-edit-modal {
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .quick-edit-modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 0;
        border: 1px solid #ccc;
        width: 500px;
        border-radius: 4px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .quick-edit-modal-header {
        background: #f0f0f0;
        padding: 15px 20px;
        border-bottom: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .quick-edit-modal-header h2 {
        margin: 0;
        font-size: 18px;
    }
    
    .close {
        color: #aaa;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .close:hover {
        color: #000;
    }
    
    .quick-edit-fields {
        padding: 20px;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .field-group {
        margin-bottom: 15px;
    }
    
    .field-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
    }
    
    .field-group input[type="text"] {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 3px;
    }
    
    .quick-edit-modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #ddd;
        background: #f9f9f9;
        text-align: right;
    }
    
    .quick-edit-docente {
        cursor: pointer;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        var modal = $('#quick-edit-modal');
        var form = $('#quick-edit-form');
        
        // Abrir modal
        $('.quick-edit-docente').on('click', function(e) {
            e.preventDefault();
            var docenteId = $(this).data('id');
            
            // Cargar datos del docente via AJAX
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_docente_data',
                    docente_id: docenteId,
                    nonce: '<?php echo wp_create_nonce('get_docente_data'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        $('#docente_id').val(docenteId);
                        $('#prefijo_abrev').val(data.prefijo_abrev || '');
                        $('#prefijo_full').val(data.prefijo_full || '');
                        $('#nombre').val(data.nombre || '');
                        $('#apellido').val(data.apellido || '');
                        
                        // Limpiar checkboxes
                        $('input[name="equipo_docente[]"]').prop('checked', false);
                        
                        // Marcar equipos actuales
                        if (data.equipos) {
                            data.equipos.forEach(function(equipoId) {
                                $('input[name="equipo_docente[]"][value="' + equipoId + '"]').prop('checked', true);
                            });
                        }
                        
                        modal.show();
                    }
                }
            });
        });
        
        // Cerrar modal
        $('.close, .cancel').on('click', function() {
            modal.hide();
        });
        
        // Cerrar al hacer clic fuera del modal
        $(window).on('click', function(e) {
            if (e.target == modal[0]) {
                modal.hide();
            }
        });
        
        // Enviar formulario
        form.on('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        window.location.href = '<?php echo add_query_arg('quick_edit_success', '1', remove_query_arg(['quick_edit_error'])); ?>';
                    } else {
                        window.location.href = '<?php echo add_query_arg('quick_edit_error', '1', remove_query_arg(['quick_edit_success'])); ?>';
                    }
                }
            });
        });
    });
    </script>
    <?php
}

// AJAX para obtener datos del docente
add_action('wp_ajax_get_docente_data', function() {
    check_ajax_referer('get_docente_data', 'nonce');
    
    $docente_id = intval($_POST['docente_id']);
    
    if (!$docente_id) {
        wp_die();
    }
    
    $data = [
        'prefijo_abrev' => get_post_meta($docente_id, 'prefijo_abrev', true),
        'prefijo_full' => get_post_meta($docente_id, 'prefijo_full', true),
        'nombre' => get_post_meta($docente_id, 'nombre', true),
        'apellido' => get_post_meta($docente_id, 'apellido', true),
        'equipos' => wp_get_post_terms($docente_id, 'equipo-docente', ['fields' => 'ids'])
    ];
    
    wp_send_json_success($data);
});

// AJAX para guardar edición rápida
add_action('wp_ajax_update_docente_quick', function() {
    check_ajax_referer('docente_quick_edit', 'docente_nonce');
    
    $docente_id = intval($_POST['docente_id']);
    
    if (!$docente_id || !current_user_can('edit_post', $docente_id)) {
        wp_send_json_error('No tiene permisos para editar este docente');
    }
    
    // Campos obligatorios
    if (empty($_POST['nombre']) || empty($_POST['apellido'])) {
        wp_send_json_error('Nombre y Apellido son obligatorios');
    }
    
    // Actualizar campos
    $campos = ['prefijo_abrev', 'prefijo_full', 'nombre', 'apellido'];
    foreach ($campos as $campo) {
        if (isset($_POST[$campo])) {
            update_post_meta($docente_id, $campo, sanitize_text_field($_POST[$campo]));
        }
    }
    
    // Actualizar equipo docente
    if (isset($_POST['equipo_docente'])) {
        wp_set_object_terms($docente_id, array_map('intval', $_POST['equipo_docente']), 'equipo-docente');
    } else {
        wp_delete_object_term_relationships($docente_id, 'equipo-docente');
    }
    
    wp_send_json_success('Docente actualizado correctamente');
});

function docentes_equipos_page(){
    if (!current_user_can('edit_posts')) {
        wp_die(__('No tienes permisos suficientes para acceder a esta página.'));
    }

    dp_docentes_admin_shared_styles();

    $notice_slug = 'dp_docentes_equipos';

    if (!empty($_POST['dp_bulk_update_equipo'])) {
        check_admin_referer('dp_bulk_update_equipo', 'dp_bulk_update_equipo_nonce');

        $term_ids = isset($_POST['bulk_term_ids']) ? array_map('absint', (array) $_POST['bulk_term_ids']) : [];
        $term_ids = array_values(array_filter(array_unique($term_ids)));

        $do_color = !empty($_POST['bulk_update_color']);
        $do_relation = !empty($_POST['bulk_update_relation']);
        $do_page = !empty($_POST['bulk_update_page']);

        if (!$term_ids) {
            add_settings_error($notice_slug, 'dp_bulk_update_missing', 'Selecciona al menos un equipo para actualizar.', 'error');
        } elseif (!$do_color && !$do_relation && !$do_page) {
            add_settings_error($notice_slug, 'dp_bulk_update_empty', 'Selecciona al menos un campo para actualizar.', 'error');
        } else {
            $color = $do_color ? sanitize_hex_color(wp_unslash($_POST['bulk_color'] ?? '')) : '';
            if ($do_color && !$color) {
                add_settings_error($notice_slug, 'dp_bulk_update_color', 'Selecciona un color valido para aplicar.', 'error');
            } else {
                $relation = $do_relation ? sanitize_text_field(wp_unslash($_POST['bulk_relation_name'] ?? '')) : null;
                $page_id = $do_page ? absint($_POST['bulk_page_id'] ?? 0) : null;

                foreach ($term_ids as $term_id) {
                    if ($do_color) {
                        update_term_meta($term_id, 'equipo_docente_color', $color);
                    }
                    if ($do_relation) {
                        if ($relation !== '') {
                            update_term_meta($term_id, 'equipo_docente_relacion_nombre', $relation);
                        } else {
                            delete_term_meta($term_id, 'equipo_docente_relacion_nombre');
                        }
                    }
                    if ($do_page) {
                        if ($page_id) {
                            update_term_meta($term_id, 'equipo_docente_page_id', $page_id);
                        } else {
                            delete_term_meta($term_id, 'equipo_docente_page_id');
                        }
                    }
                }

                add_settings_error($notice_slug, 'dp_bulk_update_success', sprintf('Actualizacion masiva aplicada en %d equipos.', count($term_ids)), 'updated');
            }
        }
    } elseif (!empty($_POST['dp_delete_equipo'])) {
        check_admin_referer('dp_delete_equipo', 'dp_delete_equipo_nonce');

        $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        if (!$term_id) {
            add_settings_error($notice_slug, 'dp_delete_equipo_missing', 'No se pudo identificar el equipo a eliminar.', 'error');
        } else {
            $result = wp_delete_term($term_id, 'equipo-docente');
            if (is_wp_error($result)) {
                add_settings_error($notice_slug, 'dp_delete_equipo_error', $result->get_error_message(), 'error');
            } else {
                add_settings_error($notice_slug, 'dp_delete_equipo_success', 'Equipo eliminado correctamente.', 'updated');
            }
        }
    } elseif (!empty($_POST['dp_update_equipo'])) {
        check_admin_referer('dp_update_equipo', 'dp_update_equipo_nonce');

        $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        $nombre = isset($_POST['equipo_nombre']) ? sanitize_text_field(wp_unslash($_POST['equipo_nombre'])) : '';
        $slug = isset($_POST['equipo_slug']) ? sanitize_title(wp_unslash($_POST['equipo_slug'])) : '';
        $description = isset($_POST['equipo_description']) ? sanitize_textarea_field(wp_unslash($_POST['equipo_description'])) : '';
        $color = isset($_POST['equipo_color']) ? sanitize_hex_color(wp_unslash($_POST['equipo_color'])) : '';
        $relacion_nombre = isset($_POST['equipo_relacion_nombre']) ? sanitize_text_field(wp_unslash($_POST['equipo_relacion_nombre'])) : '';

        if (!$term_id) {
            add_settings_error($notice_slug, 'dp_update_equipo_missing', 'No se pudo identificar el equipo a actualizar.', 'error');
        } else {
            $args = [];
            if ($nombre !== '') {
                $args['name'] = $nombre;
            }
            if ($slug !== '') {
                $args['slug'] = $slug;
            }
            if ($description !== '') {
                $args['description'] = $description;
            }

            $result = true;
            if (!empty($args)) {
                $result = wp_update_term($term_id, 'equipo-docente', $args);
            }

            if (is_wp_error($result)) {
                add_settings_error($notice_slug, 'dp_update_equipo_error', $result->get_error_message(), 'error');
            } else {
                if ($color) {
                    update_term_meta($term_id, 'equipo_docente_color', $color);
                }
                if ($relacion_nombre !== '') {
                    update_term_meta($term_id, 'equipo_docente_relacion_nombre', $relacion_nombre);
                } else {
                    delete_term_meta($term_id, 'equipo_docente_relacion_nombre');
                }
                add_settings_error($notice_slug, 'dp_update_equipo_success', 'Equipo actualizado correctamente.', 'updated');
            }
        }
    }

    dp_admin_handle_quick_equipo_submission($notice_slug);

    $equipos = get_terms([
        'taxonomy' => 'equipo-docente',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ]);

    $total_equipos = is_wp_error($equipos) ? 0 : count($equipos);
    $total_docentes = wp_count_posts('docente');
    $docentes_publicados = isset($total_docentes->publish) ? (int) $total_docentes->publish : 0;

    $tax_admin_url = admin_url('edit-tags.php?taxonomy=equipo-docente&post_type=docente');
    ?>
    <div class="wrap dp-docentes-admin">
        <h1>Gestión de equipos académicos</h1>
        <p class="description">Administra los equipos, colores y relaciones desde una vista pensada para este plugin.</p>
        <?php settings_errors($notice_slug); ?>

        <div class="dp-grid dp-two-columns">
            <div class="dp-card stat-card">
                <span class="stat-label">Equipos totales</span>
                <strong class="stat-value"><?php echo esc_html($total_equipos); ?></strong>
                <span class="stat-meta">Perfiles publicados: <?php echo esc_html($docentes_publicados); ?></span>
            </div>
            <div class="dp-card">
                <h2>Crear nuevo equipo</h2>
                <form method="post" class="dp-form">
                    <?php wp_nonce_field('dp_quick_add_equipo', 'dp_quick_add_equipo_nonce'); ?>
                    <input type="hidden" name="dp_quick_add_equipo" value="1">
                    <div class="dp-form-row">
                        <label for="dp_equipo_nombre_admin">Nombre <span class="required">*</span></label>
                        <input type="text" id="dp_equipo_nombre_admin" name="dp_equipo_nombre" required>
                    </div>
                    <div class="dp-form-row">
                        <label for="dp_equipo_slug_admin">Slug</label>
                        <input type="text" id="dp_equipo_slug_admin" name="dp_equipo_slug">
                    </div>
                    <div class="dp-form-row">
                        <label for="dp_equipo_color_admin">Color</label>
                        <input type="color" id="dp_equipo_color_admin" name="dp_equipo_color" value="#0d6efd">
                    </div>
                    <div class="dp-form-row">
                        <label for="dp_equipo_relacion_admin">Nombre de la relacion</label>
                        <input type="text" id="dp_equipo_relacion_admin" name="dp_equipo_relacion_nombre" placeholder="Ej.: Comite asesor">
                    </div>
                    <div class="dp-form-row">
                        <label for="dp_equipo_page_admin">Página de posgrado</label>
                        <?php echo dp_equipo_docente_pages_dropdown(0, 'dp_equipo_page_id', 'dp_equipo_page_admin'); ?>
                    </div>
                    <div class="dp-form-row">
                        <label for="dp_equipo_description_admin">Descripción</label>
                        <textarea id="dp_equipo_description_admin" name="dp_equipo_description" rows="3"></textarea>
                    </div>
                    <button class="button button-primary">Crear equipo</button>
                </form>
            </div>
        </div>

        <div class="dp-card dp-bulk-edit">
            <div class="dp-card-header">
                <h2>Actualizacion por lote</h2>
                <span class="description">Selecciona equipos en la lista y define los cambios a aplicar.</span>
            </div>
            <form method="post" id="dp-bulk-equipo-form" class="dp-form dp-bulk-form">
                <?php wp_nonce_field('dp_bulk_update_equipo', 'dp_bulk_update_equipo_nonce'); ?>
                <input type="hidden" name="dp_bulk_update_equipo" value="1">
                <div class="dp-bulk-grid">
                    <div class="dp-bulk-field">
                        <label>
                            <input type="checkbox" name="bulk_update_color" value="1"> Actualizar color
                        </label>
                        <input type="color" name="bulk_color" value="#1d3a72">
                    </div>
                    <div class="dp-bulk-field">
                        <label>
                            <input type="checkbox" name="bulk_update_relation" value="1"> Actualizar relacion
                        </label>
                        <input type="text" name="bulk_relation_name" placeholder="Ej.: Comite asesor">
                        <p class="description">Deja vacio para limpiar el nombre de la relacion.</p>
                    </div>
                    <div class="dp-bulk-field">
                        <label>
                            <input type="checkbox" name="bulk_update_page" value="1"> Actualizar pagina de posgrado
                        </label>
                        <?php echo dp_equipo_docente_pages_dropdown(0, 'bulk_page_id', 'bulk_page_id'); ?>
                        <p class="description">Selecciona "Sin pagina asociada" para limpiar la pagina.</p>
                    </div>
                </div>
                <button class="button button-primary">Aplicar cambios</button>
            </form>
        </div>

        <div class="dp-card">
            <div class="dp-card-header">
                <h2>Equipos registrados</h2>
                <a class="button button-link" href="<?php echo esc_url($tax_admin_url); ?>">Ver vista clásica</a>
            </div>

            <?php if ($equipos && !is_wp_error($equipos)): ?>
                <div class="dp-equipos-list">
                    <?php foreach ($equipos as $equipo): ?>
                        <?php
                        $color = get_term_meta($equipo->term_id, 'equipo_docente_color', true) ?: '#0d6efd';
                        $page_selected = (int) get_term_meta($equipo->term_id, 'equipo_docente_page_id', true);
                        $relacion_nombre = get_term_meta($equipo->term_id, 'equipo_docente_relacion_nombre', true);
                        ?>
                        <form method="post" class="dp-equipos-item">
                            <?php wp_nonce_field('dp_update_equipo', 'dp_update_equipo_nonce'); ?>
                            <?php wp_nonce_field('dp_delete_equipo', 'dp_delete_equipo_nonce'); ?>
                            <input type="hidden" name="dp_update_equipo" value="1">
                            <input type="hidden" name="term_id" value="<?php echo esc_attr($equipo->term_id); ?>">
                            <div class="dp-equipos-header">
                                <div class="dp-equipos-select">
                                    <label for="bulk-equipo-<?php echo esc_attr($equipo->term_id); ?>">Lote</label>
                                    <input type="checkbox" id="bulk-equipo-<?php echo esc_attr($equipo->term_id); ?>" name="bulk_term_ids[]" value="<?php echo esc_attr($equipo->term_id); ?>" form="dp-bulk-equipo-form">
                                </div>
                                <div>
                                    <label for="equipo_nombre_<?php echo esc_attr($equipo->term_id); ?>">Nombre</label>
                                    <input type="text" id="equipo_nombre_<?php echo esc_attr($equipo->term_id); ?>" name="equipo_nombre" value="<?php echo esc_attr($equipo->name); ?>">
                                </div>
                                <div>
                                    <label for="equipo_slug_<?php echo esc_attr($equipo->term_id); ?>">Slug</label>
                                    <input type="text" id="equipo_slug_<?php echo esc_attr($equipo->term_id); ?>" name="equipo_slug" value="<?php echo esc_attr($equipo->slug); ?>">
                                </div>
                                <div>
                                    <label for="equipo_relacion_<?php echo esc_attr($equipo->term_id); ?>">Relacion</label>
                                    <input type="text" id="equipo_relacion_<?php echo esc_attr($equipo->term_id); ?>" name="equipo_relacion_nombre" value="<?php echo esc_attr($relacion_nombre); ?>">
                                </div>
                                <div>
                                    <label for="equipo_color_<?php echo esc_attr($equipo->term_id); ?>">Color</label>
                                    <input type="color" id="equipo_color_<?php echo esc_attr($equipo->term_id); ?>" name="equipo_color" value="<?php echo esc_attr($color); ?>">
                                </div>
                                <div>
                                    <label for="equipo_page_<?php echo esc_attr($equipo->term_id); ?>">Página</label>
                                    <?php echo dp_equipo_docente_pages_dropdown($page_selected, 'equipo_docente_page_id', 'equipo_page_' . $equipo->term_id); ?>
                                </div>
                                <div class="dp-equipos-count">
                                    <span><?php echo esc_html($equipo->count); ?> integrantes</span>
                                </div>
                            </div>
                            <div class="dp-form-row">
                                <label for="equipo_description_<?php echo esc_attr($equipo->term_id); ?>">Descripción</label>
                                <textarea id="equipo_description_<?php echo esc_attr($equipo->term_id); ?>" name="equipo_description" rows="2"><?php echo esc_textarea($equipo->description); ?></textarea>
                            </div>
                            <div class="dp-equipos-actions">
                                <button class="button button-primary button-small">Guardar</button>
                                <button class="button button-secondary button-small" type="submit" name="dp_delete_equipo" value="1" onclick="return confirm('Eliminar este equipo?');">Eliminar</button>
                                <a class="button button-link" href="<?php echo esc_url(get_edit_term_link($equipo)); ?>">Editar completo</a>
                                <a class="button button-link" href="<?php echo esc_url(get_term_link($equipo)); ?>" target="_blank">Ver en el sitio</a>
                            </div>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No hay equipos registrados todavía.</p>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .dp-equipos-list {
            display: grid;
            gap: 16px;
        }
        .dp-equipos-item {
            border: 1px solid #dcdcde;
            border-radius: 12px;
            padding: 16px;
            background: #fff;
        }
        .dp-bulk-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .dp-bulk-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .dp-bulk-field label {
            font-weight: 600;
            display: block;
            margin-bottom: 6px;
        }
        .dp-bulk-field input[type="text"] {
            width: 100%;
        }
        .dp-equipos-select {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 90px;
        }
        .dp-equipos-item label {
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.05em;
            display: block;
            margin-bottom: 4px;
        }
        .dp-equipos-header {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }
        .dp-equipos-count {
            display: flex;
            align-items: flex-end;
            font-weight: 600;
            color: #111827;
        }
        .dp-equipos-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        @media (max-width: 782px) {
            .dp-equipos-header {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php
}

function docentes_documentacion_page() {
    $archivo_docentes = get_post_type_archive_link('docente');
    $equipos_page = home_url('/equipo-docente/');
    $equipos_taxonomy_example = trailingslashit(home_url('/equipo-docente/slug-del-equipo'));
    $single_docente_example = trailingslashit(home_url('/docente/slug-del-docente'));
    ?>
    <div class="wrap docentes-docs">
        <h1>Documentacion del plugin Docentes</h1>
        <p class="doc-intro">
            Esta pagina resume como funciona el plugin y cuales son los pasos recomendados para publicar el directorio
            de docentes. Compartila con quienes administran contenidos para mantener un flujo consistente.
        </p>

        <ul class="doc-nav">
            <li><a href="#resumen">Resumen</a></li>
            <li><a href="#flujo">Carga de docentes</a></li>
            <li><a href="#equipos">Equipos docentes</a></li>
            <li><a href="#bloques">Bloques</a></li>
            <li><a href="#frontend">Paginas publicas</a></li>
            <li><a href="#tips">Tips</a></li>
        </ul>

        <div id="resumen" class="doc-section">
            <h2>1. Que incluye el plugin</h2>
            <ul class="doc-list">
                <li><strong>CPT Docente:</strong> tipo de contenido <code>docente</code> con campos obligatorios (prefijo, nombre, apellido y CV) definidos en <code>includes/meta-docente.php</code>.</li>
                <li><strong>Taxonomia Equipo Docente:</strong> agrupa docentes en ofertas o equipos, permite asignar un color por termino y es visible tanto en el admin como en el frontend.</li>
                <li><strong>Listado del admin mejorado:</strong> en <em>Docentes &gt; Docentes</em> hay filtros por equipo, busqueda, columnas con foto y botones de edicion rapida.</li>
                <li><strong>Plantillas publicas:</strong> los archivos de <code>templates/</code> reemplazan las vistas del archivo de docentes, la ficha individual y la grilla de equipos.</li>
                <li><strong>Bloques reutilizables:</strong> existen bloques de Gutenberg para mostrar listas por equipo y el CV de un docente dentro de cualquier pagina. Los shortcodes fueron retirados a pedido.</li>
            </ul>
        </div>

        <div id="flujo" class="doc-section">
            <h2>2. Flujo sugerido para cargar docentes</h2>
            <ol class="doc-steps">
                <li>Ir a <em>Docentes &gt; Anadir nuevo</em> y completar el titulo (puede ser redundante) y el contenido si hace falta.</li>
                <li>Usar la caja "Informacion del Docente" para ingresar prefijos, nombre, apellido y el CV (editor enriquecido). Nombre, apellido y CV son obligatorios.</li>
                <li>Desde el panel "Imagen destacada" abrir WP Media (boton Establecer imagen destacada) y subir o elegir una foto cuadrada (ideal 800x800). Si no hay foto se mostrara un avatar con iniciales.</li>
                <li>Asignar uno o mas terminos de <em>Equipo Docente</em>. El color se puede ajustar desde la pantalla de taxonomia.</li>
                <li>Publicar o actualizar. Si falta un campo obligatorio se mostrara un aviso y la publicacion no avanzara hasta completarlo.</li>
            </ol>
            <p class="doc-note">
                Para cambios masivos existe el boton <strong>Edicion rapida</strong> dentro del listado principal: permite modificar prefijos, nombre, apellido y equipos sin abrir cada entrada.
            </p>
        </div>

        <div id="equipos" class="doc-section">
            <h2>3. Gestionar equipos docentes</h2>
            <ul class="doc-list">
                <li>Se administran en <em>Docentes &gt; Equipo Docente</em>. Cada termino representa una oferta o cohorte.</li>
                <li>Al crear o editar un termino se puede definir un color mediante un selector. El color se usa en chips, badges y fondos.</li>
                <li>La columna "Color" y la edicion rapida permiten modificarlo sin abandonar la tabla de terminos.</li>
                <li>Los terminos se muestran automaticamente en la pagina especial <code>/equipo-docente</code> (usa <code>templates/archive-equipo-docente.php</code>).</li>
            </ul>
        </div>

        <div id="bloques" class="doc-section">
            <h2>4. Bloques disponibles (shortcodes eliminados)</h2>
            <table class="widefat doc-table">
                <thead>
                    <tr>
                        <th>Bloque</th>
                        <th>Slug</th>
                        <th>Uso y notas</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Docentes por Equipo</strong></td>
                        <td><code>flacso-uruguay/docentes-equipo</code></td>
                        <td>
                            Muestra una grilla con los docentes del equipo indicado. En el inspector se completa el <strong>slug</strong> del termino
                            (por ejemplo <code>maestria-educacion</code>). Ideal para construir paginas de oferta academica sin tocar plantillas.
                        </td>
                    </tr>
                    <tr>
                        <td><strong>CV de Docente</strong></td>
                        <td><code>flacso-uruguay/cv-docente</code></td>
                        <td>
                            Imprime el CV completo de un docente especifico. Requiere el <strong>slug del docente</strong> (ultima parte de la URL o columna "Slug" en la edicion rapida).
                            Es util para insertar perfiles en landing pages o sitios externos sin duplicar contenido.
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="doc-note">
                Los bloques anteriores reemplazan a los antiguos shortcodes; si encuentras referencias a shortcodes viejos basta con insertar el bloque equivalente.
            </p>
        </div>

        <div id="frontend" class="doc-section">
            <h2>5. Paginas publicas generadas</h2>
            <ul class="doc-list">
                <li><strong>Directorio general:</strong>
                    <?php if ($archivo_docentes) : ?>
                        <code><?php echo esc_html($archivo_docentes); ?></code>
                    <?php else : ?>
                        <code>/docentes</code> (activa los enlaces permanentes para que el archivo funcione).
                    <?php endif; ?>
                    Usa <code>templates/archive-docente.php</code>.
                </li>
                <li><strong>Pagina de equipos:</strong> <code><?php echo esc_html($equipos_page); ?></code> (crea o mantiene publicada una pagina con el slug <code>equipo-docente</code> para disparar el template <code>archive-equipo-docente.php</code>).</li>
                <li><strong>Detalle de un equipo:</strong> <code><?php echo esc_html($equipos_taxonomy_example); ?></code>, generado por <code>templates/taxonomy-equipo-docente.php</code>.</li>
                <li><strong>Ficha individual:</strong> <code><?php echo esc_html($single_docente_example); ?></code>, renderizada por <code>templates/single-docente.php</code>.</li>
            </ul>
        </div>

        <div id="tips" class="doc-section">
            <h2>6. Tips y solucion rapida</h2>
            <ul class="doc-list">
                <li>Si un docente no aparece en el listado publico, confirma que este publicado y que tenga al menos un equipo asignado si estas filtrando por taxonomia.</li>
                <li>Para cambiar el orden alfabetico del archivo se usa el campo "Apellido"; si queda vacio se ordena por titulo.</li>
                <li>El color de cada equipo se guarda en su metadata. Para forzar un nuevo color solo edita el termino y guarda nuevamente.</li>
                <li>Los bloques dependen del slug: verifica el valor exacto desde "Enlace permanente" o usando la accion "Editar" dentro del listado.</li>
                <li>Si necesitas personalizar estilos, copia los archivos dentro de <code>templates/</code> al theme activo y ajusta alli tus cambios.</li>
            </ul>
        </div>
    </div>
    <style>
        .docentes-docs .doc-intro {
            max-width: 720px;
            font-size: 14px;
            color: #555;
        }
        .docentes-docs .doc-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 1rem;
            padding: 0;
            list-style: none;
            margin: 1.5rem 0;
        }
        .docentes-docs .doc-nav a {
            text-decoration: none;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            background: #f0f0f1;
            color: #1d2327;
        }
        .docentes-docs .doc-section {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .docentes-docs .doc-section h2 {
            margin-top: 0;
        }
        .docentes-docs .doc-list {
            padding-left: 1.2rem;
        }
        .docentes-docs .doc-list li {
            margin-bottom: 0.5rem;
        }
        .docentes-docs .doc-steps {
            padding-left: 1.2rem;
            margin-bottom: 1rem;
        }
        .docentes-docs .doc-steps li {
            margin-bottom: 0.4rem;
        }
        .docentes-docs .doc-note {
            background: #fef8e7;
            border-left: 4px solid #dba617;
            padding: 0.75rem 1rem;
            border-radius: 4px;
            font-size: 13px;
        }
        .docentes-docs .doc-table th,
        .docentes-docs .doc-table td {
            vertical-align: top;
        }
        .docentes-docs code {
            background: #f6f7f7;
            padding: 0 4px;
            border-radius: 3px;
        }
    </style>
    <?php
}

function docentes_api_page() {
    if (!current_user_can('edit_posts')) {
        wp_die(__('No tienes permisos suficientes para acceder a esta página.'));
    }

    $base_url = esc_url(rest_url('flacso-docentes/v1/'));
    $posgrados_base_url = esc_url(rest_url('flacso-posgrados/v1/'));
    ?>
    <div class="wrap docentes-docs">
        <h1>API REST de Docentes y Posgrados</h1>
        <p class="doc-intro">
            Estas APIs permiten gestionar perfiles, equipos academicos y posgrados desde servicios externos. Requieren
            autenticacion y respetan permisos de usuario.
        </p>

        <div class="doc-section">
            <h2>URLs base</h2>
            <p><code><?php echo esc_html($base_url); ?></code></p>
            <p><code><?php echo esc_html($posgrados_base_url); ?></code></p>
        </div>

        <div class="doc-section">
            <h2>Autenticación</h2>
            <ul class="doc-list">
                <li>Usar <strong>Contraseñas de aplicación</strong> en el perfil del usuario (Usuarios &gt; Tu perfil).</li>
                <li>Enviar Basic Auth: <code>-u usuario:app_password</code> (puedes quitar los espacios de la app password).</li>
                <li>Para leer docentes se requiere <code>edit_posts</code> y para editar docentes <code>edit_others_posts</code>.</li>
                <li>Para leer posgrados se requiere <code>edit_pages</code> y para editar posgrados <code>edit_others_pages</code>.</li>
                <li>Para crear/editar equipos se requiere <code>manage_categories</code>.</li>
            </ul>
        </div>

        <div class="doc-section">
            <h2>Endpoints de docentes</h2>
            <table class="widefat doc-table">
                <thead>
                    <tr>
                        <th>Método</th>
                        <th>Endpoint</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>GET</td>
                        <td><code>/docentes</code></td>
                        <td>Lista perfiles. Soporta <code>per_page</code>, <code>page</code>, <code>search</code>, <code>status</code>, <code>equipo</code> (id o slug), <code>page_id</code>.</td>
                    </tr>
                    <tr>
                        <td>GET</td>
                        <td><code>/docentes/{id}</code></td>
                        <td>Detalle de un perfil.</td>
                    </tr>
                    <tr>
                        <td>POST</td>
                        <td><code>/docentes</code></td>
                        <td>Crea un perfil.</td>
                    </tr>
                    <tr>
                        <td>PUT/PATCH</td>
                        <td><code>/docentes/{id}</code></td>
                        <td>Actualiza un perfil.</td>
                    </tr>
                    <tr>
                        <td>DELETE</td>
                        <td><code>/docentes/{id}</code></td>
                        <td>Elimina un perfil. Usa <code>?force=true</code> para borrar definitivamente.</td>
                    </tr>
                </tbody>
            </table>

            <h3>Campos soportados (docentes)</h3>
            <p>
                <code>title</code>, <code>slug</code>, <code>status</code>, <code>content</code>, <code>prefijo_abrev</code>,
                <code>prefijo_full</code>, <code>nombre</code>, <code>apellido</code>, <code>cv</code>, <code>correos</code>,
                <code>redes</code>, <code>equipos</code> (array de IDs o slugs).
            </p>

            <pre><code>{
  "title": "Mag. Ana Perez",
  "status": "publish",
  "prefijo_abrev": "Mag.",
  "nombre": "Ana",
  "apellido": "Perez",
  "cv": "&lt;p&gt;Resumen curricular...&lt;/p&gt;",
  "correos": [
    { "email": "ana@flacso.edu.uy", "label": "Correo", "principal": true }
  ],
  "redes": [
    { "url": "https://linkedin.com/in/ana", "label": "LinkedIn" }
  ],
  "equipos": [12, "maestria-edutic"]
}</code></pre>
        </div>

        <div class="doc-section">
            <h2>Endpoints de equipos académicos</h2>
            <table class="widefat doc-table">
                <thead>
                    <tr>
                        <th>Método</th>
                        <th>Endpoint</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>GET</td>
                        <td><code>/equipos</code></td>
                        <td>Lista equipos (incluye metadata).</td>
                    </tr>
                    <tr>
                        <td>GET</td>
                        <td><code>/equipos/{id}</code></td>
                        <td>Detalle de un equipo.</td>
                    </tr>
                    <tr>
                        <td>POST</td>
                        <td><code>/equipos</code></td>
                        <td>Crea un equipo.</td>
                    </tr>
                    <tr>
                        <td>PUT/PATCH</td>
                        <td><code>/equipos/{id}</code></td>
                        <td>Actualiza un equipo.</td>
                    </tr>
                    <tr>
                        <td>DELETE</td>
                        <td><code>/equipos/{id}</code></td>
                        <td>Elimina un equipo.</td>
                    </tr>
                </tbody>
            </table>

            <h3>Campos soportados (equipos)</h3>
            <p>
                <code>name</code>, <code>slug</code>, <code>description</code>, <code>color</code>, <code>page_id</code>,
                <code>relation_name</code>, <code>autosync</code>.
            </p>

            <pre><code>{
  "name": "Maestria EDUTIC",
  "slug": "maestria-edutic",
  "relation_name": "Comite asesor",
  "color": "#1d3a72",
  "page_id": 12345,
  "autosync": false
}</code></pre>
        </div>

        <div class="doc-section">
            <h2>Endpoints de posgrados</h2>
            <table class="widefat doc-table">
                <thead>
                    <tr>
                        <th>Método</th>
                        <th>Endpoint</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>GET</td>
                        <td><code>/posgrados</code></td>
                        <td>Lista posgrados. Soporta <code>per_page</code>, <code>page</code>, <code>search</code>, <code>status</code>, <code>tipo</code>, <code>activo</code>, <code>parent_id</code>.</td>
                    </tr>
                    <tr>
                        <td>GET</td>
                        <td><code>/posgrados/{id}</code></td>
                        <td>Detalle de un posgrado.</td>
                    </tr>
                    <tr>
                        <td>POST</td>
                        <td><code>/posgrados</code></td>
                        <td>Crea un posgrado. Requiere <code>title</code> y <code>parent_id</code> (categoria).</td>
                    </tr>
                    <tr>
                        <td>PUT/PATCH</td>
                        <td><code>/posgrados/{id}</code></td>
                        <td>Actualiza un posgrado.</td>
                    </tr>
                    <tr>
                        <td>DELETE</td>
                        <td><code>/posgrados/{id}</code></td>
                        <td>Elimina un posgrado. Usa <code>?force=true</code> para borrar definitivamente.</td>
                    </tr>
                </tbody>
            </table>

            <h3>Campos soportados (posgrados)</h3>
            <p>
                <code>title</code>, <code>slug</code>, <code>status</code>, <code>content</code>, <code>excerpt</code>,
                <code>parent_id</code>, <code>tipo_posgrado</code>, <code>fecha_inicio</code>, <code>proximo_inicio</code>,
                <code>calendario_anio</code>, <code>calendario_link</code>, <code>malla_curricular_link</code>,
                <code>imagen_promocional</code>, <code>posgrado_activo</code>, <code>abreviacion</code>, <code>duracion</code>, <code>link</code>.
            </p>

            <pre><code>{
  "title": "Maestria en Educacion Digital",
  "status": "publish",
  "parent_id": 12345,
  "tipo_posgrado": "Maestria",
  "posgrado_activo": true,
  "abreviacion": "EDUTIC",
  "proximo_inicio": "2025-03-01"
}</code></pre>
        </div>

        <div class="doc-section">
            <h2>Ejemplos rápidos</h2>
            <pre><code>curl -u usuario:app_password "<?php echo esc_html($base_url); ?>docentes?per_page=5"</code></pre>
            <pre><code>curl -u usuario:app_password "<?php echo esc_html($posgrados_base_url); ?>posgrados?per_page=5"</code></pre>
            <pre><code>curl -u usuario:app_password -X PATCH \
  -H "Content-Type: application/json" \
  -d "{\"prefijo_abrev\":\"Dra.\",\"apellido\":\"Gomez\"}" \
  "<?php echo esc_html($base_url); ?>docentes/123"</code></pre>
        </div>
    </div>
    <style>
        .docentes-docs .doc-intro {
            max-width: 720px;
            font-size: 14px;
            color: var(--global-palette5, #555);
        }
        .docentes-docs .doc-section {
            background: var(--global-palette9, #fff);
            border: 1px solid var(--global-palette6, #dcdcde);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .docentes-docs .doc-list {
            padding-left: 1.2rem;
        }
        .docentes-docs pre {
            background: var(--global-palette8, #f6f7f7);
            padding: 0.75rem 1rem;
            border-radius: 6px;
            overflow: auto;
        }
        .docentes-docs code {
            background: var(--global-palette8, #f6f7f7);
            padding: 0 4px;
            border-radius: 3px;
        }
    </style>
    <?php
}



