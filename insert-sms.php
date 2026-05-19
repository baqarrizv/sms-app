<?php
require_once 'includes/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$user     = currentUser();
$selected = $_POST['selected'] ?? [];

if (empty($selected)) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Koi record select nahi tha.'];
    header('Location: dashboard.php');
    exit;
}

$phones = $_POST['record_phone'] ?? [];
$msgs   = $_POST['record_msg']   ?? [];

$db      = getDB();
$stmt    = $db->prepare("
    INSERT INTO sms (msg, number, current_status, status, activity_by, activity_at)
    VALUES (?, ?, 'queued', 'pending', ?, NOW())
");

$inserted = 0;
$skipped  = 0;

foreach ($selected as $idx) {
    $phone = trim($phones[$idx] ?? '');
    $msg   = trim($msgs[$idx]   ?? '');

    if (!$phone && !$msg) {
        $skipped++;
        continue;
    }

    $stmt->execute([$msg, $phone, $user['id']]);
    $inserted++;
}

$_SESSION['flash'] = [
    'type' => 'success',
    'msg'  => "$inserted records SMS table mein insert ho gaye." . ($skipped ? " ($skipped skip hue empty records)" : '')
];

header('Location: sms-list.php');
exit;
