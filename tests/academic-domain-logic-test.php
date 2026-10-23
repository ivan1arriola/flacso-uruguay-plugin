<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/modules/academico/logica/bootstrap.php';

use FLACSO\Academico\Aplicacion\CasosDeUso\CrearCohorte;
use FLACSO\Academico\Aplicacion\Dto\CrearCohorteInput;
use FLACSO\Academico\Contratos\RepositorioAcademicoInterface;
use FLACSO\Academico\Dominio\Entidades\Cohorte;
use FLACSO\Academico\Dominio\Entidades\Edicion;
use FLACSO\Academico\Dominio\Entidades\Seminario;
use FLACSO\Academico\Dominio\Servicios\ResolutorSeminarioIntegrado;
use FLACSO\Academico\Dominio\Servicios\SelectorCohorteActual;
use FLACSO\Academico\Dominio\Valores\EstadoAcademico;
use FLACSO\Academico\Dominio\Valores\NumeroCohorte;
use FLACSO\Academico\Dominio\Valores\PeriodoEdicion;
use FLACSO\Academico\Dominio\Valores\RangoFechas;
use FLACSO\Academico\Dominio\Valores\TipoOfertaAcademica;

function academic_logic_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

function academic_logic_throws(callable $callback, string $message): void {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return;
    }
    academic_logic_assert(false, $message);
}

academic_logic_assert((new NumeroCohorte(7))->getEtiqueta() === 'Cohorte VII', 'NumeroCohorte deriva la etiqueta romana.');
academic_logic_throws(static function (): void {
    new NumeroCohorte(0);
}, 'NumeroCohorte rechaza cero.');

academic_logic_assert((new TipoOfertaAcademica('maestria'))->getValor() === 'maestria', 'TipoOfertaAcademica acepta los tipos institucionales.');
academic_logic_throws(static function (): void {
    new TipoOfertaAcademica('seminario');
}, 'TipoOfertaAcademica rechaza tipos no académicos.');
academic_logic_assert((new TipoOfertaAcademica('doctorado'))->compararCon(new TipoOfertaAcademica('diploma')) < 0, 'TipoOfertaAcademica conserva el orden institucional.');

academic_logic_throws(static function (): void {
    new RangoFechas('2026-06-10', '2026-06-09');
}, 'RangoFechas rechaza un fin anterior al inicio.');

$planificada = new Cohorte(10, 1, new NumeroCohorte(2), new EstadoAcademico('planificada'), new RangoFechas('2026-07-01', null));
$enCurso = new Cohorte(11, 1, new NumeroCohorte(1), new EstadoAcademico('en_curso'), new RangoFechas('2026-03-01', '2026-12-01'));
$selector = new SelectorCohorteActual();
academic_logic_assert($selector->seleccionar([$planificada, $enCurso], new DateTimeImmutable('2026-06-15'))->getId() === 11, 'SelectorCohorteActual prioriza una cohorte en curso.');

$cohorteEstrategica = new Cohorte(12, 1, new NumeroCohorte(3), new EstadoAcademico('planificada'), new RangoFechas('2026-08-01', null), null, null, true);
academic_logic_assert($cohorteEstrategica->getPreinscripcionesAbiertas() === true, 'Cohorte conserva la apertura estratégica elegida.');
$cohorteEstrategica->setEstado(new EstadoAcademico('cancelada'));
academic_logic_assert($cohorteEstrategica->getPreinscripcionesAbiertas() === false, 'Cohorte cancelada se cierra automáticamente.');
academic_logic_throws(static function (): void {
    $cohorte = new Cohorte(13, 1, new NumeroCohorte(4), new EstadoAcademico('finalizada'), new RangoFechas('2026-01-01', '2026-06-01'));
    $cohorte->setPreinscripcionesAbiertas(true);
}, 'Cohorte finalizada no admite una apertura estratégica.');

$edicionAbierta = new Edicion(20, 101, new PeriodoEdicion(2026, 1), new EstadoAcademico('planificada'), new RangoFechas('2026-03-01', null));
academic_logic_assert($edicionAbierta->tienePreinscripcionesAbiertas(new DateTimeImmutable('2026-03-11 23:59:59'), 10) === true, 'Edicion permanece abierta hasta el cierre configurado.');
academic_logic_assert($edicionAbierta->tienePreinscripcionesAbiertas(new DateTimeImmutable('2026-03-12 00:00:00'), 10) === false, 'Edicion se cierra después del plazo configurado.');
$edicionAbierta->setEstado(new EstadoAcademico('cancelada'));
academic_logic_assert($edicionAbierta->tienePreinscripcionesAbiertas(new DateTimeImmutable('2026-03-02'), 10) === false, 'Edicion cancelada se cierra aunque esté dentro del plazo.');

$componente = new Seminario(101, 'Componente', 12.0);
$integrado = new Seminario(102, 'Integrado', 0.0, [101]);
$resolutor = new ResolutorSeminarioIntegrado();
academic_logic_assert($resolutor->resolverCargaHoraria($integrado, [101 => $componente, 102 => $integrado]) === 12.0, 'ResolutorSeminarioIntegrado suma la carga horaria transitiva.');

$repository = new class implements RepositorioAcademicoInterface {
    public $saved;
    public function existeCohorte(int $ofertaId, int $numero, ?int $excluirId = null): bool { return false; }
    public function guardarCohorte(Cohorte $cohorte): Cohorte { $this->saved = $cohorte; return $cohorte; }
    public function guardarOferta(\FLACSO\Academico\Dominio\Entidades\OfertaAcademica $oferta): \FLACSO\Academico\Dominio\Entidades\OfertaAcademica { return $oferta; }
    public function guardarSeminario(Seminario $seminario): Seminario { return $seminario; }
    public function guardarEdicion(Edicion $edicion): Edicion { return $edicion; }
    public function obtenerCohortesDeOferta(int $ofertaId): array { return []; }
    public function obtenerEdicionesDeSeminario(int $seminarioId): array { return []; }
};
$useCase = new CrearCohorte($repository);
$created = $useCase->ejecutar(new CrearCohorteInput(1, 3, 'planificada', '2027-03-01', null));
academic_logic_assert($created->getEtiqueta() === 'Cohorte III', 'CrearCohorte construye una entidad pura y la persiste mediante el contrato.');

fwrite(STDOUT, "OK academic domain logic\n");
