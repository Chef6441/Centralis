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
