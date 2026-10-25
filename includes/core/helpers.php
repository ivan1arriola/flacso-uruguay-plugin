<?php
/**
 * Funciones helper compartidas para todos los módulos
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// Funciones generales
// ============================================

/**
 * Obtener versión de activo para cache busting
 */
if (!function_exists('flacso_asset_version')) {
    function flacso_asset_version(string $relative_path): string {
        $absolute_path = FLACSO_URUGUAY_PATH . ltrim($relative_path, '/');
        if (file_exists($absolute_path)) {
            return (string) filemtime($absolute_path);
        }
        return FLACSO_URUGUAY_VERSION;
    }
}

/**
 * Detecta una petición a la REST API también antes de `parse_request`.
 *
 * WordPress define REST_REQUEST recién en `parse_request`, es decir, después de
 * `plugins_loaded` e `init`. Para decidir qué cargar en esos momentos se
 * inspecciona la URL solicitada.
 */
if (!function_exists('flacso_is_rest_request')) {
    function flacso_is_rest_request(): bool {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        if (isset($_GET['rest_route'])) {
            return true;
        }

        if (empty($_SERVER['REQUEST_URI']) || !function_exists('rest_get_url_prefix')) {
            return false;
        }

        $request_path = wp_parse_url((string) wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH);
        if (!is_string($request_path)) {
            return false;
        }

        $home_path = trailingslashit((string) wp_parse_url(home_url('/'), PHP_URL_PATH));
        $rest_path = $home_path . trailingslashit(rest_get_url_prefix());

        return strpos(trailingslashit($request_path), $rest_path) === 0;
    }
}

/**
 * Cargar archivo de forma segura
 */
if (!function_exists('flacso_safe_require')) {
    function flacso_safe_require(string $path): bool {
        $path = ltrim($path, '/');
        $full_path = FLACSO_URUGUAY_PATH . $path;
        
        if (!file_exists($full_path)) {
            error_log("[FLACSO] Archivo no encontrado: $path");
            FLACSO_Error_Notifier::report("Archivo no encontrado: $path", __FILE__, __LINE__, 'loader');
            return false;
        }
        
        try {
            require_once $full_path;
            return true;
        } catch (Throwable $e) {
            error_log("[FLACSO] Error al cargar $path: " . $e->getMessage());
            FLACSO_Error_Notifier::report_exception($e);
            return false;
        }
    }
}

// ============================================
// Funciones para docentes
// ============================================

if (!function_exists('dp_docentes_asset_version')) {
    function dp_docentes_asset_version(string $relative_path): string {
        return flacso_asset_version($relative_path);
    }
}

if (!function_exists('dp_is_docentes_view')) {
    function dp_is_docentes_view() {
        return is_post_type_archive('docente')
            || is_singular('docente');
    }
}

if (!function_exists('dp_nombre_completo')) {
    function dp_nombre_completo(int $post_id, bool $with_complete_prefix = false): string {
        $prefijo_abrev = get_post_meta($post_id, 'prefijo_abrev', true);
        $titulo = get_post_meta($post_id, 'titulo', true);
        $nombre = get_post_meta($post_id, 'nombre', true);
        $apellido = get_post_meta($post_id, 'apellido', true);

        if (!$nombre && !$apellido) {
            return (string) get_the_title($post_id);
        }

        $prefijo = $with_complete_prefix ? $titulo : $prefijo_abrev;
        $partes = array_filter([$prefijo, $nombre, $apellido]);
        return implode(' ', $partes);
    }
}

// ============================================
// Helpers de admin
// ============================================

if (!function_exists('flacso_print_quicktags_paragraph_buttons')) {
    function flacso_print_quicktags_paragraph_buttons(): void {
        if (!is_admin()) {
            return;
        }
        ?>
        <script>
        (function() {
            if (typeof window.QTags === 'undefined' || !window.QTags.instances) {
                return;
            }

            var editorIds = Object.keys(window.QTags.instances);
            if (!editorIds.length) {
                return;
            }

            editorIds.forEach(function(editorId) {
                if (!editorId || !window.QTags.instances[editorId]) {
                    return;
                }

                if (!document.getElementById('qt_' + editorId + '_flacso_p_' + editorId)) {
                    window.QTags.addButton(
                        'flacso_p_' + editorId,
                        'p',
                        '<p>',
                        '</p>',
                        '',
                        '<?php echo esc_js(__('Insertar párrafo', 'flacso-uruguay')); ?>',
                        101,
                        editorId
                    );
                }

                if (!document.getElementById('qt_' + editorId + '_flacso_br_' + editorId)) {
                    window.QTags.addButton(
                        'flacso_br_' + editorId,
                        'br',
                        '<br>',
                        '',
                        '',
                        '<?php echo esc_js(__('Insertar salto de línea', 'flacso-uruguay')); ?>',
                        102,
                        editorId
                    );
                }
            });
        })();
        </script>
        <?php
    }
}

add_action('admin_print_footer_scripts', 'flacso_print_quicktags_paragraph_buttons', 99);
