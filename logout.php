<?php
require_once __DIR__ . '/includes/config.php';
logActivity('LOGOUT', 'Logout');
session_destroy();
header('Location: ' . APP_URL . '/login.php');
exit;
