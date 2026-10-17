-- Persist the delivery destination so retries never change providers.
ALTER TABLE inquiry_deliveries
    ADD COLUMN IF NOT EXISTS "assignedProvider" VARCHAR(32) NOT NULL DEFAULT 'mautic';

ALTER TABLE inquiry_deliveries
    DROP CONSTRAINT IF EXISTS inquiry_deliveries_provider_check;

ALTER TABLE inquiry_deliveries
    ADD CONSTRAINT inquiry_deliveries_provider_check
    CHECK ("assignedProvider" IN ('mautic', 'flacso_mail'));
