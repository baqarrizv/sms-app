<?php
require_once 'includes/config.php';
if (isLoggedIn()) {
    header('Location: dashboard');
} else {
    header('Location: login');
}
exit;
