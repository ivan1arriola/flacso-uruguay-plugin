<?php
if (!defined('ABSPATH')) {
    exit;
}

class Seminario_Admin
{
    public static function register_menu()
    {
        add_submenu_page(
            'edit.php?post_type=seminario',
            'Configuración',
            'Configuración',
            'manage_options',
            'seminario-config',
            array(__CLASS__, 'render_config_page')
        );
        
        add_submenu_page(
            'edit.php?post_type=seminario',
            'API Seminario',
            'API Seminario',
            'edit_posts',
            'seminario-api',
            array(__CLASS__, 'render_api_page')
        );
    }

    public static function render_config_page()
    {
        // Guardar configuración
        if (isset($_POST['flacso_seminario_config_nonce']) && wp_verify_nonce($_POST['flacso_seminario_config_nonce'], 'flacso_seminario_config')) {
            $webhook_url = isset($_POST['webhook_url']) ? esc_url_raw($_POST['webhook_url']) : '';
            update_option('flacso_seminario_webhook_url', $webhook_url);
            echo '<div class="notice notice-success is-dismissible"><p>Configuración guardada correctamente.</p></div>';
        }
        
        $webhook_url = get_option('flacso_seminario_webhook_url', '');
        
        echo '<div class="wrap">';
        echo '<h1>Configuración de Seminarios</h1>';
        echo '<form method="post" action="">';
        wp_nonce_field('flacso_seminario_config', 'flacso_seminario_config_nonce');
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row"><label for="webhook_url">URL del Webhook (Preinscripciones)</label></th>';
        echo '<td>';
        echo '<input type="url" id="webhook_url" name="webhook_url" value="' . esc_attr($webhook_url) . '" class="regular-text" placeholder="https://ejemplo.com/webhook" />';
        echo '<p class="description">Ingresa la URL donde se enviarán los datos de las preinscripciones. Si está vacío, no se enviará a ningún webhook externo.</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        submit_button('Guardar Configuración');
        echo '</form>';
        echo '</div>';
    }

    public static function render_api_page()
    {
        $markdown = '';
        $path = FLACSO_SEMINARIO_PATH . 'API.md';
        if (file_exists($path)) {
            $markdown = file_get_contents($path);
        }

        echo '<div class="wrap">';
        echo '<h1>API Seminarios (flacso/v1)</h1>';
        echo '<p>Visualización de endpoints y esquema de datos. Consulte <code>/wp-json/flacso/v1</code>.</p>';
        if ($markdown !== '') {
            echo '<pre class="seminario-api-docs">' . esc_html($markdown) . '</pre>';
        } else {
            echo '<p>No se encontró el archivo de documentación API.md.</p>';
        }
        echo '</div>';
    }

    public static function add_list_columns($columns)
    {
        $new = array();
        foreach ($columns as $key => $label) {
            if ($key === 'cb') {
                $new[$key] = $label;
                $new['thumbnail'] = 'Imagen';
                continue;
            }
            $new[$key] = $label;
            if ($key === 'title') {
                $new['periodo_inicio'] = 'Inicio';
                $new['periodo_fin'] = 'Fin';
            }
        }
        return $new;
    }

    public static function make_list_columns_sortable($sortable)
    {
        $sortable['periodo_inicio'] = '_seminario_periodo_inicio';
        $sortable['periodo_fin'] = '_seminario_periodo_fin';
        return $sortable;
    }

    public static function render_list_columns($column, $post_id)
    {
        if ($column === 'thumbnail') {
            $thumb = get_the_post_thumbnail($post_id, 'thumbnail');
            echo $thumb ? $thumb : '—';
            return;
        }
        if ($column === 'periodo_inicio' || $column === 'periodo_fin') {
            $meta = get_post_meta($post_id, '_seminario_' . $column, true);
            echo esc_html($meta ? $meta : '—');
        }
    }

    public static function add_meta_boxes()
    {
        add_meta_box(
            'seminario_detalles',
            'Detalles del seminario',
            array(__CLASS__, 'render_meta_box'),
            'seminario',
            'normal',
            'default'
        );
    }

    public static function enqueue_admin_assets($hook)
    {
        if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'seminario') {
            return;
        }

        wp_enqueue_style(
            'flacso-seminario-admin',
            plugins_url('modules/seminarios/assets/css/admin.css', FLACSO_URUGUAY_FILE),
            array(),
            FLACSO_SEMINARIO_VERSION
        );

        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script(
            'flacso-seminario-admin',
            plugins_url('modules/seminarios/assets/js/admin.js', FLACSO_URUGUAY_FILE),
            array('jquery', 'jquery-ui-sortable'),
            FLACSO_SEMINARIO_VERSION,
            true
        );

        wp_localize_script(
            'flacso-seminario-admin',
            'SEMINARIO_ADMIN',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'searchNonce' => wp_create_nonce('flacso_seminario_docente_search'),
            )
        );
    }

    public static function render_meta_box($post)
    {
        $fields = array(
            'nombre' => 'Nombre',
            'periodo_inicio' => 'Periodo inicio',
            'periodo_fin' => 'Periodo fin',
            'creditos' => 'Creditos',
            'carga_horaria' => 'Carga horaria',
            'acredita_maestria' => 'Acredita maestria',
            'acredita_doctorado' => 'Acredita doctorado',
            'forma_aprobacion' => 'Forma de aprobacion',
            'modalidad' => 'Modalidad',
            'objetivo_general' => 'Objetivo general',
            'presentacion_seminario' => 'Presentacion del seminario (max 250 palabras)',
        );

        $encuentros = get_post_meta($post->ID, '_seminario_encuentros_sincronicos', true);
        $encuentros = is_array($encuentros) ? $encuentros : array();

        $objetivos = get_post_meta($post->ID, '_seminario_objetivos_especificos', true);
        $objetivos = is_array($objetivos) ? $objetivos : array();

        $unidades = get_post_meta($post->ID, '_seminario_unidades_academicas', true);
        $unidades = is_array($unidades) ? $unidades : array();

        $docentes_ids = get_post_meta($post->ID, '_seminario_docentes', true);
        $docentes_ids = is_array($docentes_ids) ? $docentes_ids : array();

        $docentes_posts = array();
        if (!empty($docentes_ids)) {
            $docentes_posts = get_posts(array(
                'post_type' => 'docente',
                'post__in' => $docentes_ids,
                'posts_per_page' => -1,
                'orderby' => 'post__in',
            ));
        }

        $template = dirname(__DIR__) . '/templates/admin-metabox.php';
        if (file_exists($template)) {
            include $template;
        } else {
            echo '<p style="color: red;">Error: No se encuentra el template admin-metabox.php en: ' . esc_html($template) . '</p>';
        }
    }

    public static function save_meta($post_id)
    {
        if (!isset($_POST['flacso_seminario_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['flacso_seminario_nonce'], 'flacso_seminario_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $meta_input = array();
        foreach (Seminario_Helpers::meta_keys() as $key) {
            $meta_key = '_seminario_' . $key;

            if ($key === 'acredita_maestria' || $key === 'acredita_doctorado') {
                $meta_input[$key] = isset($_POST[$meta_key]);
                continue;
            }

            if (isset($_POST[$meta_key])) {
                $meta_input[$key] = $_POST[$meta_key];
            }
        }

        if (isset($_POST['_seminario_encuentros_sincronicos'])) {
            $meta_input['encuentros_sincronicos'] = $_POST['_seminario_encuentros_sincronicos'];
        }

        if (isset($_POST['_seminario_objetivos_especificos'])) {
            $meta_input['objetivos_especificos'] = $_POST['_seminario_objetivos_especificos'];
        }

        if (isset($_POST['_seminario_unidades_academicas'])) {
            $meta_input['unidades_academicas'] = $_POST['_seminario_unidades_academicas'];
        }

        if (isset($_POST['_seminario_docentes'])) {
            $meta_input['docentes'] = $_POST['_seminario_docentes'];
        } else {
            delete_post_meta($post_id, '_seminario_docentes');
        }

        Seminario_Meta::update_from_request($post_id, $meta_input);
    }

    public static function search_docentes()
    {
        check_ajax_referer('flacso_seminario_docente_search', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array(), 403);
        }

        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
        if ($term === '') {
            wp_send_json_success(array());
        }

        $query = new WP_Query(array(
            'post_type' => 'docente',
            'post_status' => 'publish',
            's' => $term,
            'posts_per_page' => 10,
        ));

        $items = array();
        foreach ($query->posts as $post) {
            $items[] = array(
                'id' => $post->ID,
                'title' => get_the_title($post),
            );
        }

        wp_send_json_success($items);
    }

    public static function handle_sortable_columns($query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $orderby = $query->get('orderby');

        if ($orderby === '_seminario_periodo_inicio') {
            $query->set('meta_key', '_seminario_periodo_inicio');
            $query->set('orderby', 'meta_value');
        } elseif ($orderby === '_seminario_periodo_fin') {
            $query->set('meta_key', '_seminario_periodo_fin');
            $query->set('orderby', 'meta_value');
        }
    }
}
