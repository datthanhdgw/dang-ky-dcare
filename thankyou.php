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
        :root {
            --main-color: #FF6E00;
        }
        body {
            font-size: 1.2rem;
        }
        .btn-main {
            background-color: var(--main-color);
            border-color: var(--main-color);
            color: #fff;
        }
        .btn-main:hover {
            background-color: #e66100;
            border-color: #e66100;
        }
    </style>
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">

<div class="container text-center">
    <div class="card shadow-sm p-5">
        <h2 class="text-main mb-4" style="color: var(--main-color)">🎉 Cảm ơn bạn đã đăng ký!</h2>
        <p class="fs-5">Bạn đã được thêm vào hàng đợi tại <strong><?= htmlspecialchars($centerName ?: 'Trung tâm bảo hành') ?></strong>.</p>

        <a href="index.php?center=<?= urlencode($centerId) ?>" class="btn btn-main mt-4">← Đăng ký thêm người khác</a>
    </div>
</div>

</body>
</html>
