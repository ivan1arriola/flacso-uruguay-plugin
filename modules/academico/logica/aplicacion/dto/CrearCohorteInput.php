<?php

declare(strict_types=1);

namespace FLACSO\Academico\Aplicacion\Dto;

final class CrearCohorteInput {
    private $ofertaId; private $numero; private $estado; private $fechaInicio; private $fechaFin;
    public function __construct(int $ofertaId, int $numero, string $estado, ?string $fechaInicio, ?string $fechaFin) { $this->ofertaId = $ofertaId; $this->numero = $numero; $this->estado = $estado; $this->fechaInicio = $fechaInicio; $this->fechaFin = $fechaFin; }
    public function getOfertaId(): int { return $this->ofertaId; }
    public function getNumero(): int { return $this->numero; }
    public function getEstado(): string { return $this->estado; }
    public function getFechaInicio(): ?string { return $this->fechaInicio; }
    public function getFechaFin(): ?string { return $this->fechaFin; }
}
