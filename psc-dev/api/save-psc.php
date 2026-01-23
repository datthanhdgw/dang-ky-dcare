<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db-helpers.php';

// Require authentication
requireAuthAjax();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON decode error: ' . json_last_error_msg());
    }
    
    if (!$data || !is_array($data)) {
        throw new Exception('Payload JSON không hợp lệ hoặc rỗng');
    }
    
    $master = $data['master'] ?? null;
    $details = $data['details'] ?? [];
    
    if (!$master || !isset($master['psc_no'])) {
        throw new Exception('Thiếu dữ liệu master');
    }
    
    $pdo = getDB();
    $result = savePSCData($pdo, $master, $details);
    
    echo json_encode($result);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
