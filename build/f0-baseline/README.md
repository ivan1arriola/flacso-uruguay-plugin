# F0 — Línea base estática

- Archivos PHP: **1454**
- Módulos con archivos PHP: **20**
- Candidatos de registro/hook/configuración: **9124**
- Referencias de dependencia (heurísticas): **1606**
- Ciclos potenciales (heurísticos): **0**
- Declaraciones de tipos repetidas (heurísticas): **156**

## Tipos de puntos de entrada

| Tipo | Coincidencias |
|---|---:|
| add_action | 1749 |
| add_filter | 548 |
| add_option | 18 |
| add_shortcode | 140 |
| delete_option | 22 |
| get_option | 1358 |
| get_post_meta | 2931 |
| get_user_meta | 2 |
| register_activation_hook | 5 |
| register_deactivation_hook | 5 |
| register_meta | 56 |
| register_post_meta | 241 |
| register_post_type | 126 |
| register_rest_route | 134 |
| register_taxonomy | 5 |
| update_option | 373 |
| update_post_meta | 1356 |
| update_user_meta | 2 |
| wp_cli | 25 |
| wp_next_scheduled | 14 |
| wp_schedule_event | 14 |

## Archivos de mayor tamaño (líneas)

| Archivo | Líneas |
|---|---:|
| .worktrees/consultas-transaccionales/modules/main-page/sections/novedades-section.php | 2717 |
| .worktrees/mautic-offer-campaigns/modules/main-page/sections/novedades-section.php | 2717 |
| .worktrees/preinscripciones-integration/modules/main-page/sections/novedades-section.php | 2717 |
| .worktrees/seminar-permalink-availability/modules/main-page/sections/novedades-section.php | 2717 |
| modules/main-page/sections/novedades-section.php | 2717 |
| .worktrees/consultas-transaccionales/includes/core/class-flacso-integrations-settings.php | 2341 |
| .worktrees/preinscripciones-integration/includes/core/class-flacso-integrations-settings.php | 2341 |
| .worktrees/seminar-permalink-availability/includes/core/class-flacso-integrations-settings.php | 2341 |
| modules/main-page/includes/flacso-consultas.php | 2233 |
| .worktrees/consultas-transaccionales/modules/main-page/includes/flacso-consultas.php | 2202 |
| .worktrees/mautic-offer-campaigns/modules/main-page/includes/flacso-consultas.php | 2202 |
| .worktrees/preinscripciones-integration/modules/main-page/includes/flacso-consultas.php | 2202 |
| .worktrees/seminar-permalink-availability/modules/main-page/includes/flacso-consultas.php | 2202 |
| .worktrees/mautic-offer-campaigns/includes/core/class-flacso-integrations-settings.php | 2067 |
| includes/core/class-flacso-integrations-settings.php | 2067 |

## Limitaciones

- Regex matches may include text strings or inactive code; no full PHP AST.
- Class references and cycles are candidate dependencies, never proven calls.
- Dynamic hooks/routes/meta and permission callbacks need runtime verification.
- SQL keywords do not establish a live database table or schema.
- No production, credentials, data records or environment inspected.

Artefactos: files.csv, symbols.csv, entrypoints.csv, dependencies.csv, sql-candidates.csv, summary.json.
