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

function fetchPartnerResults(PDO $pdo, string $searchTerm): array
{
    $baseSql = <<<SQL
SELECT
    partners.id,
    partner_company.name AS partner_name,
    partner_company.contact_name AS contact_name,
    partner_company.contact_email AS contact_email,
    partner_company.contact_phone AS contact_phone,
    partners.revenue_share_percentage,
    broker_company.name AS broker_name,
    broker_company.contact_name AS broker_contact
FROM partners
INNER JOIN companies AS partner_company ON partner_company.id = partners.company_id
INNER JOIN brokers ON brokers.id = partners.broker_id
INNER JOIN companies AS broker_company ON broker_company.id = brokers.company_id
SQL;

    if ($searchTerm !== '') {
        $statement = $pdo->prepare($baseSql . ' WHERE partner_company.name LIKE :term OR partner_company.contact_name LIKE :term ORDER BY partner_company.name LIMIT 25');
        $statement->execute([':term' => '%' . $searchTerm . '%']);
    } else {
        $statement = $pdo->query($baseSql . ' ORDER BY partner_company.name LIMIT 25');
    }

    return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
}

function fetchBrokerOptions(PDO $pdo): array
{
    $statement = $pdo->query('SELECT brokers.id, companies.name, companies.contact_name FROM brokers INNER JOIN companies ON companies.id = brokers.company_id ORDER BY companies.name');
    return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
}

$returnTo = resolveReturnTo($_GET['return_to'] ?? null, $allowedReturnPages);
$encodedReturnTo = rawurlencode($returnTo);
$searchTerm = trim((string)($_GET['query'] ?? ''));
$errors = [];

$newPartnerData = [
    'partner_name' => '',
    'contact_name' => '',
    'contact_email' => '',
    'contact_phone' => '',
    'address' => '',
    'revenue_share_percentage' => '',
    'broker_id' => '',
];

$brokers = fetchBrokerOptions($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnTo = resolveReturnTo($_POST['return_to'] ?? null, $allowedReturnPages);
    $encodedReturnTo = rawurlencode($returnTo);
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $newPartnerData['partner_name'] = trim((string)($_POST['partner_name'] ?? ''));
        $newPartnerData['contact_name'] = trim((string)($_POST['contact_name'] ?? ''));
        $newPartnerData['contact_email'] = trim((string)($_POST['contact_email'] ?? ''));
        $newPartnerData['contact_phone'] = trim((string)($_POST['contact_phone'] ?? ''));
        $newPartnerData['address'] = trim((string)($_POST['address'] ?? ''));
        $newPartnerData['revenue_share_percentage'] = trim((string)($_POST['revenue_share_percentage'] ?? ''));
        $newPartnerData['broker_id'] = trim((string)($_POST['broker_id'] ?? ''));

        $brokerId = $newPartnerData['broker_id'] !== '' ? (int) $newPartnerData['broker_id'] : 0;
        $revenueShare = $newPartnerData['revenue_share_percentage'] !== '' ? (float) $newPartnerData['revenue_share_percentage'] : null;

        if ($newPartnerData['partner_name'] === '') {
            $errors[] = 'Partner company name is required.';
        }

        if ($brokerId <= 0) {
            $errors[] = 'Please select a broker for this partner.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $lookupCompany = $pdo->prepare('SELECT id FROM companies WHERE name = :name');
                $lookupCompany->execute([':name' => $newPartnerData['partner_name']]);
                $companyId = $lookupCompany->fetchColumn();

                if ($companyId) {
                    $updateCompany = $pdo->prepare('UPDATE companies SET contact_name = :contact_name, contact_email = :contact_email, contact_phone = :contact_phone, address = :address WHERE id = :id');
                    $updateCompany->execute([
                        ':contact_name' => $newPartnerData['contact_name'] ?: null,
                        ':contact_email' => $newPartnerData['contact_email'] ?: null,
                        ':contact_phone' => $newPartnerData['contact_phone'] ?: null,
                        ':address' => $newPartnerData['address'] ?: null,
                        ':id' => $companyId,
                    ]);
                } else {
                    $insertCompany = $pdo->prepare('INSERT INTO companies (name, contact_name, contact_email, contact_phone, address) VALUES (:name, :contact_name, :contact_email, :contact_phone, :address)');
                    $insertCompany->execute([
                        ':name' => $newPartnerData['partner_name'],
                        ':contact_name' => $newPartnerData['contact_name'] ?: null,
                        ':contact_email' => $newPartnerData['contact_email'] ?: null,
                        ':contact_phone' => $newPartnerData['contact_phone'] ?: null,
                        ':address' => $newPartnerData['address'] ?: null,
                    ]);
                    $companyId = (int) $pdo->lastInsertId();
                }

                $lookupPartner = $pdo->prepare('SELECT id FROM partners WHERE company_id = :company_id');
                $lookupPartner->execute([':company_id' => $companyId]);
                $partnerId = $lookupPartner->fetchColumn();

                if ($partnerId) {
                    $updatePartner = $pdo->prepare('UPDATE partners SET broker_id = :broker_id, revenue_share_percentage = :revenue_share WHERE id = :id');
                    $updatePartner->execute([
                        ':broker_id' => $brokerId,
                        ':revenue_share' => $revenueShare,
                        ':id' => $partnerId,
                    ]);
                } else {
                    $insertPartner = $pdo->prepare('INSERT INTO partners (broker_id, company_id, revenue_share_percentage) VALUES (:broker_id, :company_id, :revenue_share)');
                    $insertPartner->execute([
                        ':broker_id' => $brokerId,
                        ':company_id' => $companyId,
                        ':revenue_share' => $revenueShare,
                    ]);
                }

                $pdo->commit();

                $prefill = [
                    'partner_company_name' => $newPartnerData['partner_name'],
                ];

                if ($newPartnerData['contact_name'] !== '') {
                    $prefill['partner_contact_name'] = $newPartnerData['contact_name'];
                }

                $selectedBroker = null;
                foreach ($brokers as $broker) {
                    if ((int) $broker['id'] === $brokerId) {
                        $selectedBroker = $broker;
                        break;
                    }
                }

                if ($selectedBroker) {
                    $prefill['broker_company_name'] = $selectedBroker['name'];
                    if (!empty($selectedBroker['contact_name'])) {
                        $prefill['broker_consultant'] = $selectedBroker['contact_name'];
                    }
                }

                header('Location: ' . buildReturnUrl($returnTo, $prefill));
                exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Unable to save the partner. Please try again.';
            }
        }
    }
}

$searchResults = fetchPartnerResults($pdo, $searchTerm);
$returnLabel = determineReturnLabel($returnTo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Partner</title>
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
    <h1>Select Partner</h1>
    <nav class="breadcrumbs">
        <a href="index.php">Dashboard</a> &raquo;
        <a href="reports.php">Reports</a> &raquo;
        <a href="<?= htmlspecialchars($returnTo) ?>"><?= htmlspecialchars($returnLabel) ?></a> &raquo;
        Select Partner
    </nav>
</header>

<main>
    <section class="section">
        <h2>Find an Existing Partner</h2>
        <form method="get">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($encodedReturnTo) ?>">
            <div class="form-group">
                <label for="query">Search by partner or contact name</label><br>
                <input id="query" type="text" name="query" size="40" value="<?= htmlspecialchars($searchTerm) ?>">
                <button type="submit">Search</button>
            </div>
        </form>

        <?php if (!empty($searchResults)): ?>
            <table>
                <thead>
                <tr>
                    <th>Partner</th>
                    <th>Primary Contact</th>
                    <th>Broker</th>
                    <th>Revenue Share</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($searchResults as $result): ?>
                    <?php
                    $prefill = [
                        'partner_company_name' => $result['partner_name'],
                    ];

                    if (!empty($result['contact_name'])) {
                        $prefill['partner_contact_name'] = $result['contact_name'];
                    }

                    if (!empty($result['broker_name'])) {
                        $prefill['broker_company_name'] = $result['broker_name'];
                    }

                    if (!empty($result['broker_contact'])) {
                        $prefill['broker_consultant'] = $result['broker_contact'];
                    }

                    $returnUrl = buildReturnUrl($returnTo, $prefill);
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($result['partner_name']) ?></strong><br>
                            <?php if (!empty($result['contact_email'])): ?>
                                <small><?= htmlspecialchars($result['contact_email']) ?></small><br>
                            <?php endif; ?>
                            <?php if (!empty($result['contact_phone'])): ?>
                                <small><?= htmlspecialchars($result['contact_phone']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($result['contact_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($result['broker_name'] ?? 'N/A') ?></td>
                        <td><?= $result['revenue_share_percentage'] !== null ? htmlspecialchars(rtrim(rtrim(number_format((float) $result['revenue_share_percentage'], 2), '0'), '.')) . '%' : 'N/A' ?></td>
                        <td><a href="<?= htmlspecialchars($returnUrl) ?>">Use this partner</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ($searchTerm !== ''): ?>
            <p>No partners match "<?= htmlspecialchars($searchTerm) ?>".</p>
        <?php else: ?>
            <p>No partners found yet. Create a partner below.</p>
        <?php endif; ?>
    </section>

    <section class="section">
        <h2>Create a New Partner</h2>

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
                <label for="partner_name">Partner Name</label><br>
                <input id="partner_name" type="text" name="partner_name" size="40" value="<?= htmlspecialchars($newPartnerData['partner_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="contact_name">Primary Contact</label><br>
                <input id="contact_name" type="text" name="contact_name" size="40" value="<?= htmlspecialchars($newPartnerData['contact_name']) ?>">
            </div>

            <div class="form-group">
                <label for="contact_email">Contact Email</label><br>
                <input id="contact_email" type="email" name="contact_email" size="40" value="<?= htmlspecialchars($newPartnerData['contact_email']) ?>">
            </div>

            <div class="form-group">
                <label for="contact_phone">Contact Phone</label><br>
                <input id="contact_phone" type="text" name="contact_phone" size="40" value="<?= htmlspecialchars($newPartnerData['contact_phone']) ?>">
            </div>

            <div class="form-group">
                <label for="address">Business Address</label><br>
                <textarea id="address" name="address" rows="3" cols="60"><?= htmlspecialchars($newPartnerData['address']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="revenue_share_percentage">Revenue Share Percentage</label><br>
                <input id="revenue_share_percentage" type="number" step="0.01" name="revenue_share_percentage" value="<?= htmlspecialchars($newPartnerData['revenue_share_percentage']) ?>">
            </div>

            <div class="form-group">
                <label for="broker_id">Broker</label><br>
                <select id="broker_id" name="broker_id" required>
                    <option value="">Select a broker</option>
                    <?php foreach ($brokers as $broker): ?>
                        <option value="<?= htmlspecialchars((string) $broker['id']) ?>" <?= $newPartnerData['broker_id'] !== '' && (int) $newPartnerData['broker_id'] === (int) $broker['id'] ? 'selected' : '' ?>><?= htmlspecialchars($broker['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit">Save Partner</button>
            <a href="<?= htmlspecialchars($returnTo) ?>">Cancel</a>
        </form>
    </section>
</main>
</body>
</html>
