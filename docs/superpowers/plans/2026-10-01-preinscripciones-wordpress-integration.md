# WordPress–Preinscripciones Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a reversible, public REST v1 catalog so the new Preinscripciones app can discover FLACSO destinations, form configuration, open/closed state, and structured orientation options while the legacy flow remains unchanged.

**Architecture:** Add a focused `modules/preinscripciones/` module. Keep field semantics and submission logic in the new app, store effective form configuration on each Cohorte/Edición, and serialize existing WordPress academic entities behind `GET /wp-json/flacso/v1/preinscripciones`.

**Tech Stack:** PHP 7.4+, WordPress REST API, WordPress post meta/admin metaboxes, existing plugin loader, standalone PHP contract tests.

**Spec:** `docs/superpowers/specs/2026-10-01-preinscripciones-wordpress-integration-design.md`

## Global Constraints

- The public endpoint is `GET /wp-json/flacso/v1/preinscripciones`.
- The response has numeric `version: 1` and opaque stable target IDs.
- `kind` discriminates `academic_offer` and `seminar` targets.
- Destinations include open and closed targets.
- `registrationWindow.from` and `until` are ISO 8601 strings or `null`.
- `configRevision` is a stable `sha256:<digest>` over canonical effective configuration.
- The plugin does not generate `appUrl` and does not receive personal form data.
- `link_preinscripcion` and the current legacy flow remain compatible and are not rewritten automatically.
- The public API is read-only, contains no personal data, and advertises approximately 60 seconds of caching.
- Invalid configurations are explicit and safe; the app must reject invalid forms.
- Do not add a second registration-opening flag; reuse the current Cohorte/Edición opening decisions.

## Review Focus

- A legacy or malformed saved configuration must not be silently treated as valid; test `unknown_input`, duplicates, malformed positions, and invalid orientation structures.
- A target ID and `configRevision` must not change because a visible title, slug, array insertion order, or response timestamp changed; test canonicalization and hashing.
- A closed destination must remain discoverable while `registrationOpen` is false; test legacy date/boolean behavior for both Cohorte and Edición.
- The public response must not leak arbitrary post meta, admin credentials, traces, or personal data; test an allowlisted serializer shape.
- Existing `link_preinscripcion` values and current registration methods must remain unchanged; test legacy URL serialization and source contracts.

---

### Task 1: Add the Preinscriptions module and pure configuration contract

**Files:**
- Create: `modules/preinscripciones/init.php`
- Create: `modules/preinscripciones/includes/class-preinscriptions-field-catalog.php`
- Create: `modules/preinscripciones/includes/class-preinscriptions-config.php`
- Create: `modules/preinscripciones/includes/class-preinscriptions-meta.php`
- Test: `tests/preinscriptions-config-contract-test.php`

**Interfaces:**
- `FLACSO_Preinscriptions_Field_Catalog::keys(): array` returns the approved eight keys and no others.
- `FLACSO_Preinscriptions_Field_Catalog::labels(): array` returns admin labels for those keys.
- `FLACSO_Preinscriptions_Field_Catalog::has(string $key): bool` checks membership after key normalization.
- `FLACSO_Preinscriptions_Config::sanitize_inputs($value): array` returns ordered unique `{key, position, required}` records.
- `FLACSO_Preinscriptions_Config::sanitize_orientations($value): array` returns normalized orientation records with nested mentions.
- `FLACSO_Preinscriptions_Config::canonical_payload(array $inputs, array $orientations): array` returns deterministic data for hashing.
- `FLACSO_Preinscriptions_Config::revision(array $canonical): string` returns `sha256:<digest>`.
- `FLACSO_Preinscriptions_Meta::init(): void` registers the two serialized meta values for `cohorte` and `edicion` with admin-only write callbacks.

- [ ] **Step 1: Write the failing pure contract tests**

  Assert the exact field catalog; reject unknown keys and duplicate keys; normalize positions to positive ordered integers; normalize `required` to boolean; preserve orientation-to-mention nesting; produce the same canonical payload and revision for semantically identical input with different array insertion order; produce different revisions when a required flag or field changes; and reject malformed records without PHP warnings.

- [ ] **Step 2: Run the focused test to verify it fails**

  Run: `php tests/preinscriptions-config-contract-test.php`

  Expected: FAIL because the module classes do not exist.

- [ ] **Step 3: Implement the catalog, config normalizer, revision, and meta registration**

  Keep the catalog authoritative only for admin selection. Sort by numeric `position`, deduplicate by key, omit invalid records, and hash canonical JSON with stable key ordering. Register `preinscripcion_formulario` and `preinscripcion_orientaciones` as single array meta without exposing them through generic public REST.

- [ ] **Step 4: Run the focused test to verify it passes**

  Run: `php tests/preinscriptions-config-contract-test.php`

  Expected: PASS with explicit assertions for all normalization and revision rules.

- [ ] **Step 5: Commit**

  ```bash
  git add modules/preinscripciones tests/preinscriptions-config-contract-test.php
  git commit -m "feat: add preinscription configuration contract"
  ```

### Task 2: Add administrative configuration UI for Cohortes and Ediciones

**Files:**
- Create: `modules/preinscripciones/includes/class-preinscriptions-admin.php`
- Modify: `modules/preinscripciones/init.php`
- Test: `tests/preinscriptions-admin-contract-test.php`

**Interfaces:**
- `FLACSO_Preinscriptions_Admin::init(): void` registers metaboxes and save hooks for both post types.
- `FLACSO_Preinscriptions_Admin::render($post): void` renders the field selector, sortable configured list, required controls, and structured orientation/mention editor.
- `FLACSO_Preinscriptions_Admin::save(int $post_id, WP_Post $post): void` verifies nonce/capability/autosave/revision guards, sanitizes submitted arrays through `FLACSO_Preinscriptions_Config`, and persists both metas.

- [ ] **Step 1: Write the failing admin contract tests**

  Assert the module initializes the admin class; both `cohorte` and `edicion` receive the metabox and save hook; the rendered UI names use the approved field keys; the sortable list carries positions; the UI includes required controls and orientation/mention structure; saving is guarded by nonce and `current_user_can`; and no admin control exposes HTML type, regex, Sheets column, or arbitrary input key editing.

- [ ] **Step 2: Run the focused test to verify it fails**

  Run: `php tests/preinscriptions-admin-contract-test.php`

  Expected: FAIL because the admin class and hooks do not exist.

- [ ] **Step 3: Implement the shared admin metabox**

  Use the existing plugin admin patterns and `jquery-ui-sortable` already used by the editor. Keep the new UI in its own metabox so `class-cohorte.php` and `class-edicion-admin-fields.php` retain their existing responsibilities. Save blank submissions as empty arrays, normalize positions server-side, and preserve existing legacy preinscription fields untouched.

- [ ] **Step 4: Run the focused test to verify it passes**

  Run: `php tests/preinscriptions-admin-contract-test.php`

  Expected: PASS.

- [ ] **Step 5: Commit**

  ```bash
  git add modules/preinscripciones tests/preinscriptions-admin-contract-test.php
  git commit -m "feat: add preinscription form administration"
  ```

### Task 3: Implement target serializers and public REST v1

**Files:**
- Create: `modules/preinscripciones/includes/class-preinscriptions-serializer.php`
- Create: `modules/preinscripciones/includes/class-preinscriptions-rest.php`
- Modify: `modules/preinscripciones/init.php`
- Test: `tests/preinscriptions-rest-contract-test.php`

**Interfaces:**
- `FLACSO_Preinscriptions_Serializer::all_targets(): array` returns the complete allowlisted target payload.
- `FLACSO_Preinscriptions_Serializer::for_cohort(int $cohort_id): ?array` returns one academic-offer target or `null`.
- `FLACSO_Preinscriptions_Serializer::for_edition(int $edition_id): ?array` returns one seminar target or `null`.
- `FLACSO_Preinscriptions_REST::init(): void` attaches route registration to `rest_api_init`.
- `FLACSO_Preinscriptions_REST::register_routes(): void` registers the public GET route with `__return_true` permission.
- `FLACSO_Preinscriptions_REST::index(WP_REST_Request $request): WP_REST_Response` returns `{version: 1, targets: [...]}` and sets `Cache-Control: public, max-age=60`.

- [ ] **Step 1: Write the failing serializer and REST tests**

  Assert the exact top-level version, `kind` discriminators, opaque stable IDs, distinct `wordpress` fields, ISO/null registration windows, open and closed target inclusion, `configRevision`, valid/invalid form states, structured orientations, allowlisted URLs including legacy registration, absence of personal data/arbitrary meta, public permission, and 60-second cache header.

- [ ] **Step 2: Run the focused test to verify it fails**

  Run: `php tests/preinscriptions-rest-contract-test.php`

  Expected: FAIL because the serializer, route, and contract fixtures do not exist.

- [ ] **Step 3: Implement allowlisted target serialization**

  Resolve each Cohorte through its parent Oferta and each Edición through its parent Seminario. Reuse the existing `accepts_registration()` methods and current date/window metadata. Use stable opaque IDs based on immutable WordPress identity, not visible title/slug. Hash canonical effective form configuration, and include safe issue codes for invalid legacy data.

- [ ] **Step 4: Implement the public route and response headers**

  Register only the read route in this module. Keep write routes absent. Return all eligible destinations, including closed ones, and never serialize `$_POST`, arbitrary post meta, internal stack traces, or personal fields.

- [ ] **Step 5: Run the focused test to verify it passes**

  Run: `php tests/preinscriptions-rest-contract-test.php`

  Expected: PASS.

- [ ] **Step 6: Commit**

  ```bash
  git add modules/preinscripciones tests/preinscriptions-rest-contract-test.php
  git commit -m "feat: expose preinscriptions REST catalog"
  ```

### Task 4: Add integration smoke coverage and documentation

**Files:**
- Create: `tests/preinscriptions-module-contract-test.php`
- Modify: `API.md` with the public v1 route and compatibility rules
- Modify: `CHANGELOG.md` with the release-facing integration entry

**Interfaces:**
- The module contract test proves `preinscripciones/init.php` is loaded after the academic and seminar modules and that all required classes are required exactly once.
- The API documentation describes the public response, cache behavior, `configRevision`, invalid form behavior, and the no-rewrite rollout.

- [ ] **Step 1: Write the failing module and documentation checks**

  Assert loader order, required module files, route registration, version `1`, legacy link preservation, and the documented endpoint.

- [ ] **Step 2: Run the focused checks to verify they fail**

  Run: `php tests/preinscriptions-module-contract-test.php`

  Expected: FAIL until loader integration and documentation are present.

- [ ] **Step 3: Add the module to the loader and document the public contract**

  Load the module after `seminarios` and `oferta-academica` so parent entities and their registration methods are available. Document that the new API is additive and that the legacy URL remains authoritative for the coexistence phase.

- [ ] **Step 4: Run the focused checks to verify they pass**

  Run: `php tests/preinscriptions-module-contract-test.php`

  Expected: PASS.

- [ ] **Step 5: Commit**

  ```bash
  git add tests/preinscriptions-module-contract-test.php API.md CHANGELOG.md flacso-uruguay.php modules/preinscripciones/init.php
  git commit -m "docs: document preinscriptions REST rollout"
  ```

### Task 5: Run the complete validation suite and perform a manual contract review

**Files:**
- Verify only; no planned source changes.

- [ ] **Step 1: Run all focused integration tests**

  Run:

  ```bash
  php tests/preinscriptions-config-contract-test.php
  php tests/preinscriptions-admin-contract-test.php
  php tests/preinscriptions-rest-contract-test.php
  php tests/preinscriptions-module-contract-test.php
  ```

  Expected: all commands exit successfully.

- [ ] **Step 2: Run the existing academic and legacy preinscription tests**

  Run:

  ```bash
  php tests/academic-final-contract-test.php
  php tests/academic-final-model-test.php
  php tests/preinscription-links-admin-contract-test.php
  php tests/django-preinscription-integration-test.php
  ```

  Expected: all existing contracts remain green.

- [ ] **Step 3: Run syntax validation over changed PHP files**

  Run `php -l` for every changed PHP file under `modules/preinscripciones/` and the modified loader/admin files.

  Expected: no syntax errors.

- [ ] **Step 4: Review the REST fixture against the approved v1 contract**

  Confirm numeric version, opaque ID, discriminated `kind`, ISO/null dates, revision hash, invalid-form issue codes, no `appUrl`, legacy URL preservation, open/closed inclusion, no personal data, and `Cache-Control: public, max-age=60`.

- [ ] **Step 5: Commit the final validation-only changes if any**

  If validation reveals a required correction, add a focused fix commit. Do not alter legacy links or perform production writes as part of this validation.

## Execution Notes

- Implement in the listed order because the admin and REST layers consume the configuration interfaces from Task 1.
- Keep each task independently reviewable and commit after its focused test passes.
- Do not connect the new app, Google Sheets, or Google Drive in this plugin change.
- Do not deploy or edit production WordPress data automatically; production rollout requires a separate, explicitly authorized operation.
