# Consultas: correo transaccional y campañas

## Campañas por oferta (desde 2026-10-25)

Diseño completo: `FLACSO-Uruguay-Mautic-Plugin/docs/2026-10-25-campanas-por-oferta-diseno.md`.

Con `flacso_mautic_offer_campaigns_enabled = 1`, una consulta de oferta **con consentimiento** deja de recibir el acuse transaccional. Su entrega de la cola hace `POST /api/flacso/inquiries` al plugin FLACSO de Mautic, que da de alta al contacto en la campaña de la oferta. La campaña envía el acuse según el estado de preinscripción y después el seguimiento.

- **Consentimiento:** lo genera el servidor (`FLACSO_Offer_Campaigns::form_consent()`) sólo en el formulario «Más información», que muestra `FLACSO_Offer_Campaigns::CONSENT_TEXT` junto al botón. `FLACSO_Offer_Inquiry_Service::submit()` lo recibe como segundo argumento y nunca lo lee del navegador. El bot de Meta, cuando se implemente, debe pasar su propia evidencia por ese argumento. El formulario flotante y los formularios heredados no entran a campañas.
- **Oferta sin campaña:** si el plugin responde `campaign_not_configured` o `campaign_unpublished`, la entrega sigue con el acuse transaccional. Así se puede activar oferta por oferta cargando el mapa en Mautic (`php bin/console flacso:offer-campaign set <oferta> <campaña>`).
- **Errores:** un 422 por datos es definitivo y alerta. 5xx, 401/403/429 o un fallo de red se reintentan cada 5 minutos, hasta 6 intentos. El plugin es idempotente por `consultaId`, así que reintentar es seguro.
- **Campaña global:** con el interruptor encendido, las consultas que van a la campaña por oferta no se agregan a `flacso_mautic_campaign_consultas_id`.

Activación (después del piloto en Mautic):

```bash
wp option update flacso_mautic_offer_campaigns_enabled 1   # encender
wp option update flacso_mautic_offer_campaigns_enabled 0   # volver atrás
```

## Acuse transaccional

- La operación vigente usa dos plantillas Mautic de envío directo desde WordPress:
  - F1 consulta abierta: ID `4`.
  - F1 consulta cerrada: ID `5`.
- La cola transaccional de snapshots continúa deshabilitada hasta aprobar una plantilla versionada con SHA-256.
- Fuente de datos: tokens persistidos con cada InquirySnapshot.
- Campaña Mautic: **no interviene en el acuse**.

La plantilla debe resolver datos como oferta, cohorte, fecha, modalidad y enlaces desde los tokens de la entrega. No debe reconstruirlos desde campos mutables del contacto.

El manifiesto mantiene deliberadamente vacío content_sha256 hasta completar la prueba controlada descrita en token-delivery-proof.md. Mientras la huella no esté aprobada, el validador devuelve blocked y la cola no puede enviar.

## Campaña comercial

- Campaña: Consultas web FLACSO
- ID operativo actual: `3`
- Estado: inactiva durante la transición y las pruebas internas.
- Rol nuevo: seguimiento/marketing posterior, no acuse.
- Opción de WordPress: flacso_mautic_campaign_enabled
- Estado operativo durante la transición: desactivada.

Aunque la opción se active posteriormente, sync_commercial_contact() exige consentimiento completo (granted, acceptedAt, source, textVersion) antes de añadir un contacto a la campaña.

## Estado de la transición

1. La consulta y su contexto se persisten en WordPress/PostgreSQL.
2. La entrega empieza en pending.
3. El worker sólo procesa si flacso_inquiry_delivery_queue_enabled = 1.
4. El contrato Mautic debe validar campos, plantilla y SHA-256.
5. Un timeout después de iniciar el POST de envío pasa a acceptance_unknown y requiere conciliación manual.
6. No existe fallback automático a Mailjet ni a wp_mail en el flujo nuevo.

Los bloques Mailjet heredados permanecen inalcanzables únicamente hasta que el piloto sea aprobado. Su retirada se realiza después de esa evidencia, tal como indica el plan de implementación.

La aceptación HTTP de Mautic confirma aceptación API, no entrega efectiva del mensaje.
