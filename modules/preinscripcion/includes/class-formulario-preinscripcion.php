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

    private function sanitize_error_text($text, $max_length = 280) {
        if (!is_string($text) || $text === '') {
            return '';
        }

        $clean = $this->remove_utf8_bom($text);
        $clean = wp_strip_all_tags($clean, true);
        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = preg_replace('/\s+/', ' ', (string) $clean);
        $clean = trim((string) $clean);

        if ($clean === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($clean, 'UTF-8') > $max_length) {
                $clean = mb_substr($clean, 0, max(0, $max_length - 3), 'UTF-8') . '...';
            }
        } elseif (strlen($clean) > $max_length) {
            $clean = substr($clean, 0, max(0, $max_length - 3)) . '...';
        }

        return $clean;
    }

    private function response_body_looks_like_html($body) {
        if (!is_string($body) || trim($body) === '') {
            return false;
        }

        return preg_match('/<(?:!doctype|html|head|body|meta|style|script|title|div|p)\b/i', $body) === 1;
    }

    private function is_google_bad_request_response($body, $clean_message = '') {
        $haystack = strtolower((string) $body . ' ' . (string) $clean_message);

        return str_contains($haystack, 'error 400 (bad request)')
            || str_contains($haystack, "that's an error")
            || str_contains($haystack, 'that’s an error')
            || str_contains($haystack, 'malformed or illegal request')
            || str_contains($haystack, 'googlelogo')
            || str_contains($haystack, 'www.google.com/images/errors/robot.png');
    }

    private function extract_webhook_error_text($body, $json = null, $max_length = 280) {
        if (is_array($json) && is_array($json['error'] ?? null) && !empty($json['error']['message'])) {
            return $this->sanitize_error_text((string) $json['error']['message'], $max_length);
        }

        if (is_array($json) && is_string($json['error'] ?? null) && trim((string) $json['error']) !== '') {
            return $this->sanitize_error_text((string) $json['error'], $max_length);
        }

        if (is_array($json) && is_string($json['message'] ?? null) && trim((string) $json['message']) !== '') {
            return $this->sanitize_error_text((string) $json['message'], $max_length);
        }

        return $this->sanitize_error_text((string) $body, $max_length);
    }

    private function get_public_webhook_error_message($status, $body, $json = null) {
        $fallback = 'No pudimos enviar la postulación en este momento. Por favor, intente nuevamente en unos minutos.';
        $message = $this->extract_webhook_error_text($body, $json, 220);

        if ($message === '') {
            return $fallback;
        }

        if ($this->response_body_looks_like_html($body) || $this->is_google_bad_request_response($body, $message)) {
            return $fallback;
        }

        if ((int) $status >= 500) {
            return $fallback;
        }

        return $message;
    }

    private function get_admin_webhook_error_message($status, $body, $json = null) {
        $base = (int) $status > 0
            ? 'El servidor respondió HTTP ' . (int) $status . '.'
            : 'No se pudo completar la prueba del webhook.';
        $message = $this->extract_webhook_error_text($body, $json, 280);

        if ($this->is_google_bad_request_response($body, $message)) {
            return $base . ' El servicio remoto devolvió un 400. Revisá que la URL apunte al endpoint oficial de preinscripciones de la app.';
        }

        if ($this->response_body_looks_like_html($body)) {
            return $base . ' El webhook devolvió HTML en lugar de JSON. Revise la publicación y los permisos del servicio externo.';
        }

        if ($message === '') {
            return $base;
        }

        return $base . ' ' . $message;
    }

    private function get_external_editor_preinscripciones_webhook_url() {
        $external_editor_url = trim((string) get_option('flacso_external_editor_url', 'https://editor-flacso-uy.vercel.app'));
        if ($external_editor_url === '') {
            return '';
        }

        return rtrim($external_editor_url, '/') . '/api/preinscripciones/ofertas';
    }

    private function get_preinscripciones_webhook_candidates() {
        $configured_url = trim((string) get_option('flacso_preinscripciones_webhook_url', ''));
        $editor_url = trim((string) $this->get_external_editor_preinscripciones_webhook_url());
        $has_unified_token = trim((string) get_option('flacso_webhook_token', '')) !== '';

        $candidates = array();

        if ($configured_url !== '') {
            $candidates[] = array(
                'url' => $configured_url,
                'source' => 'configured',
            );
        } elseif ($has_unified_token && $editor_url !== '') {
            $candidates[] = array(
                'url' => $editor_url,
                'source' => 'editor_default',
            );
        }

        $unique = array();
        $seen = array();

        foreach ($candidates as $candidate) {
            $url = trim((string) ($candidate['url'] ?? ''));
            if ($url === '' || isset($seen[$url])) {
                continue;
            }

            $seen[$url] = true;
            $unique[] = array(
                'url' => $url,
                'source' => (string) ($candidate['source'] ?? 'configured'),
            );
        }

        return $unique;
    }

    private function post_preinscripciones_webhook($webhook_url, $body_json, $webhook_token, $timeout = 100) {
        $webhook_headers = array();
        if ($webhook_token !== '') {
            $webhook_headers['X-FLACSO-Webhook-Token'] = $webhook_token;
            $webhook_headers['Authorization'] = 'Bearer ' . $webhook_token;
        }

        return wp_remote_post($webhook_url, array(
            'headers' => array_merge(array(
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept'       => 'application/json',
                'User-Agent'   => 'FLACSO-Uruguay-Form/1.0',
            ), $webhook_headers),
            'body' => $body_json,
            'timeout' => $timeout,
            'redirection' => 3,
            'blocking' => true,
            'httpversion' => '1.1',
            'data_format' => 'body',
        ));
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
        $transient_key = 'flacso_oferta_id_by_page_' . $page_id;
        $cached_val = get_transient($transient_key);
        if ($cached_val !== false) {
            return (int) $cached_val;
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
        $oferta_id = !empty($ids) ? (int) $ids[0] : 0;
        set_transient($transient_key, $oferta_id, DAY_IN_SECONDS);
        return $oferta_id;
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

    private function get_preinscripciones_files_upload_url() {
        $editor_url = $this->get_external_editor_preinscripciones_webhook_url();
        if ($editor_url === '') {
            $configured_url = trim((string) get_option('flacso_preinscripciones_webhook_url', ''));
            if ($configured_url !== '') {
                $base = rtrim(preg_replace('#/api/preinscripciones/ofertas/?$#', '', $configured_url), '/');
                return $base . '/api/preinscripciones/ofertas/files';
            }
            return '';
        }
        return rtrim($editor_url, '/') . '/files';
    }

    private function upload_single_file_to_endpoint($file_data, $field_name, $offer_info, $applicant_info) {
        $webhook_token = sanitize_text_field((string) get_option('flacso_webhook_token', ''));
        $upload_url = $this->get_preinscripciones_files_upload_url();

        if ($upload_url === '') {
            return array('ok' => false, 'error' => 'No hay URL configurada para subida de archivos.');
        }

        $body = array(
            'field' => $field_name,
            'file' => array(
                'name' => $file_data['name'],
                'type' => $file_data['type'],
                'content' => $file_data['content'],
            ),
            'offer' => array(
                'id' => $offer_info['id'],
                'title' => $offer_info['title'],
            ),
            'applicant' => array(
                'nombre1' => $applicant_info['nombre1'],
                'apellido1' => $applicant_info['apellido1'],
                'nombre2' => $applicant_info['nombre2'] ?? '',
                'apellido2' => $applicant_info['apellido2'] ?? '',
                'documento' => $applicant_info['documento'] ?? '',
            ),
        );

        $body_json = wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body_json === false) {
            return array('ok' => false, 'error' => 'Error codificando archivo para subida.');
        }

        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'User-Agent' => 'FLACSO-Uruguay-Form/1.0',
        );
        if ($webhook_token !== '') {
            $headers['X-FLACSO-Webhook-Token'] = $webhook_token;
            $headers['Authorization'] = 'Bearer ' . $webhook_token;
        }

        $response = wp_remote_post($upload_url, array(
            'headers' => $headers,
            'body' => $body_json,
            'timeout' => 120,
            'redirection' => 0,
            'blocking' => true,
            'httpversion' => '1.1',
            'data_format' => 'body',
        ));

        if (is_wp_error($response)) {
            return array('ok' => false, 'error' => $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $json = json_decode($body_raw, true);

        if ($status === 200 && is_array($json) && !empty($json['ok']) && is_array($json['data'])) {
            return array(
                'ok' => true,
                'file_id' => $json['data']['file_id'] ?? '',
                'file_url' => $json['data']['file_url'] ?? '',
                'file_name' => $json['data']['file_name'] ?? $file_data['name'],
                'file_size' => $json['data']['file_size'] ?? null,
            );
        }

        $error_msg = 'Error HTTP ' . $status;
        if (is_array($json) && !empty($json['error']['message'])) {
            $error_msg .= ': ' . $json['error']['message'];
        } elseif (!empty($body_raw)) {
            $error_msg .= ': ' . substr($body_raw, 0, 200);
        }

        if ($status === 200) {
            $error_msg = 'Respuesta inválida del endpoint de archivos';
            if (!empty($body_raw)) {
                $error_msg .= ': ' . substr($body_raw, 0, 200);
            }
        }

        return array('ok' => false, 'error' => $error_msg);
    }

    private function get_php_upload_error_message($error_code) {
        $messages = array(
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el límite configurado por el servidor.',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el límite permitido por el formulario.',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente.',
            UPLOAD_ERR_NO_FILE => 'No se recibió ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo temporal en el servidor.',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida.',
        );

        return $messages[(int) $error_code] ?? 'Error de subida PHP desconocido.';
    }

    private function send_telegram_attachment_failure_notification(
        array $context,
        int $selected_files_count,
        int $uploaded_files_count,
        array $file_upload_errors
    ): void {
        if (!function_exists('fc_send_telegram_message')) {
            return;
        }

        $nombre = trim(
            ($context['nombre1'] ?? '') . ' ' .
            ($context['nombre2'] ?? '') . ' ' .
            ($context['apellido1'] ?? '') . ' ' .
            ($context['apellido2'] ?? '')
        );
        if ($nombre === '') {
            $nombre = 'No especificado';
        }

        $errores = array_map(function($error) {
            return $this->sanitize_error_text((string) $error, 300);
        }, $file_upload_errors);
        $errores = array_values(array_filter($errores));
        if (empty($errores)) {
            $errores[] = 'Sin detalle adicional.';
        }

        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $fecha = current_time('d/m/Y H:i:s');

        $msg = "🚨 <b>Fallo de subida de adjuntos</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "<b>📍 Sitio:</b> " . htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8') . "\n";
        $msg .= "<b>📅 Fecha:</b> " . $fecha . "\n\n";
        $msg .= "<b>🎓 Oferta:</b>\n";
        $msg .= "  <b>ID:</b> " . intval($context['oferta_id'] ?? 0) . "\n";
        $msg .= "  <b>Título:</b> " . htmlspecialchars((string) ($context['titulo_posgrado'] ?? 'No especificado'), ENT_QUOTES, 'UTF-8') . "\n\n";
        $msg .= "<b>👤 Postulante:</b>\n";
        $msg .= "  <b>Nombre:</b> " . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . "\n";
        $msg .= "  <b>Email:</b> " . htmlspecialchars((string) ($context['correo'] ?? 'No especificado'), ENT_QUOTES, 'UTF-8') . "\n";
        $msg .= "  <b>Teléfono:</b> " . htmlspecialchars((string) ($context['telefono'] ?? 'No especificado'), ENT_QUOTES, 'UTF-8') . "\n\n";
        $msg .= "<b>📎 Adjuntos:</b>\n";
        $msg .= "  <b>Seleccionados:</b> " . $selected_files_count . "\n";
        $msg .= "  <b>Subidos:</b> " . $uploaded_files_count . "\n\n";
        $msg .= "<b>❌ Errores concretos:</b>\n";
        $msg .= "<pre>" . htmlspecialchars(implode("\n", $errores), ENT_QUOTES, 'UTF-8') . "</pre>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "⚠️ <i>La preinscripción no fue registrada.</i>";

        fc_send_telegram_message($msg);
    }

    public function procesar_formulario() {
        $this->flush_output_buffers();
        $this->configurar_limites_archivos();
        set_time_limit(300);
        ini_set('memory_limit', '256M');
        ini_set('max_execution_time', 300);

        $webhook_token = sanitize_text_field((string) get_option('flacso_webhook_token', ''));
        $webhook_candidates = $this->get_preinscripciones_webhook_candidates();
        if (empty($webhook_candidates)) {
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

        $documentacion_completa = sanitize_text_field($_POST['documentacion_completa'] ?? '');
        if ($documentacion_completa !== 'No') {
            if (!$this->archivo_obligatorio_presente('carta_motivacion')) {
                $this->send_json_error('La carta de motivación es obligatoria para todos los posgrados.');
            }
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

        // Archivos — subir uno por uno al endpoint de archivos, luego enviar solo referencias
        $archivos_con_drive = array();
        $max_file_size = 3 * 1024 * 1024;
        $file_upload_errors = array();
        $selected_files_count = 0;
        $uploaded_files_count = 0;
        $offer_info = array(
            'id' => $oferta_id,
            'title' => $titulo_posgrado,
        );
        $applicant_info = array(
            'nombre1' => $datos_basicos['nombre1'] ?? '',
            'apellido1' => $datos_basicos['apellido1'] ?? '',
            'nombre2' => $datos_basicos['nombre2'] ?? '',
            'apellido2' => $datos_basicos['apellido2'] ?? '',
            'documento' => $datos_basicos['documento'] ?? '',
        );

        if (!empty($_FILES)) {
            foreach ($_FILES as $campo => $file) {
                if (!$es_maestria && in_array($campo, array('carta_recomendacion_1','carta_recomendacion_2'), true)) { continue; }

                $collectFile = function($name, $type, $tmp, $error) use (&$archivos_con_drive, &$file_upload_errors, &$selected_files_count, &$uploaded_files_count, $campo, $max_file_size, $offer_info, $applicant_info) {
                    $name = (string) $name;
                    if ($name === '') {
                        return;
                    }

                    $selected_files_count++;

                    if ($error !== UPLOAD_ERR_OK) {
                        $error_msg = $this->get_php_upload_error_message($error) . " Código: $error.";
                        $file_upload_errors[] = "$campo/$name: $error_msg";
                        error_log("[Preinscripcion] Error subiendo archivo '$campo/$name': $error_msg");
                        return;
                    }
                    if (!is_string($tmp) || $tmp === '' || !file_exists($tmp)) {
                        $error_msg = 'Archivo temporal inexistente.';
                        $file_upload_errors[] = "$campo/$name: $error_msg";
                        error_log("[Preinscripcion] $error_msg Ruta: $tmp");
                        return;
                    }
                    $file_size = filesize($tmp);
                    if ($file_size === false) {
                        $error_msg = 'No se pudo determinar el tamaño del archivo.';
                        $file_upload_errors[] = "$campo/$name: $error_msg";
                        error_log("[Preinscripcion] $error_msg Ruta: $tmp");
                        return;
                    }
                    if ($file_size > $max_file_size) {
                        $error_msg = 'Archivo excede el tamaño máximo de 3 MB. Tamaño: ' . $file_size . ' bytes.';
                        $file_upload_errors[] = "$campo/$name: $error_msg";
                        error_log("[Preinscripcion] $error_msg");
                        return;
                    }
                    $content = file_get_contents($tmp);
                    if ($content === false) {
                        $error_msg = 'No se pudo leer el archivo temporal.';
                        $file_upload_errors[] = "$campo/$name: $error_msg";
                        error_log("[Preinscripcion] $error_msg Ruta: $tmp");
                        return;
                    }
                    $b64_content = base64_encode($content);
                    $sanitized_name = sanitize_file_name($name);
                    $sanitized_type = $type ?: 'application/octet-stream';

                    error_log("[Preinscripcion] Subiendo archivo '$campo/$name' ($file_size bytes) al endpoint de archivos...");
                    $upload_result = $this->upload_single_file_to_endpoint(
                        array(
                            'name' => $sanitized_name,
                            'type' => $sanitized_type,
                            'content' => $b64_content,
                        ),
                        $campo,
                        $offer_info,
                        $applicant_info
                    );

                    if ($upload_result['ok']) {
                        $archivos_con_drive[$campo][] = array(
                            'name' => $sanitized_name,
                            'type' => $sanitized_type,
                            'drive_url' => $upload_result['file_url'],
                            'drive_id' => $upload_result['file_id'],
                            'size' => $file_size,
                        );
                        $uploaded_files_count++;
                        error_log("[Preinscripcion] Archivo '$campo/$name' subido a Drive: " . $upload_result['file_url']);
                    } else {
                        $error_msg = $upload_result['error'] ?? 'Error desconocido';
                        $file_upload_errors[] = "$campo/$name: $error_msg";
                        error_log("[Preinscripcion] Error subiendo archivo '$campo/$name': $error_msg");
                    }
                };

                if (is_array($file['name'])) {
                    foreach ($file['name'] as $i => $name) {
                        if (!empty($name)) { $collectFile($name, $file['type'][$i] ?? '', $file['tmp_name'][$i] ?? '', $file['error'][$i] ?? UPLOAD_ERR_NO_FILE); }
                    }
                } elseif (!empty($file['name'])) {
                    $collectFile($file['name'], $file['type'] ?? '', $file['tmp_name'] ?? '', $file['error'] ?? UPLOAD_ERR_NO_FILE);
                }
            }
        }

        error_log("[Preinscripcion] Archivos seleccionados: $selected_files_count, subidos: $uploaded_files_count");

        if ($documentacion_completa === 'No' && empty($datos_basicos['documentacion_faltante'])) {
            if ($uploaded_files_count === 0) {
                $datos_basicos['documentacion_faltante'] = 'No se adjuntaron archivos en el formulario. La persona indicó que falta documentación.';
            } else {
                $datos_basicos['documentacion_faltante'] = 'La persona indicó que falta documentación, pero no especificó cuál.';
            }
            error_log('[Preinscripcion] documentacion_faltante autocompletada: ' . $datos_basicos['documentacion_faltante']);
        }

        if ($selected_files_count > 0 && $uploaded_files_count < $selected_files_count) {
            $file_upload_errors[] = "Conteo inconsistente: seleccionados=$selected_files_count, subidos=$uploaded_files_count.";
        }

        if ($documentacion_completa === 'Si' && $uploaded_files_count === 0) {
            $file_upload_errors[] = 'Documentación completa declarada, pero no se subió ningún archivo.';
        }

        if (!empty($file_upload_errors)) {
            $attachment_error_message = 'No pudimos subir tus archivos. Revisá que cada archivo pese menos de 3 MB y volvé a intentar. La preinscripción no fue registrada.';
            $file_upload_errors = array_values(array_unique($file_upload_errors));
            $error_detail = implode('; ', $file_upload_errors);
            error_log("[Preinscripcion] Preinscripción detenida por fallo de adjuntos: $error_detail");
            $this->send_telegram_attachment_failure_notification(
                array(
                    'oferta_id' => $oferta_id,
                    'titulo_posgrado' => $titulo_posgrado,
                    'nombre1' => $datos_basicos['nombre1'] ?? '',
                    'nombre2' => $datos_basicos['nombre2'] ?? '',
                    'apellido1' => $datos_basicos['apellido1'] ?? '',
                    'apellido2' => $datos_basicos['apellido2'] ?? '',
                    'correo' => $datos_basicos['correo'] ?? '',
                    'telefono' => $cel_e164 ?: ($datos_basicos['celular'] ?? ''),
                ),
                $selected_files_count,
                $uploaded_files_count,
                $file_upload_errors
            );
            $this->send_json_error($attachment_error_message);
        }

        // Capturar metadata de la solicitud
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $posted_attribution = array();
        if (function_exists('fc_sanitize_campaign_attribution_payload')) {
            $posted_attribution = fc_sanitize_campaign_attribution_payload(
                wp_unslash($_POST),
                isset($_POST['landing_url']) ? esc_url_raw(wp_unslash($_POST['landing_url'])) : (function_exists('fc_get_current_request_url') ? fc_get_current_request_url() : ''),
                isset($_POST['referrer_url']) ? esc_url_raw(wp_unslash($_POST['referrer_url'])) : (wp_get_referer() ?: '')
            );
        }
        $url_referer = $posted_attribution['referrer_url'] ?? '';
        if ($url_referer === '') {
            $url_referer = wp_get_referer() ?: '';
        }
        $campaign_id = $posted_attribution['campaign_external_id'] ?? '';

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
            'archivos' => $archivos_con_drive,
            'meta' => array(
                'timestamp_client' => current_time('c'),
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'host_post_id' => $id_pagina,
                'url_referer' => $url_referer,
                'origen' => 'wordpress_formulario_preinscripcion',
                'campaign_id' => $campaign_id,
                'attribution' => array_filter(array(
                    'provider' => $posted_attribution['campaign_provider'] ?? '',
                    'source' => $posted_attribution['campaign_source'] ?? '',
                    'medium' => $posted_attribution['campaign_medium'] ?? '',
                    'name' => $posted_attribution['campaign_name'] ?? '',
                    'external_id' => $posted_attribution['campaign_external_id'] ?? '',
                    'content' => $posted_attribution['campaign_content'] ?? '',
                    'term' => $posted_attribution['campaign_term'] ?? '',
                )),
            ) + $posted_attribution
        );

        // Log seguro del tamaño del payload antes del fetch
        $body_json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body_json === false) { $this->send_json_error('Error codificando los datos del formulario.'); }
        $payload_size_mb = strlen($body_json) / 1024 / 1024;
        error_log("[Preinscripcion] Payload JSON listo para enviar: " . sprintf('%.2f', $payload_size_mb) . " MB, oferta_id=$oferta_id, archivos=" . count($archivos_con_drive, COUNT_RECURSIVE));

        $last_status = 0;
        $last_body = '';
        $last_json = null;
        $last_error_msg = 'Error del servidor. Por favor, contacte a inscripciones@flacso.edu.uy';
        $last_wp_error_message = '';

        foreach ($webhook_candidates as $candidate) {
            $candidate_url = (string) ($candidate['url'] ?? '');
            $candidate_source = (string) ($candidate['source'] ?? 'configured');
            $result = $this->post_preinscripciones_webhook($candidate_url, $body_json, $webhook_token, 100);

            if (is_wp_error($result)) {
                $last_wp_error_message = $result->get_error_message();
                error_log('Error en webhook preinscripciones [' . $candidate_source . ']: ' . $last_wp_error_message);
                continue;
            }

            $status = wp_remote_retrieve_response_code($result);
            $body = wp_remote_retrieve_body($result);
            $json = json_decode($body, true);
            $last_status = (int) $status;
            $last_body = (string) $body;
            $last_json = $json;

            error_log("Respuesta webhook preinscripciones [$candidate_source] - Status: $status, Body: " . substr($body, 0, 500));

            if ($status >= 200 && $status < 300 && is_array($json) && ($json['ok'] ?? false)) {
                $this->send_json_success(array(
                    'message' => 'Preinscripción enviada correctamente.',
                    'editor_response' => $json,
                ));
            }

            // Manejo específico para HTTP 413 Payload Too Large
            if ($status === 413) {
                $error_detail = 'El payload es demasiado grande para el servidor remoto (413 Request Entity Too Large). ';
                $error_detail .= 'Los archivos se subieron individualmente a Drive correctamente, pero el payload final (' . sprintf('%.2f', $payload_size_mb) . ' MB) excede el límite del servidor. ';
                $error_detail .= 'Por favor, contacte a inscripciones@flacso.edu.uy para completar la preinscripción de forma manual.';
                $error_msg_413 = 'El sistema de archivos es demasiado grande. ';
                $error_msg_413 .= 'Por favor, contacte a inscripciones@flacso.edu.uy para completar su postulación manualmente. Código de seguimiento: oferta-' . $oferta_id;

                $this->send_telegram_error_notification(
                    'HTTP 413 Payload Too Large',
                    $error_detail . ' | Datos del alumno: ' . ($datos_basicos['nombre1'] ?? '') . ' ' . ($datos_basicos['apellido1'] ?? '') . ' - ' . ($datos_basicos['correo'] ?? ''),
                    $payload
                );
                $this->send_json_error($error_msg_413);
            }

            if (is_array($json) && is_array($json['error'] ?? null) && !empty($json['error']['message'])) {
                $last_error_msg = (string) $json['error']['message'];
            } elseif (is_array($json) && is_string($json['message'] ?? null) && $json['message'] !== '') {
                $last_error_msg = $json['message'];
            } elseif ($body) {
                $last_error_msg = (string) $body;
            }
        }

        if ($last_wp_error_message !== '' && $last_status === 0) {
            $this->send_telegram_error_notification('WP_Error / Fallo de Red', $last_wp_error_message, $payload);
            $this->send_json_error('Error de conexión con el servidor. Por favor, intente nuevamente.');
        }

        $this->send_telegram_error_notification("HTTP $last_status", $last_error_msg, $payload);
        $this->send_json_error($this->get_public_webhook_error_message($last_status, $last_body, $last_json));
    }
    


    public function procesar_test_webhook() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos suficientes.', 'flacso-uruguay'));
        }
        if (!isset($_POST['flacso_preinscripciones_test_webhook_nonce']) || !wp_verify_nonce(wp_unslash($_POST['flacso_preinscripciones_test_webhook_nonce']), 'flacso_preinscripciones_test_webhook')) {
            wp_die(esc_html__('Solicitud no válida.', 'flacso-uruguay'));
        }

        $result = array('ok' => false, 'code' => 0, 'error' => '', 'message' => '');
        $webhook_candidates = $this->get_preinscripciones_webhook_candidates();

        if (empty($webhook_candidates)) {
            $result['error'] = 'No se ha configurado la URL del webhook.';
        } else {
            $webhook_token = sanitize_text_field((string) get_option('flacso_webhook_token', ''));

            $payload = array(
                'test' => true,
                'origen' => 'wp_preinscripciones_test',
                'timestamp' => current_time('mysql'),
                'posgrado' => array(
                    'id' => 9999,
                    'titulo' => 'Prueba de Conectividad Webhook',
                    'es_maestria' => 'no'
                ),
                'datos' => array(
                    'correo' => 'test@flacso.edu.uy',
                    'nombre1' => 'Test',
                    'apellido1' => 'Test',
                    'celular' => '00000000',
                    'pais_residencia' => 'Uruguay'
                ),
                'archivos' => array(),
                'meta' => array(
                    'timestamp_client' => current_time('c'),
                    'origen' => 'wp_preinscripciones_test'
                )
            );
            $body_json = wp_json_encode($payload);

            foreach ($webhook_candidates as $candidate) {
                $response = $this->post_preinscripciones_webhook((string) ($candidate['url'] ?? ''), $body_json, $webhook_token, 15);

                if (is_wp_error($response)) {
                    $result['error'] = 'Error de conexión: ' . $response->get_error_message();
                    continue;
                }

                $status = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $json = json_decode($body, true);
                
                $result['code'] = $status;
                if ($status >= 200 && $status < 300) {
                    $result['ok'] = true;
                    $result['error'] = '';
                    $result['message'] = '';
                    break;
                }

                $result['message'] = $this->get_admin_webhook_error_message($status, $body, $json);
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

        $webhook_candidates = $this->get_preinscripciones_webhook_candidates();
        $webhook_url = (string) (($webhook_candidates[0]['url'] ?? ''));

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

        $error_clean = $this->sanitize_error_text($error_msg, 400);

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
