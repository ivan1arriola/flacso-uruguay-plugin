<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Descubre contratos REST por modulo sin acoplar el core a dominios concretos.
 *
 * Cada modulo puede publicar `includes/rest-dto.php`. Los contratos se cargan
 * despues de los modulos para que puedan apoyarse en sus clases existentes,
 * pero antes de atender peticiones REST normales.
 */
final class FLACSO_REST_DTO_Loader {
    public static function init(): void {
        add_action('plugins_loaded', [self::class, 'load_module_dtos'], 20);
    }

    public static function load_module_dtos(): void {
        $pattern = FLACSO_URUGUAY_PATH . 'modules/*/includes/rest-dto.php';
        $files = glob($pattern);

        if (!is_array($files)) {
            return;
        }

        sort($files, SORT_STRING);

        foreach ($files as $file) {
            if (is_readable($file)) {
                require_once $file;
            }
        }
    }
}

FLACSO_REST_DTO_Loader::init();
