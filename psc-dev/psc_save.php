<?php
require_once 'db.php';

/* ========= JSON response ========= */
function jsonResponse($ok, $msg = '', $data = null) {
    echo json_encode([
        'success' => $ok,
        'message' => $msg,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ========= Lấy dữ liệu ========= */
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(false, 'Dữ liệu không hợp lệ');
}

$psc_no   = trim($input['psc_no'] ?? '');
$psc_date = $input['psc_date'] ?? null;
$details  = $input['details'] ?? [];

if ($psc_no === '') {
    jsonResponse(false, 'Chưa nhập số PSC');
}

try {
    $pdo->beginTransaction();

    /* ========= LƯU MASTER ========= */
    $stmt = $pdo->prepare("
        INSERT INTO psc_master (psc_no, psc_date)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE
            psc_date = VALUES(psc_date)
    ");

    $psc_date = ($psc_date === '' ? null : $psc_date);
    $stmt->execute([$psc_no, $psc_date]);

    /* ========= XOÁ DETAIL CŨ ========= */
    $pdo->prepare("DELETE FROM psc_detail WHERE psc_no = ?")
        ->execute([$psc_no]);

    /* ========= PREPARE CHECK LINH KIỆN ========= */
    $stmtLK = $pdo->prepare("
        SELECT gia_ban, thue_pct
        FROM dm_linh_kien
        WHERE ma_linh_kien = ?
          AND is_active = 1
    ");

    /* ========= INSERT DETAIL ========= */
    $stmtIns = $pdo->prepare("
        INSERT INTO psc_detail (
            psc_no,
            linh_kien,
            ten_linh_kien,
            so_luong,
            don_gia,
            thue_pct,
            tien_thue,
            thanh_tien,
            ghi_chu,
            ngay_good_delivery
        ) VALUES (?,?,?,?,?,?,?,?,?,?)
    ");

    foreach ($details as $row) {

        // ❗ Không lưu dòng rỗng
        if (empty($row['linh_kien'])) {
            continue;
        }

        /* --- Validate linh kiện --- */
        $stmtLK->execute([$row['linh_kien']]);
        $lk = $stmtLK->fetch();

        if (!$lk) {
            throw new Exception(
                'Mã linh kiện không tồn tại: ' . $row['linh_kien']
            );
        }

        /* --- ÉP GIÁ & THUẾ THEO DANH MỤC --- */
        $so_luong = (int)($row['so_luong'] ?? 0);
        if ($so_luong <= 0) $so_luong = 1;

        $don_gia  = (int)$lk['gia_ban'];
        $thue_pct = (int)$lk['thue_pct'];

        $tien = $so_luong * $don_gia;
        $tien_thue = round($tien * $thue_pct / 100);
        $thanh_tien = $tien + $tien_thue;

        $ngay_good_delivery = $row['ngay_good_delivery'] ?? null;
        if ($ngay_good_delivery === '') {
            $ngay_good_delivery = null;
        }

        $stmtIns->execute([
            $psc_no,
            $row['linh_kien'],
            $row['ten_linh_kien'] ?? '',
            $so_luong,
            $don_gia,
            $thue_pct,
            $tien_thue,
            $thanh_tien,
            $row['ghi_chu'] ?? '',
            $ngay_good_delivery
        ]);
    }

    $pdo->commit();
    jsonResponse(true, 'Lưu PSC thành công');

} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(false, $e->getMessage());
}
