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

/* ========= Lấy tham số ========= */
$psc_no = trim($_GET['psc_no'] ?? '');

if ($psc_no === '') {
    jsonResponse(false, 'Chưa nhập số PSC');
}

try {
    /* ========= LOAD MASTER ========= */
    $stmt = $pdo->prepare("
        SELECT 
            psc_no,
            psc_date
        FROM psc_master
        WHERE psc_no = ?
        LIMIT 1
    ");
    $stmt->execute([$psc_no]);
    $master = $stmt->fetch();

    if (!$master) {
        jsonResponse(false, 'Không tìm thấy phiếu PSC');
    }

    /* ========= LOAD DETAIL ========= */
    $stmt = $pdo->prepare("
        SELECT
            linh_kien,
            ten_linh_kien,
            so_luong,
            don_gia,
            thue_pct,
            tien_thue,
            thanh_tien,
            ghi_chu,
            ngay_good_delivery
        FROM psc_detail
        WHERE psc_no = ?
        ORDER BY id
    ");
    $stmt->execute([$psc_no]);
    $details = $stmt->fetchAll();

    /* ========= MAP DATA CHO HANDSONTABLE ========= */
    foreach ($details as &$r) {
        // đảm bảo NULL đúng kiểu cho JS
        if ($r['ngay_good_delivery'] === null) {
            $r['ngay_good_delivery'] = '';
        }
    }

    jsonResponse(true, '', [
        'master'  => $master,
        'details' => $details
    ]);

} catch (Exception $e) {
    jsonResponse(false, $e->getMessage());
}
