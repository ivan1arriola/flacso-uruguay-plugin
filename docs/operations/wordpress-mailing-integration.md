# Integración WordPress → FLACSO Mailing

## Estado

El outbox de consultas académicas admite `mautic` (predeterminado) y `flacso_mail` por entrega. La migración añade `assignedProvider`; cada nueva fila conserva el proveedor incluso al reintentar. La ruta Mailing usa `POST /api/v1/events/academic-inquiries`, HMAC-SHA256 y `Idempotency-Key` estable. No se cambian los proveedores de entregas ya existentes.

## Configuración

Antes de habilitar Mailing:

1. Aplicar `scripts/migrations/2026-10-17-inquiry-delivery-provider.sql` después de la migración inicial de la cola.
2. En WordPress, abrir **FLACSO → Correos**, elegir `FLACSO Mailing (SES)` y guardar la URL HTTPS del servicio. El proveedor y la URL se almacenan en opciones de WordPress; el proveedor predeterminado continúa siendo Mautic.
3. Configurar `WP_WEBHOOK_HMAC_SECRET` en la configuración protegida del servidor de WordPress y con el mismo valor de entorno ya existente en Mailing. Es la única credencial necesaria para este puente; no duplicar ni guardar el valor en Git o en la base de datos de WordPress.
4. Antes de usar el shortcode de suscripción, crear y activar en Mailing la lista con slug `suscripcion-institucional`.

El receptor debe tener publicadas las plantillas `f1-open`, `f1-closed`, `f1-seminar` e `internal-general-inquiry`. Para F2 también debe existir la automatización `f2-academic-followup`. La política F2 sólo se activa para ofertas abiertas cuando se recibe evidencia válida de consentimiento. El formulario general usa el destinatario institucional y BCC definidos en Mailing, sin crear un contacto. El shortcode conserva una clave por formulario y la evidencia de consentimiento en un transient de 30 días para que un reenvío mantenga los bytes del evento.

## Entrega y recuperación

Un `200` o `202` del receptor marca el evento como aceptado. Un `409` es conflicto idempotente definitivo. Las entregas académicas y la sincronización de suscripciones pueden reintentarse tras `429`/`5xx` con la misma clave idempotente. Para consultas generales, `429` se reintenta, pero un timeout o `5xx` se conserva como `acceptance_unknown` hasta reconciliarlo, porque WordPress ya guardó la consulta y no debe liberar otra creación. Los fallos de configuración se bloquean. El worker no crea contactos ni mensajes fuera del receptor transaccional.

La integración no se habilita en producción mediante este cambio. La activación requiere verificar conectividad, el secreto compartido, la migración y las plantillas en un entorno de prueba.
