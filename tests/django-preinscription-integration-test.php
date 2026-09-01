<?php

$root = dirname(__DIR__);
$main = file_get_contents($root . '/modules/oferta-academica/init.php');
$client = file_get_contents($root . '/modules/oferta-academica/includes/class-django-api-client.php');
$ajax = file_get_contents($root . '/modules/oferta-academica/includes/class-django-ajax-handlers.php');
$cohorte = file_get_contents($root . '/modules/oferta-academica/includes/class-cohorte.php');
$edicion = file_get_contents($root . '/modules/seminarios/includes/class-edicion.php');
$catalog = file_get_contents($root . '/modules/oferta-academica/includes/class-academic-catalog.php');

function django_pre_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

django_pre_assert(strpos($main, 'class-django-api-client.php') !== false, 'se carga el cliente Django');
django_pre_assert(strpos($main, 'class-django-ajax-handlers.php') !== false, 'se cargan los handlers AJAX');
django_pre_assert(strpos($client, "'Authorization' => 'Bearer '") !== false, 'el cliente usa Bearer token');
django_pre_assert(strpos($client, '): array|WP_Error') === false, 'el cliente conserva compatibilidad con PHP 7.4');
django_pre_assert(strpos($client, 'FLACSO_Oferta_Academica::get_tipo') !== false, 'el tipo sale de la taxonomía canónica');

foreach ([
    'flacso_abrir_preinscripcion_cohorte',
    'flacso_cerrar_preinscripcion_cohorte',
    'flacso_abrir_preinscripcion_edicion',
    'flacso_cerrar_preinscripcion_edicion',
] as $action) {
    django_pre_assert(strpos($ajax, $action) !== false, "falta la acción {$action}");
}

django_pre_assert(strpos($ajax, 'set_single_open_instance') !== false, 'WordPress cierra instancias hermanas');
django_pre_assert(strpos($ajax, "current_user_can('edit_post'") !== false, 'los handlers validan permiso por registro');
django_pre_assert(strpos($cohorte, 'No configurada') !== false, 'Cohorte distingue el estado no configurado');
django_pre_assert(strpos($edicion, 'No configurada') !== false, 'Edición distingue el estado no configurado');
django_pre_assert(strpos($edicion, "'preinscripcion_habilitada'") !== false, 'Edición registra el booleano de apertura');
django_pre_assert(strpos($edicion, 'flacso-abrir-preinscripcion-edicion') !== false, 'Edición muestra el control de apertura');
django_pre_assert(substr_count($catalog, "'estado'              => 'preinscripciones_abiertas'") === 2, 'el catálogo defensivo declara solo instancias abiertas');

fwrite(STDOUT, "OK Django preinscription integration contract\n");
