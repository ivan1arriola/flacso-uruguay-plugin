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
        $templates = self::get_price_templates();
        $default_price_template_ids = self::get_default_price_template_ids();

        $saved_price_template_ids = get_option('flacso_seminario_price_template_ids', array());
        if (!is_array($saved_price_template_ids) || empty($saved_price_template_ids)) {
            $saved_price_template_ids = $default_price_template_ids;
        }

        $notice_html = '';

        if (isset($_POST['flacso_seminario_config_nonce']) && wp_verify_nonce($_POST['flacso_seminario_config_nonce'], 'flacso_seminario_config')) {
            $webhook_url = isset($_POST['webhook_url']) ? esc_url_raw(wp_unslash($_POST['webhook_url'])) : '';
            update_option('flacso_seminario_webhook_url', $webhook_url);

            $incoming_template_ids = isset($_POST['price_template_ids']) && is_array($_POST['price_template_ids'])
                ? wp_unslash($_POST['price_template_ids'])
                : array();

            $price_template_ids_to_save = array();
            foreach ($templates as $template_key => $template_data) {
                $raw_ids = isset($incoming_template_ids[$template_key]) ? $incoming_template_ids[$template_key] : '';
                $price_template_ids_to_save[$template_key] = self::parse_post_ids($raw_ids);
            }

            update_option('flacso_seminario_price_template_ids', $price_template_ids_to_save);
            $saved_price_template_ids = $price_template_ids_to_save;

            $notice_html = '<div class="notice notice-success is-dismissible"><p>Configuración guardada correctamente.</p></div>';

            if (isset($_POST['apply_price_templates'])) {
                $apply_result = self::apply_price_templates_to_ids($price_template_ids_to_save, $templates);
                $extra_lines = array();
                $extra_lines[] = 'Plantillas aplicadas en ' . (int) $apply_result['updated_posts'] . ' seminario(s).';

                if (!empty($apply_result['duplicated_ids'])) {
                    $extra_lines[] = 'IDs repetidos (se aplicó solo la primera coincidencia): ' . implode(', ', $apply_result['duplicated_ids']) . '.';
                }

                if (!empty($apply_result['invalid_ids'])) {
                    $extra_lines[] = 'IDs no válidos o que no pertenecen al CPT seminario: ' . implode(', ', $apply_result['invalid_ids']) . '.';
                }

                $notice_html = '<div class="notice notice-success is-dismissible"><p>' . implode('<br>', array_map('esc_html', $extra_lines)) . '</p></div>';
            }
        }

        $webhook_url = get_option('flacso_seminario_webhook_url', '');

        echo '<div class="wrap">';
        echo '<h1>Configuración de Seminarios</h1>';
        echo $notice_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<form method="post" action="">';
        wp_nonce_field('flacso_seminario_config', 'flacso_seminario_config_nonce');

        echo '<h2>Webhook</h2>';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row"><label for="webhook_url">URL del Webhook (Preinscripciones)</label></th>';
        echo '<td>';
        echo '<input type="url" id="webhook_url" name="webhook_url" value="' . esc_attr($webhook_url) . '" class="regular-text" placeholder="https://ejemplo.com/webhook" />';
        echo '<p class="description">Ingresa la URL donde se enviarán los datos de las preinscripciones. Si está vacío, no se enviará a ningún webhook externo.</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        echo '<h2>Plantillas de precios por ID</h2>';
        echo '<p class="description">Define los IDs de seminarios para cada plantilla. Usa IDs separados por coma, espacio o salto de línea.</p>';
        echo '<table class="widefat striped" style="max-width:1200px;">';
        echo '<thead>';
        echo '<tr>';
        echo '<th style="width:220px;">Plantilla</th>';
        echo '<th style="width:240px;">Montos</th>';
        echo '<th>IDs de seminarios</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($templates as $template_key => $template_data) {
            $ids_value = isset($saved_price_template_ids[$template_key]) && is_array($saved_price_template_ids[$template_key])
                ? implode(', ', $saved_price_template_ids[$template_key])
                : '';

            $label = isset($template_data['label']) ? $template_data['label'] : $template_key;
            $montos = '$' . number_format((float) $template_data['valor_uyu'], 0, ',', '.') .
                ' / $' . number_format((float) $template_data['valor_uyu_15_descuento'], 0, ',', '.') .
                ' (UYU) - U$S ' . number_format((float) $template_data['valor_usd'], 0, ',', '.') .
                ' / U$S ' . number_format((float) $template_data['valor_usd_15_descuento'], 0, ',', '.') .
                ' (USD)';

            echo '<tr>';
            echo '<th scope="row">' . esc_html($label) . '</th>';
            echo '<td>' . esc_html($montos) . '</td>';
            echo '<td>';
            echo '<textarea name="price_template_ids[' . esc_attr($template_key) . ']" rows="3" class="large-text code" placeholder="23828, 23900, 23901">' . esc_textarea($ids_value) . '</textarea>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        submit_button('Guardar configuración', 'primary', 'save_config');
        submit_button('Guardar y aplicar plantillas por ID', 'secondary', 'apply_price_templates', false);

        echo '</form>';
        echo '</div>';
    }

    private static function get_price_templates()
    {
        return array(
            'completo_4_creditos' => array(
                'label' => 'Seminario completo (4 créditos)',
                'valor_uyu' => 9000,
                'valor_uyu_15_descuento' => 7650,
                'valor_usd' => 260,
                'valor_usd_15_descuento' => 220,
            ),
            'intermedio_3_creditos' => array(
                'label' => 'Seminario intermedio (3 créditos)',
                'valor_uyu' => 8000,
                'valor_uyu_15_descuento' => 6800,
                'valor_usd' => 200,
                'valor_usd_15_descuento' => 170,
            ),
            'breve_2_a_2_5_creditos' => array(
                'label' => 'Seminario breve (2 a 2.5 créditos)',
                'valor_uyu' => 5600,
                'valor_uyu_15_descuento' => 4750,
                'valor_usd' => 120,
                'valor_usd_15_descuento' => 100,
            ),
            'micro_1_5_creditos' => array(
                'label' => 'Seminario micro (1.5 créditos)',
                'valor_uyu' => 3400,
                'valor_uyu_15_descuento' => 2900,
                'valor_usd' => 85,
                'valor_usd_15_descuento' => 75,
            ),
        );
    }

    private static function get_default_price_template_ids()
    {
        return array(
            'completo_4_creditos' => array(23828, 23900, 23901, 23905, 23906, 23912, 23898, 23903, 25716, 23907, 23909, 23916, 25717, 23925, 25892, 25893, 25894, 25895),
            'intermedio_3_creditos' => array(23919, 23897, 23899, 23908, 23910, 23911, 23913, 23914, 23915, 23917, 23918, 23920, 23921, 23922, 23923, 23924, 23926, 23927),
            'breve_2_a_2_5_creditos' => array(23902, 25624, 25625, 24299, 24432),
            'micro_1_5_creditos' => array(25623, 23904),
        );
    }

    private static function parse_post_ids($raw_ids)
    {
        if (!is_string($raw_ids)) {
            return array();
        }

        $parts = preg_split('/[\s,;]+/', $raw_ids);
        if (!is_array($parts)) {
            return array();
        }

        $ids = array();
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $id = absint($part);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private static function apply_price_templates_to_ids($price_template_ids, $templates)
    {
        $updated_posts = 0;
        $invalid_ids = array();
        $duplicated_ids = array();
        $used_ids = array();

        foreach ($price_template_ids as $template_key => $ids) {
            if (!isset($templates[$template_key]) || !is_array($ids)) {
                continue;
            }

            $template = $templates[$template_key];

            foreach ($ids as $id) {
                $post_id = absint($id);
                if ($post_id <= 0) {
                    continue;
                }

                if (isset($used_ids[$post_id])) {
                    $duplicated_ids[] = $post_id;
                    continue;
                }

                $post = get_post($post_id);
                if (!$post || $post->post_type !== 'seminario') {
                    $invalid_ids[] = $post_id;
                    continue;
                }

                update_post_meta($post_id, '_seminario_valor_uyu', $template['valor_uyu']);
                update_post_meta($post_id, '_seminario_valor_uyu_15_descuento', $template['valor_uyu_15_descuento']);
                update_post_meta($post_id, '_seminario_valor_usd', $template['valor_usd']);
                update_post_meta($post_id, '_seminario_valor_usd_15_descuento', $template['valor_usd_15_descuento']);

                $used_ids[$post_id] = true;
                $updated_posts++;
            }
        }

        return array(
            'updated_posts' => $updated_posts,
            'invalid_ids' => array_values(array_unique($invalid_ids)),
            'duplicated_ids' => array_values(array_unique($duplicated_ids)),
        );
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
            'valor_uyu' => 'Valor general (UYU)',
            'valor_uyu_15_descuento' => 'Valor Comunidad FLACSO 15% (UYU)',
            'valor_usd' => 'Valor general (USD)',
            'valor_usd_15_descuento' => 'Valor Comunidad FLACSO 15% (USD)',
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
