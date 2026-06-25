<?php
if (!defined('ABSPATH')) { exit; }

trait FLACSO_Formulario_Preinscripcion_Templates {
    
    /**
     * Registra los templates personalizados
     */
    public function registrar_templates() {
        add_filter('template_include', array($this, 'cargar_template_preinscripcion'), 99);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets_en_templates'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets_en_shortcode'));
        add_shortcode('formulario_preinscripcion', array($this, 'render_shortcode_preinscripcion'));
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
                $custom_template = plugin_dir_path(dirname(__FILE__)) . 'templates/preinscripcion-template.php';
                if (file_exists($custom_template)) {
                    add_filter('wp_title', array($this, 'modificar_titulo_preinscripcion'), 10, 3);
                    add_filter('document_title_parts', array($this, 'modificar_titulo_parts_preinscripcion'));
                    add_action('wp_head', array($this, 'add_og_meta_tags'), 5);
                    return $custom_template;
                }
            } elseif ($es_carta) {
                // Lógica de carta de presentación
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
        </div>
        <?php
        return ob_get_clean();
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
        );
        
        if ($id_posgrado) {
            $imagen_url = get_the_post_thumbnail_url($id_posgrado, 'full');
            $info['imagen_destacada'] = $imagen_url ? $imagen_url : '';
        }
        
        return $info;
    }
}
