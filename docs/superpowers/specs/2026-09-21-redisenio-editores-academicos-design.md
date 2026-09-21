# Rediseño de editores académicos

## Objetivo

Hacer que el equipo pueda localizar y modificar cualquier dato cotidiano de
Programa Académico, Oferta Académica, Cohorte y Tabla de Aranceles con rapidez,
sin introducir un asistente secuencial ni modificar el contrato académico.

## Alcance y límites

- Se rediseñan los editores administrativos existentes de los cuatro CPT.
- Las secciones usan el mismo patrón visual: encabezado accionable, resumen de
  contenido, ayuda breve y cuerpo plegable.
- Se preservan las claves de metadatos, los permisos, los nonce y los flujos de
  guardado existentes, salvo el nuevo metadato de colores de Oferta.
- No se alteran las rutas públicas, los endpoints REST, las relaciones entre
  entidades, ni se ejecutan migraciones.
- Los cambios anteriores de Cohorte (solo comienzo, previsualización y enlaces
  al padre) permanecen dentro del rediseño.

## Modelo académico que la interfaz debe respetar

- Programa Académico agrupa y presenta sus ofertas.
- Oferta Académica contiene identidad, contenido y equipos estables.
- Cohorte pertenece a una Oferta y concentra comienzo, estado, cursado,
  preinscripción, equipos variables y la Tabla de Aranceles elegida.
- Tabla de Aranceles es reutilizable y solo se vincula desde Cohorte o Edición
  de Seminario; no se asigna directamente a una Oferta.

## Patrón de interacción común

Cada editor tendrá una cabecera breve con el título, enlace público cuando
corresponda y un resumen de sus relaciones. Debajo, cada grupo de campos se
renderiza como un `details` accesible con:

1. icono, título y descripción de una línea;
2. indicador de completitud o resumen de los valores relevantes;
3. controles “Abrir todas” y “Cerrar todas”;
4. foco visible, uso sin ratón y adaptación a pantallas angostas.

No se impone un orden de completado: todas las secciones pueden editarse en
cualquier momento y se guardan con el botón nativo de WordPress.

## Estructura por editor

### Programa Académico

- **Identidad y contacto:** título, correo y orden de aparición.
- **Presentación:** descripción rica destinada a la página pública.
- **Coordinación:** integrantes, rol y acciones para agregar o quitar filas.
- **Ofertas vinculadas:** listado de ofertas del programa, con estado de sus
  cohortes, enlace público, edición y alta de una oferta nueva.

### Oferta Académica

- **Datos generales:** tipo, programa, datos breves y color principal.
- **Presentación y objetivos:** contenido editorial de la propuesta.
- **Cursado y aprobación:** duración, carga, créditos y condiciones.
- **Perfiles y requisitos:** ingreso, egreso y requisitos.
- **Titulación, acreditación y financiación:** datos institucionales.
- **Plan de estudios:** malla, variantes y relaciones académicas.
- **Equipos estables:** Coordinación académica y otros grupos propios de la
  oferta que no cambian entre cohortes.
- **Contacto de la carta:** selector limitado a integrantes de Coordinación
  académica, con título y correo específicos de la Oferta.
- **Reconocimientos y visualización:** indicadores específicos de publicación.
- **Cohortes:** listado operativo, alta y edición de aperturas temporales.

### Cohorte

- **Oferta padre:** selector y enlaces separados a la página pública y a la
  edición de la Oferta.
- **Estado y aranceles:** estado académico y Tabla de Aranceles asignada.
- **Comienzo:** precisión día/mes/año, año, fecha exacta y previsualización
  pública en vivo. No se solicitan ni muestran fechas de fin.
- **Cursado:** modalidad, instancias presenciales y calendario.
- **Preinscripción:** apertura/cierre, enlace, mensajes y fechas propias de la
  inscripción.
- **Equipos de la cohorte:** uno o más grupos cuyos integrantes cambian para
  esta apertura, sin duplicar la Coordinación académica estable.
- **Enlaces útiles:** URL de preinscripción y acceso público relacionados.

### Tabla de Aranceles

- **Identificación:** título, tipo interno y visibilidad de USD.
- **Filas de precios:** conceptos, UYU, USD, fila principal y acciones de alta,
  eliminación y estado vacío.
- **Nota:** aclaraciones publicables.
- **Usos vinculados:** cohortes o ediciones que utilizan la tabla, con enlaces
  seguros de consulta y edición.

## Color de Oferta Académica

Se agregará un metadato único, serializado y opcional llamado
`colores_presentacion`. Tendrá este formato desde su primera versión:

```php
[
    'principal' => '#0057a8',
    'secundarios' => [],
]
```

La interfaz inicial permite elegir o limpiar solo `principal`, validado como
color hexadecimal de seis dígitos. `secundarios` se conserva siempre como una
lista vacía. Una evolución futura podrá admitir hasta dos valores secundarios
sin migrar las ofertas ya creadas. El color se previsualiza junto al selector y
no cambia aún la representación pública: su consumo público requiere una
decisión y un trabajo posterior explícitos.

## Equipos y contacto de carta

El metadato estable actual `equipo_academico` evolucionará de un único grupo
genérico a una lista de grupos de Oferta, conservando los miembros, roles,
correos y descripciones existentes. La lista podrá tener exactamente un grupo
con tipo `coordinacion_academica` y cualquier cantidad de grupos estables
adicionales con nombre libre. Los datos históricos se muestran inicialmente
como el grupo personalizado “Equipo académico”: no se los presume Coordinación
académica ni se los reclasifica automáticamente. La interfaz siempre muestra
la Coordinación académica primero cuando existe y permite agregar, editar o
eliminar los demás grupos sin alterar los equipos de Cohorte.

Los grupos de Cohorte continúan en su metadato `equipos`: pertenecen solo a la
apertura y pueden ser varios. No se copian ni se sincronizan con los equipos
estables de Oferta.

El contacto de la carta se mantiene en la Oferta mediante las claves canónicas
`carta_contacto_docente_id`, `carta_contacto_titulo` y
`carta_contacto_correo`. Al guardar, una nueva selección debe ser integrante de
Coordinación académica; si no lo es, el editor muestra un error y conserva el
contacto previamente guardado. El archivo remoto de contacto por Cohorte no se
carga ni forma parte de este diseño porque modela incorrectamente un dato
estable como variable.

## Manejo de datos y errores

- Un valor ausente se muestra como “Sin completar”, sin fabricar valores.
- Las secciones continúan siendo editables cuando falten relaciones o datos.
- Los selectores vacíos y las listas sin filas tienen estados vacíos claros.
- La eliminación de filas conserva la regla actual de no dejar sin la fila
  mínima requerida cuando aplique.
- El guardado conserva los sanitizadores y validaciones de cada entidad; el
  color inválido se descarta en vez de persistirse.

## Pruebas y validación

- Pruebas de contrato para la presencia de secciones, enlaces, accesibilidad y
  controles comunes en cada editor.
- Pruebas funcionales del sanitizador y persistencia de `colores_presentacion`.
- Pruebas de compatibilidad para que el equipo único histórico se conserve como
  grupo estable personalizado sin reclasificarse automáticamente.
- Pruebas que impidan guardar un contacto de carta fuera de Coordinación
  académica y que permitan contactos válidos de cualquiera de sus integrantes.
- Pruebas de regresión que comprueben que Cohorte no vuelve a pedir ni renderiza
  fecha de fin y que el texto público solo refleja el comienzo.
- Suite PHP completa, control de codificación, `php -l` de todos los archivos y
  `git diff --check`.
- Revisión visual local del alta y edición de los cuatro CPT antes de publicar.

## Fuera de alcance

- Migraciones o modificaciones masivas de datos existentes.
- Cambios a la web pública basados en el color.
- Cambios a Edición de Seminario, salvo que se solicite una fase equivalente.
- Publicación, despliegue o escritura en producción.
