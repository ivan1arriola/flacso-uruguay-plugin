<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Entidades;

use FLACSO\Academico\Logica\Excepciones\ReglaDeDominioException;

final class Seminario {
    private $id;
    private $nombre;
    private $cargaHoraria;
    private $componentes;
    public function __construct(int $id, string $nombre, float $cargaHoraria, array $componentes = []) {
        $this->id = $id;
        $this->setNombre($nombre);
        $this->setCargaHoraria($cargaHoraria);
        $this->setComponentes($componentes);
    }
    public function getId(): int { return $this->id; }
    public function getNombre(): string { return $this->nombre; }
    public function setNombre(string $nombre): void { $nombre = trim($nombre); if ($nombre === '') { throw new ReglaDeDominioException('El nombre del seminario es obligatorio.'); } $this->nombre = $nombre; }
    public function getCargaHoraria(): float { return $this->cargaHoraria; }
    public function setCargaHoraria(float $cargaHoraria): void { if ($cargaHoraria < 0) { throw new ReglaDeDominioException('La carga horaria no puede ser negativa.'); } $this->cargaHoraria = $cargaHoraria; }
    public function getComponentes(): array { return $this->componentes; }
    public function setComponentes(array $componentes): void { $this->componentes = array_values(array_unique(array_filter(array_map('intval', $componentes), function (int $id): bool { return $id > 0 && $id !== $this->id; }))); }
    public function esIntegrado(): bool { return $this->componentes !== []; }
}
