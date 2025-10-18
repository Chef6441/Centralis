<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDbConnection();

$formData = [
    'report_date' => '',
    'customer_business_name' => '',
    'customer_contact_name' => '',
    'customer_abn' => '',
    'broker_consultant' => '',
    'site_nmi' => '',
    'site_current_retailer' => '',
    'site_contract_end_date' => '',
    'site_address_line1' => '',
    'site_address_line2' => '',
    'site_peak_kwh' => '',
    'site_shoulder_kwh' => '',
    'site_off_peak_kwh' => '',
    'site_total_kwh' => '',
    'site_nmi_bulk' => '',
    'contract_current_retailer' => '',
    'contract_term_months' => '',
    'current_cost' => '',
    'new_cost' => '',
    'validity_period' => '',
    'payment_terms' => '',
];

$contracts = [
    ['supplier_name' => 'Current', 'term_months' => 12, 'peak_rate' => '', 'shoulder_rate' => '', 'off_peak_rate' => '', 'total_cost' => '', 'diff_dollar' => '', 'diff_percentage' => ''],
    ['supplier_name' => 'Supplier 2', 'term_months' => 12, 'peak_rate' => '', 'shoulder_rate' => '', 'off_peak_rate' => '', 'total_cost' => '', 'diff_dollar' => '', 'diff_percentage' => ''],
    ['supplier_name' => 'Supplier 3', 'term_months' => 12, 'peak_rate' => '', 'shoulder_rate' => '', 'off_peak_rate' => '', 'total_cost' => '', 'diff_dollar' => '', 'diff_percentage' => ''],
];

$otherCosts = [
    ['cost_label' => 'Network Costs', 'cost_amount' => ''],
    ['cost_label' => 'Cost 2', 'cost_amount' => ''],
    ['cost_label' => 'Cost 3', 'cost_amount' => ''],
];

$errors = [];
$reportIdentifier = null;
$siteNmiRows = [];
$siteNmiParseErrors = [];
$siteNmiParseRequested = false;
$siteNmiParseMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($formData) as $key) {
        $formData[$key] = trim((string)($_POST[$key] ?? ''));
    }

    $contracts = array_values($_POST['contracts'] ?? $contracts);
    $otherCosts = array_values($_POST['other_costs'] ?? $otherCosts);

    $siteNmiParseRequested = isset($_POST['parse_site_nmi']);
    $siteNmiRows = parseSiteNmiBulkInput($formData['site_nmi_bulk'], $siteNmiParseErrors);

    if ($formData['site_nmi'] === '' && !empty($siteNmiRows)) {
        $formData['site_nmi'] = $siteNmiRows[0]['nmi'];
    }

    if ($siteNmiParseRequested) {
        if (!empty($siteNmiRows)) {
            $count = count($siteNmiRows);
            $siteNmiParseMessage = sprintf('Parsed %d site%s from the bulk input.', $count, $count === 1 ? '' : 's');
        } else {
            $siteNmiParseMessage = 'No site NMIs were detected in the provided input.';
        }
    } else {
        if (!empty($siteNmiParseErrors)) {
            $errors = array_merge($errors, $siteNmiParseErrors);
        }

        if ($formData['customer_business_name'] === '') {
            $errors[] = 'Customer business name is required.';
        }

        if (empty($errors)) {
            try {
                $reportIdentifier = generateReportIdentifier($pdo);
            } catch (Throwable $exception) {
                $errors[] = 'Unable to generate a report ID. Please try again.';
            }
        }

        if (empty($errors)) {
            $insertReport = $pdo->prepare(
            'INSERT INTO reports (
                report_identifier,
                report_date,
                customer_business_name,
                customer_contact_name,
                customer_abn,
                broker_consultant,
                site_nmi,
                site_current_retailer,
                site_contract_end_date,
                site_address_line1,
                site_address_line2,
                site_peak_kwh,
                site_shoulder_kwh,
                site_off_peak_kwh,
                site_total_kwh,
                contract_current_retailer,
                contract_term_months,
                current_cost,
                new_cost,
                validity_period,
                payment_terms
            ) VALUES (
                :report_identifier,
                :report_date,
                :customer_business_name,
                :customer_contact_name,
                :customer_abn,
                :broker_consultant,
                :site_nmi,
                :site_current_retailer,
                :site_contract_end_date,
                :site_address_line1,
                :site_address_line2,
                :site_peak_kwh,
                :site_shoulder_kwh,
                :site_off_peak_kwh,
                :site_total_kwh,
                :contract_current_retailer,
                :contract_term_months,
                :current_cost,
                :new_cost,
                :validity_period,
                :payment_terms
            )'
        );

            $insertReport->execute([
                ':report_identifier' => $reportIdentifier,
                ':report_date' => $formData['report_date'] ?: null,
                ':customer_business_name' => $formData['customer_business_name'],
                ':customer_contact_name' => $formData['customer_contact_name'] ?: null,
                ':customer_abn' => $formData['customer_abn'] ?: null,
                ':broker_consultant' => $formData['broker_consultant'] ?: null,
                ':site_nmi' => $formData['site_nmi'] ?: null,
                ':site_current_retailer' => $formData['site_current_retailer'] ?: null,
                ':site_contract_end_date' => $formData['site_contract_end_date'] ?: null,
                ':site_address_line1' => $formData['site_address_line1'] ?: null,
                ':site_address_line2' => $formData['site_address_line2'] ?: null,
                ':site_peak_kwh' => $formData['site_peak_kwh'] !== '' ? (float) str_replace(',', '', $formData['site_peak_kwh']) : null,
                ':site_shoulder_kwh' => $formData['site_shoulder_kwh'] !== '' ? (float) str_replace(',', '', $formData['site_shoulder_kwh']) : null,
                ':site_off_peak_kwh' => $formData['site_off_peak_kwh'] !== '' ? (float) str_replace(',', '', $formData['site_off_peak_kwh']) : null,
                ':site_total_kwh' => $formData['site_total_kwh'] !== '' ? (float) str_replace(',', '', $formData['site_total_kwh']) : null,
                ':contract_current_retailer' => $formData['contract_current_retailer'] ?: null,
                ':contract_term_months' => $formData['contract_term_months'] !== '' ? (int) $formData['contract_term_months'] : null,
                ':current_cost' => parseCurrency($formData['current_cost']),
                ':new_cost' => parseCurrency($formData['new_cost']),
                ':validity_period' => $formData['validity_period'] ?: null,
                ':payment_terms' => $formData['payment_terms'] ?: null,
            ]);

            $reportId = (int) $pdo->lastInsertId();

            if (!empty($siteNmiRows)) {
                $insertSiteNmi = $pdo->prepare(
                'INSERT INTO report_site_nmis (
                    report_id,
                    site_identifier,
                    abn,
                    nmi,
                    utility,
                    building_name,
                    unit,
                    street_number,
                    street,
                    suburb,
                    state,
                    postcode,
                    tariff,
                    annual_estimated_usage_kwh,
                    peak_c_per_kwh,
                    off_peak_c_per_kwh,
                    daily_supply_c_per_day,
                    average_daily_consumption,
                    annual_usage_charge,
                    annual_supply_charge,
                    offer_12_months,
                    offer_24_months,
                    offer_36_months
                ) VALUES (
                    :report_id,
                    :site_identifier,
                    :abn,
                    :nmi,
                    :utility,
                    :building_name,
                    :unit,
                    :street_number,
                    :street,
                    :suburb,
                    :state,
                    :postcode,
                    :tariff,
                    :annual_estimated_usage_kwh,
                    :peak_c_per_kwh,
                    :off_peak_c_per_kwh,
                    :daily_supply_c_per_day,
                    :average_daily_consumption,
                    :annual_usage_charge,
                    :annual_supply_charge,
                    :offer_12_months,
                    :offer_24_months,
                    :offer_36_months
                )'
            );

                foreach ($siteNmiRows as $row) {
                    $insertSiteNmi->execute([
                        ':report_id' => $reportId,
                        ':site_identifier' => $row['site_identifier'] ?: null,
                        ':abn' => $row['abn'] ?: null,
                        ':nmi' => $row['nmi'],
                        ':utility' => $row['utility'] ?: null,
                        ':building_name' => $row['building_name'] ?: null,
                        ':unit' => $row['unit'] ?: null,
                        ':street_number' => $row['street_number'] ?: null,
                        ':street' => $row['street'] ?: null,
                        ':suburb' => $row['suburb'] ?: null,
                        ':state' => $row['state'] ?: null,
                        ':postcode' => $row['postcode'] ?: null,
                        ':tariff' => $row['tariff'] ?: null,
                        ':annual_estimated_usage_kwh' => $row['annual_estimated_usage_kwh'] ?: null,
                        ':peak_c_per_kwh' => $row['peak_c_per_kwh'] ?: null,
                        ':off_peak_c_per_kwh' => $row['off_peak_c_per_kwh'] ?: null,
                        ':daily_supply_c_per_day' => $row['daily_supply_c_per_day'] ?: null,
                        ':average_daily_consumption' => $row['average_daily_consumption'] ?: null,
                        ':annual_usage_charge' => $row['annual_usage_charge'] ?: null,
                        ':annual_supply_charge' => $row['annual_supply_charge'] ?: null,
                        ':offer_12_months' => $row['offer_12_months'] ?: null,
                        ':offer_24_months' => $row['offer_24_months'] ?: null,
                        ':offer_36_months' => $row['offer_36_months'] ?: null,
                    ]);
                }
            }

            $insertContract = $pdo->prepare(
            'INSERT INTO contract_offers (
                report_id,
                supplier_name,
                term_months,
                peak_rate,
                shoulder_rate,
                off_peak_rate,
                total_cost,
                diff_dollar,
                diff_percentage
            ) VALUES (
                :report_id,
                :supplier_name,
                :term_months,
                :peak_rate,
                :shoulder_rate,
                :off_peak_rate,
                :total_cost,
                :diff_dollar,
                :diff_percentage
            )'
        );

            foreach ($contracts as $contract) {
                $supplier = trim((string)($contract['supplier_name'] ?? ''));
                $term = isset($contract['term_months']) ? (int) $contract['term_months'] : null;

                if ($supplier === '' || !$term) {
                    continue;
                }

                $insertContract->execute([
                    ':report_id' => $reportId,
                    ':supplier_name' => $supplier,
                    ':term_months' => $term,
                    ':peak_rate' => $contract['peak_rate'] !== '' ? (float) $contract['peak_rate'] : null,
                    ':shoulder_rate' => $contract['shoulder_rate'] !== '' ? (float) $contract['shoulder_rate'] : null,
                    ':off_peak_rate' => $contract['off_peak_rate'] !== '' ? (float) $contract['off_peak_rate'] : null,
                    ':total_cost' => $contract['total_cost'] !== '' ? parseCurrency((string)$contract['total_cost']) : null,
                    ':diff_dollar' => $contract['diff_dollar'] !== '' ? parseCurrency((string)$contract['diff_dollar']) : null,
                    ':diff_percentage' => $contract['diff_percentage'] !== '' ? (float) $contract['diff_percentage'] : null,
                ]);
            }

            $insertOtherCost = $pdo->prepare(
            'INSERT INTO other_costs (report_id, cost_label, cost_amount) VALUES (:report_id, :cost_label, :cost_amount)'
        );

            foreach ($otherCosts as $otherCost) {
                $label = trim((string)($otherCost['cost_label'] ?? ''));
                $amount = isset($otherCost['cost_amount']) ? parseCurrency((string)$otherCost['cost_amount']) : null;

                if ($label === '' || $amount === null) {
                    continue;
                }

                $insertOtherCost->execute([
                    ':report_id' => $reportId,
                    ':cost_label' => $label,
                    ':cost_amount' => $amount,
                ]);
            }

            header('Location: report.php?id=' . $reportId);
            exit;
        }
    }
}

$showSiteNmiPreview = $siteNmiParseRequested || !empty($siteNmiParseErrors);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Energy Report</title>
    <script>
        function addContractRow() {
            const template = document.getElementById('contract-row-template');
            const container = document.getElementById('contract-rows');
            const clone = template.content.cloneNode(true);
            container.appendChild(clone);
        }

        function addOtherCostRow() {
            const template = document.getElementById('other-cost-template');
            const container = document.getElementById('other-cost-rows');
            const clone = template.content.cloneNode(true);
            container.appendChild(clone);
        }
    </script>
</head>
<body>
<header>
    <nav class="navbar-main">
        <a href="index.php">Dashboard</a> |
        <a href="#">Accounts</a> |
        <a href="reports.php">Reports</a> |
        <a href="#">Billing</a> |
        <a href="#">Tasks</a> |
        <a href="#">Settings</a>
    </nav>
    <br>
    <nav class="navbar-sub">
        <a href="reports.php">Back to Reports</a>
    </nav>
    <h1>Create Energy Report</h1>
</header>

<main>
    <section style="padding: 16px;">
        <?php if (!empty($errors)): ?>
            <div>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post">
            <h2>Report Details</h2>
            <div style="padding: 12px 0;">
                <p>
                    <label for="report_date">Report Date</label><br>
                    <input id="report_date" type="date" name="report_date" value="<?= htmlspecialchars($formData['report_date']) ?>">
                </p>
                <p>
                    <label for="customer_business_name">Customer Business Name</label><br>
                    <input id="customer_business_name" type="text" name="customer_business_name" size="40" value="<?= htmlspecialchars($formData['customer_business_name']) ?>" required>
                </p>
                <p>
                    <label for="customer_contact_name">Customer Contact Name</label><br>
                    <input id="customer_contact_name" type="text" name="customer_contact_name" size="40" value="<?= htmlspecialchars($formData['customer_contact_name']) ?>">
                </p>
                <p>
                    <label for="customer_abn">Customer ABN</label><br>
                    <input id="customer_abn" type="text" name="customer_abn" size="20" value="<?= htmlspecialchars($formData['customer_abn']) ?>">
                </p>
                <p>
                    <label for="broker_consultant">Broker Consultant</label><br>
                    <input id="broker_consultant" type="text" name="broker_consultant" size="40" value="<?= htmlspecialchars($formData['broker_consultant']) ?>">
                </p>
            </div>

            <h2>Site Information</h2>
            <div style="padding: 12px 0;">
                <p>
                    <label for="site_nmi">NMI</label><br>
                    <input id="site_nmi" type="text" name="site_nmi" size="40" value="<?= htmlspecialchars($formData['site_nmi']) ?>">
                </p>
                <p>
                    <label for="site_current_retailer">Current Retailer</label><br>
                    <input id="site_current_retailer" type="text" name="site_current_retailer" size="40" value="<?= htmlspecialchars($formData['site_current_retailer']) ?>">
                </p>
                <p>
                    <label for="site_contract_end_date">Contract End Date</label><br>
                    <input id="site_contract_end_date" type="date" name="site_contract_end_date" value="<?= htmlspecialchars($formData['site_contract_end_date']) ?>">
                </p>
                <p>
                    <label for="site_address_line1">Supply Address Line 1</label><br>
                    <input id="site_address_line1" type="text" name="site_address_line1" size="60" value="<?= htmlspecialchars($formData['site_address_line1']) ?>">
                </p>
                <p>
                    <label for="site_address_line2">Supply Address Line 2</label><br>
                    <input id="site_address_line2" type="text" name="site_address_line2" size="60" value="<?= htmlspecialchars($formData['site_address_line2']) ?>">
                </p>
                <p>
                    <label for="site_peak_kwh">Peak kWh</label><br>
                    <input id="site_peak_kwh" type="number" step="1" name="site_peak_kwh" value="<?= htmlspecialchars($formData['site_peak_kwh']) ?>">
                </p>
                <p>
                    <label for="site_shoulder_kwh">Shoulder kWh</label><br>
                    <input id="site_shoulder_kwh" type="number" step="1" name="site_shoulder_kwh" value="<?= htmlspecialchars($formData['site_shoulder_kwh']) ?>">
                </p>
                <p>
                    <label for="site_off_peak_kwh">Off Peak kWh</label><br>
                    <input id="site_off_peak_kwh" type="number" step="1" name="site_off_peak_kwh" value="<?= htmlspecialchars($formData['site_off_peak_kwh']) ?>">
                </p>
                <p>
                    <label for="site_total_kwh">Total kWh</label><br>
                    <input id="site_total_kwh" type="number" step="1" name="site_total_kwh" value="<?= htmlspecialchars($formData['site_total_kwh']) ?>">
                </p>
            </div>

            <h3>Site NMIs</h3>
            <div style="padding: 12px 0;">
                <p>Paste tab-separated site data from Excel. Columns should follow this order: SITE IDENTIFIER, ABN, NMI/MIRN, UTILITY, BUILDING NAME, UNIT, NUMBER, STREET, SUBURB, STATE, POSTCODE, TARIFF, ANNUAL ESTIMATED USAGE (kWh), Peak (c/kWh), Off-Peak (kWh), Daily Supply (c/day), Average Daily Consumption, Annual Usage Charge, Annual Supply Charge, 12 months, 24 months, 36 months. A header row is optional and will be ignored.</p>
                <textarea id="site_nmi_bulk" name="site_nmi_bulk" rows="8" cols="120" placeholder="SITE IDENTIFIER<TAB>ABN<TAB>NMI/MIRN<TAB>..."><?= htmlspecialchars($formData['site_nmi_bulk']) ?></textarea>
                <p>
                    <button type="submit" name="parse_site_nmi" value="1" formnovalidate>Parse</button>
                </p>
                <?php if ($siteNmiParseMessage !== null): ?>
                    <p><strong><?= htmlspecialchars($siteNmiParseMessage) ?></strong></p>
                <?php endif; ?>
                <?php if ($showSiteNmiPreview): ?>
                    <?php if (!empty($siteNmiRows)): ?>
                        <div style="overflow-x: auto;">
                            <table border="1" cellpadding="6" cellspacing="0">
                                <thead>
                                <tr>
                                    <th>SITE IDENTIFIER</th>
                                    <th>ABN</th>
                                    <th>NMI/MIRN</th>
                                    <th>UTILITY</th>
                                    <th>BUILDING NAME</th>
                                    <th>UNIT</th>
                                    <th>NUMBER</th>
                                    <th>STREET</th>
                                    <th>SUBURB</th>
                                    <th>STATE</th>
                                    <th>POSTCODE</th>
                                    <th>TARIFF</th>
                                    <th>ANNUAL ESTIMATED USAGE (kWh)</th>
                                    <th>Peak (c/kWh)</th>
                                    <th>Off-Peak (kWh)</th>
                                    <th>Daily Supply (c/day)</th>
                                    <th>Average Daily Consumption</th>
                                    <th>Annual Usage Charge</th>
                                    <th>Annual Supply Charge</th>
                                    <th>12 months</th>
                                    <th>24 months</th>
                                    <th>36 months</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($siteNmiRows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['site_identifier']) ?></td>
                                        <td><?= htmlspecialchars($row['abn']) ?></td>
                                        <td><?= htmlspecialchars($row['nmi']) ?></td>
                                        <td><?= htmlspecialchars($row['utility']) ?></td>
                                        <td><?= htmlspecialchars($row['building_name']) ?></td>
                                        <td><?= htmlspecialchars($row['unit']) ?></td>
                                        <td><?= htmlspecialchars($row['street_number']) ?></td>
                                        <td><?= htmlspecialchars($row['street']) ?></td>
                                        <td><?= htmlspecialchars($row['suburb']) ?></td>
                                        <td><?= htmlspecialchars($row['state']) ?></td>
                                        <td><?= htmlspecialchars($row['postcode']) ?></td>
                                        <td><?= htmlspecialchars($row['tariff']) ?></td>
                                        <td><?= htmlspecialchars($row['annual_estimated_usage_kwh']) ?></td>
                                        <td><?= htmlspecialchars($row['peak_c_per_kwh']) ?></td>
                                        <td><?= htmlspecialchars($row['off_peak_c_per_kwh']) ?></td>
                                        <td><?= htmlspecialchars($row['daily_supply_c_per_day']) ?></td>
                                        <td><?= htmlspecialchars($row['average_daily_consumption']) ?></td>
                                        <td><?= htmlspecialchars($row['annual_usage_charge']) ?></td>
                                        <td><?= htmlspecialchars($row['annual_supply_charge']) ?></td>
                                        <td><?= htmlspecialchars($row['offer_12_months']) ?></td>
                                        <td><?= htmlspecialchars($row['offer_24_months']) ?></td>
                                        <td><?= htmlspecialchars($row['offer_36_months']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($siteNmiParseRequested || !empty($siteNmiParseErrors)): ?>
                        <p>No site NMIs could be parsed.</p>
                    <?php endif; ?>
                    <?php if (!empty($siteNmiParseErrors)): ?>
                        <div style="margin-top: 8px;">
                            <strong>Errors</strong>
                            <ul>
                                <?php foreach ($siteNmiParseErrors as $parseError): ?>
                                    <li><?= htmlspecialchars($parseError) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <h2>Current Contract</h2>
            <div style="padding: 12px 0;">
                <p>
                    <label for="contract_current_retailer">Current Retailer</label><br>
                    <input id="contract_current_retailer" type="text" name="contract_current_retailer" size="40" value="<?= htmlspecialchars($formData['contract_current_retailer']) ?>">
                </p>
                <p>
                    <label for="contract_term_months">Term (months)</label><br>
                    <input id="contract_term_months" type="number" step="1" name="contract_term_months" value="<?= htmlspecialchars($formData['contract_term_months']) ?>">
                </p>
                <p>
                    <label for="current_cost">Current Cost</label><br>
                    <input id="current_cost" type="text" name="current_cost" size="20" value="<?= htmlspecialchars($formData['current_cost']) ?>">
                </p>
                <p>
                    <label for="new_cost">New Cost</label><br>
                    <input id="new_cost" type="text" name="new_cost" size="20" value="<?= htmlspecialchars($formData['new_cost']) ?>">
                </p>
                <p>
                    <label for="validity_period">Validity Period</label><br>
                    <input id="validity_period" type="text" name="validity_period" size="20" value="<?= htmlspecialchars($formData['validity_period']) ?>">
                </p>
                <p>
                    <label for="payment_terms">Payment Terms</label><br>
                    <input id="payment_terms" type="text" name="payment_terms" size="20" value="<?= htmlspecialchars($formData['payment_terms']) ?>">
                </p>
            </div>

            <h2>Contract Offers</h2>
            <div style="padding: 12px 0;">
                <p>
                    <button type="button" onclick="addContractRow()">Add Contract Offer</button>
                </p>
                <table border="1" cellpadding="6" cellspacing="0">
                    <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Term (months)</th>
                        <th>Peak Rate</th>
                        <th>Shoulder Rate</th>
                        <th>Off Peak Rate</th>
                        <th>Total Cost</th>
                        <th>$ Diff</th>
                        <th>% Diff</th>
                    </tr>
                    </thead>
                    <tbody id="contract-rows">
                    <?php foreach ($contracts as $index => $contract): ?>
                        <tr>
                            <td><input type="text" name="contracts[<?= $index ?>][supplier_name]" value="<?= htmlspecialchars($contract['supplier_name'] ?? '') ?>"></td>
                            <td>
                                <select name="contracts[<?= $index ?>][term_months]">
                                    <option value="">Select</option>
                                    <?php foreach ([12, 24, 36] as $term): ?>
                                        <option value="<?= $term ?>" <?= isset($contract['term_months']) && (int)$contract['term_months'] === $term ? 'selected' : '' ?>><?= $term ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" step="0.00001" name="contracts[<?= $index ?>][peak_rate]" value="<?= htmlspecialchars($contract['peak_rate'] ?? '') ?>"></td>
                            <td><input type="number" step="0.00001" name="contracts[<?= $index ?>][shoulder_rate]" value="<?= htmlspecialchars($contract['shoulder_rate'] ?? '') ?>"></td>
                            <td><input type="number" step="0.00001" name="contracts[<?= $index ?>][off_peak_rate]" value="<?= htmlspecialchars($contract['off_peak_rate'] ?? '') ?>"></td>
                            <td><input type="text" name="contracts[<?= $index ?>][total_cost]" value="<?= htmlspecialchars($contract['total_cost'] ?? '') ?>"></td>
                            <td><input type="text" name="contracts[<?= $index ?>][diff_dollar]" value="<?= htmlspecialchars($contract['diff_dollar'] ?? '') ?>"></td>
                            <td><input type="number" step="0.01" name="contracts[<?= $index ?>][diff_percentage]" value="<?= htmlspecialchars($contract['diff_percentage'] ?? '') ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h2>Other Costs</h2>
            <div style="padding: 12px 0;">
                <p>
                    <button type="button" onclick="addOtherCostRow()">Add Other Cost</button>
                </p>
                <table border="1" cellpadding="6" cellspacing="0">
                    <thead>
                    <tr>
                        <th>Description</th>
                        <th>Amount</th>
                    </tr>
                    </thead>
                    <tbody id="other-cost-rows">
                    <?php foreach ($otherCosts as $index => $otherCost): ?>
                        <tr>
                            <td><input type="text" name="other_costs[<?= $index ?>][cost_label]" value="<?= htmlspecialchars($otherCost['cost_label'] ?? '') ?>"></td>
                            <td><input type="text" name="other_costs[<?= $index ?>][cost_amount]" value="<?= htmlspecialchars($otherCost['cost_amount'] ?? '') ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p>
                <button type="submit">Save Report</button>
            </p>
        </form>
    </section>
</main>

<template id="contract-row-template">
    <tr>
        <td><input type="text" name="contracts[][supplier_name]"></td>
        <td>
            <select name="contracts[][term_months]">
                <option value="">Select</option>
                <option value="12">12</option>
                <option value="24">24</option>
                <option value="36">36</option>
            </select>
        </td>
        <td><input type="number" step="0.00001" name="contracts[][peak_rate]"></td>
        <td><input type="number" step="0.00001" name="contracts[][shoulder_rate]"></td>
        <td><input type="number" step="0.00001" name="contracts[][off_peak_rate]"></td>
        <td><input type="text" name="contracts[][total_cost]"></td>
        <td><input type="text" name="contracts[][diff_dollar]"></td>
        <td><input type="number" step="0.01" name="contracts[][diff_percentage]"></td>
    </tr>
</template>

<template id="other-cost-template">
    <tr>
        <td><input type="text" name="other_costs[][cost_label]"></td>
        <td><input type="text" name="other_costs[][cost_amount]"></td>
    </tr>
</template>

</body>
</html>
