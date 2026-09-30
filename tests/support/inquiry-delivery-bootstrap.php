<?php
/**
 * Bootstrap SQLite compartido para pruebas de cola transaccional.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

function flacso_test_delivery_pdo(): PDO {
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('
    CREATE TABLE offer_inquiries (
        id TEXT PRIMARY KEY, consultaId TEXT UNIQUE, offerWpId INTEGER, offerName TEXT,
        offerAbbreviation TEXT, offerType TEXT, cohortWpId INTEGER, cohortNumber INTEGER, cohortName TEXT,
        registrationOpenAt TEXT, registrationCloseAt TEXT,
        firstName TEXT, lastName TEXT, fullName TEXT, email TEXT, emailNormalized TEXT, country TEXT,
        profession TEXT, educationLevel TEXT, source TEXT, campaignProvider TEXT, campaignSource TEXT,
        campaignMedium TEXT, campaignName TEXT, campaignExternalId TEXT, campaignContent TEXT, campaignTerm TEXT,
        urlBase TEXT, urlReferer TEXT, inquiryAt TEXT, ipAddress TEXT, userAgent TEXT, replyToEmail TEXT,
        programUrl TEXT, cartaUrl TEXT, preinscripcionUrl TEXT, offerStatus TEXT,
        mauticContactId TEXT, mauticSyncStatus TEXT DEFAULT "skipped", mauticSyncedAt TEXT, mauticLastError TEXT,
        followupDueAt TEXT, followupStatus TEXT DEFAULT "none", followupSentAt TEXT,
        followupAttempts INTEGER DEFAULT 0, followupLastError TEXT,
        emailStatus TEXT, emailSender TEXT, gmailMessageUrl TEXT, mailjetMessageId TEXT,
        mailjetMessageUuid TEXT, payload TEXT, createdAt TEXT, updatedAt TEXT
    );

    CREATE TABLE seminar_inquiries (
        id TEXT PRIMARY KEY, consultaId TEXT UNIQUE, seminarWpId INTEGER, seminarName TEXT, seminarType TEXT,
        firstName TEXT, lastName TEXT, fullName TEXT, email TEXT, emailNormalized TEXT, country TEXT,
        profession TEXT, educationLevel TEXT, source TEXT, campaignProvider TEXT, campaignSource TEXT,
        campaignMedium TEXT, campaignName TEXT, campaignExternalId TEXT, campaignContent TEXT, campaignTerm TEXT,
        urlBase TEXT, urlReferer TEXT, inquiryAt TEXT, ipAddress TEXT, userAgent TEXT, replyToEmail TEXT,
        programUrl TEXT, cartaUrl TEXT, preinscripcionUrl TEXT, offerStatus TEXT, emailStatus TEXT,
        emailSender TEXT, gmailMessageUrl TEXT, mailjetMessageId TEXT, mailjetMessageUuid TEXT,
        payload TEXT, createdAt TEXT, updatedAt TEXT
    );

    CREATE TABLE inquiry_snapshots (
        id TEXT PRIMARY KEY,
        inquiryType TEXT NOT NULL,
        inquiryId TEXT NOT NULL,
        consultaId TEXT NOT NULL UNIQUE,
        schemaVersion TEXT NOT NULL,
        snapshotJson TEXT NOT NULL,
        createdAt TEXT NOT NULL,
        UNIQUE(inquiryType, inquiryId)
    );

    CREATE TABLE inquiry_deliveries (
        id TEXT PRIMARY KEY,
        snapshotId TEXT NULL,
        inquiryType TEXT NOT NULL,
        inquiryId TEXT NULL,
        consultaId TEXT NULL,
        deliveryType TEXT NOT NULL DEFAULT "acknowledgement",
        state TEXT NOT NULL DEFAULT "pending",
        email TEXT NULL,
        templateId INTEGER NULL,
        templateVersion TEXT NULL,
        templateSha256 TEXT NULL,
        payloadJson TEXT NULL,
        contactId INTEGER NULL,
        attempts INTEGER NOT NULL DEFAULT 0,
        nextAttemptAt TEXT NULL,
        claimedAt TEXT NULL,
        claimedUntil TEXT NULL,
        claimToken TEXT NULL,
        acceptedAt TEXT NULL,
        terminalAt TEXT NULL,
        lastHttpCode INTEGER NULL,
        lastErrorClass TEXT NULL,
        lastError TEXT NULL,
        reportMonth TEXT NOT NULL,
        anonymizedAt TEXT NULL,
        createdAt TEXT NOT NULL,
        updatedAt TEXT NOT NULL,
        UNIQUE(snapshotId, deliveryType)
    );

    CREATE TABLE inquiry_delivery_attempts (
        id TEXT PRIMARY KEY,
        deliveryId TEXT NOT NULL,
        attemptId TEXT NOT NULL UNIQUE,
        state TEXT NOT NULL,
        startedAt TEXT NOT NULL,
        finishedAt TEXT NULL,
        httpCode INTEGER NULL,
        errorClass TEXT NULL,
        errorMessage TEXT NULL
    );
    ');

    return $pdo;
}
