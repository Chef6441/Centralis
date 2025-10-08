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
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="report">
<header class="report__header">
    <div class="container">
        <p class="disclaimer">Prices are indicative only.</p>
        <div class="report__summary">
            <div>
                <h1>Energy Price Comparison</h1>
                <p>For <?= htmlspecialchars($report['customer_business_name']) ?></p>
                <p><?= htmlspecialchars(formatDisplayDate($report['report_date'])) ?></p>
            </div>
            <div class="consultant">
                <p class="consultant__name"><?= htmlspecialchars($report['broker_consultant'] ?: 'Your Consultant') ?></p>
                <p>1300 380 749</p>
            </div>
        </div>
    </div>
</header>

<main class="container report__content">
    <section class="card">
        <p class="disclaimer">Prices are indicative only.</p>
        <h2>Customer Profile</h2>
        <div class="grid two-columns">
            <div>
                <h3>Business Information</h3>
                <dl>
                    <dt>Business Name</dt>
                    <dd><?= htmlspecialchars($report['customer_business_name']) ?></dd>
                    <dt>Contact Name</dt>
                    <dd><?= htmlspecialchars($report['customer_contact_name'] ?? 'N/A') ?></dd>
                    <dt>Energy Consultant</dt>
                    <dd><?= htmlspecialchars($report['broker_consultant'] ?? 'N/A') ?></dd>
                </dl>
            </div>
            <div>
                <h3>Site Information</h3>
                <dl>
                    <dt>Current Retailer</dt>
                    <dd><?= htmlspecialchars($report['site_current_retailer'] ?? 'N/A') ?></dd>
                    <dt>Contract End Date</dt>
                    <dd><?= htmlspecialchars(formatDisplayDate($report['site_contract_end_date'])) ?></dd>
                    <dt>NMI</dt>
                    <dd><?= htmlspecialchars($report['site_nmi'] ?? 'N/A') ?></dd>
                    <dt>Supply Address</dt>
                    <dd>
                        <?= nl2br(htmlspecialchars(trim(($report['site_address_line1'] ?? '') . "\n" . ($report['site_address_line2'] ?? '')))) ?>
                    </dd>
                </dl>
            </div>
        </div>

        <div class="grid two-columns">
            <div>
                <h3>Current Retailer</h3>
                <dl>
                    <dt>Current Retailer</dt>
                    <dd><?= htmlspecialchars($report['contract_current_retailer'] ?? 'N/A') ?></dd>
                    <dt>Term</dt>
                    <dd><?= htmlspecialchars($report['contract_term_months'] !== null ? $report['contract_term_months'] . ' Months' : 'N/A') ?></dd>
                    <dt>Current</dt>
                    <dd><?= htmlspecialchars(formatCurrency($report['current_cost'] !== null ? (float) $report['current_cost'] : null)) ?></dd>
                    <dt>New</dt>
                    <dd><?= htmlspecialchars(formatCurrency($report['new_cost'] !== null ? (float) $report['new_cost'] : null)) ?></dd>
                    <dt>Validity Period</dt>
                    <dd><?= htmlspecialchars($report['validity_period'] ?? 'N/A') ?></dd>
                    <dt>Payment Terms</dt>
                    <dd><?= htmlspecialchars($report['payment_terms'] ?? 'N/A') ?></dd>
                </dl>
            </div>
            <div>
                <h3>Energy Consumption</h3>
                <dl>
                    <dt>Peak</dt>
                    <dd><?= htmlspecialchars(formatKwh($report['site_peak_kwh'] !== null ? (float) $report['site_peak_kwh'] : null)) ?></dd>
                    <dt>Shoulder</dt>
                    <dd><?= htmlspecialchars(formatKwh($report['site_shoulder_kwh'] !== null ? (float) $report['site_shoulder_kwh'] : null)) ?></dd>
                    <dt>Off Peak</dt>
                    <dd><?= htmlspecialchars(formatKwh($report['site_off_peak_kwh'] !== null ? (float) $report['site_off_peak_kwh'] : null)) ?></dd>
                    <dt>Total</dt>
                    <dd><?= htmlspecialchars(formatKwh($report['site_total_kwh'] !== null ? (float) $report['site_total_kwh'] : null)) ?></dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="card">
        <p class="disclaimer">Prices are indicative only.</p>
        <h2>Price Comparison</h2>

        <?php foreach ($termHeadings as $term => $heading): ?>
            <?php if (!isset($contractsByTerm[$term])) { continue; } ?>
            <h3><?= $heading ?></h3>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>P</th>
                        <th>S</th>
                        <th>O</th>
                        <th>Total Costs</th>
                        <th>$ Diff</th>
                        <th>% Diff</th>
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
            </div>
        <?php endforeach; ?>

        <?php if (!empty($otherCosts)): ?>
            <h3>Other Costs</h3>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Charges</th>
                        <th>Cost</th>
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
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <p class="disclaimer">Prices are indicative only.</p>
        <h2>Next Steps / Actions</h2>
        <ol>
            <li>Secure the chosen retailer’s energy agreement.</li>
            <li>Validate offer to ensure accuracy in energy rates and terms.</li>
            <li>Supply a digital copy via DocuSign.</li>
            <li>Submit signed agreement to retailer.</li>
            <li>Confirm contract acceptance.</li>
            <li>Manage transfer process.</li>
        </ol>
    </section>
</main>

<footer class="footer">
    <div class="container">
        <p class="disclaimer">Prices are indicative only.</p>
        <p>&copy; <?= date('Y') ?> Centralis</p>
    </div>
</footer>
</body>
</html>
