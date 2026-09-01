<?php

$root = dirname(__DIR__);
$cpt = file_get_contents($root . '/modules/docentes/includes/class-cpt-docente.php');
$meta = file_get_contents($root . '/modules/docentes/includes/class-docente-meta.php');
$dto = file_get_contents($root . '/modules/docentes/includes/rest-dto.php');
$api = file_get_contents($root . '/modules/docentes/includes/rest-api.php');
$columns = file_get_contents($root . '/modules/docentes/includes/admin-columns.php');
$filters = file_get_contents($root . '/modules/docentes/includes/admin-filters.php');

function equipo_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

equipo_assert(strpos($cpt, "Personas / Equipo") !== false, "CPT_Docente tiene etiquetas de Personas / Equipo");
equipo_assert(strpos($cpt, "^equipo/docentes") !== false, "CPT_Docente tiene regla para /equipo/docentes");
equipo_assert(strpos($cpt, "^equipo/administrativo") !== false, "CPT_Docente tiene regla para /equipo/administrativo");
equipo_assert(strpos($cpt, "flacso_equipo_view") !== false, "CPT_Docente registra flacso_equipo_view");

equipo_assert(strpos($meta, "ROLE_DOCENTE = 'docente'") !== false, "Docente_Meta define rol docente");
equipo_assert(strpos($meta, "ROLE_ADMINISTRATIVO = 'administrativo'") !== false, "Docente_Meta define rol administrativo");
equipo_assert(strpos($meta, "get_roles") !== false, "Docente_Meta implementa get_roles");
equipo_assert(strpos($meta, "cargo") !== false, "Docente_Meta maneja campo cargo");

equipo_assert(strpos($dto, "'roles'") !== false && strpos($dto, "'cargo'") !== false, "DTO expone roles y cargo");
equipo_assert(strpos($api, "flacso_role") !== false || strpos($api, "rol") !== false, "API soporta filtrado por rol");

equipo_assert(strpos($columns, "'roles'") !== false, "Columnas administrativas muestran roles");
equipo_assert(strpos($filters, "flacso_role") !== false, "Filtro administrativo por rol implementado");

fwrite(STDOUT, "OK FLACSO equipo roles contract
");
