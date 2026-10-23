<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Valores;

use FLACSO\Academico\Logica\Excepciones\ReglaDeDominioException;

final class Modalidad {
    private const PERMITIDAS = ['virtual', 'presencial', 'semipresencial', 'hibrida'];
    private $valor;

    public function __construct(string $valor) {
        if (!in_array($valor, self::PERMITIDAS, true)) {
            throw new ReglaDeDominioException('La modalidad académica no es válida.');
        }
        $this->valor = $valor;
    }

    public function getValor(): string { return $this->valor; }
}
