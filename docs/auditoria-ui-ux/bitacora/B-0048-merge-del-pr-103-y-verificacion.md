## B-0048 · 2026-11-22 · Merge del PR #103 (limpieza del Editor externo) y verificación
- Actor: Claude Code (verificación) · Tier: coordinación · Modelo: Sonnet. El merge lo hizo la persona dueña.
- Tarea / alcance: confirmar el merge, el despliegue y que el sitio y la API siguen respondiendo; limpiar el worktree.
- Modo: lectura de GitHub Actions y solicitudes de solo lectura al sitio público (solo códigos de estado); borrado del worktree `wt-editor`.
- Resultado: PR #103 mergeado a las 04:39:35 UTC (`39ca098`). Despliegue y "Repository Guard" en verde. Home, `/formacion/` y `/contactos/` con HTTP 200; `/wp-json/flacso/v1/ofertas` (API pública) 200; `/ofertas/settings` anónimo sigue en 401. `main` local sincronizado; worktree borrado sin errores.
- Hallazgos: H-A07 (parte del código huérfano del editor) y el código muerto del Editor externo: cerrados. Siguen fuera: el admin de docentes huérfano (`modules/docentes/includes/admin.php`), el botón de prueba y las funciones del webhook de consultas.
- Verificación: despliegue y códigos de estado. No se verificó la pantalla de Integraciones del admin (requiere sesión).
- Pendiente / siguiente paso: lo abierto de B-0040 (cuentas Colaborador o superior y rotación de secretos, aviso a recepción por el filtro anti-spam, `error_log` en unos días). Siguiente trabajo sugerido: WP-02 (errores del formulario invisibles y mailing sin feedback) y WP-08 (foco visible).
