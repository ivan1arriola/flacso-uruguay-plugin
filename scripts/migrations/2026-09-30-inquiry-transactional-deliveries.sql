-- Consultas: snapshots inmutables y cola transaccional.
-- Migración aditiva; no elimina ni modifica datos históricos.

CREATE TABLE IF NOT EXISTS inquiry_snapshots (
    id VARCHAR(25) PRIMARY KEY,
    "inquiryType" VARCHAR(16) NOT NULL,
    "inquiryId" VARCHAR(25) NOT NULL,
    "consultaId" VARCHAR(128) NOT NULL,
    "schemaVersion" VARCHAR(32) NOT NULL,
    "snapshotJson" JSONB NOT NULL,
    "createdAt" TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE ("inquiryType", "inquiryId"),
    UNIQUE ("consultaId")
);

CREATE TABLE IF NOT EXISTS inquiry_deliveries (
    id VARCHAR(25) PRIMARY KEY,
    "snapshotId" VARCHAR(25) NULL REFERENCES inquiry_snapshots(id) ON DELETE SET NULL,
    "inquiryType" VARCHAR(16) NOT NULL,
    "inquiryId" VARCHAR(25) NULL,
    "consultaId" VARCHAR(128) NULL,
    "deliveryType" VARCHAR(32) NOT NULL DEFAULT 'acknowledgement',
    state VARCHAR(32) NOT NULL DEFAULT 'pending',
    email TEXT NULL,
    "templateId" INTEGER NULL,
    "templateVersion" VARCHAR(64) NULL,
    "templateSha256" VARCHAR(64) NULL,
    "payloadJson" JSONB NULL,
    "contactId" BIGINT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    "nextAttemptAt" TIMESTAMPTZ NULL,
    "claimedAt" TIMESTAMPTZ NULL,
    "claimedUntil" TIMESTAMPTZ NULL,
    "claimToken" VARCHAR(64) NULL,
    "acceptedAt" TIMESTAMPTZ NULL,
    "terminalAt" TIMESTAMPTZ NULL,
    "lastHttpCode" INTEGER NULL,
    "lastErrorClass" VARCHAR(64) NULL,
    "lastError" TEXT NULL,
    "reportMonth" DATE NOT NULL,
    "anonymizedAt" TIMESTAMPTZ NULL,
    "createdAt" TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    "updatedAt" TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT inquiry_deliveries_state_check CHECK (
        state IN ('pending','processing','accepted','acceptance_unknown','retryable_failed','failed','blocked')
    ),
    UNIQUE ("snapshotId", "deliveryType")
);

CREATE TABLE IF NOT EXISTS inquiry_delivery_attempts (
    id VARCHAR(25) PRIMARY KEY,
    "deliveryId" VARCHAR(25) NOT NULL REFERENCES inquiry_deliveries(id) ON DELETE CASCADE,
    "attemptId" VARCHAR(64) NOT NULL UNIQUE,
    state VARCHAR(32) NOT NULL,
    "startedAt" TIMESTAMPTZ NOT NULL,
    "finishedAt" TIMESTAMPTZ NULL,
    "httpCode" INTEGER NULL,
    "errorClass" VARCHAR(64) NULL,
    "errorMessage" TEXT NULL
);

CREATE INDEX IF NOT EXISTS idx_inquiry_deliveries_pending
    ON inquiry_deliveries (state, "nextAttemptAt", "createdAt");

CREATE INDEX IF NOT EXISTS idx_inquiry_deliveries_claim
    ON inquiry_deliveries (state, "claimedUntil");

CREATE INDEX IF NOT EXISTS idx_inquiry_deliveries_reconciliation
    ON inquiry_deliveries (state, "updatedAt");

CREATE INDEX IF NOT EXISTS idx_inquiry_deliveries_retention
    ON inquiry_deliveries ("terminalAt", "anonymizedAt");

CREATE INDEX IF NOT EXISTS idx_inquiry_delivery_attempts_delivery
    ON inquiry_delivery_attempts ("deliveryId", "startedAt");
