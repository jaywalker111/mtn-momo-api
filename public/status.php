<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/MomoClient.php';
require_once __DIR__ . '/../src/helpers.php';

$reference = trim((string)($_GET['reference'] ?? ''));
if (!preg_match('/^[a-f0-9-]{36}$/i', $reference)) {
    http_response_code(400);
    exit('Invalid payment reference.');
}

$stmt = db()->prepare('SELECT * FROM transactions WHERE momo_reference_id = ? LIMIT 1');
$stmt->execute([$reference]);
$transaction = $stmt->fetch();

if (!$transaction) {
    http_response_code(404);
    exit('Transaction not found.');
}

if (in_array($transaction['status'], ['PENDING', 'UNKNOWN'], true)) {
    try {
        $result = momo()->getPaymentStatus($reference);
        $remote = $result['json'] ?? [];
        $newStatus = mapMomoStatus($remote['status'] ?? null);

        $update = db()->prepare(
            'UPDATE transactions
             SET status = ?, financial_transaction_id = ?, reason = ?, response_payload = ?
             WHERE momo_reference_id = ?'
        );
        $update->execute([
            $newStatus,
            $remote['financialTransactionId'] ?? null,
            $remote['reason'] ?? null,
            json_encode($remote),
            $reference
        ]);

        $stmt->execute([$reference]);
        $transaction = $stmt->fetch();
    } catch (Throwable $e) {
        // Keep the local transaction pending; cron/callback can retry.
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="refresh" content="8">
<title>Payment Status</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f4f4;padding:40px}
.card{max-width:500px;margin:auto;background:white;padding:28px;border-radius:12px}
.status{font-size:24px;font-weight:bold}
</style>
</head>
<body>
<div class="card">
<h2>Payment Status</h2>
<p>Reference: <?= htmlspecialchars($transaction['momo_reference_id']) ?></p>
<p>Amount: GHS <?= htmlspecialchars(number_format((float)$transaction['amount'], 2)) ?></p>
<p class="status"><?= htmlspecialchars($transaction['status']) ?></p>

<?php if ($transaction['status'] === 'SUCCESSFUL'): ?>
<p>Payment received successfully.</p>
<?php elseif ($transaction['status'] === 'FAILED'): ?>
<p>Payment was not completed.</p>
<?php else: ?>
<p>Waiting for MTN to complete the transaction. This page checks again automatically.</p>
<?php endif; ?>

<a href="index.php">Make another payment</a>
</div>
</body>
</html>
