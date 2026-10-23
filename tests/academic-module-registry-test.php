<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/includes/core/class-flacso-module-registry.php';

$definitions = FLACSO_Uruguay_Module_Registry::definitions();
if (!isset($definitions['academico'])) {
    fwrite(STDERR, "Fallo: el módulo academico debe estar registrado.\n");
    exit(1);
}
if (($definitions['academico']['required'] ?? false) !== true || ($definitions['academico']['legacy'] ?? true) !== false) {
    fwrite(STDERR, "Fallo: academico debe ser un módulo requerido y no legacy.\n");
    exit(1);
}

fwrite(STDOUT, "OK academic module registry\n");
