<?php
require_once 'includes/config.php';

define('BATCH_SIZE', 50);

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
        curl_close($ch);
        return ['status' => 'error', 'message' => curl_error($ch), 'http_code' => $httpCode];
    }

    curl_close($ch);
    return json_decode($response, true) ?: ['status' => 'error', 'message' => 'Invalid response', 'raw' => $response, 'http_code' => $httpCode];
}

logMessage('Cron job started');

$db = getDB();

$stmt = $db->prepare("
    SELECT id, number, msg FROM sms 
    WHERE status = 'pending' 
    ORDER BY id ASC 
    LIMIT ?
");
$stmt->bindValue(1, BATCH_SIZE, PDO::PARAM_INT);
$stmt->execute();
$records = $stmt->fetchAll();

if (empty($records)) {
    logMessage('No pending SMS found. Exiting.');
    exit(0);
}

logMessage('Found ' . count($records) . ' pending SMS records');

$updateStmt = $db->prepare("
    UPDATE sms 
    SET status = ?, 
        current_status = ?, 
        sms_reference_id = ?, 
        activity_at = NOW() 
    WHERE id = ?
");

$sent = 0;
$failed = 0;

foreach ($records as $record) {
    $smsId = $record['id'];
    $number = $record['number'];
    $message = $record['msg'];

    logMessage("Sending to {$number} (ID: {$smsId})...");

    $updateStmt->execute(['pending', 'processing', null, $smsId]);

    $result = sendSmsViaApi($number, $message);

    $apiStatus = $result['status'] ?? 'error';
    $messageId = $result['messageId'] ?? $result['message_id'] ?? $result['id'] ?? null;
    $statusCode = $result['statusCode'] ?? null;
    $responseMsg = $result['message'] ?? json_encode($result);

    if ($statusCode == 200 || $apiStatus === 'accepted' || $apiStatus === 'success' || !empty($messageId)) {
        $updateStmt->execute(['sent', 'done', $messageId, $smsId]);
        logMessage("✓ Sent to {$number} | Ref: {$messageId}");
        $sent++;
    } else {
        $updateStmt->execute(['failed', 'done', 'ERROR: ' . $responseMsg, $smsId]);
        logMessage("✗ Failed for {$number} | Reason: {$responseMsg}");
        $failed++;
    }

    usleep(200000);
}

logMessage("Cron job finished. Sent: {$sent}, Failed: {$failed}");
