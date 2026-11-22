<?php
/**
 * Superficie pública de admin-ajax: el buscador de convenios por distancia no tiene
 * ningún cliente (el listado filtra en el navegador) y no debe seguir expuesto sin sesión.
 */

$root   = dirname(__DIR__);
$source = (string) file_get_contents($root . '/modules/main-page/sections/convenios-responsivos.php');

function ajax_surface_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

ajax_surface_assert('' !== $source, 'Debe existir convenios-responsivos.php');
ajax_surface_assert(strpos($source, 'wp_ajax_nopriv_buscar_convenios_distancia') === false, 'No debe registrarse la acción pública buscar_convenios_distancia');
ajax_surface_assert(strpos($source, 'wp_ajax_buscar_convenios_distancia') === false, 'No debe registrarse la acción buscar_convenios_distancia');
ajax_surface_assert(strpos($source, 'flacso_ajax_buscar_convenios_distancia') === false, 'No debe quedar el handler muerto flacso_ajax_buscar_convenios_distancia');
ajax_surface_assert(strpos($source, "add_shortcode('convenios_responsivos'") !== false, 'El shortcode convenios_responsivos debe seguir registrado');

echo "OK convenios-responsivos: sin AJAX público muerto\n";
