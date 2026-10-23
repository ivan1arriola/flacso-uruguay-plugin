<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/modules/academico/logica/bootstrap.php';

use FLACSO\Academico\Dominio\Entidades\Administrativo;
use FLACSO\Academico\Dominio\Entidades\Docente;
use FLACSO\Academico\Dominio\Entidades\PersonaInstitucional;
use FLACSO\Academico\Dominio\Valores\TipoPersonaInstitucional;

function persona_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

function persona_rechaza(callable $accion, string $message): void {
    try {
        $accion();
    } catch (InvalidArgumentException $exception) {
        return;
    }
    persona_assert(false, $message);
}

$docente = new Docente(42, 'Ana', 'Pérez', '<p>Investigadora</p>', 'Doctora', 'Dra.');
persona_assert($docente instanceof PersonaInstitucional, 'Docente hereda los datos comunes de PersonaInstitucional.');
persona_assert($docente->esDocente() && !$docente->esAdministrativo(), 'Un docente no puede ser administrativo.');
persona_assert($docente->getTipo()->getValor() === 'docente', 'El tipo docente se deriva de la subclase.');
persona_assert($docente->getNombreCompleto() === 'Ana Pérez', 'El nombre completo se deriva de nombre y apellido.');
persona_assert($docente->getCv() === '<p>Investigadora</p>', 'El CV se conserva para el adaptador.');
persona_assert($docente->getTitulo() === 'Doctora' && $docente->getAbreviacionTitulo() === 'Dra.', 'El título y su abreviación son independientes.');

$administrativa = new Administrativo(43, 'Laura', 'Gómez');
persona_assert($administrativa instanceof PersonaInstitucional, 'Administrativo hereda los datos comunes.');
persona_assert(!$administrativa->esDocente() && $administrativa->esAdministrativo(), 'Una persona administrativa no es docente.');
persona_assert($administrativa->getTipo()->getValor() === 'administrativo', 'El tipo administrativo se deriva de la subclase.');
persona_assert($administrativa->getCv() === '' && $administrativa->getTitulo() === '', 'Los atributos opcionales comienzan vacíos.');

persona_rechaza(static function (): void { new TipoPersonaInstitucional('externo'); }, 'Un tipo desconocido se rechaza.');
persona_rechaza(static function () use ($docente): void { $docente->setNombre('   '); }, 'El nombre no puede quedar vacío.');
persona_rechaza(static function () use ($docente): void { $docente->setApellido(''); }, 'El apellido no puede quedar vacío.');

fwrite(STDOUT, "OK academic persona institucional\n");
