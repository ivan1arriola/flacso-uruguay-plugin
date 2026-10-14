# Integración de consultas: Meta Bot → FLACSO Uruguay

La web conserva sus formularios y servicios habituales. El bot remite las
consultas confirmadas al mismo módulo **Consultas** (PostgreSQL + snapshot +
cola transaccional Mautic). No se modifica el modelo académico: `offer`
apunta a `OfertaAcademica` y `seminar` apunta a `Seminario`.

## API

- `GET /wp-json/flacso/v1/chatbot/catalogo`: catálogo público, sólo entradas publicadas, dos entidades raíz con `id/title/kind/type/url`.
- `POST /wp-json/flacso/v1/chatbot/consultas`: requiere `Authorization: Bearer <TOKEN>`; acepta sólo un ID de post académico publicado, correo válido y nombre. Valida el origen (`instagram` o `messenger`), ignora nombres académicos enviados por el cliente y usa siempre el título canónico de WordPress.
- `eventId`: `metabot-` + SHA-256 hexadecimal de `channel:accountId:participantId:MetaMessageId`; el servicio de consultas ya elimina duplicados por `consultaId`.

Ejemplo de payload (los IDs deben existir realmente):

```json
{
  "eventId": "metabot-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
  "channel": "instagram",
  "academic": { "kind": "offer", "id": 12343 },
  "firstName": "María",
  "lastName": "Pérez",
  "email": "persona@example.com",
  "phone": "+59899123456",
  "message": "Solicitud de información sobre Maestría en Género."
}
```

La autorización se configura **únicamente en el servidor**, mediante una
constante de al menos 32 caracteres en `wp-config.php` (fuera de Git):

```php
define('FLACSO_METABOT_INQUIRY_TOKEN', getenv('FLACSO_METABOT_INQUIRY_TOKEN'));
```

Configurar la variable de entorno del proceso PHP en infraestructura o,
si se almacena fuera del repositorio, cargarla desde un archivo protegido.
No reutilizar los secretos de Meta Graph API.

## Despliegue seguro

1. Ejecutar el test autónomo `php tests/chatbot-inquiry-api-test.php`.
2. Desplegar primero el plugin WordPress. Validar la ruta de catálogo mediante `curl`; verificar que la API privada rechaza una llamada sin token con HTTP 401 (o 503 cuando aún no está configurada).
3. Configurar el mismo token en el bot, por medio de un Docker secret. El bot seguirá utilizando Sheets hasta activar `FLACSO_INQUIRIES_TOKEN` o `FLACSO_INQUIRIES_TOKEN_FILE`.
4. Realizar una consulta de prueba en Instagram y otra en Messenger; confirmar que aparecen en Consultas como oferta o seminario, con su canal y una única fila por `eventId`.
5. Verificar las entregas de Mautic; no se activa marketing sin consentimiento explícito.

## Seguridad y recuperación

- No exponer ni almacenar el token en base de datos, en respuestas REST o en el navegador.
- La ruta escribe sólo a través de los servicios existentes y hereda sus transacciones de guardado/cola.
- Un error de PostgreSQL devuelve un error y no se emite acuse de éxito al usuario.
- Los reintentos conservan `eventId`, evitando crear solicitudes duplicadas.
- Retirar la variable de activación del bot devuelve el destino a Sheets, sin borrar registros existentes.
- Si Apache no entrega `Authorization` a WordPress, configurar el reenvío de esta cabecera antes de activar el puente.
- Las consultas transaccionales y el consentimiento comercial son procesos distintos.
