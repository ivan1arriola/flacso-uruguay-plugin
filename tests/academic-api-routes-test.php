<?php

$source = file_get_contents(dirname(__DIR__) . '/modules/oferta-academica/includes/class-academic-repositories.php');
$api = file_get_contents(dirname(__DIR__) . '/modules/oferta-academica/includes/class-academic-api.php');

$entities = ['programas-academicos', 'ofertas', 'cohortes', 'seminarios', 'ediciones-seminario', 'tablas-precio'];
foreach ($entities as $entity) {
    if (strpos($source, "'{$entity}' =>") === false) {
        fwrite(STDERR, "Fallo: falta entidad REST {$entity}\n");
        exit(1);
    }
}
foreach (['READABLE', 'CREATABLE', 'EDITABLE', 'DELETABLE'] as $method) {
    if (strpos($api, 'WP_REST_Server::' . $method) === false) {
        fwrite(STDERR, "Fallo: falta método {$method}\n");
        exit(1);
    }
}
if (strpos($api, "wp_trash_post($id)") === false || strpos($api, "wp_delete_post($id, true)") === false) {
    fwrite(STDERR, "Fallo: DELETE debe usar papelera salvo force=true\n");
    exit(1);
}

fwrite(STDOUT, "OK academic API routes\n");
