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
    <nav>
        <a href="index.php">Dashboard</a> |
        <a href="#">Accounts</a> |
        <a href="reports.php">Reports</a> |
        <a href="#">Billing</a> |
        <a href="#">Tasks</a> |
        <a href="#">Settings</a>
    </nav>
    <br>
    <nav>
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
                            <a href="edit_report.php?id=<?= urlencode($report['id']) ?>">Edit</a>
                            |
                            <form action="delete_report.php" method="post" style="display:inline" onsubmit="return confirm('Delete this report?');">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($report['id']) ?>">
                                <button type="submit">Delete</button>
                            </form>
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
</body>
</html>
