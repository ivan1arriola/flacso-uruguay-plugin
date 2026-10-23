<?php

declare(strict_types=1);

$flacso_academico_logic_files = [
    'excepciones/ReglaDeDominioException.php',
    'dominio/valores/NumeroCohorte.php',
    'dominio/valores/EstadoAcademico.php',
    'dominio/valores/RangoFechas.php',
    'dominio/valores/PeriodoEdicion.php',
    'dominio/valores/Modalidad.php',
    'dominio/valores/TipoOfertaAcademica.php',
    'dominio/entidades/ProgramaAcademico.php',
    'dominio/entidades/OfertaAcademica.php',
    'dominio/entidades/Cohorte.php',
    'dominio/entidades/Seminario.php',
    'dominio/entidades/Edicion.php',
    'dominio/entidades/TablaPrecio.php',
    'dominio/servicios/SelectorCohorteActual.php',
    'dominio/servicios/SelectorEdicionActual.php',
    'dominio/servicios/ResolutorSeminarioIntegrado.php',
    'contratos/RepositorioAcademicoInterface.php',
    'contratos/RepositorioDocentesInterface.php',
    'contratos/RelojInterface.php',
    'aplicacion/dto/CrearCohorteInput.php',
    'aplicacion/casos-de-uso/CrearCohorte.php',
    'aplicacion/controladores/CrearCohorteController.php',
    'aplicacion/fabrica/AcademicoFactory.php',
];

foreach ($flacso_academico_logic_files as $flacso_academico_logic_file) {
    require_once __DIR__ . '/' . $flacso_academico_logic_file;
}
