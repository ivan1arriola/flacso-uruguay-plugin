-- Migración: Ampliación de offer_inquiries para Cohortes, Snapshots y Mautic
ALTER TABLE offer_inquiries
    ADD COLUMN IF NOT EXISTS "offerAbbreviation" VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS "cohortWpId" INTEGER NULL,
    ADD COLUMN IF NOT EXISTS "cohortNumber" INTEGER NULL,
    ADD COLUMN IF NOT EXISTS "cohortName" VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS "registrationOpenAt" TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS "registrationCloseAt" TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS "mauticContactId" VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS "mauticSyncStatus" VARCHAR(32) NOT NULL DEFAULT 'skipped',
    ADD COLUMN IF NOT EXISTS "mauticSyncedAt" TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS "mauticLastError" TEXT NULL,
    ADD COLUMN IF NOT EXISTS "followupDueAt" TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS "followupStatus" VARCHAR(32) NOT NULL DEFAULT 'none',
    ADD COLUMN IF NOT EXISTS "followupSentAt" TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS "followupAttempts" INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS "followupLastError" TEXT NULL;

CREATE INDEX IF NOT EXISTS idx_offer_inquiries_offer_abbr ON offer_inquiries ("offerAbbreviation");
CREATE INDEX IF NOT EXISTS idx_offer_inquiries_cohort ON offer_inquiries ("cohortWpId");
CREATE INDEX IF NOT EXISTS idx_offer_inquiries_mautic_sync ON offer_inquiries ("mauticSyncStatus");
CREATE INDEX IF NOT EXISTS idx_offer_inquiries_followup ON offer_inquiries ("followupStatus", "followupDueAt");
