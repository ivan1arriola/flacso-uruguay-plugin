<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Valores;

use FLACSO\Academico\Logica\Excepciones\ReglaDeDominioException;

final class PeriodoEdicion {
    private $anio;
    private $semestre;

    public function __construct(int $anio, ?int $semestre = null) {
        if ($anio < 2000 || $anio > 2200 || ($semestre !== null && $semestre !== 1 && $semestre !== 2)) {
            throw new ReglaDeDominioException('El período de edición no es válido.');
        }
        $this->anio = $anio;
        $this->semestre = $semestre;
    }

    public function getAnio(): int { return $this->anio; }
    public function getSemestre(): ?int { return $this->semestre; }
    public function getEtiqueta(): string { return $this->semestre === null ? 'Edición ' . $this->anio : 'Edición ' . $this->anio . ' — Semestre ' . $this->semestre; }
}
