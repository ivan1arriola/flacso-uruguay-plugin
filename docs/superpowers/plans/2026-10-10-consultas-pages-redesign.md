# Consultas Pages Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert Consultas from one tabbed wp-admin page into six modern, independently routed operational pages while preserving data, actions, permissions and exports.

**Architecture:** Keep `FLACSO_Consultas_Admin` as the application boundary and split its page dispatch from the existing tab renderers. Register six FLACSO submenus, map legacy `tab` links to their equivalent route, and introduce shared layout helpers plus a dedicated stylesheet for the page shell, filters, indicators and tables. Repository calls, AJAX endpoints and CSV export remain unchanged.

**Tech Stack:** PHP 8, WordPress wp-admin APIs, PostgreSQL repositories, CSS, existing PHP contract-test suite.

**Spec:** `docs/superpowers/specs/2026-10-10-consultas-pages-redesign-design.md`

## Global Constraints

- Preserve PostgreSQL schemas, Mautic delivery contracts, AJAX action names and CSV controller behavior.
- Preserve `VIEW_INQUIRIES` visibility and `MANAGE_INQUIRIES` restrictions for exports and mutable actions.
- Keep `flacso-consultas` as the Bandeja slug and redirect legacy `tab` URLs to the matching page.
- Use `main` for all commits and distinguish local checks, CI, deployment and live verification.
- Do not expose inquiry PII in new notices, diagnostics or browser-visible errors.

## Review Focus

- Legacy URL with an unknown or unauthorized `tab` must land on Bandeja without losing authorization boundaries; Task 1 contract test.
- A Gestión web user can open the five view pages but cannot access Exportar CSV or mutable actions; Task 1 and Task 5 contracts.
- Existing detail, retry and follow-up buttons must keep their AJAX nonce, table and record identifiers; Task 3 regression test.
- A filtered Bandeja reset returns to its own route, never to a removed `tab` URL; Task 3 contract test.
- Empty data and repository errors render a generic operational message without SQL/PDO details or PII; Task 4 regression test.

---

### Task 1: Routes, menu and legacy redirect

**Files:**
- Modify: `modules/consultas/includes/class-flacso-consultas-admin.php: FLACSO_Consultas_Admin::init(), register_menu(), render_page()`
- Create: `tests/consultas-pages-routing-contract-test.php`
- Modify: `tests/gestion-web-inquiries-contract-test.php`

**Interfaces:**
- Consumes: `FLACSO_Academic_Assistant::VIEW_INQUIRIES`, `MANAGE_INQUIRIES` and existing `PAGE_SLUG`.
- Produces: `PAGE_BANDEJA`, `PAGE_RESUMEN`, `PAGE_OFERTA_PAIS`, `PAGE_COMPARACION`, `PAGE_CAMPANAS`, `PAGE_EXPORTAR`; `render_page_for_route(string $route): void`; `redirect_legacy_tab(): void`.

- [ ] **Step 1: Write failing routing and capability contracts**

```php
routing_assert(strpos($admin, "'flacso-consultas-resumen'") !== false, 'registra Resumen como página propia');
routing_assert(strpos($admin, "'flacso-consultas-exportar'") !== false, 'registra Exportar como página propia');
routing_assert(strpos($admin, 'redirect_legacy_tab') !== false, 'redirige enlaces heredados por pestaña');
routing_assert(strpos($admin, 'MANAGE_INQUIRIES') !== false, 'exportar conserva autorización de gestión');
```

- [ ] **Step 2: Run the routing contract to verify it fails**

Run: `php tests/consultas-pages-routing-contract-test.php`  
Expected: FAIL because the page slugs and redirect do not exist.

- [ ] **Step 3: Register six `add_submenu_page` routes and dispatch them with `render_page_for_route(string $route): void`**

Keep `flacso-consultas` as Bandeja. Render Exportar only for `can_manage()`. Add an `admin_init` legacy handler that maps `historico`, `oferta`, `oferta-pais`, `comparacion`, `campanas` and `exportar` to their route, keeps compatible filter parameters, and sends users without export access to Bandeja.

- [ ] **Step 4: Update Gestión web contracts for all viewable pages and forbidden export route**

Assert view capability remains sufficient for Bandeja, Resumen, Oferta y país, Comparación and Campañas; assert Exportar requires management capability.

- [ ] **Step 5: Run focused contracts**

Run: `php tests/consultas-pages-routing-contract-test.php && php tests/gestion-web-inquiries-contract-test.php`  
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add modules/consultas/includes/class-flacso-consultas-admin.php tests/consultas-pages-routing-contract-test.php tests/gestion-web-inquiries-contract-test.php
git commit -m "feat: separar consultas en páginas operativas"
```

### Task 2: Shared page shell and modern admin styles

**Files:**
- Create: `modules/consultas/assets/css/consultas-admin.css`
- Modify: `modules/consultas/init.php`
- Modify: `modules/consultas/includes/class-flacso-consultas-admin.php: shared page helpers and asset enqueueing`
- Create: `tests/consultas-pages-layout-contract-test.php`

**Interfaces:**
- Consumes: route constants from Task 1.
- Produces: `enqueue_admin_assets(): void`, `render_page_header(string $title, string $description, array $actions = []): void`, `render_operational_notice(): void`.

- [ ] **Step 1: Write a failing layout contract**

```php
layout_assert(strpos($admin, 'render_page_header') !== false, 'cada ruta usa cabecera compartida');
layout_assert(strpos($admin, 'render_operational_notice') !== false, 'el diagnóstico se muestra como aviso discreto');
layout_assert(is_file($css), 'existe hoja de estilos exclusiva de Consultas');
layout_assert(strpos($css_source, '.flacso-consultas-page') !== false, 'el layout tiene contenedor propio');
```

- [ ] **Step 2: Run the layout contract to verify it fails**

Run: `php tests/consultas-pages-layout-contract-test.php`  
Expected: FAIL because the shared shell and stylesheet do not exist.

- [ ] **Step 3: Add the shared page shell and enqueue `consultas-admin.css` only on Consultas routes**

Use a white heading area, compact metric cards, responsive filter bar, semantic status-chip colors and table spacing. Do not use the former blue hero or tab CSS. Keep WordPress admin styles and accessibility focus states intact.

- [ ] **Step 4: Render transactional diagnostics only when the existing diagnostic method reports an actionable condition**

The notice must remain generic and must not include SQL exception text, email addresses or payload content.

- [ ] **Step 5: Run the layout contract and existing notice tests**

Run: `php tests/consultas-pages-layout-contract-test.php && php tests/consultas-notice-layout-test.php`  
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add modules/consultas/assets/css/consultas-admin.css modules/consultas/init.php modules/consultas/includes/class-flacso-consultas-admin.php tests/consultas-pages-layout-contract-test.php
git commit -m "feat: modernizar estructura visual de consultas"
```

### Task 3: Bandeja as an independent operational page

**Files:**
- Modify: `modules/consultas/includes/class-flacso-consultas-admin.php: render_tab_historico(), filter/reset URLs, page dispatcher`
- Modify: `tests/consultas-inbox-view-contract-test.php`
- Modify: `tests/consultas-inbox-ux-contract-test.php`

**Interfaces:**
- Consumes: shared header and route helpers from Tasks 1–2; existing AJAX endpoints and `FLACSO_Inquiry_Analytics_Repository` methods.
- Produces: `render_bandeja_page(string $table, string $from, string $to, string $nonce): void`.

- [ ] **Step 1: Add failing assertions for the dedicated Bandeja route and reset link**

```php
inbox_assert(strpos($admin, 'render_bandeja_page') !== false, 'Bandeja tiene renderizador propio');
inbox_assert(strpos($admin, "page=' . self::PAGE_BANDEJA") !== false, 'limpiar filtros conserva la ruta Bandeja');
inbox_assert(strpos($admin, 'tab=historico') === false, 'Bandeja nueva no emite pestañas heredadas');
```

- [ ] **Step 2: Run Bandeja contracts to verify they fail**

Run: `php tests/consultas-inbox-view-contract-test.php && php tests/consultas-inbox-ux-contract-test.php`  
Expected: FAIL on missing dedicated renderer or legacy URL assertions.

- [ ] **Step 3: Move the historical renderer behind `render_bandeja_page` and apply the shared shell**

Keep every existing filter, search, pagination control, detail modal, retry/follow-up action and nonce. Place search first in the filter bar, show active-filter reset only when non-default filters are present, and replace internal `tab=historico` targets with the Bandeja slug.

- [ ] **Step 4: Run Bandeja, inquiry action and permission regressions**

Run: `php tests/consultas-inbox-view-contract-test.php && php tests/consultas-inbox-ux-contract-test.php && php tests/inquiry-handler-wiring-test.php && php tests/gestion-web-inquiries-contract-test.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add modules/consultas/includes/class-flacso-consultas-admin.php tests/consultas-inbox-view-contract-test.php tests/consultas-inbox-ux-contract-test.php
git commit -m "feat: rediseñar bandeja de consultas"
```

### Task 4: Independent analytics pages

**Files:**
- Modify: `modules/consultas/includes/class-flacso-consultas-admin.php: render_tab_oferta(), render_tab_oferta_pais(), render_tab_comparacion(), render_tab_campanas(), render_date_range_form()`
- Modify: `tests/consultas-comparison-ux-contract-test.php`
- Create: `tests/consultas-analytics-pages-contract-test.php`

**Interfaces:**
- Consumes: route constants and shared page header from Tasks 1–2; existing analytics repository queries.
- Produces: `render_resumen_page`, `render_oferta_pais_page`, `render_comparacion_page`, `render_campanas_page`, each with existing table/range parameters.

- [ ] **Step 1: Write failing contracts for the four analytics routes**

```php
analytics_assert(strpos($admin, 'render_resumen_page') !== false, 'Resumen tiene una página propia');
analytics_assert(strpos($admin, 'render_oferta_pais_page') !== false, 'Oferta y país tiene una página propia');
analytics_assert(strpos($admin, 'render_comparacion_page') !== false, 'Comparación tiene una página propia');
analytics_assert(strpos($admin, 'render_campanas_page') !== false, 'Campañas tiene una página propia');
```

- [ ] **Step 2: Run analytics contracts to verify they fail**

Run: `php tests/consultas-analytics-pages-contract-test.php && php tests/consultas-comparison-ux-contract-test.php`  
Expected: FAIL because renderers still depend on `tab` navigation.

- [ ] **Step 3: Expose each analytics renderer through its own route and shared header**

Keep only filters meaningful to that page. Preserve existing date and table parameters, the prior-31-days action, current/base totals, variation values, country selector and campaign actions. Update every form target and reset target to its own route.

- [ ] **Step 4: Add empty/error handling to page wrappers**

Catch repository failures at the render boundary and show a generic operational message; retain detailed errors only in server logs. Display the existing empty-result copy when the query succeeds with no data.

- [ ] **Step 5: Run focused analytics and error contracts**

Run: `php tests/consultas-analytics-pages-contract-test.php && php tests/consultas-comparison-ux-contract-test.php && php tests/inquiry-repositories-test.php`  
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add modules/consultas/includes/class-flacso-consultas-admin.php tests/consultas-analytics-pages-contract-test.php tests/consultas-comparison-ux-contract-test.php
git commit -m "feat: separar analítica de consultas por tarea"
```

### Task 5: Independent export page and end-to-end validation

**Files:**
- Modify: `modules/consultas/includes/class-flacso-consultas-admin.php: render_tab_exportar(), export links and route dispatch`
- Modify: `tests/consultas-inbox-view-contract-test.php`
- Create: `tests/consultas-export-page-contract-test.php`

**Interfaces:**
- Consumes: route constants from Task 1 and existing `handle_export_csv()` controller.
- Produces: `render_exportar_page(string $table, string $from, string $to): void`.

- [ ] **Step 1: Write failing export-page and authorization contracts**

```php
export_assert(strpos($admin, 'render_exportar_page') !== false, 'Exportar tiene página propia');
export_assert(strpos($admin, 'flacso_consultas_export_csv') !== false, 'mantiene controlador CSV existente');
export_assert(strpos($admin, 'can_manage()') !== false, 'la página respeta autorización de gestión');
```

- [ ] **Step 2: Run the export contract to verify it fails**

Run: `php tests/consultas-export-page-contract-test.php`  
Expected: FAIL because there is no renderizador o ruta exclusiva.

- [ ] **Step 3: Render Exportar on its own restricted route**

Reuse the existing form fields, nonce, raw/dedup mode and item selection. Update every “Exportar CSV” shortcut to point to `PAGE_EXPORTAR` with compatible current filters. Do not alter `handle_export_csv()` response headers, filename or row contents.

- [ ] **Step 4: Run full local verification**

Run: `for test in tests/*-test.php; do php "$test" || exit 1; done`  
Expected: all tests pass; document pre-existing warning output if any remains.

- [ ] **Step 5: Run authenticated visual verification after deployment**

Check all six menu routes, legacy `tab` redirect, an active Bandeja filter reset, comparison action, CSV authorization boundary and responsive table scrolling. Confirm no blue hero or horizontal tab bar remains.

- [ ] **Step 6: Commit and publish**

```bash
git add modules/consultas/includes/class-flacso-consultas-admin.php tests/consultas-export-page-contract-test.php tests/consultas-inbox-view-contract-test.php
git commit -m "feat: finalizar rediseño de consultas por páginas"
git push origin main
```

## Final verification

- [ ] Verify clean `main` with `git status --short --branch`.
- [ ] Wait for Release Auto Update, Repository Guard and Deploy WordPress Plugin for the final SHA.
- [ ] Confirm deployment activation and WordPress smoke test in the deploy workflow.
- [ ] Report local tests, publication, CI, deploy and authenticated visual verification as separate evidence.
