<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Entidades;

use FLACSO\Academico\Dominio\Valores\TipoPersonaInstitucional;

final class Administrativo extends PersonaInstitucional {
    public function getTipo(): TipoPersonaInstitucional {
        return new TipoPersonaInstitucional('administrativo');
    }
}
