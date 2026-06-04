<?php

define('ABSPATH', __DIR__ . '/../');

require_once __DIR__ . '/../modules/oferta-academica/includes/class-oferta-data-schema.php';

$cases = [
    [
        'label' => 'usa el ano de proximo inicio con precision month',
        'proximo_inicio' => ['valor' => '2027-03', 'precision' => 'month'],
        'cohorte' => '',
        'expected' => '2027',
    ],
    [
        'label' => 'usa el ano de proximo inicio con precision day y formato latino',
        'proximo_inicio' => ['valor' => '16/04/2027', 'precision' => 'day'],
        'cohorte' => '',
        'expected' => '2027',
    ],
    [
        'label' => 'usa el ano de proximo inicio cuando solo viene el ano',
        'proximo_inicio' => ['valor' => '2028', 'precision' => 'year'],
        'cohorte' => '',
        'expected' => '2028',
    ],
    [
        'label' => 'usa cohorte como respaldo si proximo inicio no define el ano',
        'proximo_inicio' => ['valor' => '', 'precision' => 'year'],
        'cohorte' => 'Cohorte 2029',
        'expected' => '2029',
    ],
];

foreach ($cases as $case) {
    $actual = Oferta_Data_Schema::resolve_inscripciones_year($case['proximo_inicio'], $case['cohorte']);

    if ($actual !== $case['expected']) {
        fwrite(
            STDERR,
            sprintf(
                "Fallo en '%s': esperado '%s' pero obtuve '%s'\n",
                $case['label'],
                $case['expected'],
                $actual
            )
        );
        exit(1);
    }
}

fwrite(STDOUT, "OK\n");
