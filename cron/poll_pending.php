<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/MomoClient.php';
require_once __DIR__ . '/../src/helpers.php';

$stmt = db()->query(
    "SELECT momo_reference_id
     FROM transactions
     WHERE status IN ('PENDING','UNKNOWN')
       AND created_at >= (NOW() - INTERVAL 24 HOUR)
     ORDER BY id ASC
     LIMIT 100"
);

$update = db()->prepare(
    'UPDATE transactions
     SET status = ?, financial_transaction_id = ?, reason = ?,
         response_payload = ?,
         paid_at = CASE WHEN ? = "SUCCESSFUL" THEN COALESCE(paid_at, NOW()) ELSE paid_at END
     WHERE momo_reference_id = ?'
);

foreach ($stmt->fetchAll() as $row) {
    try {
        $result = momo()->getPaymentStatus($row['momo_reference_id']);
        $remote = $result['json'] ?? [];
        $status = mapMomoStatus($remote['status'] ?? null);

        $update->execute([
            $status,
            $remote['financialTransactionId'] ?? null,
            $remote['reason'] ?? null,
            json_encode($remote),
            $status,
            $row['momo_reference_id']
        ]);

        echo $row['momo_reference_id'] . ' => ' . $status . PHP_EOL;
    } catch (Throwable $e) {
        error_log('MoMo polling error: ' . $e->getMessage());
        echo $row['momo_reference_id'] . ' => ERROR' . PHP_EOL;
    }
}
