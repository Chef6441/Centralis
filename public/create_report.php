<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/navigation.php';

$pdo = getDbConnection();

$activeNav = 'reports';

$formData = [
    'report_identifier' => '',
    'report_date' => '',
    'customer_business_name' => '',
    'customer_contact_name' => '',
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($formData) as $key) {
        $formData[$key] = trim((string)($_POST[$key] ?? ''));
    }

    $contracts = array_values($_POST['contracts'] ?? $contracts);
    $otherCosts = array_values($_POST['other_costs'] ?? $otherCosts);

    if ($formData['report_identifier'] === '') {
        $errors[] = 'Report ID is required.';
    }
    if ($formData['customer_business_name'] === '') {
        $errors[] = 'Customer business name is required.';
    }

    if (empty($errors)) {
        $insertReport = $pdo->prepare(
            'INSERT INTO reports (
                report_identifier,
                report_date,
                customer_business_name,
                customer_contact_name,
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
        ]);

        $reportId = (int) $pdo->lastInsertId();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Energy Report</title>
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
        <h1>Create Energy Report</h1>
        <nav>
            <a class="button" href="index.php">Back to Reports</a>
        </nav>
    </div>
</header>

<div class="app-shell">
    <?php renderSidebar($activeNav); ?>
    <div class="app-content">
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
                        <button type="submit" class="button button--primary">Save Report</button>
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
    </div>
</div>

</body>
</html>
