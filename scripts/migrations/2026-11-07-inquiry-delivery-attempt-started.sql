-- Marca atómica de "envío autorizado" para que la recuperación de reservas vencidas
-- y el inicio del intento se excluyan entre sí en la misma fila.
--
-- ORDEN DE DESPLIEGUE: 1) pausar el cron de la cola (o detener workers antiguos),
-- 2) aplicar esta migración, 3) desplegar el código, 4) reanudar el cron.
ALTER TABLE inquiry_deliveries
    ADD COLUMN IF NOT EXISTS "attemptStartedAt" TIMESTAMPTZ NULL;

-- Las entregas que están procesándose con la versión anterior pudieron haber enviado
-- ya el correo sin dejar marca. Se tratan como "envío iniciado": si su reserva vence
-- pasarán a acceptance_unknown (revisión manual) y nunca se reenviarán solas.
UPDATE inquiry_deliveries
   SET "attemptStartedAt" = COALESCE("claimedAt", NOW())
 WHERE state = 'processing' AND "attemptStartedAt" IS NULL;
