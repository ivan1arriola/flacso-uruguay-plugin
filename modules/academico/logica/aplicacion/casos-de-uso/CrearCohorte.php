<?php

declare(strict_types=1);

namespace FLACSO\Academico\Aplicacion\CasosDeUso;

use FLACSO\Academico\Aplicacion\Dto\CrearCohorteInput;
use FLACSO\Academico\Contratos\RepositorioAcademicoInterface;
use FLACSO\Academico\Dominio\Entidades\Cohorte;
use FLACSO\Academico\Dominio\Valores\EstadoAcademico;
use FLACSO\Academico\Dominio\Valores\NumeroCohorte;
use FLACSO\Academico\Dominio\Valores\RangoFechas;
use FLACSO\Academico\Logica\Excepciones\ReglaDeDominioException;

final class CrearCohorte {
    private $repositorio;
    public function __construct(RepositorioAcademicoInterface $repositorio) { $this->repositorio = $repositorio; }
    public function ejecutar(CrearCohorteInput $input): Cohorte {
        if ($this->repositorio->existeCohorte($input->getOfertaId(), $input->getNumero())) { throw new ReglaDeDominioException('Ya existe una cohorte con ese número para la oferta.'); }
        $cohorte = new Cohorte(0, $input->getOfertaId(), new NumeroCohorte($input->getNumero()), new EstadoAcademico($input->getEstado()), new RangoFechas($input->getFechaInicio(), $input->getFechaFin()));
        return $this->repositorio->guardarCohorte($cohorte);
    }
}
