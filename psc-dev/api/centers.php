<?php
/**
 * API Endpoint: Get Centers
 * Returns list of all centers
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db-helpers.php';

// Require authentication
requireAuthAjax();

try {
    $pdo = getDB();
    $centers = getCenters($pdo);
    
    echo json_encode(['centers' => $centers]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
