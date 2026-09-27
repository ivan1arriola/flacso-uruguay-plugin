# Plan de Implementación: Consultas de Ofertas Académicas, Contexto de Cohortes y Persistencia Snapshot (Fase 1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar en `flacso-uruguay-plugin` la resolución determinística de cohorte de consulta (`get_inquiry_cohort`), la validación y normalización de la abreviación de la oferta, y la captura inmutable del snapshot en la tabla `offer_inquiries` de la base de datos (con columnas forward-compatible para Mautic y seguimiento), junto con la presentación limpia en `kadence-child-flacso`.

**Architecture:** Se introduce `FLACSO_Inquiry_Context_Service` para resolver de forma pura la cohorte de consulta y el estado (`abierta`, `cerrada`, `sin_cohorte`) a partir del catálogo académico (`FLACSO_Academic_Catalog`). El repositorio `FLACSO_Offer_Inquiry_Repository` y la tabla `offer_inquiries` se amplían para almacenar estos snapshots inmutables bajo la regla "guardar primero, enviar después". El tema `kadence-child-flacso` consume el contexto vía View Model sin acoplamiento a reglas de negocio.

**Tech Stack:** PHP 7.4/8.x, WordPress (CPTs & Post Meta), PostgreSQL (producción), SQLite (suite de tests unitarios/CLI autónomos con PDO).

**Spec:** `flacso-uruguay-plugin/docs/superpowers/specs/2026-09-27-consultas-cohortes-contexto-design.md`

## Global Constraints
- Principio "guardar primero, enviar después": si la inserción en base de datos falla, abortar con 500 y no enviar ningún correo.
- Resiliencia ante abreviación ausente: si una oferta no tiene abreviación configurada, persistir con `offerAbbreviation = null` y registrar advertencia en `error_log`, sin arrojar excepción al usuario del formulario.
- Inmutabilidad de snapshots: una vez persistida la consulta, el estado de la cohorte y los límites de inscripción capturados no se recalculan ni mutan.
- Cero lógica de negocio ni persistencia en `kadence-child-flacso`: el tema solo renderiza datos preparados por el View Model.

---

### Task 1: Migración de Base de Datos y Repositorio de Consultas de Ofertas

**Files:**
- Create: `flacso-uruguay-plugin/scripts/migrations/2026-09-27-offer-inquiries-cohort-and-mautic.sql`
- Modify: `flacso-uruguay-plugin/includes/database/repositories/class-flacso-offer-inquiry-repository.php:57-97`
- Test: `flacso-uruguay-plugin/tests/inquiry-repositories-test.php`

**Interfaces:**
- Consumes: `FLACSO_Base_Inquiry_Repository`
- Produces: `FLACSO_Offer_Inquiry_Repository::insert(array $data): array` compatible con `offerAbbreviation`, `cohortWpId`, `cohortNumber`, `cohortName`, `registrationOpenAt`, `registrationCloseAt`, `offerStatus`, campos Mautic y campos Follow-up.
- Produces: `FLACSO_Offer_Inquiry_Repository::find_pending_by_email_and_cohort(string $email_normalized, int $cohort_id): ?array`

- [ ] **Step 1: Escribir el test que falla para las nuevas columnas y métodos del repositorio**

Actualizar `flacso-uruguay-plugin/tests/inquiry-repositories-test.php` para incluir la definición de las nuevas columnas en la tabla SQLite de pruebas y verificar su inserción, recuperación y el método `find_pending_by_email_and_cohort`:

```php
// En flacso-uruguay-plugin/tests/inquiry-repositories-test.php, agregar test al final:
$repo = new FLACSO_Offer_Inquiry_Repository();
$res = $repo->insert([
    'consultaId'          => 'c-snapshot-01',
    'offerWpId'           => 417,
    'offerName'           => 'DAVIA',
    'offerAbbreviation'   => 'davia',
    'cohortWpId'          => 813,
    'cohortNumber'        => 10,
    'cohortName'          => 'Cohorte X',
    'registrationOpenAt'  => '2026-09-01T00:00:00Z',
    'registrationCloseAt' => '2026-09-30T23:59:59Z',
    'offerStatus'         => 'abierta',
    'firstName'           => 'Ana',
    'lastName'            => 'Pérez',
    'email'               => 'ana@example.com',
]);

repo_assert(!empty($res['id']), 'Debe retornar un ID de inserción');
$found = $repo->find_by_consulta_id('c-snapshot-01');
repo_assert($found['offerAbbreviation'] === 'davia', 'offerAbbreviation debe ser davia');
repo_assert((int)$found['cohortWpId'] === 813, 'cohortWpId debe ser 813');
repo_assert((int)$found['cohortNumber'] === 10, 'cohortNumber debe ser 10');
repo_assert($found['cohortName'] === 'Cohorte X', 'cohortName debe ser Cohorte X');
repo_assert($found['offerStatus'] === 'abierta', 'offerStatus debe ser abierta');
repo_assert($found['registrationOpenAt'] === '2026-09-01T00:00:00Z', 'registrationOpenAt coincide');
repo_assert($found['registrationCloseAt'] === '2026-09-30T23:59:59Z', 'registrationCloseAt coincide');
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Ejecutar:
```bash
php flacso-uruguay-plugin/tests/inquiry-repositories-test.php
```
Esperado: Fallo por columnas no definidas en el SQLite del test o método `find_pending_by_email_and_cohort` inexistente.

- [ ] **Step 3: Crear script de migración SQL y actualizar el repositorio**

1. Crear `flacso-uruguay-plugin/scripts/migrations/2026-09-27-offer-inquiries-cohort-and-mautic.sql`:
```sql
-- Migración: Ampliación de offer_inquiries para Cohortes, Snapshots y Mautic
ALTER TABLE offer_inquiries
    ADD COLUMN IF NOT EXISTS "offerAbbreviation" VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS "cohortWpId" INTEGER NULL,
    ADD COLUMN IF NOT EXISTS "cohortNumber" INTEGER NULL,
    ADD COLUMN IF NOT EXISTS "cohortName" VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS "registrationOpenAt" TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS "registrationCloseAt" TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS "mauticContactId" VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS "mauticSyncStatus" VARCHAR(32) NOT NULL DEFAULT 'skipped',
    ADD COLUMN IF NOT EXISTS "mauticSyncedAt" TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS "mauticLastError" TEXT NULL,
    ADD COLUMN IF NOT EXISTS "followupDueAt" TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS "followupStatus" VARCHAR(32) NOT NULL DEFAULT 'none',
    ADD COLUMN IF NOT EXISTS "followupSentAt" TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS "followupAttempts" INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS "followupLastError" TEXT NULL;

CREATE INDEX IF NOT EXISTS idx_offer_inquiries_offer_abbr ON offer_inquiries ("offerAbbreviation");
CREATE INDEX IF NOT EXISTS idx_offer_inquiries_cohort ON offer_inquiries ("cohortWpId");
CREATE INDEX IF NOT EXISTS idx_offer_inquiries_mautic_sync ON offer_inquiries ("mauticSyncStatus");
CREATE INDEX IF NOT EXISTS idx_offer_inquiries_followup ON offer_inquiries ("followupStatus", "followupDueAt");
```

2. En `flacso-uruguay-plugin/includes/database/repositories/class-flacso-offer-inquiry-repository.php`:
Mapear en `$record`:
```php
'offerAbbreviation'   => isset($data['offerAbbreviation']) && trim((string)$data['offerAbbreviation']) !== '' ? (string)$data['offerAbbreviation'] : null,
'cohortWpId'          => isset($data['cohortWpId']) && $data['cohortWpId'] !== '' ? (int)$data['cohortWpId'] : null,
'cohortNumber'        => isset($data['cohortNumber']) && $data['cohortNumber'] !== '' ? (int)$data['cohortNumber'] : null,
'cohortName'          => isset($data['cohortName']) ? (string)$data['cohortName'] : null,
'registrationOpenAt'  => isset($data['registrationOpenAt']) && trim((string)$data['registrationOpenAt']) !== '' ? (string)$data['registrationOpenAt'] : null,
'registrationCloseAt' => isset($data['registrationCloseAt']) && trim((string)$data['registrationCloseAt']) !== '' ? (string)$data['registrationCloseAt'] : null,
'offerStatus'         => isset($data['offerStatus']) ? (string)$data['offerStatus'] : 'sin_cohorte',
'mauticContactId'     => isset($data['mauticContactId']) ? (string)$data['mauticContactId'] : null,
'mauticSyncStatus'    => isset($data['mauticSyncStatus']) ? (string)$data['mauticSyncStatus'] : 'skipped',
'mauticSyncedAt'      => isset($data['mauticSyncedAt']) ? (string)$data['mauticSyncedAt'] : null,
'mauticLastError'     => isset($data['mauticLastError']) ? (string)$data['mauticLastError'] : null,
'followupDueAt'       => isset($data['followupDueAt']) ? (string)$data['followupDueAt'] : null,
'followupStatus'      => isset($data['followupStatus']) ? (string)$data['followupStatus'] : 'none',
'followupSentAt'      => isset($data['followupSentAt']) ? (string)$data['followupSentAt'] : null,
'followupAttempts'    => isset($data['followupAttempts']) ? (int)$data['followupAttempts'] : 0,
'followupLastError'   => isset($data['followupLastError']) ? (string)$data['followupLastError'] : null,
```
Y agregar el método:
```php
public function find_pending_by_email_and_cohort(string $email_normalized, int $cohort_id): ?array {
    $email_norm = strtolower(trim($email_normalized));
    if ($email_norm === '' || $cohort_id <= 0) {
        return null;
    }
    $pdo = FLACSO_DB::connection();
    $table = $this->get_table_name();
    $sql = "SELECT * FROM {$table} WHERE \"emailNormalized\" = :email AND \"cohortWpId\" = :cohort_id AND \"followupStatus\" = 'pending' ORDER BY \"createdAt\" DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email_norm, ':cohort_id' => $cohort_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
```

- [ ] **Step 4: Ejecutar el test para verificar que pasa**

Ejecutar:
```bash
php flacso-uruguay-plugin/tests/inquiry-repositories-test.php
```
Esperado: PASS (`OK inquiry-repositories-test`).

- [ ] **Step 5: Commit de Task 1**

```bash
git -C flacso-uruguay-plugin add scripts/migrations/2026-09-27-offer-inquiries-cohort-and-mautic.sql includes/database/repositories/class-flacso-offer-inquiry-repository.php tests/inquiry-repositories-test.php
git -C flacso-uruguay-plugin commit -m "feat(consultas): add cohort snapshot and mautic columns to offer inquiries repository"
```

---

### Task 2: Normalización y Validación de Unicidad de Abreviación de Ofertas

**Files:**
- Modify: `flacso-uruguay-plugin/modules/oferta-academica/includes/class-oferta-academica.php:55-65`
- Modify: `flacso-uruguay-plugin/modules/oferta-academica/includes/class-oferta-admin-fields.php:333-379`
- Create: `flacso-uruguay-plugin/tests/oferta-abreviacion-test.php`

**Interfaces:**
- Produces: `FLACSO_Oferta_Academica::normalize_abbreviation(string $raw): string`
- Produces: `FLACSO_Oferta_Academica::is_abbreviation_available(string $abbr, int $exclude_post_id = 0): bool`
- Consumes: WordPress `get_posts()` o direct meta query

- [ ] **Step 1: Escribir el test que falla para normalización y unicidad de abreviación**

Crear `flacso-uruguay-plugin/tests/oferta-abreviacion-test.php`:
```php
<?php
// tests/oferta-abreviacion-test.php
$root = dirname(__DIR__);

// Mocks de WordPress
$GLOBALS['mock_post_meta'] = [];
if (!function_exists('sanitize_title')) {
    function sanitize_title($str) {
        $str = strtolower(trim($str));
        $str = preg_replace('/[^a-z0-9_\-]+/', '-', $str);
        return trim($str, '-');
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags((string)$str));
    }
}
if (!function_exists('get_post_meta')) {
    function get_post_meta($id, $key, $single = false) {
        return $GLOBALS['mock_post_meta'][$id][$key] ?? ($single ? '' : []);
    }
}
if (!function_exists('get_posts')) {
    function get_posts($args) {
        $results = [];
        $key = $args['meta_query'][0]['key'] ?? '';
        $val = $args['meta_query'][0]['value'] ?? '';
        $exclude = $args['exclude'] ?? [];
        foreach ($GLOBALS['mock_post_meta'] as $pid => $meta) {
            if (in_array($pid, $exclude, true)) continue;
            if (isset($meta[$key]) && $meta[$key] === $val) {
                $results[] = (object)['ID' => $pid];
            }
        }
        return $results;
    }
}

require_once $root . '/modules/oferta-academica/includes/class-oferta-academica.php';

function test_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// 1. Normalización
test_assert(FLACSO_Oferta_Academica::normalize_abbreviation('DAVIA') === 'davia', 'DAVIA -> davia');
test_assert(FLACSO_Oferta_Academica::normalize_abbreviation('  Mg. Ed  ') === 'mg-ed', 'Mg. Ed -> mg-ed');
test_assert(FLACSO_Oferta_Academica::normalize_abbreviation('') === '', 'vacio -> vacio');

// 2. Unicidad
$GLOBALS['mock_post_meta'][100] = ['abreviacion' => 'davia'];
test_assert(FLACSO_Oferta_Academica::is_abbreviation_available('davia', 100) === true, 'davia disponible para su propio post');
test_assert(FLACSO_Oferta_Academica::is_abbreviation_available('davia', 101) === false, 'davia ocupada para post 101');
test_assert(FLACSO_Oferta_Academica::is_abbreviation_available('mesyp', 101) === true, 'mesyp libre');

echo "OK oferta-abreviacion-test\n";
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Ejecutar:
```bash
php flacso-uruguay-plugin/tests/oferta-abreviacion-test.php
```
Esperado: FAIL con error de métodos no definidos en `FLACSO_Oferta_Academica`.

- [ ] **Step 3: Implementar métodos en `FLACSO_Oferta_Academica` y validación en `FLACSO_Oferta_Admin_Fields`**

1. En `flacso-uruguay-plugin/modules/oferta-academica/includes/class-oferta-academica.php`, añadir:
```php
public static function normalize_abbreviation(string $raw): string {
    $clean = function_exists('sanitize_title') ? sanitize_title(strtolower(trim($raw))) : strtolower(trim(preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $raw), '-'));
    return $clean;
}

public static function is_abbreviation_available(string $abbr, int $exclude_post_id = 0): bool {
    $norm = self::normalize_abbreviation($abbr);
    if ($norm === '') {
        return true;
    }
    if (!function_exists('get_posts')) {
        return true;
    }
    $args = [
        'post_type'      => self::POST_TYPE,
        'post_status'    => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'abreviacion',
                'value'   => $norm,
                'compare' => '=',
            ],
        ],
    ];
    if ($exclude_post_id > 0) {
        $args['exclude'] = [$exclude_post_id];
    }
    $existing = get_posts($args);
    return empty($existing);
}
```

2. En `flacso-uruguay-plugin/modules/oferta-academica/includes/class-oferta-admin-fields.php`:
En `save()`, al procesar `'abreviacion'`:
```php
$raw_abbr = $data['abreviacion'] ?? '';
$normalized_abbr = FLACSO_Oferta_Academica::normalize_abbreviation((string)$raw_abbr);
if ($normalized_abbr !== '') {
    if (FLACSO_Oferta_Academica::is_abbreviation_available($normalized_abbr, $post_id)) {
        update_post_meta($post_id, 'abreviacion', $normalized_abbr);
    } else {
        // No sobrescribir con valor colisionado; registrar aviso
        set_transient('flacso_oferta_abbr_error_' . $post_id, sprintf(__('La abreviación "%s" ya está en uso por otra oferta académica.', 'flacso-uruguay'), esc_html($normalized_abbr)), 45);
    }
} else {
    delete_post_meta($post_id, 'abreviacion');
}
```
Añadir hook para renderizar aviso en admin si existe el transient.

- [ ] **Step 4: Ejecutar el test para verificar que pasa**

Ejecutar:
```bash
php flacso-uruguay-plugin/tests/oferta-abreviacion-test.php
```
Esperado: PASS (`OK oferta-abreviacion-test`).

- [ ] **Step 5: Commit de Task 2**

```bash
git -C flacso-uruguay-plugin add modules/oferta-academica/includes/class-oferta-academica.php modules/oferta-academica/includes/class-oferta-admin-fields.php tests/oferta-abreviacion-test.php
git -C flacso-uruguay-plugin commit -m "feat(oferta): add abbreviation normalization and uniqueness validation"
```

---

### Task 3: Resolución de Cohorte de Consulta en `FLACSO_Academic_Catalog`

**Files:**
- Modify: `flacso-uruguay-plugin/modules/oferta-academica/includes/class-academic-catalog.php:8-18`
- Create: `flacso-uruguay-plugin/tests/inquiry-cohort-resolution-test.php`

**Interfaces:**
- Produces: `FLACSO_Academic_Catalog::get_inquiry_cohort(int $offer_id): ?array`
- Modifies: `FLACSO_Academic_Catalog::get_offer(int $offer_id): array` para incluir `'cohorte_consulta'`

- [ ] **Step 1: Escribir el test que falla para `get_inquiry_cohort`**

Crear `flacso-uruguay-plugin/tests/inquiry-cohort-resolution-test.php`:
```php
<?php
// tests/inquiry-cohort-resolution-test.php
$root = dirname(__DIR__);

if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');

$GLOBALS['mock_cohorts'] = [];

class FLACSO_Academic_Repository {
    public static function to_array($type, $id) {
        return ['id' => $id, 'nombre' => 'Oferta Test'];
    }
    public static function list($type, $filters = []) {
        $parent = $filters['parent_id'] ?? 0;
        return $GLOBALS['mock_cohorts'][$parent] ?? [];
    }
}

require_once $root . '/modules/oferta-academica/includes/class-academic-catalog.php';

function test_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// Escenario 1: Cohorte con preinscripción abierta
$GLOBALS['mock_cohorts'][1] = [
    [
        'id' => 10,
        'numero' => 10,
        'nombre' => 'Cohorte X',
        'fecha_inicio' => '2026-10-01',
        'preinscripcion' => ['abierta' => true, 'url' => 'https://pre.test/10'],
        'estado' => 'planificada',
    ],
    [
        'id' => 9,
        'numero' => 9,
        'nombre' => 'Cohorte IX',
        'fecha_inicio' => '2025-10-01',
        'preinscripcion' => ['abierta' => false],
        'estado' => 'finalizada',
    ],
];
$c1 = FLACSO_Academic_Catalog::get_inquiry_cohort(1);
test_assert($c1 !== null && $c1['id'] === 10, 'Debe elegir cohorte 10 abierta');

// Escenario 2: Sin cohorte abierta, pero cohorte futura planificada
$GLOBALS['mock_cohorts'][2] = [
    [
        'id' => 11,
        'numero' => 11,
        'nombre' => 'Cohorte XI',
        'fecha_inicio' => '2027-03-01',
        'preinscripcion' => ['abierta' => false],
        'estado' => 'planificada',
    ],
    [
        'id' => 10,
        'numero' => 10,
        'nombre' => 'Cohorte X',
        'fecha_inicio' => '2026-03-01',
        'preinscripcion' => ['abierta' => false],
        'estado' => 'finalizada',
    ],
];
$c2 = FLACSO_Academic_Catalog::get_inquiry_cohort(2);
test_assert($c2 !== null && $c2['id'] === 11, 'Debe elegir cohorte futura 11');

// Escenario 3: Sin cohorte abierta ni futura
$GLOBALS['mock_cohorts'][3] = [
    [
        'id' => 10,
        'numero' => 10,
        'nombre' => 'Cohorte X',
        'fecha_inicio' => '2025-03-01',
        'preinscripcion' => ['abierta' => false],
        'estado' => 'finalizada',
    ],
];
$c3 = FLACSO_Academic_Catalog::get_inquiry_cohort(3);
test_assert($c3 === null, 'Debe retornar null cuando no hay abierta ni futura');

// Escenario 4: get_offer incluye cohorte_consulta
$offer = FLACSO_Academic_Catalog::get_offer(1);
test_assert(isset($offer['cohorte_consulta']) && $offer['cohorte_consulta']['id'] === 10, 'get_offer incluye cohorte_consulta');

echo "OK inquiry-cohort-resolution-test\n";
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Ejecutar:
```bash
php flacso-uruguay-plugin/tests/inquiry-cohort-resolution-test.php
```
Esperado: FAIL con error de método no definido `get_inquiry_cohort`.

- [ ] **Step 3: Implementar `get_inquiry_cohort` en `FLACSO_Academic_Catalog`**

En `flacso-uruguay-plugin/modules/oferta-academica/includes/class-academic-catalog.php`:
1. Implementar `get_inquiry_cohort(int $offer_id): ?array`:
```php
public static function get_inquiry_cohort(int $offer_id): ?array {
    $cohorts = FLACSO_Academic_Repository::list('cohortes', ['parent_id' => $offer_id, 'per_page' => 200]);
    if (empty($cohorts)) {
        return null;
    }

    $today = function_exists('current_time') ? current_time('Y-m-d') : date('Y-m-d');

    // 1. Cohorte con preinscripción abierta
    $open_cohorts = [];
    foreach ($cohorts as $cohort) {
        if (!empty($cohort['preinscripcion']['abierta'])) {
            $open_cohorts[] = $cohort;
        }
    }

    if (!empty($open_cohorts)) {
        usort($open_cohorts, static function (array $a, array $b): int {
            $date_a = (string) ($a['fecha_inicio'] ?? '');
            $date_b = (string) ($b['fecha_inicio'] ?? '');
            if ($date_a !== '' && $date_b !== '') {
                $cmp = strcmp($date_a, $date_b);
                if ($cmp !== 0) {
                    return $cmp;
                }
            } elseif ($date_a !== '') {
                return -1;
            } elseif ($date_b !== '') {
                return 1;
            }
            return absint($b['numero'] ?? 0) <=> absint($a['numero'] ?? 0);
        });
        return $open_cohorts[0];
    }

    // 2. Cohorte planificada futura
    $upcoming_planificadas = [];
    foreach ($cohorts as $cohort) {
        if (($cohort['estado'] ?? '') === 'planificada') {
            $start = (string) ($cohort['fecha_inicio'] ?? '');
            if ($start === '' || $start >= $today) {
                $upcoming_planificadas[] = $cohort;
            }
        }
    }

    if (!empty($upcoming_planificadas)) {
        usort($upcoming_planificadas, static function (array $a, array $b): int {
            $date_a = (string) ($a['fecha_inicio'] ?? '');
            $date_b = (string) ($b['fecha_inicio'] ?? '');
            if ($date_a !== '' && $date_b !== '') {
                $cmp = strcmp($date_a, $date_b);
                if ($cmp !== 0) {
                    return $cmp;
                }
            } elseif ($date_a !== '') {
                return -1;
            } elseif ($date_b !== '') {
                return 1;
            }
            return absint($a['numero'] ?? 0) <=> absint($b['numero'] ?? 0);
        });
        return $upcoming_planificadas[0];
    }

    // 3. Ninguna cohorte abierta ni futura
    return null;
}
```
2. En `get_offer(int $offer_id)`:
```php
$offer['cohortes'] = $cohorts;
$offer['cohorte_vigente'] = self::current_item($cohorts);
$offer['cohorte_consulta'] = self::get_inquiry_cohort($offer_id);
return $offer;
```

- [ ] **Step 4: Ejecutar el test para verificar que pasa**

Ejecutar:
```bash
php flacso-uruguay-plugin/tests/inquiry-cohort-resolution-test.php
```
Esperado: PASS (`OK inquiry-cohort-resolution-test`).

- [ ] **Step 5: Commit de Task 3**

```bash
git -C flacso-uruguay-plugin add modules/oferta-academica/includes/class-academic-catalog.php tests/inquiry-cohort-resolution-test.php
git -C flacso-uruguay-plugin commit -m "feat(catalog): add get_inquiry_cohort to academic catalog"
```

---

### Task 4: Servicio de Contexto de Consultas (`FLACSO_Inquiry_Context_Service`)

**Files:**
- Create: `flacso-uruguay-plugin/modules/consultas/services/class-flacso-inquiry-context-service.php`
- Modify: `flacso-uruguay-plugin/modules/consultas/init.php:1-35`
- Create: `flacso-uruguay-plugin/tests/inquiry-context-test.php`

**Interfaces:**
- Consumes: `FLACSO_Academic_Catalog::get_offer()`, `FLACSO_Oferta_Academica::normalize_abbreviation()`
- Produces: `FLACSO_Inquiry_Context_Service::resolve(int $offer_id, array $inquiry_data = []): array`

- [ ] **Step 1: Escribir el test unitario para `FLACSO_Inquiry_Context_Service`**

Crear `flacso-uruguay-plugin/tests/inquiry-context-test.php` probando:
1. Oferta con cohorte abierta $\to$ `offerStatus = 'abierta'`, captura `registrationOpenAt`, `registrationCloseAt`, `offerAbbreviation`.
2. Oferta con cohorte planificada $\to$ `offerStatus = 'cerrada'`.
3. Oferta sin cohorte $\to$ `offerStatus = 'sin_cohorte'`, campos de cohorte en `null`.
4. Oferta sin abreviación $\to$ `offerAbbreviation = null`, sin arrojar excepción.

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Ejecutar:
```bash
php flacso-uruguay-plugin/tests/inquiry-context-test.php
```
Esperado: FAIL con error de clase inexistente `FLACSO_Inquiry_Context_Service`.

- [ ] **Step 3: Implementar `FLACSO_Inquiry_Context_Service` y registrar en `init.php`**

Crear `flacso-uruguay-plugin/modules/consultas/services/class-flacso-inquiry-context-service.php`:
```php
<?php
/**
 * Servicio de resolución del contexto académico y snapshots para consultas de ofertas.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

class FLACSO_Inquiry_Context_Service {
    public static function resolve(int $offer_id, array $inquiry_data = []): array {
        $catalog_data = [];
        if ($offer_id > 0 && class_exists('FLACSO_Academic_Catalog') && method_exists('FLACSO_Academic_Catalog', 'get_offer')) {
            try {
                $catalog_data = FLACSO_Academic_Catalog::get_offer($offer_id);
            } catch (\Throwable $t) {
                $catalog_data = [];
            }
        }

        // 1. Nombre de la oferta
        $offer_name = trim((string)($inquiry_data['offerName'] ?? $inquiry_data['titulo_posgrado'] ?? ''));
        if ($offer_name === '' && !empty($catalog_data['nombre'])) {
            $offer_name = (string)$catalog_data['nombre'];
        } elseif ($offer_name === '' && function_exists('get_post')) {
            $post = get_post($offer_id);
            if ($post && !empty($post->post_title)) {
                $offer_name = (string)$post->post_title;
            }
        }

        // 2. Tipo de oferta
        $offer_type = $inquiry_data['offerType'] ?? $inquiry_data['tipo_oferta'] ?? ($catalog_data['tipo'] ?? null);

        // 3. Abreviación canónica
        $raw_abbr = $inquiry_data['offerAbbreviation'] ?? ($catalog_data['abreviacion'] ?? null);
        $offer_abbr = null;
        if ($raw_abbr !== null && trim((string)$raw_abbr) !== '') {
            $offer_abbr = class_exists('FLACSO_Oferta_Academica')
                ? FLACSO_Oferta_Academica::normalize_abbreviation((string)$raw_abbr)
                : sanitize_title(strtolower(trim((string)$raw_abbr)));
        } else {
            error_log(sprintf('[FLACSO] Oferta ID %d (%s) sin abreviacion configurada al procesar consulta.', $offer_id, $offer_name));
        }

        // 4. Cohorte de consulta
        $inquiry_cohort = $catalog_data['cohorte_consulta'] ?? null;
        if ($inquiry_cohort === null && class_exists('FLACSO_Academic_Catalog') && method_exists('FLACSO_Academic_Catalog', 'get_inquiry_cohort')) {
            $inquiry_cohort = FLACSO_Academic_Catalog::get_inquiry_cohort($offer_id);
        }

        $cohort_wp_id = null;
        $cohort_number = null;
        $cohort_name = null;
        $reg_open_at = null;
        $reg_close_at = null;
        $preinscripcion_url = null;
        $offer_status = 'sin_cohorte';

        if (!empty($inquiry_cohort)) {
            $cohort_wp_id = absint($inquiry_cohort['id'] ?? 0) ?: null;
            $cohort_number = absint($inquiry_cohort['numero'] ?? 0) ?: null;
            $cohort_name = (string)($inquiry_cohort['nombre'] ?? '');
            
            $reg_data = $inquiry_cohort['preinscripcion'] ?? [];
            $is_open = !empty($reg_data['abierta']);
            $offer_status = $is_open ? 'abierta' : 'cerrada';
            
            $reg_open_at = !empty($reg_data['desde']) ? (string)$reg_data['desde'] : null;
            $reg_close_at = !empty($reg_data['hasta']) ? (string)$reg_data['hasta'] : null;
            $preinscripcion_url = !empty($reg_data['url']) ? (string)$reg_data['url'] : null;
        }

        return [
            'offerWpId'           => $offer_id > 0 ? $offer_id : null,
            'offerName'           => $offer_name,
            'offerType'           => $offer_type,
            'offerAbbreviation'   => $offer_abbr,
            'cohortWpId'          => $cohort_wp_id,
            'cohortNumber'        => $cohort_number,
            'cohortName'          => $cohort_name,
            'registrationOpenAt'  => $reg_open_at,
            'registrationCloseAt' => $reg_close_at,
            'offerStatus'         => $offer_status,
            'preinscripcionUrl'   => $preinscripcion_url,
            'replyToEmail'        => $catalog_data['correo'] ?? null,
            'startValue'          => $inquiry_cohort['fecha_inicio'] ?? ($catalog_data['cohorte_vigente']['fecha_inicio'] ?? ''),
            'startPrecision'      => $inquiry_cohort['precision_fecha_inicio'] ?? ($catalog_data['cohorte_vigente']['precision_fecha_inicio'] ?? 'dia'),
            'modalityLabel'       => $inquiry_cohort['modalidad'] ?? ($catalog_data['cohorte_vigente']['modalidad'] ?? ''),
        ];
    }
}
```
Y en `flacso-uruguay-plugin/modules/consultas/init.php`, incluir el archivo `require_once __DIR__ . '/services/class-flacso-inquiry-context-service.php';`.

- [ ] **Step 4: Ejecutar el test para verificar que pasa**

Ejecutar:
```bash
php flacso-uruguay-plugin/tests/inquiry-context-test.php
```
Esperado: PASS (`OK inquiry-context-test`).

- [ ] **Step 5: Commit de Task 4**

```bash
git -C flacso-uruguay-plugin add modules/consultas/services/class-flacso-inquiry-context-service.php modules/consultas/init.php tests/inquiry-context-test.php
git -C flacso-uruguay-plugin commit -m "feat(consultas): add FLACSO_Inquiry_Context_Service"
```

---

### Task 5: Integración en `FLACSO_Offer_Inquiry_Service` y Panel de Consultas Admin

**Files:**
- Modify: `flacso-uruguay-plugin/modules/consultas/services/class-flacso-offer-inquiry-service.php`
- Modify: `flacso-uruguay-plugin/modules/consultas/includes/class-flacso-consultas-admin.php`
- Test: `flacso-uruguay-plugin/tests/inquiry-services-test.php`
- Test: `flacso-uruguay-plugin/tests/mail-console-and-consultas-admin-test.php`

**Interfaces:**
- Consumes: `FLACSO_Inquiry_Context_Service::resolve()`
- Produces: `FLACSO_Offer_Inquiry_Service::submit(array $data): array` enriquecido con `offer_status`, `cohort_number`, `offer_abbreviation`.

- [ ] **Step 1: Escribir el test que falla para la orquestación en `inquiry-services-test.php`**

Actualizar `flacso-uruguay-plugin/tests/inquiry-services-test.php` para verificar que `FLACSO_Offer_Inquiry_Service::submit($data)`:
1. Delega en `FLACSO_Inquiry_Context_Service`.
2. Persiste en DB con `offerAbbreviation`, `cohortWpId`, `cohortNumber`, `cohortName`, `offerStatus`, etc.
3. Retorna `offer_status` y `cohort_number` en la respuesta.

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Ejecutar:
```bash
php flacso-uruguay-plugin/tests/inquiry-services-test.php
```
Esperado: FAIL o advertencia de campos faltantes.

- [ ] **Step 3: Actualizar `FLACSO_Offer_Inquiry_Service` y `FLACSO_Consultas_Admin`**

1. En `flacso-uruguay-plugin/modules/consultas/services/class-flacso-offer-inquiry-service.php`:
Invocar `FLACSO_Inquiry_Context_Service::resolve($offer_id, $data)` y utilizar los campos resueltos para poblar `$record` antes de `repo->insert($record)`.
2. En `flacso-uruguay-plugin/modules/consultas/includes/class-flacso-consultas-admin.php`:
Añadir a la tabla de consultas de ofertas la columna Abreviación (con badge), Cohorte (ej: `Cohorte X`), y el badge de estado al consultar (`🟢 Abierta`, `⚪ Cerrada`, `➖ Sin cohorte`).

- [ ] **Step 4: Ejecutar los tests de servicios y admin**

Ejecutar:
```bash
php flacso-uruguay-plugin/tests/inquiry-services-test.php
php flacso-uruguay-plugin/tests/mail-console-and-consultas-admin-test.php
```
Esperado: PASS en ambos.

- [ ] **Step 5: Commit de Task 5**

```bash
git -C flacso-uruguay-plugin add modules/consultas/services/class-flacso-offer-inquiry-service.php modules/consultas/includes/class-flacso-consultas-admin.php tests/inquiry-services-test.php
git -C flacso-uruguay-plugin commit -m "feat(consultas): integrate context service and update admin columns"
```

---

### Task 6: View Model y Contexto en `kadence-child-flacso`

**Files:**
- Modify: `kadence-child-flacso/inc/academic-public-model.php`
- Modify: `kadence-child-flacso/template-parts/oferta/default.php`
- Test: `kadence-child-flacso/tests/academic-public-model-test.php`
- Verify: `kadence-child-flacso/bin/check-theme.sh`

**Interfaces:**
- Consumes: `FLACSO_Academic_Catalog::get_offer()` -> `'cohorte_consulta'`
- Produces: `$context['inquiry_cohort']` en el View Model del tema.

- [ ] **Step 1: Escribir el test que falla para `$context['inquiry_cohort']`**

Actualizar `kadence-child-flacso/tests/academic-public-model-test.php` para verificar que `flacso_theme_get_offer_context($post_id)` contiene la clave `inquiry_cohort` con `has_cohort`, `name`, `number`, `is_open`, `label`.

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Ejecutar:
```bash
php kadence-child-flacso/tests/academic-public-model-test.php
```
Esperado: FAIL por clave `inquiry_cohort` ausente.

- [ ] **Step 3: Implementar la proyección en `academic-public-model.php` y el banner sutil en `default.php`**

1. En `kadence-child-flacso/inc/academic-public-model.php`:
Proyectar `cohorte_consulta` en `$context['inquiry_cohort']`.
2. En `kadence-child-flacso/template-parts/oferta/default.php`:
Antes de `flacso_consultas_render_form()`, si `!empty($context['inquiry_cohort']['has_cohort'])`, imprimir el indicador sutil de cohorte de consulta.

- [ ] **Step 4: Ejecutar tests del tema y script de validación arquitectónica**

Ejecutar:
```bash
php kadence-child-flacso/tests/academic-public-model-test.php
bash kadence-child-flacso/bin/check-theme.sh
```
Esperado: PASS en ambos.

- [ ] **Step 5: Commit de Task 6**

```bash
git -C kadence-child-flacso add inc/academic-public-model.php template-parts/oferta/default.php tests/academic-public-model-test.php
git -C kadence-child-flacso commit -m "feat(oferta): add inquiry cohort view model and subtle context indicator"
```

---

### Task 7: Verificación Integral End-to-End y Regresión

**Files:**
- Test: Todas las suites de tests en `flacso-uruguay-plugin/tests/`
- Test: Todas las suites de tests en `kadence-child-flacso/tests/`

- [ ] **Step 1: Ejecutar la suite completa de tests del plugin**

Ejecutar:
```bash
for t in flacso-uruguay-plugin/tests/*-test.php; do
    echo "Running $t..."
    php "$t" || exit 1
done
```
Esperado: Todos los tests pasan con código de salida 0.

- [ ] **Step 2: Ejecutar la suite completa del tema**

Ejecutar:
```bash
for t in kadence-child-flacso/tests/*-test.php; do
    echo "Running $t..."
    php "$t" || exit 1
done
bash kadence-child-flacso/bin/check-theme.sh
```
Esperado: Todos los tests pasan y no hay violaciones de arquitectura en el tema.

- [ ] **Step 3: Commit final de verificación (si aplica) o reporte**
