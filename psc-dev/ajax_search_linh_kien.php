<?php
// ajax_search_linh_kien.php
header('Content-Type: application/json; charset=utf-8');

require_once 'db.php'; // file kết nối PDO $conn

$keyword = trim($_GET['q'] ?? '');

if (mb_strlen($keyword) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $sql = "
        SELECT 
            ma_hang,
            ten_hang,
            don_gia,
            vat_percent 
        FROM linh_kien
        WHERE trang_thai = 1
          AND (ma_hang LIKE :kw OR ten_hang LIKE :kw)
        ORDER BY ma_hang
        LIMIT 20
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':kw' => '%' . $keyword . '%'
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $r) {
        $result[] = [
            'value' => $r['ma_hang'],          // Handsontable dùng value
            'label' => $r['ma_hang'] . ' - ' . $r['ten_hang'],
            'ten_hang' => $r['ten_hang'],
            'don_gia'  => (int)$r['don_gia']
        ];
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
