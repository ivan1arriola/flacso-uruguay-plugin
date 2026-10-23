<?php

declare(strict_types=1);

namespace FLACSO\Academico\Contratos;

use FLACSO\Academico\Dominio\Entidades\PersonaInstitucional;

interface RepositorioPersonaInstitucionalInterface {
    public function obtenerPorId(int $id): ?PersonaInstitucional;
    public function guardar(PersonaInstitucional $persona): PersonaInstitucional;
}
