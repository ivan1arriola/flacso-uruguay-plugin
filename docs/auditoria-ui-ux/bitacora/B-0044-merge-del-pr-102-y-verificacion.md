## B-0044 · 2026-11-22 · Merge del PR #102 y verificación en producción
- Actor: Claude Code (verificación) · Tier: coordinación · Modelo: Sonnet. El merge lo hizo la persona dueña.
- Tarea / alcance: confirmar el merge, el despliegue y el efecto de los dos cambios del lote chico; limpiar el worktree.
- Modo: lectura de GitHub Actions y solicitudes de solo lectura al sitio público (solo códigos de estado); borrado del worktree `wt-lote`.
- Resultado: la primera verificación (poco después del aviso) vio el PR todavía abierto; la segunda lo vio mergeado a las 03:48:42 UTC (`ebfd292`). Despliegue y "Repository Guard" en verde. Home HTTP 200; `admin-ajax.php?action=buscar_convenios_distancia` responde 400 con cuerpo `0` (acción sin handler: el AJAX público retirado ya no existe); `/ofertas/settings` anónimo sigue en 401. `main` local sincronizado con `origin/main`.
- Hallazgos: H-A21 y el AJAX muerto de convenios: cerrados. La parte de H-A21 (que el log no lleve el correo) solo se verifica con un incidente real: no se provocó uno.
- Verificación: despliegue y códigos de estado; el efecto sobre el `error_log` no se verificó en ejecución.
- Pendiente / siguiente paso (dueño): acciones de B-0040 (valor de `fc_consultas_webhook_url`, DNS de `editor.flacso.edu.uy`, cuentas Colaborador o superior y rotación de secretos, aviso a recepción por el filtro anti-spam, mirar el `error_log` en unos días). Resto del plan, en espera.
