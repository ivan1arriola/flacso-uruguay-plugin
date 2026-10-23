<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Entidades;

use FLACSO\Academico\Dominio\Valores\EstadoAcademico;
use FLACSO\Academico\Dominio\Valores\Modalidad;
use FLACSO\Academico\Dominio\Valores\NumeroCohorte;
use FLACSO\Academico\Dominio\Valores\RangoFechas;
use FLACSO\Academico\Logica\Excepciones\ReglaDeDominioException;

final class Cohorte {
    private $id;
    private $ofertaId;
    private $numero;
    private $estado;
    private $fechas;
    private $modalidad;
    private $tablaPrecioId;

    public function __construct(int $id, int $ofertaId, NumeroCohorte $numero, EstadoAcademico $estado, RangoFechas $fechas, ?Modalidad $modalidad = null, ?int $tablaPrecioId = null) {
        if ($ofertaId < 1) { throw new ReglaDeDominioException('La cohorte debe pertenecer a una oferta.'); }
        $this->id = $id;
        $this->ofertaId = $ofertaId;
        $this->numero = $numero;
        $this->estado = $estado;
        $this->fechas = $fechas;
        $this->modalidad = $modalidad;
        $this->setTablaPrecioId($tablaPrecioId);
    }
    public function getId(): int { return $this->id; }
    public function getOfertaId(): int { return $this->ofertaId; }
    public function getNumero(): NumeroCohorte { return $this->numero; }
    public function setNumero(NumeroCohorte $numero): void { $this->numero = $numero; }
    public function getEtiqueta(): string { return $this->numero->getEtiqueta(); }
    public function getEstado(): EstadoAcademico { return $this->estado; }
    public function setEstado(EstadoAcademico $estado): void { $this->estado = $estado; }
    public function getFechas(): RangoFechas { return $this->fechas; }
    public function setFechas(RangoFechas $fechas): void { $this->fechas = $fechas; }
    public function getModalidad(): ?Modalidad { return $this->modalidad; }
    public function setModalidad(?Modalidad $modalidad): void { $this->modalidad = $modalidad; }
    public function getTablaPrecioId(): ?int { return $this->tablaPrecioId; }
    public function setTablaPrecioId(?int $tablaPrecioId): void {
        if ($tablaPrecioId !== null && $tablaPrecioId < 1) { throw new ReglaDeDominioException('La tabla de precios no es válida.'); }
        $this->tablaPrecioId = $tablaPrecioId;
    }
}
