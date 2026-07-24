# Formularios webhook

Módulo independiente para crear formularios configurables desde WordPress y
entregar sus respuestas directamente a un webhook. No guarda las respuestas ni
los archivos en WordPress.

## Uso

1. En el administrador, abrir `Formularios webhook > Crear formulario webhook`.
2. Indicar un título y el ID de la carpeta de Google Drive de destino.
3. Agregar y ordenar los campos.
4. Escribir el texto introductorio en el editor y asignar una imagen destacada.
5. Publicar y usar la página pública o el shortcode mostrado en la barra lateral.

En la página individual, el tema muestra título e imagen destacada, luego aparece
el contenido escrito en el editor y finalmente el formulario.

## Gestión desde una app

Además de los endpoints estándar del CPT en
`/wp-json/wp/v2/formularios-webhook`, existe una API compacta para gestionar la
configuración completa:

```text
GET  /wp-json/flacso/v1/webhook-forms
GET  /wp-json/flacso/v1/webhook-forms/{id}
POST /wp-json/flacso/v1/webhook-forms
PUT  /wp-json/flacso/v1/webhook-forms/{id}
```

Todos estos endpoints requieren un usuario autenticado con permisos para editar
entradas. Se pueden consumir desde la app mediante la autenticación de WordPress
que ya utilice el sistema o mediante Application Passwords.

Ejemplo de creación:

```json
{
  "title": "Inscripción a actividad",
  "content": "<p>Completá tus datos para participar.</p>",
  "status": "publish",
  "featured_media": 1234,
  "drive_folder_id": "1AbCdEfGhIjKlMn",
  "fields": [
    {
      "type": "nombre",
      "label": "Nombre",
      "name": "nombre",
      "required": true
    },
    {
      "type": "documento",
      "label": "Documento de identidad",
      "name": "documento",
      "required": true
    },
    {
      "type": "archivo",
      "label": "Constancia",
      "name": "constancia",
      "help": "Adjuntá una imagen o PDF",
      "required": false
    }
  ]
}
```

Tipos válidos: `nombre`, `apellido`, `email`, `pais`, `telefono`, `documento`,
`texto`, `textarea` y `archivo`. `nombre` y `apellido` son casos específicos de
texto corto; correo, país y teléfono agregan controles y validaciones propios.
El token no se configura ni se
expone por esta API: siempre se usa la
opción global `flacso_webhook_token`, compartida con los demás formularios.
La URL se deriva de `flacso_external_editor_url` y usa automáticamente
`/api/formularios/respuestas`.

## Contrato del webhook

La solicitud usa `POST multipart/form-data` y contiene:

- `form_id`: ID del formulario en WordPress.
- `form_title`: título del formulario.
- `submitted_at`: fecha ISO 8601 en UTC.
- `source_url`: URL pública del formulario.
- `submission_id`: UUID único para evitar duplicados.
- `drive_folder_id`: carpeta raíz configurada para el formulario.
- `fields`: objeto de respuestas codificado como JSON.
- `field_labels`: relación entre nombres internos y etiquetas visibles.
- `field_{nombre}`: cada respuesta repetida como campo plano para integraciones
  que no decodifican el JSON.
- `files[{nombre}]`: archivo binario asociado al campo.

Para documentos, el objeto `fields` también incluye `{nombre}_tipo`, cuyo valor
es `uy` o `ext`.

Si existe el token global compartido, se agregan ambos encabezados usados por
las demás integraciones:

```text
X-FLACSO-Webhook-Token: {flacso_webhook_token}
Authorization: Bearer {flacso_webhook_token}
```

El webhook debe devolver un código HTTP `2xx`. Cualquier otro código se muestra
como error de entrega y se registra en el log de WordPress sin incluir datos
personales ni el token.

## Archivos

Se aceptan PDF, JPG, PNG y WebP de hasta 10 MB por archivo y 12 MB en total por
envío. El tipo real se valida en el servidor. Los temporales de PHP se leen
únicamente para construir la solicitud y no se incorporan a la biblioteca de
medios.
