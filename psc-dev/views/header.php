<?php
require_once __DIR__ . '/../includes/auth.php';

$userInfo = getUserInfo();
// var_dump($userInfo);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PSC - Phần Mềm Quản Lý</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable@13/dist/handsontable.full.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Header Bar -->
<div class="header">
    <h1>📋 PSC</h1>
    <div class="user-info">
        <span class="user-name">👤 <?php echo htmlspecialchars($userInfo['full_name'] ?? 'User'); ?></span>
        <a href="logout.php" class="btn-logout">Đăng xuất</a>
    </div>
</div>
