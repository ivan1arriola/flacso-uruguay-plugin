<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GitHub Manual Updater para FLACSO Uruguay Plugin
 * 
 * Permite verificar y descargar actualizaciones desde GitHub manualmente.
 * No realiza actualizaciones automáticas.
 */
class FLACSO_GitHub_Updater {

    private static $instance = null;
    private $repo_owner = 'ivan1arriola';
    private $repo_name = 'flacso-uruguay-plugin';
    private $branch = 'main';
    private $transient_key = 'flacso_github_latest_version';
    private $transient_ttl = 3600; // 1 hora

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->hooks();
        }
        return self::$instance;
    }

    private function hooks() {
        add_action('admin_init', [$this, 'check_for_updates']);
        add_action('admin_notices', [$this, 'display_update_notice']);
        add_action('wp_ajax_flacso_check_github_version', [$this, 'ajax_check_version']);
        add_action('wp_ajax_flacso_download_update', [$this, 'ajax_download_update']);
    }

    /**
     * Verifica automáticamente si hay actualizaciones disponibles
     */
    public function check_for_updates() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Solo verificar una vez por hora
        $cached = get_transient($this->transient_key);
        if ($cached !== false) {
            return;
        }

        $latest = $this->fetch_latest_version();
        if ($latest) {
            set_transient($this->transient_key, $latest, $this->transient_ttl);
        }
    }

    /**
     * Muestra notificación de actualización disponible
     */
    public function display_update_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $latest = get_transient($this->transient_key);
        if (!$latest) {
            return;
        }

        $current = FLACSO_URUGUAY_VERSION;
        if (version_compare($latest, $current, '<=')) {
            return;
        }

        $nonce = wp_create_nonce('flacso_update_nonce');
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong>FLACSO Uruguay:</strong> 
                Hay una nueva versión disponible 
                (<code><?php echo esc_html($latest); ?></code>). 
                <a href="#" id="flacso-update-btn" class="button button-primary" style="margin-left: 10px;" data-nonce="<?php echo esc_attr($nonce); ?>">
                    Descargar e instalar
                </a>
            </p>
        </div>

        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                const btn = document.getElementById('flacso-update-btn');
                if (btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        flacso_perform_update(this.getAttribute('data-nonce'));
                    });
                }
            });

            function flacso_perform_update(nonce) {
                const btn = document.getElementById('flacso-update-btn');
                if (!btn) return;

                btn.disabled = true;
                btn.textContent = 'Descargando...';

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=flacso_download_update&nonce=' + encodeURIComponent(nonce)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        btn.textContent = 'Actualización completada. Recargando...';
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        btn.textContent = 'Error: ' + (data.data?.message || 'Desconocido');
                        btn.disabled = false;
                        btn.style.background = '#dc3545';
                    }
                })
                .catch(error => {
                    btn.textContent = 'Error al descargar';
                    btn.disabled = false;
                    btn.style.background = '#dc3545';
                    console.error('Error:', error);
                });
            }
        </script>
        <?php
    }

    /**
     * AJAX: Verifica versión en GitHub
     */
    public function ajax_check_version() {
        check_ajax_referer('flacso_update_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permiso denegado']);
        }

        $latest = $this->fetch_latest_version();
        $current = FLACSO_URUGUAY_VERSION;

        if (!$latest) {
            wp_send_json_error(['message' => 'No se pudo conectar a GitHub']);
        }

        if (version_compare($latest, $current, '<=')) {
            wp_send_json_success([
                'current' => $current,
                'latest' => $latest,
                'has_update' => false,
                'message' => 'Ya tienes la última versión',
            ]);
        }

        wp_send_json_success([
            'current' => $current,
            'latest' => $latest,
            'has_update' => true,
            'message' => "Nueva versión disponible: {$latest}",
        ]);
    }

    /**
     * AJAX: Descarga e instala la actualización
     */
    public function ajax_download_update() {
        check_ajax_referer('flacso_update_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permiso denegado']);
        }

        $latest = $this->fetch_latest_version();

        if (!$latest) {
            wp_send_json_error(['message' => 'No se pudo obtener la versión más reciente']);
        }

        $download_url = $this->get_download_url($latest);

        if (!$download_url) {
            wp_send_json_error(['message' => 'No se pudo generar la URL de descarga']);
        }

        // Descargar el archivo
        $response = wp_remote_get($download_url, [
            'timeout' => 30,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Error descargando: ' . $response->get_error_message()]);
        }

        $body = wp_remote_retrieve_body($response);

        if (empty($body)) {
            wp_send_json_error(['message' => 'La descarga está vacía']);
        }

        // Crear directorio temporal
        $upload_dir = wp_get_upload_dir();
        $temp_dir = trailingslashit($upload_dir['basedir']) . 'flacso-updates';

        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        $zip_file = $temp_dir . '/flacso-update-' . time() . '.zip';

        // Guardar ZIP
        if (file_put_contents($zip_file, $body) === false) {
            wp_send_json_error(['message' => 'No se pudo guardar el archivo descargado']);
        }

        // Extraer
        $extract_dir = $temp_dir . '/flacso-extract-' . time();

        if (!class_exists('ZipArchive')) {
            wp_send_json_error(['message' => 'ZipArchive no disponible en este servidor']);
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_file) !== true) {
            wp_send_json_error(['message' => 'No se pudo abrir el archivo ZIP']);
        }

        if (!$zip->extractTo($extract_dir)) {
            wp_send_json_error(['message' => 'No se pudo extraer el archivo']);
        }

        $zip->close();

        // Buscar la carpeta del repositorio (puede tener nombre flacso-uruguay-plugin-main)
        $dirs = glob($extract_dir . '/flacso-uruguay-plugin*', GLOB_ONLYDIR);

        if (empty($dirs)) {
            wp_send_json_error(['message' => 'No se encontró la estructura del plugin en el ZIP']);
        }

        $source_dir = $dirs[0];
        $plugin_dir = dirname(FLACSO_URUGUAY_PATH);

        // Backup del plugin actual
        $backup_dir = $temp_dir . '/backup-' . time();
        if (!wp_mkdir_p($backup_dir)) {
            wp_send_json_error(['message' => 'No se pudo crear backup']);
        }

        // Copiar archivos nuevos (excepto la carpeta de datos)
        if (!$this->copy_recursive($source_dir, FLACSO_URUGUAY_PATH, ['uploads', '.git', 'node_modules'])) {
            wp_send_json_error(['message' => 'Error al copiar archivos. El plugin puede estar corrupto.']);
        }

        // Limpiar temporales
        $this->delete_recursive($extract_dir);
        @unlink($zip_file);

        // Limpiar transiente de versión
        delete_transient($this->transient_key);

        wp_send_json_success([
            'message' => "Plugin actualizado a versión {$latest}. Recargando...",
            'version' => $latest,
        ]);
    }

    /**
     * Obtiene la versión más reciente desde GitHub
     */
    private function fetch_latest_version() {
        $api_url = "https://api.github.com/repos/{$this->repo_owner}/{$this->repo_name}/releases/latest";

        $response = wp_remote_get($api_url, [
            'timeout' => 10,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'headers' => [],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['tag_name'])) {
            return null;
        }

        // Limpia el tag_name (v1.0.0 -> 1.0.0)
        $version = ltrim($data['tag_name'], 'v');

        return $version;
    }

    /**
     * Genera URL de descarga del repositorio
     */
    private function get_download_url($version) {
        return "https://github.com/{$this->repo_owner}/{$this->repo_name}/archive/refs/heads/{$this->branch}.zip";
    }

    /**
     * Copia recursivamente un directorio
     */
    private function copy_recursive($src, $dst, $exclude = []) {
        $dir = opendir($src);
        if (!$dir) {
            return false;
        }

        if (!file_exists($dst)) {
            wp_mkdir_p($dst);
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..' || in_array($file, $exclude, true)) {
                continue;
            }

            $src_path = $src . '/' . $file;
            $dst_path = $dst . '/' . $file;

            if (is_dir($src_path)) {
                if (!$this->copy_recursive($src_path, $dst_path, $exclude)) {
                    closedir($dir);
                    return false;
                }
            } else {
                if (!copy($src_path, $dst_path)) {
                    closedir($dir);
                    return false;
                }
            }
        }

        closedir($dir);
        return true;
    }

    /**
     * Elimina recursivamente un directorio
     */
    private function delete_recursive($dir) {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return @unlink($dir);
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                if (!$this->delete_recursive($path)) {
                    return false;
                }
            } else {
                if (!@unlink($path)) {
                    return false;
                }
            }
        }

        return @rmdir($dir);
    }
}

// Inicializar
FLACSO_Github_Updater::init();
