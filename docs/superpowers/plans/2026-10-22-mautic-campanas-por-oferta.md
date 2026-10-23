# Seguimiento Mautic por oferta Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Configurar 18 campañas Mautic por oferta para esperar siete días después del F1 aceptado y elegir F2 abierto/cerrado según el estado actual de WordPress.

**Architecture:** WordPress inscribe al contacto, con consentimiento, en la campaña asociada al `offerWpId` solo después de aceptar Mautic el F1. Cada campaña usa un campo de estado propio, espera siete días, marca ese campo `verificando`, llama a un endpoint WordPress autenticado y ramifica a dos correos según el estado actual que WordPress escribió en Mautic. Las campañas y correos se preparan sin publicar y se activan tras un piloto.

**Tech Stack:** WordPress/PHP 7.4+, REST API WordPress, PostgreSQL/PDO, API REST Mautic 7.2, Campaign Builder y webhooks Mautic.

**Spec:** `docs/superpowers/specs/2026-10-22-mautic-campanas-por-cohorte.md`

## Global Constraints

- Crear 18 campañas, una por oferta; el mapa WP es `offerWpId → mauticCampaignId`.
- La espera es de siete días desde la aceptación del email 4 por Mautic.
- Mantener el consentimiento completo como requisito del seguimiento.
- El camino abierto usa un F2 basado en email 6; el cerrado usa el mensaje basado en email 5 aprobado por el usuario.
- WordPress consulta el catálogo vigente al vencerse la espera.
- Un estado `verificando`/error nunca envía un correo.
- No excluir ni consultar personas preinscriptas.
- No modificar emails F1 IDs 4/5, datos/contactos/campañas existentes, tags ni consultas históricas.
- El endpoint no registra PII y acepta solo la lista configurada de oferta/contacto.
- Compatible con PHP 7.4+; sin paquetes nuevos.

## Review Focus

- Contacto consultó dos ofertas y llega a ambas campañas: cada webhook actualiza solo el campo de su oferta.
- F1 falla o queda `acceptance_unknown`: no se incorpora a campaña ni se reinicia el envío.
- Webhook inválido, replayado o con ID de oferta sin configurar: rechazo seguro, sin cambiar campos.
- El catálogo falla o no encuentra cohorte: permanece `verificando`; ninguna rama envía.
- Una oferta cerrada al día siete: recibe solo el correo de cierre; una abierta, solo F2 abierto.

## Archivos y unidades

| Ruta | Responsabilidad |
| --- | --- |
| `includes/integrations/class-flacso-mautic-client.php` | Actualizar campos de un contacto Mautic con respuesta tipada. |
| `modules/mailing/includes/class-flacso-mail-settings.php` | Guardar/validar el mapa de campañas por oferta y el secreto de webhook en un control enmascarado. |
| `modules/consultas/services/class-flacso-inquiry-marketing-service.php` | Sincronizar perfil/consentimiento y diferir la membresía hasta F1 aceptado. |
| `modules/consultas/services/class-flacso-inquiry-campaign-service.php` | Resolver campaña por oferta, agregar miembro idempotente luego de F1 y refrescar estado actual. |
| `modules/consultas/includes/class-flacso-mautic-campaign-webhook.php` | Registrar/autenticar webhook, validar payload y devolver respuesta sin PII. |
| `modules/consultas/services/class-flacso-inquiry-delivery-service.php` | Llamar al servicio de campaña solo en aceptación Mautic del F1 abierto. |
| `modules/consultas/init.php` | Cargar servicio/controlador en el módulo. |
| `tests/mautic-client-test.php` | Contrato HTTP de actualización de campos. |
| `tests/mautic-campaign-config-test.php` | Sanitización y validación de mapa/secretos. |
| `tests/inquiry-campaign-service-test.php` | Consentimiento, aceptación F1, estado vigente y fallo cerrado. |
| `tests/mautic-campaign-webhook-test.php` | Autenticación, IDs y errores del endpoint. |
| `tests/inquiry-delivery-service-test.php` | Integración del alta tras `mark_accepted`. |
| `docs/mautic/campaigns.md`, `docs/mautic/operations.md` | Mapeo, configuración, piloto, cron y reversa. |

## Mapeo inicial de ofertas WordPress

La consulta del catálogo encontró estos 18 IDs de oferta. Los estados de cohortes cambian; no codificar su estado actual en campañas ni en PHP.

`24160, 24161, 24162, 24163, 24164, 24165, 24166, 24167, 24168, 24169, 24170, 24171, 24172, 24173, 24174, 26595, 27556, 27681`

## Task 1: Cliente Mautic y configuración segura

**Files:**
- Modify: `includes/integrations/class-flacso-mautic-client.php`
- Modify: `modules/mailing/includes/class-flacso-mail-settings.php`
- Create: `tests/mautic-campaign-config-test.php`
- Modify: `tests/mautic-client-test.php`

**Interfaces:**
- Produce `FLACSO_Mautic_Client::update_contact_fields(int $contact_id, array $fields): array` con `ok`, `status`, `http_code`, `error`; validar IDs positivos y aliases permitidos.
- Produce `FLACSO_Mail_Settings::get_offer_campaign_map(): array<int,int>` desde `flacso_mautic_campaign_offer_map`.
- Produce `FLACSO_Mail_Settings::get_campaign_webhook_secret(): string` desde la opción secreta; el render del admin nunca devuelve su valor guardado.

- [ ] **Step 1: Escribir pruebas fallidas del cliente y settings**

En `tests/mautic-client-test.php`, comprobar PATCH a `/api/contacts/{id}/edit`, respuesta válida, 401/422/500, timeout y rechazo de IDs inválidos. En `tests/mautic-campaign-config-test.php`, comprobar que se conservan solo IDs enteros positivos de oferta/campaña, se descartan entradas malformadas y el campo secreto vacío conserva el valor existente.

- [ ] **Step 2: Ejecutar las pruebas y confirmar FAIL**

Run: `php tests/mautic-client-test.php && php tests/mautic-campaign-config-test.php`
Expected: FAIL porque los contratos aún no existen.

- [ ] **Step 3: Implementar el PATCH y las opciones**

Usar el cliente HTTP/autenticación existentes; usar `sanitize_text_field`/validación estricta y control `manage_options`; renderizar el secreto enmascarado y no volver a mostrarlo en respuestas/logs.

- [ ] **Step 4: Ejecutar pruebas y confirmar PASS; commit**

Run: `php tests/mautic-client-test.php && php tests/mautic-campaign-config-test.php`
Expected: PASS sin revelar secretos.

## Task 2: Servicio de pertenencia y estado por oferta

**Files:**
- Create: `modules/consultas/services/class-flacso-inquiry-campaign-service.php`
- Modify: `modules/consultas/services/class-flacso-inquiry-marketing-service.php`
- Modify: `modules/consultas/init.php`
- Create: `tests/inquiry-campaign-service-test.php`
- Modify: `tests/inquiry-marketing-consent-test.php`

**Interfaces:**
- Produce `FLACSO_Inquiry_Campaign_Service::add_after_f1_accepted(array $snapshot, array $consent, int $contact_id, string $inquiry_id, ?FLACSO_Offer_Inquiry_Repository $repository = null): array`.
- Produce `FLACSO_Inquiry_Campaign_Service::refresh_offer_status(int $offer_id, int $contact_id): array` que consulta `FLACSO_Academic_Catalog::get_offer($offer_id)` y actualiza `flacso_estado_oferta_{offer_id}` con `abierta` o `cerrada` mediante el cliente Mautic.

- [ ] **Step 1: Escribir pruebas fallidas**

Cubrir: consentimiento incompleto; oferta sin mapeo; membresía nueva; ya miembro; dos ofertas con campos/IDs diferentes; cohortes abiertas/cerradas; oferta no publicada/sin cohorte; catálogo fallido; API Mautic fallida. Confirmar que `refresh_offer_status` no escribe el estado previo ante error.

- [ ] **Step 2: Ejecutar prueba dirigida y confirmar FAIL**

Run: `php tests/inquiry-campaign-service-test.php`
Expected: FAIL por servicio/clase aún ausente.

- [ ] **Step 3: Implementar el servicio y separar la sincronización**

Mantener sync de perfil/tags en `sync_commercial_contact`, pero quitar la membresía inmediata al enviar el formulario. Requerir consentimiento completo y campaña configurada. Para el estado, consultar catálogo actual y limitar campos a `flacso_estado_oferta_{offer_id}`; un fallo retorna error y nunca transforma un resultado desconocido en abierto/cerrado.

- [ ] **Step 4: Ejecutar pruebas y revisar regresión de consentimiento; commit**

Run: `php tests/inquiry-campaign-service-test.php && php tests/inquiry-marketing-consent-test.php && php tests/inquiry-marketing-tags-test.php`
Expected: PASS; sin alta de campaña antes de F1.

## Task 3: Alta a campaña tras aceptación de F1

**Files:**
- Modify: `modules/consultas/services/class-flacso-inquiry-delivery-service.php`
- Modify: `tests/inquiry-delivery-service-test.php`
- Modify: `tests/inquiry-delivery-order-test.php`

**Interfaces:**
- Consume `FLACSO_Inquiry_Campaign_Service::add_after_f1_accepted(...)` desde la rama del envío Mautic F1 abierto.
- Usa el `contactId` guardado para la entrega, el snapshot/payload persistido, consentimiento y `inquiryId`.

- [ ] **Step 1: Escribir pruebas fallidas de orden y estados terminales**

Afirmar que la membresía ocurre solo después de respuesta Mautic exitosa y `mark_accepted`; falla o `acceptance_unknown` no incorpora; el proveedor alternativo no se incorpora; un reintento de entrega aceptada no duplica la membresía.

- [ ] **Step 2: Ejecutar pruebas y confirmar FAIL**

Run: `php tests/inquiry-delivery-service-test.php && php tests/inquiry-delivery-order-test.php`
Expected: FAIL porque no hay orquestación post-aceptación.

- [ ] **Step 3: Implementar llamada post-aceptación**

Después de persistir aceptación/contacto, pasar snapshot y consentimiento ya almacenados al servicio. No reintentar el envío de F1 si falla la membresía; guardar estado y usar notificación operativa existente sin PII.

- [ ] **Step 4: Ejecutar pruebas y confirmar PASS; commit**

Run: `php tests/inquiry-delivery-service-test.php && php tests/inquiry-delivery-order-test.php`
Expected: PASS; la consulta y el resultado de F1 conservan su respuesta actual.

## Task 4: Webhook autenticado para estado de oferta

**Files:**
- Create: `modules/consultas/includes/class-flacso-mautic-campaign-webhook.php`
- Modify: `modules/consultas/init.php`
- Create: `tests/mautic-campaign-webhook-test.php`

**Interfaces:**
- Produce ruta `POST /wp-json/flacso/v1/mautic/campaign-status`.
- Header requerido: `Authorization: Bearer <flacso_mautic_campaign_webhook_secret>`.
- Body estricto: `{ "offerId": <positive int>, "contactId": <positive int> }`; el ID de campaña fija `offerId`, y `{contactfield=id}` provee el contacto.
- Devuelve HTTP 200 y estado `abierta|cerrada` solo después de actualizar Mautic; 401/400/404/503 en autenticación, payload, oferta o dependencias inválidos.

- [ ] **Step 1: Escribir pruebas fallidas del endpoint**

Probar token incorrecto/ausente, valores no enteros, oferta fuera del mapa, contacto inválido, llamada exitosa, catálogo sin resultado, timeout y campo de estado aislado por oferta. Afirmar que logs/respuestas no contienen email, credencial ni snapshot.

- [ ] **Step 2: Ejecutar prueba y confirmar FAIL**

Run: `php tests/mautic-campaign-webhook-test.php`
Expected: FAIL porque ruta y controlador aún no existen.

- [ ] **Step 3: Implementar autenticación, validación y respuesta**

Comparar tokens con `hash_equals`; registrar solo error clasificado, offerId, resultado e instante. No aceptar campos libres ni llamadas a ofertas sin mapeo.

- [ ] **Step 4: Ejecutar prueba y confirmar PASS; commit**

Run: `php tests/mautic-campaign-webhook-test.php && php tests/inquiry-campaign-service-test.php`
Expected: PASS; webhook y servicio Mautic mantienen el contrato.

## Task 5: Preparar Mautic y el piloto

**Files:**
- Modify: `docs/mautic/campaigns.md`
- Modify: `docs/mautic/operations.md`
- Modify: `modules/mailing/includes/class-flacso-mail-settings.php` si se requieren textos de ayuda/configuración

**Interfaces:**
- El mapa WP almacena 18 IDs definitivos de campaña; sin ID el contacto no se incorpora.
- Cada campaña se llama `Seguimiento | {nombre oferta}` y tiene el campo `flacso_estado_oferta_{offerId}`.

- [ ] **Step 1: Crear campos de contacto en Mautic**

Crear 18 campos de texto con los aliases exactos por oferta y completar ficha operativa con `offerWpId`, alias y `campaignId`, sin PII.

- [ ] **Step 2: Clonar los correos para campañas**

Por oferta, preparar F2 abierto basado en email 6 con detalles y CTA a la página canónica actualizada. Preparar mensaje cerrado basado en email 5, con enlace a seminarios abiertos. Sustituir tokens exclusivos del envío por campos estándar de Mautic y verificar que no quede ningún token literal. Mantener remitente, reply-to, tracking y baja configurados.

- [ ] **Step 3: Crear las 18 campañas como no publicadas**

En cada campaña: contacto agregado por WordPress → espera 7 días → actualizar el campo de esa oferta a `verificando` → webhook a `https://flacso.edu.uy/wp-json/flacso/v1/mautic/campaign-status` con `offerId` fijo, `contactId={contactfield=id}` y Bearer secreto → condición de campo igual a `abierta` o `cerrada` → correo correspondiente. Otros valores terminan sin enviar.

- [ ] **Step 4: Configurar WordPress con IDs y secreto solo después del despliegue**

Verificar que el panel enmascara el secreto, valida los 18 pares de IDs y mantiene el mapa vacío como desactivación global. Nunca copiar el secreto al repositorio ni a logs.

- [ ] **Step 5: Piloto controlado antes de publicar**

Usar contacto autorizado, consentimiento válido y F1 de prueba aceptado. Forzar en catálogo estado abierto/cerrado y fallo; comprobar campo de estado, rama, email, demora y métricas. Confirmar que no se incorpora a quien carece de consentimiento y que llamadas inválidas no afectan contactos.

## Task 6: Regresión, despliegue y activación

**Files:**
- No se añaden archivos nuevos salvo correcciones detectadas.
- Revisar workflow `.github/workflows/deploy-plugin.yml` y estado de Git antes de publicar.

**Interfaces:**
- El plugin desplegado mantiene mapa vacío/inactivo hasta que termine el piloto.
- Rollback: vaciar/desactivar el mapa, despublicar las 18 campañas y conservar datos/campos.

- [ ] **Step 1: Ejecutar verificación de proyecto**

Run: `python scripts/agent_check.py`
Expected: `RESULTADO: [EXITO]`.

- [ ] **Step 2: Revisar diff y estado**

Run: `git diff --check; git status --short; git diff --stat`
Expected: solo archivos de la especificación, sin secretos ni cambios no relacionados.

- [ ] **Step 3: Desplegar código con mapa vacío**

Commit y push a `main` según `AGENTS.md`; esperar workflow y comprobar estado saludable en producción. No publicar campañas aún.

- [ ] **Step 4: Activar tras piloto**

Cargar IDs definitivos, activar campañas y verificar la primera membresía originada por F1 aceptado. Si el piloto no satisface todos los criterios, desactivar mapa/campañas y registrar el error sin activar envíos.

## Spec Coverage Review

Campañas por oferta (Task 5), estado vigente y rama abierta/cerrada (Tasks 2, 4, 5), espera de 7 días y etapa webhook (Task 5), alta solo después de F1 aceptado (Task 3), consentimiento (Tasks 2-3), seguridad/token y estados de error sin envío (Tasks 1-2, 4), no modificar F1 ni histórico (Tasks 3, 6), diagnóstico y reversa (Tasks 5-6), verificación automatizada y despliegue (Task 6).

