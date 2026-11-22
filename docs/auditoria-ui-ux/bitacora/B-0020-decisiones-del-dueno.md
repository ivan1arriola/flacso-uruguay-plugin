## B-0020 · 2026-11-21 · Decisiones del dueño sobre deploy, AGENTS.md, bitácora y capturas
- Actor: Claude Code (coordinación) con decisiones de la persona dueña del proyecto · Tier: coordinación · Modelo: Sonnet
- Tarea / alcance: cerrar los puntos abiertos por la revisión T3 (B-0019).
- Modo: escritura de documentación; sin commit ni push
- Resultado:
  1. **Deploy y secretos:** decidido usar un **environment protegido** (`production`, con revisores y solo la rama `main`), mover los secretos SSH ahí y poner CODEOWNERS sobre `.github/`. **Pendiente de configurar en GitHub por la persona dueña**; no se tocó ningún workflow ni configuración. Hasta entonces, `workflow_dispatch` sigue pudiendo desplegar cualquier rama.
  2. **`AGENTS.md`:** editado el del plugin (sección 0 nueva con reglas obligatorias para agentes, flujo de PR en lugar de `git push origin HEAD:main`, escrituras de `flacso_api.py` solo por pedido explícito de una persona) y **creado** el del tema. Sin commit: pendiente de revisión humana.
  3. **Bitácora:** modelo de **una entrada por archivo** (`bitacora/B-NNNN-slug.md`); B-0001 a B-0019 quedan como historia en `BITACORA.md`.
  4. **Capturas de Playwright:** copiadas a una carpeta fuera de los repos, `auditoria-baseline/2026-11-21/` dentro del directorio de trabajo `Plugin + Tema` (que no es un repositorio git). Incluye `shots/` (66 capturas), `results.json`, `run.log`, `informe.md`, los dos `a3-innerhtml-*.html` y `scripts/`. Corrige lo dicho en B-0018, que las dejaba solo en la carpeta temporal de la sesión.
- Hallazgos: ninguno nuevo
- Verificación: no ejecutado; las ediciones se hicieron sobre archivos de texto. No se probó que el nuevo `AGENTS.md` sea leído por cada herramienta.
- Pendiente / siguiente paso: (a) configurar el environment protegido y los secretos en GitHub; (b) revisar y commitear (por una persona) `AGENTS.md` y `docs/auditoria-ui-ux/`; (c) repetir con selectores correctos el foco del buscador, el menú móvil y el carrusel, y leer `--global-palette1` a `--global-palette9` con `getComputedStyle` (cierra H-B08); (d) lanzar WP-01 y WP-02 solo después de (a) y (b).
