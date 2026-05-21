<?php
require_once 'includes/config.php';

set_time_limit(0);
ignore_user_abort(true);

define('BATCH_SIZE', 10);
define('DELAY_BETWEEN_SMS', 300000);
define('DELAY_BETWEEN_BATCHES', 3000000);

function logMessage($msg) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

function sendSmsViaApi($to, $text) {
    $data = [
        'to' => $to,
        'from' => SMS_FROM,
        'text' => $text
    ];

    $ch = curl_init(SMS_API_URL . 'sms/v3/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-key: ' . SMS_API_KEY,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        return ['status' => 'error', 'message' => $error, 'http_code' => $httpCode];
    }

    return json_decode($response, true) ?: ['status' => 'error', 'message' => 'Invalid response', 'raw' => $response, 'http_code' => $httpCode];
}

logMessage('Cron job started');
logMessage('Rate limit: 10 SMS per batch, 300ms between each SMS, 3s between batches');

$db = getDB();

$updateStmt = $db->prepare("
    UPDATE sms 
    SET  
        current_status = ?, 
        sms_reference_id = ?, 
        activity_at = NOW() 
    WHERE id = ?
");

$totalSent = 0;
$totalFailed = 0;
$batchCount = 0;

while (true) {
    $stmt = $db->prepare("
        SELECT id, number, msg FROM sms 
        WHERE current_status = 'pending' AND status = 'active'
        ORDER BY id ASC 
        LIMIT ?
    ");
    $stmt->bindValue(1, BATCH_SIZE, PDO::PARAM_INT);
    $stmt->execute();
    $records = $stmt->fetchAll();

    if (empty($records)) {
        logMessage('No active pending SMS found. Exiting.');
        break;
    }

    $batchCount++;
    logMessage("Batch #{$batchCount}: Processing " . count($records) . " SMS records");

    $batchSent = 0;
    $batchFailed = 0;

    foreach ($records as $record) {
        $smsId = $record['id'];
        $number = $record['number'];
        $message = substr($record['msg'], 0, 160);

        logMessage("Sending to {$number} (ID: {$smsId})...");

        $updateStmt->execute(['processing', null, $smsId]);

        $result = sendSmsViaApi($number, $message);

        $apiStatus = $result['status'] ?? 'error';
        $messageId = $result['messageId'] ?? $result['message_id'] ?? $result['id'] ?? null;
        $statusCode = $result['statusCode'] ?? null;
        $responseMsg = $result['message'] ?? json_encode($result);

        if ($statusCode == 200 || $apiStatus === 'accepted' || $apiStatus === 'success' || !empty($messageId)) {
            $updateStmt->execute(['sent', $messageId, $smsId]);
            logMessage("✓ Sent to {$number} | Ref: {$messageId}");
            $batchSent++;
        } else {
            $updateStmt->execute(['failed', 'ERROR: ' . $responseMsg, $smsId]);
            logMessage("✗ Failed for {$number} | Reason: {$responseMsg}");
            $batchFailed++;
        }

        usleep(DELAY_BETWEEN_SMS);
    }

    $totalSent += $batchSent;
    $totalFailed += $batchFailed;
    logMessage("Batch #{$batchCount} finished. Sent: {$batchSent}, Failed: {$batchFailed} | Totals: {$totalSent} sent, {$totalFailed} failed");

    usleep(DELAY_BETWEEN_BATCHES);
}

logMessage("Cron job finished. Total Sent: {$totalSent}, Total Failed: {$totalFailed}");
