<?php
// La lectura pública de /flacso/v1/{entidad}/{id} solo entrega contenido publicado:
// un borrador, privado o auto-draft responde 404 salvo para quien puede editarlo.
$api = str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__) . '/modules/oferta-academica/includes/class-academic-api.php'));
$start = strpos($api, "'/(?P<id>\d+)'");
$block = $start === false ? '' : substr($api, $start, 900);
$checks = [
    "get_post_status(\$id) !== 'publish'" => 'comprueba que el registro esté publicado',
    "current_user_can('edit_post', \$id)" => 'deja leer no publicados solo a quien puede editarlos',
];
foreach ($checks as $needle => $what) {
    if (strpos($block, $needle) === false) {
        fwrite(STDERR, "FAIL: la lectura por ID {$what}\n");
        exit(1);
    }
}
fwrite(STDOUT, "OK academic API: lectura pública solo de publicados\n");
