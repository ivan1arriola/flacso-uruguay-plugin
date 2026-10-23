<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Servicios;

use DateTimeImmutable;
use FLACSO\Academico\Dominio\Entidades\Cohorte;

final class SelectorCohorteActual {
    /** @param Cohorte[] $cohortes */
    public function seleccionar(array $cohortes, DateTimeImmutable $hoy): ?Cohorte {
        $enCurso = array_values(array_filter($cohortes, static function (Cohorte $cohorte): bool {
            return $cohorte->getEstado()->es('en_curso');
        }));
        if ($enCurso !== []) { return $this->porNumeroMayor($enCurso); }
        $planificadas = array_values(array_filter($cohortes, static function (Cohorte $cohorte) use ($hoy): bool {
            return $cohorte->getEstado()->es('planificada') && ($cohorte->getFechas()->getInicio() === null || $cohorte->getFechas()->getInicio() >= $hoy->format('Y-m-d'));
        }));
        if ($planificadas === []) { return null; }
        usort($planificadas, static function (Cohorte $a, Cohorte $b): int { return strcmp((string) $a->getFechas()->getInicio(), (string) $b->getFechas()->getInicio()); });
        return $planificadas[0];
    }
    private function porNumeroMayor(array $cohortes): Cohorte {
        usort($cohortes, static function (Cohorte $a, Cohorte $b): int { return $b->getNumero()->getNumero() <=> $a->getNumero()->getNumero(); });
        return $cohortes[0];
    }
}
