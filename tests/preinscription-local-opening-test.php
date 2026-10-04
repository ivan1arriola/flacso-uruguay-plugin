<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/../');

final class PreinscriptionJsonResponse extends RuntimeException {
    public function __construct(
        public readonly bool $success,
        public readonly array $data,
        public readonly int $status = 200
    ) {
        parent::__construct('JSON response');
    }
}

$GLOBALS['flacso_local_opening_meta'] = [
    101 => ['oferta_academica_id' => 10, 'preinscripcion_habilitada' => true],
    102 => ['oferta_academica_id' => 10, 'preinscripcion_habilitada' => false],
    201 => ['seminario_id' => 20, 'fecha_inicio' => '2026-10-01', 'preinscripcion_habilitada' => true],
    202 => ['seminario_id' => 20, 'fecha_inicio' => '2026-10-01', 'preinscripcion_habilitada' => false],
    203 => ['seminario_id' => 21, 'fecha_inicio' => '2026-09-01', 'preinscripcion_habilitada' => false],
];
$GLOBALS['flacso_local_opening_types'] = [
    10 => 'oferta-academica',
    101 => 'cohorte',
    102 => 'cohorte',
    20 => 'seminario',
    201 => 'edicion',
    202 => 'edicion',
    21 => 'seminario',
    203 => 'edicion',
];
$GLOBALS['flacso_local_opening_slugs'] = [
    10 => 'maestria-en-genero',
    20 => 'diseno-de-cursos-virtuales',
];

function add_action(string $hook, $callback): void {}
function absint($value): int { return abs((int) $value); }
function sanitize_text_field($value): string { return trim((string) $value); }
function wp_unslash($value) { return $value; }
function wp_verify_nonce($nonce, $action): bool { return $nonce === 'valid'; }
function current_user_can($capability, ...$args): bool { return true; }
function current_time(string $type, bool $gmt = false) { return (new DateTimeImmutable('2026-10-05 12:00:00', new DateTimeZone('UTC')))->getTimestamp(); }
function wp_timezone(): DateTimeZone { return new DateTimeZone('UTC'); }
function get_option($key, $default = false) { return $default; }
function metadata_exists(string $type, int $post_id, string $key): bool { return array_key_exists($key, $GLOBALS['flacso_local_opening_meta'][$post_id] ?? []); }
function rest_sanitize_boolean($value): bool { return filter_var($value, FILTER_VALIDATE_BOOLEAN); }
function sanitize_key($value): string { return strtolower(trim((string) $value)); }
function sanitize_title($value): string { return strtolower(trim((string) $value)); }
function get_post_field(string $field, int $post_id): string {
    return $field === 'post_name' ? ($GLOBALS['flacso_local_opening_slugs'][$post_id] ?? '') : '';
}
function get_post_type($post_id): ?string { return $GLOBALS['flacso_local_opening_types'][(int) $post_id] ?? null; }
function get_post_meta(int $post_id, string $key, bool $single = false) {
    return $GLOBALS['flacso_local_opening_meta'][$post_id][$key] ?? ($single ? '' : []);
}
function update_post_meta(int $post_id, string $key, $value): bool {
    $GLOBALS['flacso_local_opening_meta'][$post_id][$key] = $value;
    return true;
}
function get_posts(array $args): array {
    $parent_key = (string) ($args['meta_query'][0]['key'] ?? '');
    $parent_id = (int) ($args['meta_query'][0]['value'] ?? 0);
    $post_type = (string) ($args['post_type'] ?? '');

    return array_map(
        'intval',
        array_keys(array_filter(
            $GLOBALS['flacso_local_opening_types'],
            static fn (string $type, int $id): bool => $type === $post_type
                && (int) get_post_meta($id, $parent_key, true) === $parent_id,
            ARRAY_FILTER_USE_BOTH
        ))
    );
}
function wp_send_json_success(array $data, int $status_code = 200): void {
    throw new PreinscriptionJsonResponse(true, $data, $status_code);
}
function wp_send_json_error(array $data, int $status_code = 400): void {
    throw new PreinscriptionJsonResponse(false, $data, $status_code);
}

final class FLACSO_Cohorte {
    public const POST_TYPE = 'cohorte';
    public const META_PARENT_ID = 'oferta_academica_id';
}
final class FLACSO_Oferta_Academica {
    public const POST_TYPE = 'oferta-academica';
}
final class FLACSO_Seminario {
    public const POST_TYPE = 'seminario';
}

require_once __DIR__ . '/../modules/seminarios/includes/class-edicion.php';
require_once __DIR__ . '/../modules/oferta-academica/includes/class-preinscription-ajax-handlers.php';

$_POST = [
    '_wpnonce' => 'valid',
    'cohorte_id' => 102,
];

try {
    FLACSO_Preinscription_Ajax_Handlers::abrir_cohorte();
    throw new RuntimeException('El handler debe devolver una respuesta JSON.');
} catch (PreinscriptionJsonResponse $response) {
    if (!$response->success) {
        throw new RuntimeException('Abrir una cohorte debe ser exitoso: ' . ($response->data['message'] ?? 'sin detalle'));
    }
}

if ($GLOBALS['flacso_local_opening_meta'][102]['preinscripcion_habilitada'] !== true) {
    throw new RuntimeException('La cohorte elegida debe quedar abierta en WordPress.');
}
if ($GLOBALS['flacso_local_opening_meta'][101]['preinscripcion_habilitada'] !== false) {
    throw new RuntimeException('Las cohortes hermanas deben cerrarse en WordPress.');
}
if (($response->data['url'] ?? '') !== 'https://preinscripciones.flacso.edu.uy/oferta/maestria-en-genero/') {
    throw new RuntimeException('La cohorte debe comunicar el permalink canónico de la oferta.');
}

$_POST = [
    '_wpnonce' => 'valid',
    'edicion_id' => 202,
];

try {
    FLACSO_Preinscription_Ajax_Handlers::abrir_edicion();
    throw new RuntimeException('El handler debe devolver una respuesta JSON.');
} catch (PreinscriptionJsonResponse $response) {
    if (!$response->success) {
        throw new RuntimeException('Abrir una edición vigente debe ser exitoso: ' . ($response->data['message'] ?? 'sin detalle'));
    }
}

if ($GLOBALS['flacso_local_opening_meta'][202]['preinscripcion_habilitada'] !== true) {
    throw new RuntimeException('La edición elegida debe quedar abierta en WordPress.');
}
if ($GLOBALS['flacso_local_opening_meta'][201]['preinscripcion_habilitada'] !== false) {
    throw new RuntimeException('Las ediciones hermanas deben cerrarse en WordPress.');
}
if (($response->data['url'] ?? '') !== 'https://preinscripciones.flacso.edu.uy/seminario/diseno-de-cursos-virtuales/') {
    throw new RuntimeException('La edición debe comunicar el permalink canónico del seminario.');
}

$_POST = [
    '_wpnonce' => 'valid',
    'edicion_id' => 203,
];

try {
    FLACSO_Preinscription_Ajax_Handlers::abrir_edicion();
    throw new RuntimeException('Una edición vencida no puede abrirse.');
} catch (PreinscriptionJsonResponse $response) {
    if ($response->success || $response->status !== 422) {
        throw new RuntimeException('Una edición vencida debe rechazarse con estado 422.');
    }
}

if ($GLOBALS['flacso_local_opening_meta'][203]['preinscripcion_habilitada'] !== false) {
    throw new RuntimeException('Una edición vencida debe conservarse cerrada.');
}

echo "OK: local preinscription opening\n";
