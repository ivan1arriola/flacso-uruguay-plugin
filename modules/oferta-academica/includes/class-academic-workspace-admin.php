<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Capa de presentación de los editores de Ofertas y Cohortes.
 *
 * Reutiliza el formulario clásico, sus nonces, metaboxes y permisos:
 * la navegación y el acceso a guardar son exclusivamente visuales.
 */
final class FLACSO_Academic_Workspace_Admin {
    private const POST_TYPES = ['oferta-academica', 'cohorte'];

    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_filter('admin_body_class', [self::class, 'body_class']);
    }

    private static function is_editor_screen(): bool {
        $screen = get_current_screen();

        return $screen
            && $screen->base === 'post'
            && in_array($screen->post_type, self::POST_TYPES, true);
    }

    public static function body_class(string $classes): string {
        return self::is_editor_screen()
            ? $classes . ' flacso-academic-workspace-screen'
            : $classes;
    }

    public static function enqueue_assets(): void {
        if (!self::is_editor_screen()) {
            return;
        }

        $url = FLACSO_OFERTA_ACADEMICA_URL . 'assets/';
        $version = defined('FLACSO_URUGUAY_VERSION') ? (string) FLACSO_URUGUAY_VERSION : '1.0.0';

        wp_enqueue_style(
            'flacso-academic-workspace-admin',
            $url . 'css/academic-workspace-admin.css',
            [],
            $version
        );
        wp_enqueue_script(
            'flacso-academic-workspace-admin',
            $url . 'js/academic-workspace-admin.js',
            [],
            $version,
            true
        );
    }
}
