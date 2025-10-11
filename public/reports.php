<?php
require_once __DIR__ . '/../includes/db.php';

$pdo = getDbConnection();

$statement = $pdo->query('SELECT id, report_identifier, customer_business_name, report_date FROM reports ORDER BY created_at DESC');
$reports = $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Centralis Reports</title>
</head>
<body>
<header>
    <div>
        <h1>Centralis</h1>
    </div>
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
        <a href="create_report.php">Create Report</a>
    </nav>
</header>

<main>
    <section>
        <h2>Existing Reports</h2>
        <?php if (empty($reports)): ?>
            <p>No reports have been created yet. <a href="create_report.php">Create your first report</a>.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Business Name</th>
                        <th>Report Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reports as $report): ?>
                    <tr>
                        <td><?= htmlspecialchars($report['report_identifier']) ?></td>
                        <td><?= htmlspecialchars($report['customer_business_name']) ?></td>
                        <td><?= htmlspecialchars($report['report_date']) ?></td>
                        <td>
                            <a href="report.php?id=<?= urlencode($report['id']) ?>">View</a> |
                            <a href="edit_report.php?id=<?= urlencode($report['id']) ?>">Edit</a> |
                            <a href="#" class="delete-report-link" data-report-id="<?= htmlspecialchars($report['id']) ?>">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</main>

<footer>
    <div>
        <p>&copy; <?= date('Y') ?> Centralis</p>
    </div>
</footer>

<form id="delete-report-form" action="delete_report.php" method="post" style="display:none;">
    <input type="hidden" name="id" id="delete-report-id">
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var deleteLinks = document.querySelectorAll('.delete-report-link');
    var deleteForm = document.getElementById('delete-report-form');
    var deleteIdField = document.getElementById('delete-report-id');

    deleteLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            var reportId = this.getAttribute('data-report-id');
            if (reportId && confirm('Delete this report?')) {
                deleteIdField.value = reportId;
                deleteForm.submit();
            }
        });
    });
});
</script>
</body>
</html>
