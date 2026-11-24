<?php

$root = dirname(__DIR__);
$file = file_get_contents($root . '/modules/core/includes/class-flacso-custom-404.php');

function custom_404_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

custom_404_assert(strpos($file, 'class Flacso_Custom_404') !== false, 'Flacso_Custom_404 class exists');
custom_404_assert(strpos($file, 'flacso-404-number') !== false, 'Hero visual includes flacso-404-number class');
custom_404_assert(strpos($file, '<div class="flacso-404-number">404</div>') !== false, 'Hero visual renders 404 number');
custom_404_assert(strpos($file, 'flacso-404-compass') !== false, 'Hero visual includes flacso-404-compass');
custom_404_assert(strpos($file, 'No encontramos esta página') !== false, '404 title is present');

// D4: buscador vacío (sin precarga de slug ni ruta) y cuatro accesos útiles directos (sin 301).
custom_404_assert(
    strpos($file, 'get_search_query_from_path') === false,
    '404 no deriva la consulta del buscador desde la ruta pedida'
);
custom_404_assert(
    preg_match('/id="flacso-404-search-field"[^>]*value=""/s', $file) === 1,
    '404 deja el campo de búsqueda vacío (value="")'
);
custom_404_assert(
    strpos($file, "home_url( '/' )") !== false
        && strpos($file, "home_url( '/formacion/' )") !== false
        && strpos($file, "home_url( '/institucional/investigaciones/' )") !== false
        && strpos($file, "home_url( '/contactos/' )") !== false,
    '404 enlaza Inicio, Oferta académica (/formacion/), Investigación (/institucional/investigaciones/) y Contacto (/contactos/)'
);
custom_404_assert(
    strpos($file, "home_url( '/investigacion/' )") === false
        && strpos($file, "home_url( '/contacto/' )") === false,
    '404 no usa rutas con redirección (/investigacion/ ni /contacto/)'
);
custom_404_assert(
    strpos($file, 'outline: 3px solid var(--flacso-404-blue);') !== false
        && strpos($file, 'rgba(252, 209, 22, 0.8)') === false,
    '404 usa foco visible en azul institucional con contraste, no amarillo translúcido'
);

fwrite(STDOUT, "OK custom 404 layout contract\n");

