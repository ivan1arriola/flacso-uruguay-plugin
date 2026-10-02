# Operación de consultas transaccionales

## Estado seguro por defecto

La opción `flacso_inquiry_delivery_queue_enabled` debe permanecer en `0` hasta
que la prueba de tokens de Mautic esté aprobada y el manifiesto contenga la
huella SHA-256 de la plantilla exacta. El código no registra WP-Cron.

## Migración

Antes de ejecutar `scripts/migrations/2026-09-30-inquiry-transactional-deliveries.sql`:

1. crear un backup restaurable de PostgreSQL;
2. validar que el backup pueda leerse;
3. aplicar la migración una sola vez;
4. comprobar tablas e índices;
5. mantener la cola desactivada.

## Cron de servidor

Después de la autorización operativa y con la cola habilitada, ejecutar cada minuto:

```sh
* * * * * cd /var/www/clients/client2/web5/web && wp flacso consultas deliveries run --limit=10 --quiet
```

La retención puede ejecutarse diariamente:

```sh
17 3 * * * cd /var/www/clients/client2/web5/web && wp flacso consultas deliveries retention --days=90 --quiet
```

El worker usa una protección global con vencimiento y, como garantía primaria,
reclamos SQL condicionales por entrega. Una reserva vencida pasa a
`acceptance_unknown`; nunca vuelve automáticamente a `pending`.

## Conciliación

`wp flacso consultas deliveries reconcile` lista IDs técnicos que requieren
revisión. No muestra payloads, correos ni tokens y no reenvía.

Una entrega `acceptance_unknown` sólo puede resolverse luego de verificar en
Mautic si la solicitud fue aceptada. No debe crearse un segundo POST por
suposición.

## Rollback

El rollback inicial consiste en deshabilitar la cola. No borrar snapshots,
entregas ni consultas. No reactivar Mailjet como fallback.
