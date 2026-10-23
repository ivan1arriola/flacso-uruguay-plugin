<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Servicios;

use FLACSO\Academico\Dominio\Entidades\Seminario;
use FLACSO\Academico\Logica\Excepciones\ReglaDeDominioException;

final class ResolutorSeminarioIntegrado {
    /** @param array<int,Seminario> $seminarios */
    public function resolverCargaHoraria(Seminario $seminario, array $seminarios): float {
        return $this->carga($seminario, $seminarios, []);
    }
    private function carga(Seminario $seminario, array $seminarios, array $visitados): float {
        if (isset($visitados[$seminario->getId()])) { throw new ReglaDeDominioException('La composición de seminarios contiene un ciclo.'); }
        if (!$seminario->esIntegrado()) { return $seminario->getCargaHoraria(); }
        $visitados[$seminario->getId()] = true;
        $total = 0.0;
        foreach ($seminario->getComponentes() as $id) {
            if (!isset($seminarios[$id])) { throw new ReglaDeDominioException('Falta un seminario componente.'); }
            $total += $this->carga($seminarios[$id], $seminarios, $visitados);
        }
        return $total;
    }
}
