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

try {
    $stmt = $pdo->query("
        SELECT
            ma_linh_kien,
            ten_linh_kien,
            gia_ban,
            thue_pct
        FROM dm_linh_kien
        WHERE is_active = 1
        ORDER BY ma_linh_kien
    ");

    jsonResponse(true, '', $stmt->fetchAll());

} catch (Exception $e) {
    jsonResponse(false, $e->getMessage());
}
