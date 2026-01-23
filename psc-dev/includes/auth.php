<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged in user data
 * @return array|null
 */
function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'name' => $_SESSION['user_name'] ?? null,
    ];
}

/**
 * Attempt to login with username and password
 * @param string $username
 * @param string $password
 * @return bool
 */
function login(string $username, string $password): bool
{
    // Include database connection
    require_once __DIR__ . '/../db.php';
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, password, full_name FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        // var_dump($user);
        if (!$user) {
            return false;
        }
        
        if (!password_verify($password, $user['password'])) {
            // echo 'Password mismatch';
            return false;
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_name'] = $user['full_name'];

        session_regenerate_id(true);
        
        return true;
        
    } catch (PDOException $e) {
        // var_dump($e);
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

/**
 * Logout the current user
 * @return void
 */
function logout(): void
{
    // Unset all session variables
    $_SESSION = [];
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Require authentication - redirect to login if not authenticated
 * @return void
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        header('Location: views/login.php');
        exit;
    }
}

/**
 * Require authentication for AJAX requests - return JSON error if not authenticated
 * @return void
 */
function requireAuthAjax(): void
{
    if (!isLoggedIn()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized. Please login.',
            'code' => 'UNAUTHORIZED'
        ]);
        exit;
    }
}

/**
 * Get user info for display (alias for header.php compatibility)
 * @return array
 */
function getUserInfo(): array
{
    $user = getCurrentUser();
    if (!$user) {
        return [
            'full_name' => 'Guest',
            'username' => null,
            'id' => null,
        ];
    }
    
    return [
        'id' => $user['id'],
        'username' => $user['username'],
        'full_name' => $user['name'],
    ];
}
