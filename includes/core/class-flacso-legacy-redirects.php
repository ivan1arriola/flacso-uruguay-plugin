<?php
/**
 * Redirecciones temporales para URLs antiguas de preinscripciones
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FLACSO_Legacy_Redirects {
    
    public static function init() {
        add_action('template_redirect', [__CLASS__, 'handle_legacy_preinscripciones_redirects'], 1);
    }
    
    public static function handle_legacy_preinscripciones_redirects() {
        // Obtenemos el path sin query string
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (!$path) return;
        
        // Buscamos patrones: /ofertas/{slug}/preinscripcion/ o /formacion/seminarios/{slug}/preinscripcion/
        if (preg_match('#^/(ofertas|formacion/seminarios)/([^/]+)/preinscripcion/?$#i', $path, $matches)) {
            $type = $matches[1];
            $slug = $matches[2];
            
            $base_url = 'https://preinscripciones.flacso.edu.uy';
            
            if ($type === 'ofertas') {
                $redirect_url = $base_url . '/ofertas/' . $slug . '/';
            } else { // formacion/seminarios
                $redirect_url = $base_url . '/seminarios/' . $slug . '/';
            }
            
            wp_redirect($redirect_url, 301);
            exit;
        }
    }
}

FLACSO_Legacy_Redirects::init();
