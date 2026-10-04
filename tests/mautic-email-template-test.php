<?php

$template = file_get_contents(__DIR__ . '/../docs/mautic/acuse-consulta-academica.mjml');

if ($template === false) {
    fwrite(STDERR, "FAIL: no se pudo leer la plantilla MJML\n");
    exit(1);
}

if (strpos($template, 'href="{url_carta}"') === false) {
    fwrite(STDERR, "FAIL: el enlace de costos, programa y requisitos debe usar el token de carta\n");
    exit(1);
}

if (strpos($template, 'href="{oferta_academica_url}"') !== false) {
    fwrite(STDERR, "FAIL: la plantilla no debe usar el token ambiguo de URL de oferta\n");
    exit(1);
}

if (strpos($template, 'contactfield=flacso_oferta_') !== false) {
    fwrite(STDERR, "FAIL: la plantilla no debe depender de campos mutables de la oferta\n");
    exit(1);
}

if (strpos($template, 'href="{url_preinscripcion}"') === false) {
    fwrite(STDERR, "FAIL: el enlace de preinscripción debe usar el token canónico\n");
    exit(1);
}

echo "OK mautic email template\n";
