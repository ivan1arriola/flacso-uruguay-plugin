# Guía visual del modelo académico de FLACSO Uruguay

**Para quién es:** equipos académicos, coordinación, comunicación, gestión web y personas que cargan información en el sistema.  
**Objetivo:** entender qué es cada registro, qué campos tiene, de quién depende y qué cambia de una cursada a otra.  
**Alcance:** Programa Académico, Oferta Académica, Cohorte, Seminario y Edición, con sus relaciones con personas docentes y tablas de precios.

> Esta guía describe el **modelo de contenido del plugin WordPress**, no un diagrama de tablas físicas de la base de datos. Se contrastó con los registros y metadatos del código vigente en octubre de 2026. Para contratos de API, validaciones y detalles de implementación, consultar [modelo-academico-final.md](modelo-academico-final.md), [seminarios-integrados.md](seminarios-integrados.md) y [API.md](../API.md). Cuando la documentación histórica difiera del código operativo, se indica expresamente.

## 1. El sistema, de un vistazo

~~~mermaid
flowchart TB
    P["PROGRAMA ACADÉMICO<br/>Agrupa formación por programa institucional"]
    O["OFERTA ACADÉMICA<br/>Carrera o trayecto formativo"]
    S["SEMINARIO<br/>Unidad o actividad académica"]
    C["COHORTE<br/>Una cursada concreta de la oferta"]
    E["EDICIÓN<br/>Una realización concreta del seminario"]
    D["PERSONA DOCENTE<br/>Registro reutilizable de personas"]
    T["TABLA DE PRECIOS<br/>Aranceles de una cursada"]

    P -->|"Agrupa varias"| O
    P -->|"Agrupa varios"| S
    O -->|"Tiene cohortes"| C
    S -->|"Tiene ediciones"| E
    O -.->|"Puede incluir seminarios"| S
    S -.->|"Puede componerse de otros"| S
    C -->|"Selecciona precios"| T
    E -->|"Selecciona precios"| T
    P -.->|"Coordinación"| D
    O -.->|"Equipos académicos"| D
    E -->|"Docentes de esa edición"| D

    classDef instit fill:#e9eef6,stroke:#526883,color:#203048
    classDef oferta fill:#e0ebff,stroke:#3367b5,color:#17365d
    classDef seminario fill:#def4ec,stroke:#248268,color:#15523e
    classDef recurso fill:#fff2d4,stroke:#b88b39,color:#614918
    class P instit
    class O,C oferta
    class S,E seminario
    class D,T recurso
~~~

**Cómo leerlo:** una flecha continua indica una relación de dependencia o uso; una flecha punteada indica una asociación académica. Cada ficha de abajo aclara las condiciones exactas.

**Dos recorridos distintos:**

- **Programa → Oferta → Cohorte.** La oferta define *qué formación es*; la cohorte indica *cuándo y cómo se dicta esa formación*.
- **Programa → Seminario → Edición.** El seminario define *qué actividad es*; la edición indica *cuándo se realiza, quién la dicta y qué encuentros tiene*.

Un seminario **no es un tipo de oferta**. Puede dictarse por sí mismo y, además, formar parte de una o varias ofertas. Una cohorte **no es** una edición.

## 2. ¿Qué información permanece y cuál cambia?

| Dato | Se administra en… | Explicación |
|---|---|---|
| Nombre y presentación de un programa institucional | **Programa Académico** | Identidad del programa que reúne ofertas y seminarios. |
| Nombre, tipo, plan formativo, malla, créditos y títulos de una maestría/diploma | **Oferta Académica** | Datos que se mantienen al abrir nuevas cohortes. |
| Número de cohorte, fecha de inicio, modalidad, calendario y preinscripción | **Cohorte** | Datos propios de una cursada de esa oferta. |
| Contenido, objetivos, horas y créditos de un seminario | **Seminario** | Datos académicos reutilizados entre ediciones. |
| Año, semestre, fechas, clases, docentes y preinscripción de un seminario | **Edición** | Datos que pueden cambiar en cada realización. |
| Aranceles | **Tabla de Precios**, vinculada desde Cohorte o Edición | Evita guardar precios contradictorios en el contenido estable. |

> **Ejemplo ilustrativo:** una maestría puede mantener el mismo nombre y la misma malla durante años, mientras se crean Cohorte IV, V y VI con distintas fechas, modalidades y tablas de precios. Del mismo modo, un seminario puede ofrecer una edición en 2026 y otra en 2027 con docentes diferentes.

## 3. Relaciones y cantidades

| Desde | Hacia | Cantidad y regla |
|---|---|---|
| Programa Académico | Oferta Académica | Un programa agrupa **muchas ofertas**; cada oferta referencia **un programa**. |
| Programa Académico | Seminario | Un programa agrupa **muchos seminarios**; cada seminario referencia **un programa**. |
| Oferta Académica | Cohorte | Una oferta puede tener **muchas cohortes**; cada cohorte pertenece a **una sola oferta**. |
| Seminario | Edición | Un seminario puede tener **muchas ediciones**; cada edición pertenece a **un solo seminario**. |
| Oferta Académica | Seminario | Una oferta puede incluir **varios seminarios**, obligatorios u opcionales, con orden y créditos reconocidos. Un seminario puede reutilizarse. |
| Seminario integrado | Seminario componente | Un seminario puede componerse de otros seminarios; no puede incluirse a sí mismo. |
| Edición integrada | Edición componente | La edición integrada enlaza las **ediciones concretas** correspondientes a los seminarios componentes. |
| Cohorte / Edición | Tabla de Precios | Cada cohorte o edición puede referenciar **una tabla**; la misma tabla puede reutilizarse. |
| Programa / Oferta / Edición | Persona docente | Programa: coordinación; Oferta: equipos académicos; Edición: quienes dictan esa realización. |

Los seminarios asociados a una Oferta Académica describen su **estructura académica**, no las fechas de sus ediciones.

## 4. Fichas de las cinco entidades

La columna «Campo interno» permite reconocer la información al hablar con el equipo técnico. No es necesario conocer esos nombres para utilizar el sistema.

### 4.1 Programa Académico

**Qué representa:** agrupación institucional de ofertas y seminarios. No representa una cohorte ni una cursada con fechas propias.

| Campo visible | Campo interno | Qué guarda |
|---|---|---|
| Nombre | nombre / título WordPress | Denominación del programa. |
| Identificador | id | Número único del registro. |
| Dirección web | slug | Identificador de la URL pública. |
| Imagen | imagen destacada | Imagen del programa. |
| Presentación | presentacion | Descripción editorial del programa. |
| Correo de contacto | correo | Casilla institucional de referencia. |
| Coordinación / dirección | coordinacion | Lista de personas y funciones (véase más abajo). |
| Orden de aparición | orden | Posición en los listados institucionales. |
| Publicación | estado_publicacion | Publicado, borrador, pendiente o privado. |
| Texto principal y resumen | contenido / resumen | Campos generales que WordPress puede conservar; su uso público depende de la plantilla. |

**Estructura de la coordinación:** cada integrante tiene **docente_id** (referencia opcional a una persona registrada), **nombre** y **rol** (p. ej., coordinación académica o dirección). La relación con ofertas y seminarios se obtiene desde los registros que apuntan a este programa.

### 4.2 Oferta Académica

**Qué representa:** una formación estable, como una maestría, una especialización, un diplomado o un diploma. Una cohorte nueva **no requiere duplicar** la oferta.

| Grupo | Campos internos | Para qué sirven |
|---|---|---|
| Identidad | nombre, id, slug, imagen, resumen, estado_publicacion | Identificación, presentación pública y publicación en WordPress. |
| Dependencia institucional | programa_academico_id | Programa Académico al que pertenece. |
| Clase de formación | tipo | Doctorado, maestría, especialización, diplomado o diploma. |
| Identificación breve | abreviacion | Sigla o abreviatura de la oferta. |
| Contacto | correo, correo_oferta | Correos almacenados para contacto; el correo propio de la oferta es independiente y opcional. |
| Presentación | presentacion | Texto de presentación académica. |
| Organización del plan | composicion_academica | Secciones del plan formativo con título y contenido. |
| Duración y dedicación | duracion_meses, carga_horaria, creditos | Meses, horas y créditos de la oferta. |
| Ingreso | perfil_ingreso_html, requisitos_ingreso_html | Destinatarios, perfil esperado y condiciones de ingreso. |
| Egreso | perfil_egreso_html, requisitos_egreso_html | Perfil de egreso y requisitos de culminación. |
| Certificaciones | titulos_certificaciones_html | Titulaciones, certificaciones y descripción institucional. |
| Trayectorias | menciones, orientaciones, titulos_intermedios | Menciones, orientaciones y títulos intermedios vinculados con otras ofertas. |
| Malla curricular | malla_curricular | Enlace al documento con la malla. |
| Documentación | documentos | Documentos y sus enlaces, disponibilidad y fechas cuando corresponda. |
| Personas y equipos | equipos | Grupos de personas vinculadas académicamente a la oferta. |
| Reconocimientos | reconocido_mec, reconocimiento_internacional, reconocimiento_red_flacso, convenio_iin_oea | Indicadores institucionales y convenios que se muestran en la página y documentos. |
| Presentación de costos | mostrar_costos_envio, mostrar_expedicion_titulo | Opciones para visualizar determinados conceptos. |
| Identidad visual | colores_presentacion | Color principal y colores secundarios. |
| Seminarios incorporados | seminarios | Vínculos con seminarios, orden, carácter obligatorio/opcional y créditos reconocidos. |

**Tipos posibles de Oferta Académica:** doctorado, maestría, especialización, diplomado y diploma. **Seminario queda fuera** de esta clasificación: tiene entidad propia.

**Campos compuestos importantes:**

- **composicion_academica:** varias secciones; cada una tiene *titulo* y *contenido*.
- **documentos:** registros con *enabled* (disponible), *link* y eventualmente *fecha*.
- **equipos:** grupos con *nombre*, *descripcion*, *importancia* y una lista de *docentes*; cada persona puede tener *id*, *rol* y *correo*.
- **titulos_intermedios:** vínculos con otras ofertas (*oferta_id*) y las *menciones* reconocidas.
- **colores_presentacion:** *principal* y *secundarios*.
- **seminarios:** por cada vínculo se guarda *seminario_id*, *orden*, *caracter* (obligatorio u opcional) y *creditos_reconocidos*. **No se asigna una edición** en este vínculo.

La Oferta Académica **no guarda el número de cohorte ni la fecha de inicio de cada cursada**: esas variables pertenecen a Cohorte.

### 4.3 Cohorte

**Qué representa:** una cursada concreta de una Oferta Académica. Se administra desde su oferta y no tiene una página web independiente.

| Campo visible | Campo interno | Qué guarda |
|---|---|---|
| Oferta a la que pertenece | oferta_academica_id | Referencia obligatoria a una Oferta Académica. |
| Número de cohorte | numero | Número entero positivo; dentro de una oferta no puede repetirse. |
| Nombre mostrado | derivado | Se calcula automáticamente: Cohorte I, II, III, IV, etc. |
| Fechas de dictado | fecha_inicio, fecha_fin | Fechas concretas cuando están definidas. |
| Años del período | anio_inicio, anio_fin | Años de comienzo y finalización. |
| Precisión de fecha | precision_fecha_inicio | Indica si se conoce día, mes o solo año. |
| Situación académica | estado | Planificada, en curso, finalizada o cancelada. |
| Modalidad | modalidad | Modalidad correspondiente a esta cohorte. |
| Detalle de modalidad | modalidad_descripcion | Aclaraciones sobre la cursada. |
| Instancias presenciales | instancias_presenciales | Indica si incluye instancias exclusivamente presenciales. |
| Calendario académico | calendario_academico | Enlace al calendario de esa cohorte. |
| Aranceles | tabla_precio_id | Tabla de Precios utilizada por esta cohorte. |
| Enlace de preinscripción | link_preinscripcion | URL externa de la plataforma de preinscripciones. |
| Habilitación de preinscripción | preinscripcion_habilitada | Apertura/cierre administrativo. |

**Reglas:** «Cohorte V» se genera desde **numero = 5**; no se escribe como título independiente. Una cohorte no puede pertenecer a dos ofertas. Cancelada o finalizada no debería aceptar nuevas preinscripciones; la habilitación de preinscripción se evalúa por separado del estado académico.

### 4.4 Seminario

**Qué representa:** actividad académica estable, con contenidos y características propios. Puede ofrecerse de forma independiente, incorporarse a una oferta o ser un seminario integrado.

| Grupo | Campos internos | Para qué sirven |
|---|---|---|
| Identidad | nombre, id, slug, imagen, resumen, estado_publicacion | Datos públicos y publicación del seminario. |
| Programa | programa_academico_id | Programa Académico al que se vincula. |
| Asociación adicional | oferta_academica_id | Vínculo directo presente en el modelo técnico; no sustituye la lista explícita de seminarios de una oferta. |
| Contacto | correo | Correo de referencia. |
| Presentación | presentacion | Descripción general. |
| Objetivos | objetivo_general, objetivos_especificos | Objetivo principal y lista de objetivos. |
| Contenidos | composicion_academica | Unidades o secciones con *titulo* y *contenido*. |
| Evaluación | forma_aprobacion | Condiciones académicas de aprobación. |
| Dedicación | carga_horaria, carga_horaria_descripcion, creditos | Horas, detalle de horas y créditos. |
| Reconocimiento | acreditacion, acredita_maestria, acredita_doctorado | Acreditación y posibilidad de reconocer créditos en estudios de posgrado. |
| Seminarios componentes | componentes | Otros seminarios que integran académicamente uno mayor, con *seminario_id* y *orden*. |

Un seminario **no tiene sus propios docentes de dictado ni fechas de una realización específica**: se consultan en sus Ediciones. En un seminario integrado, créditos y otros valores pueden calcularse desde sus componentes, según las reglas de integración.

### 4.5 Edición

**Qué representa:** una realización determinada de un Seminario. Se administra desde ese seminario y no tiene URL pública independiente.

| Campo visible | Campo interno | Qué guarda |
|---|---|---|
| Seminario | seminario_id | Referencia obligatoria al Seminario correspondiente. |
| Año | anio | Año de la edición. |
| Semestre | semestre | Semestre, cuando corresponda. |
| Nombre mostrado | derivado | «Edición 2027» o «Edición 2027 — Semestre 1». |
| Fechas | fecha_inicio, fecha_fin | Período de dictado. |
| Estado | estado | Planificada, en curso, finalizada o cancelada. |
| Modalidad | modalidad | Modalidad de esa realización. |
| Encuentros sincrónicos | encuentros_sincronicos | Lista de encuentros concretos. |
| Docentes | docentes | Personas que dictan **esa edición**. |
| Aranceles | tabla_precio_id | Tabla de Precios usada en la edición. |
| Enlace de preinscripción | link_preinscripcion | URL de la plataforma externa. |
| Habilitación de preinscripción | preinscripcion_habilitada | Permite o impide nuevas preinscripciones, sujeto a las demás reglas. |
| Inclusión en formulario | mostrar_en_formulario | Define si aparece en el catálogo externo correspondiente. |
| Ediciones componentes | ediciones_componentes | Ediciones concretas que forman una edición integrada, con *edicion_id* y *orden*. |
| Modalidad asincrónica | es_asincronica (derivado) | Se determina a partir de la ausencia de encuentros sincrónicos. |

**Cada encuentro sincrónico** incluye *fecha*, *hora_inicio*, *hora_fin* y *zona_horaria*. Los horarios de encuentro corresponden a la **Edición**, no al Seminario estable.

Las ediciones del mismo seminario no pueden duplicar la combinación de **año y semestre**. Una edición cancelada no debe tratarse como vigente. La apertura de preinscripción no depende únicamente del interruptor: también puede quedar restringida por estado y reglas temporales.

## 5. Recursos compartidos importantes

### Tabla de Precios

Una tabla de precios es un registro reutilizable, no una cohorte ni una edición. Incluye **nombre**, **tabla_precios_tipo**, **precios_filas**, **precios_nota** y **mostrar_precios_dolares**. Cada fila de precio contiene:

| Subcampo | Significado |
|---|---|
| concepto | Concepto o categoría del importe. |
| uyu | Monto en pesos uruguayos. |
| usd | Monto en dólares cuando se usa esa moneda. |
| destacada | Señala una fila principal. |

**Las referencias a precios están en Cohorte y Edición.** No corresponde duplicar importes en Oferta Académica o Seminario.

### Personas docentes y equipos

Hay tres relaciones distintas que deben conservarse separadas:

1. **Programa → coordinación:** personas y funciones de conducción académica.
2. **Oferta → equipos:** grupos docentes/académicos, descripción, rol e importancia institucional.
3. **Edición → docentes:** personas responsables del dictado de esa edición en particular.

**Una cohorte no posee el mismo campo de docentes de dictado que una Edición.** No hay que interpretar automáticamente el equipo estable de una oferta como una lista de docentes asignada a todas sus cohortes.

## 6. Seminarios integrados: dos niveles de relación

~~~mermaid
flowchart LR
    SI["Seminario integrado<br/>Define la composición estable"]
    SA["Seminario A"]
    SB["Seminario B"]
    EI["Edición integrada 2027<br/>Selecciona realizaciones concretas"]
    EA["Edición A · 2027"]
    EB["Edición B · 2027"]
    SI -->|"componente"| SA
    SI -->|"componente"| SB
    SI -.->|"tiene"| EI
    EI -->|"edición componente"| EA
    EI -->|"edición componente"| EB
    SA -.-> EA
    SB -.-> EB
~~~

La **composición académica** se define en Seminario mediante *componentes*. La **selección temporal** se realiza en Edición mediante *ediciones_componentes*. No se deben mezclar ambas cosas: una edición integrada necesita saber **qué ediciones concretas** de los componentes se cursan juntas.

En ediciones integradas, los **docentes y encuentros** se obtienen de las ediciones componentes y se consolidan sin duplicarlos; no se deben copiar a mano. Consultar [Seminarios integrados](seminarios-integrados.md) para las reglas de validación, recursión y límites.

## 7. Reglas prácticas para cargar y publicar información

| Situación | Qué hacer | Qué no hacer |
|---|---|---|
| Se abre una nueva cohorte de una maestría | Agregar Cohorte a la Oferta existente. | Duplicar la maestría como otra Oferta. |
| Cambia la fecha de inicio o modalidad de una cohorte | Editar esa Cohorte. | Modificar la definición general de la Oferta. |
| Se dicta nuevamente un seminario con otros docentes | Crear nueva Edición y asignar allí docentes y encuentros. | Duplicar el Seminario estable. |
| Una oferta incorpora seminarios | Editar la lista *seminarios* de Oferta y su carácter, orden y créditos. | Asociar una Edición en lugar del Seminario. |
| Un seminario es integrado | Definir seminarios componentes y, en cada edición integrada, ediciones componentes. | Copiar manualmente docentes y horarios de sus componentes. |
| Cambian los aranceles para un nuevo dictado | Asignar la Tabla de Precios apropiada a la Cohorte o Edición. | Guardar un precio global que afecte todas las cursadas. |
| Se cancela una edición | Marcarla cancelada y revisar visibilidad e inscripción. | Mantenerla como edición vigente o mostrarla solo por tener un enlace activo. |

**Estado académico y estado de publicación son diferentes.** Un registro puede estar publicado en WordPress y representar una cohorte o edición finalizada; «publicado» no significa «inscripciones abiertas». A su vez, la disponibilidad pública de un seminario depende de la existencia y validez de sus ediciones, además de otras comprobaciones del catálogo.

**Preinscripciones:** se gestionan en **https://preinscripciones.flacso.edu.uy**. El plugin conserva los enlaces y expone información al catálogo; no procesa la preinscripción como un formulario WordPress local.

## 8. Glosario rápido

| Palabra | Significado sencillo |
|---|---|
| **Entidad** | Tipo de registro que el sistema permite crear y editar. |
| **Programa** | Agrupación académica institucional. |
| **Oferta** | Formación estable (maestría, diploma, etc.). |
| **Cohorte** | Grupo o período concreto de una oferta. |
| **Seminario** | Actividad académica con contenidos propios. |
| **Edición** | Realización concreta de un seminario. |
| **Relación** | Enlace que permite que un registro use o incluya otro. |
| **Campo derivado** | Dato que el sistema calcula; por ejemplo, el número romano de cohorte. |
| **Vigente** | Cohorte o edición que cumple las reglas para considerarse actual; no equivale siempre a «preinscripción abierta». |
| **Slug / ID** | Identificador legible de URL / identificador numérico interno. |

## 9. Notas de precisión técnica (para quien mantiene el sistema)

- Las entidades públicas principales son *programa-academico*, *oferta-academica* y *seminario*. *cohorte* y *edicion* son dependientes y no tienen URL pública propia.
- Los metadatos de Oferta actualmente registrados priorizan **presentación, composición, perfiles, malla, documentos, equipos y certificaciones**. Campos como **objetivo_general, objetivos_especificos, forma_aprobacion y acreditacion** pueden encontrarse en documentación anterior o datos heredados de ofertas, pero **no forman parte del listado principal de metadatos activos de Oferta** en *class-oferta-academica.php*. En cambio, algunos sí están activos para **Seminario**.
- *colores_presentacion* está registrado en el modelo de Oferta y se edita por el administrador; no figura en el listado de campos del repositorio REST académico *class-academic-repositories.php*. Lo mismo ocurre con la presentación de Programa: existe en el editor, pero no está en ese listado REST. **«Registrado» no implica «expuesto por todas las APIs»**.
- El campo técnico *oferta_academica_id* está registrado en Seminario, además de la relación *Oferta.seminarios*. Para comprender la composición académica de una oferta, la lista explícita de seminarios de Oferta es la referencia que contiene **orden, carácter y créditos reconocidos**.
- Los nombres internos y sus reglas pueden variar entre la interfaz WordPress, el repositorio académico y las plantillas. Esta guía distingue conceptos de usuario y claves de persistencia, sin afirmar que todos los campos se editan desde la misma pantalla o API.

### Fuentes del repositorio

- [Modelo académico formal](modelo-academico-final.md) y [Seminarios integrados](seminarios-integrados.md).
- [Definiciones y campos de las APIs académicas](../modules/oferta-academica/includes/class-academic-repositories.php).
- [Programa Académico](../modules/oferta-academica/includes/class-cpt-programa-academico.php).
- [Oferta Académica](../modules/oferta-academica/includes/class-oferta-academica.php).
- [Cohorte](../modules/oferta-academica/includes/class-cohorte.php).
- [Seminario](../modules/seminarios/includes/class-seminario.php).
- [Edición](../modules/seminarios/includes/class-edicion.php).
- [Tabla de Precios](../modules/oferta-academica/includes/class-cpt-tabla-precio.php).

---

**Uso recomendado:** leer primero la vista general y los dos recorridos; consultar después la ficha de la entidad que se necesita editar. Esta guía sirve para formación interna y validación funcional; no reemplaza los contratos técnicos ni las pruebas de integridad del código.
