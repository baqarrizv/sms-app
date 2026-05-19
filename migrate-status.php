<?php
/**
 * Migration Script - Update sms table ENUM values
 * Run ONCE after the new status structure
 */
require_once 'includes/config.php';

$db = getDB();

echo "Starting migration...\n";

$db->exec("ALTER TABLE `sms` MODIFY COLUMN `current_status` ENUM('pending','processing','sent','stop') DEFAULT 'pending'");
echo "✓ current_status ENUM updated\n";

$db->exec("ALTER TABLE `sms` MODIFY COLUMN `status` ENUM('active','inactive') DEFAULT 'active'");
echo "✓ status ENUM updated\n";

$db->exec("UPDATE `sms` SET `current_status` = 'pending' WHERE `current_status` = 'queued'");
echo "✓ Converted 'queued' to 'pending'\n";

$db->exec("UPDATE `sms` SET `current_status` = 'sent' WHERE `current_status` = 'done'");
echo "✓ Converted 'done' to 'sent'\n";

$db->exec("UPDATE `sms` SET `current_status` = 'pending' WHERE `current_status` = 'processing'");
echo "✓ Converted 'processing' to 'pending' (will be resent)\n";

$db->exec("UPDATE `sms` SET `status` = 'active' WHERE `status` IN ('pending','sent')");
echo "✓ Converted old 'pending'/'sent' to 'active'\n";

$db->exec("UPDATE `sms` SET `status` = 'inactive' WHERE `status` = 'failed'");
echo "✓ Converted old 'failed' to 'inactive'\n";

echo "\nMigration complete!\n";
