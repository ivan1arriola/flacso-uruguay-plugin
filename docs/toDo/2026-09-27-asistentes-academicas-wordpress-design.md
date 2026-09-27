# Rediseño del wp-admin para Asistentes Académicas

**Fecha:** 2026-09-27  
**Repositorio:** `flacso-uruguay-plugin`  
**Estado:** pendiente de implementación

## 1. Objetivo

Convertir el `wp-admin` actual de FLACSO Uruguay en una herramienta cómoda, segura y orientada a tareas para las Asistentes Académicas, reutilizando el modelo académico, los CPT, los editores y la infraestructura ya existente en WordPress.

La solución no debe crear una plataforma nueva ni duplicar la lógica académica en otro sistema.

La idea central es:

> WordPress sigue siendo el sistema donde viven y se editan los datos académicos; el plugin adapta permisos, navegación y experiencia de usuario para que una Asistente Académica vea únicamente las tareas que necesita.

---

## 2. Alcance de la primera versión

La V1 debe resolver exclusivamente:

1. Crear un rol específico `asistente_academica`.
2. Definir capabilities específicas para gestión académica.
3. Simplificar el menú de WordPress para ese rol.
4. Adaptar el Panel FLACSO como página de inicio de trabajo.
5. Permitir editar Ofertas Académicas existentes.
6. Permitir crear y editar Cohortes desde su Oferta.
7. Permitir crear y editar Seminarios.
8. Permitir crear y editar Ediciones desde su Seminario.
9. Permitir crear y editar Docentes.
10. Permitir asociar Docentes y equipos usando el modelo actual.
11. Mostrar un enlace externo a Preinscripciones.
12. Mostrar un enlace externo a Sala Virtual.
13. Ocultar y bloquear las áreas de WordPress que no correspondan al trabajo de una Asistente Académica.

No se deben crear nuevas APIs, sincronizaciones, bases de datos ni aplicaciones externas para esta primera etapa.

---

## 3. Principios de diseño

1. **No duplicar datos.** Oferta Académica, Cohorte, Seminario, Edición y Docente siguen viviendo en WordPress.
2. **No crear un CRUD paralelo.** Se reutilizan las pantallas nativas de edición de los CPT y los editores ya construidos en el plugin.
3. **No crear otra aplicación.** No se construye un nuevo frontend React, Django, Frappe ni similar.
4. **No integrar todavía Sala Virtual ni Preinscripciones por API.** En esta primera versión ambas aparecen únicamente como enlaces externos.
5. **Permisos reales, no sólo ocultamiento visual.** Lo que una Asistente no puede hacer debe bloquearse mediante capabilities de WordPress, no únicamente con CSS o eliminación de botones.
6. **Cohortes y Ediciones son entidades subordinadas.** Se gestionan desde Oferta Académica y Seminario respectivamente.
7. **Una sola fuente de verdad.** La interfaz administrativa y el sitio público consumen los mismos datos.
8. **El administrador conserva el wp-admin completo.** La experiencia simplificada se aplica al rol de Asistente Académica.
9. **La V1 debe ser pequeña.** El foco es permisos, navegación y comodidad de edición.
10. **No modificar el core de WordPress.** Toda la personalización debe vivir en el plugin.

---

## 4. Modelo académico que se conserva

No se modifica el modelo académico final documentado en `docs/modelo-academico-final.md`.

```text
ProgramaAcademico
├── OfertaAcademica
│   └── Cohorte
└── Seminario
    └── Edicion
```

También se conservan las relaciones actuales con `Docente`, `TablaPrecio` y equipos académicos.

En particular:

- `OfertaAcademica` y `Seminario` siguen siendo raíces distintas.
- `Cohorte` depende de una `OfertaAcademica`.
- `Edicion` depende de un `Seminario`.
- Los docentes efectivos de seminarios pertenecen a la `Edicion`.
- Los equipos académicos existentes no se sustituyen por un modelo nuevo.
- `TablaPrecio` continúa siendo una entidad reutilizable.
- WordPress no pasa a almacenar preinscripciones.

---

## 5. Nuevo rol: `asistente_academica`

Se debe crear un rol específico de WordPress.

No se debe reutilizar el rol genérico `editor`.

### 5.1. Capabilities funcionales propuestas

Los nombres concretos pueden ajustarse durante la implementación, pero deben permitir separar claramente estas acciones:

```text
flacso_access_academic_management

flacso_edit_offers

flacso_create_seminars
flacso_edit_seminars

flacso_create_cohorts
flacso_edit_cohorts

flacso_create_editions
flacso_edit_editions

flacso_create_teachers
flacso_edit_teachers
flacso_assign_teachers

flacso_view_external_services
```

### 5.2. Acciones no permitidas

Una Asistente Académica no debe poder:

- crear Ofertas Académicas;
- eliminar Ofertas Académicas;
- administrar configuración general;
- administrar integraciones;
- administrar tracking;
- administrar correo;
- administrar plugins;
- administrar temas;
- administrar usuarios;
- administrar migraciones;
- administrar APIs;
- administrar herramientas técnicas;
- abrir o cerrar preinscripciones desde WordPress.

### 5.3. Matriz inicial de permisos

| Entidad / función | Ver | Crear | Editar | Eliminar |
|---|---:|---:|---:|---:|
| Oferta Académica | Sí | No | Sí | No |
| Cohorte | Sí | Sí | Sí | No por defecto |
| Seminario | Sí | Sí | Sí | No por defecto |
| Edición | Sí | Sí | Sí | No por defecto |
| Docente | Sí | Sí | Sí | No por defecto |
| Programa Académico | Contextual | No | No en V1 | No |
| Tabla de precios | Contextual | No en V1 | No en V1 | No |
| Preinscripciones | Link | No | No | No |
| Sala Virtual | Link | No | No | No |

La posibilidad de eliminar Cohortes, Seminarios, Ediciones o Docentes se evaluará más adelante. En V1 se prioriza evitar borrados accidentales.

---

## 6. Problema actual que debe corregirse primero

Actualmente gran parte del plugin utiliza capacidades genéricas como:

```php
current_user_can('edit_posts')
```

y varios CPT utilizan las capabilities genéricas de `post`.

Esto impide expresar correctamente reglas como:

- puede editar una Oferta pero no crearla;
- puede crear una Cohorte pero no administrar otras partes del sitio;
- puede gestionar Docentes pero no herramientas técnicas;
- puede editar una Edición pero no abrir/cerrar preinscripciones.

Por lo tanto, la primera etapa técnica debe ser introducir una capa de capabilities académicas específicas y migrar gradualmente los checks relevantes desde `edit_posts`.

---

## 7. Menú para Asistentes Académicas

La navegación debe reducirse a:

```text
FLACSO Gestión

Inicio

Gestión académica
  Ofertas académicas
  Seminarios
  Docentes

Sistemas externos
  Preinscripciones ↗
  Sala Virtual ↗
```

### 7.1. Elementos que no deben aparecer

Para `asistente_academica` se deben ocultar y bloquear:

```text
Entradas
Páginas
Medios
Comentarios
Apariencia
Plugins
Usuarios
Herramientas
Ajustes

Programas Académicos
Tablas de Aranceles
Portada
Consultas
Correos
Analítica / Meta
Sistema
Integraciones
Documentación técnica
API REST
Migraciones
```

Los administradores mantienen el menú completo.

---

## 8. Inicio / Panel FLACSO

No se debe crear un dashboard nuevo desde cero.

Se reutiliza y adapta `FLACSO_Admin_Panel`.

Para una Asistente Académica debe funcionar como portada de trabajo.

### 8.1. Acciones principales

```text
[ + Nuevo seminario ]   [ + Nuevo docente ]

[ Ofertas académicas ]  [ Seminarios ]

[ Docentes ]
```

### 8.2. Sistemas externos

```text
[ Preinscripciones ↗ ]  [ Sala Virtual ↗ ]
```

URLs iniciales:

- `https://preinscripciones.flacso.edu.uy`
- `https://salavirtual.flacso.edu.uy`

No se implementa integración API en V1.

### 8.3. Información útil

El panel puede mostrar:

- próximas Cohortes;
- próximas Ediciones de Seminarios;
- ofertas sin Cohorte planificada;
- seminarios sin Edición futura;
- alertas simples de datos faltantes relevantes para asistentes.

No debe mostrar paneles técnicos de tracking, correo, sistema o integraciones.

---

## 9. Ofertas Académicas

La Asistente puede listar, buscar y editar Ofertas Académicas existentes.

No puede crear una nueva Oferta.

### 9.1. Lista

La lista debe priorizar:

- nombre;
- tipo académico;
- Cohorte vigente o próxima;
- estado;
- próximo inicio;
- acciones principales.

Ejemplo conceptual:

```text
Maestría en Educación, Sociedad y Política
Maestría
Cohorte IX · En curso
Próximo inicio: marzo 2027

[ Editar oferta ] [ Gestionar cohortes ]
```

### 9.2. Acciones a eliminar para Asistentes

- `Añadir nueva`;
- enviar a papelera;
- acciones masivas de borrado;
- edición rápida si no aporta valor;
- acciones técnicas.

La prohibición de crear debe aplicarse también a la URL directa:

```text
/wp-admin/post-new.php?post_type=oferta-academica
```

No alcanza con ocultar el botón.

### 9.3. Editor de Oferta

Se conserva el editor actual y el patrón de `FLACSO_Academic_Admin_UI`.

Debe priorizar:

```text
Información básica
Presentación
Objetivos
Equipo académico
Documentación
Cohortes
```

Las Cohortes siguen administrándose dentro del contexto de la Oferta.

---

## 10. Cohortes

Las Cohortes no deben tener un módulo principal independiente.

Se accede a ellas desde su Oferta Académica.

### 10.1. Flujo

```text
Oferta Académica
→ Cohortes
→ Editar Cohorte

o

Oferta Académica
→ + Nueva cohorte
```

Se conserva el patrón actual:

```text
post-new.php?post_type=cohorte&oferta_academica_id={ID}
```

### 10.2. Editor de Cohorte

Debe concentrarse en:

- número / identidad derivada;
- estado;
- fecha o precisión de comienzo;
- fecha de fin;
- modalidad;
- calendario;
- equipo de Cohorte;
- tabla de aranceles vinculada;
- mensajes o datos públicos aplicables.

### 10.3. Preinscripciones dentro de Cohorte

Para Asistentes se eliminan de la UI:

```text
Abrir preinscripción
Cerrar preinscripción
```

En su lugar:

```text
[ Gestionar preinscripciones ↗ ]
```

que abre:

`https://preinscripciones.flacso.edu.uy`

La capacidad de editar una Cohorte no debe implicar capacidad de abrir/cerrar preinscripciones.

Los handlers AJAX existentes también deben impedir estas operaciones para el rol de Asistente aunque se invoquen manualmente.

---

## 11. Seminarios

La Asistente puede:

- listar;
- buscar;
- crear;
- editar Seminarios.

### 11.1. Lista

Debe mostrar:

- título;
- Programa Académico;
- Edición vigente o próxima;
- fechas principales;
- acciones.

Ejemplo:

```text
Gestión en Instituciones Educativas

Programa: MESYP
Edición actual: 2026
30/09 – 28/10

[ Editar seminario ] [ Gestionar ediciones ]
```

Debe existir:

```text
[ + Nuevo seminario ]
```

### 11.2. Editor de Seminario

Se conserva el editor académico actual.

Los datos estables continúan en Seminario.

Los datos temporales siguen perteneciendo a la Edición.

No se agregan docentes directamente al Seminario si el modelo vigente indica que pertenecen a la Edición.

---

## 12. Ediciones de Seminario

Las Ediciones no deben aparecer como módulo principal independiente.

Se gestionan desde su Seminario.

### 12.1. Flujo

```text
Seminario
→ Ediciones
→ Editar Edición

o

Seminario
→ + Nueva edición
```

### 12.2. Editor de Edición

Debe priorizar:

- año / nombre;
- estado;
- fechas;
- modalidad;
- encuentros sincrónicos;
- docentes;
- ediciones componentes cuando corresponda;
- tabla de aranceles;
- datos públicos de la edición.

El selector de docentes actual debe conservarse y mejorar cuando sea necesario:

- buscador;
- lista de seleccionados;
- eliminación simple;
- ordenamiento.

### 12.3. Links externos

La pantalla puede incluir:

```text
[ Ir a Sala Virtual ↗ ]
[ Ir a Preinscripciones ↗ ]
```

Sin integración adicional en V1.

---

## 13. Docentes

El módulo de Docentes necesita simplificación para el rol de Asistente.

Actualmente existen pantallas adicionales como:

- Panel;
- Documentación;
- API REST;
- Migración.

Estas no deben mostrarse a Asistentes.

### 13.1. Experiencia deseada

```text
Docentes

[ Buscar por nombre... ]

[ + Nuevo docente ]

María Pérez
[ Editar ]

Juan Gómez
[ Editar ]
```

### 13.2. Editor

Debe mostrar solamente los datos necesarios para gestionar la persona:

- foto;
- nombre;
- apellido;
- prefijo / título;
- correo;
- información de perfil;
- CV / resumen cuando corresponda;
- demás campos públicos actualmente soportados.

Las herramientas de migración, API o diagnóstico quedan reservadas a administradores.

---

## 14. Asociación de docentes y equipos

No se crea una nueva tabla ni un nuevo modelo de relaciones.

Se reutiliza la infraestructura existente:

- `FLACSO_Academic_Team_Editor`;
- equipos estables de Oferta;
- equipos variables de Cohorte;
- docentes de Edición.

La UI debe ser simple:

```text
Equipo académico

Coordinación
María Pérez ×

Docentes
Juan Gómez ×
Ana Rodríguez ×

[ + Agregar persona ]
```

No se deben mostrar IDs internos de WordPress a la Asistente.

---

## 15. Preinscripciones

### V1

Preinscripciones es únicamente un enlace externo:

```text
Preinscripciones ↗
https://preinscripciones.flacso.edu.uy
```

No se implementa en esta etapa:

- consulta embebida;
- API de lectura;
- modificación de preinscripciones;
- sincronización;
- dashboard dentro de WordPress.

Los botones actuales de abrir/cerrar preinscripciones no deben estar disponibles para Asistentes Académicas.

---

## 16. Sala Virtual

### V1

Sala Virtual es únicamente un enlace externo:

```text
Sala Virtual ↗
https://salavirtual.flacso.edu.uy
```

No se implementa en esta etapa:

- API entre WordPress y Sala Virtual;
- creación de reuniones desde WordPress;
- sincronización de reuniones;
- integración con Zoom;
- integración con Google Meet.

Sala Virtual continúa siendo el sistema responsable de reuniones.

---

## 17. Experiencia visual de WordPress

Para el rol `asistente_academica`, WordPress debe sentirse como una herramienta llamada **FLACSO Gestión**, no como un CMS genérico.

Se puede simplificar:

- menú lateral;
- barra superior;
- accesos rápidos;
- textos;
- botones;
- acciones de lista;
- metaboxes irrelevantes;
- opciones de pantalla innecesarias.

No se debe alterar el funcionamiento normal de WordPress para administradores.

No se debe implementar seguridad mediante CSS. El CSS sólo afecta presentación; la autorización debe resolverse con capabilities.

---

## 18. Evitar formularios administrativos paralelos

No se deben crear nuevas páginas del tipo:

```text
/wp-admin/admin.php?page=flacso-editar-oferta&id=123
```

si la edición ya puede resolverse mediante:

```text
/wp-admin/post.php?post=123&action=edit
```

La personalización debe hacerse sobre las pantallas existentes mediante:

- capabilities;
- metaboxes;
- hooks;
- filtros;
- CSS;
- JavaScript;
- `FLACSO_Academic_Admin_UI`.

Esto evita mantener dos CRUD distintos sobre la misma entidad.

---

## 19. Servicios externos centralizados

Aunque inicialmente sean simples links, las URLs no deberían dispersarse por distintos archivos.

Se recomienda una pequeña abstracción, por ejemplo:

```php
FLACSO_External_Services::preinscripciones_url();
FLACSO_External_Services::salavirtual_url();
```

Valores iniciales:

```text
https://preinscripciones.flacso.edu.uy
https://salavirtual.flacso.edu.uy
```

Esto permitirá reemplazar posteriormente un simple enlace por una integración más elaborada sin modificar toda la UI.

---

## 20. Estructura propuesta para el código nuevo

No es necesario crear nuevos módulos para Oferta, Cohorte, Seminario, Edición o Docente.

Se propone una capa específica de experiencia administrativa:

```text
includes/
└── admin/
    └── academic-assistant/
        ├── class-role.php
        ├── class-capabilities.php
        ├── class-menu.php
        ├── class-dashboard.php
        ├── class-screen-policy.php
        ├── class-external-services.php
        └── assets/
            ├── assistant-admin.css
            └── assistant-admin.js
```

Esta capa se apoya en los módulos existentes:

```text
modules/
├── oferta-academica/
├── seminarios/
└── docentes/
```

---

## 21. Código histórico del Editor externo

El repositorio todavía conserva `FLACSO_Editor_Admin_Mode`, diseñado para ocultar pantallas de WordPress cuando la edición se realizaba en una aplicación externa.

Ese enfoque ya no corresponde al diseño actual.

La clase:

```text
includes/core/class-flacso-editor-admin-mode.php
```

debe revisarse durante la implementación.

Objetivo:

- eliminarla si ya no tiene consumidores reales;
- eliminar mensajes que indiquen que la edición se realiza desde la app FLACSO;
- actualizar la documentación que todavía describe al antiguo Editor como consumidor principal;
- conservar únicamente aquello que siga teniendo una función real.

No eliminar de forma ciega antes de verificar referencias y despliegue.

---

## 22. Orden recomendado de implementación

### Fase 1 — Autorización

1. Crear rol `asistente_academica`.
2. Definir capabilities académicas.
3. Adaptar CPT relevantes para capacidades específicas.
4. Migrar checks críticos de `edit_posts` a capabilities específicas.
5. Crear tests de autorización.

### Fase 2 — Navegación

1. Crear política de menú para Asistentes.
2. Adaptar `FLACSO_Admin_Panel`.
3. Agregar accesos a Preinscripciones y Sala Virtual.
4. Ocultar menús de WordPress innecesarios.
5. Redirigir el ingreso de la Asistente al Panel FLACSO.

### Fase 3 — Ofertas y Cohortes

1. Prohibir alta de Oferta.
2. Simplificar listado de Ofertas.
3. Mantener creación contextual de Cohortes.
4. Simplificar editor de Cohorte.
5. Quitar controles de apertura/cierre de preinscripción para Asistentes.

### Fase 4 — Seminarios y Ediciones

1. Permitir alta de Seminario.
2. Simplificar listado.
3. Mantener creación contextual de Ediciones.
4. Revisar editor de Edición.
5. Mantener selector de docentes y encuentros sincrónicos.

### Fase 5 — Docentes

1. Integrar Docentes dentro del menú FLACSO.
2. Ocultar Panel/API/Migración/Documentación a Asistentes.
3. Simplificar listado.
4. Simplificar formulario de alta/edición.

### Fase 6 — Limpieza

1. Revisar `FLACSO_Editor_Admin_Mode`.
2. Eliminar referencias obsoletas al Editor externo.
3. Actualizar `docs/architecture.md`.
4. Actualizar README si corresponde.
5. Agregar tests contractuales para el rol.
6. Verificar que administrador mantiene toda la funcionalidad existente.

---

## 23. Criterios de aceptación de la V1

La V1 se considera terminada cuando:

- [ ] existe el rol `asistente_academica`;
- [ ] una Asistente puede iniciar sesión normalmente en WordPress;
- [ ] al ingresar llega a FLACSO Gestión;
- [ ] no ve menús genéricos o técnicos innecesarios;
- [ ] puede listar y editar Ofertas existentes;
- [ ] no puede crear una Oferta ni entrando por URL directa;
- [ ] puede crear y editar Cohortes desde una Oferta;
- [ ] puede crear y editar Seminarios;
- [ ] puede crear y editar Ediciones desde un Seminario;
- [ ] puede crear y editar Docentes;
- [ ] puede asociar Docentes utilizando los editores existentes;
- [ ] no puede abrir/cerrar preinscripciones desde WordPress;
- [ ] dispone de un enlace a Preinscripciones;
- [ ] dispone de un enlace a Sala Virtual;
- [ ] no puede acceder a configuración, integraciones, tracking, plugins, usuarios ni herramientas técnicas;
- [ ] un administrador sigue viendo y utilizando el wp-admin completo;
- [ ] no se duplicó ninguna entidad académica;
- [ ] no se creó una base de datos nueva;
- [ ] no se creó un CRUD administrativo alternativo;
- [ ] los tests existentes continúan pasando;
- [ ] existen tests nuevos para capabilities y restricciones del rol.

---

## 24. Fuera de alcance de la V1

Queda explícitamente fuera:

- integración API con Sala Virtual;
- creación de Zoom o Google Meet desde WordPress;
- consulta de reuniones desde WordPress;
- integración API de lectura con Preinscripciones;
- administración de postulaciones desde WordPress;
- SSO entre WordPress, Sala Virtual y Preinscripciones;
- migración de datos a otra plataforma;
- reemplazo del modelo académico;
- nuevo frontend independiente;
- rediseño completo del sitio público;
- CRM;
- email marketing;
- automatizaciones de campañas;
- cambios en Mautic;
- cambios en Amazon SES.

Estas mejoras pueden evaluarse después de estabilizar la experiencia administrativa de las Asistentes Académicas.

---

## 25. Resultado esperado

La Asistente Académica debería percibir una herramienta simple:

```text
FLACSO Gestión

Inicio
Ofertas académicas
Seminarios
Docentes

Preinscripciones ↗
Sala Virtual ↗
```

Mientras que técnicamente se conserva:

```text
WordPress
    +
flacso-uruguay-plugin
    +
modelo académico existente
```

El objetivo no es esconder WordPress por completo ni construir una aplicación nueva, sino aprovechar WordPress como núcleo estable y convertir el plugin en una capa de gestión académica segura, específica y cómoda para el trabajo cotidiano.
