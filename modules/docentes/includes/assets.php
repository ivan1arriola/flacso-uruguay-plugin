<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('dp_docentes_register_assets')) {
    function dp_docentes_register_assets(): void {
        static $registered = false;
        if ($registered) {
            return;
        }

        $bootstrap_css_rel = 'modules/docentes/assets/css/docentes-bootstrap-scoped.min.css';
        $templates_css_rel = 'modules/docentes/assets/css/docentes-templates.css';
        $directory_js_rel  = 'modules/docentes/assets/js/docentes-directory.js';

        $bootstrap_css_url = FLACSO_URUGUAY_URL . $bootstrap_css_rel;
        $templates_css_url = FLACSO_URUGUAY_URL . $templates_css_rel;
        $directory_js_url  = FLACSO_URUGUAY_URL . $directory_js_rel;

        $bootstrap_css_ver = function_exists('dp_docentes_asset_version')
            ? dp_docentes_asset_version($bootstrap_css_rel)
            : (string) @filemtime(FLACSO_URUGUAY_PATH . $bootstrap_css_rel);
        $templates_css_ver = function_exists('dp_docentes_asset_version')
            ? dp_docentes_asset_version($templates_css_rel)
            : (string) @filemtime(FLACSO_URUGUAY_PATH . $templates_css_rel);
        $directory_js_ver  = function_exists('dp_docentes_asset_version')
            ? dp_docentes_asset_version($directory_js_rel)
            : (string) @filemtime(FLACSO_URUGUAY_PATH . $directory_js_rel);

        wp_register_style(
            'flacso-docentes-bootstrap',
            $bootstrap_css_url,
            [],
            $bootstrap_css_ver
        );

        wp_register_style(
            'flacso-docentes-bootstrap-icons',
            'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
            ['flacso-docentes-bootstrap'],
            '1.11.3'
        );

        wp_register_style(
            'flacso-docentes-templates',
            $templates_css_url,
            ['flacso-docentes-bootstrap', 'flacso-docentes-bootstrap-icons'],
            $templates_css_ver
        );

        wp_register_style(
            'bootstrap-avatar',
            'https://cdn.jsdelivr.net/npm/bootstrap-avatar@latest/dist/avatar.min.css',
            ['flacso-docentes-bootstrap'],
            null
        );

        wp_register_script(
            'flacso-docentes-bootstrap-js',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
            [],
            '5.3.3',
            true
        );

        wp_register_script(
            'flacso-docentes-directory',
            $directory_js_url,
            ['flacso-docentes-bootstrap-js'],
            $directory_js_ver,
            true
        );

        $registered = true;
    }
}

if (!function_exists('dp_docentes_enqueue_assets')) {
    function dp_docentes_enqueue_assets(): void {
        static $enqueued = false;
        if ($enqueued) {
            return;
        }

        if (function_exists('dp_docentes_register_assets')) {
            dp_docentes_register_assets();
        }

        wp_enqueue_style('flacso-docentes-bootstrap');
        wp_enqueue_style('flacso-docentes-bootstrap-icons');
        wp_enqueue_style('flacso-docentes-templates');
        wp_enqueue_style('bootstrap-avatar');

        wp_enqueue_script('flacso-docentes-bootstrap-js');
        wp_enqueue_script('flacso-docentes-directory');

        $enqueued = true;
    }
}

