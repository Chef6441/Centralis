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

$contractsQuery = $pdo->prepare('SELECT * FROM contract_offers WHERE report_id = :id ORDER BY term_months, supplier_name');
$contractsQuery->execute([':id' => $reportId]);
$contracts = $contractsQuery->fetchAll(PDO::FETCH_ASSOC);

$otherCostsQuery = $pdo->prepare('SELECT cost_label, cost_amount FROM other_costs WHERE report_id = :id');
$otherCostsQuery->execute([':id' => $reportId]);
$otherCosts = $otherCostsQuery->fetchAll(PDO::FETCH_ASSOC);

$contractsByTerm = [];
foreach ($contracts as $contract) {
    $contractsByTerm[(int) $contract['term_months']][] = $contract;
}

$termHeadings = [
    12 => '12 MONTH OFFERS',
    24 => '24 MONTH OFFERS',
    36 => '36 MONTH OFFERS',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Energy Report <?= htmlspecialchars($report['report_identifier']) ?></title>
    <style>
        @media print {
            .report-section {
                page-break-inside: avoid;
            }

            .report-section:not(:last-of-type) {
                page-break-after: always;
            }
        }
    </style>
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
        <a href="create_report.php">Create Report</a> |
        <a href="edit_report.php?id=<?= urlencode($reportId) ?>">Edit Report</a> |
        <a href="#" onclick="return confirmDeleteReport();">Delete Report</a>
    </nav>
</header>
<main>
    <section class="report-section">
        <h1>Energy Price Comparison</h1>
        <p><strong>Report reference:</strong> <?= htmlspecialchars($report['report_identifier']) ?></p>
        <p><strong>Prepared for:</strong> <?= htmlspecialchars($report['customer_business_name']) ?></p>
        <p><strong>Report date:</strong> <?= htmlspecialchars(formatDisplayDate($report['report_date'])) ?></p>
        <p><strong>Consultant:</strong> <?= htmlspecialchars($report['broker_consultant'] ?: 'Your Consultant') ?></p>
        <p><strong>Primary contact:</strong> <?= htmlspecialchars($report['customer_contact_name'] ?? 'N/A') ?></p>
    </section>

    <section class="report-section">
        <h2>Business &amp; Site Details</h2>

        <h3>Business Information</h3>
        <table border="1" cellspacing="0" cellpadding="6">
            <tbody>
            <tr>
                <th align="left">Business Name</th>
                <td><?= htmlspecialchars($report['customer_business_name']) ?></td>
            </tr>
            <tr>
                <th align="left">Contact Name</th>
                <td><?= htmlspecialchars($report['customer_contact_name'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <th align="left">Energy Consultant</th>
                <td><?= htmlspecialchars($report['broker_consultant'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <th align="left">Contract Term</th>
                <td><?= htmlspecialchars($report['contract_term_months'] !== null ? $report['contract_term_months'] . ' Months' : 'N/A') ?></td>
            </tr>
            </tbody>
        </table>

        <h3>Site Information</h3>
        <table border="1" cellspacing="0" cellpadding="6">
            <tbody>
            <tr>
                <th align="left">NMI</th>
                <td><?= htmlspecialchars($report['site_nmi'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <th align="left">Supply Address</th>
                <td><?= nl2br(htmlspecialchars(trim(($report['site_address_line1'] ?? '') . "\n" . ($report['site_address_line2'] ?? '')))) ?></td>
            </tr>
            <tr>
                <th align="left">Current Retailer</th>
                <td><?= htmlspecialchars($report['site_current_retailer'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <th align="left">Contract End Date</th>
                <td><?= htmlspecialchars(formatDisplayDate($report['site_contract_end_date'])) ?></td>
            </tr>
            </tbody>
        </table>

        <h3>Consumption &amp; Costs</h3>
        <table border="1" cellspacing="0" cellpadding="6">
            <tbody>
            <tr>
                <th align="left">Peak Consumption (kWh)</th>
                <td><?= htmlspecialchars(formatKwh($report['site_peak_kwh'] !== null ? (float) $report['site_peak_kwh'] : null)) ?></td>
            </tr>
            <tr>
                <th align="left">Shoulder Consumption (kWh)</th>
                <td><?= htmlspecialchars(formatKwh($report['site_shoulder_kwh'] !== null ? (float) $report['site_shoulder_kwh'] : null)) ?></td>
            </tr>
            <tr>
                <th align="left">Off Peak Consumption (kWh)</th>
                <td><?= htmlspecialchars(formatKwh($report['site_off_peak_kwh'] !== null ? (float) $report['site_off_peak_kwh'] : null)) ?></td>
            </tr>
            <tr>
                <th align="left">Total Consumption (kWh)</th>
                <td><?= htmlspecialchars(formatKwh($report['site_total_kwh'] !== null ? (float) $report['site_total_kwh'] : null)) ?></td>
            </tr>
            <tr>
                <th align="left">Current Cost</th>
                <td><?= htmlspecialchars(formatCurrency($report['current_cost'] !== null ? (float) $report['current_cost'] : null)) ?></td>
            </tr>
            <tr>
                <th align="left">Proposed Cost</th>
                <td><?= htmlspecialchars(formatCurrency($report['new_cost'] !== null ? (float) $report['new_cost'] : null)) ?></td>
            </tr>
            <tr>
                <th align="left">Validity Period</th>
                <td><?= htmlspecialchars($report['validity_period'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <th align="left">Payment Terms</th>
                <td><?= htmlspecialchars($report['payment_terms'] ?? 'N/A') ?></td>
            </tr>
            </tbody>
        </table>
    </section>

    <section class="report-section">
        <h2>Price Comparison</h2>
        <?php foreach ($termHeadings as $term => $heading): ?>
            <?php if (!isset($contractsByTerm[$term])) { continue; } ?>
            <h3><?= $heading ?></h3>
            <table border="1" cellspacing="0" cellpadding="6">
                <thead>
                <tr>
                    <th align="left">Supplier</th>
                    <th align="left">P</th>
                    <th align="left">S</th>
                    <th align="left">O</th>
                    <th align="left">Total Costs</th>
                    <th align="left">$ Diff</th>
                    <th align="left">% Diff</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($contractsByTerm[$term] as $contract): ?>
                    <tr>
                        <td><?= htmlspecialchars($contract['supplier_name']) ?></td>
                        <td><?= htmlspecialchars($contract['peak_rate'] !== null ? number_format((float)$contract['peak_rate'], 3) : 'N/A') ?></td>
                        <td><?= htmlspecialchars($contract['shoulder_rate'] !== null ? number_format((float)$contract['shoulder_rate'], 3) : 'N/A') ?></td>
                        <td><?= htmlspecialchars($contract['off_peak_rate'] !== null ? number_format((float)$contract['off_peak_rate'], 3) : 'N/A') ?></td>
                        <td><?= htmlspecialchars(formatCurrency($contract['total_cost'] !== null ? (float)$contract['total_cost'] : null)) ?></td>
                        <td><?= htmlspecialchars($contract['diff_dollar'] !== null ? formatCurrency((float)$contract['diff_dollar']) : 'N/A') ?></td>
                        <td><?= htmlspecialchars($contract['diff_percentage'] !== null ? formatPercentage((float)$contract['diff_percentage']) : 'N/A') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>

        <?php if (!empty($otherCosts)): ?>
            <h3>Other Costs</h3>
            <table border="1" cellspacing="0" cellpadding="6">
                <thead>
                <tr>
                    <th align="left">Charges</th>
                    <th align="left">Cost</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($otherCosts as $cost): ?>
                    <tr>
                        <td><?= htmlspecialchars($cost['cost_label']) ?></td>
                        <td><?= htmlspecialchars(formatCurrency((float)$cost['cost_amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p><small>Prices are indicative only.</small></p>
    </section>

    <section class="report-section">
        <h2>Next Steps / Actions</h2>
        <ol>
            <li>Secure the chosen retailer’s energy agreement.</li>
            <li>Validate offer to ensure accuracy in energy rates and terms.</li>
            <li>Supply a digital copy via DocuSign.</li>
            <li>Submit signed agreement to retailer.</li>
            <li>Confirm contract acceptance.</li>
            <li>Manage transfer process.</li>
        </ol>

        <p><small>Prices are indicative only.</small></p>
    </section>
</main>

<form id="delete-report-form" action="delete_report.php" method="post" style="display:none;">
    <input type="hidden" name="id" value="<?= htmlspecialchars($reportId) ?>">
</form>
<script>
function confirmDeleteReport() {
    if (confirm('Delete this report?')) {
        document.getElementById('delete-report-form').submit();
    }
    return false;
}
</script>
</body>
</html>
