<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Entidades;

use FLACSO\Academico\Dominio\Valores\EstadoAcademico;
use FLACSO\Academico\Dominio\Valores\Modalidad;
use FLACSO\Academico\Dominio\Valores\PeriodoEdicion;
use FLACSO\Academico\Dominio\Valores\RangoFechas;
use FLACSO\Academico\Logica\Excepciones\ReglaDeDominioException;

final class Edicion {
    private $id;
    private $seminarioId;
    private $periodo;
    private $estado;
    private $fechas;
    private $modalidad;
    private $docenteIds;
    private $componentes;
    public function __construct(int $id, int $seminarioId, PeriodoEdicion $periodo, EstadoAcademico $estado, RangoFechas $fechas, ?Modalidad $modalidad = null, array $docenteIds = [], array $componentes = []) {
        if ($seminarioId < 1) { throw new ReglaDeDominioException('La edición debe pertenecer a un seminario.'); }
        $this->id = $id; $this->seminarioId = $seminarioId; $this->periodo = $periodo; $this->estado = $estado; $this->fechas = $fechas; $this->modalidad = $modalidad;
        $this->setDocenteIds($docenteIds); $this->setComponentes($componentes);
    }
    public function getId(): int { return $this->id; }
    public function getSeminarioId(): int { return $this->seminarioId; }
    public function getPeriodo(): PeriodoEdicion { return $this->periodo; }
    public function setPeriodo(PeriodoEdicion $periodo): void { $this->periodo = $periodo; }
    public function getEstado(): EstadoAcademico { return $this->estado; }
    public function setEstado(EstadoAcademico $estado): void { $this->estado = $estado; }
    public function getFechas(): RangoFechas { return $this->fechas; }
    public function setFechas(RangoFechas $fechas): void { $this->fechas = $fechas; }
    public function getModalidad(): ?Modalidad { return $this->modalidad; }
    public function setModalidad(?Modalidad $modalidad): void { $this->modalidad = $modalidad; }
    public function getDocenteIds(): array { return $this->docenteIds; }
    public function setDocenteIds(array $ids): void { $this->docenteIds = self::ids($ids); }
    public function getComponentes(): array { return $this->componentes; }
    public function setComponentes(array $ids): void { $this->componentes = array_values(array_filter(self::ids($ids), function (int $id): bool { return $id !== $this->id; })); }
    private static function ids(array $ids): array { return array_values(array_unique(array_filter(array_map('intval', $ids), static function (int $id): bool { return $id > 0; }))); }
}
