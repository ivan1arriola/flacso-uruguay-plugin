<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Valores;

use FLACSO\Academico\Logica\Excepciones\ReglaDeDominioException;

final class EstadoAcademico {
    private const PERMITIDOS = ['planificada', 'en_curso', 'finalizada', 'cancelada'];
    private $valor;

    public function __construct(string $valor) {
        if (!in_array($valor, self::PERMITIDOS, true)) {
            throw new ReglaDeDominioException('El estado académico no es válido.');
        }
        $this->valor = $valor;
    }

    public function getValor(): string { return $this->valor; }
    public function es(string $valor): bool { return $this->valor === $valor; }
    public function estaCerrado(): bool { return $this->es('finalizada') || $this->es('cancelada'); }
}
