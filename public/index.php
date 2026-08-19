<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/MomoClient.php';
require_once __DIR__ . '/../src/helpers.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf($_POST['csrf'] ?? '');

        $amount = trim((string)($_POST['amount'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));

        if (!preg_match('/^\d+(\.\d{1,4})?$/', $amount)) {
            throw new InvalidArgumentException('Enter a valid amount.');
        }

        // >>> OPTIONAL CHANGE: enforce your own minimum/maximum amount here <<<
        if ((float)$amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        $msisdn = normalizeMsisdn($phone);
        /*
         * >>> CHANGE THIS if you already have an order/invoice system <<<
         * Replace this generated ID with your own order ID, for example:
         * $externalId = 'ORDER-' . $yourOrderId;
         */
        $externalId = 'ORD-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $referenceId = newUuidV4();

        $config = appConfig();
        $payloadResult = momo()->requestToPay(
            $referenceId,
            number_format((float)$amount, 2, '.', ''),
            $config['momo']['currency'],
            $msisdn,
            $externalId
        );

        $status = $payloadResult['http_status'] === 202 ? 'PENDING' : 'UNKNOWN';

        $stmt = db()->prepare(
            'INSERT INTO transactions
            (external_id, momo_reference_id, payer_msisdn, amount, currency, status, request_payload, response_payload)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $externalId,
            $referenceId,
            $msisdn,
            (float)$amount,
            $config['momo']['currency'],
            $status,
            json_encode($payloadResult['payload']),
            json_encode([
                'http_status' => $payloadResult['http_status'],
                'body' => $payloadResult['raw'],
            ]),
        ]);

        if ($payloadResult['http_status'] !== 202) {
            throw new RuntimeException(
                'MTN did not accept the payment request. HTTP ' . $payloadResult['http_status']
            );
        }

        header('Location: status.php?reference=' . urlencode($referenceId));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pay with MTN MoMo</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:40px}
.card{max-width:460px;margin:auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 4px 18px #0001}
input,button{width:100%;box-sizing:border-box;padding:13px;margin:7px 0 15px;font-size:16px}
button{cursor:pointer}
.error{background:#fee;padding:12px;border-radius:6px}
small{color:#666}
</style>
</head>
<body>
<div class="card">
<h2>MTN MoMo Payment</h2>

<?php if ($error): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">

<label>Amount (GHS)</label>
<input name="amount" type="number" min="0.01" step="0.01" required placeholder="10.00">

<label>MTN Mobile Number</label>
<input name="phone" type="tel" required placeholder="0241234567">

<button type="submit">Pay with MTN MoMo</button>
</form>

<small>You will receive a MoMo payment prompt on your phone.</small>
</div>
</body>
</html>
