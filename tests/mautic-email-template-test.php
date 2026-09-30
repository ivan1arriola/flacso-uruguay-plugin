<?php

$template = file_get_contents(__DIR__ . '/../docs/mautic/acuse-consulta-academica.mjml');

if ($template === false) {
    fwrite(STDERR, "FAIL: no se pudo leer la plantilla MJML\n");
    exit(1);
}

if (strpos($template, 'href="{contactfield=flacso_oferta_url}"') === false) {
    fwrite(STDERR, "FAIL: el enlace carta debe usar un token Mautic completo\n");
    exit(1);
}

echo "OK mautic email template\n";
