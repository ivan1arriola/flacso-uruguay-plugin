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

$required_tokens = [
    '{nombre}',
    '{apellido}',
    '{correo}',
    '{pais}',
    '{profesion}',
    '{nivel_academico}',
    '{oferta_academica_articulo}',
    '{oferta_academica_fecha_inicio}',
    '{oferta_academica_modalidad}',
    '{oferta_academica_nombre}',
    '{url_carta}',
    '{url_preinscripcion}',
];

foreach ($required_tokens as $token) {
    if (strpos($template, $token) === false) {
        fwrite(STDERR, "FAIL: la plantilla debe usar el token {$token} del correo de consulta\n");
        exit(1);
    }
}

if (strpos($template, '{contactfield=firstname}') !== false
    || strpos($template, '{contactfield=lastname}') !== false
    || strpos($template, '{contactfield=flacso_pais}') !== false
    || strpos($template, '{contactfield=flacso_nivel_academico}') !== false
    || strpos($template, '{contactfield=email}') !== false
    || strpos($template, '{contactfield=flacso_profesion}') !== false) {
    fwrite(STDERR, "FAIL: los datos del correo deben usar tokens inmutables de la entrega\n");
    exit(1);
}

echo "OK mautic email template\n";
