# Integración WordPress → FLACSO Mailing

## Estado

El outbox de consultas académicas admite `mautic` (predeterminado) y `flacso_mail` por entrega. La migración añade `assignedProvider`; cada nueva fila conserva el proveedor incluso al reintentar. La ruta Mailing usa `POST /api/v1/events/academic-inquiries`, HMAC-SHA256 y `Idempotency-Key` estable. No se cambian los proveedores de entregas ya existentes.

## Configuración

Antes de habilitar Mailing:

1. Aplicar `scripts/migrations/2026-10-17-inquiry-delivery-provider.sql` después de la migración inicial de la cola.
2. Configurar en `wp-config.php` la URL HTTPS del servicio mediante `FLACSO_MAILING_BASE_URL`.
3. Configurar `FLACSO_INQUIRY_DELIVERY_PROVIDER` como `flacso_mail` para asignar Mailing a consultas académicas nuevas. Sin esta constante el valor sigue en `mautic`.
4. Configurar `WP_WEBHOOK_HMAC_SECRET` con el mismo secreto ya existente en Mailing. No generar un secreto distinto para este flujo ni guardar el valor en Git.

El receptor debe tener publicadas las plantillas `f1-open`, `f1-closed` y `f1-seminar`. Para F2 también debe existir la automatización `f2-academic-followup`. La política F2 sólo se activa para ofertas abiertas cuando se recibe evidencia válida de consentimiento.

## Entrega y recuperación

Un `200` o `202` del receptor marca la entrega como aceptada. Un `409` es conflicto idempotente definitivo. `429` y `5xx` se reintentan hasta el límite existente, siempre con la misma clave; errores de transporte se marcan `acceptance_unknown` y requieren conciliación antes de una repetición manual. Los fallos de configuración se bloquean. El worker no crea contactos ni mensajes fuera del receptor transaccional.

La integración no se habilita en producción mediante este cambio. La activación requiere verificar conectividad, el secreto compartido, la migración y las plantillas en un entorno de prueba.
