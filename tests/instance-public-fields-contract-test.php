<?php

$source = file_get_contents(__DIR__ . '/../modules/instancias-oferta/includes/class-instancia-oferta-api.php');
$model = file_get_contents(__DIR__ . '/../modules/instancias-oferta/includes/class-instancia-oferta.php');

$required_domain_fields = [
    "'meetings'",
    "'teachers'",
    "'modality'",
    "'isAsync'",
    "'priceUyu'",
    "'priceUsd'",
    "'discountedPriceUyu'",
    "'discountedPriceUsd'",
    "'showInForm'",
    "'legacyOpen'",
];

foreach ($required_domain_fields as $field) {
    if (substr_count($source, $field) < 2) {
        fwrite(STDERR, "Fallo: el contrato de InstanciaOferta no expone/persiste {$field}\n");
        exit(1);
    }
}

$required_meta_constants = [
    'META_ENCUENTROS',
    'META_DOCENTES',
    'META_MODALIDAD',
    'META_ES_ASINCRONICO',
    'META_VALOR_UYU',
    'META_VALOR_USD',
    'META_VALOR_UYU_DESCUENTO',
    'META_VALOR_USD_DESCUENTO',
    'META_MOSTRAR_FORMULARIO',
    'META_LEGACY_OPEN',
];

foreach ($required_meta_constants as $constant) {
    if (strpos($model, $constant) === false || strpos($source, 'FLACSO_Instancia_Oferta::' . $constant) === false) {
        fwrite(STDERR, "Fallo: falta persistencia pública de {$constant}\n");
        exit(1);
    }
}

if (strpos($source, "'preinscriptionFlow'") === false
    || strpos($source, 'META_PREINSCRIPCION_APERTURA') === false
    || strpos($source, 'META_PREINSCRIPCION_CIERRE_MANUAL') === false) {
    fwrite(STDERR, "Fallo: esta entrega no debe retirar todavía la compatibilidad de preinscripciones\n");
    exit(1);
}

fwrite(STDOUT, "OK instance public editor contract\n");
