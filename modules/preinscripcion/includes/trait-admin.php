<?php
if (!defined('ABSPATH')) { exit; }

trait FLACSO_Formulario_Preinscripcion_Admin {
    
    /**
     * Registra el menú de administración
     */
    public function registrar_menu_admin() {
        add_menu_page(
            'Preinscripciones FLACSO',
            'Preinscripciones',
            'manage_options',
            'flacso-preinscripciones',
            array($this, 'render_pagina_admin'),
            'dashicons-welcome-learn-more',
            30
        );
    }
    
    /**
     * Obtiene las páginas padre permitidas desde la configuración
     */
    private function obtener_paginas_padre_permitidas() {
        $paginas_configuradas = get_option('flacso_preinscripciones_paginas_padre', array());
        
        // Si no hay configuración, usar valores por defecto
        if (empty($paginas_configuradas)) {
            $paginas_configuradas = array(
                array('id' => 12294, 'nombre' => 'Diplomas'),
                array('id' => 12320, 'nombre' => 'Maestrías'),
                array('id' => 12309, 'nombre' => 'Especializaciones'),
                array('id' => 12275, 'nombre' => 'Diplomados'),
            );
        }
        
        // Verificar que las páginas existen y construir array asociativo
        $paginas_validas = array();
        foreach ($paginas_configuradas as $index => $config) {
            if (empty($config['id'])) {
                continue;
            }
            $page_id = intval($config['id']);
            if (get_post_status($page_id) !== false) {
                // Usar índice + nombre para evitar colisiones
                $nombre_key = $index . '_' . sanitize_key($config['nombre']);
                $paginas_validas[$nombre_key] = array(
                    'id' => $page_id,
                    'nombre' => $config['nombre']
                );
            }
        }
        
        return $paginas_validas;
    }
    
    /**
     * Obtiene todas las páginas hijas de las categorías permitidas
     */
    private function obtener_paginas_disponibles() {
        $paginas_padre = $this->obtener_paginas_padre_permitidas();
        $paginas_disponibles = array();
        
        foreach ($paginas_padre as $tipo => $padre_data) {
            $parent_id = $padre_data['id'];
            $nombre_categoria = $padre_data['nombre'];
            
            $args = array(
                'post_type' => 'page',
                'parent' => $parent_id,
                'post_status' => 'publish',
                'orderby' => 'title',
                'order' => 'ASC',
                'posts_per_page' => -1,
            );
            
            $pages = get_pages($args);
            foreach ($pages as $page) {
                $paginas_disponibles[$page->ID] = array(
                    'titulo' => $page->post_title,
                    'url' => get_permalink($page->ID),
                    'parent_id' => $parent_id,
                    'tipo' => $tipo,
                    'tipo_nombre' => $nombre_categoria,
                    'slug' => $page->post_name,
                );
            }
        }
        
        return $paginas_disponibles;
    }
    
    /**
     * Obtiene las páginas que tienen preinscripción activa
     */
    private function obtener_paginas_activas() {
        return get_option('flacso_preinscripciones_activas', array());
    }
    
    /**
     * Guarda las páginas activas
     */
    private function guardar_paginas_activas($paginas) {
        update_option('flacso_preinscripciones_activas', $paginas);
    }
    
    /**
     * Procesa el formulario de administración
     */
    public function procesar_formulario_admin() {
        // Procesar migración si se solicita
        if (isset($_POST['flacso_ejecutar_migracion']) && wp_verify_nonce($_POST['flacso_migracion_nonce'] ?? '', 'flacso_migracion')) {
            if (current_user_can('manage_options')) {
                $resultado = $this->ejecutar_migracion(array(
                    'eliminar_paginas' => isset($_POST['flacso_migrar_eliminar']),
                    'limpiar_meta' => true
                ));
                
                if ($resultado['exito']) {
                    $mensaje = "Migración completada: {$resultado['paginas_migradas']} programa(s) migrado(s)";
                    if ($resultado['paginas_eliminadas'] > 0) {
                        $mensaje .= ", {$resultado['paginas_eliminadas']} página(s) movida(s) a papelera";
                    }
                    if ($resultado['tiene_errores']) {
                        $mensaje .= ". " . count($resultado['errores']) . " advertencia(s)";
                    }
                    add_settings_error('flacso_preinscripciones', 'migracion_exito', $mensaje, 'success');
                } else {
                    add_settings_error('flacso_preinscripciones', 'migracion_error', 'Error en la migración: ' . $resultado['mensaje'], 'error');
                }
            }
            return;
        }
        
        if (!isset($_POST['flacso_preinscripciones_nonce']) || 
            !wp_verify_nonce($_POST['flacso_preinscripciones_nonce'], 'flacso_preinscripciones_guardar')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Guardar páginas padre si se enviaron
        if (isset($_POST['paginas_padre']) && is_array($_POST['paginas_padre'])) {
            $paginas_padre = array();
            foreach ($_POST['paginas_padre'] as $index => $padre) {
                if (!empty($padre['id']) && !empty($padre['nombre'])) {
                    $paginas_padre[] = array(
                        'id' => intval($padre['id']),
                        'nombre' => sanitize_text_field($padre['nombre'])
                    );
                }
            }
            update_option('flacso_preinscripciones_paginas_padre', $paginas_padre);
        }
        
        $paginas_seleccionadas = isset($_POST['paginas_preinscripcion']) && is_array($_POST['paginas_preinscripcion']) 
            ? array_map('intval', $_POST['paginas_preinscripcion']) 
            : array();
        
        // Guardar webhook URL
        if (isset($_POST['webhook_url'])) {
            $webhook_url = esc_url_raw($_POST['webhook_url']);
            update_option('flacso_preinscripciones_webhook_url', $webhook_url);
        }
        
        // Guardar la configuración de páginas activas
        $this->guardar_paginas_activas($paginas_seleccionadas);
        
        // Limpiar rewrite rules para que las URLs virtuales funcionen
        flush_rewrite_rules();
        
        // Mostrar mensaje de éxito
        $mensaje = 'Configuración guardada correctamente.';
        $total_activas = count($paginas_seleccionadas);
        if ($total_activas > 0) {
            $mensaje .= " Se activaron formularios de preinscripción para $total_activas programa(s).";
        }
        
        add_settings_error(
            'flacso_preinscripciones',
            'flacso_preinscripciones_guardado',
            $mensaje,
            'success'
        );
    }
    
    /**
     * Renderiza la página de administración
     */
    public function render_pagina_admin() {
        if (isset($_POST['flacso_preinscripciones_nonce']) || isset($_POST['flacso_migracion_nonce'])) {
            $this->procesar_formulario_admin();
        }
        
        // Verificar si hay páginas antiguas que migrar
        $estado_migracion = $this->obtener_estado_migracion();
        
        $paginas_disponibles = $this->obtener_paginas_disponibles();
        $paginas_activas = $this->obtener_paginas_activas();
        $todas_paginas = get_pages(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'hierarchical' => true,
            'sort_column' => 'post_title',
        ));
        $paginas_padre_config = get_option('flacso_preinscripciones_paginas_padre', array(
            array('id' => 12294, 'nombre' => 'Diplomas'),
            array('id' => 12320, 'nombre' => 'Maestrías'),
            array('id' => 12309, 'nombre' => 'Especializaciones'),
            array('id' => 12275, 'nombre' => 'Diplomados'),
        ));
        
        ?>
        <div class="wrap">
            <div style="display: flex; align-items: center; margin-bottom: 30px;">
                <h1 style="margin: 0;">Gestión de Preinscripciones FLACSO</h1>
            </div>
            
            <?php settings_errors('flacso_preinscripciones'); ?>
            
            <?php if ($estado_migracion['necesita_migracion']): ?>
                <div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 5px; padding: 20px; margin-bottom: 30px;">
                    <h2 style="margin-top: 0; color: #856404;">⚠️ Migración de Datos Disponible</h2>
                    <p style="color: #856404; margin: 10px 0;">Se detectaron <strong><?php echo $estado_migracion['cantidad_paginas_antiguas']; ?> página(s)</strong> del sistema anterior que pueden migrarse automáticamente:</p>
                    
                    <ul style="color: #856404; margin: 15px 0;">
                        <?php foreach ($estado_migracion['paginas'] as $info): ?>
                            <li><strong><?php echo esc_html($info['titulo_parent']); ?></strong> → <?php echo esc_html($info['titulo_pagina']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <form method="post" action="" style="margin-top: 15px;">
                        <?php wp_nonce_field('flacso_migracion', 'flacso_migracion_nonce'); ?>
                        <label style="display: block; margin-bottom: 10px;">
                            <input type="checkbox" name="flacso_migrar_eliminar" value="1" checked>
                            <span style="color: #856404;">Mover páginas antiguas a papelera (se pueden recuperar)</span>
                        </label>
                        <button type="submit" name="flacso_ejecutar_migracion" value="1" class="button" style="background: #ffc107; color: #856404; border-color: #ffc107; padding: 8px 16px; cursor: pointer;">
                            ▶ Ejecutar Migración
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- PASO 1: WEBHOOK -->
            <div style="background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 25px; margin-bottom: 25px;">
                <h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 3px solid #0073aa; color: #0073aa;">
                    <span style="background: #0073aa; color: white; border-radius: 50%; width: 35px; height: 35px; display: inline-flex; align-items: center; justify-content: center; margin-right: 10px; font-size: 18px; font-weight: bold;">1</span>
                    Webhook (Google Apps Script)
                </h2>
                <form method="post" action="">
                    <?php wp_nonce_field('flacso_preinscripciones_guardar', 'flacso_preinscripciones_nonce'); ?>
                    
                    <p style="color: #666; margin-bottom: 15px;">Configura la URL del Google Apps Script donde se enviarán los datos de preinscripción:</p>
                    
                    <div style="margin-bottom: 15px;">
                        <label for="webhook_url" style="display: block; font-weight: 600; margin-bottom: 8px;">URL del Webhook:</label>
                        <input type="url" 
                               name="webhook_url" 
                               id="webhook_url" 
                               value="<?php echo esc_attr(get_option('flacso_preinscripciones_webhook_url', '')); ?>" 
                               class="regular-text" 
                               placeholder="https://script.google.com/macros/s/AKfycbz..."
                               style="width: 100%; max-width: 600px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <p style="color: #666; font-size: 0.9em; margin-top: 8px;">Ej: https://script.google.com/macros/s/AKfycbz.../usercontent</p>
                    </div>
                    
                    <button type="submit" class="button button-primary" style="padding: 10px 20px;">Guardar Webhook</button>
                </form>
            </div>
            
            <!-- PASO 2: CATEGORÍAS -->
            <div style="background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 25px; margin-bottom: 25px;">
                <h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 3px solid #0073aa; color: #0073aa;">
                    <span style="background: #0073aa; color: white; border-radius: 50%; width: 35px; height: 35px; display: inline-flex; align-items: center; justify-content: center; margin-right: 10px; font-size: 18px; font-weight: bold;">2</span>
                    Categorías de Programas
                </h2>
                <p style="color: #666; margin-bottom: 15px;">Define las categorías principales. Los programas se mostrarán organizados por estas categorías.</p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('flacso_preinscripciones_guardar', 'flacso_preinscripciones_nonce'); ?>
                    
                    <div id="categorias-container">
                        <?php foreach ($paginas_padre_config as $index => $padre): 
                            $page_exists = get_post_status($padre['id']) !== false;
                        ?>
                        <div style="background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 4px; padding: 15px; margin-bottom: 15px; position: relative;">
                            <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 20px;">
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Nombre de Categoría:</label>
                                    <input type="text" 
                                           name="paginas_padre[<?php echo $index; ?>][nombre]" 
                                           value="<?php echo esc_attr($padre['nombre']); ?>" 
                                           placeholder="Ej: Maestrías"
                                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                                </div>
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Página Padre:</label>
                                    <div style="display: flex; gap: 10px;">
                                        <select name="paginas_padre[<?php echo $index; ?>][id]" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                            <option value="">-- Seleccionar página --</option>
                                            <?php foreach ($todas_paginas as $page): ?>
                                                <option value="<?php echo esc_attr($page->ID); ?>" 
                                                        <?php selected($padre['id'], $page->ID); ?>>
                                                    <?php 
                                                    echo esc_html($page->post_title);
                                                    if ($page->post_parent) {
                                                        $parent_title = get_the_title($page->post_parent);
                                                        echo ' (hijo de: ' . esc_html($parent_title) . ')';
                                                    }
                                                    ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span style="<?php echo $page_exists ? 'color: green;' : 'color: red;'; ?> font-size: 20px; line-height: 35px;">
                                            <?php echo $page_exists ? '✓' : '✗'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="background: #e8f4f8; border: 2px dashed #0073aa; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
                        <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 20px;">
                            <div>
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;">➕ Nueva Categoría:</label>
                                <input type="text" 
                                       name="paginas_padre[<?php echo count($paginas_padre_config); ?>][nombre]" 
                                       placeholder="Ej: Cursos"
                                       style="width: 100%; padding: 8px; border: 1px solid #0073aa; border-radius: 4px; box-sizing: border-box;">
                            </div>
                            <div>
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Página Padre:</label>
                                <select name="paginas_padre[<?php echo count($paginas_padre_config); ?>][id]" style="width: 100%; padding: 8px; border: 1px solid #0073aa; border-radius: 4px;">
                                    <option value="">-- Seleccionar página --</option>
                                    <?php foreach ($todas_paginas as $page): ?>
                                        <option value="<?php echo esc_attr($page->ID); ?>">
                                            <?php 
                                            echo esc_html($page->post_title);
                                            if ($page->post_parent) {
                                                $parent_title = get_the_title($page->post_parent);
                                                echo ' (hijo de: ' . esc_html($parent_title) . ')';
                                            }
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="button button-primary" style="padding: 10px 20px;">Guardar Categorías</button>
                </form>
            </div>
            
            <!-- PASO 3: PROGRAMAS -->
            <div style="background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 25px; margin-bottom: 25px;">
                <h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 3px solid #0073aa; color: #0073aa;">
                    <span style="background: #0073aa; color: white; border-radius: 50%; width: 35px; height: 35px; display: inline-flex; align-items: center; justify-content: center; margin-right: 10px; font-size: 18px; font-weight: bold;">3</span>
                    Programas con Preinscripción
                </h2>
                <p style="color: #666; margin-bottom: 20px;">Selecciona los programas que tendrán formulario de preinscripción. Cada uno tendrá acceso en: <code style="background: #f5f5f5; padding: 2px 6px;">[programa]/preinscripcion/</code></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('flacso_preinscripciones_guardar', 'flacso_preinscripciones_nonce'); ?>
                    
                    <?php if (empty($paginas_disponibles)): ?>
                        <div style="background: #fff8e5; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px;">
                            <p style="margin: 0; color: #7d6b39;"><strong>⚠️</strong> No se encontraron programas disponibles. Verifica que existan páginas publicadas bajo las categorías configuradas.</p>
                        </div>
                    <?php else: ?>
                        <?php
                        // Reagrupar páginas utilizando el ID de su página padre configurada
                        $paginas_por_padre = array();
                        foreach ($paginas_disponibles as $page_id => $page_data) {
                            $parent_id = $page_data['parent_id'];
                            if (!isset($paginas_por_padre[$parent_id])) {
                                $paginas_por_padre[$parent_id] = array();
                            }
                            $paginas_por_padre[$parent_id][$page_id] = $page_data;
                        }
                        
                        foreach ($paginas_padre_config as $categoria):
                            $categoria_nombre = !empty($categoria['nombre']) ? $categoria['nombre'] : 'Categoría sin nombre';
                            $categoria_id = isset($categoria['id']) ? intval($categoria['id']) : 0;
                            $paginas_categoria = ($categoria_id && isset($paginas_por_padre[$categoria_id]))
                                ? $paginas_por_padre[$categoria_id]
                                : array();
                            $page_exists = $categoria_id ? get_post_status($categoria_id) !== false : false;
                        ?>
                        
                        <h3 style="margin-top: 25px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #0073aa; color: #0073aa;">
                            📚 <?php echo esc_html($categoria_nombre); ?> (<?php echo count($paginas_categoria); ?>)
                        </h3>
                        
                        <?php if (!$categoria_id): ?>
                            <div style="background: #fff8e5; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 30px; color: #7d6b39;">
                                Debes seleccionar una página padre para esta categoría en el paso 2.
                            </div>
                        <?php elseif (!$page_exists): ?>
                            <div style="background: #fdecea; border-left: 4px solid #d93025; padding: 15px; margin-bottom: 30px; color: #7a1c12;">
                                La página padre seleccionada ya no existe o está en estado inactivo. Actualiza la categoría en el paso 2.
                            </div>
                        <?php elseif (empty($paginas_categoria)): ?>
                            <div style="background: #e8f4f8; border-left: 4px solid #0073aa; padding: 15px; margin-bottom: 30px; color: #004b6e;">
                                No se encontraron páginas hijas publicadas para esta categoría. Crea o asigna páginas hijas bajo la página padre seleccionada.
                            </div>
                        <?php else: ?>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; margin-bottom: 30px;">
                                <?php foreach ($paginas_categoria as $page_id => $page_data): 
                                    $checked = in_array($page_id, $paginas_activas);
                                    $url_preinscripcion = trailingslashit($page_data['url']) . 'preinscripcion/';
                                ?>
                                <div style="border: 2px solid <?php echo $checked ? '#0073aa' : '#e0e0e0'; ?>; border-radius: 5px; padding: 15px; background: <?php echo $checked ? '#f0f7ff' : '#fafafa'; ?>; transition: all 0.3s;">
                                    <label style="display: flex; align-items: start; cursor: pointer; margin: 0;">
                                        <input type="checkbox" 
                                               name="paginas_preinscripcion[]" 
                                               value="<?php echo esc_attr($page_id); ?>"
                                               id="page_<?php echo esc_attr($page_id); ?>"
                                               style="margin-right: 10px; margin-top: 3px;"
                                               <?php checked($checked); ?>>
                                        <div style="flex: 1;">
                                            <strong style="display: block; margin-bottom: 5px;"><?php echo esc_html($page_data['titulo']); ?></strong>
                                            <a href="<?php echo esc_url($page_data['url']); ?>" target="_blank" style="font-size: 0.85em; color: #666; text-decoration: none;">
                                                Ver página →
                                            </a>
                                            <?php if ($checked): ?>
                                                <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #ddd;">
                                                    <a href="<?php echo esc_url($url_preinscripcion); ?>" target="_blank" style="font-size: 0.85em; color: #0073aa; text-decoration: none; font-weight: 600;">
                                                        ✓ Ver formulario →
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php endforeach; ?>
                        
                        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                            <button type="submit" class="button button-primary button-large" style="padding: 12px 30px; font-size: 16px;">
                                💾 Guardar Cambios
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- INFO -->
            <div style="background: #e8f4f8; border: 1px solid #0073aa; border-radius: 5px; padding: 20px;">
                <h3 style="margin-top: 0; color: #0073aa;">ℹ️ Información Importante</h3>
                <ul style="margin: 0; color: #333; line-height: 1.8;">
                    <li><strong>Páginas Virtuales:</strong> No se crean páginas reales en la base de datos.</li>
                    <li><strong>URLs dinámicas:</strong> Se generan automáticamente siguiendo el patrón de tus páginas.</li>
                    <li><strong>Títulos automáticos:</strong> Cada formulario muestra "Preinscripción - [Nombre del Programa]".</li>
                    <li><strong>Sin pérdida de datos:</strong> Al desactivar un programa, se desactiva el acceso pero no se pierde nada.</li>
                    <li><strong>Datos a Google Sheets:</strong> Los formularios envían datos al webhook configurado.</li>
                </ul>
            </div>
        </div>
        
        <style>
            #categorias-container { max-height: 500px; overflow-y: auto; }
            .button { border-radius: 4px; }
            .button-primary { background: #0073aa; border-color: #0073aa; }
            .button-primary:hover { background: #005a87; }
        </style>
        <?php
    }
}
