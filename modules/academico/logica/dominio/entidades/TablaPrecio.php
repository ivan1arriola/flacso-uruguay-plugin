<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Entidades;

use FLACSO\Academico\Logica\Excepciones\ReglaDeDominioException;

final class TablaPrecio {
    private $id;
    private $nombre;
    private $filas;
    public function __construct(int $id, string $nombre, array $filas = []) { $this->id = $id; $this->setNombre($nombre); $this->setFilas($filas); }
    public function getId(): int { return $this->id; }
    public function getNombre(): string { return $this->nombre; }
    public function setNombre(string $nombre): void { $nombre = trim($nombre); if ($nombre === '') { throw new ReglaDeDominioException('El nombre de la tabla de precios es obligatorio.'); } $this->nombre = $nombre; }
    public function getFilas(): array { return $this->filas; }
    public function setFilas(array $filas): void { $this->filas = array_values($filas); }
}
