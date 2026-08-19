<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/MomoClient.php';
require_once __DIR__ . '/../src/helpers.php';

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    exit('Invalid JSON.');
}

/*
 * MTN callbacks are asynchronous. Treat the callback as a signal to look up
 * the transaction, then verify the authoritative status with MTN before
 * fulfilling an order.
 */
$reference = $_SERVER['HTTP_X_REFERENCE_ID'] ?? $_GET['reference'] ?? '';

if (!$reference && isset($data['referenceId'])) {
    $reference = (string)$data['referenceId'];
}

if (!$reference) {
    http_response_code(400);
    exit('Missing reference.');
}

$stmt = db()->prepare('SELECT * FROM transactions WHERE momo_reference_id = ? LIMIT 1');
$stmt->execute([$reference]);
$transaction = $stmt->fetch();

if (!$transaction) {
    http_response_code(404);
    exit('Unknown transaction.');
}

try {
    $result = momo()->getPaymentStatus($reference);
    $remote = $result['json'] ?? [];

    $status = mapMomoStatus($remote['status'] ?? null);

/*
 * >>> CHANGE THIS when connecting to your own order system <<<
 * After MTN confirms SUCCESSFUL, update your orders/invoices table here.
 * Make the fulfilment operation idempotent so it can never happen twice.
 */

    $update = db()->prepare(
        'UPDATE transactions
         SET status = ?,
             financial_transaction_id = ?,
             reason = ?,
             callback_payload = ?,
             response_payload = ?,
             paid_at = CASE WHEN ? = "SUCCESSFUL" THEN COALESCE(paid_at, NOW()) ELSE paid_at END
         WHERE momo_reference_id = ?'
    );

    $update->execute([
        $status,
        $remote['financialTransactionId'] ?? null,
        $remote['reason'] ?? null,
        $raw,
        json_encode($remote),
        $status,
        $reference
    ]);

    http_response_code(200);
    echo 'OK';
} catch (Throwable $e) {
    error_log('MoMo callback processing error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Retry later';
}
