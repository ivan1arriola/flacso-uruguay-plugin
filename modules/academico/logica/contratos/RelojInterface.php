<?php

declare(strict_types=1);

namespace FLACSO\Academico\Contratos;

use DateTimeImmutable;

interface RelojInterface {
    public function ahora(): DateTimeImmutable;
}
