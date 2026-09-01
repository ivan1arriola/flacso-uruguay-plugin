<?php

$root = dirname(__DIR__);
$cpt_prog = file_get_contents($root . '/modules/oferta-academica/includes/class-cpt-programa-academico.php');
$theme_dir = dirname($root) . '/kadence-child-flacso';
$archive_prog = file_get_contents($theme_dir . '/archive-programa-academico.php');
$single_prog = file_get_contents($theme_dir . '/single-programa-academico.php');

function prog_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

prog_assert(strpos($cpt_prog, "'has_archive'        => 'programas'") !== false, "Programa Academico tiene has_archive programas");
prog_assert(strpos($cpt_prog, "'rewrite'            => ['slug' => 'programas'") !== false, "Programa Academico tiene rewrite slug programas");
prog_assert(strpos($cpt_prog, "'publicly_queryable' => true") !== false, "Programa Academico es publicly_queryable");

prog_assert(strpos($archive_prog, "programa-academico") !== false, "Archive programa template consulta CPT");
prog_assert(strpos($single_prog, "coordinacion") !== false, "Single programa template muestra coordinacion");
prog_assert(strpos($single_prog, "oferta-academica") !== false, "Single programa template muestra ofertas");
prog_assert(strpos($single_prog, "seminario") !== false, "Single programa template muestra seminarios");

fwrite(STDOUT, "OK programas routes and templates contract
");
