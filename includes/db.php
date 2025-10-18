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
        ensureCoreTablesExist($pdo);
        ensureReportsTableHasAdditionalColumns($pdo);
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
    ensureCoreTablesExist($pdo);
    ensureReportsTableHasAdditionalColumns($pdo);
}

/**
 * Adds optional columns to the reports table when missing.
 */
function ensureReportsTableHasAdditionalColumns(PDO $pdo): void
{
    $statement = $pdo->query('PRAGMA table_info(reports)');
    $columns = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];

    $existingColumns = array_map(static function (array $column): string {
        return (string) ($column['name'] ?? '');
    }, $columns);

    $alterStatements = [
        'customer_abn' => 'ALTER TABLE reports ADD COLUMN customer_abn TEXT',
        'partner_company_name' => 'ALTER TABLE reports ADD COLUMN partner_company_name TEXT',
        'broker_company_name' => 'ALTER TABLE reports ADD COLUMN broker_company_name TEXT',
    ];

    foreach ($alterStatements as $columnName => $sql) {
        if (!in_array($columnName, $existingColumns, true)) {
            $pdo->exec($sql);
        }
    }
}

/**
 * Adds the customer_abn column to the reports table when missing.
 */
function ensureReportsTableHasCustomerAbn(PDO $pdo): void
{
    $statement = $pdo->query('PRAGMA table_info(reports)');
    $columns = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];

    $hasCustomerAbnColumn = array_reduce(
        $columns,
        static function (bool $carry, array $column): bool {
            return $carry || ($column['name'] ?? '') === 'customer_abn';
        },
        false
    );

    if ($hasCustomerAbnColumn) {
        return;
    }

    $pdo->exec('ALTER TABLE reports ADD COLUMN customer_abn TEXT');
}

/**
 * Creates foundational tables when upgrading an existing database.
 */
function ensureCoreTablesExist(PDO $pdo): void
{
    $tableDefinitions = [
        'companies' => <<<SQL
CREATE TABLE companies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    contact_name TEXT,
    contact_email TEXT,
    contact_phone TEXT,
    address TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)
SQL,
        'brokers' => <<<SQL
CREATE TABLE brokers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL UNIQUE,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
)
SQL,
        'partners' => <<<SQL
CREATE TABLE partners (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    broker_id INTEGER NOT NULL,
    company_id INTEGER NOT NULL UNIQUE,
    revenue_share_percentage REAL DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (broker_id) REFERENCES brokers(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
)
SQL,
        'clients' => <<<SQL
CREATE TABLE clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    broker_id INTEGER NOT NULL,
    partner_id INTEGER,
    company_id INTEGER NOT NULL UNIQUE,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (broker_id) REFERENCES brokers(id) ON DELETE CASCADE,
    FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
)
SQL,
    ];

    foreach ($tableDefinitions as $tableName => $createSql) {
        if (!tableExists($pdo, $tableName)) {
            $pdo->exec($createSql);
        }
    }
}

/**
 * Determines whether the specified table exists in the database.
 */
function tableExists(PDO $pdo, string $tableName): bool
{
    $statement = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
    $statement->execute([':name' => $tableName]);

    return $statement->fetchColumn() !== false;
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
