<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Entidades;

use FLACSO\Academico\Dominio\Valores\TipoPersonaInstitucional;
use FLACSO\Academico\Logica\Excepciones\ReglaDeDominioException;

abstract class PersonaInstitucional {
    private $id;
    private $nombre;
    private $apellido;
    private $cv;
    private $titulo;
    private $abreviacionTitulo;

    public function __construct(int $id, string $nombre, string $apellido, string $cv = '', string $titulo = '', string $abreviacionTitulo = '') {
        if ($id < 0) {
            throw new ReglaDeDominioException('El identificador de persona no puede ser negativo.');
        }
        $this->id = $id;
        $this->setNombre($nombre);
        $this->setApellido($apellido);
        $this->setCv($cv);
        $this->setTitulo($titulo);
        $this->setAbreviacionTitulo($abreviacionTitulo);
    }

    abstract public function getTipo(): TipoPersonaInstitucional;

    public function getId(): int { return $this->id; }
    public function getNombre(): string { return $this->nombre; }
    public function getApellido(): string { return $this->apellido; }
    public function getNombreCompleto(): string { return $this->nombre . ' ' . $this->apellido; }
    public function getCv(): string { return $this->cv; }
    public function getTitulo(): string { return $this->titulo; }
    public function getAbreviacionTitulo(): string { return $this->abreviacionTitulo; }
    public function esDocente(): bool { return $this->getTipo()->getValor() === 'docente'; }
    public function esAdministrativo(): bool { return $this->getTipo()->getValor() === 'administrativo'; }

    public function setNombre(string $nombre): void {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new ReglaDeDominioException('El nombre no puede estar vacío.');
        }
        $this->nombre = $nombre;
    }

    public function setApellido(string $apellido): void {
        $apellido = trim($apellido);
        if ($apellido === '') {
            throw new ReglaDeDominioException('El apellido no puede estar vacío.');
        }
        $this->apellido = $apellido;
    }

    public function setCv(string $cv): void { $this->cv = $cv; }
    public function setTitulo(string $titulo): void { $this->titulo = trim($titulo); }
    public function setAbreviacionTitulo(string $abreviacionTitulo): void { $this->abreviacionTitulo = trim($abreviacionTitulo); }
}
