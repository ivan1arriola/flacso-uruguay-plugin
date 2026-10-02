<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/../');

function get_option($key, $default = false) {
    return $default;
}

function get_post_meta(int $post_id, string $key = '', bool $single = false) {
    return $GLOBALS['flacso_test_post_meta'][$post_id][$key] ?? ($single ? '' : []);
}

function metadata_exists(string $meta_type, int $post_id, string $key): bool {
    return array_key_exists($key, $GLOBALS['flacso_test_post_meta'][$post_id] ?? []);
}

function rest_sanitize_boolean($value): bool {
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function wp_timezone(): DateTimeZone {
    return new DateTimeZone('UTC');
}

require_once __DIR__ . '/../modules/seminarios/includes/class-edicion.php';

function availability_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function test_timestamp(string $value): int {
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->getTimestamp();
}

$GLOBALS['flacso_test_post_meta'] = [
    501 => [
        'estado' => 'planificada',
        'fecha_inicio' => '2026-10-01',
        'preinscripcion_habilitada' => true,
    ],
    502 => [
        'estado' => 'planificada',
        'fecha_inicio' => '2026-10-01',
        'preinscripcion_habilitada' => true,
    ],
    503 => [
        'estado' => 'planificada',
        'preinscripcion_habilitada' => true,
    ],
];

$open = FLACSO_Edicion::registration_availability(501, test_timestamp('2026-10-11 23:59:59'));
availability_assert($open['status'] === 'open', 'la edición permanece abierta durante el décimo día');
availability_assert($open['from'] === null, 'sin fecha explícita de apertura, from es null');
availability_assert($open['until'] === '2026-10-11T23:59:59+00:00', 'until representa el final del décimo día');

$closed = FLACSO_Edicion::registration_availability(502, test_timestamp('2026-10-12 00:00:00'));
availability_assert($closed['status'] === 'closed', 'la edición se cierra al comenzar el día posterior');

$missing_start = FLACSO_Edicion::registration_availability(503, test_timestamp('2026-10-01 12:00:00'));
availability_assert($missing_start['status'] === 'closed', 'una edición sin fecha de inicio queda cerrada');
availability_assert($missing_start['until'] === null, 'una edición sin fecha no inventa una ventana');

echo "OK: seminar registration availability\n";
