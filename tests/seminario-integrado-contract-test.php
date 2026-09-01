<?php

$root = dirname(__DIR__);
$resolver = file_get_contents($root . '/modules/seminarios/includes/class-seminario-integrado.php');
$catalog = file_get_contents($root . '/modules/oferta-academica/includes/class-academic-catalog.php');
$init = file_get_contents($root . '/modules/seminarios/init.php');

function integrated_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

integrated_assert(strpos($init, 'FLACSO_Seminario_Integrado::init()') !== false, 'las reglas derivadas se inicializan');
integrated_assert(strpos($resolver, "meta_key === 'creditos'") !== false, 'los créditos del integrado son derivados');
integrated_assert(strpos($resolver, "meta_key === 'docentes'") !== false, 'los docentes de la edición integrada son derivados');
integrated_assert(strpos($resolver, "meta_key === 'encuentros_sincronicos'") !== false, 'los encuentros de la edición integrada son derivados');
integrated_assert(strpos($resolver, 'component_edition_ids') !== false, 'existe una resolución explícita de ediciones componentes');
integrated_assert(strpos($resolver, 'isset($allowed_seminars[$component_parent])') !== false, 'una edición componente debe pertenecer a un seminario componente');
integrated_assert(strpos($resolver, 'isset($used_parent[$component_parent])') !== false, 'sólo se admite una edición por seminario componente');
integrated_assert(strpos($resolver, 'array_unique') === false || strpos($resolver, 'unique_positive_ids') !== false, 'los docentes se deduplican');
integrated_assert(strpos($resolver, 'usort($meetings') !== false, 'los encuentros derivados se ordenan cronológicamente');
integrated_assert(strpos($catalog, 'comp_current') === false, 'el catálogo no elige arbitrariamente una edición vigente de cada componente');
integrated_assert(strpos($catalog, 'ediciones_componentes') !== false, 'el catálogo documenta que la fuente son las ediciones componentes');

echo "OK: contrato de seminarios integrados\n";
