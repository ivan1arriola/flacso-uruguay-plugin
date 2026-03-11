<?php
if (!defined('ABSPATH')) { exit; }

trait FLACSO_Formulario_Preinscripcion_Migracion {
    
    /**
     * Detecta páginas antiguas de preinscripción del sistema anterior
     */
    public function detectar_paginas_antiguas() {
        $args = array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_flacso_es_pagina_preinscripcion',
                    'value' => '1',
                    'compare' => '='
                )
            ),
            'fields' => 'ids'
        );
        
        return get_posts($args);
    }
    
    /**
     * Obtiene información de páginas antiguas para migración
     */
    public function obtener_info_paginas_antiguas() {
        $paginas_antiguas = $this->detectar_paginas_antiguas();
        $info = array();
        
        foreach ($paginas_antiguas as $page_id) {
            $page = get_post($page_id);
            $parent_id = $page->post_parent;
            
            if (!$parent_id) {
                continue;
            }
            
            $parent_page = get_post($parent_id);
            if (!$parent_page) {
                continue;
            }
            
            $info[$page_id] = array(
                'page_id' => $page_id,
                'titulo_pagina' => $page->post_title,
                'slug_pagina' => $page->post_name,
                'parent_id' => $parent_id,
                'titulo_parent' => $parent_page->post_title,
                'slug_parent' => $parent_page->post_name,
            );
        }
        
        return $info;
    }
    
    /**
     * Ejecuta la migración de páginas antiguas al nuevo sistema
     * 
     * @param array $opciones Opciones de migración
     *                         - 'eliminar_paginas' => bool (default: true)
     *                         - 'limpiar_meta' => bool (default: true)
     * @return array Resultado de la migración
     */
    public function ejecutar_migracion($opciones = array()) {
        if (!current_user_can('manage_options')) {
            return array(
                'exito' => false,
                'mensaje' => 'No tiene permisos para ejecutar la migración',
                'error' => true
            );
        }
        
        $opciones = wp_parse_args($opciones, array(
            'eliminar_paginas' => true,
            'limpiar_meta' => true,
        ));
        
        $paginas_antiguas = $this->detectar_paginas_antiguas();
        
        if (empty($paginas_antiguas)) {
            return array(
                'exito' => true,
                'mensaje' => 'No hay páginas antiguas para migrar',
                'paginas_migradas' => 0,
                'paginas_eliminadas' => 0
            );
        }
        
        $paginas_activas = array();
        $migradas = 0;
        $eliminadas = 0;
        $errores = array();
        
        // Migrar páginas
        foreach ($paginas_antiguas as $page_id) {
            $page = get_post($page_id);
            $parent_id = $page->post_parent;
            
            if (!$parent_id || !get_post($parent_id)) {
                $errores[] = "Página {$page_id}: No tiene padre válido";
                continue;
            }
            
            // Agregar a lista de páginas activas
            if (!in_array($parent_id, $paginas_activas)) {
                $paginas_activas[] = (int)$parent_id;
                $migradas++;
            }
            
            // Limpiar meta antigua si está habilitado
            if ($opciones['limpiar_meta']) {
                delete_post_meta($page_id, '_flacso_es_pagina_preinscripcion');
                delete_post_meta($page_id, '_flacso_pagina_posgrado_id');
            }
            
            // Eliminar página si está habilitado
            if ($opciones['eliminar_paginas']) {
                wp_trash_post($page_id);
                $eliminadas++;
            }
        }
        
        // Guardar configuración nueva
        if (!empty($paginas_activas)) {
            $actuales = get_option('flacso_preinscripciones_activas', array());
            $combinadas = array_unique(array_merge($actuales, $paginas_activas));
            update_option('flacso_preinscripciones_activas', array_map('intval', $combinadas));
        }
        
        // Limpiar rewrite rules
        flush_rewrite_rules();
        
        return array(
            'exito' => true,
            'mensaje' => 'Migración completada exitosamente',
            'paginas_migradas' => count($paginas_activas),
            'paginas_eliminadas' => $eliminadas,
            'errores' => $errores,
            'tiene_errores' => !empty($errores)
        );
    }
    
    /**
     * Obtiene el estado de la migración
     */
    public function obtener_estado_migracion() {
        $paginas_antiguas = $this->detectar_paginas_antiguas();
        
        return array(
            'necesita_migracion' => !empty($paginas_antiguas),
            'cantidad_paginas_antiguas' => count($paginas_antiguas),
            'paginas' => $this->obtener_info_paginas_antiguas()
        );
    }
}
