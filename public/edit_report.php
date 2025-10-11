<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDbConnection();

$reportId = (int) ($_GET['id'] ?? $_POST['report_id'] ?? 0);
if ($reportId <= 0) {
    http_response_code(400);
    echo '<p>Invalid report reference.</p>';
    exit;
}

$reportQuery = $pdo->prepare('SELECT * FROM reports WHERE id = :id');
$reportQuery->execute([':id' => $reportId]);
$existingReport = $reportQuery->fetch(PDO::FETCH_ASSOC);

if (!$existingReport) {
    http_response_code(404);
    echo '<p>Report not found.</p>';
    exit;
}

$contractsQuery = $pdo->prepare('SELECT * FROM contract_offers WHERE report_id = :id ORDER BY id');
$contractsQuery->execute([':id' => $reportId]);
$contracts = $contractsQuery->fetchAll(PDO::FETCH_ASSOC);

$otherCostsQuery = $pdo->prepare('SELECT * FROM other_costs WHERE report_id = :id ORDER BY id');
$otherCostsQuery->execute([':id' => $reportId]);
$otherCosts = $otherCostsQuery->fetchAll(PDO::FETCH_ASSOC);

$formData = [
    'report_identifier' => (string) $existingReport['report_identifier'],
    'report_date' => $existingReport['report_date'] ?? '',
    'customer_business_name' => (string) $existingReport['customer_business_name'],
    'customer_contact_name' => $existingReport['customer_contact_name'] ?? '',
    'broker_consultant' => $existingReport['broker_consultant'] ?? '',
    'site_nmi' => $existingReport['site_nmi'] ?? '',
    'site_current_retailer' => $existingReport['site_current_retailer'] ?? '',
    'site_contract_end_date' => $existingReport['site_contract_end_date'] ?? '',
    'site_address_line1' => $existingReport['site_address_line1'] ?? '',
    'site_address_line2' => $existingReport['site_address_line2'] ?? '',
    'site_peak_kwh' => $existingReport['site_peak_kwh'] !== null ? rtrim(rtrim((string) $existingReport['site_peak_kwh'], '0'), '.') : '',
    'site_shoulder_kwh' => $existingReport['site_shoulder_kwh'] !== null ? rtrim(rtrim((string) $existingReport['site_shoulder_kwh'], '0'), '.') : '',
    'site_off_peak_kwh' => $existingReport['site_off_peak_kwh'] !== null ? rtrim(rtrim((string) $existingReport['site_off_peak_kwh'], '0'), '.') : '',
    'site_total_kwh' => $existingReport['site_total_kwh'] !== null ? rtrim(rtrim((string) $existingReport['site_total_kwh'], '0'), '.') : '',
    'contract_current_retailer' => $existingReport['contract_current_retailer'] ?? '',
    'contract_term_months' => $existingReport['contract_term_months'] !== null ? (string) $existingReport['contract_term_months'] : '',
    'current_cost' => $existingReport['current_cost'] !== null ? number_format((float) $existingReport['current_cost'], 2, '.', '') : '',
    'new_cost' => $existingReport['new_cost'] !== null ? number_format((float) $existingReport['new_cost'], 2, '.', '') : '',
    'validity_period' => $existingReport['validity_period'] ?? '',
    'payment_terms' => $existingReport['payment_terms'] ?? '',
];

$contracts = array_map(static function (array $contract): array {
    return [
        'supplier_name' => $contract['supplier_name'] ?? '',
        'term_months' => isset($contract['term_months']) ? (string) $contract['term_months'] : '',
        'peak_rate' => isset($contract['peak_rate']) ? rtrim(rtrim((string) $contract['peak_rate'], '0'), '.') : '',
        'shoulder_rate' => isset($contract['shoulder_rate']) ? rtrim(rtrim((string) $contract['shoulder_rate'], '0'), '.') : '',
        'off_peak_rate' => isset($contract['off_peak_rate']) ? rtrim(rtrim((string) $contract['off_peak_rate'], '0'), '.') : '',
        'total_cost' => isset($contract['total_cost']) && $contract['total_cost'] !== null ? number_format((float) $contract['total_cost'], 2, '.', '') : '',
        'diff_dollar' => isset($contract['diff_dollar']) && $contract['diff_dollar'] !== null ? number_format((float) $contract['diff_dollar'], 2, '.', '') : '',
        'diff_percentage' => isset($contract['diff_percentage']) && $contract['diff_percentage'] !== null ? rtrim(rtrim((string) $contract['diff_percentage'], '0'), '.') : '',
    ];
}, $contracts);

$otherCosts = array_map(static function (array $otherCost): array {
    return [
        'cost_label' => $otherCost['cost_label'] ?? '',
        'cost_amount' => isset($otherCost['cost_amount']) ? number_format((float) $otherCost['cost_amount'], 2, '.', '') : '',
    ];
}, $otherCosts);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($formData) as $key) {
        $formData[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    $contracts = array_values($_POST['contracts'] ?? []);
    $otherCosts = array_values($_POST['other_costs'] ?? []);

    if ($formData['report_identifier'] === '') {
        $errors[] = 'Report ID is required.';
    }
    if ($formData['customer_business_name'] === '') {
        $errors[] = 'Customer business name is required.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $updateReport = $pdo->prepare(
                'UPDATE reports SET
                    report_identifier = :report_identifier,
                    report_date = :report_date,
                    customer_business_name = :customer_business_name,
                    customer_contact_name = :customer_contact_name,
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
                ':report_identifier' => $formData['report_identifier'],
                ':report_date' => $formData['report_date'] ?: null,
                ':customer_business_name' => $formData['customer_business_name'],
                ':customer_contact_name' => $formData['customer_contact_name'] ?: null,
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
                ':id' => $reportId,
            ]);

            $pdo->prepare('DELETE FROM contract_offers WHERE report_id = :report_id')->execute([':report_id' => $reportId]);
            $pdo->prepare('DELETE FROM other_costs WHERE report_id = :report_id')->execute([':report_id' => $reportId]);

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
                $supplier = trim((string) ($contract['supplier_name'] ?? ''));
                $term = isset($contract['term_months']) && $contract['term_months'] !== '' ? (int) $contract['term_months'] : null;

                if ($supplier === '' || $term === null) {
                    continue;
                }

                $insertContract->execute([
                    ':report_id' => $reportId,
                    ':supplier_name' => $supplier,
                    ':term_months' => $term,
                    ':peak_rate' => $contract['peak_rate'] !== '' ? (float) $contract['peak_rate'] : null,
                    ':shoulder_rate' => $contract['shoulder_rate'] !== '' ? (float) $contract['shoulder_rate'] : null,
                    ':off_peak_rate' => $contract['off_peak_rate'] !== '' ? (float) $contract['off_peak_rate'] : null,
                    ':total_cost' => $contract['total_cost'] !== '' ? parseCurrency((string) $contract['total_cost']) : null,
                    ':diff_dollar' => $contract['diff_dollar'] !== '' ? parseCurrency((string) $contract['diff_dollar']) : null,
                    ':diff_percentage' => $contract['diff_percentage'] !== '' ? (float) $contract['diff_percentage'] : null,
                ]);
            }

            $insertOtherCost = $pdo->prepare('INSERT INTO other_costs (report_id, cost_label, cost_amount) VALUES (:report_id, :cost_label, :cost_amount)');

            foreach ($otherCosts as $otherCost) {
                $label = trim((string) ($otherCost['cost_label'] ?? ''));
                $amount = isset($otherCost['cost_amount']) ? parseCurrency((string) $otherCost['cost_amount']) : null;

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
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = 'An unexpected error occurred while updating the report.';
        }
    }
}

if (empty($contracts)) {
    $contracts = [
        ['supplier_name' => '', 'term_months' => '', 'peak_rate' => '', 'shoulder_rate' => '', 'off_peak_rate' => '', 'total_cost' => '', 'diff_dollar' => '', 'diff_percentage' => ''],
    ];
}

if (empty($otherCosts)) {
    $otherCosts = [
        ['cost_label' => '', 'cost_amount' => ''],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Energy Report</title>
    <link rel="stylesheet" href="css/style.css">
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
<header class="top-bar">
    <div class="container">
        <h1>Edit Energy Report</h1>
        <nav>
            <a class="button" href="index.php">Back to Reports</a>
            <a class="button button--primary" href="report.php?id=<?= urlencode($reportId) ?>">View Report</a>
        </nav>
    </div>
</header>

<main class="container">
    <section class="card">
        <?php if (!empty($errors)): ?>
            <div class="alert alert--error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="form-grid">
            <input type="hidden" name="report_id" value="<?= htmlspecialchars((string) $reportId) ?>">

            <h2>Report Details</h2>
            <label>Report ID
                <input type="text" name="report_identifier" value="<?= htmlspecialchars($formData['report_identifier']) ?>" required>
            </label>
            <label>Report Date
                <input type="date" name="report_date" value="<?= htmlspecialchars($formData['report_date']) ?>">
            </label>
            <label>Customer Business Name
                <input type="text" name="customer_business_name" value="<?= htmlspecialchars($formData['customer_business_name']) ?>" required>
            </label>
            <label>Customer Contact Name
                <input type="text" name="customer_contact_name" value="<?= htmlspecialchars($formData['customer_contact_name']) ?>">
            </label>
            <label>Broker Consultant
                <input type="text" name="broker_consultant" value="<?= htmlspecialchars($formData['broker_consultant']) ?>">
            </label>

            <h2>Site Information</h2>
            <label>NMI
                <input type="text" name="site_nmi" value="<?= htmlspecialchars($formData['site_nmi']) ?>">
            </label>
            <label>Current Retailer
                <input type="text" name="site_current_retailer" value="<?= htmlspecialchars($formData['site_current_retailer']) ?>">
            </label>
            <label>Contract End Date
                <input type="date" name="site_contract_end_date" value="<?= htmlspecialchars($formData['site_contract_end_date']) ?>">
            </label>
            <label>Supply Address Line 1
                <input type="text" name="site_address_line1" value="<?= htmlspecialchars($formData['site_address_line1']) ?>">
            </label>
            <label>Supply Address Line 2
                <input type="text" name="site_address_line2" value="<?= htmlspecialchars($formData['site_address_line2']) ?>">
            </label>
            <label>Peak kWh
                <input type="number" step="1" name="site_peak_kwh" value="<?= htmlspecialchars($formData['site_peak_kwh']) ?>">
            </label>
            <label>Shoulder kWh
                <input type="number" step="1" name="site_shoulder_kwh" value="<?= htmlspecialchars($formData['site_shoulder_kwh']) ?>">
            </label>
            <label>Off Peak kWh
                <input type="number" step="1" name="site_off_peak_kwh" value="<?= htmlspecialchars($formData['site_off_peak_kwh']) ?>">
            </label>
            <label>Total kWh
                <input type="number" step="1" name="site_total_kwh" value="<?= htmlspecialchars($formData['site_total_kwh']) ?>">
            </label>

            <h2>Current Contract</h2>
            <label>Current Retailer
                <input type="text" name="contract_current_retailer" value="<?= htmlspecialchars($formData['contract_current_retailer']) ?>">
            </label>
            <label>Term (months)
                <input type="number" step="1" name="contract_term_months" value="<?= htmlspecialchars($formData['contract_term_months']) ?>">
            </label>
            <label>Current Cost
                <input type="text" name="current_cost" value="<?= htmlspecialchars($formData['current_cost']) ?>">
            </label>
            <label>New Cost
                <input type="text" name="new_cost" value="<?= htmlspecialchars($formData['new_cost']) ?>">
            </label>
            <label>Validity Period
                <input type="text" name="validity_period" value="<?= htmlspecialchars($formData['validity_period']) ?>">
            </label>
            <label>Payment Terms
                <input type="text" name="payment_terms" value="<?= htmlspecialchars($formData['payment_terms']) ?>">
            </label>

            <h2>Contract Offers</h2>
            <div class="table-scroll">
                <table class="data-table">
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
                                        <option value="<?= $term ?>" <?= isset($contract['term_months']) && (int) $contract['term_months'] === $term ? 'selected' : '' ?>><?= $term ?></option>
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
            <button type="button" class="button" onclick="addContractRow()">Add Contract Offer</button>

            <h2>Other Costs</h2>
            <div class="table-scroll">
                <table class="data-table">
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
            <button type="button" class="button" onclick="addOtherCostRow()">Add Other Cost</button>

            <div class="form-actions">
                <button type="submit" class="button button--primary">Update Report</button>
            </div>
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
