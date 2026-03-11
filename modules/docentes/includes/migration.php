<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Funciones de migración para cpt-docentes
 */
class Docente_Migration {
    
    /**
     * Migrar datos de prefijo_full a titulo
     */
    public static function migrate_prefijo_full_to_titulo(): array {
        global $wpdb;
        
        // Buscar todos los posts de tipo docente que tienen prefijo_full
        $query = "
            SELECT p.ID, pm.meta_value
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'docente'
            AND pm.meta_key = 'prefijo_full'
            AND pm.meta_value != ''
        ";
        
        $results = $wpdb->get_results($query);
        
        if (empty($results)) {
            return [
                'success' => true,
                'message' => 'No se encontraron registros de prefijo_full para migrar.',
                'migrated' => 0
            ];
        }
        
        $migrated = 0;
        $errors = [];
        
        foreach ($results as $row) {
            $docente_id = (int) $row->ID;
            $prefijo_full_value = $row->meta_value;
            
            // Verificar si ya existe titulo (no sobrescribir si ya tiene valor)
            $titulo_actual = get_post_meta($docente_id, 'titulo', true);
            
            if (!empty($titulo_actual)) {
                // Ya tiene titulo, no migrar
                continue;
            }
            
            // Copiar prefijo_full a titulo
            $updated = update_post_meta($docente_id, 'titulo', $prefijo_full_value);
            
            if ($updated !== false) {
                $migrated++;
            } else {
                $errors[] = "Error migrando docente ID: {$docente_id}";
            }
        }
        
        return [
            'success' => empty($errors),
            'message' => sprintf(
                'Migración completada. %d registros migrados de prefijo_full a titulo.',
                $migrated
            ),
            'migrated' => $migrated,
            'errors' => $errors
        ];
    }
    
    /**
     * Eliminar meta prefijo_full después de confirmar la migración
     * SOLO usar después de verificar que la migración fue exitosa
     */
    public static function cleanup_prefijo_full(): array {
        global $wpdb;
        
        $deleted = $wpdb->delete(
            $wpdb->postmeta,
            [
                'meta_key' => 'prefijo_full'
            ],
            ['%s']
        );
        
        return [
            'success' => true,
            'message' => sprintf('%d registros de prefijo_full eliminados.', $deleted),
            'deleted' => $deleted
        ];
    }
    
    /**
     * Verificar el estado de la migración
     */
    public static function check_migration_status(): array {
        global $wpdb;
        
        // Contar docentes con prefijo_full
        $with_prefijo_full = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'docente'
            AND pm.meta_key = 'prefijo_full'
            AND pm.meta_value != ''
        ");
        
        // Contar docentes con titulo
        $with_titulo = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'docente'
            AND pm.meta_key = 'titulo'
            AND pm.meta_value != ''
        ");
        
        // Total de docentes
        $total_docentes = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_type = 'docente'
            AND post_status IN ('publish', 'draft', 'pending', 'private')
        ");
        
        return [
            'total_docentes' => (int) $total_docentes,
            'con_prefijo_full' => (int) $with_prefijo_full,
            'con_titulo' => (int) $with_titulo,
            'migracion_necesaria' => ($with_prefijo_full > 0 && $with_titulo < $with_prefijo_full)
        ];
    }
}

/**
 * Página de administración para migración
 */
class Docente_Migration_Admin {
    
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu'], 20);
        add_action('admin_post_migrate_prefijo_full', [__CLASS__, 'handle_migration']);
        add_action('admin_post_cleanup_prefijo_full', [__CLASS__, 'handle_cleanup']);
    }
    
    public static function add_admin_menu(): void {
        add_submenu_page(
            'edit.php?post_type=docente',
            'Migración de Datos',
            'Migración de Datos',
            'manage_options',
            'docente-migration',
            [__CLASS__, 'render_admin_page']
        );
    }
    
    public static function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para acceder a esta página.');
        }
        
        $status = Docente_Migration::check_migration_status();
        
        ?>
        <div class="wrap">
            <h1>Migración de Datos - Docentes</h1>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Estado de la Migración: prefijo_full → titulo</h2>
                
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong>Total de docentes:</strong></td>
                            <td><?php echo esc_html($status['total_docentes']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Con campo "prefijo_full":</strong></td>
                            <td><?php echo esc_html($status['con_prefijo_full']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Con campo "titulo":</strong></td>
                            <td><?php echo esc_html($status['con_titulo']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>¿Migración necesaria?:</strong></td>
                            <td>
                                <?php if ($status['migracion_necesaria']): ?>
                                    <span style="color: orange;">⚠️ Sí, hay datos por migrar</span>
                                <?php else: ?>
                                    <span style="color: green;">✅ No, migración completa</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if ($status['migracion_necesaria']): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
                        <h3>⚠️ Acción Requerida</h3>
                        <p>Se encontraron <strong><?php echo esc_html($status['con_prefijo_full']); ?> docentes</strong> con el campo antiguo "prefijo_full".</p>
                        <p>Esta migración copiará los valores de "prefijo_full" a "titulo" sin sobrescribir datos existentes.</p>
                        
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 15px;">
                            <?php wp_nonce_field('migrate_prefijo_full_action', 'migrate_prefijo_full_nonce'); ?>
                            <input type="hidden" name="action" value="migrate_prefijo_full">
                            <button type="submit" class="button button-primary button-large">
                                🔄 Ejecutar Migración
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <?php if ($status['con_prefijo_full'] > 0 && !$status['migracion_necesaria']): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #d1ecf1; border-left: 4px solid #0c5460;">
                        <h3>🧹 Limpieza de Datos Antiguos</h3>
                        <p>La migración está completa. Puedes eliminar los registros antiguos de "prefijo_full".</p>
                        <p><strong>Advertencia:</strong> Esta acción es irreversible. Asegúrate de que todo funciona correctamente antes de proceder.</p>
                        
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 15px;" onsubmit="return confirm('¿Estás seguro? Esta acción no se puede deshacer.');">
                            <?php wp_nonce_field('cleanup_prefijo_full_action', 'cleanup_prefijo_full_nonce'); ?>
                            <input type="hidden" name="action" value="cleanup_prefijo_full">
                            <button type="submit" class="button button-secondary">
                                🗑️ Eliminar registros de "prefijo_full"
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #6c757d;">
                    <h3>ℹ️ Información</h3>
                    <ul>
                        <li>El campo "prefijo_full" se ha renombrado a "titulo" para mayor claridad.</li>
                        <li>La migración NO sobrescribe valores existentes de "titulo".</li>
                        <li>Después de la migración, el campo antiguo se mantiene hasta que lo elimines manualmente.</li>
                        <li>Puedes ejecutar la migración múltiples veces sin riesgo.</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    public static function handle_migration(): void {
        check_admin_referer('migrate_prefijo_full_action', 'migrate_prefijo_full_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        $result = Docente_Migration::migrate_prefijo_full_to_titulo();
        
        $redirect = add_query_arg([
            'page' => 'docente-migration',
            'migration_result' => $result['success'] ? 'success' : 'error',
            'migrated' => $result['migrated']
        ], admin_url('edit.php?post_type=docente'));
        
        wp_safe_redirect($redirect);
        exit;
    }
    
    public static function handle_cleanup(): void {
        check_admin_referer('cleanup_prefijo_full_action', 'cleanup_prefijo_full_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        $result = Docente_Migration::cleanup_prefijo_full();
        
        $redirect = add_query_arg([
            'page' => 'docente-migration',
            'cleanup_result' => 'success',
            'deleted' => $result['deleted']
        ], admin_url('edit.php?post_type=docente'));
        
        wp_safe_redirect($redirect);
        exit;
    }
}

// Mostrar mensajes de éxito/error
add_action('admin_notices', function() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'docente-migration') {
        return;
    }
    
    if (isset($_GET['migration_result']) && $_GET['migration_result'] === 'success') {
        $migrated = isset($_GET['migrated']) ? (int) $_GET['migrated'] : 0;
        echo '<div class="notice notice-success is-dismissible">
                <p>✅ Migración completada exitosamente. Se migraron ' . esc_html($migrated) . ' registros.</p>
              </div>';
    }
    
    if (isset($_GET['cleanup_result']) && $_GET['cleanup_result'] === 'success') {
        $deleted = isset($_GET['deleted']) ? (int) $_GET['deleted'] : 0;
        echo '<div class="notice notice-success is-dismissible">
                <p>✅ Se eliminaron ' . esc_html($deleted) . ' registros de prefijo_full.</p>
              </div>';
    }
});
