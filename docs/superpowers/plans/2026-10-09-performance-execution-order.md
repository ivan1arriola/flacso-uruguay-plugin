# Performance and Execution Order Improvements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reduce avoidable database queries, external HTTP calls, memory use, and queue latency while preserving the plugin's public contracts, delivery states, historical data, and visible admin behavior.

**Architecture:** Keep WordPress as the orchestration layer, PostgreSQL as the source for inquiry analytics and delivery state, and Mautic as the communication system. Move cheap local validation before remote work, add short-lived request or transient caches only where stale data is safe, and replace N+1 reads with bounded set-based queries. Each subsystem remains independently testable and deployable.

**Tech Stack:** PHP 7.4+, WordPress APIs, PDO PostgreSQL/SQLite test doubles, WP-Cron, Mautic REST API, PHPUnit-style autonomous PHP contract tests.

**Spec:** Static execution-order audit from 2026-10-09; no separate product specification exists.

## Global Constraints

- Preserve existing public REST response shapes, delivery state transitions, Mautic template IDs, and Spanish-facing copy.
- Do not run migrations, queue cleanup, retries, sends, or production writes automatically during implementation.
- Do not cache credentials, personal data, raw inquiry payloads, or failed external responses beyond the current request.
- Keep PHP 7.4 compatibility and SQLite-compatible test coverage where the existing tests use SQLite.
- Validate the exact triggering SHA, CI, deployment workflow, smoke test, and public health separately.
- Default queue work must remain bounded by the existing lock and database timeouts.

## Review Focus

- A malformed or incomplete delivery snapshot must not trigger a Mautic request; test this in the delivery service.
- A temporary Mautic or PostgreSQL outage must leave a retryable, observable state and release the worker lock; test both paths.
- A cached valid contract must be invalidated or bypassed when an administrator explicitly requests diagnostics; test the force path.
- Grouped analytics must return the same children, ordering, pagination, and totals after removing the N+1 queries; test raw and grouped modes.
- A catalog cache must invalidate after relevant cohort, edition, or offer metadata changes; test REST output before and after invalidation.

### Task 1: Reorder and cache transactional delivery validation

**Files:**
- Create: `modules/consultas/services/class-flacso-mautic-delivery-contract-cache.php`
- Modify: `modules/consultas/services/class-flacso-inquiry-delivery-service.php:58-149`
- Modify: `modules/consultas/services/class-flacso-mautic-contract-validator.php:20-150`
- Modify: `modules/consultas/init.php`
- Create: `tests/inquiry-delivery-order-test.php`
- Modify: `tests/inquiry-delivery-service-test.php`

**Interfaces:**
- Produces `FLACSO_Mautic_Delivery_Contract_Cache::get(int $template_id, bool $force = false): ?array`.
- Produces `FLACSO_Mautic_Contract_Validator::validate_delivery_template(int $template_id, bool $force = false): array`.
- Keeps `validate()` as the full diagnostic validation used by admin screens.

- [ ] Write tests proving invalid snapshot/payload and missing local delivery identity return before any Mautic transport call.
- [ ] Write tests proving delivery validation reads only the selected template, while full diagnostics still validate all required templates and fields.
- [ ] Write tests proving successful delivery-contract results are cached for the request and short-lived transient, while failed remote reads are not cached.
- [ ] Run the focused tests and verify they fail for the current ordering.
- [ ] Implement local JSON/token/identity validation before remote validation, then use the delivery-specific validator and cache.
- [ ] Add explicit force invalidation for the admin diagnostic path; never cache secrets or response payloads beyond the minimal status/requirements result.
- [ ] Run focused delivery, Mautic, and PHP lint checks.
- [ ] Commit `perf(consultas): reorder delivery validation and cache contract checks`.

### Task 2: Increase bounded queue throughput without weakening safety

**Files:**
- Modify: `modules/consultas/services/class-flacso-inquiry-delivery-worker.php:19-110`
- Modify: `includes/database/repositories/class-flacso-inquiry-delivery-repository.php:184-240`
- Modify: `tests/inquiry-delivery-worker-test.php`
- Modify: `tests/inquiry-delivery-worker-db-outage-test.php`
- Create: `tests/inquiry-delivery-worker-batch-test.php`

**Interfaces:**
- `FLACSO_Inquiry_Delivery_Worker::MAX_BATCH_SIZE` defaults to `10`, with the effective request still clamped to `1..10`.
- `run_cron()` continues to call `run(10)` and returns the existing result shape.

- [ ] Add a test with several claimed deliveries proving one cron execution processes up to ten items and releases the lock.
- [ ] Add a test proving a requested limit above ten is clamped and a limit below one becomes one.
- [ ] Preserve the PostgreSQL outage test and verify no claimed delivery is silently lost.
- [ ] Run the worker tests and confirm the current one-item limit fails the new batch assertion.
- [ ] Raise the bounded batch limit and make any claim/update statement set-based where the existing transaction and lock semantics permit it.
- [ ] Run all delivery repository/service/worker tests and lint.
- [ ] Commit `perf(consultas): process bounded delivery batches`.

### Task 3: Remove analytics N+1 queries and repeated full summaries

**Files:**
- Modify: `includes/database/repositories/class-flacso-inquiry-analytics-repository.php:675-950`
- Modify: `modules/consultas/includes/class-flacso-consultas-admin.php:839-865`
- Modify: `modules/consultas/includes/class-flacso-consultas-admin.php:1382-1386`
- Modify: `modules/consultas/includes/class-flacso-consultas-admin.php:1472-1482`
- Create: `tests/inquiry-analytics-query-plan-test.php`
- Modify: existing analytics/consultas tests as needed

**Interfaces:**
- Preserve `get_paginated_inquiries(array $filters): array` and its `items`, `metrics`, and `pageInfo` keys.
- Preserve `get_analytics_summary(string $desde, string $hasta, string $offer_filter, string $table): array` and all output keys.

- [ ] Capture baseline query counts for grouped history, raw history, country view, and period comparison using a traceable test PDO or SQL logger.
- [ ] Add behavioral tests for grouped children, child ordering, page totals, empty pages, and raw mode.
- [ ] Replace one-child-query-per-group with one bounded query for all groups on the requested page, assembling the same response in PHP.
- [ ] Add request-local memoization so the same summary is reused within one admin request; comparison must still calculate two different date ranges.
- [ ] Move high-volume deduplication and aggregate counts toward PostgreSQL SQL aggregation in a separate, driver-safe method; retain the existing PHP fallback for SQLite tests.
- [ ] Add or verify indexes for the actual filter/order columns only after checking the deployed schema and query plans; document any required migration separately.
- [ ] Verify query counts decrease and response fixtures remain byte/shape compatible where applicable.
- [ ] Commit `perf(consultas): remove analytics N+1 reads`.

### Task 4: Cache and invalidate the preinscriptions catalog

**Files:**
- Create: `modules/preinscripciones/includes/class-preinscriptions-cache.php`
- Modify: `modules/preinscripciones/includes/class-preinscriptions-rest.php:31-42`
- Modify: `modules/preinscripciones/init.php`
- Modify: relevant cohort, edition, and offer save/meta hooks
- Create: `tests/preinscriptions-cache-contract-test.php`

**Interfaces:**
- `FLACSO_Preinscriptions_Cache::get_catalog(): array` returns the same `{version, targets}` payload currently assembled by the REST callback.
- `FLACSO_Preinscriptions_Cache::invalidate_for_post(int $post_id, ?string $meta_key = null): void` invalidates only relevant catalog changes.

- [ ] Add tests proving the first REST call builds the catalog, the second call reuses it, and the payload shape is unchanged.
- [ ] Add tests proving saves and relevant metadata changes invalidate the cache for `cohorte`, `edicion`, and `oferta-academica`.
- [ ] Run the current preinscription REST/module/configuration tests and confirm the new cache test fails before implementation.
- [ ] Add a 60-second server-side cache using the existing object cache when available and a transient fallback; cache only the catalog payload, never credentials or inquiry data.
- [ ] Register narrowly scoped invalidation hooks and ensure activation/deactivation does not perform a catalog rebuild.
- [ ] Verify the REST endpoint still returns `Cache-Control: public, max-age=60` and recomputes after invalidation.
- [ ] Commit `perf(preinscripciones): cache catalog serialization`.

### Task 5: Reduce repeated scans in the FLACSO admin panel

**Files:**
- Modify: `includes/core/class-flacso-admin-panel.php:142-155`
- Modify: `includes/core/class-flacso-admin-panel.php:364-475`
- Create: `tests/admin-panel-performance-contract-test.php`

**Interfaces:**
- Keep `counts()`, `open_registration_count()`, `integrity_alerts()`, and `upcoming_items()` output shapes unchanged.

- [ ] Add tests for assistant and administrator views proving both retain their current metrics and alerts.
- [ ] Add request-local memoization for repeated counts and parent titles during one panel render.
- [ ] Replace full ID loading where only a count is needed with bounded count queries or `WP_Query` totals, preserving the existing status and metadata semantics.
- [ ] Keep expensive integrity checks out of the assistant view and cache them briefly for the administrator view.
- [ ] Verify the panel renders with unavailable or empty post types without warnings.
- [ ] Run focused admin contract tests and PHP lint.
- [ ] Commit `perf(admin): reduce panel catalog scans`.

### Task 6: End-to-end measurement, rollout, and production verification

**Files:**
- Create: `docs/operations/performance-baseline.md`
- Modify: `README.md` only if operational commands or cache behavior need documentation.

- [ ] Add a non-production benchmark procedure that records WordPress query count/time, PostgreSQL query count/time, Mautic HTTP calls, queue throughput, and REST serialization time.
- [ ] Run the complete PHP contract suite, encoding check, PHP lint, and focused regression tests serially.
- [ ] Review the combined diff for contract changes, PII exposure, cache invalidation gaps, and unrelated edits.
- [ ] Publish only after the implementation branch is reviewed and the exact release SHA is known.
- [ ] Verify GitHub Actions, artifact creation, deploy step, WordPress smoke test, public HTTP health, and queue/diagnostic behavior separately.
- [ ] Commit `docs(operations): document performance baseline` if documentation changed.
