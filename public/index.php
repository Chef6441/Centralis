<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/navigation.php';

$pdo = getDbConnection();

$statement = $pdo->query('SELECT id, report_identifier, customer_business_name, report_date FROM reports ORDER BY created_at DESC');
$reports = $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];

$activeNav = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Centralis Energy Reports</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="top-bar">
    <div class="container">
        <h1>Centralis Energy Reports</h1>
        <nav>
            <a class="button" href="create_report.php">Create Report</a>
        </nav>
    </div>
</header>

<div class="app-shell">
    <?php renderSidebar($activeNav); ?>
    <div class="app-content">
        <main class="container">
            <section class="card">
                <h2>Existing Reports</h2>
                <?php if (empty($reports)): ?>
                    <p>No reports have been created yet. <a href="create_report.php">Create your first report</a>.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Business Name</th>
                                <th>Report Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?= htmlspecialchars($report['report_identifier']) ?></td>
                                <td><?= htmlspecialchars($report['customer_business_name']) ?></td>
                                <td><?= htmlspecialchars($report['report_date']) ?></td>
                                <td><a class="button button--link" href="report.php?id=<?= urlencode($report['id']) ?>">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<footer class="footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> Centralis</p>
    </div>
</footer>
</body>
</html>
