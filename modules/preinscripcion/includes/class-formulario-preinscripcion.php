<?php
/**
 * Clase principal del formulario independiente de preinscripción.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/trait-assets.php';
require_once __DIR__ . '/trait-render.php';
require_once __DIR__ . '/trait-admin.php';
require_once __DIR__ . '/trait-templates.php';
require_once __DIR__ . '/trait-migracion.php';

class FLACSO_Formulario_Preinscripcion_Final {
    use FLACSO_Formulario_Preinscripcion_Assets, 
        FLACSO_Formulario_Preinscripcion_Render,
        FLACSO_Formulario_Preinscripcion_Admin,
        FLACSO_Formulario_Preinscripcion_Templates,
        FLACSO_Formulario_Preinscripcion_Migracion;


    private static $instance = null;
    public static function get_instance() {
        if (self::$instance === null) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        // AJAX handlers para el formulario
        add_action('wp_ajax_flacso_enviar_preinscripcion', array($this, 'procesar_formulario'));
        add_action('wp_ajax_nopriv_flacso_enviar_preinscripcion', array($this, 'procesar_formulario'));

        // Admin panel
        add_action('admin_menu', array($this, 'registrar_menu_admin'));
        
        // Template system y rewrite rules
        add_action('init', array($this, 'registrar_templates'));
        add_action('init', array($this, 'registrar_rewrite_rules'));
        add_filter('query_vars', array($this, 'agregar_query_vars'));
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
        // Captura /cualquier-pagina/preinscripcion/ como página virtual
        add_rewrite_rule(
            '^(.+?)/preinscripcion/?$',
            'index.php?pagename=$matches[1]&es_preinscripcion=1',
            'top'
        );
    }

    /**
     * Agrega variables de consulta personalizadas
     */
    public function agregar_query_vars($vars) {
        $vars[] = 'es_preinscripcion';
        return $vars;
    }

    /**
     * Verifica si una página tiene preinscripción activa
     */
    public function es_pagina_preinscripcion_activa($page_id) {
        $paginas_activas = get_option('flacso_preinscripciones_activas', array());
        return in_array((int)$page_id, array_map('intval', $paginas_activas));
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
        $maestrias = array_map('intval', array(12330, 12336, 12343));

        $info = array(
            'page_id' => (int)$page_id,
            'parent_page_id' => $parent_page_id ? (int)$parent_page_id : 0,
            'id_posgrado' => $id_posgrado,
            'titulo_posgrado' => $id_posgrado ? get_the_title($id_posgrado) : '',
            'es_maestria' => in_array($id_posgrado, $maestrias, true),
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
        $webhook_url = get_option('flacso_preinscripciones_webhook_url', '');
        if (empty($webhook_url)) {
            $this->send_json_error('Error de configuración: No se ha configurado la URL del webhook. Contacte al administrador.');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->send_json_error('Método no permitido.'); }
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'flacso_form_nonce')) { $this->send_json_error('Error de seguridad. Por favor, recargue la página.'); }

        $id_pagina       = (int)($_POST['id_pagina'] ?? 0);
        $titulo_posgrado = sanitize_text_field($_POST['titulo_posgrado'] ?? '');
        $es_maestria     = (($_POST['es_maestria'] ?? '') === 'si');
        if (!$id_pagina || !$titulo_posgrado) { $this->send_json_error('Datos incompletos del formulario.'); }

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
            if (empty($documento)) { $this->send_json_error('El campo Cedula de Identidad Uruguaya es obligatorio.'); }
            $documento = preg_replace('/\D+/', '', $documento);
            if (!$this->validar_cedula_uruguaya($documento)) { $this->send_json_error('El numero de cedula uruguaya no es valido. Verifique el digito verificador.'); }
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
                'id' => $id_pagina,
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
                'timestamp' => current_time('mysql'),
                'ip' => $ip_address,
                'ua' => $user_agent,
                'origen' => 'wordpress_formulario_preinscripcion'
            )
        );
        $body_json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body_json === false) { $this->send_json_error('Error codificando los datos del formulario.'); }

        $result = $this->tester_pre_manual_post_to_gas($webhook_url, $body_json, array(), 45);
        if (isset($result['error'])) { error_log('Error en webhook: ' . $result['error']); $this->send_json_error('Error de conexión con el servidor. Por favor, intente nuevamente.'); }

        $status = $result['code']; $body = $this->remove_utf8_bom($result['body']);
        if (isset($result['step1'])) { $status = $result['code']; $body = $this->remove_utf8_bom($result['body']); }
        error_log("Respuesta webhook - Status: $status, Body: " . substr($body, 0, 500));

        $json = json_decode($body, true);
        if ($status === 200 && is_array($json) && ($json['success'] ?? false)) {
            $this->send_json_success(array('message' => 'Preinscripción enviada correctamente.'));
        }

        $error_msg = 'Error del servidor. Por favor, contacte a inscripciones@flacso.edu.uy';
        if (is_array($json) && isset($json['error'])) { $error_msg = $json['error']; }
        elseif ($body) { $error_msg = "Error: $body"; }
        $this->send_json_error($error_msg);
    }
    
    private function tester_pre_manual_post_to_gas($endpoint, $payload_json, $headers = array(), $timeout = 45) {
        $args1 = array(
            'headers' => array_merge(array(
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept'       => 'application/json, text/plain, */*',
                'Expect'       => '',
                'User-Agent'   => 'FLACSO-Uruguay-Form/1.0',
            ), $headers),
            'body' => $payload_json, 'timeout'=>$timeout, 'redirection'=>0, 'httpversion'=>'1.1', 'data_format'=>'body',
        );
        $res1 = wp_remote_post($endpoint, $args1);
        if (is_wp_error($res1)) { return array('error' => $res1->get_error_message(), 'step' => 1); }

        $code1 = intval(wp_remote_retrieve_response_code($res1));
        $hdrs1 = wp_remote_retrieve_headers($res1);
        $body1 = $this->remove_utf8_bom(wp_remote_retrieve_body($res1));
        $loc = null;
        if (is_array($hdrs1)) { $loc = $hdrs1['location'] ?? $hdrs1['Location'] ?? null; }
        elseif (is_object($hdrs1) && method_exists($hdrs1, 'offsetGet')) { $loc = $hdrs1->offsetGet('location') ?: $hdrs1->offsetGet('Location'); }

        if ($code1 < 300 || $code1 > 399 || empty($loc)) {
            return array('step'=>1,'code'=>$code1,'headers'=>$hdrs1,'body'=>$body1,'note'=>'Sin redirect; respuesta directa del Web App');
        }

        $args2 = array(
            'headers'=>array_merge(array('Accept'=>'application/json, text/plain, */*','User-Agent'=>'FLACSO-Uruguay-Form/1.0'), $headers),
            'timeout'=>$timeout,'redirection'=>0,'httpversion'=>'1.1',
        );
        $res2 = wp_remote_get($loc, $args2);
        if (is_wp_error($res2)) {
            return array('step1'=>array('code'=>$code1,'headers'=>$hdrs1,'body'=>$body1,'location'=>$loc),'error'=>$res2->get_error_message(),'step'=>2);
        }
        $body2 = $this->remove_utf8_bom(wp_remote_retrieve_body($res2));
        return array(
            'step1'=>array('code'=>$code1,'headers'=>$hdrs1,'body'=>$body1,'location'=>$loc),
            'step'=>2,'code'=>intval(wp_remote_retrieve_response_code($res2)),
            'headers'=>wp_remote_retrieve_headers($res2),'body'=>$body2,
        );
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
}







