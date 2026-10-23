<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Servicios;

use DateTimeImmutable;
use FLACSO\Academico\Dominio\Entidades\Edicion;

final class SelectorEdicionActual {
    /** @param Edicion[] $ediciones */
    public function seleccionar(array $ediciones, DateTimeImmutable $hoy): ?Edicion {
        foreach ($ediciones as $edicion) {
            if ($edicion->getEstado()->es('en_curso') && $edicion->getFechas()->contiene($hoy)) { return $edicion; }
        }
        $planificadas = array_values(array_filter($ediciones, static function (Edicion $edicion) use ($hoy): bool {
            return $edicion->getEstado()->es('planificada') && ($edicion->getFechas()->getInicio() === null || $edicion->getFechas()->getInicio() >= $hoy->format('Y-m-d'));
        }));
        usort($planificadas, static function (Edicion $a, Edicion $b): int { return strcmp((string) $a->getFechas()->getInicio(), (string) $b->getFechas()->getInicio()); });
        return $planificadas[0] ?? null;
    }
}
