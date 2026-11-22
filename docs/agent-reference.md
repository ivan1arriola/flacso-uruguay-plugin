# Referencia rápida para agentes de FLACSO Uruguay Plugin

Para empezar, leé [AGENTS.md](../AGENTS.md) y la [guía común](desarrollo/README.md). La [guía técnica del plugin](desarrollo/plugin.md) contiene rutas por dominio, flujos, pruebas y protocolos de entrega. Esta página lista los identificadores que suelen necesitarse para seguir el código.

| Identificador o contrato | Fuente de definición |
|---|---|
| `programa-academico`, `oferta-academica`, `cohorte`, `tabla-precio` | `modules/oferta-academica/includes/`. Las clases de registro, esquema y entidad definen claves y validación. |
| `tipo-oferta-academica` | Registro de oferta y [modelo académico](modelo-academico-final.md). Tipos: doctorado, maestría, especialización, diplomado y diploma. |
| `seminario` y `edicion` | `modules/seminarios/includes/class-seminario-cpt.php` y `class-edicion.php`. Usá las constantes del registro para crear fixtures. |
| `docente` y `evento` | `modules/docentes/` y `modules/eventos/`. Las URLs públicas se resuelven con los registros y permalinks de la instalación. |
| `FLACSO_Academic_Catalog` y `FLACSO_Academic_Repository` | `modules/oferta-academica/includes/class-academic-catalog.php` y `class-academic-repositories.php`. El tema consume una vista de presentación desde su adaptador. |
| `FLACSO_Carta_Settings` | `modules/oferta-academica/includes/class-carta-settings.php`. La configuración tiene un administrador del plugin. El tema usa los textos en su modelo de Carta. |
| `flacso_homepage_sections` y `flacso_main_page_component_files` | Registro de secciones en `modules/main-page/`, con aportes desde archivos `includes/homepage.php` de los dominios. |
| `fc_submit_consulta` y `flacso_enviar_consulta` | Entrada de formulario general y acción de consulta académica, respectivamente. Seguí los handlers descritos en la [guía técnica](desarrollo/plugin.md#consultas-correo-y-tracking). |
| `flacso_inquiry_delivery_cron` | Worker de consultas en `modules/consultas/`. Usá el [runbook](operations/consultas-transaccionales.md) para operación. |
| `flacsoMetaTrackCustom` y `data-flacso-preinscription-cta` | Contrato de analítica del plugin y consumidores del tema, respectivamente. |

Para endpoints, usá [API.md](../API.md) como índice y confirmá código, ruta, método, visibilidad y permisos en el controlador activo. Para datos, seguí registros y repositorios y usá fixtures existentes. Para conocer el permalink de una entidad, preferí el resolver de WordPress o el del catálogo según el contrato.

Antes de entregar, ejecutá `python scripts/agent_check.py --diff` durante la revisión y `python scripts/agent_check.py` para validar el conjunto. Adjuntá los comandos y lo que faltó verificar en una instalación de WordPress.
