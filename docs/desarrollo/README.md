# Guía de desarrollo y trabajo con agentes de FLACSO Uruguay

Esta es la entrada común para mantener el sitio WordPress de FLACSO Uruguay. Usala para decidir dónde intervenir, preparar una instalación de prueba y entregar cambios que otra persona o agente pueda reproducir. La documentación describe el tema hijo y el plugin de plataforma. El portal de preinscripciones y los proveedores de comunicación aparecen como límites de integración.

## Repositorios y responsabilidades

| Capa | Responsabilidad | Referencia |
|---|---|---|
| Plataforma institucional | `flacso-uruguay-plugin` registra tipos de contenido, metadatos, taxonomías, paneles, REST, formularios, consultas, integraciones y reglas académicas. | [Guía técnica del plugin](plugin.md) |
| Presentación pública | `flacso-theme-kadence` se instala como `kadence-child-flacso`. Renderiza plantillas, organiza CSS/JS y adapta los datos del plugin. | [Repositorio del tema y sus guías](https://github.com/ivan1arriola/flacso-theme-kadence) |
| Diseño global | Kadence aporta el Personalizador y los constructores cuando el modo nativo está activado. WordPress aporta el editor de bloques para el contenido que cada plantilla renderiza. | [Arquitectura del tema hijo](https://github.com/ivan1arriola/flacso-theme-kadence/blob/main/ARCHITECTURE.md) |
| Preinscripción | El plugin publica ofertas y ocurrencias, decide apertura y resuelve destinos. El portal externo recibe postulaciones. | [Modelo académico](../modelo-academico-final.md) |
| Comunicación y medición | El plugin concentra consultas, correo mediante Mautic e instrumentación. Los renderers del tema usan los contratos del plugin. | [Consultas transaccionales](../operations/consultas-transaccionales.md) · [Contrato Mautic](../mautic/data-contract.md) |

## Preparar el entorno

1. Leé `AGENTS.md` del repositorio de destino antes de intervenir. Si el trabajo cruza ambos repositorios, leé los dos. Para UI/UX consultá también los [paquetes vigentes](../auditoria-ui-ux/TAREAS-CI.md).
2. Prepará worktrees o clones separados. Para reproducir los contratos de Carta, colocá las copias de **código fuente** del plugin y el tema como hermanas. La prueba del tema busca el directorio hermano `flacso-uruguay-plugin`.
3. En una instalación descartable de WordPress o staging autorizado, instalá el padre `kadence`, el hijo `kadence-child-flacso` y el plugin `flacso-uruguay-plugin`. El tema declara WordPress 6.2+ y el plugin 6.0+, ambos PHP 7.4+. Para reproducir una incidencia, registrá las versiones que realmente corren en el entorno, los SHA de ambos repos y el modo de cabecera/pie. Los nombres de carpetas de los checkouts pueden diferir de los slugs de instalación.
4. Usá datos ficticios. Como mínimo cargá un programa, una oferta con cohorte abierta, una oferta cerrada o sin cohorte, un seminario con edición, una tabla de precios, docentes, una entrada, una FAQ y un contacto de prueba. Para formularios e integraciones usá transporte y servicios de prueba. Separá la prueba anónima, la editorial y la de diseño según las capacidades de la instalación.
5. Probá enlaces permanentes y rutas tras activar los componentes. Si una ruta virtual falla, inspeccioná reglas y query vars antes de regenerarlas. Guardá los cambios de ajustes y menús del entorno si son parte de la prueba. En producción rigen las autorizaciones de `AGENTS.md`.

## Encontrar el punto de cambio

| Pedido | Primer lugar | Qué comprobar al cruzar repositorios |
|---|---|---|
| Cambiar color, tipo, ancho o botón en el sitio | Personalizador → ajuste real y CSS ganador. En el tema, `style.css`, `inc/assets.php` y CSS de la familia. | Diferenciar estilo global, acento de oferta y CSS de bloques o formularios del plugin. Probar vista previa, publicación local y render anónimo. |
| Cambiar texto, foto o campaña de portada | Plugin `modules/main-page/` para la fuente. Tema `inc/home.php` y `template-parts/home/` para composición. | Revisar prioridad de campaña, configuración guardada y respaldo. Verificar imagen ausente y campaña inactiva. |
| Cambiar qué se muestra en una ficha, listado o Carta | Tema `inc/academic-public-model.php`, plantilla y tests correspondientes. | Trazar la clave hasta `FLACSO_Academic_Catalog` y confirmar la misma cohorte/edición en las superficies pertinentes. |
| Cambiar apertura, fecha, precio, equipo o relación | Plugin `modules/oferta-academica/` o `modules/seminarios/`, registro de la entidad y repositorio. | Mantener contrato público de DTO, destino externo y cobertura para datos ausentes, borradores y cierre. |
| Cambiar una consulta, FAQ o contacto | Formularios, Consultas o FAQ del plugin para datos y comportamiento. Tema para la página pública, sus wrappers y ajustes de Contacto. | Identificar renderer y handler exactos. Probar con formulario ficticio en staging, revisar foco y contar una confirmación por operación. |
| Cambiar un evento de analítica | Plugin `modules/core/` y su contrato de tracking. | Localizar los consumidores del tema, preservar marcadores, semántica de eventos y deduplicación. |
| Cambiar un menú de administración o endpoint REST | `init.php` del dominio, registro de pantalla o controlador del plugin. | Verificar registros, capacidades por operación, respuestas y los llamadores reales. |
| Revisar una URL inexistente o plantilla equivocada | WordPress y reglas del plugin/tema. | Comprobar HTTP, canonical, query vars, plantilla, título, robots y recursos. Formación, Contactos y Carta tienen despacho especial. |

## Método de trabajo

1. **Reproducí y acotá.** Registrá ruta o pantalla, rol, estado de los datos y versión. Trazá la ejecución desde el hook o ruta hasta el renderer y sus tests. Las búsquedas por literal orientan. Para concluir qué código se ejecuta, comprobá callbacks y condiciones.
2. **Elegí un dueño por cambio.** Los campos y reglas se modifican en la capa que los gobierna. Si cambian ambas capas, prepará PR separados y acordá compatibilidad: plugin anterior/tema nuevo, plugin nuevo/tema anterior y ambos nuevos según el cambio. El deploy del tema valida contra la rama `main` del plugin.
3. **Verificá desde la interfaz normal.** Combiná pruebas CLI de contratos con pruebas en WordPress cuando el cambio dependa de roles, encolado, Customizer, persistencia, cron, rutas o estados del navegador. Un test que busca CSS o PHP en disco da evidencia del contrato de fuente. Los screenshots y estilos calculados prueban la superficie que se abrió.
4. **Prepará la entrega.** Usá rama de trabajo y PR con alcance, rutas, comandos y resultados, evidencias antes/después cuando corresponda, contratos preservados, dependencias y reversión. Una persona revisa y mergea bajo las reglas del repositorio. Una rama documental también puede acabar en el workflow de despliegue si se mergea a `main`.
5. **Registrá límites.** Señalá casos omitidos, dependencias que causaron SKIP y pruebas que necesitan WordPress o staging. Para UI/UX tramitá hallazgos y bitácora según la [guía de subagentes](../auditoria-ui-ux/GUIA-SUBAGENTES.md).

## Si la tarea la ejecuta un agente

- La fuente de permisos y límites es `AGENTS.md` del repositorio donde se trabaja. Antes de delegar, declarale a cada agente repositorio, carpeta, límites de escritura y tarea concreta.
- En auditorías UI/UX, el rol explorador releva con archivos y líneas. Quien implementa toma un paquete acotado. Quien coordina y revisa consolida evidencia y decide si está listo para revisión humana. Usá el preámbulo y los sellos de evidencia de la guía de auditoría.
- Para operaciones locales, separá los comandos de código de los comandos que pueden afectar instalaciones o servicios. Usá datos de prueba y confirmá el entorno de destino. Evitá que formularios o scripts de integración apunten a servicios productivos durante desarrollo.
- En el traspaso incluí ruta/síntoma, estado reproducido, evidencia, archivos y tests, SHA de cada repo, estado de la rama/PR y un pendiente verificable. Ejemplo: «Ficha cerrada sin CTA de preinscripción, plugin X y tema Y. Prueba CLI pasó. Falta comprobar el enlace de consulta anónimo en staging».

## Dos recorridos de diagnóstico

### La ficha muestra una cohorte distinta de la Carta o del formulario

1. Anotá ID de oferta, cohorte esperada, visibilidad y estado de inscripción usando un fixture. Compará con los datos que muestra cada superficie.
2. En el plugin, seguí entidad → repositorio → catálogo y confirmá la cohorte pública que se devuelve. Una cohorte histórica abierta no debe aportar su CTA a una cohorte posterior cerrada.
3. En el tema, seguí el contexto de `inc/academic-public-model.php` hasta ficha y Carta. Revisá el contexto que el renderer de consulta recibe en su camino normal.
4. Ejecutá `php tests/academic-latest-public-cohort-test.php` en el plugin y `php tests/academic-public-model-test.php` en el tema. Repetí el escenario en WordPress con inscripción abierta, cerrada y sin cohorte publicada.

### Un cambio del Personalizador aparece en una página y falta en otra

1. Identificá la URL, el tema que eligió la plantilla y el modo de cabecera/pie. Guardá versión del padre, controles afectados y valor computado de ambos elementos.
2. Seguí el registro de recursos hasta el stylesheet cargado. Compará el control, su variable emitida y la declaración que gana. Identificá reglas del plugin, excepciones editoriales y acentos académicos.
3. Cambiá un ajuste desde su interfaz real en staging. Compará vista previa, resultado anónimo tras publicar y estado restaurado. Si también debe reflejarse en el editor, probá el bloque bajo el rol editorial.
4. Al entregar, enlazá la ruta, captura y propiedad verificadas. La [línea base](../auditoria-ui-ux/baseline/2026-11-22-kadence/README.md) contiene ejemplos y un sondeo de variables útil para localizar la capa que interviene.

## Lecturas según el trabajo

- [Referencia rápida para agentes](../agent-reference.md) y [reglas del plugin](../../AGENTS.md).
- [Modelo académico](../modelo-academico-final.md), [API del plugin](../../API.md) y [seminarios integrados](../seminarios-integrados.md).
- [Protocolo F0 en staging](../architecture/f0-staging-protocol.md), [operación de consultas](../operations/consultas-transaccionales.md) y [contrato Mautic](../mautic/data-contract.md).
- [Línea base y plan T3 de Kadence](../auditoria-ui-ux/baseline/2026-11-22-kadence/README.md). El plan identifica mejoras futuras y pruebas que todavía requieren staging.
