<?php
require 'db.php';
session_start();

$centerId = $_GET['center'] ?? null;
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php?center=" . urlencode($centerId));
    exit;
}
if (!$centerId) {
    die('<div style="margin:2rem; font-size:1.5rem; color:red;">❌ Thiếu tham số ?center=storeId trên URL</div>');
}

// Lọc
$filter = $_GET['filter'] ?? 'today';
$status = $_GET['status'] ?? 'not_called';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$whereClause = "center_id = ?";
$params = [$centerId];

if ($status === 'not_called') {
    $whereClause .= " AND called = 0";
}

switch ($filter) {
    case '7days':
        $whereClause .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'range':
        if ($from && $to) {
            $whereClause .= " AND DATE(created_at) BETWEEN ? AND ?";
            $params[] = $from;
            $params[] = $to;
        }
        break;
    default: // today
        $whereClause .= " AND DATE(created_at) = CURDATE()";
        break;
}

// Xác nhận khách
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['call_id'])) {
    $callId = (int) $_POST['call_id'];

    $stmt = $pdo->prepare("SELECT * FROM queue WHERE id = ? AND center_id = ?");
    $stmt->execute([$callId, $centerId]);
    $customer = $stmt->fetch();

    if (!$customer) {
        $message = "⚠️ Khách không tồn tại.";
    } elseif ($customer['called']) {
        $message = "⚠️ Khách này đã xác nhận rồi.";
    } else {
        $response = sendToERP($customer);
        if ($response['success']) {
            $pdo->prepare("UPDATE queue SET called = 1 WHERE id = ?")->execute([$callId]);
            $message = "✅ Đã xác nhận khách hàng: <strong>" . htmlspecialchars($customer['name']) . "</strong>";
        } else {
            $message = "<span class='text-danger'>❌ Xác nhận thất bại:</span> " . htmlspecialchars($response['message']);
        }
    }
}

// Truy vấn danh sách
$stmt = $pdo->prepare("SELECT * FROM queue WHERE $whereClause ORDER BY created_at ASC");
$stmt->execute($params);
$queue = $stmt->fetchAll();

function sendToERP($data) {
    $jsonData = json_encode([
        'name' => $data['name'],
        'phone' => $data['phone'],
        'address' => $data['address'],
        'productname' => $data['product'],
        'center' => $data['center_id']
    ]);

    $ch = curl_init('https://n8n.danhthiep.info/webhook/tiepnhandcaredgw');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData)
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) return ['success' => false, 'message' => $err];
    return $code === 200 ? ['success' => true] : ['success' => false, 'message' => $res];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tiếp nhận khách hàng</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --main-color: #FF6E00; }
        .bg-main { background-color: var(--main-color) !important; }
        .btn-outline-main {
            color: var(--main-color);
            border-color: var(--main-color);
        }
        .btn-outline-main:hover {
            background-color: var(--main-color);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
<div class="container mt-4 mb-5">
    <div class="card shadow">
        <div class="card-header bg-main text-white">
            <h4 class="mb-0">📋 Danh sách tiếp nhận</h4>
        </div>
        <div class="card-body">
            <h5 class="mb-3">🔧 Trung tâm: <strong><?= htmlspecialchars($centerId) ?></strong></h5>

            <?php if ($message): ?>
                <div class="alert alert-info"><?= $message ?></div>
            <?php endif; ?>

            <!-- BỘ LỌC -->
            <form method="get" class="row g-2 mb-4">
                <input type="hidden" name="center" value="<?= htmlspecialchars($centerId) ?>">
                <div class="col-md-2">
                    <select name="filter" class="form-select" onchange="toggleDateRange(this.value)">
                        <option value="today" <?= $filter == 'today' ? 'selected' : '' ?>>📅 Hôm nay</option>
                        <option value="7days" <?= $filter == '7days' ? 'selected' : '' ?>>📆 7 ngày qua</option>
                        <option value="range" <?= $filter == 'range' ? 'selected' : '' ?>>📆 Từ ngày → đến ngày</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="not_called" <?= $status == 'not_called' ? 'selected' : '' ?>>🟡 Chưa xác nhận</option>
                        <option value="all" <?= $status == 'all' ? 'selected' : '' ?>>📋 Tất cả</option>
                    </select>
                </div>
                <div class="col-md-2 d-none" id="rangeFrom">
                    <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
                </div>
                <div class="col-md-2 d-none" id="rangeTo">
                    <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
                </div>
                <div class="col-md-4 d-flex">
                    <button type="submit" class="btn btn-outline-main me-2">Lọc</button>
                    <a href="?center=<?= urlencode($centerId) ?>&filter=<?= $filter ?>&status=<?= $status ?>" class="btn btn-outline-secondary">🔄 Làm mới</a>
                </div>
            </form>

            <!-- DANH SÁCH -->
            <?php if (!empty($queue)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th>#</th>
                                <th>Họ tên</th>
                                <th>SĐT</th>
                                <th>Địa chỉ</th>
                                <th>Sản phẩm</th>
                                <th>Thời gian</th>
                                <th>Trạng thái</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($queue as $i => $item): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= htmlspecialchars($item['phone']) ?></td>
                                    <td><?= htmlspecialchars($item['address']) ?></td>
                                    <td><?= htmlspecialchars($item['product']) ?></td>
                                    <td><?= $item['created_at'] ?></td>
                                    <td>
                                        <?= $item['called']
                                            ? '<span class="badge bg-success">Đã xác nhận</span>'
                                            : '<span class="badge bg-warning text-dark">Chưa xác nhận</span>' ?>
                                    </td>
                                    <td>
                                        <?php if (!$item['called']): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="call_id" value="<?= $item['id'] ?>">
                                                <button class="btn btn-sm btn-outline-main">Xác nhận</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">✅ Không có khách nào phù hợp với bộ lọc.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="text-end mt-3">
        <a href="logout.php?center=<?= urlencode($centerId) ?>" class="btn btn-sm btn-outline-danger">Đăng xuất</a>
    </div>
</div>

<script>
function toggleDateRange(value) {
    const from = document.getElementById('rangeFrom');
    const to = document.getElementById('rangeTo');
    const show = value === 'range';
    from.classList.toggle('d-none', !show);
    to.classList.toggle('d-none', !show);
}
document.addEventListener('DOMContentLoaded', () => {
    toggleDateRange("<?= $filter ?>");
});
</script>
</body>
</html>
