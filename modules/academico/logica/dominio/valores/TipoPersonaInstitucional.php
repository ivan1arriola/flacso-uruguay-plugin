<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Valores;

use FLACSO\Academico\Logica\Excepciones\ReglaDeDominioException;

final class TipoPersonaInstitucional {
    private const TIPOS = ['docente', 'administrativo'];

    private $valor;

    public function __construct(string $valor) {
        if (!in_array($valor, self::TIPOS, true)) {
            throw new ReglaDeDominioException('Tipo de persona institucional desconocido.');
        }
        $this->valor = $valor;
    }

    public function getValor(): string {
        return $this->valor;
    }
}
