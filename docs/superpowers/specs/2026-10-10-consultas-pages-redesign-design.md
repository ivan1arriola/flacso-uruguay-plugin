# Rediseño de la plataforma de Consultas como páginas operativas

**Estado:** diseño aprobado en conversación; pendiente de revisión del documento y plan de implementación.

## Objetivo

Reemplazar la página única de Consultas, que hoy alterna seis vistas mediante `?tab=`, por un submenú de páginas independientes dentro de FLACSO. El resultado debe reducir la carga visual, hacer que cada tarea tenga una URL estable y conservar los contratos existentes con PostgreSQL, Mautic, exportación CSV, acciones AJAX y permisos.

La plataforma deja de presentarse como una pantalla institucional con una gran cabecera azul. Cada página se presenta como una herramienta de trabajo: título, descripción breve, indicadores solo cuando ayudan a decidir, filtros propios y el resultado principal.

## Alcance

Se crean estas seis páginas bajo el menú FLACSO:

| Página | Slug previsto | Propósito | Acción principal |
| --- | --- | --- | --- |
| Bandeja | `flacso-consultas` | Revisar, buscar y operar consultas individuales. | Ver detalle; reintentos autorizados. |
| Resumen por oferta | `flacso-consultas-resumen` | Comparar volumen y estado por oferta o seminario. | Ajustar período. |
| Oferta y país | `flacso-consultas-oferta-pais` | Analizar distribución geográfica. | Ajustar período y país. |
| Comparación | `flacso-consultas-comparacion` | Comparar dos períodos. | Comparar períodos. |
| Campañas | `flacso-consultas-campanas` | Consultar atribución de campañas. | Ajustar período. |
| Exportar CSV | `flacso-consultas-exportar` | Descargar el conjunto seleccionado. | Exportar CSV. |

No se incluye una página de configuración en esta fase. Tampoco se modifican esquemas de base de datos, reglas de entrega, contratos Mautic ni el rol Gestión web.

## Navegación y rutas

`FLACSO_Consultas_Admin::register_menu()` registrará una entrada por página usando `add_submenu_page`. La Bandeja conservará `flacso-consultas` como slug para no romper enlaces administrativos existentes. Cada submenú reutilizará `VIEW_INQUIRIES`; Exportar CSV seguirá verificando `MANAGE_INQUIRIES` dentro de su renderizador y controlador.

Los enlaces de acciones rápidas, formularios y restablecimiento de filtros usarán el slug de la página actual. Se elimina `tab` de URLs nuevas. Durante la transición, una URL antigua con `page=flacso-consultas&tab=<nombre>` redirige de manera segura al slug equivalente, conservando únicamente los parámetros compatibles de filtro. Esto evita enlaces rotos y permite retirar la interfaz de pestañas.

## Patrón visual común

Cada página sigue esta secuencia:

1. Cabecera blanca: título, descripción de una línea y acción principal cuando exista.
2. Aviso operativo discreto, solo si el diagnóstico transaccional requiere atención.
3. Tarjetas de indicadores compactas, solo en páginas analíticas.
4. Barra de filtros: conjunto, fechas, filtros específicos, búsqueda cuando aplique, botón primario y enlace de limpieza condicional.
5. Resultado central: tabla, comparación o formulario de exportación.

Se reemplazan la cabecera azul y la barra de pestañas. El sistema de color se reserva para estados: verde para abierto o correcto, ámbar para pendiente o atención, rojo para fallo o bloqueo, violeta para estados de Mautic y gris para información neutral. Las tablas mantienen encabezados claros, densidad legible, estados como chips y acciones alineadas a la derecha.

## Comportamiento por página

### Bandeja

Conserva tabla, detalle modal, paginación, reintentos y filtros actuales. La búsqueda ocupa el lugar más visible de la barra. Los filtros de rango, tipo de consulta, oferta/seminario, país, estado de correo y estado de Mautic se conservan. El diagnóstico de entregas se muestra como aviso breve y no como parte de la cabecera de la herramienta.

### Resumen por oferta y Oferta y país

Conservan las consultas existentes de `FLACSO_Inquiry_Analytics_Repository`. Muestran indicadores del período y tabla de resultado. El selector de tabla y el rango de fechas se mantienen; Oferta y país conserva además país.

### Comparación

Conserva dos períodos, el acceso para usar los 31 días anteriores y la tabla de variaciones. Las tarjetas muestran total, Uruguay y exterior. Los colores de variación permanecen semánticos y no sustituyen valores numéricos.

### Campañas

Conserva las métricas y acciones de atribución disponibles actualmente. Sus filtros se limitan al conjunto y período, sin copiar filtros de Bandeja que no afectan el análisis.

### Exportar CSV

Conserva modo deduplicado o crudo, rango, tipo de consulta y selección de ítems. El controlador `flacso_consultas_export_csv` y sus verificaciones de permiso/nonce no cambian.

## Arquitectura de presentación

La clase administrativa se separará en métodos de página: un método de enrutamiento y cabecera compartida, más un renderizador por página. Los actuales renderizadores `render_tab_*` se podrán reutilizar tras retirar campos ocultos `tab` y reemplazar sus URLs. Las funciones de consulta, AJAX y exportación permanecen sin cambios funcionales.

Los estilos se organizan por componentes comunes y por página, evitando estilos inline que dificultan mantener las seis interfaces. La carga de datos seguirá siendo servidor-renderizada; no se incorpora un framework nuevo ni llamadas adicionales que expongan datos de consultas.

## Errores y permisos

Cada página verifica `can_view()` antes de renderizar. Exportar y operaciones mutables mantienen `can_manage()` y sus nonces actuales. Una excepción de PostgreSQL se transforma en un aviso operativo genérico sin exponer datos personales o trazas. Los resultados vacíos explican el efecto de los filtros y ofrecen limpiar filtros cuando corresponda.

## Pruebas y verificación

- Contratos de menú: seis slugs, capacidades y redirección de enlaces `tab` heredados.
- Contratos de vistas: cada página conserva los filtros, datos y acciones que ya expone.
- Pruebas existentes de Bandeja, comparación, exportación, permisos y acciones AJAX.
- Sintaxis PHP y suite completa del plugin.
- Revisión visual autenticada de las seis rutas con datos reales o de prueba, incluida Bandeja paginada y estados de acceso Gestión web.
- Publicación en `main`, CI, despliegue, activación y smoke test de WordPress verificados por separado.

## Decisiones explícitas

- No se implementa Configuración de consultas en este cambio.
- No se oculta el historial de consultas ni se alteran los datos existentes.
- No se cambian las políticas de retención, reintentos, Mautic ni los permisos de Gestión web.
