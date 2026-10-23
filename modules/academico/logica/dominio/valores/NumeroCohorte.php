<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Valores;

use FLACSO\Academico\Logica\Excepciones\ReglaDeDominioException;

final class NumeroCohorte {
    private $numero;

    public function __construct(int $numero) {
        if ($numero < 1) {
            throw new ReglaDeDominioException('El número de cohorte debe ser mayor que cero.');
        }
        $this->numero = $numero;
    }

    public function getNumero(): int { return $this->numero; }

    public function getRomano(): string {
        $restante = $this->numero;
        $resultado = '';
        foreach ([1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD', 100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'] as $valor => $simbolo) {
            while ($restante >= $valor) {
                $resultado .= $simbolo;
                $restante -= $valor;
            }
        }
        return $resultado;
    }

    public function getEtiqueta(): string { return 'Cohorte ' . $this->getRomano(); }
}
