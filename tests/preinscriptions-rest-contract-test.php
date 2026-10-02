<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

$GLOBALS['flacso_registered_rest_routes'] = [];
$GLOBALS['flacso_rest_assertions'] = 0;

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
    $GLOBALS['flacso_registered_actions'][$hook][] = [
        'callback'      => $callback,
        'priority'      => $priority,
        'accepted_args' => $accepted_args,
    ];
}

function register_rest_route(string $namespace, string $route, array $args = [], bool $override = false): bool {
    $GLOBALS['flacso_registered_rest_routes'][$namespace . $route] = $args;
    return true;
}

function __return_true(): bool {
    return true;
}

function admin_url(string $path = '', string $scheme = 'admin'): string {
    return 'https://flacso.edu.uy/wp-admin/' . ltrim($path, '/');
}

function get_permalink($post = 0, bool $leavename = false): string {
    $id = is_object($post) ? (int) $post->ID : (int) $post;
    return 'https://flacso.edu.uy/?p=' . $id;
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public $data;
        public $status;
        public $headers = [];

        public function __construct($data = null, int $status = 200, array $headers = []) {
            $this->data = $data;
            $this->status = $status;
            $this->headers = $headers;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status(): int {
            return $this->status;
        }

        public function get_headers(): array {
            return $this->headers;
        }

        public function header(string $key, string $value): void {
            $this->headers[$key] = $value;
        }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        protected $params = [];

        public function __construct(string $method = 'GET', string $route = '', array $params = []) {
            $this->params = $params;
        }

        public function get_param(string $key) {
            return $this->params[$key] ?? null;
        }
    }
}

if (!class_exists('FLACSO_Cohorte')) {
    class FLACSO_Cohorte {
        public const POST_TYPE = 'cohorte';
        public const META_PARENT_ID = 'oferta_academica_id';
        public static $open_cohorts = [];

        public static function accepts_registration(int $cohort_id, ?int $timestamp = null): bool {
            return !empty(self::$open_cohorts[$cohort_id]);
        }
    }
}

if (!class_exists('FLACSO_Edicion')) {
    class FLACSO_Edicion {
        public const POST_TYPE = 'edicion';
        public const META_PARENT_ID = 'seminario_id';
        public static $open_editions = [];

        public static function accepts_registration(int $edition_id, ?int $timestamp = null): bool {
            return !empty(self::$open_editions[$edition_id]);
        }
    }
}

$GLOBALS['flacso_test_posts'] = [];
$GLOBALS['flacso_test_post_meta'] = [];

function get_post($post = null, string $output = 'OBJECT', string $filter = 'raw') {
    $id = is_object($post) ? (int) $post->ID : (int) $post;
    return $GLOBALS['flacso_test_posts'][$id] ?? null;
}

function get_post_meta(int $post_id, string $key = '', bool $single = false) {
    if (!isset($GLOBALS['flacso_test_post_meta'][$post_id][$key])) {
        return $single ? '' : [];
    }
    return $GLOBALS['flacso_test_post_meta'][$post_id][$key];
}

function get_posts(array $args = []): array {
    $post_type = $args['post_type'] ?? '';
    $result = [];
    foreach ($GLOBALS['flacso_test_posts'] as $post) {
        if ($post->post_type === $post_type && $post->post_status === 'publish') {
            $result[] = $post;
        }
    }
    return $result;
}

function flacso_rest_assert_same($expected, $actual, string $message): void {
    static $assertions = 0;
    $assertions++;
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
    $GLOBALS['flacso_rest_assertions'] = $assertions;
}

function flacso_rest_assert_true($condition, string $message): void {
    flacso_rest_assert_same(true, (bool) $condition, $message);
}

// Include required module files
require_once __DIR__ . '/../modules/preinscripciones/includes/class-preinscriptions-field-catalog.php';
require_once __DIR__ . '/../modules/preinscripciones/includes/class-preinscriptions-config.php';
require_once __DIR__ . '/../modules/preinscripciones/includes/class-preinscriptions-meta.php';

$serializer_file = __DIR__ . '/../modules/preinscripciones/includes/class-preinscriptions-serializer.php';
if (!is_file($serializer_file)) {
    fwrite(STDERR, "FAIL: class-preinscriptions-serializer.php does not exist\n");
    exit(1);
}
require_once $serializer_file;

$rest_file = __DIR__ . '/../modules/preinscripciones/includes/class-preinscriptions-rest.php';
if (!is_file($rest_file)) {
    fwrite(STDERR, "FAIL: class-preinscriptions-rest.php does not exist\n");
    exit(1);
}
require_once $rest_file;

// Set up mock WordPress entities
// 1. Parent Oferta (DAVIA)
$GLOBALS['flacso_test_posts'][100] = (object) [
    'ID'          => 100,
    'post_type'   => 'oferta-academica',
    'post_status' => 'publish',
    'post_title'  => 'Diploma Superior en Aprendizaje Visual e Inteligencia Artificial',
    'post_name'   => 'davia',
];
$GLOBALS['flacso_test_post_meta'][100]['sigla'] = 'DAVIA';

// 2. Child Cohorte 11 (open)
$GLOBALS['flacso_test_posts'][101] = (object) [
    'ID'          => 101,
    'post_type'   => 'cohorte',
    'post_status' => 'publish',
    'post_title'  => 'DAVIA - Cohorte 11',
    'post_name'   => 'davia-cohorte-11',
];
$GLOBALS['flacso_test_post_meta'][101]['oferta_academica_id'] = 100;
$GLOBALS['flacso_test_post_meta'][101]['numero'] = 11;
$GLOBALS['flacso_test_post_meta'][101]['nombre'] = 'Cohorte XI';
$GLOBALS['flacso_test_post_meta'][101]['link_preinscripcion'] = 'https://flacso.edu.uy/legacy-davia';
$GLOBALS['flacso_test_post_meta'][101]['fecha_limite_preinscripcion'] = '2026-05-15';
$GLOBALS['flacso_test_post_meta'][101]['preinscripcion_formulario'] = [
    ['key' => 'documento', 'position' => 10, 'required' => true],
    ['key' => 'orientacion', 'position' => 20, 'required' => true],
    ['key' => 'mencion', 'position' => 30, 'required' => false],
];
$GLOBALS['flacso_test_post_meta'][101]['preinscripcion_orientaciones'] = [
    [
        'id'       => 'educacion',
        'name'     => 'Educación',
        'mentions' => [
            ['id' => 'tec-edu', 'name' => 'Tecnología Educativa'],
        ],
    ],
];
FLACSO_Cohorte::$open_cohorts[101] = true;

// 3. Child Cohorte 10 (closed)
$GLOBALS['flacso_test_posts'][102] = (object) [
    'ID'          => 102,
    'post_type'   => 'cohorte',
    'post_status' => 'publish',
    'post_title'  => 'DAVIA - Cohorte 10',
    'post_name'   => 'davia-cohorte-10',
];
$GLOBALS['flacso_test_post_meta'][102]['oferta_academica_id'] = 100;
$GLOBALS['flacso_test_post_meta'][102]['numero'] = 10;
$GLOBALS['flacso_test_post_meta'][102]['nombre'] = 'Cohorte X';
FLACSO_Cohorte::$open_cohorts[102] = false;

// 4. Seminario
$GLOBALS['flacso_test_posts'][200] = (object) [
    'ID'          => 200,
    'post_type'   => 'seminario',
    'post_status' => 'publish',
    'post_title'  => 'Seminario de Investigación Cualitativa',
    'post_name'   => 'metodologia',
];

// 5. Edición (open)
$GLOBALS['flacso_test_posts'][201] = (object) [
    'ID'          => 201,
    'post_type'   => 'edicion',
    'post_status' => 'publish',
    'post_title'  => 'Investigación Cualitativa - Edición 2026-03',
    'post_name'   => 'edicion-2026-03',
];
$GLOBALS['flacso_test_post_meta'][201]['seminario_id'] = 200;
$GLOBALS['flacso_test_post_meta'][201]['numero'] = 3;
$GLOBALS['flacso_test_post_meta'][201]['nombre'] = 'Edición 2026-03';
FLACSO_Edicion::$open_editions[201] = true;

// Test Serializer for single cohort
$target_cohort = FLACSO_Preinscriptions_Serializer::for_cohort(101);
flacso_rest_assert_true(is_array($target_cohort), 'cohort target is an array');
flacso_rest_assert_same('academic_offer', $target_cohort['kind'], 'target kind is academic_offer');
flacso_rest_assert_same(true, $target_cohort['registrationOpen'], 'cohort 101 registration is open');
flacso_rest_assert_same(100, $target_cohort['wordpress']['offerId'], 'offerId matches parent');
flacso_rest_assert_same(101, $target_cohort['wordpress']['cohortId'], 'cohortId matches post ID');
flacso_rest_assert_true((bool) preg_match('/^sha256:[a-f0-9]{64}$/', $target_cohort['configRevision']), 'configRevision is a valid sha256 hash');
flacso_rest_assert_same(true, $target_cohort['form']['valid'], 'form is valid');
flacso_rest_assert_same('https://flacso.edu.uy/legacy-davia', $target_cohort['urls']['legacyRegistration'], 'preserves legacy registration url');

// Test Serializer for single edition
$target_edition = FLACSO_Preinscriptions_Serializer::for_edition(201);
flacso_rest_assert_true(is_array($target_edition), 'edition target is an array');
flacso_rest_assert_same('seminar', $target_edition['kind'], 'target kind is seminar');
flacso_rest_assert_same(true, $target_edition['registrationOpen'], 'edition 201 registration is open');
flacso_rest_assert_same(200, $target_edition['wordpress']['seminarId'], 'seminarId matches parent');
flacso_rest_assert_same(201, $target_edition['wordpress']['editionId'], 'editionId matches post ID');

// Test all targets (includes open and closed)
$all = FLACSO_Preinscriptions_Serializer::all_targets();
flacso_rest_assert_same(3, count($all), 'all_targets returns both cohorts and edition');

// Verify closed cohort is included with registrationOpen = false
$closed_targets = array_filter($all, static function ($t) { return ($t['wordpress']['cohortId'] ?? null) === 102; });
$closed_target = reset($closed_targets);
flacso_rest_assert_true(!empty($closed_target), 'closed cohort is included');
flacso_rest_assert_same(false, $closed_target['registrationOpen'], 'closed cohort has registrationOpen = false');

// Test REST initialization and route registration
FLACSO_Preinscriptions_REST::init();
FLACSO_Preinscriptions_REST::register_routes();

flacso_rest_assert_true(isset($GLOBALS['flacso_registered_rest_routes']['flacso/v1/preinscripciones']), 'route flacso/v1/preinscripciones is registered');
$route_config = $GLOBALS['flacso_registered_rest_routes']['flacso/v1/preinscripciones'][0] ?? $GLOBALS['flacso_registered_rest_routes']['flacso/v1/preinscripciones'];
flacso_rest_assert_same('GET', $route_config['methods'], 'route allows GET');
flacso_rest_assert_true(is_callable($route_config['permission_callback']), 'permission_callback is callable');
flacso_rest_assert_same(true, call_user_func($route_config['permission_callback']), 'permission_callback returns true for public GET');

// Test REST index handler response
$request = new WP_REST_Request('GET', '/flacso/v1/preinscripciones');
$response = FLACSO_Preinscriptions_REST::index($request);

flacso_rest_assert_true($response instanceof WP_REST_Response, 'response is WP_REST_Response');
flacso_rest_assert_same(200, $response->get_status(), 'status is 200');

$response_data = $response->get_data();
flacso_rest_assert_same(1, $response_data['version'], 'top level version is 1');
flacso_rest_assert_true(is_array($response_data['targets']), 'targets is array');
flacso_rest_assert_same(3, count($response_data['targets']), 'contains all 3 targets');

// Check Cache-Control header
$headers = $response->get_headers();
flacso_rest_assert_true(isset($headers['Cache-Control']), 'Cache-Control header is set');
flacso_rest_assert_true(strpos($headers['Cache-Control'], 'max-age=60') !== false, 'Cache-Control has max-age=60');

printf("PASS: preinscriptions REST contract (%d assertions)\n", $GLOBALS['flacso_rest_assertions'] ?? 0);
