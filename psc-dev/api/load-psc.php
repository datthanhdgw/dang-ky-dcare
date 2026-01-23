<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db-helpers.php';

// Require authentication
requireAuthAjax();

try {
    $pscNo = $_GET['psc_no'] ?? '';
    
    if (empty($pscNo)) {
        throw new Exception('PSC number is required');
    }
    
    $pdo = getDB();
    $data = getPSCData($pdo, $pscNo);
    
    if ($data === null) {
        echo json_encode(['exists' => false]);
    } else {
        echo json_encode([
            'exists' => true,
            'master' => $data['master'],
            'details' => $data['details']
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
