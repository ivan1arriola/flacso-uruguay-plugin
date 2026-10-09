# Rol Gestión web

## Propósito

`Gestión web` es un rol operativo para mantener información institucional y
académica. No es un rol de publicación, configuración técnica ni administración
de WordPress.

## Versión y migración

La entrega se publica como `8.0.0`, actualizando en el mismo commit la
cabecera `Version:` y `FLACSO_URUGUAY_VERSION` de `flacso-uruguay.php`.

El rol visible se llama **Gestión web** y su identificador técnico nuevo es
`gestion_web`. Durante la actualización, las personas que hoy tienen el rol
`asistente_academica` pasan a `gestion_web`, conservan sus capacidades vigentes
que correspondan al nuevo alcance y reciben asignaciones vacías de catálogo.
El rol antiguo se retira únicamente después de completar esa migración.

## Acceso permitido

| Área | Alcance |
|---|---|
| Personas / Equipo | Ver y editar todos los perfiles existentes, incluidos CV, datos, imágenes y revisiones. No crear ni eliminar perfiles. |
| Consultas | Ver toda la bandeja, el detalle y las gráficas. No exportar datos ni ejecutar acciones que envíen, reintenten, sincronicen u oculten información. |
| Ofertas académicas | Ver y editar sólo las asignadas a la persona. No crear, publicar, eliminar, clonar ni enviar ofertas a la papelera. |
| Cohortes | Ver y editar las cohortes de ofertas asignadas. Crear una cohorte sólo desde una oferta asignada. |
| Seminarios | Ver y editar sólo los asignados a la persona. No crear, publicar, eliminar, clonar ni enviar seminarios a la papelera. |
| Ediciones | Ver y editar las ediciones de seminarios asignados. Crear una edición sólo desde un seminario asignado. |

## Acceso reservado

Sólo administradores pueden publicar, eliminar, restaurar, administrar usuarios
o roles, cambiar ajustes, gestionar integraciones, operar Mautic, exportar
Consultas y modificar campañas. Los editores existentes conservan su acceso
académico completo actual.

## Asignación de catálogo

Un administrador asigna, desde el perfil de cada persona con rol Gestión web,
dos listas independientes:

- Ofertas académicas autorizadas.
- Seminarios autorizados.

Las listas se guardan como metadatos de usuario con identificadores enteros,
existentes y sin duplicados. Una cohorte hereda el alcance de
`oferta_academica_id`; una edición hereda el de `seminario_id`. Sin asignaciones,
la persona no puede listar ni abrir ningún elemento del catálogo restringido.

Las comprobaciones se realizan en el servidor para el listado, la edición por
URL, las acciones masivas, la creación de entidades dependientes y el cambio de
su entidad madre. Ocultar un botón nunca es el único control.

## Inicio de Gestión web

El inicio muestra trabajo relevante para la persona conectada:

- métricas de sus ofertas, cohortes, seminarios y ediciones;
- próximos comienzos de cohortes y ediciones asignadas;
- recorridos hacia sus ofertas y sus seminarios;
- accesos globales a Personas / Equipo y Consultas.

El aviso administrativo dentro del encabezado debe mantener contraste legible.
No se alteran la pantalla ni los permisos de administradores y editores.
