<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Valores;

use DateTimeImmutable;
use FLACSO\Academico\Logica\Excepciones\ReglaDeDominioException;

final class RangoFechas {
    private $inicio;
    private $fin;

    public function __construct(?string $inicio, ?string $fin) {
        $this->inicio = self::normalizar($inicio);
        $this->fin = self::normalizar($fin);
        if ($this->inicio !== null && $this->fin !== null && $this->fin < $this->inicio) {
            throw new ReglaDeDominioException('La fecha de fin no puede ser anterior a la fecha de inicio.');
        }
    }

    public function getInicio(): ?string { return $this->inicio; }
    public function getFin(): ?string { return $this->fin; }
    public function contiene(DateTimeImmutable $fecha): bool {
        $valor = $fecha->format('Y-m-d');
        return ($this->inicio === null || $valor >= $this->inicio) && ($this->fin === null || $valor <= $this->fin);
    }

    private static function normalizar(?string $fecha): ?string {
        if ($fecha === null || $fecha === '') { return null; }
        $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if (!$objeto || $objeto->format('Y-m-d') !== $fecha) {
            throw new ReglaDeDominioException('La fecha debe usar el formato YYYY-MM-DD.');
        }
        return $fecha;
    }
}
