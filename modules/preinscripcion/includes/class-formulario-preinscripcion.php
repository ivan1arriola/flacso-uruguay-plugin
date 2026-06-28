<?php
/**
 * Clase principal del formulario independiente de preinscripción.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/trait-assets.php';
require_once __DIR__ . '/trait-render.php';
require_once __DIR__ . '/trait-templates.php';
require_once __DIR__ . '/trait-migracion.php';

class FLACSO_Formulario_Preinscripcion_Final {
    use FLACSO_Formulario_Preinscripcion_Assets, 
        FLACSO_Formulario_Preinscripcion_Render,
        FLACSO_Formulario_Preinscripcion_Templates,
        FLACSO_Formulario_Preinscripcion_Migracion;

    private const MAESTRIA_TIPO_OFERTA_TERM_ID = 265;

    private static $instance = null;
    public static function get_instance() {
        if (self::$instance === null) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        // AJAX handlers para el formulario
        add_action('wp_ajax_flacso_enviar_preinscripcion', array($this, 'procesar_formulario'));
        add_action('wp_ajax_nopriv_flacso_enviar_preinscripcion', array($this, 'procesar_formulario'));


        // Template system y rewrite rules
        add_action('init', array($this, 'registrar_templates'));
        add_action('init', array($this, 'registrar_rewrite_rules'));
        add_filter('query_vars', array($this, 'agregar_query_vars'));

        // Test webhook
        add_action('admin_post_flacso_preinscripciones_test_webhook', array($this, 'procesar_test_webhook'));
    }

    private function flush_output_buffers() {
        while (ob_get_level() > 0) {
            if (!@ob_end_clean()) { break; }
        }
    }

    private function remove_utf8_bom($text) {
        if (!is_string($text) || $text === '') {
            return $text;
        }
        $clean = preg_replace('/^\xEF\xBB\xBF/u', '', $text);
        return ($clean === null) ? $text : $clean;
    }

    private function send_json_success($data = null, $status_code = null) {
        $this->flush_output_buffers();
        wp_send_json_success($data, $status_code);
    }

    private function send_json_error($data = null, $status_code = null) {
        $this->flush_output_buffers();
        wp_send_json_error($data, $status_code);
    }

    private function archivo_obligatorio_presente($campo) {
        if (!isset($_FILES[$campo])) { return false; }
        $file = $_FILES[$campo];
        if (is_array($file['error'])) {
            foreach ($file['error'] as $err) {
                if ($err === UPLOAD_ERR_OK) { return true; }
            }
            return false;
        }
        return ($file['error'] === UPLOAD_ERR_OK) && !empty($file['name']);
    }

    public function configurar_limites_archivos() {
        @ini_set('upload_max_size', '64M');
        @ini_set('post_max_size', '64M');
        @ini_set('max_execution_time', '300');
    }

    /**
     * Registra rewrite rules para páginas virtuales de preinscripción
     */
    public function registrar_rewrite_rules() {
        // Captura /formacion/tipo/slug/preinscripcion/ para el CPT
        add_rewrite_rule(
            '^formacion/[^/]+/([^/]+)/preinscripcion/?$',
            'index.php?oferta-academica=$matches[1]&es_preinscripcion=1',
            'top'
        );
        
        // Captura /formacion/tipo/slug/carta/ para el CPT
        add_rewrite_rule(
            '^formacion/[^/]+/([^/]+)/carta/?$',
            'index.php?oferta-academica=$matches[1]&es_carta=1',
            'top'
        );

        // Captura /cualquier-pagina/preinscripcion/ como página virtual (Legacy)
        add_rewrite_rule(
            '^(.+?)/preinscripcion/?$',
            'index.php?pagename=$matches[1]&es_preinscripcion=1',
            'top'
        );
        
        // Captura /cualquier-pagina/carta/ como página virtual (Legacy)
        add_rewrite_rule(
            '^(.+?)/carta/?$',
            'index.php?pagename=$matches[1]&es_carta=1',
            'top'
        );
    }

    /**
     * Agrega variables de consulta personalizadas
     */
    public function agregar_query_vars($vars) {
        $vars[] = 'es_preinscripcion';
        $vars[] = 'es_carta';
        return $vars;
    }

    /**
     * Verifica si una página tiene preinscripción activa
     */
    /**
     * Obtiene el ID de la oferta académica asociada a una página de WordPress.
     */
    private function obtener_oferta_id_por_pagina($page_id) {
        $page_id = (int) $page_id;
        if ($page_id <= 0) {
            return 0;
        }
        $ids = get_posts(array(
            'post_type' => 'oferta-academica',
            'post_status' => array('publish', 'private'),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_oferta_page_id',
            'meta_value' => $page_id,
            'no_found_rows' => true,
        ));
        return !empty($ids) ? (int) $ids[0] : 0;
    }

    private function resolver_oferta_id_desde_programa($programa_id) {
        $programa_id = (int) $programa_id;
        if ($programa_id <= 0) {
            return 0;
        }

        if (get_post_type($programa_id) === 'oferta-academica') {
            return $programa_id;
        }

        return $this->obtener_oferta_id_por_pagina($programa_id);
    }

    private function oferta_tiene_tipo_term_id($oferta_id, $term_id) {
        $oferta_id = (int) $oferta_id;
        $term_id = (int) $term_id;

        if ($oferta_id <= 0 || $term_id <= 0) {
            return false;
        }

        $term_ids = wp_get_post_terms($oferta_id, 'tipo-oferta-academica', array(
            'fields' => 'ids',
        ));

        if (is_wp_error($term_ids) || empty($term_ids)) {
            return false;
        }

        return in_array($term_id, array_map('intval', $term_ids), true);
    }

    private function es_oferta_maestria($programa_id) {
        $oferta_id = $this->resolver_oferta_id_desde_programa($programa_id);
        return $this->oferta_tiene_tipo_term_id($oferta_id, self::MAESTRIA_TIPO_OFERTA_TERM_ID);
    }

    public function es_pagina_preinscripcion_activa($page_id) {
        $page_id = (int)$page_id;

        // Si hay una oferta académica asociada, la preinscripción está activa
        $oferta_id = $this->obtener_oferta_id_por_pagina($page_id);
        if ($oferta_id > 0) {
            return true;
        }

        $paginas_activas = get_option('flacso_preinscripciones_activas', array());
        return in_array($page_id, array_map('intval', $paginas_activas));
    }

    /**
     * Retorna true cuando las preinscripciones estan cerradas globalmente.
     */
    public function preinscripciones_estan_cerradas() {
        return (bool) get_option('flacso_preinscripciones_cerradas', 0);
    }

    /**
     * Retorna true cuando un programa tiene su formulario cerrado temporalmente.
     */
    public function preinscripcion_programa_esta_cerrada($programa_id) {
        $programa_id = (int) $programa_id;
        if ($programa_id <= 0) {
            return false;
        }

        $oferta_id = 0;
        if (get_post_type($programa_id) === 'oferta-academica') {
            $oferta_id = $programa_id;
        } else {
            $oferta_id = $this->obtener_oferta_id_por_pagina($programa_id);
        }

        if ($oferta_id > 0) {
            $inscripciones_abiertas = get_post_meta($oferta_id, 'inscripciones_abiertas', true);
            if ($inscripciones_abiertas === '1' || $inscripciones_abiertas === 'true' || $inscripciones_abiertas === true || $inscripciones_abiertas === 1) {
                return false;
            } else {
                return true;
            }
        }

        $paginas_cerradas = get_option('flacso_preinscripciones_cerradas_por_programa', array());
        return in_array($programa_id, array_map('intval', $paginas_cerradas), true);
    }

    /**
     * Evalua cierre global o cierre puntual por programa.
     */
    public function formulario_preinscripcion_esta_cerrado($programa_id = 0) {
        $programa_id = (int) $programa_id;
        $oferta_id = $this->resolver_oferta_id_desde_programa($programa_id);

        // Cuando existe una oferta academica asociada, la app pasa a ser la
        // unica fuente de verdad para abrir/cerrar preinscripciones.
        if ($oferta_id > 0) {
            return $this->preinscripcion_programa_esta_cerrada($oferta_id);
        }

        if ($this->preinscripciones_estan_cerradas()) {
            return true;
        }
        return $this->preinscripcion_programa_esta_cerrada($programa_id);
    }

    /**
     * Mensaje estandar mostrado cuando las preinscripciones estan cerradas.
     */
    public function obtener_mensaje_preinscripciones_cerradas() {
        return 'Por el momento no estamos recibiendo más preinscripciones.';
    }

    public function enqueue_assets() {
        // Cargar assets globales solo en la ruta virtual de preinscripcion.
        if (!get_query_var('es_preinscripcion')) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', array(), '5.3.0');
        wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true);
        wp_enqueue_style('bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css', array(), '1.11.0');
    }

    public function obtener_info_posgrado() {
        $page_id = get_the_ID();
        $parent_page_id = wp_get_post_parent_id($page_id);
        $id_posgrado = $parent_page_id ? (int)$parent_page_id : (int)$page_id;
        $offer_id = $this->resolver_oferta_id_desde_programa($id_posgrado);

        $info = array(
            'page_id' => (int)$page_id,
            'parent_page_id' => $parent_page_id ? (int)$parent_page_id : 0,
            'id_posgrado' => $id_posgrado,
            'offer_id' => $offer_id,
            'titulo_posgrado' => $id_posgrado ? get_the_title($id_posgrado) : '',
            'es_maestria' => $this->es_oferta_maestria($id_posgrado),
            'preinscripcion_cerrada' => $this->formulario_preinscripcion_esta_cerrado($id_posgrado),
            'imagen_destacada' => '',
            'convenios_validos' => $this->obtener_convenios_validos(),
        );
        if ($id_posgrado) {
            $imagen_url = get_the_post_thumbnail_url($id_posgrado, 'full');
            $info['imagen_destacada'] = $imagen_url ? $imagen_url : '';
        }
        return $info;
    }

    public function obtener_convenios_validos() {
        $cat_id = 72;
        $key = 'convenios_titulos_limpios_cat_' . $cat_id;
        if (($cache = get_transient($key)) !== false) { return $cache; }

        $ids = get_posts(array(
            'post_type' => 'post','post_status' => 'publish','posts_per_page' => -1,
            'fields' => 'ids','no_found_rows' => true,'orderby' => 'title','order' => 'ASC',
            'tax_query' => array(array( 'taxonomy' => 'category','field' => 'term_id','terms' => $cat_id ))
        ));

        $out = array();
        foreach ($ids as $id) {
            $titulo = get_the_title($id);
            if (!$titulo) continue;
            $titulo = html_entity_decode($titulo, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $titulo = preg_replace('/^\s*convenio\s*(?:[\p{Pd}:])?\s*/iu', '', $titulo);
            $titulo = trim(preg_replace('/\s+/u', ' ', $titulo));
            if ($titulo !== '') { $out[] = $titulo; }
        }
        set_transient($key, $out, 12 * HOUR_IN_SECONDS);
        return $out;
    }

    public function procesar_formulario() {
        $this->flush_output_buffers();
        $this->configurar_limites_archivos();
        set_time_limit(300);
        ini_set('memory_limit', '256M');
        ini_set('max_execution_time', 300);

        // Obtener webhook URL desde la configuración
        $webhook_url = trim((string) get_option('flacso_preinscripciones_webhook_url', ''));
        if (empty($webhook_url)) {
            $webhook_url = trim((string) get_option('fc_oferta_webhook_url', ''));
        }
        if (empty($webhook_url) && defined('FLACSO_WEBHOOK_URL')) {
            $webhook_url = trim((string) FLACSO_WEBHOOK_URL);
        }

        $webhook_token = sanitize_text_field((string) get_option('flacso_webhook_token', ''));
        if (empty($webhook_url)) {
            $this->send_json_error('Error de configuración: No se ha configurado la URL del webhook. Contacte al administrador.');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->send_json_error('Método no permitido.'); }
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'flacso_form_nonce')) { $this->send_json_error('Error de seguridad. Por favor, recargue la página.'); }

        $id_pagina       = (int)($_POST['id_pagina'] ?? 0);
        $titulo_posgrado = sanitize_text_field($_POST['titulo_posgrado'] ?? '');
        $oferta_id       = $this->resolver_oferta_id_desde_programa($id_pagina);
        $es_maestria     = $this->es_oferta_maestria($id_pagina);
        if (!$id_pagina || !$titulo_posgrado) { $this->send_json_error('Datos incompletos del formulario.'); }
        if ($oferta_id <= 0) { $this->send_json_error('No se pudo resolver la oferta académica asociada al formulario.'); }
        if ($this->formulario_preinscripcion_esta_cerrado($id_pagina)) {
            $this->send_json_error($this->obtener_mensaje_preinscripciones_cerradas(), 403);
        }

        $campos_obligatorios = array('correo', 'nombre1', 'apellido1', 'celular');
        foreach ($campos_obligatorios as $campo) {
            if (empty($_POST[$campo])) { $this->send_json_error("El campo $campo es obligatorio."); }
        }

        if (!$this->archivo_obligatorio_presente('carta_motivacion')) {
            $this->send_json_error('La carta de motivación es obligatoria para todos los posgrados.');
        }

        // Validación de documento
        $tipo_documento = $_POST['tipo_documento'] ?? '';
        if ($tipo_documento === 'cedula_uruguaya') {
            $documento = $_POST['cedula_uruguaya'] ?? '';
            if (empty($documento)) { $this->send_json_error('El campo Cédula de Identidad Uruguaya es obligatorio.'); }
            $documento = preg_replace('/\D+/', '', $documento);
            if (!$this->validar_cedula_uruguaya($documento)) { $this->send_json_error('El número de cédula uruguaya no es válido. Verifique el dígito verificador.'); }
            $_POST['documento'] = $documento;
        } else {
            $documento = $_POST['otro_documento'] ?? '';
            if (empty($documento)) { $this->send_json_error("El campo Número de Documento es obligatorio."); }
            $_POST['documento'] = $documento;
        }

        // Capturar celular E.164 si vino desde el front
        $cel_e164 = sanitize_text_field($_POST['celular_e164'] ?? '');
        if (empty($cel_e164)) {
            // fallback: guardar nacional si no vino el internacional
            $cel_e164 = sanitize_text_field($_POST['celular'] ?? '');
        }

        $datos_basicos = array();
        foreach ($_POST as $k => $v) {
            if (in_array($k, array('nonce','action','id_pagina','titulo_posgrado','es_maestria'), true)) { continue; }
            $datos_basicos[$k] = is_array($v) ? array_map('sanitize_text_field', $v) : sanitize_text_field($v);
        }
        $datos_basicos['celular_e164'] = $cel_e164;

        // Archivos
        $archivos = array();
        $max_file_size = 10 * 1024 * 1024;
        if (!empty($_FILES)) {
            foreach ($_FILES as $campo => $file) {
                if (!$es_maestria && in_array($campo, array('carta_recomendacion_1','carta_recomendacion_2'), true)) { continue; }

                $pushFile = function($name, $type, $tmp, $error) use (&$archivos, $campo, $max_file_size) {
                    if ($error !== UPLOAD_ERR_OK) { error_log("Error subiendo archivo $name: código $error"); return; }
                    if (!file_exists($tmp)) { error_log("Archivo temporal no existe: $tmp"); return; }
                    $file_size = filesize($tmp);
                    if ($file_size > $max_file_size) { error_log("Archivo $name excede tamaño máximo: $file_size bytes"); return; }
                    $content = file_get_contents($tmp);
                    if ($content === false) { error_log("No se pudo leer el archivo: $tmp"); return; }
                    $archivos[$campo][] = array('name'=>sanitize_file_name($name),'type'=>$type ?: 'application/octet-stream','content'=>base64_encode($content));
                };

                if (is_array($file['name'])) {
                    foreach ($file['name'] as $i => $name) {
                        if (!empty($name)) { $pushFile($name, $file['type'][$i] ?? '', $file['tmp_name'][$i] ?? '', $file['error'][$i] ?? UPLOAD_ERR_NO_FILE); }
                    }
                } elseif (!empty($file['name'])) {
                    $pushFile($file['name'], $file['type'] ?? '', $file['tmp_name'] ?? '', $file['error'] ?? UPLOAD_ERR_NO_FILE);
                }
            }
        }

        // Capturar metadata de la solicitud
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $payload = array(
            'posgrado' => array(
                'id' => $oferta_id,
                'titulo' => $titulo_posgrado,
                'es_maestria' => $es_maestria ? 'si' : 'no'
            ),
            'datos' => array(
                'correo' => $datos_basicos['correo'] ?? '',
                'nombre1' => $datos_basicos['nombre1'] ?? '',
                'apellido1' => $datos_basicos['apellido1'] ?? '',
                'nombre2' => $datos_basicos['nombre2'] ?? '',
                'apellido2' => $datos_basicos['apellido2'] ?? '',
                'tipo_documento' => $datos_basicos['tipo_documento'] ?? '',
                'documento' => $datos_basicos['documento'] ?? '',
                'fecha_nacimiento' => $datos_basicos['fecha_nacimiento'] ?? '',
                'genero' => $datos_basicos['genero'] ?? '',
                'genero_otra' => $datos_basicos['genero_otra'] ?? '',
                'celular' => $cel_e164 ?: ($datos_basicos['celular'] ?? ''),
                'celular_e164' => $cel_e164,
                'pais_nacimiento' => $datos_basicos['pais_nacimiento'] ?? '',
                'pais_residencia' => $datos_basicos['pais_residencia'] ?? '',
                // Campos actuales del formulario (y fallback a nombres legacy si existen).
                'domicilio' => $datos_basicos['domicilio'] ?? ($datos_basicos['direccion'] ?? ''),
                'ocupacion' => $datos_basicos['ocupacion'] ?? ($datos_basicos['ocupacion_actual'] ?? ''),
                'estudios' => $datos_basicos['estudios'] ?? ($datos_basicos['nivel_estudios'] ?? ''),
                'posgrado_flacso' => $datos_basicos['posgrado_flacso'] ?? '',
                'posgrado_flacso_detalle' => $datos_basicos['posgrado_flacso_detalle'] ?? '',
                'convenio_flacso' => $datos_basicos['convenio_flacso'] ?? '',
                'convenio_flacso_detalle' => $datos_basicos['convenio_flacso_detalle'] ?? '',
                'fuente' => $datos_basicos['fuente'] ?? ($datos_basicos['como_conociste'] ?? ''),
                'acepta_difusion' => $datos_basicos['acepta_difusion'] ?? '',
                'titulo_grado_especificacion' => $datos_basicos['titulo_grado_especificacion'] ?? ($datos_basicos['titulo_obtenido'] ?? ''),
                'documentacion_completa' => $datos_basicos['documentacion_completa'] ?? '',
                'documentacion_faltante' => $datos_basicos['documentacion_faltante'] ?? '',
                // Campos legacy adicionales (ya no se muestran en el formulario actual).
                'direccion' => $datos_basicos['direccion'] ?? '',
                'nivel_estudios' => $datos_basicos['nivel_estudios'] ?? '',
                'titulo_obtenido' => $datos_basicos['titulo_obtenido'] ?? '',
                'institucion_egreso' => $datos_basicos['institucion_egreso'] ?? '',
                'ano_egreso' => $datos_basicos['ano_egreso'] ?? '',
                'area_conocimiento' => $datos_basicos['area_conocimiento'] ?? '',
                'ocupacion_actual' => $datos_basicos['ocupacion_actual'] ?? '',
                'institucion_trabajo' => $datos_basicos['institucion_trabajo'] ?? '',
                'como_conociste' => $datos_basicos['como_conociste'] ?? ''
            ),
            'archivos' => $archivos,
            'meta' => array(
                'timestamp_client' => current_time('c'),
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'host_post_id' => $id_pagina,
                'origen' => 'wordpress_formulario_preinscripcion'
            )
        );
        $body_json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body_json === false) { $this->send_json_error('Error codificando los datos del formulario.'); }

        $webhook_headers = array();
        if ($webhook_token !== '') {
            $webhook_headers['X-FLACSO-Webhook-Token'] = $webhook_token;
            $webhook_headers['Authorization'] = 'Bearer ' . $webhook_token;
        }

        $result = wp_remote_post($webhook_url, array(
            'headers' => array_merge(array(
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept'       => 'application/json',
                'User-Agent'   => 'FLACSO-Uruguay-Form/1.0',
            ), $webhook_headers),
            'body' => $body_json,
            'timeout' => 100,
            'redirection' => 3,
            'blocking' => true,
            'httpversion' => '1.1',
            'data_format' => 'body',
        ));

        if (is_wp_error($result)) {
            error_log('Error en webhook preinscripciones: ' . $result->get_error_message());
            $this->send_telegram_error_notification('WP_Error / Fallo de Red', $result->get_error_message(), $payload);
            $this->send_json_error('Error de conexión con el servidor. Por favor, intente nuevamente.');
        }

        $status = wp_remote_retrieve_response_code($result);
        $body = wp_remote_retrieve_body($result);
        error_log("Respuesta webhook preinscripciones - Status: $status, Body: " . substr($body, 0, 500));

        $json = json_decode($body, true);
        if ($status === 200 && is_array($json) && ($json['ok'] ?? false)) {
            $this->send_json_success(array(
                'message' => 'Preinscripción enviada correctamente.',
                'editor_response' => $json,
            ));
        }

        $error_msg = 'Error del servidor. Por favor, contacte a inscripciones@flacso.edu.uy';
        if (is_array($json) && is_array($json['error'] ?? null) && !empty($json['error']['message'])) {
            $error_msg = (string) $json['error']['message'];
        } elseif (is_array($json) && is_string($json['message'] ?? null) && $json['message'] !== '') {
            $error_msg = $json['message'];
        } elseif ($body) {
            $error_msg = "Error: $body";
        }

        $this->send_telegram_error_notification("HTTP $status", $error_msg, $payload);
        $this->send_json_error($error_msg);
    }
    


    public function procesar_test_webhook() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos suficientes.', 'flacso-uruguay'));
        }
        if (!isset($_POST['flacso_preinscripciones_test_webhook_nonce']) || !wp_verify_nonce(wp_unslash($_POST['flacso_preinscripciones_test_webhook_nonce']), 'flacso_preinscripciones_test_webhook')) {
            wp_die(esc_html__('Solicitud no válida.', 'flacso-uruguay'));
        }

        $webhook_url = trim((string) get_option('flacso_preinscripciones_webhook_url', ''));
        if (empty($webhook_url)) {
            $webhook_url = trim((string) get_option('fc_oferta_webhook_url', ''));
        }
        if (empty($webhook_url) && defined('FLACSO_WEBHOOK_URL')) {
            $webhook_url = trim((string) FLACSO_WEBHOOK_URL);
        }

        $result = array('ok' => false, 'code' => 0, 'error' => '', 'message' => '');

        if (empty($webhook_url)) {
            $result['error'] = 'No se ha configurado la URL del webhook.';
        } else {
            $webhook_token = sanitize_text_field((string) get_option('flacso_webhook_token', ''));
            $webhook_headers = array();
            if ($webhook_token !== '') {
                $webhook_headers['X-FLACSO-Webhook-Token'] = $webhook_token;
                $webhook_headers['Authorization'] = 'Bearer ' . $webhook_token;
            }

            $payload = array(
                'test' => true,
                'origen' => 'wp_preinscripciones_test',
                'timestamp' => current_time('mysql')
            );
            $body_json = wp_json_encode($payload);

            $response = wp_remote_post($webhook_url, array(
                'headers' => array_merge(array(
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Accept'       => 'application/json',
                    'User-Agent'   => 'FLACSO-Uruguay-Form/1.0',
                ), $webhook_headers),
                'body' => $body_json,
                'timeout' => 15,
                'blocking' => true,
            ));

            if (is_wp_error($response)) {
                $result['error'] = 'Error de conexión: ' . $response->get_error_message();
            } else {
                $status = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $json = json_decode($body, true);
                
                $result['code'] = $status;
                if ($status >= 200 && $status < 300) {
                    $result['ok'] = true;
                } else {
                    $result['message'] = 'El servidor respondió HTTP ' . $status . '.';
                    if (is_array($json) && !empty($json['error'])) {
                        $result['message'] .= ' ' . (is_string($json['error']) ? $json['error'] : wp_json_encode($json['error']));
                    }
                }
            }
        }

        $args = array(
            'flacso_preinscripciones_webhook_test' => $result['ok'] ? 'success' : 'fail',
        );

        if (!empty($result['code'])) {
            $args['flacso_preinscripciones_webhook_code'] = (int) $result['code'];
        }

        $message = '';
        if (!empty($result['message'])) {
            $message = sanitize_text_field((string) $result['message']);
        } elseif (!empty($result['error'])) {
            $message = sanitize_text_field((string) $result['error']);
        }

        if ('' !== $message) {
            $args['flacso_preinscripciones_webhook_message'] = $message;
        }

        $redirect_url = admin_url('options-general.php?page=flacso-integraciones');
        if (class_exists('FLACSO_Integrations_Settings')) {
            $redirect_url = FLACSO_Integrations_Settings::get_redirect_url_from_request($args, $redirect_url);
        } else {
            $redirect_url = add_query_arg($args, $redirect_url);
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    public function validar_cedula_uruguaya($ci) {
        $digits = preg_replace('/\D+/', '', (string) $ci);
        $len = strlen($digits);
        if ($len < 7 || $len > 8) {
            return false;
        }
        $normalized = str_pad($digits, 8, '0', STR_PAD_LEFT);
        $cuerpo = substr($normalized, 0, 7);
        $verificador = (int) substr($normalized, -1);
        $factores = array(2, 9, 8, 7, 6, 3, 4);
        $suma = 0;
        for ($i = 0; $i < 7; $i++) {
            $suma += ((int) $cuerpo[$i]) * $factores[$i];
        }
        $resto = $suma % 10;
        $esperado = ($resto === 0) ? 0 : 10 - $resto;
        return $verificador === $esperado;
    }

    private function send_telegram_error_notification(string $error_type, string $error_msg, array $payload): void {
        if (!function_exists('fc_send_telegram_message')) {
            return;
        }

        $webhook_url = trim((string) get_option('flacso_preinscripciones_webhook_url', ''));
        if (empty($webhook_url)) {
            $webhook_url = trim((string) get_option('fc_oferta_webhook_url', ''));
        }
        if (empty($webhook_url) && defined('FLACSO_WEBHOOK_URL')) {
            $webhook_url = trim((string) FLACSO_WEBHOOK_URL);
        }

        $datos = $payload['datos'] ?? [];
        $posgrado = $payload['posgrado'] ?? [];

        $nombre = trim(($datos['nombre1'] ?? '') . ' ' . ($datos['nombre2'] ?? '') . ' ' . ($datos['apellido1'] ?? '') . ' ' . ($datos['apellido2'] ?? ''));
        if ($nombre === '') {
            $nombre = 'No especificado';
        }
        $email = $datos['correo'] ?? 'No especificado';
        $telefono = $datos['celular'] ?? 'No especificado';
        $documento = trim(($datos['tipo_documento'] ?? '') . ' ' . ($datos['documento'] ?? ''));
        if ($documento === '') {
            $documento = 'No especificado';
        }
        $carrera = $posgrado['titulo'] ?? 'No especificada';
        
        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $fecha = current_time('d/m/Y H:i:s');

        // Limpiar código HTML del cuerpo de error (por ejemplo, el HTML del error 400 de Google)
        $error_clean = strip_tags($error_msg);
        $error_clean = html_entity_decode($error_clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $error_clean = preg_replace('/\s+/', ' ', $error_clean);
        $error_clean = trim($error_clean);
        if (strlen($error_clean) > 400) {
            $error_clean = substr($error_clean, 0, 397) . '...';
        }

        $msg = "🚨 <b>URGENTE: Fallo al enviar Preinscripción</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "<b>📍 Sitio:</b> " . htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8') . "\n";
        $msg .= "<b>📅 Fecha:</b> " . $fecha . "\n";
        $msg .= "<b>🔗 Webhook:</b> <code>" . htmlspecialchars($webhook_url, ENT_QUOTES, 'UTF-8') . "</code>\n\n";

        $msg .= "<b>🎓 Posgrado:</b>\n";
        $msg .= "  " . htmlspecialchars($carrera, ENT_QUOTES, 'UTF-8') . " (ID: " . intval($posgrado['id'] ?? 0) . ")\n\n";

        $msg .= "<b>👤 Datos del Alumno:</b>\n";
        $msg .= "  <b>Nombre:</b> " . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . "\n";
        $msg .= "  <b>Documento:</b> " . htmlspecialchars($documento, ENT_QUOTES, 'UTF-8') . "\n";
        $msg .= "  <b>Email:</b> " . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "\n";
        $msg .= "  <b>Teléfono:</b> " . htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8') . "\n\n";

        $msg .= "<b>❌ Detalles del Error (" . htmlspecialchars($error_type, ENT_QUOTES, 'UTF-8') . "):</b>\n";
        $msg .= "<pre>" . htmlspecialchars($error_clean, ENT_QUOTES, 'UTF-8') . "</pre>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "⚠️ <i>Por favor, contactar al alumno a la brevedad para realizar la preinscripción de forma manual.</i>";

        fc_send_telegram_message($msg);
    }
}




