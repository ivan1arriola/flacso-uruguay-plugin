<?php
if (!defined('ABSPATH')) { exit; }

trait FLACSO_Formulario_Preinscripcion_Templates {
    
    /**
     * Registra los templates personalizados
     */
    public function registrar_templates() {
        add_filter('template_include', array($this, 'cargar_template_preinscripcion'), 99);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets_en_templates'));
    }
    
    /**
     * Enqueue assets para el template de preinscripción
     */
    public function enqueue_assets_en_templates() {
        global $post;
        if (!$post) return;

        // Cargar solo en la URL virtual /.../preinscripcion/.
        if (!get_query_var('es_preinscripcion')) return;

        // Verificar si es una página con preinscripción activa.
        if (!$this->es_pagina_preinscripcion_activa($post->ID)) return;
        
        // Bootstrap e íconos solo para el template virtual.
        $this->enqueue_assets();

        $info = $this->obtener_info_posgrado();
        $this->enqueue_assets_formulario($info);
    }
    
    /**
     * Carga el template para páginas de preinscripción (virtuales)
     */
    public function cargar_template_preinscripcion($template) {
        global $post, $wp_query;
        
        // Verificar si es una URL virtual de preinscripción
        $es_preinscripcion = get_query_var('es_preinscripcion');
        
        if ($es_preinscripcion && is_singular('page') && $post) {
            // Verificar si esta página tiene preinscripción activa
            if ($this->es_pagina_preinscripcion_activa($post->ID)) {
                $custom_template = $this->obtener_ruta_template();
                
                if (file_exists($custom_template)) {
                    // Modificar el título de la página para SEO
                    add_filter('wp_title', array($this, 'modificar_titulo_preinscripcion'), 10, 3);
                    add_filter('document_title_parts', array($this, 'modificar_titulo_parts_preinscripcion'));
                    
                    return $custom_template;
                }
            } else {
                // Página no tiene preinscripción activa, mostrar 404
                $wp_query->set_404();
                status_header(404);
                return get_404_template();
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
        </div>
        <?php
    }
    
    /**
     * Obtiene la información del posgrado para el template
     */
    private function obtener_info_posgrado_para_template($pagina_padre_id) {
        $page_id = get_the_ID();
        $id_posgrado = (int)$pagina_padre_id;
        
        // IDs de maestrías
        $maestrias = array_map('intval', array(12330, 12336, 12343));

        $info = array(
            'page_id' => (int)$page_id,
            'parent_page_id' => $id_posgrado,
            'id_posgrado' => $id_posgrado,
            'titulo_posgrado' => get_the_title($id_posgrado),
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
}
