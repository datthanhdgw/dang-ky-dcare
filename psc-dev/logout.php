<?php
/**
 * Logout Handler
 */
require_once __DIR__ . '/includes/auth.php';

logout();

// Redirect to login page
header('Location: views/login.php');
exit;
