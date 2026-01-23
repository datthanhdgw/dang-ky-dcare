<?php
/**
 * Database Configuration
 * Returns PDO connection
 */

$DB_HOST = 'localhost';
$DB_NAME = 'b2x-dev';
$DB_USER = 'root';
$DB_PASS = 'root';
$DB_CHARSET = 'utf8mb4';

/**
 * Get database connection
 * @return PDO
 */
function getDB() {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_CHARSET;
    
    static $pdo = null;
    
    if ($pdo === null) {
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
    }
    
    return $pdo;
}
