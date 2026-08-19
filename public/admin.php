<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/MomoClient.php';
require_once __DIR__ . '/../src/helpers.php';

$config = appConfig();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (!($_SESSION['admin'] ?? false)) {
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = (string)($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (
            hash_equals($config['app']['admin_user'], $username) &&
            password_verify($password, $config['app']['admin_password_hash'])
        ) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            header('Location: admin.php');
            exit;
        }

        $error = 'Invalid credentials.';
    }
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Admin Login</title></head>
<body>
<h2>Admin Login</h2>
<?php if ($error): ?><p><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<input name="username" placeholder="Username" required>
<input name="password" type="password" placeholder="Password" required>
<button>Login</button>
</form>
</body></html>
<?php exit; }

$rows = db()->query(
    'SELECT id, external_id, momo_reference_id, payer_msisdn, amount, currency,
            status, financial_transaction_id, reason, created_at, paid_at
     FROM transactions
     ORDER BY id DESC
     LIMIT 200'
)->fetchAll();
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>MoMo Transactions</title>
<style>body{font-family:Arial;margin:25px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ddd;padding:8px;font-size:13px}th{background:#eee}</style>
</head>
<body>
<h2>MTN MoMo Transactions</h2>
<p><a href="?logout=1">Logout</a></p>
<table>
<tr><th>ID</th><th>External ID</th><th>Reference</th><th>Phone</th><th>Amount</th><th>Status</th><th>MTN Tx ID</th><th>Reason</th><th>Created</th><th>Paid</th></tr>
<?php foreach ($rows as $row): ?>
<tr>
<td><?= htmlspecialchars($row['id']) ?></td>
<td><?= htmlspecialchars($row['external_id']) ?></td>
<td><?= htmlspecialchars($row['momo_reference_id'] ?? '') ?></td>
<td><?= htmlspecialchars($row['payer_msisdn']) ?></td>
<td><?= htmlspecialchars($row['currency'].' '.number_format((float)$row['amount'],2)) ?></td>
<td><?= htmlspecialchars($row['status']) ?></td>
<td><?= htmlspecialchars($row['financial_transaction_id'] ?? '') ?></td>
<td><?= htmlspecialchars($row['reason'] ?? '') ?></td>
<td><?= htmlspecialchars($row['created_at']) ?></td>
<td><?= htmlspecialchars($row['paid_at'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
</table>
</body></html>
