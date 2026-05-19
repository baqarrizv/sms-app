<?php
require_once 'includes/config.php';
requireLogin();

// Remove uploaded file
if (isset($_SESSION['uploaded_file']) && file_exists($_SESSION['uploaded_file'])) {
    unlink($_SESSION['uploaded_file']);
}
unset($_SESSION['uploaded_file'], $_SESSION['file_name']);

header('Location: dashboard.php?cleared=1');
exit;
