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

function fetchCustomerResults(PDO $pdo, string $searchTerm): array
{
    $baseSql = <<<SQL
SELECT
    clients.id,
    customer_company.name AS customer_name,
    customer_company.contact_name AS customer_contact,
    customer_company.contact_email AS customer_email,
    customer_company.contact_phone AS customer_phone,
    partner_company.name AS partner_name,
    broker_company.name AS broker_name,
    broker_company.contact_name AS broker_contact
FROM clients
INNER JOIN companies AS customer_company ON customer_company.id = clients.company_id
INNER JOIN brokers ON brokers.id = clients.broker_id
INNER JOIN companies AS broker_company ON broker_company.id = brokers.company_id
LEFT JOIN partners ON partners.id = clients.partner_id
LEFT JOIN companies AS partner_company ON partner_company.id = partners.company_id
SQL;

    if ($searchTerm !== '') {
        $statement = $pdo->prepare($baseSql . ' WHERE customer_company.name LIKE :term OR customer_company.contact_name LIKE :term ORDER BY customer_company.name LIMIT 25');
        $statement->execute([':term' => '%' . $searchTerm . '%']);
    } else {
        $statement = $pdo->query($baseSql . ' ORDER BY customer_company.name LIMIT 25');
    }

    return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
}

function fetchBrokerOptions(PDO $pdo): array
{
    $statement = $pdo->query('SELECT brokers.id, companies.name, companies.contact_name FROM brokers INNER JOIN companies ON companies.id = brokers.company_id ORDER BY companies.name');
    return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
}

function fetchPartnerOptions(PDO $pdo): array
{
    $statement = $pdo->query('SELECT partners.id, companies.name FROM partners INNER JOIN companies ON companies.id = partners.company_id ORDER BY companies.name');
    return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
}

$returnTo = resolveReturnTo($_GET['return_to'] ?? null, $allowedReturnPages);
$encodedReturnTo = rawurlencode($returnTo);
$searchTerm = trim((string)($_GET['query'] ?? ''));
$errors = [];

$newCustomerData = [
    'company_name' => '',
    'contact_name' => '',
    'contact_email' => '',
    'contact_phone' => '',
    'address' => '',
    'customer_abn' => '',
    'broker_id' => '',
    'partner_id' => '',
];

$brokers = fetchBrokerOptions($pdo);
$partners = fetchPartnerOptions($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnTo = resolveReturnTo($_POST['return_to'] ?? null, $allowedReturnPages);
    $encodedReturnTo = rawurlencode($returnTo);
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $newCustomerData['company_name'] = trim((string)($_POST['company_name'] ?? ''));
        $newCustomerData['contact_name'] = trim((string)($_POST['contact_name'] ?? ''));
        $newCustomerData['contact_email'] = trim((string)($_POST['contact_email'] ?? ''));
        $newCustomerData['contact_phone'] = trim((string)($_POST['contact_phone'] ?? ''));
        $newCustomerData['address'] = trim((string)($_POST['address'] ?? ''));
        $newCustomerData['customer_abn'] = trim((string)($_POST['customer_abn'] ?? ''));
        $newCustomerData['broker_id'] = trim((string)($_POST['broker_id'] ?? ''));
        $newCustomerData['partner_id'] = trim((string)($_POST['partner_id'] ?? ''));

        $brokerId = $newCustomerData['broker_id'] !== '' ? (int) $newCustomerData['broker_id'] : 0;
        $partnerId = $newCustomerData['partner_id'] !== '' ? (int) $newCustomerData['partner_id'] : null;

        if ($newCustomerData['company_name'] === '') {
            $errors[] = 'Customer company name is required.';
        }

        if ($brokerId <= 0) {
            $errors[] = 'Please select a broker for this customer.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $companyId = null;
                $lookupCompany = $pdo->prepare('SELECT id FROM companies WHERE name = :name');
                $lookupCompany->execute([':name' => $newCustomerData['company_name']]);
                $companyId = $lookupCompany->fetchColumn();

                if ($companyId) {
                    $updateCompany = $pdo->prepare('UPDATE companies SET contact_name = :contact_name, contact_email = :contact_email, contact_phone = :contact_phone, address = :address WHERE id = :id');
                    $updateCompany->execute([
                        ':contact_name' => $newCustomerData['contact_name'] ?: null,
                        ':contact_email' => $newCustomerData['contact_email'] ?: null,
                        ':contact_phone' => $newCustomerData['contact_phone'] ?: null,
                        ':address' => $newCustomerData['address'] ?: null,
                        ':id' => $companyId,
                    ]);
                } else {
                    $insertCompany = $pdo->prepare('INSERT INTO companies (name, contact_name, contact_email, contact_phone, address) VALUES (:name, :contact_name, :contact_email, :contact_phone, :address)');
                    $insertCompany->execute([
                        ':name' => $newCustomerData['company_name'],
                        ':contact_name' => $newCustomerData['contact_name'] ?: null,
                        ':contact_email' => $newCustomerData['contact_email'] ?: null,
                        ':contact_phone' => $newCustomerData['contact_phone'] ?: null,
                        ':address' => $newCustomerData['address'] ?: null,
                    ]);
                    $companyId = (int) $pdo->lastInsertId();
                }

                $lookupClient = $pdo->prepare('SELECT id FROM clients WHERE company_id = :company_id');
                $lookupClient->execute([':company_id' => $companyId]);
                $clientId = $lookupClient->fetchColumn();

                if ($clientId) {
                    $updateClient = $pdo->prepare('UPDATE clients SET broker_id = :broker_id, partner_id = :partner_id WHERE id = :id');
                    $updateClient->execute([
                        ':broker_id' => $brokerId,
                        ':partner_id' => $partnerId,
                        ':id' => $clientId,
                    ]);
                } else {
                    $insertClient = $pdo->prepare('INSERT INTO clients (broker_id, partner_id, company_id) VALUES (:broker_id, :partner_id, :company_id)');
                    $insertClient->execute([
                        ':broker_id' => $brokerId,
                        ':partner_id' => $partnerId,
                        ':company_id' => $companyId,
                    ]);
                }

                $pdo->commit();

                $prefill = [
                    'customer_business_name' => $newCustomerData['company_name'],
                ];

                if ($newCustomerData['contact_name'] !== '') {
                    $prefill['customer_contact_name'] = $newCustomerData['contact_name'];
                }

                if ($newCustomerData['customer_abn'] !== '') {
                    $prefill['customer_abn'] = $newCustomerData['customer_abn'];
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

                if ($partnerId) {
                    foreach ($partners as $partner) {
                        if ((int) $partner['id'] === $partnerId) {
                            $prefill['partner_company_name'] = $partner['name'];
                            break;
                        }
                    }
                }

                header('Location: ' . buildReturnUrl($returnTo, $prefill));
                exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Unable to save the customer. Please try again.';
            }
        }
    }
}

$searchResults = fetchCustomerResults($pdo, $searchTerm);
$returnLabel = determineReturnLabel($returnTo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Customer</title>
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
    <h1>Add Customer</h1>
    <nav class="breadcrumbs">
        <a href="index.php">Dashboard</a> &raquo;
        <a href="reports.php">Reports</a> &raquo;
        <a href="<?= htmlspecialchars($returnTo) ?>"><?= htmlspecialchars($returnLabel) ?></a> &raquo;
        Add Customer
    </nav>
</header>

<main>
    <section class="section">
        <h2>Find an Existing Customer</h2>
        <form method="get">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($encodedReturnTo) ?>">
            <div class="form-group">
                <label for="query">Search by business or contact name</label><br>
                <input id="query" type="text" name="query" size="40" value="<?= htmlspecialchars($searchTerm) ?>">
                <button type="submit">Search</button>
            </div>
        </form>

        <?php if (!empty($searchResults)): ?>
            <table>
                <thead>
                <tr>
                    <th>Business</th>
                    <th>Primary Contact</th>
                    <th>Partner</th>
                    <th>Broker</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($searchResults as $result): ?>
                    <?php
                    $prefill = [
                        'customer_business_name' => $result['customer_name'],
                    ];

                    if (!empty($result['customer_contact'])) {
                        $prefill['customer_contact_name'] = $result['customer_contact'];
                    }

                    if (!empty($result['partner_name'])) {
                        $prefill['partner_company_name'] = $result['partner_name'];
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
                            <strong><?= htmlspecialchars($result['customer_name']) ?></strong><br>
                            <?php if (!empty($result['customer_email'])): ?>
                                <small><?= htmlspecialchars($result['customer_email']) ?></small><br>
                            <?php endif; ?>
                            <?php if (!empty($result['customer_phone'])): ?>
                                <small><?= htmlspecialchars($result['customer_phone']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($result['customer_contact'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($result['partner_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($result['broker_name'] ?? 'N/A') ?></td>
                        <td><a href="<?= htmlspecialchars($returnUrl) ?>">Use this customer</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ($searchTerm !== ''): ?>
            <p>No customers match "<?= htmlspecialchars($searchTerm) ?>".</p>
        <?php else: ?>
            <p>No customers found yet. Create a customer below.</p>
        <?php endif; ?>
    </section>

    <section class="section">
        <h2>Create a New Customer</h2>

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
                <label for="company_name">Business Name</label><br>
                <input id="company_name" type="text" name="company_name" size="40" value="<?= htmlspecialchars($newCustomerData['company_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="contact_name">Primary Contact</label><br>
                <input id="contact_name" type="text" name="contact_name" size="40" value="<?= htmlspecialchars($newCustomerData['contact_name']) ?>">
            </div>

            <div class="form-group">
                <label for="contact_email">Contact Email</label><br>
                <input id="contact_email" type="email" name="contact_email" size="40" value="<?= htmlspecialchars($newCustomerData['contact_email']) ?>">
            </div>

            <div class="form-group">
                <label for="contact_phone">Contact Phone</label><br>
                <input id="contact_phone" type="text" name="contact_phone" size="40" value="<?= htmlspecialchars($newCustomerData['contact_phone']) ?>">
            </div>

            <div class="form-group">
                <label for="address">Business Address</label><br>
                <textarea id="address" name="address" rows="3" cols="60"><?= htmlspecialchars($newCustomerData['address']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="customer_abn">Customer ABN</label><br>
                <input id="customer_abn" type="text" name="customer_abn" size="20" value="<?= htmlspecialchars($newCustomerData['customer_abn']) ?>">
            </div>

            <div class="form-group">
                <label for="broker_id">Broker</label><br>
                <select id="broker_id" name="broker_id" required>
                    <option value="">Select a broker</option>
                    <?php foreach ($brokers as $broker): ?>
                        <option value="<?= htmlspecialchars((string) $broker['id']) ?>" <?= $newCustomerData['broker_id'] !== '' && (int) $newCustomerData['broker_id'] === (int) $broker['id'] ? 'selected' : '' ?>><?= htmlspecialchars($broker['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="partner_id">Partner (optional)</label><br>
                <select id="partner_id" name="partner_id">
                    <option value="">No partner</option>
                    <?php foreach ($partners as $partner): ?>
                        <option value="<?= htmlspecialchars((string) $partner['id']) ?>" <?= $newCustomerData['partner_id'] !== '' && (int) $newCustomerData['partner_id'] === (int) $partner['id'] ? 'selected' : '' ?>><?= htmlspecialchars($partner['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit">Save Customer</button>
            <a href="<?= htmlspecialchars($returnTo) ?>">Cancel</a>
        </form>
    </section>
</main>
</body>
</html>
