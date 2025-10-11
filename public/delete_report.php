<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo '<p>Method Not Allowed</p>';
    exit;
}

$reportId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($reportId <= 0) {
    http_response_code(400);
    echo '<p>Invalid report reference.</p>';
    exit;
}

$pdo = getDbConnection();

$delete = $pdo->prepare('DELETE FROM reports WHERE id = :id');
$delete->execute([':id' => $reportId]);

header('Location: index.php');
exit;
