<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Entidades;

use FLACSO\Academico\Logica\Excepciones\ReglaDeDominioException;

final class ProgramaAcademico {
    private $id;
    private $nombre;
    private $coordinadores;

    public function __construct(int $id, string $nombre, array $coordinadores = []) {
        $this->id = $id;
        $this->setNombre($nombre);
        $this->setCoordinadores($coordinadores);
    }
    public function getId(): int { return $this->id; }
    public function getNombre(): string { return $this->nombre; }
    public function setNombre(string $nombre): void {
        $nombre = trim($nombre);
        if ($nombre === '') { throw new ReglaDeDominioException('El nombre del programa es obligatorio.'); }
        $this->nombre = $nombre;
    }
    public function getCoordinadores(): array { return $this->coordinadores; }
    public function setCoordinadores(array $coordinadores): void { $this->coordinadores = array_values($coordinadores); }
}
