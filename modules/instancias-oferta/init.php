<?php

if (!defined('ABSPATH')) {
    exit;
}

flacso_safe_require('modules/instancias-oferta/includes/class-preinscription-flow.php');
flacso_safe_require('modules/instancias-oferta/includes/class-instancia-oferta.php');
flacso_safe_require('modules/instancias-oferta/includes/class-preinscription-url-resolver.php');
flacso_safe_require('modules/instancias-oferta/includes/class-instancia-oferta-api.php');
flacso_safe_require('modules/instancias-oferta/includes/class-preinscription-catalog-api.php');

add_action('init', [FLACSO_Instancia_Oferta::class, 'init'], 6);
add_action('init', [FLACSO_Instancia_Oferta::class, 'migrate_defaults'], 20);
FLACSO_Instancia_Oferta_API::init();
FLACSO_Preinscription_Catalog_API::init();

add_filter('flacso_seminario_preinscription_url', static function ($legacy_url, $seminar_id) {
    $instance_id = flacso_get_open_preinscription_instance(absint($seminar_id));
    return $instance_id > 0 ? flacso_get_preinscription_url($instance_id, absint($seminar_id)) : $legacy_url;
}, 20, 2);
