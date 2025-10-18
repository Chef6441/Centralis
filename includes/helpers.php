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
 * Convert tab-separated lines of NMI data into structured rows.
 *
 * The expected column order is:
 * 0 => Site/Branch label (optional)
 * 1 => NMI (required)
 * 2 => Status
 * 3 => Tariff
 * 4 => DLF
 * 5 => kVA
 * 6 => Average kW demand
 * 7 => Average kVA demand
 * 8 => Average daily consumption
 * 9 => Average daily demand charge
 * 10 => Demand charge
 * 11 => Network charges
 * 12 => Subtotal
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

    $lines = preg_split('/\r\n|\r|\n/', $trimmedInput);

    if ($lines === false) {
        if ($errors !== null) {
            $errors = ['Unable to read the provided NMI data.'];
        }

        return $rows;
    }

    foreach ($lines as $index => $line) {
        $lineNumber = $index + 1;
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        $columns = preg_split('/\t/', $line);
        if ($columns === false) {
            $columns = [$line];
        }

        // Skip a potential header row on the first line.
        if ($index === 0) {
            $possibleHeader = array_map(static fn($value) => strtolower(trim((string) $value)), $columns);
            $headerText = implode(' ', $possibleHeader);
            if (str_contains($headerText, 'nmi')) {
                continue;
            }
        }

        $nmi = trim((string)($columns[1] ?? ''));

        if ($nmi === '') {
            $collectedErrors[] = "Row {$lineNumber} is missing an NMI value.";
            continue;
        }

        $rows[] = [
            'site_label' => trim((string)($columns[0] ?? '')),
            'nmi' => $nmi,
            'status' => trim((string)($columns[2] ?? '')),
            'tariff' => trim((string)($columns[3] ?? '')),
            'dlf' => trim((string)($columns[4] ?? '')),
            'kva' => trim((string)($columns[5] ?? '')),
            'avg_kw_demand' => trim((string)($columns[6] ?? '')),
            'avg_kva_demand' => trim((string)($columns[7] ?? '')),
            'avg_daily_consumption' => trim((string)($columns[8] ?? '')),
            'avg_daily_demand_charge' => trim((string)($columns[9] ?? '')),
            'demand_charge' => trim((string)($columns[10] ?? '')),
            'network_charge' => trim((string)($columns[11] ?? '')),
            'subtotal' => trim((string)($columns[12] ?? '')),
        ];
    }

    if ($trimmedInput !== '' && empty($rows) && empty($collectedErrors)) {
        $collectedErrors[] = 'No valid NMI rows were detected. Ensure each line includes an NMI value.';
    }

    if ($errors !== null) {
        $errors = $collectedErrors;
    }

    return $rows;
}

/**
 * Formats site NMI rows back into a tab separated string for editing.
 */
function formatSiteNmiBulkInput(array $rows): string
{
    if (empty($rows)) {
        return '';
    }

    $header = [
        'Site / Branch',
        'NMI',
        'Status',
        'Tariff',
        'DLF',
        'kVA',
        'Avg kW Demand',
        'Avg kVA Demand',
        'Avg Daily Consumption',
        'Avg Daily Demand Charge',
        'Demand Charge',
        'Network Charges',
        'Subtotal',
    ];

    $lines = [implode("\t", $header)];

    foreach ($rows as $row) {
        $lines[] = implode("\t", [
            $row['site_label'] ?? '',
            $row['nmi'] ?? '',
            $row['status'] ?? '',
            $row['tariff'] ?? '',
            $row['dlf'] ?? '',
            $row['kva'] ?? '',
            $row['avg_kw_demand'] ?? '',
            $row['avg_kva_demand'] ?? '',
            $row['avg_daily_consumption'] ?? '',
            $row['avg_daily_demand_charge'] ?? '',
            $row['demand_charge'] ?? '',
            $row['network_charge'] ?? '',
            $row['subtotal'] ?? '',
        ]);
    }

    return implode("\n", $lines);
}
