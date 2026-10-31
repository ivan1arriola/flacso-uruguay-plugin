# Organización del menú de wp-admin — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reorganize the WordPress admin navigation into the six approved FLACSO work groups while preserving each destination, callback, and existing capability check.

**Architecture:** Reuse the existing FLACSO root menu and dashboard. Add a focused organizer in `includes/core/` that classifies menu entries after plugin menu registration and exposes them under six non-clickable section headings; keep the existing page slugs and the dashboard rendering. The organizer must leave direct-route behavior, existing page callbacks, and permission checks intact.

**Tech Stack:** WordPress admin menu APIs and PHP 7.4+, existing PHP contract-test scripts, `scripts/agent_check.py`.

**Spec:** `docs/superpowers/specs/2026-10-31-wp-admin-menu-design.md`

## Global Constraints

- Preserve all internal destination URLs from the menu inventory.
- Preserve existing WordPress and plugin capability checks; do not broaden access.
- Reuse the current FLACSO panel and admin-bar links; do not create a duplicate panel.
- Keep the six group names and Spanish labels from the approved design.
- Do not change content, stored data, plugin settings, roles, or business logic.
- Maintain compatibility with PHP 7.4 and WordPress conventions in this repository.
- Do not push to `main`: the repository's deployment workflow deploys pushes to production.

## Review Focus

- A menu destination registered by a plugin is still reachable and invokes its original callback after reparenting; cover with a menu-registration integration test or WordPress admin smoke check.
- A user without a page capability does not gain a visible or reachable menu entry; cover with capability-filtered organizer tests.
- Assistant accounts retain their restricted menu set; cover with assistant-role contract coverage.
- Plugin menu items registered late or added after the organizer's hook are not silently lost; cover with a late-registration fallback test.
- Current admin-bar links and the FLACSO dashboard remain available and point to the same slugs; cover with the existing admin-panel contract test.

---

### Task 1: Define and test the six-group menu manifest

**Files:**
- Create: `includes/core/class-flacso-admin-menu-organizer.php`
- Create: `tests/admin-menu-organizer-contract-test.php`
- Modify: `tests/admin-panel-contract-test.php`

**Interfaces:**
- Produces: `FLACSO_Admin_Menu_Organizer::group_manifest(): array`, mapping known menu slugs to one of `inicio`, `formacion`, `contenidos`, `inscripciones`, `diseno`, or `sistema`.
- Produces: `FLACSO_Admin_Menu_Organizer::group_for_slug(string $slug): string`, returning a known group or the `sistema` fallback for an otherwise unmapped internal admin route.
- The manifest is keyed by menu slug, not by translated label, so localization does not affect route matching.

- [ ] **Step 1: Add failing contract checks** for the six group IDs and representative routes from each category, including core posts/pages, custom post types, FLACSO settings, plugin settings, and WordPress tools.
- [ ] **Step 2: Add checks** that all manifest slugs are unique, map to an allowed group, and unknown internal routes use the `sistema` fallback.
- [ ] **Step 3: Implement the manifest and `group_for_slug()`** in the new organizer class using the current admin-menu inventory.
- [ ] **Step 4: Update the panel contract** to expect six groups while retaining existing checks for the canonical panel slug and admin-bar behavior.
- [ ] **Step 5: Run the focused contract scripts** and require a passing result before proceeding.

### Task 2: Reparent registered admin menu entries without changing access

**Files:**
- Modify: `includes/core/class-flacso-admin-menu-organizer.php`
- Modify: `includes/core/class-flacso-admin-panel.php`
- Modify: `flacso-uruguay.php`
- Modify: `tests/admin-menu-organizer-contract-test.php`

**Interfaces:**
- Consumes: `group_manifest()` and `group_for_slug()` from Task 1.
- Produces: `FLACSO_Admin_Menu_Organizer::init(): void` and `FLACSO_Admin_Menu_Organizer::organize_menu(): void`.
- `organize_menu()` runs after existing module and plugin registrations, collects the current menu entries, and preserves their original slugs, labels, capability values, callbacks, and children.

- [ ] **Step 1: Add failing checks** for preservation of original slugs, capabilities, submenu children, and the FLACSO dashboard after organization.
- [ ] **Step 2: Implement the organizer** to place internal menu entries under the approved group headings and keep external URLs outside the internal-route manifest.
- [ ] **Step 3: Preserve visibility rules** by moving only entries already exposed to the current user; do not synthesize or weaken capability checks.
- [ ] **Step 4: Register the organizer** from the plugin bootstrap after loading the existing panel class.
- [ ] **Step 5: Refactor the panel's current four-group sorting hook** so the organizer is the single owner of submenu grouping, while the panel retains its rendering and admin-bar responsibilities.
- [ ] **Step 6: Run the focused contract tests** and confirm direct slugs and current dashboard links remain unchanged.

### Task 3: Validate representative user and plugin routes

**Files:**
- Modify: `tests/admin-menu-organizer-contract-test.php`
- Modify: `tests/admin-panel-contract-test.php`

**Interfaces:**
- Consumes: `FLACSO_Admin_Menu_Organizer::organize_menu()` and the existing `FLACSO_Admin_Panel` routes.

- [ ] **Step 1: Cover the assistant account path** so restricted academic menus remain restricted and visible in their approved groups.
- [ ] **Step 2: Cover core and plugin entries** including custom post type lists, settings pages, Site Kit, Rank Math, Kadence, Smush, and Scheduled Actions.
- [ ] **Step 3: Perform a WordPress admin smoke check** as an administrator and an academic assistant: open representative list, edit, settings, and report pages from the new menu; confirm each route loads and the correct parent group is highlighted.
- [ ] **Step 4: Run `python scripts/agent_check.py --diff`** and then `python scripts/agent_check.py`; both must report `RESULTADO: [EXITO]`.
- [ ] **Step 5: Review `git diff` and `git status`**. Keep the work on a non-production branch; do not push to `main`.

---

## Self-review

- **Spec coverage:** Six groups, reused FLACSO panel, preserved routes and permissions, and unchanged admin-bar shortcuts are covered by Tasks 1–3.
- **Step scan:** Each step specifies one test, implementation change, or verification result.
- **Type consistency:** The manifest, slug resolver, initializer, and organizer interfaces are named consistently.
- **Review focus:** Each listed risk is tied to a contract check or the admin smoke check.
- **Proportion:** Three tasks separate the stable taxonomy, menu reparenting, and user-facing verification.
