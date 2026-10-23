<?php

declare(strict_types=1);

namespace FLACSO\Academico\Aplicacion\Fabrica;

use FLACSO\Academico\Aplicacion\CasosDeUso\CrearCohorte;
use FLACSO\Academico\Aplicacion\Controladores\CrearCohorteController;
use FLACSO\Academico\Contratos\RepositorioAcademicoInterface;

final class AcademicoFactory {
    public static function crearControladorCohorte(RepositorioAcademicoInterface $repositorio): CrearCohorteController {
        return new CrearCohorteController(new CrearCohorte($repositorio));
    }
}
