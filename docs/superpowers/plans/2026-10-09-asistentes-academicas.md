# Alcance académico para asistentes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que cada asistente gestione perfiles de equipo, consulte toda la bandeja de consultas y trabaje sólo sobre las ofertas y seminarios que tenga asignados.

**Architecture:** Centralizar las asignaciones y la autorización por objeto en `FLACSO_Academic_Assistant`, usando metadatos de usuario y el filtro `map_meta_cap`. Filtrar los listados administrativos y las relaciones de cohortes y ediciones con esa misma fuente de verdad. Separar la lectura de Consultas de sus acciones que alteran datos o disparan comunicaciones.

**Tech Stack:** PHP 7.4+, WordPress roles/capabilities, user meta, CPTs existentes, pruebas PHP autónomas.

**Spec:** `docs/superpowers/specs/2026-10-09-asistentes-academicas.md`

## Global Constraints

- Las asistentes editan todos los perfiles `docente` y no pueden crear ni eliminar perfiles.
- Consultas es de lectura global: incluye bandeja, detalle y gráficas; no incluye exportación ni acciones sobre comunicaciones.
- El alcance de una cohorte deriva de `oferta_academica_id`; el de una edición deriva de `seminario_id`.
- La autorización debe funcionar aunque una persona conozca o modifique una URL del administrador.
- Administradores y editores mantienen el acceso actual.
- Ninguna asistente publica, elimina, clona o administra usuarios, ajustes o integraciones.
- Los cambios se validan localmente antes de publicarse en `main`.

## Review Focus

- Una asistente sin asignaciones no puede listar ni abrir una oferta, cohorte, seminario o edición.
- Una cohorte o edición no puede reasignarse mediante una petición manipulada a una entidad madre no autorizada.
- Los filtros del listado conservan las condiciones existentes al añadir la restricción de alcance.
- Las acciones AJAX de Consultas verifican la capacidad adecuada y no exponen mutaciones a perfiles de sólo lectura.
- Administradores y editores no quedan limitados por las asignaciones de asistentes.

### Task 1: Modelo de asignaciones y autorización por objeto

**Files:**
- Modify: `includes/core/class-flacso-academic-assistant.php`
- Create: `tests/academic-assistant-scope-contract-test.php`

**Interfaces:**
- Produces: `assigned_offer_ids(int $user_id): array`, `assigned_seminar_ids(int $user_id): array`, `can_manage_academic_post(int $post_id, ?int $user_id = null): bool`, y el filtro de capabilities para objetos académicos.
- Consumes: `FLACSO_Cohorte::META_PARENT_ID` y `FLACSO_Edicion::META_PARENT_ID` cuando las clases estén disponibles; las claves literales equivalentes durante el bootstrap temprano.

- [ ] **Step 1: Escribir la prueba fallida de asignaciones y alcance**

Comprobar que el código define dos claves de user meta, sanea identificadores únicos, permite perfiles `docente` a toda asistente y deriva el permiso de cohortes y ediciones de su entidad madre.

- [ ] **Step 2: Ejecutar la prueba para confirmar que falla**

Run: `php tests/academic-assistant-scope-contract-test.php`
Expected: FAIL porque las asignaciones y la autorización por objeto no existen.

- [ ] **Step 3: Implementar la fuente de verdad de alcance**

Agregar las claves de user meta, lectores saneados y el filtro `map_meta_cap`. Para asistentes, devolver `do_not_allow` al editar una oferta o seminario fuera de sus listas y al editar una cohorte o edición cuya madre no esté autorizada. No alterar la resolución para administradores, editores ni `docente`.

- [ ] **Step 4: Ejecutar la prueba de alcance**

Run: `php tests/academic-assistant-scope-contract-test.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/core/class-flacso-academic-assistant.php tests/academic-assistant-scope-contract-test.php
git commit -m "feat: limitar catálogo por asistente académica"
```

### Task 2: Administración segura de asignaciones

**Files:**
- Modify: `includes/core/class-flacso-academic-assistant.php`
- Modify: `tests/academic-assistant-scope-contract-test.php`

**Interfaces:**
- Consumes: los lectores y claves de asignación de Task 1.
- Produces: campos de perfil para administradores y guardado protegido por capability y nonce.

- [ ] **Step 1: Extender la prueba con los controles de perfil**

Exigir campos para ofertas y seminarios sólo al editar una usuaria asistente, nonce, verificación `manage_options` y saneamiento a IDs de CPT existentes.

- [ ] **Step 2: Ejecutar la prueba para confirmar que falla**

Run: `php tests/academic-assistant-scope-contract-test.php`
Expected: FAIL porque no existe una interfaz de asignación administrativa.

- [ ] **Step 3: Implementar los campos y el guardado**

Registrar los hooks de perfil. Mostrar multiselección de ofertas y seminarios a administradores; persistir sólo los IDs válidos y borrar el metadato si queda vacío. No exponer el control a la asistente que edita su propio perfil.

- [ ] **Step 4: Ejecutar la prueba de alcance**

Run: `php tests/academic-assistant-scope-contract-test.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/core/class-flacso-academic-assistant.php tests/academic-assistant-scope-contract-test.php
git commit -m "feat: asignar catálogo a asistentes académicas"
```

### Task 3: Restringir listados y entidades dependientes

**Files:**
- Modify: `includes/core/class-flacso-academic-assistant.php`
- Modify: `modules/oferta-academica/includes/class-cohorte.php`
- Modify: `modules/seminarios/includes/class-edicion.php`
- Modify: `tests/academic-assistant-scope-contract-test.php`

**Interfaces:**
- Consumes: `assigned_offer_ids`, `assigned_seminar_ids` y `can_manage_academic_post` de Task 1.
- Produces: listados filtrados y creación de cohortes/ediciones limitada a la entidad madre autorizada.

- [ ] **Step 1: Extender la prueba con filtrado y validación de madre**

Exigir un hook de consulta para los cuatro CPT y que los guardados de cohorte y edición rechacen una madre no autorizada antes de escribir su meta.

- [ ] **Step 2: Ejecutar la prueba para confirmar que falla**

Run: `php tests/academic-assistant-scope-contract-test.php`
Expected: FAIL porque los listados y selectores siguen exponiendo el catálogo completo.

- [ ] **Step 3: Implementar el filtrado y la protección de relaciones**

Aplicar `post__in` a ofertas y seminarios, y `meta_query` por madre a cohortes y ediciones en consultas principales del administrador. En los selectores de madre, mostrar sólo entidades asignadas. Validar el valor solicitado al crear o guardar, bloquear creación sin madre autorizada y retirar el permiso heredado de crear seminarios del rol existente.

- [ ] **Step 4: Ejecutar la prueba de alcance y los contratos académicos relevantes**

Run: `php tests/academic-assistant-scope-contract-test.php && php tests/academic-final-contract-test.php && php tests/cohorte-admin-start-date-contract-test.php && php tests/edicion-admin-fields-contract-test.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/core/class-flacso-academic-assistant.php modules/oferta-academica/includes/class-cohorte.php modules/seminarios/includes/class-edicion.php tests/academic-assistant-scope-contract-test.php
git commit -m "feat: restringir cohortes y ediciones por asignación"
```

### Task 4: Separar la lectura de Consultas de sus acciones operativas

**Files:**
- Modify: `includes/core/class-flacso-academic-assistant.php`
- Modify: `modules/consultas/includes/class-flacso-consultas-admin.php`
- Create: `tests/academic-assistant-inquiries-contract-test.php`

**Interfaces:**
- Produces: `flacso_view_inquiries` para la pantalla, detalle y gráficas; `flacso_manage_inquiries` reservado para exportación y mutaciones.
- Consumes: el rol y la sincronización de capabilities de `FLACSO_Academic_Assistant`.

- [ ] **Step 1: Escribir la prueba fallida de separación de Consultas**

Comprobar que el menú, renderizado y detalle usan la capability de lectura, mientras exportación, reintentos, seguimientos y campañas requieren la capability de gestión.

- [ ] **Step 2: Ejecutar la prueba para confirmar que falla**

Run: `php tests/academic-assistant-inquiries-contract-test.php`
Expected: FAIL porque toda la pantalla depende de `manage_options`.

- [ ] **Step 3: Implementar capacidades separadas y adaptar la interfaz**

Conceder sólo lectura de Consultas a asistentes. Reemplazar las verificaciones de la página y detalle por esa capability; conservar las verificaciones estrictas para exportar y toda mutación. Ocultar para asistentes los botones y pestañas de exportación, reintentos, seguimiento y gestión de campañas.

- [ ] **Step 4: Ejecutar las pruebas de Consultas**

Run: `php tests/academic-assistant-inquiries-contract-test.php && php tests/inquiry-analytics-date-filter-test.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/core/class-flacso-academic-assistant.php modules/consultas/includes/class-flacso-consultas-admin.php tests/academic-assistant-inquiries-contract-test.php
git commit -m "feat: habilitar consultas de solo lectura a asistentes"
```

### Task 5: Convertir el inicio de asistentes en su tablero de trabajo

**Files:**
- Modify: `includes/core/class-flacso-admin-panel.php`
- Modify: `includes/assets/flacso-academic-assistant.css`
- Modify: `tests/academic-assistant-interface-contract-test.php`
- Modify: `tests/admin-panel-query-plan-test.php`

**Interfaces:**
- Consumes: los lectores de asignación de Task 1 y el acceso a Consultas de Task 4.
- Produces: métricas, recorridos y próximos comienzos limitados al catálogo asignado; accesos globales a Personas/Equipo y Consultas.

- [ ] **Step 1: Escribir la prueba fallida de la portada acotada**

Exigir métodos de conteo y próximos comienzos que reciban las asignaciones de la asistente, un acceso a Consultas y los controles de accesibilidad y responsive existentes.

- [ ] **Step 2: Ejecutar la prueba para confirmar que falla**

Run: `php tests/academic-assistant-interface-contract-test.php && php tests/admin-panel-query-plan-test.php`
Expected: FAIL porque la portada cuenta y muestra datos de todo el catálogo.

- [ ] **Step 3: Implementar el tablero de trabajo**

Calcular las métricas y próximos comienzos por IDs asignados, cambiar los textos a "Mis ofertas" y "Mis seminarios" y sumar el acceso de sólo lectura a Consultas. Mantener Personas/Equipo como acceso global. Ajustar los estilos de avisos dentro del hero para conservar contraste legible sin afectar avisos de WordPress fuera del panel.

- [ ] **Step 4: Ejecutar las pruebas de interfaz**

Run: `php tests/academic-assistant-interface-contract-test.php && php tests/admin-panel-query-plan-test.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/core/class-flacso-admin-panel.php includes/assets/flacso-academic-assistant.css tests/academic-assistant-interface-contract-test.php tests/admin-panel-query-plan-test.php
git commit -m "feat: enfocar panel de asistentes en su catálogo"
```

### Task 6: Verificación integrada y publicación

**Files:**
- Modify: `AGENTS.md` (sólo si hace falta documentar un nuevo contrato operativo)

- [ ] **Step 1: Ejecutar la suite PHP completa**

Run: `for test in tests/*-test.php; do php "$test" || exit 1; done`
Expected: todos los contratos existentes y nuevos finalizan con código 0.

- [ ] **Step 2: Verificar manualmente con tres usuarios**

Comprobar una asistente sin asignaciones, una asistente con una oferta y un seminario, y un administrador. Validar listados, URL directa, creación de cohorte/edición, perfil docente, Consultas y ausencia de exportación/acciones operativas para asistentes.

- [ ] **Step 3: Publicar el resultado terminado**

Run: `git push origin main`
Expected: la rama `main` remota contiene todos los commits validados.

- [ ] **Step 4: Verificar CI, despliegue y navegador**

Confirmar el SHA desplegado, salud de WordPress y las tres sesiones de usuario antes de declarar el cambio disponible en producción.
