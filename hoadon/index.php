<?php

$host = 'localhost';
$user = 'tiepnhandcare';
$db = 'tiepnhandcare';
$pass = 'Wx2KY2EAiLJbtwBs';
$charset = 'utf8mb4';
$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8");
//require 'db.php'; // chứa kết nối PDO

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$sql = "SELECT * FROM hoa_don WHERE 1";
if ($from && $to) {
    $sql .= " AND ngay_cap_nhat BETWEEN '$from' AND '$to'";
}
$sql .= " ORDER BY ngay_cap_nhat DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thông báo Hóa đơn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="p-3">
    <h2 class="mb-4">Danh sách Hóa đơn được chúng tôi thay thế hoặc điều chỉnh, chi tiết xem bên dưới</h2>

    <form method="get" class="row g-3 mb-4">
        <div class="col-sm-5">
            <label>Từ ngày</label>
            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
        </div>
        <div class="col-sm-5">
            <label>Đến ngày</label>
            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
        </div>
        <div class="col-sm-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Lọc</button>
        </div>
    </form>

    <table class="table table-bordered table-striped table-responsive">
        <thead class="table-dark">
            <tr>
                <th>Ngày cập nhật</th>
                <th>Số HĐ Gốc</th>
                <th>Số HĐ Thay Thế</th>
                <th>Số HĐ Điều Chỉnh</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['ngay_cap_nhat'] ?></td>
                <td><?= $row['so_hd_goc'] ?></td>
                <td><?= $row['so_hd_thay_the'] ?: '-' ?></td>
                <td><?= $row['so_hd_dieu_chinh'] ?: '-' ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
