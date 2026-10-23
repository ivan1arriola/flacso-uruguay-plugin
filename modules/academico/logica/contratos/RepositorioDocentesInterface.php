<?php

declare(strict_types=1);

namespace FLACSO\Academico\Contratos;

interface RepositorioDocentesInterface {
    public function existe(int $docenteId): bool;
}
