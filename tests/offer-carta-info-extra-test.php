<?php
/** «Información extra» de la carta (antes carta_presentacion_html): nombre nuevo y compatibilidad. */

$repositories = (string) file_get_contents(dirname(__DIR__) . '/modules/oferta-academica/includes/class-academic-repositories.php');

function info_extra_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$fields = substr($repositories, (int) strpos($repositories, "'ofertas' => ["), 1400);
info_extra_assert(strpos($fields, "'carta_info_extra_html'") !== false, 'la oferta expone carta_info_extra_html');
info_extra_assert(strpos($fields, "'carta_presentacion_html'") === false, 'el nombre anterior ya no es un campo de la oferta');
info_extra_assert(strpos($repositories, "\$payload['carta_info_extra_html'] = \$payload['carta_presentacion_html'];") !== false, 'la API sigue aceptando el nombre anterior');
info_extra_assert(strpos($repositories, "get_post_meta(\$post->ID, 'carta_presentacion_html', true)") !== false, 'se lee el valor guardado con el nombre anterior mientras no se migre');
info_extra_assert(strpos($repositories, "delete_post_meta(\$post_id, 'carta_presentacion_html')") !== false, 'al guardar se retira el valor anterior, para que no reaparezca');

echo "OK offer carta info extra\n";
