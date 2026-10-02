-- Estado operativo de incorporación de consultas a campañas de Mautic.
-- Migración aditiva: no modifica ni elimina snapshots históricos.
ALTER TABLE offer_inquiries
    ADD COLUMN IF NOT EXISTS "mauticCampaignId" INTEGER NULL,
    ADD COLUMN IF NOT EXISTS "mauticCampaignStatus" VARCHAR(32) NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS "mauticCampaignAttemptedAt" TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS "mauticCampaignAttempts" INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS "mauticCampaignLastError" TEXT NULL;

CREATE INDEX IF NOT EXISTS idx_offer_inquiries_mautic_campaign_status
    ON offer_inquiries ("mauticCampaignStatus", "mauticCampaignAttemptedAt");
