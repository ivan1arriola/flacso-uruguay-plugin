<?php
/**
 * Plugin Name: FLACSO Uruguay - Plataforma Integrada
 * Plugin URI: https://flacso.edu.uy
 * Description: Plataforma integrada de FLACSO Uruguay con gestión de docentes, seminarios, eventos, oferta académica y formularios.
 * Version: 6.9.15
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

// El bootstrap define únicamente constantes verdaderamente globales. Cada
// dominio es responsable de sus propias rutas/constantes de compatibilidad.
define('FLACSO_URUGUAY_VERSION', '6.9.15');
define('FLACSO_URUGUAY_FILE', __FILE__);
define('FLACSO_URUGUAY_PATH', plugin_dir_path(__FILE__));
define('FLACSO_URUGUAY_URL', plugin_dir_url(__FILE__));

require_once FLACSO_URUGUAY_PATH . 'includes/core/helpers.php';
require_once FLACSO_URUGUAY_PATH . 'includes/core/class-flacso-editor-admin-mode.php';
require_once FLACSO_URUGUAY_PATH . 'includes/core/class-flacso-integrations-settings.php';
require_once FLACSO_URUGUAY_PATH . 'includes/core/class-flacso-meta-tracking.php';
require_once FLACSO_URUGUAY_PATH . 'includes/core/class-flacso-meta-leads-webhook.php';
require_once FLACSO_URUGUAY_PATH . 'includes/core/loader.php';
require_once FLACSO_URUGUAY_PATH . 'includes/core/class-flacso-module-registry.php';

final class FLACSO_Uruguay_Plugin {
    private static $instance = null;

    /** @var bool */
    private $modules_loaded = false;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'load_textdomain'], 5);
        add_action('plugins_loaded', [$this, 'load_modules'], 10);
        add_filter('block_categories_all', [$this, 'register_block_categories'], 10, 2);
        add_filter('wp_resource_hints', [$this, 'add_resource_hints'], 10, 2);
    }

    public function load_textdomain(): void {
        load_plugin_textdomain(
            'flacso-uruguay',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    public function load_modules(): void {
        if ($this->modules_loaded) {
            return;
        }

        $this->modules_loaded = true;
        FLACSO_Uruguay_Module_Registry::boot(FLACSO_Uruguay_Loader::instance());
    }

    public function register_block_categories(array $categories, $context): array {
        $existing_slugs = wp_list_pluck($categories, 'slug');
        if (!in_array('flacso-uruguay', $existing_slugs, true)) {
            array_unshift($categories, [
                'slug' => 'flacso-uruguay',
                'title' => __('FLACSO Uruguay', 'flacso-uruguay'),
                'icon' => null,
            ]);
        }
        return $categories;
    }

    public function add_resource_hints(array $hints, string $relation_type): array {
        if ('preconnect' !== $relation_type) {
            return $hints;
        }

        $hints[] = 'https://fonts.googleapis.com';
        $hints[] = [
            'href' => 'https://fonts.gstatic.com',
            'crossorigin' => 'anonymous',
        ];
        $hints[] = [
            'href' => 'https://cdn.jsdelivr.net',
            'crossorigin' => 'anonymous',
        ];

        return $hints;
    }

    public static function activate(): void {
        // Los módulos pueden enganchar tareas de activación sin acoplar el
        // bootstrap a sus clases concretas.
        do_action('flacso_uruguay_activate');
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        do_action('flacso_uruguay_deactivate');
        flush_rewrite_rules();
    }
}

FLACSO_Uruguay_Plugin::instance();

register_activation_hook(__FILE__, ['FLACSO_Uruguay_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['FLACSO_Uruguay_Plugin', 'deactivate']);
