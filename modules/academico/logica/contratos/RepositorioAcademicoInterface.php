<?php

declare(strict_types=1);

namespace FLACSO\Academico\Contratos;

use FLACSO\Academico\Dominio\Entidades\Cohorte;
use FLACSO\Academico\Dominio\Entidades\Edicion;
use FLACSO\Academico\Dominio\Entidades\OfertaAcademica;
use FLACSO\Academico\Dominio\Entidades\Seminario;

interface RepositorioAcademicoInterface {
    public function existeCohorte(int $ofertaId, int $numero, ?int $excluirId = null): bool;
    public function guardarCohorte(Cohorte $cohorte): Cohorte;
    public function guardarOferta(OfertaAcademica $oferta): OfertaAcademica;
    public function guardarSeminario(Seminario $seminario): Seminario;
    public function guardarEdicion(Edicion $edicion): Edicion;
    /** @return Cohorte[] */ public function obtenerCohortesDeOferta(int $ofertaId): array;
    /** @return Edicion[] */ public function obtenerEdicionesDeSeminario(int $seminarioId): array;
}
