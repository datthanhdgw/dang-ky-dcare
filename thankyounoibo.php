<?php
$centerId = $_GET['center'] ?? '';
$centerName = $_GET['center_name'] ?? '';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng ký thành công</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-size: 1.2rem;
        }
    </style>
</head>
<body class="bg-light d-flex align-items-center" style="min-height: 100vh;">

<div class="container text-center">
    <div class="card shadow-sm p-5">
        <h2 class="text-success mb-4">🎉 Cảm ơn bạn đã đăng ký!</h2>
        <p class="fs-5">Bạn đã được thêm vào hàng đợi tại <strong><?= htmlspecialchars($centerName ?: 'Trung tâm bảo hành') ?></strong>.</p>

        <a href="tiepnhannoibo.php?center=<?= urlencode($centerId) ?>" class="btn btn-primary mt-4">← Đăng ký thêm người khác</a>
    </div>
</div>

</body>
</html>
