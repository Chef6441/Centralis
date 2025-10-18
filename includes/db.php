<?php

declare(strict_types=1);

/**
 * Returns a shared PDO connection to the local SQLite database.
 */
function getDbConnection(): PDO
{
    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $databasePath = __DIR__ . '/../data/centralis.db';
    $databaseDirectory = dirname($databasePath);

    if (!is_dir($databaseDirectory)) {
        mkdir($databaseDirectory, 0777, true);
    }

    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    initializeDatabase($pdo);

    return $pdo;
}

/**
 * Ensures that the required database tables exist by running the schema.sql file
 * when the database is empty.
 */
function initializeDatabase(PDO $pdo): void
{
    $statement = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'reports'");
    $hasReportsTable = $statement !== false && $statement->fetchColumn() !== false;

    if ($hasReportsTable) {
        ensureReportsTableHasCustomerAbn($pdo);
        ensureReportSiteNmisTable($pdo);
        return;
    }

    $schemaPath = __DIR__ . '/../database/schema.sql';
    if (!file_exists($schemaPath)) {
        throw new RuntimeException('Unable to locate database schema at ' . $schemaPath);
    }

    $schemaSql = file_get_contents($schemaPath);
    if ($schemaSql === false) {
        throw new RuntimeException('Unable to read database schema file.');
    }

    $pdo->exec($schemaSql);
    ensureReportsTableHasCustomerAbn($pdo);
    ensureReportSiteNmisTable($pdo);
}

/**
 * Adds the customer_abn column to the reports table when missing.
 */
function ensureReportsTableHasCustomerAbn(PDO $pdo): void
{
    $statement = $pdo->query('PRAGMA table_info(reports)');
    $columns = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];

    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'customer_abn') {
            return;
        }
    }

    $pdo->exec('ALTER TABLE reports ADD COLUMN customer_abn TEXT');
}

/**
 * Creates the report_site_nmis table when it does not exist.
 */
function ensureReportSiteNmisTable(PDO $pdo): void
{
    $statement = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'report_site_nmis'");
    $hasTable = $statement !== false && $statement->fetchColumn() !== false;

    if ($hasTable) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE report_site_nmis (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            report_id INTEGER NOT NULL,
            site_identifier TEXT,
            abn TEXT,
            nmi TEXT NOT NULL,
            utility TEXT,
            building_name TEXT,
            unit TEXT,
            street_number TEXT,
            street TEXT,
            suburb TEXT,
            state TEXT,
            postcode TEXT,
            tariff TEXT,
            annual_estimated_usage_kwh TEXT,
            peak_c_per_kwh TEXT,
            off_peak_c_per_kwh TEXT,
            daily_supply_c_per_day TEXT,
            average_daily_consumption TEXT,
            annual_usage_charge TEXT,
            annual_supply_charge TEXT,
            offer_12_months TEXT,
            offer_24_months TEXT,
            offer_36_months TEXT,
            FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
        )'
    );
}
