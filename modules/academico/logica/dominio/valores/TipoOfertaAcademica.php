<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Valores;

use FLACSO\Academico\Logica\Excepciones\ReglaDeDominioException;

final class TipoOfertaAcademica {
    private const ORDEN_INSTITUCIONAL = [
        'doctorado' => 1,
        'maestria' => 2,
        'especializacion' => 3,
        'diplomado' => 4,
        'diploma' => 5,
    ];
    private $valor;

    public function __construct(string $valor) {
        if (!array_key_exists($valor, self::ORDEN_INSTITUCIONAL)) {
            throw new ReglaDeDominioException('El tipo de oferta no es válido.');
        }
        $this->valor = $valor;
    }

    public function getValor(): string { return $this->valor; }
    public function getOrden(): int { return self::ORDEN_INSTITUCIONAL[$this->valor]; }
    public function compararCon(self $otro): int { return $this->getOrden() <=> $otro->getOrden(); }
    public static function permitidos(): array { return array_keys(self::ORDEN_INSTITUCIONAL); }
}
