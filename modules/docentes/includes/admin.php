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

    function extra_tablenav($which) {}

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

    add_submenu_page(
        'docentes_panel',
        'Migracion de shortcodes',
        'Migracion',
        'manage_options',
        'docentes_migracion',
        'docentes_migracion_page'
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
            add_settings_error($notice_slug, 'dp_quick_add_docente_missing', 'Ingres??? al menos nombre y apellido para crear el docente.', 'error');
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
                                        <div class="description"><a href="<?php echo esc_url(get_edit_post_link($docente->ID)); ?>">Editar</a> ?????? <a href="<?php echo esc_url(get_permalink($docente->ID)); ?>" target="_blank">Ver</a></div>
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
    
    wp_send_json_success('Docente actualizado correctamente');
});


if (!function_exists('docentes_migracion_page')) {
function docentes_migracion_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('No tienes permisos suficientes para acceder a esta pagina.'));
    }

    $result = null;
    if (isset($_POST['dp_migrate_docentes_shortcodes'])) {
        check_admin_referer('dp_migrate_docentes_shortcodes_action', 'dp_migrate_docentes_shortcodes_nonce');
        $result = dp_migrate_docentes_shortcodes_to_block();
    }
    ?>
    <div class="wrap">
        <h1>Migracion: shortcode a bloque Docentes Grupo</h1>
        <p>
            Esta herramienta reemplaza las apariciones de <code>[dp_docentes_equipo ...]</code>
            por el bloque <code>flacso/docentes-grupo</code> con <code>docenteIds</code>
            respetando la logica del equipo docente (slug/term/page actual) y orden por apellido.
        </p>
        <p>
            Ejemplo de reemplazo (se aplica a todos los <code>dp_docentes_equipo</code>):
            <code>&lt;!-- wp:shortcode --&gt; [dp_docentes_equipo slug="maestria-en-genero"] &lt;!-- /wp:shortcode --&gt;</code>
            por:
            <code>&lt;!-- wp:flacso/docentes-grupo {"docenteIds":[...]} /--&gt;</code>
        </p>

        <form method="post">
            <?php wp_nonce_field('dp_migrate_docentes_shortcodes_action', 'dp_migrate_docentes_shortcodes_nonce'); ?>
            <p>
                <button type="submit" name="dp_migrate_docentes_shortcodes" value="1" class="button button-primary">
                    Ejecutar transicion
                </button>
            </p>
        </form>

        <?php if (is_array($result)) : ?>
            <div class="notice notice-success is-dismissible" style="margin:12px 0;">
                <p>
                    Transicion finalizada: 
                    <?php echo (int) $result['replaced_shortcodes']; ?> shortcode(s) reemplazado(s) en
                    <?php echo (int) $result['updated_posts']; ?> post(s).
                </p>
            </div>
            <hr>
            <h2>Resultado</h2>
            <ul>
                <li><strong>Posts escaneados:</strong> <?php echo (int) $result['scanned_posts']; ?></li>
                <li><strong>Posts actualizados:</strong> <?php echo (int) $result['updated_posts']; ?></li>
                <li><strong>Shortcodes reemplazados:</strong> <?php echo (int) $result['replaced_shortcodes']; ?></li>
                <li><strong>Shortcodes omitidos:</strong> <?php echo (int) $result['skipped_shortcodes']; ?></li>
            </ul>

            <?php if (!empty($result['issues'])) : ?>
                <h3>Observaciones</h3>
                <div style="max-height:320px;overflow:auto;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:10px;">
                    <ul style="margin:0 0 0 18px;">
                        <?php foreach ($result['issues'] as $issue) : ?>
                            <li><?php echo esc_html($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
}

if (!function_exists('dp_migrate_docentes_shortcodes_to_block')) {
function dp_migrate_docentes_shortcodes_to_block() {
    global $wpdb;

    $statuses = ['publish', 'private', 'draft', 'pending', 'future'];
    $in_status = "'" . implode("','", array_map('esc_sql', $statuses)) . "'";
    $table = $wpdb->posts;
    $ids = $wpdb->get_col(
        "SELECT ID FROM {$table} WHERE post_status IN ({$in_status}) AND post_content LIKE '%dp_docentes_equipo%'"
    );

    $result = [
        'scanned_posts' => 0,
        'updated_posts' => 0,
        'replaced_shortcodes' => 0,
        'skipped_shortcodes' => 0,
        'issues' => [],
    ];

    if (empty($ids)) {
        return $result;
    }

    $result['scanned_posts'] = count($ids);

    foreach ($ids as $post_id) {
        $post_id = (int) $post_id;
        $post = get_post($post_id);
        if (!$post || !is_string($post->post_content) || $post->post_content === '') {
            continue;
        }

        $content = $post->post_content;
        $original = $content;
        $replaced_this_post = 0;
        $skipped_this_post = 0;

        // 1) Primero, reemplazar shortcodes dentro de bloques wp:shortcode.
        $content = preg_replace_callback(
            '/<!--\s+wp:shortcode\s+-->\s*(\[dp_docentes_equipo[^\]]*\])\s*<!--\s+\/wp:shortcode\s+-->/i',
            function ($m) use ($post_id, &$replaced_this_post, &$skipped_this_post, &$result) {
                $shortcode = isset($m[1]) ? $m[1] : '';
                $replacement = dp_docentes_shortcode_to_grupo_block($shortcode, $post_id, $result['issues']);
                if ($replacement === '') {
                    $skipped_this_post++;
                    return $m[0];
                }
                $replaced_this_post++;
                return $replacement;
            },
            $content
        );

        // 2) Luego, reemplazar shortcodes sueltos.
        $content = preg_replace_callback(
            '/\[dp_docentes_equipo[^\]]*\]/i',
            function ($m) use ($post_id, &$replaced_this_post, &$skipped_this_post, &$result) {
                $shortcode = isset($m[0]) ? $m[0] : '';
                $replacement = dp_docentes_shortcode_to_grupo_block($shortcode, $post_id, $result['issues']);
                if ($replacement === '') {
                    $skipped_this_post++;
                    return $m[0];
                }
                $replaced_this_post++;
                return $replacement;
            },
            $content
        );

        if ($content !== $original) {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => wp_slash($content),
            ]);
            $result['updated_posts']++;
        }

        $result['replaced_shortcodes'] += $replaced_this_post;
        $result['skipped_shortcodes'] += $skipped_this_post;
    }

    return $result;
}
}

if (!function_exists('dp_docentes_shortcode_to_grupo_block')) {
function dp_docentes_shortcode_to_grupo_block($shortcode, $context_post_id, &$issues = []) {
    $shortcode = trim((string) $shortcode);
    if ($shortcode === '') {
        return '';
    }

    if (!preg_match('/^\[dp_docentes_equipo([^\]]*)\]$/i', $shortcode, $m)) {
        $issues[] = "Post {$context_post_id}: formato no reconocido -> {$shortcode}";
        return '';
    }

    $raw_atts = isset($m[1]) ? trim((string) $m[1]) : '';
    $atts = shortcode_parse_atts($raw_atts);
    if (!is_array($atts)) {
        $atts = [];
    }

    $slug = isset($atts['slug']) ? sanitize_title((string) $atts['slug']) : '';
    $term_id = isset($atts['termId']) ? absint($atts['termId']) : 0;
    if (!$term_id && isset($atts['termid'])) {
        $term_id = absint($atts['termid']);
    }
    $use_current_page = false;
    if (isset($atts['useCurrentPage'])) {
        $use_current_page = filter_var($atts['useCurrentPage'], FILTER_VALIDATE_BOOLEAN);
    } elseif (isset($atts['usecurrentpage'])) {
        $use_current_page = filter_var($atts['usecurrentpage'], FILTER_VALIDATE_BOOLEAN);
    }
    $page_id = isset($atts['pageId']) ? absint($atts['pageId']) : 0;
    if (!$page_id && isset($atts['pageid'])) {
        $page_id = absint($atts['pageid']);
    }

    $term_ids = [];

    if ($term_id > 0) {
        $term_obj = get_term($term_id, 'equipo-docente');
        if ($term_obj && !is_wp_error($term_obj)) {
            $term_ids[] = (int) $term_obj->term_id;
        }
    }

    if (empty($term_ids) && $slug !== '') {
        $term_obj = get_term_by('slug', $slug, 'equipo-docente');
        if ($term_obj && !is_wp_error($term_obj)) {
            $term_ids[] = (int) $term_obj->term_id;
        } else {
            $issues[] = "Post {$context_post_id}: equipo no encontrado para slug '{$slug}'.";
            return '';
        }
    }

    if (empty($term_ids) && $use_current_page) {
        $effective_page_id = $page_id ?: (int) $context_post_id;
        if ($effective_page_id > 0 && function_exists('dp_get_equipo_term_ids_by_page')) {
            $term_ids = dp_get_equipo_term_ids_by_page($effective_page_id);
        }
    }

    if (empty($term_ids)) {
        $issues[] = "Post {$context_post_id}: no se pudo resolver equipo para shortcode '{$shortcode}'.";
        return '';
    }

    $term_ids = array_values(array_unique(array_filter(array_map('intval', $term_ids))));
    $docente_ids = [];

    foreach ($term_ids as $tid) {
        $q = new WP_Query([
            'post_type'      => 'docente',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'apellido',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'tax_query'      => [[
                'taxonomy' => 'equipo-docente',
                'field'    => 'term_id',
                'terms'    => $tid,
            ]],
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        if (!empty($q->posts)) {
            $docente_ids = array_merge($docente_ids, array_map('intval', $q->posts));
        }
        wp_reset_postdata();
    }

    $docente_ids = array_values(array_unique(array_filter($docente_ids)));
    if (empty($docente_ids)) {
        $issues[] = "Post {$context_post_id}: equipo resuelto pero sin docentes ({$shortcode}).";
        return '';
    }

    $attrs = wp_json_encode(['docenteIds' => $docente_ids]);
    return '<!-- wp:flacso/docentes-grupo ' . $attrs . ' /-->';
}
}



