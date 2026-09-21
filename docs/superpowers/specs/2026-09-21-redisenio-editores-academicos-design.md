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
- Oferta Académica contiene identidad y contenido estable.
- Cohorte pertenece a una Oferta y concentra comienzo, estado, cursado,
  preinscripción y la Tabla de Aranceles elegida.
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
