<?php
if (!defined('ABSPATH')) { exit; }

trait FLACSO_Formulario_Preinscripcion_Templates {
    
    /**
     * Registra los templates personalizados
     */
    public function registrar_templates() {
        add_action('template_redirect', array($this, 'validar_ruta_virtual_canonica'), 0);
        add_filter('template_include', array($this, 'cargar_template_preinscripcion'), 99);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets_en_templates'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets_en_shortcode'));
        add_shortcode('formulario_preinscripcion', array($this, 'render_shortcode_preinscripcion'));
    }

    /**
     * Canonicaliza rutas virtuales legacy y bloquea rutas trash/old antes de renderizar.
     */
    public function validar_ruta_virtual_canonica() {
        $tipo_ruta = $this->obtener_tipo_ruta_virtual_activa();

        if ($tipo_ruta === '') {
            return;
        }

        $request_path = $this->obtener_request_path_virtual_actual();
        $has_legacy_markers = $this->ruta_virtual_tiene_marcadores_legacy($request_path);
        $queried_post = get_queried_object();

        if (!($queried_post instanceof WP_Post)) {
            if ($has_legacy_markers) {
                $fallback_url = $this->obtener_url_virtual_alternativa_desde_path($request_path, $tipo_ruta);

                if ($this->debe_redirigir_a_url_virtual($request_path, $fallback_url)) {
                    wp_safe_redirect($this->preservar_query_args_actuales($fallback_url), 301);
                    exit;
                }

                $this->renderizar_respuesta_virtual_invalida(410);
            }

            return;
        }

        $canonical_url = $this->obtener_url_virtual_canonica_para_post($queried_post, $tipo_ruta);

        if ($this->debe_redirigir_a_url_virtual($request_path, $canonical_url)) {
            wp_safe_redirect($this->preservar_query_args_actuales($canonical_url), 301);
            exit;
        }

        if ($has_legacy_markers) {
            $fallback_url = $this->obtener_url_virtual_alternativa_desde_path($request_path, $tipo_ruta);

            if ($this->debe_redirigir_a_url_virtual($request_path, $fallback_url)) {
                wp_safe_redirect($this->preservar_query_args_actuales($fallback_url), 301);
                exit;
            }

            $this->renderizar_respuesta_virtual_invalida(410);
        }

        if (!$this->post_virtual_es_publicable($queried_post)) {
            $this->renderizar_respuesta_virtual_invalida(410);
        }
    }
    
    /**
     * Enqueue assets para el template de preinscripción
     */
    public function enqueue_assets_en_templates() {
        global $post;
        if (!$post) return;

        // Cargar solo en la URL virtual /.../preinscripcion/.
        if (!get_query_var('es_preinscripcion')) return;


        
        // Bootstrap e íconos solo para el template virtual.
        $this->enqueue_assets();

        $info = $this->obtener_info_posgrado();
        $this->enqueue_assets_formulario($info);
    }

    /**
     * Enqueue assets cuando el shortcode legacy esta presente en una pagina.
     */
    public function enqueue_assets_en_shortcode() {
        if (!is_singular('page')) {
            return;
        }

        global $post;
        if (!$post || !has_shortcode((string) $post->post_content, 'formulario_preinscripcion')) {
            return;
        }

        $this->enqueue_assets();
        $info = $this->obtener_info_posgrado_para_template((int) $post->ID);
        $this->enqueue_assets_formulario($info);
    }
    
    /**
     * Carga el template para páginas virtuales (preinscripción y carta)
     */
    public function cargar_template_preinscripcion($template) {
        global $post, $wp_query;
        
        // Verificar si es una URL virtual de preinscripción
        $es_preinscripcion = get_query_var('es_preinscripcion');
        $es_carta = get_query_var('es_carta');
        
        if (($es_preinscripcion || $es_carta) && (is_singular('page') || is_singular('oferta-academica')) && $post) {
            if ($es_preinscripcion) {
                // Lógica de preinscripción
                // Always load the template; the template will handle displaying the closed message if not active
                $overridden = locate_template(array('preinscripcion-template.php'));
                if ($overridden !== '') {
                    add_filter('wp_title', array($this, 'modificar_titulo_preinscripcion'), 10, 3);
                    add_filter('document_title_parts', array($this, 'modificar_titulo_parts_preinscripcion'));
                    add_action('wp_head', array($this, 'add_og_meta_tags'), 5);
                    return $overridden;
                }

                $custom_template = plugin_dir_path(dirname(__FILE__)) . 'templates/preinscripcion-template.php';
                if (file_exists($custom_template)) {
                    add_filter('wp_title', array($this, 'modificar_titulo_preinscripcion'), 10, 3);
                    add_filter('document_title_parts', array($this, 'modificar_titulo_parts_preinscripcion'));
                    add_action('wp_head', array($this, 'add_og_meta_tags'), 5);
                    return $custom_template;
                }
            } elseif ($es_carta) {
                // Lógica de carta de presentación
                $overridden = locate_template(array('carta-template.php'));
                if ($overridden !== '') {
                    add_filter('wp_title', array($this, 'modificar_titulo_carta'), 10, 3);
                    add_filter('document_title_parts', array($this, 'modificar_titulo_parts_carta'));
                    add_action('wp_head', array($this, 'add_og_meta_tags'), 5);
                    return $overridden;
                }

                $custom_template = plugin_dir_path(dirname(__FILE__)) . 'templates/carta-template.php';
                if (file_exists($custom_template)) {
                    add_filter('wp_title', array($this, 'modificar_titulo_carta'), 10, 3);
                    add_filter('document_title_parts', array($this, 'modificar_titulo_parts_carta'));
                    add_action('wp_head', array($this, 'add_og_meta_tags'), 5);
                    return $custom_template;
                }
            }
        }
        
        return $template;
    }

    private function obtener_tipo_ruta_virtual_activa() {
        if (get_query_var('es_carta')) {
            return 'carta';
        }

        if (get_query_var('es_preinscripcion')) {
            return 'preinscripcion';
        }

        return '';
    }

    private function obtener_request_path_virtual_actual() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = is_string($request_uri) ? (string) wp_parse_url($request_uri, PHP_URL_PATH) : '';

        return $this->normalizar_path_virtual($path);
    }

    private function normalizar_path_virtual($path) {
        $path = rawurldecode((string) $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = is_string($path) ? $path : (string) $path;

        return trim($path, '/');
    }

    private function ruta_virtual_tiene_marcadores_legacy($path) {
        if (!is_string($path) || $path === '') {
            return false;
        }

        foreach (array_filter(explode('/', $path), 'strlen') as $segment) {
            if (preg_match('/__trashed(?:-\d+)?$/i', $segment)) {
                return true;
            }

            if (preg_match('/(?:-|_)old$/i', $segment)) {
                return true;
            }
        }

        return false;
    }

    private function post_virtual_es_publicable($post) {
        if (!($post instanceof WP_Post)) {
            return false;
        }

        if ($post->post_status === 'publish') {
            return true;
        }

        return $post->post_status === 'private' && current_user_can('read_post', $post->ID);
    }

    private function obtener_url_virtual_canonica_para_post($post, $tipo_ruta) {
        $tipo_ruta = $tipo_ruta === 'carta' ? 'carta' : 'preinscripcion';
        $canonical_post = $this->resolver_post_canonico_virtual($post);

        if (!($canonical_post instanceof WP_Post) || !$this->post_virtual_es_publicable($canonical_post)) {
            return '';
        }

        $permalink = get_permalink($canonical_post);

        if (!is_string($permalink) || $permalink === '') {
            return '';
        }

        return trailingslashit($permalink) . $tipo_ruta . '/';
    }

    private function resolver_post_canonico_virtual($post) {
        if (!($post instanceof WP_Post)) {
            return null;
        }

        if ($post->post_type === 'page' && class_exists('Oferta_Page_Adapter') && method_exists('Oferta_Page_Adapter', 'get_oferta_id_by_page_id')) {
            $offer_id = Oferta_Page_Adapter::get_oferta_id_by_page_id((int) $post->ID);

            if (!empty($offer_id)) {
                $offer_post = get_post((int) $offer_id);

                if ($offer_post instanceof WP_Post && $this->post_virtual_es_publicable($offer_post)) {
                    return $offer_post;
                }
            }
        }

        return $post;
    }

    private function obtener_url_virtual_alternativa_desde_path($request_path, $tipo_ruta) {
        if (!is_string($request_path) || $request_path === '') {
            return '';
        }

        $suffix = '/' . $tipo_ruta;

        if (substr($request_path, -strlen($suffix)) !== $suffix) {
            return '';
        }

        $base_path = substr($request_path, 0, -strlen($suffix));
        $segments = array_values(array_filter(explode('/', trim((string) $base_path, '/')), 'strlen'));

        if (empty($segments)) {
            return '';
        }

        $normalized_segments = array();

        foreach ($segments as $segment) {
            $clean_segment = preg_replace('/__trashed(?:-\d+)?$/i', '', $segment);
            $clean_segment = preg_replace('/(?:-|_)old$/i', '', (string) $clean_segment);
            $clean_segment = trim((string) $clean_segment);

            if ($clean_segment !== '') {
                $normalized_segments[] = $clean_segment;
            }
        }

        $candidate_base_path = implode('/', $normalized_segments);

        if ($candidate_base_path === '' || $candidate_base_path === trim((string) $base_path, '/')) {
            return '';
        }

        $candidate_post_id = url_to_postid(home_url('/' . $candidate_base_path . '/'));

        if ($candidate_post_id <= 0) {
            return '';
        }

        $candidate_post = get_post($candidate_post_id);

        if (!($candidate_post instanceof WP_Post)) {
            return '';
        }

        return $this->obtener_url_virtual_canonica_para_post($candidate_post, $tipo_ruta);
    }

    private function debe_redirigir_a_url_virtual($request_path, $target_url) {
        if (!$this->request_virtual_permite_redirect()) {
            return false;
        }

        if (!is_string($target_url) || $target_url === '') {
            return false;
        }

        $target_path = $this->normalizar_path_virtual((string) wp_parse_url($target_url, PHP_URL_PATH));

        return $target_path !== '' && $target_path !== $request_path;
    }

    private function request_virtual_permite_redirect() {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
        return in_array($method, array('GET', 'HEAD'), true);
    }

    private function preservar_query_args_actuales($url) {
        if (empty($_GET)) {
            return $url;
        }

        return add_query_arg(wp_unslash($_GET), $url);
    }

    private function renderizar_respuesta_virtual_invalida($status_code = 410) {
        $status_code = (int) $status_code;

        if ($status_code < 400) {
            $status_code = 410;
        }

        status_header($status_code);
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

        $titulo = $status_code === 410 ? 'URL no disponible' : 'Pagina no encontrada';
        $mensaje = $status_code === 410
            ? 'La direccion solicitada ya no esta disponible.'
            : 'No encontramos la direccion solicitada.';

        echo '<!doctype html><html lang="es"><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '">';
        echo '<meta name="robots" content="noindex, nofollow">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . esc_html($titulo) . ' | ' . esc_html(get_bloginfo('name')) . '</title>';
        echo '<style>body{margin:0;font-family:Arial,sans-serif;background:#f6f8fb;color:#17325c}main{max-width:720px;margin:10vh auto;padding:32px 24px}section{background:#fff;border:1px solid #d9e2f0;border-radius:18px;padding:32px;box-shadow:0 20px 60px rgba(23,50,92,.08)}h1{margin:0 0 12px;font-size:2rem}p{margin:0 0 16px;line-height:1.6}a{color:#17325c;font-weight:700}</style>';
        echo '</head><body><main><section>';
        echo '<h1>' . esc_html($titulo) . '</h1>';
        echo '<p>' . esc_html($mensaje) . '</p>';
        echo '<p><a href="' . esc_url(home_url('/')) . '">Ir al inicio</a></p>';
        echo '</section></main></body></html>';
        exit;
    }
    
    /**
     * Modifica el título para páginas de preinscripción
     */
    public function modificar_titulo_preinscripcion($title, $sep = '', $seplocation = '') {
        global $post;
        if ($post) {
            return 'Preinscripción - ' . get_the_title($post->ID) . ' ' . $sep . ' ' . get_bloginfo('name');
        }
        return $title;
    }

    public function modificar_titulo_carta($title, $sep = '', $seplocation = '') {
        global $post;
        if ($post) {
            return 'Carta de Presentación - ' . get_the_title($post->ID) . ' ' . $sep . ' ' . get_bloginfo('name');
        }
        return $title;
    }
    
    /**
     * Modifica las partes del título del documento
     */
    public function modificar_titulo_parts_preinscripcion($title_parts) {
        global $post;
        if ($post) {
            $title_parts['title'] = 'Preinscripción - ' . get_the_title($post->ID);
        }
        return $title_parts;
    }

    public function modificar_titulo_parts_carta($title_parts) {
        global $post;
        if ($post) {
            $title_parts['title'] = 'Carta de Presentación - ' . get_the_title($post->ID);
        }
        return $title_parts;
    }
    
    /**
     * Agrega etiquetas Open Graph para las páginas virtuales de preinscripción
     */
    public function add_og_meta_tags() {
        global $post;
        if (!$post) return;
        
        $info_posgrado = $this->obtener_info_posgrado_para_template($post->ID);
        $es_carta = get_query_var('es_carta');
        $prefix = $es_carta ? 'Carta de Presentación - ' : 'Preinscripción - ';
        $desc_prefix = $es_carta ? 'Sube tu carta de presentación para ' : 'Completa el formulario de preinscripción para ';
        
        $titulo = $prefix . $info_posgrado['titulo_posgrado'];
        $url = home_url(add_query_arg(array(), $GLOBALS['wp']->request));
        $imagen_url = $info_posgrado['imagen_destacada'];
        $descripcion = $desc_prefix . $info_posgrado['titulo_posgrado'];
        
        echo '<meta property="og:type" content="website" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($titulo) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($descripcion) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        if ($imagen_url) {
            echo '<meta property="og:image" content="' . esc_url($imagen_url) . '" />' . "\n";
            echo '<meta property="og:image:width" content="1200" />' . "\n";
            echo '<meta property="og:image:height" content="630" />' . "\n";
        }
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($titulo) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($descripcion) . '" />' . "\n";
        if ($imagen_url) {
            echo '<meta name="twitter:image" content="' . esc_url($imagen_url) . '" />' . "\n";
        }
    }
    
    /**
     * Obtiene la ruta del template personalizado
     */
    private function obtener_ruta_template() {
        return plugin_dir_path(dirname(__FILE__)) . 'templates/preinscripcion-template.php';
    }
    
    /**
     * Renderiza el contenido del formulario de preinscripción
     */
    public function render_template_preinscripcion() {
        global $post;
        
        // Para páginas virtuales, el $post es la página padre
        // No hay página hijo real, así que obtenemos el ID del padre directamente
        $pagina_padre_id = $post->ID;
        
        if (!$pagina_padre_id) {
            echo '<div class="error"><p>Error: No se pudo determinar el programa de posgrado.</p></div>';
            return;
        }
        
        $info_posgrado = $this->obtener_info_posgrado_para_template($pagina_padre_id);
        
        ?>
        <div class="flacso-preinscripciones-container">
            <?php $this->render_hero_header($info_posgrado); ?>

            <?php if (!empty($info_posgrado['preinscripcion_cerrada'])): ?>
                <?php $this->render_aviso_preinscripciones_cerradas($info_posgrado); ?>
            <?php else: ?>
                <div class="container" style="margin: 40px auto;">
                    <div class="row justify-content-center">
                        <div class="col-12 col-lg-8">
                            <div class="flacso-formulario-card">
                                <div class="flacso-formulario-body">
                                    <form id="flacso-formulario-preinscripcion" class="needs-validation" enctype="multipart/form-data" novalidate>
                                        <?php
                                        $this->render_campos_ocultos($info_posgrado);
                                        $this->render_seccion_correo();
                                        $this->render_seccion_info_personal();
                                        $this->render_seccion_contacto();
                                        $this->render_seccion_academica($info_posgrado);
                                        $this->render_seccion_documentacion($info_posgrado);
                                        
                                        // Cartas de recomendación solo para maestrías
                                        if ($info_posgrado['es_maestria']) { 
                                            $this->render_seccion_cartas_recomendacion(); 
                                        }
                                        
                                        $this->render_seccion_adicional();
                                        $this->render_boton_envio();
                                        ?>
                                    </form>

                                    <div id="flacso-resultado-envio" class="flacso-resultado-area mt-4" aria-live="polite" role="status"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <script>
            (function() {
                if (typeof window.flacsoMetaTrack !== 'function') return;
                try {
                    window.flacsoMetaTrack('ViewContent', {
                        content_name: <?php echo wp_json_encode((string) $info_posgrado['titulo_posgrado']); ?>,
                        content_category: 'oferta_academica',
                        content_ids: ['oferta-' + <?php echo wp_json_encode((string) $info_posgrado['id_posgrado']); ?>],
                        flacso_stage: 'formulario_preinscripcion'
                    });
                } catch (e) {}
            })();
            </script>
        </div>
        <?php
    }

    /**
     * Shortcode legacy: [formulario_preinscripcion]
     */
    public function render_shortcode_preinscripcion($atts = array()) {
        global $post;

        if (!$post || !($post instanceof WP_Post)) {
            return '';
        }

        $info_posgrado = $this->obtener_info_posgrado_para_template((int) $post->ID);

        ob_start();
        ?>
        <div class="flacso-preinscripciones-container flacso-preinscripcion-shortcode">
            <?php $this->render_hero_header($info_posgrado); ?>

            <?php if (!empty($info_posgrado['preinscripcion_cerrada'])): ?>
                <?php $this->render_aviso_preinscripciones_cerradas($info_posgrado); ?>
            <?php else: ?>
                <div class="container" style="margin: 40px auto;">
                    <div class="row justify-content-center">
                        <div class="col-12 col-lg-8">
                            <div class="flacso-formulario-card">
                                <div class="flacso-formulario-body">
                                    <form id="flacso-formulario-preinscripcion" class="needs-validation" enctype="multipart/form-data" novalidate>
                                        <?php
                                        $this->render_campos_ocultos($info_posgrado);
                                        $this->render_seccion_correo();
                                        $this->render_seccion_info_personal();
                                        $this->render_seccion_contacto();
                                        $this->render_seccion_academica($info_posgrado);
                                        $this->render_seccion_documentacion($info_posgrado);

                                        if ($info_posgrado['es_maestria']) {
                                            $this->render_seccion_cartas_recomendacion();
                                        }

                                        $this->render_seccion_adicional();
                                        $this->render_boton_envio();
                                        ?>
                                    </form>

                                    <div id="flacso-resultado-envio" class="flacso-resultado-area mt-4" aria-live="polite" role="status"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <script>
            (function() {
                if (typeof window.flacsoMetaTrack !== 'function') return;
                try {
                    window.flacsoMetaTrack('ViewContent', {
                        content_name: <?php echo wp_json_encode((string) $info_posgrado['titulo_posgrado']); ?>,
                        content_category: 'oferta_academica',
                        content_ids: ['oferta-' + <?php echo wp_json_encode((string) $info_posgrado['id_posgrado']); ?>],
                        flacso_stage: 'formulario_preinscripcion'
                    });
                } catch (e) {}
            })();
            </script>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Extrae un número float de una cadena de texto que contiene un precio.
     */
    private function extraer_numero_desde_texto_precio($str) {
        if (!is_string($str)) {
            return null;
        }
        $str = strip_tags($str);
        $str = str_replace(' ', '', $str);
        
        // Si tiene formato de decimales con coma al final (ej: 15.000,00 o 15000,00)
        if (preg_match('/(\d+[\.,]\d+)[\.,](\d{2})$/', $str, $matches)) {
            $number_part = str_replace(array('.', ','), '', $matches[1]);
            return (float) ($number_part . '.' . $matches[2]);
        }
        
        // De lo contrario, remueve todo lo que no sea dígito
        $digits = preg_replace('/[^\d]/', '', $str);
        return $digits !== '' ? (float) $digits : null;
    }

    /**
     * Obtiene el precio y divisa de una oferta académica de forma heurística.
     */
    private function obtener_precio_y_divisa_oferta($oferta_id) {
        $oferta_id = (int) $oferta_id;
        if ($oferta_id <= 0) {
            return null;
        }

        $precios_filas_str = '';
        $mostrar_usd = false;

        $tabla_precio_id = (int) get_post_meta($oferta_id, 'tabla_precio_id', true);
        if ($tabla_precio_id > 0) {
            $precios_filas_str = get_post_meta($tabla_precio_id, 'precios_filas', true);
            $mostrar_usd = get_post_meta($tabla_precio_id, 'mostrar_precios_dolares', true) === '1';
        } else {
            $precios_filas_str = get_post_meta($oferta_id, 'precios_filas', true);
            $mostrar_usd = get_post_meta($oferta_id, 'mostrar_precios_dolares', true) === '1';
        }

        if (empty($precios_filas_str)) {
            return null;
        }

        $rows = json_decode($precios_filas_str, true);
        if (!is_array($rows) || empty($rows)) {
            $rows = json_decode(wp_unslash($precios_filas_str), true);
            if (!is_array($rows) || empty($rows)) {
                return null;
            }
        }

        $selected_row = null;

        // Fase 1: Buscar concepto que contenga "total" (case-insensitive)
        foreach ($rows as $row) {
            $concept = isset($row['concept']) ? mb_strtolower($row['concept']) : '';
            if (strpos($concept, 'total') !== false) {
                $selected_row = $row;
                break;
            }
        }

        // Fase 2: Buscar fila destacada ("highlight")
        if (!$selected_row) {
            foreach ($rows as $row) {
                if (!empty($row['highlight'])) {
                    $selected_row = $row;
                    break;
                }
            }
        }

        // Fase 3: Tomar la primera fila válida
        if (!$selected_row && !empty($rows)) {
            $selected_row = $rows[0];
        }

        if (!$selected_row) {
            return null;
        }

        $uy_val = isset($selected_row['uy']) ? $this->extraer_numero_desde_texto_precio($selected_row['uy']) : null;
        $us_val = isset($selected_row['us']) ? $this->extraer_numero_desde_texto_precio($selected_row['us']) : null;

        if ($us_val !== null && $us_val > 0) {
            return array(
                'value' => $us_val,
                'currency' => 'USD'
            );
        } elseif ($uy_val !== null && $uy_val > 0) {
            // Convert UYU to USD using a standard exchange rate of 40 UYU per USD, rounded
            $converted_val = round($uy_val / 40.0);
            return array(
                'value' => $converted_val > 0 ? $converted_val : 1,
                'currency' => 'USD'
            );
        }

        return null;
    }

    /**
     * Obtiene la información del posgrado para el template
     */
    private function obtener_info_posgrado_para_template($pagina_padre_id) {
        $page_id = get_the_ID();
        $id_posgrado = (int)$pagina_padre_id;
        $offer_id = $this->resolver_oferta_id_desde_programa($id_posgrado);

        $info = array(
            'page_id' => (int)$page_id,
            'parent_page_id' => $id_posgrado,
            'id_posgrado' => $id_posgrado,
            'offer_id' => $offer_id,
            'titulo_posgrado' => get_the_title($id_posgrado),
            'es_maestria' => $this->es_oferta_maestria($id_posgrado),
            'preinscripcion_cerrada' => $this->formulario_preinscripcion_esta_cerrado($id_posgrado),
            'imagen_destacada' => '',
            'convenios_validos' => $this->obtener_convenios_validos(),
            'valor' => null,
            'currency' => null,
        );
        
        if ($offer_id > 0) {
            $precio_info = $this->obtener_precio_y_divisa_oferta($offer_id);
            if ($precio_info) {
                $info['valor'] = $precio_info['value'];
                $info['currency'] = $precio_info['currency'];
            }
        }
        
        if ($id_posgrado) {
            $imagen_url = get_the_post_thumbnail_url($id_posgrado, 'full');
            $info['imagen_destacada'] = $imagen_url ? $imagen_url : '';
        }
        
        return $info;
    }
}
