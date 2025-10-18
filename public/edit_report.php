<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDbConnection();

$reportId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$reportQuery = $pdo->prepare('SELECT * FROM reports WHERE id = :id');
$reportQuery->execute([':id' => $reportId]);
$report = $reportQuery->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    http_response_code(404);
    echo '<p>Report not found.</p>';
    exit;
}

function toInputValue($value): string
{
    if ($value === null) {
        return '';
    }

    if (is_float($value)) {
        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }

    return (string) $value;
}

$formData = [
    'report_date' => toInputValue($report['report_date'] ?? ''),
    'customer_business_name' => toInputValue($report['customer_business_name'] ?? ''),
    'customer_contact_name' => toInputValue($report['customer_contact_name'] ?? ''),
    'customer_abn' => toInputValue($report['customer_abn'] ?? ''),
    'partner_company_name' => toInputValue($report['partner_company_name'] ?? ''),
    'partner_contact_name' => '',
    'broker_company_name' => toInputValue($report['broker_company_name'] ?? ''),
    'broker_consultant' => toInputValue($report['broker_consultant'] ?? ''),
    'site_nmi' => toInputValue($report['site_nmi'] ?? ''),
    'site_current_retailer' => toInputValue($report['site_current_retailer'] ?? ''),
    'site_contract_end_date' => toInputValue($report['site_contract_end_date'] ?? ''),
    'site_address_line1' => toInputValue($report['site_address_line1'] ?? ''),
    'site_address_line2' => toInputValue($report['site_address_line2'] ?? ''),
    'site_peak_kwh' => toInputValue($report['site_peak_kwh'] ?? ''),
    'site_shoulder_kwh' => toInputValue($report['site_shoulder_kwh'] ?? ''),
    'site_off_peak_kwh' => toInputValue($report['site_off_peak_kwh'] ?? ''),
    'site_total_kwh' => toInputValue($report['site_total_kwh'] ?? ''),
    'site_nmi_bulk' => '',
    'contract_current_retailer' => toInputValue($report['contract_current_retailer'] ?? ''),
    'contract_term_months' => toInputValue($report['contract_term_months'] ?? ''),
    'current_cost' => $report['current_cost'] !== null ? number_format((float)$report['current_cost'], 2, '.', '') : '',
    'new_cost' => $report['new_cost'] !== null ? number_format((float)$report['new_cost'], 2, '.', '') : '',
    'validity_period' => toInputValue($report['validity_period'] ?? ''),
    'payment_terms' => toInputValue($report['payment_terms'] ?? ''),
];

$contractsQuery = $pdo->prepare('SELECT * FROM contract_offers WHERE report_id = :id ORDER BY term_months, supplier_name');
$contractsQuery->execute([':id' => $reportId]);
$contracts = $contractsQuery->fetchAll(PDO::FETCH_ASSOC);

$otherCostsQuery = $pdo->prepare('SELECT cost_label, cost_amount FROM other_costs WHERE report_id = :id');
$otherCostsQuery->execute([':id' => $reportId]);
$otherCosts = $otherCostsQuery->fetchAll(PDO::FETCH_ASSOC);

$siteNmiQuery = $pdo->prepare('SELECT * FROM report_site_nmis WHERE report_id = :id ORDER BY id');
$siteNmiQuery->execute([':id' => $reportId]);
$siteNmiRows = $siteNmiQuery->fetchAll(PDO::FETCH_ASSOC);

if ($formData['site_nmi_bulk'] === '') {
    $formData['site_nmi_bulk'] = formatSiteNmiBulkInput($siteNmiRows);
}

if (!$contracts) {
    $contracts = [
        ['supplier_name' => 'Current', 'term_months' => 12, 'peak_rate' => '', 'shoulder_rate' => '', 'off_peak_rate' => '', 'total_cost' => '', 'diff_dollar' => '', 'diff_percentage' => ''],
    ];
}

if (!$otherCosts) {
    $otherCosts = [
        ['cost_label' => 'Network Costs', 'cost_amount' => ''],
    ];
}

$errors = [];
$siteNmiParseErrors = [];
$siteNmiParseRequested = false;
$siteNmiParseMessage = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['prefill']) && is_array($_GET['prefill'])) {
    foreach ($_GET['prefill'] as $key => $value) {
        if (array_key_exists($key, $formData)) {
            $formData[$key] = trim((string) $value);
        }
    }
}

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
    if ($formData['customer_business_name'] === '') {
        $errors[] = 'Please select a customer before updating the report.';
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
                $pdo->beginTransaction();

                $updateReport = $pdo->prepare(
                'UPDATE reports SET
                    report_date = :report_date,
                    customer_business_name = :customer_business_name,
                    customer_contact_name = :customer_contact_name,
                    customer_abn = :customer_abn,
                    partner_company_name = :partner_company_name,
                    broker_company_name = :broker_company_name,
                    broker_consultant = :broker_consultant,
                    site_nmi = :site_nmi,
                    site_current_retailer = :site_current_retailer,
                    site_contract_end_date = :site_contract_end_date,
                    site_address_line1 = :site_address_line1,
                    site_address_line2 = :site_address_line2,
                    site_peak_kwh = :site_peak_kwh,
                    site_shoulder_kwh = :site_shoulder_kwh,
                    site_off_peak_kwh = :site_off_peak_kwh,
                    site_total_kwh = :site_total_kwh,
                    contract_current_retailer = :contract_current_retailer,
                    contract_term_months = :contract_term_months,
                    current_cost = :current_cost,
                    new_cost = :new_cost,
                    validity_period = :validity_period,
                    payment_terms = :payment_terms
                WHERE id = :id'
            );

                $updateReport->execute([
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
                    ':site_peak_kwh' => $formData['site_peak_kwh'] !== '' ? (float) $formData['site_peak_kwh'] : null,
                    ':site_shoulder_kwh' => $formData['site_shoulder_kwh'] !== '' ? (float) $formData['site_shoulder_kwh'] : null,
                    ':site_off_peak_kwh' => $formData['site_off_peak_kwh'] !== '' ? (float) $formData['site_off_peak_kwh'] : null,
                    ':site_total_kwh' => $formData['site_total_kwh'] !== '' ? (float) $formData['site_total_kwh'] : null,
                    ':contract_current_retailer' => $formData['contract_current_retailer'] ?: null,
                    ':contract_term_months' => $formData['contract_term_months'] !== '' ? (int) $formData['contract_term_months'] : null,
                    ':current_cost' => parseCurrency($formData['current_cost']),
                    ':new_cost' => parseCurrency($formData['new_cost']),
                    ':validity_period' => $formData['validity_period'] ?: null,
                    ':payment_terms' => $formData['payment_terms'] ?: null,
                    ':id' => $reportId,
                ]);

                $pdo->prepare('DELETE FROM contract_offers WHERE report_id = :report_id')->execute([':report_id' => $reportId]);
                $pdo->prepare('DELETE FROM other_costs WHERE report_id = :report_id')->execute([':report_id' => $reportId]);
                $pdo->prepare('DELETE FROM report_site_nmis WHERE report_id = :report_id')->execute([':report_id' => $reportId]);

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

                $pdo->commit();

                header('Location: report.php?id=' . $reportId);
                exit;
            } catch (Throwable $exception) {
                $pdo->rollBack();
                $errors[] = 'Unable to update the report. Please try again.';
            }
        }
    }
}

$showSiteNmiPreview = $siteNmiParseRequested || !empty($siteNmiParseErrors);
$returnToPath = buildStakeholderReturnPath($formData, 'edit_report.php?id=' . $reportId);
$selectCustomerUrl = 'add_customer.php?return_to=' . rawurlencode($returnToPath);
$selectPartnerUrl = 'add_partner.php?return_to=' . rawurlencode($returnToPath);
$selectBrokerUrl = 'add_broker.php?return_to=' . rawurlencode($returnToPath);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Energy Report</title>
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
        <a href="report.php?id=<?= urlencode($reportId) ?>">View Report</a> |
        <a href="reports.php">Back to Reports</a>
    </nav>
    <h1>Edit Energy Report</h1>
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
            <input type="hidden" name="report_date" value="<?= htmlspecialchars($formData['report_date']) ?>">

            <h2>Report Details</h2>
            <div style="padding: 12px 0;">
                <div style="margin-bottom: 16px;">
                    <h3>Customers</h3>
                    <?php
                    $customerSummary = '';
                    if ($formData['customer_business_name'] !== '') {
                        $customerSummary = $formData['customer_business_name'];
                        if ($formData['customer_contact_name'] !== '') {
                            $customerSummary .= ' - ' . $formData['customer_contact_name'];
                        }
                    }
                    ?>
                    <p>
                        <input
                            type="text"
                            value="<?= htmlspecialchars($customerSummary) ?>"
                            readonly
                            style="width: 100%; max-width: 360px;"
                        >
                        <a href="<?= htmlspecialchars($selectCustomerUrl) ?>" style="margin-left: 8px;">Select Customer</a>
                    </p>
                    <input type="hidden" name="customer_business_name" value="<?= htmlspecialchars($formData['customer_business_name']) ?>">
                    <input type="hidden" name="customer_contact_name" value="<?= htmlspecialchars($formData['customer_contact_name']) ?>">
                    <input type="hidden" name="customer_abn" value="<?= htmlspecialchars($formData['customer_abn']) ?>">
                </div>

                <div style="margin-bottom: 16px;">
                    <h3>Partner</h3>
                    <?php
                    $partnerSummary = '';
                    if ($formData['partner_company_name'] !== '') {
                        $partnerSummary = $formData['partner_company_name'];
                        if ($formData['partner_contact_name'] !== '') {
                            $partnerSummary .= ' - ' . $formData['partner_contact_name'];
                        }
                    }
                    ?>
                    <p>
                        <input
                            type="text"
                            value="<?= htmlspecialchars($partnerSummary) ?>"
                            readonly
                            style="width: 100%; max-width: 360px;"
                        >
                        <a href="<?= htmlspecialchars($selectPartnerUrl) ?>" style="margin-left: 8px;">Select Partner</a>
                    </p>
                    <input type="hidden" name="partner_company_name" value="<?= htmlspecialchars($formData['partner_company_name']) ?>">
                    <input type="hidden" name="partner_contact_name" value="<?= htmlspecialchars($formData['partner_contact_name']) ?>">
                </div>

                <div>
                    <h3>Broker</h3>
                    <?php
                    $brokerSummary = '';
                    if ($formData['broker_company_name'] !== '') {
                        $brokerSummary = $formData['broker_company_name'];
                        if ($formData['broker_consultant'] !== '') {
                            $brokerSummary .= ' - ' . $formData['broker_consultant'];
                        }
                    }
                    ?>
                    <p>
                        <input
                            type="text"
                            value="<?= htmlspecialchars($brokerSummary) ?>"
                            readonly
                            style="width: 100%; max-width: 360px;"
                        >
                        <a href="<?= htmlspecialchars($selectBrokerUrl) ?>" style="margin-left: 8px;">Select Broker</a>
                    </p>
                    <input type="hidden" name="broker_company_name" value="<?= htmlspecialchars($formData['broker_company_name']) ?>">
                    <input type="hidden" name="broker_consultant" value="<?= htmlspecialchars($formData['broker_consultant']) ?>">
                </div>
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
                            <td><input type="number" step="0.00001" name="contracts[<?= $index ?>][peak_rate]" value="<?= htmlspecialchars(toInputValue($contract['peak_rate'] ?? '')) ?>"></td>
                            <td><input type="number" step="0.00001" name="contracts[<?= $index ?>][shoulder_rate]" value="<?= htmlspecialchars(toInputValue($contract['shoulder_rate'] ?? '')) ?>"></td>
                            <td><input type="number" step="0.00001" name="contracts[<?= $index ?>][off_peak_rate]" value="<?= htmlspecialchars(toInputValue($contract['off_peak_rate'] ?? '')) ?>"></td>
                            <td><input type="text" name="contracts[<?= $index ?>][total_cost]" value="<?= htmlspecialchars($contract['total_cost'] !== null ? number_format((float)$contract['total_cost'], 2, '.', '') : '') ?>"></td>
                            <td><input type="text" name="contracts[<?= $index ?>][diff_dollar]" value="<?= htmlspecialchars($contract['diff_dollar'] !== null ? number_format((float)$contract['diff_dollar'], 2, '.', '') : '') ?>"></td>
                            <td><input type="number" step="0.01" name="contracts[<?= $index ?>][diff_percentage]" value="<?= htmlspecialchars(toInputValue($contract['diff_percentage'] ?? '')) ?>"></td>
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
                            <td><input type="text" name="other_costs[<?= $index ?>][cost_amount]" value="<?= htmlspecialchars($otherCost['cost_amount'] !== null ? number_format((float)$otherCost['cost_amount'], 2, '.', '') : '') ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p>
                <button type="submit">Update Report</button>
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
