<?php
function getCenters() {
    $url = "https://dgw-icrm-prd-masterdata.isdcorp.vn/api/v1/MasterData/Common/get-list-store-2?AccountId=18d8c4cc-d073-4637-9a31-9e9253dac3ed";
    $headers = [
        "Accept: application/json",
        "User-Agent: Mozilla/5.0",
        "sec-ch-ua-platform: \"Windows\"",
        "sec-ch-ua: \"Chromium\";v=\"134\"",
        "sec-ch-ua-mobile: ?0"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);
    return $json['data'][0]['storeListResponses'] ?? [];
}

$selectedCenter = $_GET['center'] ?? ($_COOKIE['selected_center'] ?? '');
$disableCenterSelect = isset($_GET['center']);
$centers = getCenters();

$selectedCenterName = '';
foreach ($centers as $c) {
    if ($c['storeId'] === $selectedCenter) {
        $selectedCenterName = $c['storeName'];
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký dịch vụ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --main-color: #FF6E00;
        }
        body {
            font-size: 1rem;
        }
        .form-control, .form-select {
            font-size: 1rem;
            padding: 0.5rem 0.75rem;
        }
        .btn {
            font-size: 1rem;
            padding: 0.5rem 0.75rem;
        }
        .form-check-label {
            font-size: 0.95rem;
        }
        .form-check-input {
            width: 1.25em;
            height: 1.25em;
        }

        .bg-main {
            background-color: var(--main-color) !important;
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

        @media (max-width: 576px) {
            .container {
                padding: 0 10px;
            }
            .card {
                margin: 0;
            }
        }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-main text-white">
            <h5 class="mb-0">🛠 Trung tâm bảo hành – Đăng ký tiếp nhận dịch vụ</h5>
        </div>
        <div class="card-body">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">🎉 Đăng ký thành công! Bạn đã được thêm vào hàng đợi.</div>
            <?php endif; ?>

            <form method="post" action="queue.php" onsubmit="saveCenterCookie()">
                <!-- Trung tâm bảo hành -->
                <div class="mb-3">
                    <label for="center" class="form-label">Trung tâm bảo hành</label>
                    <select class="form-select" name="center_display" id="center" required <?= $disableCenterSelect ? 'disabled' : '' ?> onchange="updateCenterName()">
                        <option value="">-- Chọn trung tâm --</option>
                        <?php foreach ($centers as $c): ?>
                            <option value="<?= htmlspecialchars($c['storeId']) ?>"
                                <?= ($selectedCenter === $c['storeId']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['storeName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="center" id="center_hidden" value="<?= htmlspecialchars($selectedCenter) ?>">
                    <input type="hidden" name="center_name" id="center_name" value="<?= htmlspecialchars($selectedCenterName) ?>">
                </div>

                <div class="mb-3">
                    <label for="name" class="form-label">Họ tên</label>
                    <input type="text" class="form-control" id="name" name="name" required placeholder="VD: Nguyễn Văn A">
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Số điện thoại (10 số)</label>
                    <input type="text" class="form-control" id="phone" name="phone" pattern="\d{10}" required placeholder="VD: 0987654321">
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Địa chỉ</label>
                    <input type="text" class="form-control" id="address" name="address" required placeholder="VD: 123 Đường ABC, Quận X">
                </div>

                <div class="mb-3">
                    <label for="productname" class="form-label">Tên sản phẩm</label>
                    <input type="text" class="form-control" id="productname" name="productname" required placeholder="VD: Máy lọc không khí XYZ">
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" value="1" name="agree" id="agree" required>
                    <label class="form-check-label" for="agree">
                        Tôi đồng ý cung cấp và cho phép Chúng tôi thu thập, xử lý và lưu trữ dữ liệu cá nhân theo
                        <a href="https://digiworld.com.vn/rieng-tu" target="_blank">Chính sách bảo vệ dữ liệu cá nhân</a>. 
                        Nếu cung cấp thông tin người khác, tôi cam kết đã được họ đồng ý.
                    </label>
                </div>

                <button type="submit" class="btn btn-main w-100">✅ Đồng ý & đăng ký</button>
            </form>
        </div>
    </div>
</div>

<script>
function saveCenterCookie() {
    const select = document.getElementById('center');
    if (select && !select.disabled) {
        const centerId = select.value;
        document.cookie = "selected_center=" + encodeURIComponent(centerId) + "; path=/; max-age=2592000";
    }
}

function updateCenterName() {
    const select = document.getElementById('center');
    const selectedName = select.options[select.selectedIndex]?.text || '';
    const selectedId = select.value;

    document.getElementById('center_name').value = selectedName;
    document.getElementById('center_hidden').value = selectedId;
}

document.addEventListener('DOMContentLoaded', updateCenterName);
</script>

</body>
</html>
