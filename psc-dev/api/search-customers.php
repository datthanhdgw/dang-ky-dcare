<?php
/**
 * API endpoint to search customers by customer_id or customer_name
 * Used with Select2 AJAX
 */
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../db.php';
    $term = isset($_GET['term']) ? trim($_GET['term']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    if (strlen($term) < 1) {
        echo json_encode([
            'results' => [],
            'pagination' => ['more' => false]
        ]);
        exit;
    }
    
    $searchTerm = '%' . $term . '%';

    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.customer_id,
            c.customer_name,
            c.address,
            c.mst,
            c.email
        FROM customer c
        WHERE c.customer_id LIKE ? 
           OR c.customer_name LIKE ?
        ORDER BY c.customer_name
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$searchTerm, $searchTerm, $limit + 1, $offset]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($customers) > $limit;
    if ($hasMore) {
        array_pop($customers);
    }
    // Format results for Select2
    $results = array_map(function($customer) {
        return [
            'id' => $customer['id'],
            'text' => $customer['customer_id'] . ' - ' . $customer['customer_name'],
            'customer_id' => $customer['customer_id'],
            'customer_name' => $customer['customer_name'],
            'address' => $customer['address'] ?? '',
            'mst' => $customer['mst'] ?? '',
            'email' => $customer['email'] ?? ''
        ];
    }, $customers);
    
    echo json_encode([
        'results' => $results,
        'pagination' => [
            'more' => $hasMore
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
