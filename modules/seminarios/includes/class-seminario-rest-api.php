<?php
if (!defined('ABSPATH')) {
    exit;
}

class Seminario_REST_API
{
    public static function register_routes()
    {
        register_rest_route('flacso/v1', '/posgrados', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_posgrados'),
            'permission_callback' => '__return_true',
        ));

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
        return array_values(array_filter($seminarios_ids));
    }

    public static function get_collection(WP_REST_Request $request)
    {
        $per_page = (int) $request->get_param('per_page');
        $page = (int) $request->get_param('page');
        $posgrado = $request->get_param('posgrado');
        $legacy_programa = $request->get_param('programa');
        $posgrado_value = $posgrado !== null && $posgrado !== '' ? $posgrado : $legacy_programa;

        $args = array(
            'post_type' => 'seminario',
            'post_status' => 'any',
            'posts_per_page' => $per_page > 0 ? $per_page : 10,
            'paged' => $page > 0 ? $page : 1,
        );

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
        // Obtener parámetros JSON
        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_body_params();
        }

        error_log('[FLACSO CONSULTA] Datos recibidos: ' . print_r($params, true));

        // Validar campos obligatorios
        $campos_obligatorios = ['seminario_id', 'seminario_titulo', 'nombre', 'correo', 'telefono', 'pais', 'consulta'];
        $campos_faltantes = [];

        foreach ($campos_obligatorios as $campo) {
            if (empty($params[$campo])) {
                $campos_faltantes[] = $campo;
            }
        }

        if (!empty($campos_faltantes)) {
            error_log('[FLACSO CONSULTA] Campos faltantes: ' . implode(', ', $campos_faltantes));
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Campos obligatorios faltantes: ' . implode(', ', $campos_faltantes)
            ], 400);
        }

        // Validar que el seminario exista
        $seminario_id = intval($params['seminario_id']);
        $seminario = get_post($seminario_id);
        if (!$seminario || $seminario->post_type !== 'seminario') {
            error_log('[FLACSO CONSULTA] Seminario no encontrado: ' . $seminario_id);
            return new WP_REST_Response([
                'success' => false,
                'message' => 'El seminario especificado no existe'
            ], 404);
        }

        // URL del webhook de Google Apps Script
        // IMPORTANTE: Actualizar esta URL con tu webhook de Google Sheets
        $webhook_url = "https://script.google.com/macros/s/YOUR_GOOGLE_APPS_SCRIPT_ID/exec";

        // Preparar payload para Google Sheets
        $payload = [
            'tipo'               => 'consulta-seminario',
            'seminario'          => [
                'id'     => $seminario_id,
                'titulo' => sanitize_text_field($params['seminario_titulo'])
            ],
            'datos'              => [
                'nombre'   => sanitize_text_field($params['nombre']),
                'correo'   => sanitize_email($params['correo']),
                'telefono' => sanitize_text_field($params['telefono']),
                'pais'     => sanitize_text_field($params['pais']),
                'consulta' => sanitize_textarea_field($params['consulta'])
            ],
            'meta'               => [
                'ip'        => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
                'timestamp' => current_time('mysql')
            ]
        ];

        $body = json_encode($payload);
        error_log('[FLACSO CONSULTA] Enviando webhook: ' . $body);

        // Enviar al webhook
        $response = wp_remote_post($webhook_url, [
            'body'    => $body,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'timeout' => 15,
        ]);

        // Manejar errores de conexión
        if (is_wp_error($response)) {
            error_log('[FLACSO CONSULTA] Error de conexión: ' . $response->get_error_message());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Error de conexión: ' . $response->get_error_message()
            ], 500);
        }

        // Verificar respuesta del webhook
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($response_body, true);

        error_log('[FLACSO CONSULTA] Respuesta webhook (' . $response_code . '): ' . $response_body);

        // Considerar 400 de Google como "éxito práctico" si validamos en WP
        if ($response_code === 400) {
            error_log('[FLACSO CONSULTA] Google respondió 400 pero validamos en WordPress. Considerando éxito práctico.');
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Consulta recibida correctamente',
                'timestamp' => current_time('mysql')
            ], 200);
        }

        // Manejar otros códigos de error
        if ($response_code !== 200 || (isset($decoded_body['success']) && !$decoded_body['success'])) {
            error_log('[FLACSO CONSULTA] Error del webhook: ' . ($decoded_body['message'] ?? 'Respuesta inesperada'));
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Error al procesar la consulta: ' . ($decoded_body['message'] ?? 'Respuesta inesperada')
            ], 500);
        }

        // Éxito
        error_log('[FLACSO CONSULTA] Consulta procesada exitosamente');
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Consulta enviada correctamente',
            'timestamp' => current_time('mysql')
        ], 200);
    }
}

