# Guía técnica de FLACSO Uruguay Plugin

Este plugin concentra las reglas y datos institucionales de WordPress. Empezá por la [guía de desarrollo de ambos repositorios](README.md) y por [AGENTS.md](../../AGENTS.md). Esta página permite rastrear una tarea hasta las clases, interfaces y pruebas que le corresponden.

## Arranque y módulos activos

WordPress carga `flacso-uruguay.php`. La carga principal registra componentes de núcleo y llama a `FLACSO_Uruguay_Plugin::load_modules()`. `includes/core/loader.php` incluye `modules/<dominio>/init.php` en el orden declarado, con control de módulos ya cargados. Los hooks de activación y desactivación refrescan las reglas de rewrite. Verificá las rutas al modificar registros o jerarquías. La presencia de una carpeta, clase o `init.php` en el árbol no demuestra que se ejecute.

| Dominio | Entrada a revisar | Qué gobierna |
|---|---|---|
| Núcleo y administración | `modules/core/init.php`, `includes/core/` | Configuración transversal, navegación administrativa, componentes técnicos y recursos compartidos. En `class-flacso-admin-menu.php` y `class-flacso-admin-panel.php`, rastrear registro real y destino del menú. |
| Consultas | `modules/consultas/init.php`, `modules/consultas/services/`, `includes/database/` | Bandeja, servicios de ingreso, persistencia PostgreSQL y entregas transaccionales. |
| Personas y organización | `modules/docentes/`, `modules/autoridades/` | Perfiles, roles, red de personas, autoridades, bloques y edición administrativa de cada dominio. |
| Seminarios | `modules/seminarios/includes/class-seminario-cpt.php`, `class-edicion.php`, `class-seminario-integrado.php` | Seminario, edición temporal y composición de seminarios integrados. |
| Eventos y convenios | `modules/eventos/`, `modules/convenios/` | Tipos, campos, reglas y componentes de cada dominio. |
| Oferta académica | `modules/oferta-academica/includes/` | Programa, oferta, cohorte, tabla de precios, catálogo, API académica, textos de Carta y administración académica. |
| Formularios, mailing y FAQ | `modules/formularios/`, `modules/mailing/`, `modules/preguntas-frecuentes/` | Contacto general, suscripción/comunicaciones y edición/render de preguntas y respuestas. |
| Preinscripciones | `modules/preinscripciones/includes/` | Catálogo REST para el portal externo, configuración, caché y serialización. |
| Portada | `modules/main-page/includes/` | Ajustes y bloques editoriales, campañas, consumo de secciones aportadas por otros módulos. El tema compone la portada pública. |

Orden de carga verificado de `load_modules()`: core, consultas, docentes, autoridades, seminarios, eventos, convenios, oferta-academica, formularios, mailing, preguntas-frecuentes, preinscripciones y main-page. Mantené las dependencias y la ubicación por dominio. `modules/academico/` existe como carpeta y queda fuera de esa lista. Antes de reutilizar una clase de esa carpeta, buscá su llamador o inclusión explícita.

## Datos, API y permisos

### Modelo académico

- **Programa académico** agrupa ofertas y aporta contexto institucional. **Oferta académica** define una propuesta. **Cohorte** representa su ocurrencia temporal, incluida su fecha y estado. Su etiqueta se deriva del número de cohorte.
- **Seminario** es otra propuesta. **Edición de seminario** representa su ocurrencia temporal. Un seminario integrado tiene reglas de composición y su relación con componentes se resuelve desde el módulo.
- **Tabla de precios** centraliza aranceles. Cohorte y edición referencian la tabla. Seguí las clases de entidad para constantes, claves y normalización. El catálogo alimenta a la ficha, Carta y listados del tema mediante su adaptador.
- En `modules/oferta-academica/includes/`, rastreá `class-academic-repositories.php` → `class-academic-catalog.php` → `class-academic-api.php` o adaptador del tema. La definición de campos vive en las clases del dominio y sus registros. La página [modelo académico](../modelo-academico-final.md) explica relaciones y DTO. Los [contratos de reconocimientos y coordinación](../oferta-academica-publica-reconocimientos-y-coordinacion.md) explican casos de presentación y equipos.

### REST y administración

Empezá por [API.md](../../API.md), luego localizá el `register_rest_route()` real. Confirmá método, callback, `permission_callback`, esquema, validación del ID y forma de respuesta. Probá operación y permisos por separado, incluida lectura anónima, modificación por el rol autorizado y visibilidad de borradores o recursos ajenos cuando aplique. Para pantallas, seguí el registro de admin, capacidad y handler de guardado. Un nonce cubre la intención de la solicitud. La capacidad y el contexto del recurso gobiernan autorización.

`modules/main-page/includes/class-flacso-main-page-rest-api.php` registra la configuración de portada en `/main-page` y su alias `/main-page/settings`. Revisá `class-flacso-main-page-settings.php`, `class-flacso-main-page-blocks.php` y `class-flacso-home-campaign.php` para editor, estado y archivos de portada. Las secciones registradas por otros dominios tienen archivos `includes/homepage.php`. Si se toca el hero, verificá el dato en la configuración guardada, la prioridad de campaña y los respaldos en `inc/home.php` del tema. Los paneles de WordPress son el lugar de administración vigente. Tratá los documentos de retirada del editor externo como antecedentes.

### Preinscripción

`modules/preinscripciones/includes/class-preinscriptions-rest.php` registra `/flacso/v1/preinscripciones` y se apoya en el serializador y la capa de caché del módulo. `/flacso/v1/preinscripciones/catalogo` pertenece al API académico. Para apertura y cierre desde el panel, seguí `modules/oferta-academica/includes/class-preinscription-ajax-handlers.php`. Confirmá el contrato y permisos de cada ruta en su propio controlador. Probá caché, estados y destinos. La postulación ocurre en el portal externo y el tema conserva el marcador de CTA para tracking.

## Consultas, correo y tracking

| Flujo | Punto de entrada | Diagnóstico |
|---|---|---|
| Contacto general | `modules/formularios/includes/form-render.php` → `form-handlers.php` (`fc_submit_consulta`) → `general-inquiry.php`. | Revisar validación, política de errores, confirmación y avisos/acuse de Mautic con una dirección ficticia. |
| Consulta académica de oferta | Renderer `flacso_consultas_render_form()` y acción AJAX `flacso_enviar_consulta` en `modules/main-page/includes/flacso-consultas.php`. | Seguir hacia el servicio en `modules/consultas/`. Confirmar asociación a oferta/cohorte, persistencia, estado de entrega y respuesta pública de la acción. |
| Entregas transaccionales | `modules/consultas/init.php`, `modules/consultas/services/`, `includes/database/`, `includes/integrations/`. | La conexión PostgreSQL del plugin requiere `pdo_pgsql` para esa funcionalidad. Revisar repositorio, worker y cron conforme al [runbook de consultas transaccionales](../operations/consultas-transaccionales.md). Distinguir persistencia y entrega. |
| Mailing y Mautic | `modules/mailing/` y `includes/integrations/`. | Usar [contrato de datos](../mautic/data-contract.md), [operación](../mautic/operations.md) y fixtures. Preservar campos, etiquetas, permisos y límites del proveedor. |
| Tracking | `includes/core/`, incluida la cola en `class-flacso-meta-event-queue.php`, y consumidores de `window.flacsoMetaTrackCustom`. | Mapear origen, nombre y deduplicación del evento. El trazado de un clic de CTA es distinto del estado persistido de una consulta. Usar la configuración de prueba del entorno y revisar el contrato del evento. |

Para pruebas de comunicación usá transporte de prueba, formularios y datos ficticios, y los ejemplos de los contratos. Las operaciones de esquema, replay o conciliación pertenecen a los runbooks y al entorno autorizado. La ausencia de correo por sí sola no identifica un fallo de persistencia.

## Verificación local

Desde la raíz, con Python 3 y PHP CLI en PATH:

```bash
python scripts/agent_check.py --diff
python scripts/agent_check.py
git diff --check
```

`--diff` limita la comprobación de codificación y lint a los archivos detectados por Git, y ejecuta F0 y contratos PHP si detecta cambios y PHP está disponible. La corrida completa comprueba el árbol según las exclusiones del script. `--quick` omite los contratos PHP y conserva F0. Un resultado sin detección del binario PHP deja lint y contratos PHP pendientes. Leé el resumen de ambas, revisá cualquier SKIP y guardá resultados antes de commitear. El workflow [Repository Guard](../../.github/workflows/encoding-check.yml) cubre codificación y tests relevantes para cambios al código. [PHP Lint](../../.github/workflows/php-lint.yml), [F0](../../.github/workflows/f0-baseline.yml), [Preinscripciones](../../.github/workflows/preinscriptions-contract.yml) e [Inventario](../../.github/workflows/plugin-inventory.yml) tienen propios filtros y disparadores. La lista efectiva de checks se verifica en el PR.

| Tarea | Tests PHP CLI de partida, bajo `tests/` | Prueba de integración |
|---|---|---|
| Campos, relaciones y API académica | `academic-final-model-test.php`, `weak-entities-contract-test.php`, `academic-api-routes-test.php`, `academic-api-permissions-test.php`, `rest-public-visibility-test.php` | Crear y editar fixture desde su interfaz normal. Confirmar lectura por rol y consumo en el tema. |
| Estado y catálogo de preinscripción | `preinscriptions-rest-contract-test.php`, `preinscriptions-cache-contract-test.php`, `preinscription-local-opening-test.php` | Abrir, cerrar y volver a cargar catálogo y ficha. Inspeccionar el destino sin iniciar una postulación real. |
| Portada y administración | `homepage-builder-render-test.php`, `home-campaign-test.php`, `unified-admin-menu-test.php`, `settings-ownership-test.php` | Editar ajuste o campaña con su rol, comprobar el resultado y volver al valor anterior. |
| Formularios y entregas | `general-inquiry-handler-test.php`, `formularios-spam-detection-test.php`, `inquiry-handler-wiring-test.php`, `inquiry-delivery-cron-test.php`, `inquiry-delivery-worker-test.php` | Enviar solo en el entorno aislado, con transporte de prueba. Confirmar una entrada y el comportamiento de reintentos. |
| Compatibilidad del editor | `editor-externo-retirado-test.php`, `academic-workspace-admin-contract-test.php` | Confirmar menú y operación en WordPress con una capacidad existente. |

Para un contrato individual, por ejemplo `php tests/academic-api-permissions-test.php`. Los contratos CLI usan sus propios stubs. Verificá también WordPress instalado para pantallas, permisos efectivos, URLs, hooks y el sistema de entrega.

## Entrega

El [workflow de despliegue](../../.github/workflows/deploy-plugin.yml) es la fuente del disparador y el empaquetado actual. Entregá un PR con pruebas, versiones y compatibilidad con el tema. Si hay cambio en persistencia, cacheado, integración o cron, documentá modo de prueba y restauración, y enlazá el runbook pertinente. Aplicá las reglas del [repositorio](../../AGENTS.md) también a las herramientas auxiliares.
