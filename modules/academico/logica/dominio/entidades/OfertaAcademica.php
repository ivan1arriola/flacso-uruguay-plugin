<?php

declare(strict_types=1);

namespace FLACSO\Academico\Dominio\Entidades;

use FLACSO\Academico\Dominio\Valores\TipoOfertaAcademica;
use FLACSO\Academico\Logica\Excepciones\ReglaDeDominioException;

final class OfertaAcademica {
    private $id;
    private $programaId;
    private $nombre;
    private $tipo;
    private $creditos;
    private $seminarioIds;

    public function __construct(int $id, int $programaId, string $nombre, TipoOfertaAcademica $tipo, float $creditos = 0.0, array $seminarioIds = []) {
        if ($programaId < 1) { throw new ReglaDeDominioException('La oferta debe pertenecer a un programa.'); }
        $this->id = $id;
        $this->programaId = $programaId;
        $this->setNombre($nombre);
        $this->setTipo($tipo);
        $this->setCreditos($creditos);
        $this->setSeminarioIds($seminarioIds);
    }
    public function getId(): int { return $this->id; }
    public function getProgramaId(): int { return $this->programaId; }
    public function getNombre(): string { return $this->nombre; }
    public function setNombre(string $nombre): void { $this->nombre = self::nombreObligatorio($nombre); }
    public function getTipo(): TipoOfertaAcademica { return $this->tipo; }
    public function setTipo(TipoOfertaAcademica $tipo): void { $this->tipo = $tipo; }
    public function getCreditos(): float { return $this->creditos; }
    public function setCreditos(float $creditos): void {
        if ($creditos < 0) { throw new ReglaDeDominioException('Los créditos no pueden ser negativos.'); }
        $this->creditos = $creditos;
    }
    public function getSeminarioIds(): array { return $this->seminarioIds; }
    public function setSeminarioIds(array $ids): void { $this->seminarioIds = self::idsUnicos($ids); }
    private static function nombreObligatorio(string $nombre): string { $nombre = trim($nombre); if ($nombre === '') { throw new ReglaDeDominioException('El nombre es obligatorio.'); } return $nombre; }
    private static function idsUnicos(array $ids): array { return array_values(array_unique(array_filter(array_map('intval', $ids), static function (int $id): bool { return $id > 0; }))); }
}
