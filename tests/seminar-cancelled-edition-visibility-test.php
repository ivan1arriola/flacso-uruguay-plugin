<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/../');

function absint($value): int { return abs((int) $value); }
function current_time(string $format): string { return '2026-10-21'; }
function get_permalink(int $id): string { return 'https://flacso.edu.uy/seminario/' . $id . '/'; }
function get_post_modified_time(string $format, bool $gmt, int $id): string { return '2026-10-20T12:00:00Z'; }

final class FLACSO_Academic_Repository {
    public static function list(string $entity, array $filters = []): array {
        if ($entity === 'seminarios') {
            return array_values($GLOBALS['seminar_fixtures']);
        }
        if ($entity === 'ediciones') {
            $parent_id = absint($filters['parent_id'] ?? 0);
            return $GLOBALS['edition_fixtures'][$parent_id] ?? [];
        }
        return [];
    }
    public static function to_array(string $entity, int $id): array {
        return $entity === 'seminarios' ? ($GLOBALS['seminar_fixtures'][$id] ?? []) : [];
    }
}

require_once __DIR__ . '/../modules/oferta-academica/includes/class-academic-catalog.php';

function visibility_assert(bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function fixture_edition(int $id, string $state, string $start, string $end = '', string $publication = 'publish'): array {
    return [
        'id' => $id,
        'nombre' => 'Edición 2026',
        'anio' => 2026,
        'estado_publicacion' => $publication,
        'estado' => $state,
        'fecha_inicio' => $start,
        'fecha_fin' => $end,
        'preinscripcion' => ['abierta' => true, 'url' => 'https://preinscripciones.flacso.edu.uy/'],
        'mostrar_en_formulario' => true,
    ];
}

$GLOBALS['seminar_fixtures'] = [
    101 => ['id' => 101, 'nombre' => 'Seminario con otra edición vigente', 'slug' => 'seminario-101'],
    102 => ['id' => 102, 'nombre' => 'Seminario cancelado', 'slug' => 'seminario-102'],
    103 => ['id' => 103, 'nombre' => 'Seminario en curso', 'slug' => 'seminario-103'],
    104 => ['id' => 104, 'nombre' => 'Seminario pasado', 'slug' => 'seminario-104'],
];
$GLOBALS['edition_fixtures'] = [
    101 => [
        fixture_edition(201, 'cancelada', '2026-11-04'),
        fixture_edition(202, 'planificada', '2026-12-01'),
    ],
    102 => [
        fixture_edition(203, 'cancelada', '2026-11-25'),
        fixture_edition(204, 'finalizada', '2026-09-01', '2026-09-25'),
        fixture_edition(205, 'planificada', '2026-12-15', '', 'draft'),
    ],
    103 => [fixture_edition(206, 'en_curso', '2026-10-01', '2026-10-28')],
    104 => [fixture_edition(207, 'planificada', '2026-09-01')],
];

visibility_assert(!FLACSO_Academic_Catalog::is_public_current_edition($GLOBALS['edition_fixtures'][101][0]), 'cancelada futura excluida');
visibility_assert(FLACSO_Academic_Catalog::is_public_current_edition($GLOBALS['edition_fixtures'][101][1]), 'otra edición planificada futura es vigente');
visibility_assert(!FLACSO_Academic_Catalog::is_public_current_edition($GLOBALS['edition_fixtures'][102][2]), 'borrador excluido');
visibility_assert(!FLACSO_Academic_Catalog::is_public_current_edition($GLOBALS['edition_fixtures'][104][0]), 'planificada vencida excluida');

$first = FLACSO_Academic_Catalog::get_seminar(101);
visibility_assert(($first['edicion_vigente']['id'] ?? 0) === 202, 'elige edición vigente en lugar de la cancelada');
$second = FLACSO_Academic_Catalog::get_seminar(102);
visibility_assert($second['edicion_vigente'] === null, 'no hay fallback a edición cancelada/finalizada');
$third = FLACSO_Academic_Catalog::get_seminar(103);
visibility_assert(($third['edicion_vigente']['id'] ?? 0) === 206, 'conserva edición en curso');

$catalog = FLACSO_Academic_Catalog::registration_catalog();
$items = $catalog['items'];
visibility_assert(count($items) === 2, 'catálogo omite canceladas y vencidas incluso con preinscripción abierta');
$by_seminar = [];
foreach ($items as $item) {
    $by_seminar[(int) $item['seminario']['id']] = (int) $item['edicion']['id'];
}
visibility_assert(($by_seminar[101] ?? 0) === 202, 'catálogo expone la otra edición vigente');
visibility_assert(($by_seminar[103] ?? 0) === 206, 'catálogo conserva la edición en curso');
visibility_assert(!isset($by_seminar[102]) && !isset($by_seminar[104]), 'catálogo no muestra seminarios sin edición vigente');

echo "OK: cancelled seminar edition visibility\n";
