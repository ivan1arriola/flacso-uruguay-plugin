# Bitácora de la auditoría UI/UX

Registro de solo-agregar. Cada intervención (de una persona o de un agente, de cualquier herramienta) termina con una entrada. Una entrada ya escrita no se edita: las correcciones son entradas nuevas que citan el ID anterior.

Antes de empezar, leer también `HALLAZGOS.md` (estado de cada hallazgo) y `GUIA-SUBAGENTES.md` (reglas y tiers).

## Modelo vigente (desde B-0020)

- **Historia:** B-0001 a B-0019 quedan en este archivo y no se modifican.
- **Entradas nuevas:** un archivo por entrada, `bitacora/B-NNNN-slug.md`, con el ID reservado por quien coordina (T3 o una persona). T1 y T2 proponen la entrada en su informe; T3 o una persona la registra. Los PR de paquetes de trabajo no editan este archivo ni `HALLAZGOS.md`.
- **Índice de entradas nuevas** (lo mantiene T3 o una persona):

| ID | Fecha | Título | Archivo |
|---|---|---|---|
| B-0020 | 2026-11-21 | Decisiones del dueño sobre deploy, AGENTS.md, bitácora y capturas | [Entrada B-0020](bitacora/B-0020-decisiones-del-dueno.md) |
| B-0021 | 2026-11-21 | Repetición de WP-00: paleta Kadence, foco del buscador, menú móvil y carrusel | [Entrada B-0021](bitacora/B-0021-rerun-playwright-palette-foco-menu.md) |
| B-0022 | 2026-11-21 | WP-02 mapa previo de formularios y mailing (lectura) | [Entrada B-0022](bitacora/B-0022-wp02-mapa-previo-formularios.md) |
| B-0023 | 2026-11-21 | WP-03 verificación de candidatos a código muerto (lectura) | [Entrada B-0023](bitacora/B-0023-wp03-mapa-previo-codigo-muerto.md) |
| B-0024 | 2026-11-21 | WP-04 mapa previo de tokens Kadence y hexes (lectura) | [Entrada B-0024](bitacora/B-0024-wp04-mapa-previo-tokens-kadence.md) |
| B-0025 | 2026-11-21 | WP-05 mapa previo de componente de botón único (lectura) | [Entrada B-0025](bitacora/B-0025-wp05-mapa-previo-botones.md) |
| B-0026 | 2026-11-21 | WP-06 mapa previo de componentes y estilos de editor (lectura) | [Entrada B-0026](bitacora/B-0026-wp06-mapa-previo-componentes-editor.md) |
| B-0027 | 2026-11-21 | WP-07 mapa previo de capas móviles, breakpoints y header (lectura) | [Entrada B-0027](bitacora/B-0027-wp07-mapa-previo-mobile-header.md) |
| B-0028 | 2026-11-21 | WP-08 mapa previo de accesibilidad WCAG 2.2 AA (lectura) | [Entrada B-0028](bitacora/B-0028-wp08-mapa-previo-accesibilidad.md) |
| B-0030 | 2026-11-21 | WP-10 mapa previo de rendimiento y errores JS (lectura) | [Entrada B-0030](bitacora/B-0030-wp10-mapa-previo-rendimiento-js.md) |
| B-0031 | 2026-11-21 | WP-11 mapa previo de admin UX y seguridad (lectura) | [Entrada B-0031](bitacora/B-0031-wp11-mapa-previo-admin.md) |
| B-0033 | 2026-11-21 | WP-13 mapa previo de estados e interacción (lectura) | [Entrada B-0033](bitacora/B-0033-wp13-mapa-previo-estados-interaccion.md) |
| B-0035 | 2026-11-22 | WP-S1 implementación: `/ofertas/settings` solo para administradores | [Entrada B-0035](bitacora/B-0035-wp-s1-permisos-settings-api.md) |
| B-0036 | 2026-11-22 | Recorte del plan a tres cambios | [Entrada B-0036](bitacora/B-0036-recorte-del-plan.md) |
| B-0037 | 2026-11-22 | H-A11: el filtro anti-spam ya no descarta consultas legítimas | [Entrada B-0037](bitacora/B-0037-h-a11-filtro-antispam.md) |
| B-0038 | 2026-11-22 | Push de las dos ramas y apertura de los PR | [Entrada B-0038](bitacora/B-0038-push-de-ramas-y-prs.md) |
| B-0039 | 2026-11-22 | Resultado del chequeo de los PR #100 y #101 | [Entrada B-0039](bitacora/B-0039-chequeo-de-los-pr.md) |
| B-0040 | 2026-11-22 | Merge de #100 y #101 y verificación en producción | [Entrada B-0040](bitacora/bitacora\B-0040-merge-y-verificacion-en-produccion.md) |
| B-0041 | 2026-11-22 | Barrido de la superficie pública tras publicar los docs | [Entrada B-0041](bitacora/bitacora\B-0041-barrido-superficie-publica.md) |
| B-0042 | 2026-11-22 | Lote chico: privacidad del log y AJAX público sin uso | [Entrada B-0042](bitacora/bitacora\B-0042-lote-privacidad-y-superficie.md) |
| B-0043 | 2026-11-22 | Push de la rama del lote chico y PR #102 | [Entrada B-0043](bitacora/bitacora\B-0043-push-y-pr-del-lote-chico.md) |
| B-0044 | 2026-11-22 | Merge del PR #102 y verificación en producción | [Entrada B-0044](bitacora/bitacora\B-0044-merge-del-pr-102-y-verificacion.md) |
| B-0045 | 2026-11-22 | Plan de Kadence: revisión de la rama, vista previa y PR #83 del tema | [Entrada B-0045](bitacora/bitacora\B-0045-kadence-revision-previa-y-pr-83.md) |
| B-0046 | 2026-11-22 | Merge del PR #83 del tema y verificación en producción | [Entrada B-0046](bitacora/bitacora\B-0046-merge-del-pr-83-y-verificacion.md) |
| B-0047 | 2026-11-22 | Ajustes de Kadence verificados y limpieza del Editor externo | [Entrada B-0047](bitacora/bitacora\B-0047-ajustes-de-kadence-y-limpieza-del-editor.md) |
| B-0048 | 2026-11-22 | Merge del PR #103 (limpieza del Editor externo) y verificación | [Entrada B-0048](bitacora/bitacora\B-0048-merge-del-pr-103-y-verificacion.md) |
| B-0049 | 2026-11-22 | Opciones huérfanas del admin: diagnóstico en el servidor y PR #104 | [Entrada B-0049](bitacora/bitacora\B-0049-opciones-huerfanas-del-admin-y-pr-104.md) |
| B-0050 | 2026-11-22 | Auditoría de Kadence e identidad visual | [Entrada B-0050](bitacora/B-0050-auditoria-kadence-y-plan-t3.md) |
| B-0051 | 2026-11-23 | Auditoría del plan maestro, las bitácoras y los contratos entre repositorios | [Entrada B-0051](bitacora/B-0051-auditoria-documental.md) |
| B-0052 | 2026-11-24 | Plan maestro v9 en el repositorio y rediseño de «Más información» | [Entrada B-0052](bitacora/B-0052-plan-v9-y-mas-informacion.md) |

## Formato de entrada

```
## B-NNNN · AAAA-MM-DD · <título corto>
- Actor: <persona | herramienta> · Tier: T1|T2|T3 · Modelo: <nombre>
- Tarea / alcance: <qué se pidió; repos y carpetas leídas o tocadas>
- Modo: lectura | escritura (rama: ui/WP-xx-...)
- Resultado: <2 a 4 líneas>
- Hallazgos: H-... (nuevo | confirmado | descartado | intencional | hecho)
- Verificación: <comando, captura o medición; o "no verificado">
- Pendiente / siguiente paso: <...>
```

Estados de un hallazgo: `nuevo` → `confirmado` | `descartado` | `intencional` → `en curso` → `hecho` (con PR y captura).

---

## B-0001 · 2026-11-21 · Barrido del tema
- Actor: Claude Code · Tier: T1 · Modelo: Haiku (Explore)
- Tarea / alcance: auditoría UI/UX estática de `flacso-theme-kadence` (plantillas, template-parts, inc, CSS, JS). Excluye `.worktrees/`, `node_modules`, `vendor`, `*.min.*`.
- Modo: lectura
- Resultado: tokens propios y 1.137 hex contra 87 `var(--global-*)`; 688 `!important`; unos 29 breakpoints; 14 o más familias de botón; foco con 13 estilos; cuatro `outline` suprimidos sin sustituto.
- Hallazgos: H-B01 a H-B07, H-C01 a H-C05, H-D01, H-E01, H-E02 (nuevos)
- Verificación: no verificado (lectura de código; contrastes de cálculo propio)
- Pendiente / siguiente paso: `oferta-academica/` del tema solo se revisó por `grep`.

## B-0002 · 2026-11-21 · Barrido del plugin, bloque A
- Actor: Claude Code · Tier: T1 · Modelo: Haiku (Explore)
- Tarea / alcance: `modules/main-page`, `docentes`, `formularios`, `core`, `autoridades` del plugin.
- Modo: lectura
- Resultado: `class-flacso-kadence-compat.php` solo sanea tipografía de bloques; fallbacks divergentes de `--global-palette*`; dos formularios de consulta; mensajes de error por campo ocultos por `display:none` inline; 412 `!important`.
- Hallazgos: H-A01, H-A02, H-B01 a H-B07, H-C04, H-C06, H-E02, H-F01 (nuevos)
- Verificación: no verificado
- Pendiente / siguiente paso: confirmar en navegador (ver B-0017).

## B-0003 · 2026-11-21 · Barrido del plugin, bloque B
- Actor: Claude Code · Tier: T1 · Modelo: Haiku (Explore)
- Tarea / alcance: `oferta-academica`, `eventos`, `convenios`, `mailing`, `consultas`, `seminarios`, `preinscripciones`, `academico`, `preguntas-frecuentes`, `includes/`, `assets/`, enqueues de la raíz.
- Modo: lectura
- Resultado: cero referencias a `--global-palette*`; 7 familias de badge, 4 de chip, 4 de cabecera de card; estados de admin en inglés; 3 tratamientos de persona (voseo, tuteo, usted); 6 text domains.
- Hallazgos: H-B01, H-B02, H-C03, H-G01 a H-G04, H-J01 (nuevos)
- Verificación: no verificado
- Pendiente / siguiente paso: ninguno propio.

## B-0004 · 2026-11-21 · D1 respeto a Kadence
- Actor: Claude Code · Tier: T1 · Modelo: Haiku (Explore)
- Tarea / alcance: ambos repos, dimensión "respeto a la configuración de Kadence".
- Modo: lectura
- Resultado: 29 de 104 archivos de front usan alguna `var(--global-*)`; sin `theme.json`, paleta ni tipografías registradas; `--global-palette-btn-bg` usado 43 veces y `--global-content-width` 4. Peores archivos: `flacso-main-page.css` (415 hex), `oferta-academica/style.css` (393 hex, 0 vars).
- Hallazgos: H-B01 a H-B08 (nuevos)
- Verificación: no verificado; faltan los valores reales de `palette1-9`.
- Pendiente / siguiente paso: Playwright lee `:root` en vivo (B-0017).

## B-0005 · 2026-11-21 · D2 consistencia de componentes
- Actor: Claude Code · Tier: T1 · Modelo: Haiku (Explore)
- Tarea / alcance: ambos repos, componentes del front público.
- Modo: lectura
- Resultado: 7 o más variantes de botón primario con radios de 2 a 999 px; `.evento-card` definida 3 veces; convenios duplicado entre tema y plugin; `front-page/hero.php` sin CSS (huérfano).
- Hallazgos: H-C01 a H-C06 (nuevos)
- Verificación: no verificado
- Pendiente / siguiente paso: ninguno propio.

## B-0006 · 2026-11-21 · D3 sistema de diseño
- Actor: Claude Code · Tier: T1 · Modelo: Haiku (Explore)
- Tarea / alcance: ambos repos, tokens y escalas.
- Modo: lectura
- Resultado: sin archivo central de tokens; unas 366 variables propias; unos 12 azules, 11 amarillos, 20 radios, más de 40 sombras, 52 breakpoints.
- Hallazgos: H-B02 a H-B04, H-D01 (nuevos)
- Verificación: conteos por `grep` (con falsos positivos posibles)
- Pendiente / siguiente paso: segundo `grep` independiente antes de planificar WP-04.

## B-0007 · 2026-11-21 · D4 accesibilidad
- Actor: Claude Code · Tier: T1 · Modelo: Haiku (Explore)
- Tarea / alcance: ambos repos, WCAG 2.2 AA en el front público.
- Modo: lectura
- Resultado: contrastes de 3,98:1, 4,33:1 y foco `#fcd116` de 1,5:1 (cálculo propio); buscador del header sin foco; menú móvil sin trampa de foco; botón con `aria-label` fijo; errores sin `aria-describedby`.
- Hallazgos: H-A01, H-A04, H-E01, H-E02 (nuevos)
- Verificación: no verificado
- Pendiente / siguiente paso: medir en navegador.

## B-0008 · 2026-11-21 · D5 responsive y mobile
- Actor: Claude Code · Tier: T1 · Modelo: Haiku (Explore)
- Tarea / alcance: ambos repos.
- Modo: lectura
- Resultado: 4 capas CSS móviles que se pisan; la portada quita las del plugin; padding lateral de 12 px contra 20 px; `overflow-x:hidden` global; `h1` de 64 px fijo; `100vh` sin `dvh`.
- Hallazgos: H-D01 a H-D03 (nuevos)
- Verificación: no verificado
- Pendiente / siguiente paso: medir `scrollWidth` de 320 a 1080 px.

## B-0009 · 2026-11-21 · D6 estados de la interfaz
- Actor: Claude Code · Tier: T1 · Modelo: Haiku (Explore)
- Tarea / alcance: ambos repos; vacío, carga, error, éxito, hover, foco, 404, gracias.
- Modo: lectura
- Resultado: mailing sin feedback; formulario general con errores invisibles; instagram silencia errores; vacío con 2 o 3 textos distintos.
- Hallazgos: H-A01, H-A02, H-A06, H-F01 (nuevos)
- Verificación: no verificado
- Pendiente / siguiente paso: confirmar `display:none` inline en navegador.

## B-0010 · 2026-11-21 · D7 contenido y microcopy
- Actor: Claude Code · Tier: T1 · Modelo: Haiku (Explore)
- Tarea / alcance: ambos repos.
- Modo: lectura
- Resultado: voseo, tuteo y usted mezclados; unas 20 etiquetas de CTA para unas 8 acciones; moneda de 4 formas; 6 formatos de fecha; 13 palabras sin tilde; 6 text domains.
- Hallazgos: H-G01 a H-G04 (nuevos)
- Verificación: conteos por `grep`
- Pendiente / siguiente paso: propuesta de glosario a validar con el dueño (WP-09).

## B-0011 · 2026-11-21 · D8 render y markup
- Actor: Claude Code · Tier: T1 · Modelo: Haiku (Explore)
- Tarea / alcance: ambos repos.
- Modo: lectura
- Resultado: causa probable del párrafo partido en "¿Qué es FLACSO Uruguay?": `wpautop` sobre contenido guardado con saltos entre `<strong>` (`about.php:4`); 688 `!important`; estilos inline en 8 archivos.
- Hallazgos: H-A03, H-A10, H-C05 (nuevos)
- Verificación: no verificado (falta ver la opción guardada)
- Pendiente / siguiente paso: `GET /wp-json/flacso/v1/main-page`, sección `quienes`.

## B-0012 · 2026-11-21 · D9 rendimiento percibido
- Actor: Claude Code · Tier: T1 · Modelo: Haiku (Explore)
- Tarea / alcance: ambos repos; tamaños en disco, sin compresión.
- Modo: lectura
- Resultado: hero de 336 KB a 2560 px sin `srcset`; Bootstrap global y segunda copia por CDN; analítica síncrona en `<head>`; `oferta-academica/style.css` de 151 KB.
- Hallazgos: H-H01 a H-H03 (nuevos)
- Verificación: tamaños de archivo medidos; sin medición en navegador
- Pendiente / siguiente paso: Lighthouse o equivalente en Playwright.

## B-0013 · 2026-11-21 · D10 UX del admin
- Actor: Claude Code · Tier: T1 · Modelo: Haiku (Explore)
- Tarea / alcance: admin de WordPress del plugin.
- Modo: lectura
- Resultado: submenús de docentes sin callback; acciones de correo real sin confirmación; secretos en el `value`; unas 750 líneas muertas en Meta; 104 estilos inline en autoridades.
- Hallazgos: H-A07 a H-A09, H-J01, H-J02 (nuevos)
- Verificación: no verificado
- Pendiente / siguiente paso: confirmar que el código de Meta no se llama por hooks dinámicos antes de borrar.

## B-0014 · 2026-11-21 · P1 leyes de UX
- Actor: Claude Code · Tier: T1 · Modelo: Haiku (Explore)
- Tarea / alcance: front público contra las 10 leyes de UX del artículo provisto.
- Modo: lectura
- Resultado: Jakob incumple; Similitud incumple parcial; Miller incumple en FAQ; resto parcial o cumple; Conectividad no verificable (flujo en otro repo).
- Hallazgos: H-A17, H-I01 (nuevos)
- Verificación: no verificado
- Pendiente / siguiente paso: auditar `preinscripcionesFLACSOUY` si el dueño lo incluye.

## B-0015 · 2026-11-21 · P2 heurísticas de Nielsen
- Actor: Claude Code · Tier: T1 · Modelo: Haiku (Explore)
- Tarea / alcance: ambos repos, 10 heurísticas de Nielsen.
- Modo: lectura
- Resultado: posible descarte silencioso de consultas por el regex anti-spam; `wp_die` que pierde datos; modo relajado y sin nonce por defecto; errores sin código; carrusel de eventos sin pausa.
- Hallazgos: H-A11 a H-A18, H-I02 (nuevos)
- Verificación: no verificado (varios ítems son inferidos del código y deben probarse)
- Pendiente / siguiente paso: probar H-A11 en staging, nunca en producción.

## B-0016 · 2026-11-21 · Consolidación y plan
- Actor: Claude Code · Tier: T3/coordinación · Modelo: Sonnet
- Tarea / alcance: consolidar B-0001 a B-0015, leer `AGENTS.md` y los workflows del plugin, redactar el plan.
- Modo: lectura y escritura del archivo de plan
- Resultado: plan aprobado por el dueño con bitácora, guía por tiers, paquetes de CI y fases de verificación. Se detectó que `AGENTS.md` ordena `git push origin HEAD:main` (deploy a producción) y el uso de `flacso_api.py` (escribe en producción).
- Hallazgos: ninguno nuevo
- Verificación: n/a
- Pendiente / siguiente paso: la revisión T3 de la lista (fase 3) se hará con el informe de Playwright. Nota de precisión: el plan decía 17 agentes en la fase 1; fueron 15.

## B-0017 · 2026-11-21 · Alta de la gobernanza y lanzamiento de Playwright
- Actor: Claude Code · Tier: T3/coordinación + T1 · Modelo: Sonnet (coordinación), Haiku (Playwright)
- Tarea / alcance: crear `docs/auditoria-ui-ux/` (4 documentos) y `LEEME.md` en el tema; lanzar WP-00 (línea base con Playwright sobre el sitio público, sin enviar formularios).
- Modo: escritura de documentación (sin commit); lectura del sitio público
- Resultado: pendiente. El informe del agente de Playwright queda en la carpeta de trabajo de la sesión y se registrará en B-0018.
- Hallazgos: ninguno aún
- Verificación: pendiente
- Pendiente / siguiente paso: B-0018 con el resultado; después, fase 3 (revisión T3).

## B-0019 · 2026-11-21 · Validación del plan por T3
- Actor: Claude Code · Tier: T3 · Modelo: Opus (Explore, solo lectura)
- Tarea / alcance: validar el plan, los 4 documentos de gobernanza y 5 hallazgos críticos contra el código; leyó `AGENTS.md`, los workflows de ambos repos, `agent_check.py` y los tests del tema.
- Modo: lectura
- Resultado: **aprobado con cambios**. Confirmados H-A09, H-A11, H-A12, H-A13, H-A14, H-A15; parcial H-A01; reformulado H-A07. Riesgos nuevos: `workflow_dispatch` despliega cualquier rama, secretos de repo al alcance de cualquier rama, checkouts locales en `main`, bitácora compartida que choca con "archivos disjuntos", CI que no cubre PR solo de CSS, no existe staging, el caso A1 de Playwright no era seguro, y mergear solo `docs/` igual despliega.
- Hallazgos: H-A01, H-A07, H-A09, H-A11, H-A13 (actualizados); H-A12, H-A14, H-A15 (confirmados)
- Verificación: lectura de código y de workflows; nada ejecutado. No verificado: `wp-config` de producción, reCAPTCHA en producción, el menú de docentes en tiempo de ejecución.
- Correcciones a entradas anteriores (esta bitácora es de solo-agregar): (1) B-0016 figura con modelo Sonnet: esa fue coordinación, y la revisión T3 de verdad es esta entrada (Opus). (2) B-0016 dice haber leído los workflows: solo se listaron los nombres; su contenido se leyó recién en B-0019. (3) B-0016 dice "plan aprobado": aprobado por el dueño, y recién ahora con cambios de T3. (4) H-A05 proviene de B-0001 y H-G02 de B-0010, aunque esas entradas no los listan. (5) B-0017 lanzó WP-00 antes de esta revisión; era solo lectura del sitio público, pero su caso A1 se corrige (ver `GUIA-SUBAGENTES.md`).
- Pendiente / siguiente paso: prerrequisitos bloqueantes de `TAREAS-CI.md` (decisiones del dueño sobre deploy, secretos, `AGENTS.md` y modelo de bitácora); cerrar B-0018 (Playwright).

## B-0018 · 2026-11-21 · WP-00 línea base con Playwright (registrada después de B-0019)
- Actor: Claude Code · Tier: T1 · Modelo: Haiku (general-purpose, con Bash y Playwright 1.65.1)
- Tarea / alcance: 21 páginas públicas de `https://flacso.edu.uy` a 375 y 1280 px, y 768 y 1024 px en 8 páginas (62 visitas); 4 corridas de foco; 141 enlaces internos únicos. Sin sesión, sin formularios enviados, sin escritura en los repos. El docente quedó sin cubrir (no hay enlace desde las fichas).
- Modo: lectura del sitio público
- Resultado: todas las páginas responden 200 salvo la 404 esperada; sin desborde horizontal en los anchos probados; h1 único en todas; 0 imágenes sin `alt`. Confirmado en producción el párrafo roto de la portada (H-A03) y los mensajes de error ocultos en `/contactos/` (H-A01). Refutada la hipótesis del párrafo editorial oculto en móvil (H-A05). Nuevos: error de JS en consola (H-A19), enlaces rotos (H-A20) y peso de imágenes (H-H04).
- Hallazgos: H-A01, H-A03, H-A04, H-A05, H-D02, H-H01 (actualizados); H-A19, H-A20, H-H04 (nuevos)
- Verificación: MEDIDO en navegador, con estas salvedades: (1) el selector del buscador del header no coincidió, así que su foco no se probó; (2) la prueba del menú móvil falló por timeout (abrió un submenú); (3) no se detectó ningún carrusel en la portada, probablemente por selectores; (4) el campo `stickyOrFixedBehaviour` del script está mal calculado, la conclusión correcta es "no sticky"; (5) el agente no encontró ningún `--global-palette*` en las hojas de estilo que leyó, pero eso no prueba que no existan en el navegador: **H-B08 sigue sin resolver**; (6) los ratios de contraste son cálculo propio y varios casos tienen un ancestro con imagen, así que no son fiables; (7) los tiempos de LCP son de una sola corrida desde este entorno.
- Efectos en el sitio: el agente visitó producción con tráfico de lectura, unas 62 páginas más los 141 enlaces internos. Mi prompt no pedía bloquear analítica ni rechazar cookies (esas reglas se agregaron a la guía después), así que el pixel de Facebook y la analítica pueden haber registrado esas visitas; Meta CAPI se registra además del lado del servidor. Conviene filtrar ese tráfico (ventana aproximada 21:26 a 21:53, hora del servidor de la sesión).
- Archivos: `baseline/informe-playwright-2026-11-21.md` (copia del informe). Las 66 capturas y `results.json` (2,3 MB) quedan en la carpeta de trabajo de la sesión, fuera de los repos, y esa carpeta puede borrarse al reiniciar.
- Pendiente / siguiente paso: repetir foco del buscador, menú móvil y carrusel con selectores correctos; leer `--global-palette1` a `--global-palette9` con `getComputedStyle` y nombres conocidos para cerrar H-B08; decidir dónde guardar las capturas.
