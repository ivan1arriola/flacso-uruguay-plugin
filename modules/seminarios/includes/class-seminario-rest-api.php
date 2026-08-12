<?php
if (!defined('ABSPATH')) {
    exit;
}

class Seminario_REST_API
{
    public static function register_routes()
    {


        register_rest_route('flacso/v1', '/seminarios', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_collection'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('flacso/v1', '/seminarios', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'create_item'),
            'permission_callback' => array('Seminario_Helpers', 'permissions_write'),
        ));

        register_rest_route('flacso/v1', '/seminarios/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_item'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('flacso/v1', '/seminarios/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => array(__CLASS__, 'update_item'),
            'permission_callback' => array('Seminario_Helpers', 'permissions_write'),
        ));

        register_rest_route('flacso/v1', '/seminarios/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array(__CLASS__, 'delete_item'),
            'permission_callback' => array('Seminario_Helpers', 'permissions_write'),
        ));

        register_rest_route('flacso/v1', '/consulta-seminario', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'submit_consulta'),
            'permission_callback' => '__return_true',
        ));
    }

    public static function get_posgrados(WP_REST_Request $request)
    {
        if (!post_type_exists('oferta-academica')) {
            return array();
        }

        $statuses = current_user_can('manage_options')
            ? array('publish', 'private')
            : array('publish');

        $query = new WP_Query(array(
            'post_type'      => 'oferta-academica',
            'post_status'    => $statuses,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $items = array();
        foreach ($query->posts as $post) {
            $items[] = array(
                'id'    => $post->ID,
                'title' => get_the_title($post),
                'slug'  => $post->post_name,
                'url'   => get_permalink($post),
            );
        }

        return $items;
    }

    private static function resolve_oferta_id($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            $oferta_id = absint($value);
            $post = get_post($oferta_id);
            if ($post && $post->post_type === 'oferta-academica') {
                return $oferta_id;
            }
            return 0;
        }

        $slug = sanitize_title((string) $value);
        if ($slug === '') {
            return 0;
        }

        $post = get_page_by_path($slug, OBJECT, 'oferta-academica');
        if (!$post) {
            return 0;
        }

        return (int) $post->ID;
    }

    private static function get_oferta_seminarios_ids(int $oferta_id): array
    {
        if ($oferta_id <= 0) {
            return array();
        }

        $seminarios_ids = get_post_meta($oferta_id, '_oferta_seminarios_ids', true);
        if (!is_array($seminarios_ids) || empty($seminarios_ids)) {
            return array();
        }

        $seminarios_ids = array_values(array_unique(array_map('intval', $seminarios_ids)));
        $resolved_ids = array();
        foreach (array_values(array_filter($seminarios_ids)) as $seminario_id) {
            $parent = Seminario_Helpers::get_integrated_parent($seminario_id);
            $resolved_ids[] = $parent ? (int) $parent->ID : (int) $seminario_id;
        }
        return array_values(array_unique($resolved_ids));
    }

    public static function get_collection(WP_REST_Request $request)
    {
        $per_page = (int) $request->get_param('per_page');
        $page = (int) $request->get_param('page');
        $posgrado = $request->get_param('oferta_academica'); // Nuevo nombre preferido
        if ($posgrado === null) {
            $posgrado = $request->get_param('posgrado'); // Fallback
        }
        $legacy_programa = $request->get_param('programa');
        $posgrado_value = $posgrado !== null && $posgrado !== '' ? $posgrado : $legacy_programa;

        $args = array(
            'post_type' => 'seminario',
            'post_status' => 'any',
            'posts_per_page' => $per_page > 0 ? $per_page : 10,
            'paged' => $page > 0 ? $page : 1,
        );

        $include_components = (bool) $request->get_param('include_components') && current_user_can('edit_posts');
        if (!$include_components) {
            $component_ids = Seminario_Helpers::get_all_component_ids();
            if (!empty($component_ids)) {
                $args['post__not_in'] = $component_ids;
            }
        }

        $oferta_id = self::resolve_oferta_id($posgrado_value);
        if ($oferta_id > 0) {
            $seminarios_ids = self::get_oferta_seminarios_ids($oferta_id);
            $args['post__in'] = !empty($seminarios_ids) ? $seminarios_ids : array(0);
        }

        $query = new WP_Query($args);

        $items = array();
        foreach ($query->posts as $post) {
            $items[] = Seminario_Helpers::build_response($post);
        }

        $response = new WP_REST_Response($items);
        $response->header('X-WP-Total', (int) $query->found_posts);
        $response->header('X-WP-TotalPages', (int) $query->max_num_pages);
        return $response;
    }

    public static function get_item(WP_REST_Request $request)
    {
        $post = get_post((int) $request['id']);
        if (!$post || $post->post_type !== 'seminario') {
            return new WP_Error('seminario_not_found', 'Seminario no encontrado', array('status' => 404));
        }

        return Seminario_Helpers::build_response($post);
    }

    public static function create_item(WP_REST_Request $request)
    {
        $data = array(
            'post_type' => 'seminario',
            'post_title' => (string) $request->get_param('title'),
            'post_content' => (string) $request->get_param('content'),
            'post_status' => $request->get_param('status') ? (string) $request->get_param('status') : 'publish',
        );

        $post_id = wp_insert_post($data, true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        Seminario_Meta::update_from_request($post_id, $request->get_param('meta'));
        Seminario_Taxonomies::set_terms_from_request($post_id, $request->get_param('taxonomies'));

        $featured_id = absint($request->get_param('featured_media'));
        if ($featured_id > 0) {
            $attachment = get_post($featured_id);
            if ($attachment && $attachment->post_type === 'attachment') {
                set_post_thumbnail($post_id, $featured_id);
            }
        }

        $post = get_post($post_id);
        return new WP_REST_Response(Seminario_Helpers::build_response($post), 201);
    }

    public static function update_item(WP_REST_Request $request)
    {
        $post = get_post((int) $request['id']);
        if (!$post || $post->post_type !== 'seminario') {
            return new WP_Error('seminario_not_found', 'Seminario no encontrado', array('status' => 404));
        }

        $data = array('ID' => $post->ID);
        if ($request->get_param('title') !== null) {
            $data['post_title'] = (string) $request->get_param('title');
        }
        if ($request->get_param('content') !== null) {
            $data['post_content'] = (string) $request->get_param('content');
        }
        if ($request->get_param('status') !== null) {
            $data['post_status'] = (string) $request->get_param('status');
        }

        $updated = wp_update_post($data, true);
        if (is_wp_error($updated)) {
            return $updated;
        }

        Seminario_Meta::update_from_request($post->ID, $request->get_param('meta'));
        Seminario_Taxonomies::set_terms_from_request($post->ID, $request->get_param('taxonomies'));

        if ($request->get_param('featured_media') !== null) {
            $featured_id = absint($request->get_param('featured_media'));
            if ($featured_id > 0) {
                $attachment = get_post($featured_id);
                if ($attachment && $attachment->post_type === 'attachment') {
                    set_post_thumbnail($post->ID, $featured_id);
                }
            } else {
                delete_post_thumbnail($post->ID);
            }
        }

        $post = get_post($post->ID);
        return Seminario_Helpers::build_response($post);
    }

    public static function delete_item(WP_REST_Request $request)
    {
        $post = get_post((int) $request['id']);
        if (!$post || $post->post_type !== 'seminario') {
            return new WP_Error('seminario_not_found', 'Seminario no encontrado', array('status' => 404));
        }

        $force = (bool) $request->get_param('force');
        $deleted = wp_delete_post($post->ID, $force);
        if (!$deleted) {
            return new WP_Error('seminario_not_deleted', 'No se pudo eliminar el seminario', array('status' => 500));
        }

        return array(
            'id' => $post->ID,
            'deleted' => true,
        );
    }

    /**
     * Endpoint REST para enviar consultas sobre seminarios
     * POST /flacso/v1/consulta-seminario
     */
    public static function submit_consulta(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_body_params();
        }

        $campos_obligatorios = ['seminario_id', 'seminario_titulo', 'nombre', 'correo', 'telefono', 'pais', 'consulta'];
        $campos_faltantes = [];

        foreach ($campos_obligatorios as $campo) {
            if (empty($params[$campo])) {
                $campos_faltantes[] = $campo;
            }
        }

        if (!empty($campos_faltantes)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Campos obligatorios faltantes: ' . implode(', ', $campos_faltantes)
            ], 400);
        }

        $seminario_id = intval($params['seminario_id']);
        $seminario = get_post($seminario_id);
        if (!$seminario || $seminario->post_type !== 'seminario') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'El seminario especificado no existe'
            ], 404);
        }

        $endpoint_url = get_option('flacso_seminario_consulta_endpoint_url', '');
        if ($endpoint_url === '') {
            $editor_url = get_option('flacso_external_editor_url', '');
            if ($editor_url !== '') {
                $endpoint_url = trailingslashit($editor_url) . 'api/consultas/seminarios';
            }
        }

        if ($endpoint_url === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'El endpoint de consultas no está configurado.'
            ], 503);
        }

        $webhook_token = get_option('flacso_webhook_token', '');

        $event_id = wp_generate_uuid4();
        $payload = [
            'event_id' => $event_id,
            'seminario_id' => (string) $seminario_id,
            'seminario_titulo' => sanitize_text_field($params['seminario_titulo']),
            'nombre' => sanitize_text_field($params['nombre']),
            'correo' => sanitize_email($params['correo']),
            'telefono' => sanitize_text_field($params['telefono']),
            'pais' => sanitize_text_field($params['pais']),
            'consulta' => sanitize_textarea_field($params['consulta']),
            'meta' => [
                'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
                'timestamp' => current_time('mysql')
            ]
        ];

        $headers = ['Content-Type' => 'application/json; charset=utf-8'];
        if ($webhook_token !== '') {
            $headers['X-FLACSO-Webhook-Token'] = $webhook_token;
            $headers['Authorization'] = 'Bearer ' . $webhook_token;
        }
        $headers['X-Idempotency-Key'] = $event_id;

        $response = wp_remote_post($endpoint_url, [
            'body'    => wp_json_encode($payload),
            'headers' => $headers,
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Error de conexión con el CRM.'
            ], 502);
        }

        $response_code = (int) wp_remote_retrieve_response_code($response);
        $response_body = (string) wp_remote_retrieve_body($response);
        $decoded_body = json_decode($response_body, true);

        if ($response_code < 200 || $response_code >= 300) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'El CRM respondió con código ' . $response_code . '. La consulta no se confirmó.',
                'response_code' => $response_code,
            ], 502);
        }

        $crm_confirmed = !empty($decoded_body['ok']) && !empty($decoded_body['data']['saved']);
        if (!$crm_confirmed) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'El CRM no confirmó el guardado de la consulta.',
                'editor_response' => $decoded_body,
            ], 502);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Consulta enviada correctamente',
            'timestamp' => current_time('mysql'),
            'editor_response' => $decoded_body,
        ], 200);
    }
}

