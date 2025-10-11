<?php

declare(strict_types=1);

/**
 * Removes currency formatting and converts the value to a float.
 */
function parseCurrency(?string $value): ?float
{
    if ($value === null) {
        return null;
    }

    $cleanValue = preg_replace('/[^0-9.\-]/', '', $value);

    if ($cleanValue === '' || $cleanValue === null) {
        return null;
    }

    return (float) $cleanValue;
}

/**
 * Formats a numeric value as Australian currency.
 */
function formatCurrency(?float $amount): string
{
    if ($amount === null) {
        return 'N/A';
    }

    return '$' . number_format($amount, 2);
}

/**
 * Formats a numeric value as kWh with thousands separators.
 */
function formatKwh(?float $value): string
{
    if ($value === null) {
        return 'N/A';
    }

    return number_format($value) . ' kWh';
}

/**
 * Formats a number as a percentage string.
 */
function formatPercentage(?float $value): string
{
    if ($value === null) {
        return 'N/A';
    }

    return rtrim(rtrim(number_format($value, 2), '0'), '.') . '%';
}

/**
 * Formats a date string from YYYY-MM-DD to `d M Y`.
 */
function formatDisplayDate(?string $value): string
{
    if (!$value) {
        return 'N/A';
    }

    $date = DateTime::createFromFormat('Y-m-d', $value) ?: DateTime::createFromFormat('d/m/Y', $value);
    if ($date === false) {
        return $value;
    }

    return $date->format('d M Y');
}

/**
 * Generates a unique report identifier with a predictable prefix.
 *
 * @throws RuntimeException When a unique identifier cannot be generated.
 */
function generateReportIdentifier(PDO $pdo): string
{
    $maxAttempts = 5;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $identifier = 'RPT-' . strtoupper(bin2hex(random_bytes(4)));

        $statement = $pdo->prepare('SELECT 1 FROM reports WHERE report_identifier = :identifier LIMIT 1');
        $statement->execute([':identifier' => $identifier]);

        if ($statement->fetchColumn() === false) {
            return $identifier;
        }
    }

    throw new RuntimeException('Unable to generate a unique report identifier.');
}
