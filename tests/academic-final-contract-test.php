<?php

$root = dirname(__DIR__);
$main = file_get_contents($root . '/flacso-uruguay.php');
$offer = file_get_contents($root . '/modules/oferta-academica/includes/class-oferta-academica.php');
$api = file_get_contents($root . '/modules/oferta-academica/includes/class-academic-api.php');
$repository = file_get_contents($root . '/modules/oferta-academica/includes/class-academic-repositories.php');
$documentation = file_get_contents($root . '/docs/modelo-academico-final.md');

function contract_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

contract_assert(strpos($main, "load_module('instancias-oferta')") === false, 'no se carga InstanciaOferta');
contract_assert(strpos($main, "load_module('preinscripcion')") === false, 'no se carga formulario local');
contract_assert(strpos($offer, 'TIPO_SEMINARIO') === false, 'Seminario está separado de OfertaAcademica');
contract_assert(strpos($api, "'/preinscripciones/catalogo'") !== false, 'existe catálogo externo');
contract_assert(strpos($repository, 'https://preinscripciones.flacso.edu.uy') !== false, 'destino externo único');
contract_assert(strpos($documentation, 'especializacion') !== false, 'modelo documenta especializacion');
contract_assert(!is_dir($root . '/modules/instancias-oferta') || count(glob($root . '/modules/instancias-oferta/*')) === 0, 'módulo genérico eliminado');

fwrite(STDOUT, "OK academic final contract\n");
