<?php
/**
 * Plugin Name: FLACSO Uruguay - Plataforma Integrada
 * Plugin URI: https://flacso.edu.uy
 * Description: Plataforma integrada de FLACSO Uruguay con gestion de docentes, seminarios, eventos, oferta academica y formularios. Consolida multiples plugins en una arquitectura modular.
 * Version: 4.2.6
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: FLACSO Uruguay
 * Author URI: https://flacso.edu.uy
 * Text Domain: flacso-uruguay
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// Constantes Globales
// ============================================
define('FLACSO_URUGUAY_VERSION', '4.2.6');
define('FLACSO_URUGUAY_FILE', __FILE__);
define('FLACSO_URUGUAY_PATH', plugin_dir_path(__FILE__));
define('FLACSO_URUGUAY_URL', plugin_dir_url(__FILE__));

// Compatibilidad con plugins antiguos
define('CPT_DOCENTES_VERSION', FLACSO_URUGUAY_VERSION);
define('CPT_DOCENTES_PATH', FLACSO_URUGUAY_PATH);
define('CPT_DOCENTES_URL', FLACSO_URUGUAY_URL);

define('FLACSO_SEMINARIO_VERSION', FLACSO_URUGUAY_VERSION);
// En el plugin unificado, los assets y templates de seminarios
// viven dentro del modulo `modules/seminarios/`, no en la raiz.
// Ajustamos las constantes de compatibilidad para que apunten ahi,
// de modo que `Seminario_Templates` encuentre correctamente
// `templates/single-seminario.php`, `seminarios-listado.php`, etc.
define('FLACSO_SEMINARIO_PATH', FLACSO_URUGUAY_PATH . 'modules/seminarios/');
define('FLACSO_SEMINARIO_URL', FLACSO_URUGUAY_URL . 'modules/seminarios/');

define('CPT_EVENTOS_VERSION', FLACSO_URUGUAY_VERSION);
define('CPT_EVENTOS_PATH', FLACSO_URUGUAY_PATH);
define('CPT_EVENTOS_URL', FLACSO_URUGUAY_URL);

define('FLACSO_OFERTA_ACADEMICA_VERSION', FLACSO_URUGUAY_VERSION);
define('FLACSO_OFERTA_ACADEMICA_PATH', FLACSO_URUGUAY_PATH);
define('FLACSO_OFERTA_ACADEMICA_URL', FLACSO_URUGUAY_URL);

define('FLACSO_POSGRADOS_SLUG', 'flacso-posgrados-docentes');
define('FLACSO_POSGRADOS_PLUGIN_PATH', FLACSO_URUGUAY_PATH);

// ============================================
// Carga de funciones principales
// ============================================
require_once FLACSO_URUGUAY_PATH . 'includes/core/helpers.php';
require_once FLACSO_URUGUAY_PATH . 'includes/core/loader.php';

if (!function_exists('flacso_uruguay_guard_encoding_on_upgrade_source')) {
    /**
     * Bloquea instalacion/actualizacion del plugin si detecta BOM o mojibake.
     *
     * @param string|WP_Error $source Ruta temporal del paquete descomprimido.
     * @param string          $remote_source Ruta temporal remota (no usada).
     * @param WP_Upgrader     $upgrader Instancia del upgrader.
     * @param array           $hook_extra Metadatos de la operacion.
     * @return string|WP_Error
     */
    function flacso_uruguay_guard_encoding_on_upgrade_source($source, $remote_source, $upgrader, $hook_extra) {
        if (is_wp_error($source)) {
            return $source;
        }

        if (!flacso_uruguay_should_guard_plugin_package($hook_extra, $source)) {
            return $source;
        }

        $issues = flacso_uruguay_scan_package_encoding_issues((string) $source);
        if (count($issues) === 0) {
            return $source;
        }

        $max_feedback = 20;
        if (is_object($upgrader) && isset($upgrader->skin) && is_object($upgrader->skin) && method_exists($upgrader->skin, 'feedback')) {
            $upgrader->skin->feedback('Encoding guard: se detectaron BOM/mojibake y se cancela la instalacion.');
            foreach (array_slice($issues, 0, $max_feedback) as $issue) {
                $upgrader->skin->feedback(' - ' . $issue);
            }

            if (count($issues) > $max_feedback) {
                $upgrader->skin->feedback(
                    sprintf(' - ... y %d problema(s) adicional(es).', count($issues) - $max_feedback)
                );
            }
        }

        return new WP_Error(
            'flacso_uruguay_encoding_guard_failed',
            sprintf(
                __('Actualizacion cancelada: se detectaron %d problema(s) de codificacion (BOM/mojibake/UTF-8).', 'flacso-uruguay'),
                count($issues)
            ),
            [
                'issues' => $issues,
            ]
        );
    }
}

if (!function_exists('flacso_uruguay_should_guard_plugin_package')) {
    /**
     * Determina si la operacion de upgrader corresponde al plugin FLACSO.
     *
     * @param array          $hook_extra Metadatos del upgrader.
     * @param string|WP_Error $source Ruta del paquete descomprimido.
     * @return bool
     */
    function flacso_uruguay_should_guard_plugin_package($hook_extra, $source) {
        if (!is_array($hook_extra)) {
            return false;
        }

        $action = isset($hook_extra['action']) ? (string) $hook_extra['action'] : '';
        $type = isset($hook_extra['type']) ? (string) $hook_extra['type'] : '';
        if ($type !== 'plugin' || ($action !== 'update' && $action !== 'install')) {
            return false;
        }

        $plugin_basename = plugin_basename(FLACSO_URUGUAY_FILE);
        if (isset($hook_extra['plugin']) && (string) $hook_extra['plugin'] === $plugin_basename) {
            return true;
        }

        if (isset($hook_extra['plugins']) && is_array($hook_extra['plugins']) && in_array($plugin_basename, $hook_extra['plugins'], true)) {
            return true;
        }

        return flacso_uruguay_package_matches_plugin((string) $source);
    }
}

if (!function_exists('flacso_uruguay_package_matches_plugin')) {
    /**
     * Valida que el paquete corresponde a este plugin.
     *
     * @param string $source Ruta temporal del paquete.
     * @return bool
     */
    function flacso_uruguay_package_matches_plugin($source) {
        $source = rtrim((string) $source, "/\\");
        if ($source === '' || !is_dir($source)) {
            return false;
        }

        $main_file = $source . DIRECTORY_SEPARATOR . 'flacso-uruguay.php';
        if (!is_file($main_file)) {
            return false;
        }

        $header = @file_get_contents($main_file, false, null, 0, 4096);
        if ($header === false) {
            return false;
        }

        return strpos($header, 'Plugin Name: FLACSO Uruguay') !== false
            || strpos($header, "define('FLACSO_URUGUAY_VERSION'") !== false;
    }
}

if (!function_exists('flacso_uruguay_scan_package_encoding_issues')) {
    /**
     * Escanea recursivamente el paquete para detectar problemas de encoding.
     *
     * @param string $source_root Ruta temporal del paquete.
     * @return array
     */
    function flacso_uruguay_scan_package_encoding_issues($source_root) {
        $source_root = rtrim((string) $source_root, "/\\");
        if ($source_root === '' || !is_dir($source_root)) {
            return ['No se pudo escanear el paquete descargado.'];
        }

        $issues = [];
        $flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source_root, $flags),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
        } catch (UnexpectedValueException $exception) {
            return [sprintf('No se pudo escanear el paquete descargado: %s', $exception->getMessage())];
        }

        foreach ($iterator as $file_info) {
            if (!$file_info->isFile()) {
                continue;
            }

            $file_path = $file_info->getPathname();
            $relative_path = ltrim(str_replace(['\\', $source_root], ['/', ''], $file_path), '/');
            if (!flacso_uruguay_is_scan_candidate($relative_path)) {
                continue;
            }

            $raw = @file_get_contents($file_path);
            if ($raw === false) {
                $issues[] = sprintf('%s: no se pudo leer el archivo.', $relative_path);
                continue;
            }

            $bom_name = flacso_uruguay_detect_bom_name($raw);
            if ($bom_name !== '') {
                $issues[] = sprintf('%s: contiene %s.', $relative_path, $bom_name);
            }

            if (!flacso_uruguay_is_valid_utf8_bytes($raw)) {
                $issues[] = sprintf('%s: UTF-8 invalido.', $relative_path);
                continue;
            }

            $lines = preg_split('/\R/u', $raw);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $line_number => $line) {
                if (!flacso_uruguay_has_mojibake_pattern($line)) {
                    continue;
                }

                $issues[] = sprintf(
                    '%s:%d: posible mojibake -> %s',
                    $relative_path,
                    (int) $line_number + 1,
                    flacso_uruguay_trim_issue_snippet((string) $line)
                );
            }
        }

        return $issues;
    }
}

if (!function_exists('flacso_uruguay_is_scan_candidate')) {
    /**
     * Define si un archivo debe incluirse en el escaneo.
     *
     * @param string $relative_path Ruta relativa.
     * @return bool
     */
    function flacso_uruguay_is_scan_candidate($relative_path) {
        $normalized = str_replace('\\', '/', (string) $relative_path);
        if ($normalized === '') {
            return false;
        }

        static $excluded_dirs = [
            '.git',
            '.svn',
            '.hg',
            'node_modules',
            'vendor',
            'dist',
            'build',
            '.idea',
            '.vscode',
        ];

        $parts = explode('/', strtolower($normalized));
        foreach ($parts as $part) {
            if (in_array($part, $excluded_dirs, true)) {
                return false;
            }
        }

        static $binary_extensions = [
            '.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg', '.ico', '.bmp', '.tiff',
            '.pdf', '.zip', '.gz', '.tar', '.7z', '.rar', '.phar',
            '.mp3', '.wav', '.ogg', '.mp4', '.mov', '.avi', '.mkv',
            '.woff', '.woff2', '.ttf', '.otf', '.eot',
            '.exe', '.dll', '.so', '.dylib', '.class', '.jar',
            '.lock',
        ];

        static $text_extensions = [
            '.php', '.phtml', '.inc',
            '.js', '.jsx', '.ts', '.tsx', '.mjs', '.cjs',
            '.css', '.scss', '.sass', '.less',
            '.html', '.htm', '.xml',
            '.json', '.yml', '.yaml', '.toml', '.ini', '.cfg',
            '.md', '.txt', '.csv', '.sql',
            '.sh', '.bash', '.zsh', '.ps1', '.bat',
            '.gitignore', '.gitattributes', '.editorconfig',
        ];

        $basename = strtolower((string) basename($normalized));
        $extension = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));
        $suffix = $extension !== '' ? '.' . $extension : '';

        if (in_array($suffix, $binary_extensions, true)) {
            return false;
        }

        if (in_array($suffix, $text_extensions, true) || in_array($basename, $text_extensions, true)) {
            return true;
        }

        return $suffix === '' && preg_match('/^[\x00-\x7F]+$/', $basename) === 1;
    }
}

if (!function_exists('flacso_uruguay_detect_bom_name')) {
    /**
     * Detecta BOM por firma de bytes.
     *
     * @param string $raw Contenido crudo del archivo.
     * @return string
     */
    function flacso_uruguay_detect_bom_name($raw) {
        static $signatures = [
            "\x00\x00\xFE\xFF" => 'UTF-32 BE BOM',
            "\xFF\xFE\x00\x00" => 'UTF-32 LE BOM',
            "\xEF\xBB\xBF" => 'UTF-8 BOM',
            "\xFE\xFF" => 'UTF-16 BE BOM',
            "\xFF\xFE" => 'UTF-16 LE BOM',
        ];

        foreach ($signatures as $signature => $name) {
            if (strncmp($raw, $signature, strlen($signature)) === 0) {
                return $name;
            }
        }

        return '';
    }
}

if (!function_exists('flacso_uruguay_is_valid_utf8_bytes')) {
    /**
     * Valida que el contenido sea UTF-8.
     *
     * @param string $raw Contenido crudo.
     * @return bool
     */
    function flacso_uruguay_is_valid_utf8_bytes($raw) {
        if (function_exists('mb_check_encoding')) {
            return mb_check_encoding($raw, 'UTF-8');
        }

        return preg_match('//u', $raw) === 1;
    }
}

if (!function_exists('flacso_uruguay_has_mojibake_pattern')) {
    /**
     * Busca patrones de mojibake mas frecuentes.
     *
     * @param string $line Linea a analizar.
     * @return bool
     */
    function flacso_uruguay_has_mojibake_pattern($line) {
        static $patterns = [
            '/\xC3\x83\xC2[\x80-\xBF]/',
            '/\xC3\x82\xC2[\x80-\xBF]/',
            '/\xC3\x83\xC2\xA2\xC3\xA2\xE2\x80\x9A\xC2\xAC/',
            '/\xC3\x83\xC2\xAF\xC3\x82\xC2\xBB\xC3\x82\xC2\xBF/',
            '/\xEF\xBF\xBD/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line) === 1) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('flacso_uruguay_trim_issue_snippet')) {
    /**
     * Acorta el snippet reportado para feedback de errores.
     *
     * @param string $line Linea original.
     * @param int    $max_length Longitud maxima.
     * @return string
     */
    function flacso_uruguay_trim_issue_snippet($line, $max_length = 140) {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $line));
        if ($normalized === '') {
            return '[linea vacia]';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($normalized, 0, (int) $max_length, 'UTF-8');
        }

        return substr($normalized, 0, (int) $max_length);
    }
}

add_filter('upgrader_source_selection', 'flacso_uruguay_guard_encoding_on_upgrade_source', 10, 4);

// ============================================
// Inicializacion del Plugin
// ============================================
class FLACSO_Uruguay_Plugin {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Cargar modulos
        add_action('plugins_loaded', [$this, 'load_modules'], 10);
        
        // Cargar idiomas
        add_action('plugins_loaded', [$this, 'load_textdomain'], 5);
        
        // Registrar categorias de bloques
        add_filter('block_categories_all', [$this, 'register_block_categories'], 10, 2);
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'flacso-uruguay',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    public function register_block_categories($categories, $context) {
        // Obtener slugs existentes para evitar duplicados
        $existing_slugs = wp_list_pluck($categories, 'slug');
        
        // Registrar categoria principal de FLACSO Uruguay
        if (!in_array('flacso-uruguay', $existing_slugs, true)) {
            array_unshift($categories, [
                'slug'  => 'flacso-uruguay',
                'title' => __('FLACSO Uruguay', 'flacso-uruguay'),
                'icon'  => null
            ]);
        }
        
        return $categories;
    }
    
    public function load_modules() {
        $loader = FLACSO_Uruguay_Loader::instance();
        
        // Cargar modulos en orden de dependencias
        $loader->load_module('core');      // Funciones base
        $loader->load_module('docentes');  // CPT Docentes
        $loader->load_module('seminarios'); // CPT Seminarios
        $loader->load_module('eventos');    // CPT Eventos
        $loader->load_module('oferta-academica'); // Oferta Academica
        $loader->load_module('formularios'); // Formularios
        $loader->load_module('charlas-abiertas'); // Charlas Abiertas
        $loader->load_module('posgrados');  // Posgrados
        $loader->load_module('shortcodes'); // Shortcodes
        $loader->load_module('main-page');  // Landing Page y Secciones
        $loader->load_module('preinscripcion'); // Formularios de Preinscripcion
    }
    
    public static function activate() {
        // Logica de activacion
        flush_rewrite_rules();
    }
    
    public static function deactivate() {
        // Logica de desactivacion
        flush_rewrite_rules();
    }
}

// Inicializar el plugin
FLACSO_Uruguay_Plugin::instance();

// Hooks de activacion/desactivacion
register_activation_hook(__FILE__, ['FLACSO_Uruguay_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['FLACSO_Uruguay_Plugin', 'deactivate']);
