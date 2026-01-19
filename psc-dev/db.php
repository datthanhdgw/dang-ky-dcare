<?php
/**
 * Database connection (PDO)
 * DB: b2x
 */

$DB_HOST = 'localhost';
$DB_NAME = 'b2x-dev';
$DB_USER = 'b2x-dev';
$DB_PASS = 'YyAWE4HeJfn36yG8';
$DB_CHARSET = 'utf8mb4';

try {
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";

    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Không kết nối được database',
        'error'   => $e->getMessage()
    ]);
    exit;
}
