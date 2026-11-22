# División de tareas para integración continua

**Alcance vigente (2026-11-22, B-0036): recortado a WP-S1, H-A11 (parte de WP-02) y la verificación del webhook. Todo lo demás de este documento está en espera.**

Estado: propuesta aprobada el 2026-11-21 y **corregida tras la revisión T3 (B-0019): aprobado con cambios**. Ningún paquete T2 puede empezar hasta cumplir los prerrequisitos bloqueantes.

## Prerrequisitos bloqueantes (antes de cualquier paquete T2)

Los marcados "decisión del dueño" requieren una persona con acceso a GitHub, al servidor o a `AGENTS.md`.

1. **Deploy manual desde cualquier rama** (decisión del dueño). `deploy-plugin.yml` y `deploy-theme.yml` tienen `workflow_dispatch` sin restricción de rama y despliegan `github.sha` de la rama elegida. La protección de rama no lo impide. **Decidido: environment `production` con revisores y "deployment branches: main"; lo configura el dueño en GitHub (pendiente).**
2. **Secretos de repositorio al alcance de cualquier rama** (decisión del dueño). `EC2_SSH_KEY`, `EC2_HOST` y `EC2_USER` son de repositorio: una rama con un workflow `on: push` podría usarlos. **Decidido: mover a un environment protegido y poner CODEOWNERS sobre `.github/` (pendiente de configurar por el dueño).**
3. **`AGENTS.md`** (decisión del dueño). Hoy ordena `git push origin HEAD:main` y el uso de `flacso_api.py` (escribe en producción), y otras herramientas lo leen antes que cualquier preámbulo. Corregirlo para que apunte a esta guía. El tema no tiene `AGENTS.md`: crear uno, porque el `LEEME.md` del tema no lo lee ninguna herramienta automáticamente. **Hecho (B-0020): ambos `AGENTS.md` editados o creados, sin commit; falta la revisión humana.**
4. **Checkouts en `main`.** Plugin y tema están en `main` con `docs/auditoria-ui-ux/` sin commitear. Todo T2 trabaja en un worktree o clon aparte, en una rama `ui/WP-XX-slug`; nadie commitea en `main`.
5. **Prueba de H-A11 sin producción.** `fc_is_spam_content()` es una función pura: se prueba con un test PHP CLI en `tests/`. No existe staging (el "remote staging" del workflow es un `/tmp` en el servidor de producción), así que ninguna prueba manual se hace en producción.
6. **Modelo de bitácora** (decisión del dueño). Propuesta: una entrada por archivo (`bitacora/B-NNNN-slug.md`, con ID reservado por quien coordina) y `BITACORA.md` como índice que mantiene T3 o una persona. Así los PR de paquetes no tocan archivos compartidos. **Decidido y vigente desde B-0020.**
7. **Merges de solo documentación.** Mergear `docs/` dispara igual el deploy completo, vacía la caché de página (`deploy-plugin.yml:341-347`) y publica una release (`release-auto-update.yml`). Agregar `paths-ignore: docs/**` o asumirlo.
8. **Orden tema/plugin.** `deploy-theme.yml` clona `main` del plugin para correr sus contratos y no tiene rollback (el plugin sí). "Tema primero" tiene riesgo: probar con el plugin en `main` estable.

## Principios

- **Una rama y un PR por paquete** (`ui/WP-XX-slug`), un solo propósito, diff acotado (orientativo: menos de unas 400 líneas).
- **Dueño de archivos por paquete, con archivos concretos.** Dos paquetes abiertos a la vez no comparten archivos; si comparten, el segundo depende del primero.
- **Nadie hace push a `main`, ni ejecuta `workflow_dispatch`, ni mergea.** Los agentes abren PR; una persona mergea.
- **Cada PR adjunta** capturas antes/después, la propuesta de entrada de bitácora y los IDs de `HALLAZGOS.md` que cierra. `HALLAZGOS.md` y el índice de bitácora los actualiza T3 o una persona, fuera de los PR de paquetes.
- Un paquete que toca tema y plugin se divide en dos PR.

## Compuertas

Lo que realmente corre hoy (leído en los workflows):
- Plugin: `encoding-check` ("Repository Guard") en cualquier PR; `php-lint`, `f0-baseline`, `plugin-inventory` y `preinscriptions-contract` con filtros de ruta ajenos a UI. Un PR solo de CSS corre prácticamente solo `encoding-check`.
- Tema: `theme-ci.yml` solo corre en PR con cambios en `**.php`. Los PR solo de CSS (WP-04, WP-07, WP-12) **no corren ningún test del tema**.
- Si se marcan como "required checks" workflows con filtro de ruta, los PR quedan colgados en "Expected".
- `agent_check.py --diff` mira `git status --porcelain`: no ve archivos ya commiteados, descarta directorios no rastreados, no revisa CSS ni el tema. **No usarlo como compuerta de WP-04 a WP-13.**
- Tests del tema acoplados al CSS: `offer-contrast-ink-test.php`, `page-formacion-color-contract-test.php`, `front-page-legacy-assets-test.php` y unos 20 más que mencionan `.css`. Cada T1 previo los mapea. Los colores por área (`#6b1fad`, `#489ddd`, …) son intencionales y no se colapsan a la paleta.

Propuestas (requieren aprobación): `stylelint` en modo advertencia; job manual con Playwright (capturas y `scrollWidth`) y `axe`; presupuesto de tamaño de assets; un workflow de PR que corra los tests del tema también en cambios de CSS.

## Paquetes de trabajo

Flujo: T1 previo (mapa de archivos, llamadores y tests acoplados) → T2 implementa en worktree → verificación local → PR → T3 revisa → una persona mergea → registro en bitácora y estado en `HALLAZGOS.md`.

| ID | Objetivo | Archivos (dueño) | Depende de | Compuerta de aceptación | Cierra |
|---|---|---|---|---|---|
| WP-00 | Línea base: capturas y mediciones del sitio público | Solo lectura; T3 o una persona copia lo valioso a `docs/auditoria-ui-ux/baseline/` | – | Capturas y métricas registradas | confirma H-A01 (estilo computado), H-A03, H-A05, H-B08 |
| WP-01 | Gobernanza y prerrequisitos 1 a 8 | `docs/auditoria-ui-ux/`, `AGENTS.md` (plugin y tema), `.github/` (solo con permiso) | – | Revisión T3 | – |
| WP-02 | Formularios y mailing | `modules/formularios/includes/{validation,form-handlers,form-render,assets,error-policy,confirmacion-consulta}.php`, `modules/formularios/assets/**`, `modules/main-page/includes/flacso-consultas.php`, `modules/mailing/includes/class-flacso-mailing-subscription.php`. Primero un test PHP CLI de `fc_is_spam_content()` en `tests/`. Incluye quitar `fc-iti` | WP-01 | Test nuevo en `tests/` verde, contratos del plugin, prueba manual con Playwright bloqueando `admin-post.php` y `admin-ajax.php`; T3 obligatorio | H-A01, A02, A11 a A15, A18, parte de H-C04 |
| WP-03 | Código muerto y huérfano | Tema `inc/assets.php` (handles legacy, jQuery UI), `template-parts/front-page/hero.php`, bloques muertos `.seminarios-hero__details` en `assets/css/seminarios-listado*.css`, `template-parts/home/about.php` (URL del logo); plugin métodos sin uso de `includes/core/class-flacso-integrations-settings.php`, `modules/docentes/includes/admin.php` (huérfano) y el enlace roto de `includes/core/class-flacso-admin-panel.php:175` | WP-01 | Capturas sin diferencia visual; confirmar que no hay llamadas por hooks dinámicos | H-A07, H-A10, H-C05 |
| WP-04 | Archivo de tokens que apunte a `var(--global-*)` y sustitución de hex | Tema `style.css`, `assets/css/*`; luego plugin `modules/*/assets/css` | WP-03; mapeo real de `palette1-9` | Capturas por tipo de página (portada, formación, ficha de oferta, seminario, docente, convenio, contacto, FAQ, noticias); tests del tema acoplados mapeados y verdes; editor de bloques revisado; fallbacks donde Kadence no imprime variables | H-B01 a H-B06, H-B08 |
| WP-05 | Componente de botón único | Reglas de botón de tema y plugin | WP-04 | Capturas de las 7 variantes unificadas | H-C01 |
| WP-06 | Cards, heroes, badges, títulos y estilos de editor de bloques | Tema y plugin, por componente | WP-04, WP-05 | Capturas por viewport | H-C02, H-C03, H-C06 |
| WP-07 | Capas móviles, breakpoints canónicos, header (`site-chrome.css/js`, `mobile-ux.css`) y foco del buscador | `site-chrome.css`, `site-chrome.js`, `mobile-ux.css`, `flacso-mobile-first.css`, `flacso-public-mobile.css`, `seminarios-listado-polish.css:836-838` | WP-04; confirmar antes los breakpoints reales de Kadence | `scrollWidth` sin desborde de 320 a 1080 px | H-D01 a H-D03, H-A05 |
| WP-08 | Accesibilidad | Tema y plugin, excluyendo los archivos de WP-07 | WP-07 | `axe` sin errores nuevos y prueba de teclado | H-A04, H-A16, H-E01, H-E02 |
| WP-09 | Microcopy e i18n | Cadenas fuera de `modules/formularios/` y `flacso-consultas.php` hasta que WP-02 se merge | WP-02 | `grep` de cadenas fijas y revisión T3 | H-G01 a H-G04, H-I02 |
| WP-10 | Rendimiento | Tema `inc/assets.php`, `inc/home.php`; plugin enqueues | WP-03, WP-04 | Mediciones antes/después | H-H01 a H-H04, H-A19 |
| WP-11 | Admin | `class-flacso-integrations-settings.php` (tras WP-03), `class-flacso-system-settings.php`, `modules/consultas/`, `modules/docentes/` (resto) | WP-03 | `agent_check.py` y revisión T3 (seguridad) | H-A08, H-A09, H-J01, H-J02 |
| WP-12 | Partir archivos grandes y cargar por página | `flacso-main-page.css`, `oferta-academica/style.css`, `single-carta-editorial.css` | WP-04 a WP-07; inventario previo del uso real en la base (`flacso-db`, solo lectura y sin tablas con datos personales) | Capturas sin diferencia | H-B07 |
| WP-13 | Estados e interacción: vacío, carga y error de Instagram, FAQ (acordeón, buscador), indicador de enlaces externos | Módulos `preguntas-frecuentes`, `instagram`, enlaces de la portada | WP-06 | Capturas y prueba de teclado | H-A06, H-A17, H-F01, H-I01 |

Hallazgos que cierra una decisión del dueño y no un paquete: H-A03 (contenido guardado de la sección `quienes`) y H-A20 (enlaces rotos y redirecciones en menús y contenido).

## Orden recomendado
Prerrequisitos → WP-00 → WP-01 → WP-02 → WP-03 → WP-04 → WP-05, WP-06, WP-07 y WP-10 en paralelo cuando sus archivos sean disjuntos → WP-08, WP-09, WP-11 y WP-13 → WP-12.

La fase 3 (revisión T3 de `HALLAZGOS.md` con el informe de Playwright) puede reordenar esta lista.
