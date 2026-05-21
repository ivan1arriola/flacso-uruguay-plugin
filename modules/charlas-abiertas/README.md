# FLACSO Uruguay - Charlas Abiertas

Plugin para gestionar charlas como objeto (`charla_abierta`), renderizar formulario desde bloque Gutenberg y enviar inscripciones a un endpoint REST con contrato estable.

## Endpoints

> [!IMPORTANT]
> La documentación técnica completa y actualizada de todos los endpoints de este y otros módulos se encuentra en el archivo principal [API.md](../../API.md) en la raíz del proyecto.

### Listado de charlas

- `GET /wp-json/flacso-charlas/v1/charla-abierta`

Parámetros opcionales:

- `page`: página de resultados. Default `1`.
- `per_page`: cantidad por página. Default `10`, máximo `100`.
- `search`: busca por título.
- `modalidad`: `virtual|presencial|hibrida`.
- `desde`: fecha/hora ISO 8601 mínima de inicio.
- `hasta`: fecha/hora ISO 8601 máxima de inicio.
- `order`: `asc|desc` sobre `_charla_inicio`.

Ejemplo:

```bash
curl "https://tu-sitio/wp-json/flacso-charlas/v1/charla-abierta?modalidad=virtual&order=asc"
```

Respuesta:

```json
{
  "ok": true,
  "code": "CHARLAS_LIST",
  "data": {
    "items": [
      {
        "id": 123,
        "slug": "nombre-de-la-charla",
        "titulo": "Nombre de la charla",
        "inicio": "2026-03-15T18:00:00-03:00",
        "inicio_timestamp": 1773618000,
        "modalidad": "virtual",
        "zoom_join_url": "https://zoom.us/j/123456789",
        "youtube_transmision_url": "https://www.youtube.com/watch?v=abc123",
        "duracion_minutos": 90,
        "direccion": "",
        "descripcion": "<p>Descripción original</p>",
        "descripcion_rendered": "<p>Descripción original</p>\n",
        "fecha_creacion": "2026-03-01T12:00:00",
        "fecha_actualizacion": "2026-03-05T09:00:00",
        "post_featured_image": "https://tu-sitio/wp-content/uploads/2026/03/imagen.jpg",
        "meta": {
          "_charla_inicio": "2026-03-15T18:00:00-03:00",
          "_charla_modalidad": "virtual",
          "_charla_zoom_join_url": "https://zoom.us/j/123456789",
          "_charla_youtube_transmision_url": "https://www.youtube.com/watch?v=abc123",
          "_charla_duracion_minutos": 90,
          "_charla_direccion": "",
          "_charla_descripcion": "<p>Descripción original</p>"
        },
        "endpoint": "https://tu-sitio/wp-json/flacso-charlas/v1/charla-abierta/123"
      }
    ],
    "pagination": {
      "page": 1,
      "per_page": 10,
      "total_items": 1,
      "total_pages": 1
    },
    "filters": {
      "search": "",
      "modalidad": "virtual",
      "desde": "",
      "hasta": "",
      "order": "asc"
    }
  },
  "error": null,
  "processing_ms": 3.45
}
```

### Detalle de charla

- `GET /wp-json/flacso-charlas/v1/charla-abierta/{id}`

Ejemplo:

```bash
curl "https://tu-sitio/wp-json/flacso-charlas/v1/charla-abierta/123"
```

Respuesta:

```json
{
  "ok": true,
  "code": "CHARLA_FOUND",
  "data": {
    "id": 123,
    "slug": "nombre-de-la-charla",
    "titulo": "Nombre de la charla",
    "inicio": "2026-03-15T18:00:00-03:00",
    "inicio_timestamp": 1773618000,
    "modalidad": "virtual",
    "zoom_join_url": "https://zoom.us/j/123456789",
    "youtube_transmision_url": "https://www.youtube.com/watch?v=abc123",
    "duracion_minutos": 90,
    "direccion": "",
    "descripcion": "<p>Descripción original</p>",
    "descripcion_rendered": "<p>Descripción original</p>\n",
    "fecha_creacion": "2026-03-01T12:00:00",
    "fecha_actualizacion": "2026-03-05T09:00:00",
    "post_featured_image": "https://tu-sitio/wp-content/uploads/2026/03/imagen.jpg",
    "meta": {
      "_charla_inicio": "2026-03-15T18:00:00-03:00",
      "_charla_modalidad": "virtual",
      "_charla_zoom_join_url": "https://zoom.us/j/123456789",
      "_charla_youtube_transmision_url": "https://www.youtube.com/watch?v=abc123",
      "_charla_duracion_minutos": 90,
      "_charla_direccion": "",
      "_charla_descripcion": "<p>Descripción original</p>"
    },
    "endpoint": "https://tu-sitio/wp-json/flacso-charlas/v1/charla-abierta/123"
  },
  "error": null,
  "processing_ms": 1.02
}
```

### Inscripción

- `POST /wp-json/flacso-charlas/v1/inscripcion`
- `Content-Type: application/json`

## Contrato de Request

```ts
type Payload = {
  evento: {
    id: string | number;                 // requerido
    titulo: string;                      // requerido
    inicio: string;                      // requerido (ISO 8601 con TZ)
    modalidad: string;                   // requerido

    // opcionales
    zoom_join_url?: string;              // URL
    youtube_transmision_url?: string;    // URL
    duracion_minutos?: number;           // minutos
    direccion?: string;
    google_maps_url?: string;            // URL opcional
    descripcion?: string;                // puede incluir HTML
  };

  inscripcion: {
    nombre: string;                      // requerido
    apellido: string;                    // requerido
    correo: string;                      // requerido (email válido)
    pais_residencia?: string;
    profesion?: string;
    institucion?: string;
    telefono?: string;                   // opcional, valor visible ingresado por el usuario
    telefono_e164?: string;              // opcional, normalizado por intl-tel-input
    celular?: string;                    // opcional, alias legacy para compatibilidad
    modalidad_asistencia: 'presencial' | 'virtual'; // requerido
  };

  device?: {
    ip?: string;
    user_agent?: string;
    referer?: string;
    origin?: string;
    device_type?: string;
    screen_width?: number | string;
    screen_height?: number | string;
    language?: string;
    timezone?: string;
  };

  meta?: {
    wp_user_logged_in?: boolean;
    timestamp_client?: string;           // ISO 8601
    host_post_id?: number | string;
    post_featured_image?: string;        // URL http/https
  };
};
```

## JSON definitivo esperado

```json
{
  "evento": {
    "id": "string|number",
    "titulo": "string",
    "inicio": "string (ISO 8601 con TZ)",
    "modalidad": "string",
    "zoom_join_url": "string (url) [opcional]",
    "youtube_transmision_url": "string (url) [opcional]",
    "duracion_minutos": "number [opcional, en minutos]",
    "direccion": "string [opcional]",
    "google_maps_url": "string (url) [opcional]",
    "descripcion": "string [opcional]"
  },
  "inscripcion": {
    "nombre": "string",
    "apellido": "string",
    "correo": "string (email)",
    "pais_residencia": "string [opcional]",
    "profesion": "string [opcional]",
    "institucion": "string [opcional]",
    "telefono": "string [opcional]",
    "telefono_e164": "string [opcional]",
    "celular": "string [opcional, alias legacy]",
    "modalidad_asistencia": "presencial|virtual"
  },
  "device": {
    "ip": "string [opcional]",
    "user_agent": "string [opcional]",
    "referer": "string [opcional]",
    "origin": "string [opcional]",
    "device_type": "string [opcional]",
    "screen_width": "number|string [opcional]",
    "screen_height": "number|string [opcional]",
    "language": "string [opcional]",
    "timezone": "string [opcional]"
  },
  "meta": {
    "wp_user_logged_in": "boolean [opcional]",
    "timestamp_client": "string (ISO 8601) [opcional]",
    "host_post_id": "number|string [opcional]",
    "post_featured_image": "string (url) [opcional]"
  }
}
```

## Contrato de Response

### Respuesta OK

```json
{
  "ok": true,
  "code": "CONFIRMADA|DUPLICADA",
  "data": {
    "inscripcion_id": "string (uuid-timestamp)",
    "duplicada": "boolean",
    "saved": true,
    "telegram": "sent|failed",
    "email": "sent|skipped|failed",
    "email_sender": "string|null",
    "gmail_message_url": "string|null",
    "spreadsheet": {
      "id": "string",
      "name": "string"
    }
  },
  "error": null,
  "processing_ms": "number"
}
```

Notas:
- `code: CONFIRMADA` si no era duplicada.
- `code: DUPLICADA` si el correo ya estaba registrado en ese evento.
- `gmail_message_url` solo debe venir si hubo envío por Gmail API con `messageId`.
- `email_sender` es la cuenta usada cuando se envía correo.

### Error de validación

```json
{
  "ok": false,
  "code": "VALIDATION_ERROR",
  "data": null,
  "error": {
    "message": "Errores de validación",
    "details": {
      "fields": [
        { "field": "string", "message": "string" }
      ]
    }
  },
  "processing_ms": "number"
}
```

### Error BAD_REQUEST (sin body)

```json
{
  "ok": false,
  "code": "BAD_REQUEST",
  "data": null,
  "error": {
    "message": "Solicitud inválida: falta body",
    "details": null
  },
  "processing_ms": "number"
}
```

### Error INTERNAL_ERROR

```json
{
  "ok": false,
  "code": "INTERNAL_ERROR",
  "data": null,
  "error": {
    "message": "Error en el procesamiento de la inscripción",
    "details": {
      "message": "string",
      "inscripcion_id": "string|null",
      "spreadsheet_id": "string|null",
      "spreadsheet_name": "string|null"
    }
  },
  "processing_ms": "number"
}
```

## Validaciones implementadas

- `evento.id`: obligatorio y debe corresponder a `charla_abierta`.
- `evento.titulo`: obligatorio.
- `evento.inicio`: obligatorio, ISO 8601 con zona horaria.
- `evento.modalidad`: obligatorio.
- `inscripcion.nombre`: obligatorio.
- `inscripcion.apellido`: obligatorio.
- `inscripcion.correo`: obligatorio, email válido.
- `inscripcion.correo` (servidor): además se valida que el dominio exista por DNS (`MX/A/AAAA/CNAME`) con caché de 6 horas.
- Excepción DNS: si el dominio termina en `gmail.com` o `outlook.com`, se omite verificación DNS.
- `inscripcion.modalidad_asistencia`: obligatorio, `virtual|presencial`.
- `inscripcion.telefono`: opcional; si viene, formato validado.
- `inscripcion.telefono_e164`: opcional; si viene, se prioriza como valor normalizado.
- `inscripcion.celular`: alias legacy opcional para compatibilidad.

## Integraciones Frontend

### intl-tel-input

- Campo visible: `inscripcion.telefono`.
- Campo oculto normalizado: `inscripcion.telefono_e164`.
- Compatibilidad legacy: `inscripcion.celular`.
- Inicialización: `assets/js/frontend.js`.
- Assets: cargados desde CDN en `includes/frontend-assets.php`.
- Comportamiento:
  - Si el usuario ingresa teléfono, se valida con la misma lógica de `preinscripcion`: se aceptan números locales de 8+ dígitos o internacionales válidos.
  - Si es válido, se normaliza y envía en formato internacional (`E.164`) en `telefono_e164`.
  - Además se mantiene `celular` como alias compatible para consumidores legacy.
  - Si no es válido, no se envía el formulario y se muestra error en pantalla.

### country-select-js

- Campo: `inscripcion.pais_residencia`.
- Inicialización: `assets/js/frontend.js` (requiere jQuery).
- Assets: cargados desde CDN en `includes/frontend-assets.php`.
- Comportamiento:
  - Muestra selector con bandera y país.
  - Al enviar, toma el nombre del país seleccionado y lo coloca en `pais_residencia`.

### Nombre y apellido en UI y payload

- En el formulario se solicitan por separado:
  - `nombre`
  - `apellido`
- En el POST se envían por separado:
  - `inscripcion.nombre`
  - `inscripcion.apellido`
- Compatibilidad legacy:
  - Si un cliente viejo envía `inscripcion.nombre_apellido`, el receptor lo intenta separar automáticamente en `nombre` y `apellido`.

## Duplicados

Se detecta duplicado por combinación:
- `evento.id`
- `inscripcion.correo` (normalizado en minúsculas)

Resultado:
- primer envío: `code = CONFIRMADA`
- siguientes envíos para mismo evento/correo: `code = DUPLICADA`

## Configuración

- Webhook configurable en: `Ajustes > Charlas Abiertas`.
- Campo: `Webhook URL`.
- Destino sugerido para la app: `https://<tu-dominio-flacso-editor>/api/charlas/inscripciones`.
- Seguridad opcional recomendada:
  - En `flacso-editor`, definir `FLACSO_CHARLAS_WEBHOOK_TOKEN`.
  - En WordPress, pegar ese mismo valor en `Ajustes > Charlas Abiertas > Webhook Token`.
  - El plugin lo enviará como `Authorization: Bearer <token>`.
- Si el webhook no está configurado, el plugin ahora corta el envío para evitar perder inscripciones silenciosamente.

## Estructura del modulo integrado

- `init.php`: bootstrap del modulo en `flacso-uruguay`.
- `includes/cpt.php`: objeto `charla_abierta` + metadatos.
- `includes/block.php`: bloque Gutenberg + render formulario.
- `includes/rest.php`: endpoint y contrato request/response.
- `includes/settings.php`: ajustes del webhook.
- `assets/js/frontend.js`: armado y envio del payload.
- `assets/js/block-editor.js`: selector de charla en editor.
- `assets/css/frontend.css`: estilos de formulario.
