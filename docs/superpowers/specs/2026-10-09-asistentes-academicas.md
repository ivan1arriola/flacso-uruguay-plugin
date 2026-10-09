# Alcance académico para asistentes

## Objetivo

Dar a las asistentes académicas una interfaz útil para su trabajo diario sin
darles capacidades de publicación ni acceso a la administración técnica de
WordPress.

## Versión de entrega

Esta es una actualización mayor. La entrega terminada se publica como
`8.0.0`, actualizando en el mismo commit la cabecera `Version:` y la constante
`FLACSO_URUGUAY_VERSION` de `flacso-uruguay.php`. La planificación no cambia
la versión `7.0.0` actualmente desplegada.

## Decisiones funcionales

- Las asistentes pueden editar cualquier perfil de `Personas / Equipo`.
- Las asistentes pueden consultar toda la bandeja y las gráficas de
  `Consultas`, incluidos los detalles necesarios para atenderlas.
- Las asistentes no pueden exportar datos de consultas ni ejecutar acciones
  operativas sobre comunicaciones: reintentar correos, reintentar Mautic,
  disparar seguimientos ni ocultar campañas.
- Cada asistente sólo puede ver y editar las ofertas académicas y los
  seminarios que un administrador le haya asignado.
- Una cohorte hereda el alcance de su oferta. Una edición hereda el alcance de
  su seminario.
- Las asistentes no pueden crear, publicar, eliminar, clonar ni enviar a la
  papelera ofertas o seminarios.
- Pueden crear y editar cohortes o ediciones únicamente desde una oferta o un
  seminario que tengan asignado. El selector de entidad madre sólo muestra
  elementos asignados y el servidor rechaza cualquier otro identificador.
- Administradores y editores conservan el acceso académico completo actual.
- El usuario administrador es el único que publica y administra usuarios,
  ajustes e integraciones.

## Administración del alcance

Un administrador gestiona, desde el perfil de cada usuario con el rol
`asistente_academica`, dos listas de asignación:

- Ofertas académicas autorizadas.
- Seminarios autorizados.

Las asignaciones se guardan como metadatos del usuario con identificadores
enteros saneados, sin duplicados. Un usuario sin asignaciones no visualiza ni
puede editar ofertas, cohortes, seminarios o ediciones.

## Pantalla inicial

El resumen de una asistente muestra únicamente el catálogo que tiene asignado:

- métricas de sus ofertas, cohortes, seminarios y ediciones;
- accesos a `Personas / Equipo` y `Consultas` como áreas compartidas;
- próximos comienzos de cohortes y ediciones asignadas;
- recorridos para gestionar sus ofertas y sus seminarios.

No se altera la pantalla ni los permisos de administradores y editores.

## Seguridad y privacidad

La interfaz no es el control de seguridad. El alcance se comprueba en el
servidor para listados, edición por URL, acciones masivas, creación de
entidades dependientes y modificación de su relación madre. Consultas conserva
la capacidad de lectura separada de las acciones que cambian datos o disparan
comunicaciones.
