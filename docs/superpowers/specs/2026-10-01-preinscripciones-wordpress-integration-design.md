# Integración WordPress–Preinscripciones FLACSO

## Estado

Diseño aprobado para la primera integración con la nueva plataforma de Preinscripciones.

## Objetivo

Permitir que la nueva plataforma descubra desde WordPress qué destinos académicos existen, si están abiertos, qué campos específicos requieren y qué orientaciones/menciones son válidas, sin conocer Custom Post Types ni metadatos internos.

Durante la primera etapa, el flujo actual de preinscripciones seguirá funcionando. La nueva integración se incorporará de forma reversible y no reescribirá automáticamente los enlaces existentes.

## Responsabilidades

### Plugin FLACSO / WordPress

- Fuente de verdad del catálogo académico.
- Estado abierto/cerrado de cada Cohorte o Edición.
- Configuración efectiva de campos específicos por destino.
- Orientaciones, menciones y su relación cuando corresponda.
- Publicación del contrato REST v1.
- Conservación del flujo legacy y de `link_preinscripcion`.

### Nueva plataforma

- Catálogo técnico de inputs.
- Renderizado y validación de formularios.
- Revalidación del destino al enviar.
- Escritura en Google Sheets y Google Drive.
- Diagnóstico operativo de WordPress, Sheets y Drive.

El plugin no recibirá datos personales del formulario ni implementará la escritura en Sheets o Drive.

## Arquitectura propuesta

Se agregará un módulo independiente `modules/preinscripciones/`, cargado por el loader principal. Sus responsabilidades serán:

- `class-preinscriptions-field-catalog.php`: catálogo cerrado de claves seleccionables en wp-admin.
- `class-preinscriptions-config.php`: sanitización, deduplicación, posiciones y obligatoriedad.
- `class-preinscriptions-serializer.php`: adaptación del modelo académico al contrato externo.
- `class-preinscriptions-rest.php`: registro del endpoint público y respuestas REST.
- componentes administrativos para la configuración de Cohorte y Edición.

El módulo reutilizará `FLACSO_Cohorte::accepts_registration()` y `FLACSO_Edicion::accepts_registration()` como decisión única de apertura. No agregará una segunda bandera de apertura.

## Persistencia administrativa

La configuración efectiva se guardará en el destino concreto:

- Cohorte: meta `preinscripcion_formulario`.
- Edición: meta `preinscripcion_formulario`.

Formato:

```json
[
  {
    "key": "documento",
    "position": 10,
    "required": true
  }
]
```

El catálogo inicial de claves será:

```text
documento
fechaNacimiento
titulo
escolaridad
orientacion
mencion
institucion
ocupacion
```

WordPress solo definirá la clave, el orden y la obligatoriedad. No definirá tipo HTML, regex, normalización ni columna de Sheets.

La interfaz administrativa permitirá agregar, quitar y reordenar campos, sin claves duplicadas ni claves desconocidas. Las operaciones estarán protegidas por nonce y capacidades de edición.

## Orientaciones y menciones

Las listas editoriales textuales actuales de la Oferta Académica no se reutilizarán como contrato de opciones del formulario.

Cuando corresponda, cada Cohorte podrá guardar una estructura específica como `preinscripcion_orientaciones`:

```json
[
  {
    "id": "educacion",
    "name": "Educación",
    "mentions": [
      {
        "id": "tecnologia-educativa",
        "name": "Tecnología educativa"
      }
    ]
  }
]
```

Esto permite que una Cohorte tenga opciones diferentes sin alterar la descripción histórica de la Oferta Académica. Las Ediciones de seminario devolverán una lista vacía salvo que el dominio lo requiera en una etapa posterior.

## Contrato REST público v1

### Endpoint agregado

```http
GET /wp-json/flacso/v1/preinscripciones
```

La respuesta incluirá destinos abiertos y cerrados:

```json
{
  "version": 1,
  "targets": [
    {
      "id": "target_7f4b6d2a9c1e",
      "kind": "academic_offer",
      "wordpress": {
        "offerId": 12295,
        "cohortId": 1234
      },
      "offer": {
        "id": 12295,
        "slug": "davia",
        "name": "DAVIA"
      },
      "cohort": {
        "number": 11,
        "name": "Cohorte XI"
      },
      "registrationOpen": true,
      "registrationWindow": {
        "from": null,
        "until": null
      },
      "configRevision": "sha256:...",
      "form": {
        "valid": true,
        "inputs": [
          {
            "key": "documento",
            "position": 10,
            "required": true
          }
        ]
      },
      "orientations": [],
      "urls": {
        "public": "https://flacso.edu.uy/...",
        "wordpress": "https://flacso.edu.uy/...",
        "edit": "https://flacso.edu.uy/wp-admin/...",
        "legacyRegistration": "https://preinscripciones.flacso.edu.uy/..."
      }
    }
  ]
}
```

Para seminarios, `kind` será `seminar` y `wordpress` tendrá `seminarId` y `editionId`. El discriminador permite que la app interprete cada tipo sin depender de nombres de CPT.

### Identidad y URLs

- `version` será numérica y tendrá el valor `1`.
- `id` será estable y opaco; no dependerá de nombres, títulos ni slugs visibles.
- `wordpress` contendrá los IDs nativos necesarios para diagnóstico y enlaces.
- `registrationWindow.from` y `until` serán fechas ISO 8601 o `null`.
- `appUrl` no será responsabilidad del plugin.
- `legacyRegistration` conservará el enlace actual cuando exista.

### Revisión de configuración

`configRevision` será una huella estable, por ejemplo `sha256:<digest>`, calculada sobre una representación JSON canónica de la configuración efectiva que la app necesita para renderizar y validar el destino. No incluirá campos volátiles como la hora de generación o el orden accidental de las claves.

La app enviará esa revisión junto con la preinscripción y volverá a consultar WordPress antes de aceptar el envío. Si la huella cambió, rechazará el envío para evitar guardar datos contra un formulario obsoleto.

### Contratos inválidos

Los datos antiguos o inconsistentes no se ocultarán silenciosamente:

```json
{
  "form": {
    "valid": false,
    "issues": ["unknown_input"]
  }
}
```

Los códigos serán técnicos y seguros. No incluirán trazas, metadatos internos arbitrarios ni datos personales. La nueva app no aceptará formularios cuyo contrato sea inválido.

### Acceso y caché

- API pública y de solo lectura.
- `permission_callback` abierto para GET.
- Sin datos personales.
- `Cache-Control` breve, inicialmente 60 segundos.
- Las futuras operaciones de escritura permanecerán autenticadas y fuera de este contrato.

## Flujo de operación

1. La app consulta el endpoint agregado.
2. Descubre destinos abiertos y cerrados.
3. Construye el formulario con su propio catálogo técnico.
4. Reconsulta el destino antes de aceptar el envío.
5. Comprueba existencia, apertura, `configRevision` y opciones académicas.
6. Escribe la preinscripción en Sheets y los documentos en Drive.

WordPress continúa siendo la autoridad para la existencia, apertura y configuración académica; la app es la autoridad para interpretar y validar cada input.

## Compatibilidad y rollout

- Se conserva `link_preinscripcion`.
- No se cambian automáticamente botones ni enlaces públicos.
- La API se puede desplegar y validar mientras el flujo legacy continúa operativo.
- El MVP puede consumir primero una respuesta simulada con este contrato.
- Luego se conecta al endpoint real y compara destinos, estados y configuraciones.
- La activación de la nueva app por destino será una decisión posterior y reversible.

## Pruebas requeridas

El plugin deberá cubrir:

- catálogo de claves permitido;
- sanitización y deduplicación;
- posiciones y obligatoriedad;
- orientaciones y relación con menciones;
- IDs estables;
- cálculo de `configRevision`;
- apertura/cierre y ventana temporal;
- serialización de ofertas y seminarios;
- contrato REST v1;
- destinos inválidos y códigos seguros;
- compatibilidad con `link_preinscripcion`;
- ausencia de datos personales;
- preservación del comportamiento legacy.

La integración se validará localmente con pruebas de contrato y, cuando exista WordPress operativo, con una comprobación REST de lectura y una revisión manual del editor de Cohorte y Edición.
