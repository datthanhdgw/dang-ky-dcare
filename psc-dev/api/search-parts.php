<?php
/**
 * API endpoint to search parts from master_parts table
 * Used with Handsontable autocomplete
 */
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../db.php';
    
    $term = isset($_GET['term']) ? trim($_GET['term']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    
    // If term is empty, return empty array
    if (strlen($term) < 1) {
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
        exit;
    }
    
    $searchTerm = '%' . $term . '%';
    
    // Search by part_code or part_name
    $stmt = $pdo->prepare("
        SELECT 
            part_code,
            part_name,
            retail_price,
            max_price_diff_percent,
            is_active
        FROM master_parts
        WHERE part_code LIKE ? 
           OR part_name LIKE ?
        ORDER BY is_active DESC, part_name
        LIMIT ?
    ");
    $stmt->execute([$searchTerm, $searchTerm, $limit]);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format results for Handsontable autocomplete
    // Returns array of strings for dropdown display
    $labels = [];
    $partsMap = [];
    
    foreach ($parts as $part) {
        $isActive = isset($part['is_active']) ? (int)$part['is_active'] : 1;
        $label = $part['part_code'] . ' - ' . $part['part_name'];
        
        // Add prefix for inactive parts
        if ($isActive === 0) {
            $label = '[INACTIVE] ' . $label;
        }
        
        $labels[] = $label;
        $partsMap[$label] = [
            'part_code' => $part['part_code'],
            'part_name' => $part['part_name'],
            'retail_price' => (float)$part['retail_price'],
            'max_price_diff_percent' => isset($part['max_price_diff_percent']) ? (int)$part['max_price_diff_percent'] : 10,
            'is_active' => $isActive
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $labels,
        'partsMap' => $partsMap
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
