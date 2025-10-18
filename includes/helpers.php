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

/**
 * Convert tab-separated lines of site data into structured rows.
 *
 * The expected column order is:
 * 0  => Site Identifier
 * 1  => ABN
 * 2  => NMI / MIRN (required)
 * 3  => Utility
 * 4  => Building Name
 * 5  => Unit
 * 6  => Street Number
 * 7  => Street
 * 8  => Suburb
 * 9  => State
 * 10 => Postcode
 * 11 => Tariff
 * 12 => Annual Estimated Usage (kWh)
 * 13 => Peak (c/kWh)
 * 14 => Off-Peak (kWh)
 * 15 => Daily Supply (c/day)
 * 16 => Average Daily Consumption
 * 17 => Annual Usage Charge
 * 18 => Annual Supply Charge
 * 19 => 12 months
 * 20 => 24 months
 * 21 => 36 months
 */
function parseSiteNmiBulkInput(string $input, ?array &$errors = null): array
{
    $rows = [];
    $collectedErrors = [];
    $trimmedInput = trim($input);

    if ($trimmedInput === '') {
        if ($errors !== null) {
            $errors = [];
        }

        return $rows;
    }

    $handle = fopen('php://temp', 'r+');
    if ($handle === false) {
        if ($errors !== null) {
            $errors = ['Unable to process the provided NMI data.'];
        }

        return $rows;
    }

    if (fwrite($handle, $trimmedInput) === false) {
        fclose($handle);

        if ($errors !== null) {
            $errors = ['Unable to process the provided NMI data.'];
        }

        return $rows;
    }

    rewind($handle);

    $columnKeys = [
        'site_identifier',
        'abn',
        'nmi',
        'utility',
        'building_name',
        'unit',
        'street_number',
        'street',
        'suburb',
        'state',
        'postcode',
        'tariff',
        'annual_estimated_usage_kwh',
        'peak_c_per_kwh',
        'off_peak_c_per_kwh',
        'daily_supply_c_per_day',
        'average_daily_consumption',
        'annual_usage_charge',
        'annual_supply_charge',
        'offer_12_months',
        'offer_24_months',
        'offer_36_months',
    ];

    $lineNumber = 0;
    while (($columns = fgetcsv($handle, 0, "\t", '"', '\\')) !== false) {
        $lineNumber++;

        if ($columns === null) {
            continue;
        }

        $trimmedColumns = array_map(
            static function ($value) {
                return trim((string) $value);
            },
            $columns
        );

        $nonEmptyValues = array_filter(
            $trimmedColumns,
            static function ($value) {
                return $value !== '';
            }
        );

        if (count($nonEmptyValues) === 0) {
            continue;
        }

        if ($lineNumber === 1) {
            $headerText = strtolower(implode(' ', $trimmedColumns));
            if (strpos($headerText, 'nmi') !== false) {
                continue;
            }
        }

        $columns = array_pad($trimmedColumns, count($columnKeys), '');
        $nmi = $columns[2] ?? '';

        if ($nmi === '') {
            $collectedErrors[] = "Row {$lineNumber} is missing an NMI/MIRN value.";
            continue;
        }

        $row = [];
        foreach ($columnKeys as $index => $key) {
            $row[$key] = $columns[$index] ?? '';
        }

        $rows[] = $row;
    }

    fclose($handle);

    if ($trimmedInput !== '' && empty($rows) && empty($collectedErrors)) {
        $collectedErrors[] = 'No valid NMI rows were detected. Ensure each line includes an NMI/MIRN value.';
    }

    if ($errors !== null) {
        $errors = $collectedErrors;
    }

    return $rows;
}

// Formats site data rows back into a tab separated string for editing.
function formatSiteNmiBulkInput(array $rows): string
{
    if (empty($rows)) {
        return '';
    }

    $header = [
        'SITE IDENTIFIER',
        'ABN',
        'NMI/MIRN',
        'UTILITY',
        'BUILDING NAME',
        'UNIT',
        'NUMBER',
        'STREET',
        'SUBURB',
        'STATE',
        'POSTCODE',
        'TARIFF',
        'ANNUAL ESTIMATED USAGE (kWh)',
        'Peak (c/kWh)',
        'Off-Peak (kWh)',
        'Daily Supply (c/day)',
        'Average Daily Consumption',
        'Annual Usage Charge',
        'Annual Supply Charge',
        '12 months',
        '24 months',
        '36 months',
    ];

    $lines = [implode("\t", $header)];

    foreach ($rows as $row) {
        $lines[] = implode("\t", [
            $row['site_identifier'] ?? '',
            $row['abn'] ?? '',
            $row['nmi'] ?? '',
            $row['utility'] ?? '',
            $row['building_name'] ?? '',
            $row['unit'] ?? '',
            $row['street_number'] ?? '',
            $row['street'] ?? '',
            $row['suburb'] ?? '',
            $row['state'] ?? '',
            $row['postcode'] ?? '',
            $row['tariff'] ?? '',
            $row['annual_estimated_usage_kwh'] ?? '',
            $row['peak_c_per_kwh'] ?? '',
            $row['off_peak_c_per_kwh'] ?? '',
            $row['daily_supply_c_per_day'] ?? '',
            $row['average_daily_consumption'] ?? '',
            $row['annual_usage_charge'] ?? '',
            $row['annual_supply_charge'] ?? '',
            $row['offer_12_months'] ?? '',
            $row['offer_24_months'] ?? '',
            $row['offer_36_months'] ?? '',
        ]);
    }

    return implode("\n", $lines);
}

/**
 * Builds a return path that preserves the current stakeholder selections.
 */
function buildStakeholderReturnPath(array $formData, string $basePath): string
{
    $prefillKeys = [
        'customer_business_name',
        'customer_contact_name',
        'customer_abn',
        'partner_company_name',
        'partner_contact_name',
        'broker_company_name',
        'broker_consultant',
    ];

    $prefill = [];

    foreach ($prefillKeys as $key) {
        if (!empty($formData[$key])) {
            $prefill[$key] = $formData[$key];
        }
    }

    if (empty($prefill)) {
        return $basePath;
    }

    $separator = strpos($basePath, '?') !== false ? '&' : '?';

    return $basePath . $separator . http_build_query(['prefill' => $prefill]);
}
