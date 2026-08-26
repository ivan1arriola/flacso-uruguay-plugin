<?php

define('ABSPATH', __DIR__ . '/');

function absint($value) {
    return abs((int) $value);
}

class WP_Error {
    public string $code;

    public function __construct(string $code) {
        $this->code = $code;
    }
}

function is_wp_error($value): bool {
    return $value instanceof WP_Error;
}

require_once dirname(__DIR__) . '/modules/seminarios/includes/class-seminario-taxonomies.php';
require_once dirname(__DIR__) . '/modules/oferta-academica/includes/class-oferta-data-schema.php';

function assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Esperado: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Recibido: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

assert_same(
    [17],
    Seminario_Taxonomies::normalize_program_term_ids(['id' => '17']),
    'El contrato program debe aceptar un objeto con id.'
);

assert_same(
    17,
    Seminario_Taxonomies::resolve_single_program_id([17, '17']),
    'La migración debe resolver un único Programa coincidente.'
);

assert_same(
    0,
    Seminario_Taxonomies::resolve_single_program_id([17, 23]),
    'La migración no debe adivinar cuando hay Programas incompatibles.'
);

assert_same(
    true,
    Seminario_Taxonomies::validate_program_request(null, true, ['id' => 17]),
    'El contrato semántico program debe validar un único Programa.'
);

$multiple_programs = Seminario_Taxonomies::validate_program_request(['area_tematica' => [17, 23]], true);
assert_same(
    'seminario_program_invalid',
    $multiple_programs instanceof WP_Error ? $multiple_programs->code : null,
    'La API debe rechazar contenidos asociados a más de un Programa.'
);

$conflicting_contracts = Seminario_Taxonomies::validate_program_request(
    ['area_tematica' => [17]],
    true,
    ['id' => 23]
);
assert_same(
    'seminario_program_invalid',
    $conflicting_contracts instanceof WP_Error ? $conflicting_contracts->code : null,
    'La API debe rechazar representaciones de Programa contradictorias.'
);

assert_same(
    [17],
    Oferta_Data_Schema::normalize_program_ids(['id' => 17]),
    'La API de ofertas debe normalizar el contrato semántico program.'
);

assert_same(
    [17],
    Seminario_Taxonomies::normalize_program_term_ids([17, 17, 23]),
    'La capa de persistencia debe conservar exactamente un Programa.'
);

fwrite(STDOUT, "OK program contract\n");
