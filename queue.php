<?php
require 'db.php'; // chứa kết nối PDO

function getClientIP() {
    return $_SERVER['HTTP_CLIENT_IP'] ??
           $_SERVER['HTTP_X_FORWARDED_FOR'] ??
           $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['name'], $_POST['phone'], $_POST['address'],
                $_POST['productname'], $_POST['center'],
                $_POST['center_name'], $_POST['agree'])
    ) {
        die("❌ Thiếu thông tin đầu vào.");
    }

    // Lấy và xử lý dữ liệu đầu vào
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $product = trim($_POST['productname']);
    $centerId = trim($_POST['center']);
    $centerName = trim($_POST['center_name']);
    $ip = getClientIP();

    // Kiểm tra SĐT
    if (!preg_match('/^\d{10}$/', $phone)) {
        die("<h4 style='color:red'>❌ Số điện thoại phải đủ 10 chữ số.</h4><a href='index.php'>← Quay lại</a>");
    }

    // Ghi vào database
    $stmt = $pdo->prepare("INSERT INTO queue 
        (name, phone, address, product, center_id, center_name, client_ip) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $phone, $address, $product, $centerId, $centerName, $ip]);

    // Chuyển đến trang cảm ơn
    header("Location: thankyou.php?center=" . urlencode($centerId) . "&center_name=" . urlencode($centerName));
    exit;
} else {
    die("❌ Phương thức không hợp lệ.");
}
