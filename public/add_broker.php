<?php
require_once __DIR__ . '/../includes/db.php';

$pdo = getDbConnection();

$allowedReturnPages = ['create_report.php', 'edit_report.php'];

function resolveReturnTo(?string $rawValue, array $allowedPages): string
{
    $decoded = $rawValue !== null ? trim((string) rawurldecode($rawValue)) : '';

    if ($decoded === '') {
        return 'create_report.php';
    }

    if (strpos($decoded, '://') !== false) {
        return 'create_report.php';
    }

    foreach ($allowedPages as $allowed) {
        if (strpos($decoded, $allowed) === 0) {
            return $decoded;
        }
    }

    return 'create_report.php';
}

function buildReturnUrl(string $basePath, array $prefill): string
{
    if (empty($prefill)) {
        return $basePath;
    }

    $query = http_build_query(['prefill' => $prefill]);
    return $basePath . (strpos($basePath, '?') === false ? '?' : '&') . $query;
}

function determineReturnLabel(string $returnTo): string
{
    if (strpos($returnTo, 'edit_report.php') === 0) {
        return 'Edit Report';
    }

    return 'Create Report';
}

function fetchBrokerResults(PDO $pdo, string $searchTerm): array
{
    $baseSql = <<<SQL
SELECT
    brokers.id,
    companies.name AS broker_name,
    companies.contact_name AS contact_name,
    companies.contact_email AS contact_email,
    companies.contact_phone AS contact_phone,
    companies.address AS address
FROM brokers
INNER JOIN companies ON companies.id = brokers.company_id
SQL;

    if ($searchTerm !== '') {
        $statement = $pdo->prepare($baseSql . ' WHERE companies.name LIKE :term OR companies.contact_name LIKE :term ORDER BY companies.name LIMIT 25');
        $statement->execute([':term' => '%' . $searchTerm . '%']);
    } else {
        $statement = $pdo->query($baseSql . ' ORDER BY companies.name LIMIT 25');
    }

    return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
}

$returnTo = resolveReturnTo($_GET['return_to'] ?? null, $allowedReturnPages);
$encodedReturnTo = rawurlencode($returnTo);
$searchTerm = trim((string)($_GET['query'] ?? ''));
$errors = [];

$newBrokerData = [
    'broker_name' => '',
    'contact_name' => '',
    'contact_email' => '',
    'contact_phone' => '',
    'address' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnTo = resolveReturnTo($_POST['return_to'] ?? null, $allowedReturnPages);
    $encodedReturnTo = rawurlencode($returnTo);
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $newBrokerData['broker_name'] = trim((string)($_POST['broker_name'] ?? ''));
        $newBrokerData['contact_name'] = trim((string)($_POST['contact_name'] ?? ''));
        $newBrokerData['contact_email'] = trim((string)($_POST['contact_email'] ?? ''));
        $newBrokerData['contact_phone'] = trim((string)($_POST['contact_phone'] ?? ''));
        $newBrokerData['address'] = trim((string)($_POST['address'] ?? ''));

        if ($newBrokerData['broker_name'] === '') {
            $errors[] = 'Broker company name is required.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $lookupCompany = $pdo->prepare('SELECT id FROM companies WHERE name = :name');
                $lookupCompany->execute([':name' => $newBrokerData['broker_name']]);
                $companyId = $lookupCompany->fetchColumn();

                if ($companyId) {
                    $updateCompany = $pdo->prepare('UPDATE companies SET contact_name = :contact_name, contact_email = :contact_email, contact_phone = :contact_phone, address = :address WHERE id = :id');
                    $updateCompany->execute([
                        ':contact_name' => $newBrokerData['contact_name'] ?: null,
                        ':contact_email' => $newBrokerData['contact_email'] ?: null,
                        ':contact_phone' => $newBrokerData['contact_phone'] ?: null,
                        ':address' => $newBrokerData['address'] ?: null,
                        ':id' => $companyId,
                    ]);
                } else {
                    $insertCompany = $pdo->prepare('INSERT INTO companies (name, contact_name, contact_email, contact_phone, address) VALUES (:name, :contact_name, :contact_email, :contact_phone, :address)');
                    $insertCompany->execute([
                        ':name' => $newBrokerData['broker_name'],
                        ':contact_name' => $newBrokerData['contact_name'] ?: null,
                        ':contact_email' => $newBrokerData['contact_email'] ?: null,
                        ':contact_phone' => $newBrokerData['contact_phone'] ?: null,
                        ':address' => $newBrokerData['address'] ?: null,
                    ]);
                    $companyId = (int) $pdo->lastInsertId();
                }

                $lookupBroker = $pdo->prepare('SELECT id FROM brokers WHERE company_id = :company_id');
                $lookupBroker->execute([':company_id' => $companyId]);
                $brokerId = $lookupBroker->fetchColumn();

                if ($brokerId) {
                    // Nothing more to update for brokers beyond company linkage.
                } else {
                    $insertBroker = $pdo->prepare('INSERT INTO brokers (company_id) VALUES (:company_id)');
                    $insertBroker->execute([':company_id' => $companyId]);
                }

                $pdo->commit();

                $prefill = [
                    'broker_company_name' => $newBrokerData['broker_name'],
                ];

                if ($newBrokerData['contact_name'] !== '') {
                    $prefill['broker_consultant'] = $newBrokerData['contact_name'];
                }

                header('Location: ' . buildReturnUrl($returnTo, $prefill));
                exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Unable to save the broker. Please try again.';
            }
        }
    }
}

$searchResults = fetchBrokerResults($pdo, $searchTerm);
$returnLabel = determineReturnLabel($returnTo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Broker</title>
    <style>
        .breadcrumbs {
            margin-bottom: 16px;
        }

        .breadcrumbs a {
            text-decoration: none;
        }

        .section {
            margin-bottom: 32px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        table th,
        table td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        .form-group {
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
<header>
    <h1>Add Broker</h1>
    <nav class="breadcrumbs">
        <a href="index.php">Dashboard</a> &raquo;
        <a href="reports.php">Reports</a> &raquo;
        <a href="<?= htmlspecialchars($returnTo) ?>"><?= htmlspecialchars($returnLabel) ?></a> &raquo;
        Add Broker
    </nav>
</header>

<main>
    <section class="section">
        <h2>Find an Existing Broker</h2>
        <form method="get">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($encodedReturnTo) ?>">
            <div class="form-group">
                <label for="query">Search by broker or contact name</label><br>
                <input id="query" type="text" name="query" size="40" value="<?= htmlspecialchars($searchTerm) ?>">
                <button type="submit">Search</button>
            </div>
        </form>

        <?php if (!empty($searchResults)): ?>
            <table>
                <thead>
                <tr>
                    <th>Broker</th>
                    <th>Primary Contact</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($searchResults as $result): ?>
                    <?php
                    $prefill = [
                        'broker_company_name' => $result['broker_name'],
                    ];

                    if (!empty($result['contact_name'])) {
                        $prefill['broker_consultant'] = $result['contact_name'];
                    }

                    $returnUrl = buildReturnUrl($returnTo, $prefill);
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($result['broker_name']) ?></strong><br>
                            <?php if (!empty($result['address'])): ?>
                                <small><?= nl2br(htmlspecialchars($result['address'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($result['contact_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($result['contact_email'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($result['contact_phone'] ?? 'N/A') ?></td>
                        <td><a href="<?= htmlspecialchars($returnUrl) ?>">Use this broker</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ($searchTerm !== ''): ?>
            <p>No brokers match "<?= htmlspecialchars($searchTerm) ?>".</p>
        <?php else: ?>
            <p>No brokers found yet. Create a broker below.</p>
        <?php endif; ?>
    </section>

    <section class="section">
        <h2>Create a New Broker</h2>

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
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($encodedReturnTo) ?>">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label for="broker_name">Broker Name</label><br>
                <input id="broker_name" type="text" name="broker_name" size="40" value="<?= htmlspecialchars($newBrokerData['broker_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="contact_name">Primary Contact</label><br>
                <input id="contact_name" type="text" name="contact_name" size="40" value="<?= htmlspecialchars($newBrokerData['contact_name']) ?>">
            </div>

            <div class="form-group">
                <label for="contact_email">Contact Email</label><br>
                <input id="contact_email" type="email" name="contact_email" size="40" value="<?= htmlspecialchars($newBrokerData['contact_email']) ?>">
            </div>

            <div class="form-group">
                <label for="contact_phone">Contact Phone</label><br>
                <input id="contact_phone" type="text" name="contact_phone" size="40" value="<?= htmlspecialchars($newBrokerData['contact_phone']) ?>">
            </div>

            <div class="form-group">
                <label for="address">Business Address</label><br>
                <textarea id="address" name="address" rows="3" cols="60"><?= htmlspecialchars($newBrokerData['address']) ?></textarea>
            </div>

            <button type="submit">Save Broker</button>
            <a href="<?= htmlspecialchars($returnTo) ?>">Cancel</a>
        </form>
    </section>
</main>
</body>
</html>
