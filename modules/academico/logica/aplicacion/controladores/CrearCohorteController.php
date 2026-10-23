<?php

declare(strict_types=1);

namespace FLACSO\Academico\Aplicacion\Controladores;

use FLACSO\Academico\Aplicacion\CasosDeUso\CrearCohorte;
use FLACSO\Academico\Aplicacion\Dto\CrearCohorteInput;
use FLACSO\Academico\Dominio\Entidades\Cohorte;

final class CrearCohorteController {
    private $casoDeUso;
    public function __construct(CrearCohorte $casoDeUso) { $this->casoDeUso = $casoDeUso; }
    public function ejecutar(CrearCohorteInput $input): Cohorte { return $this->casoDeUso->ejecutar($input); }
}
