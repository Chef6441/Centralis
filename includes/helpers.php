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
