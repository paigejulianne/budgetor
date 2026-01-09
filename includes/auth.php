<?php
/**
 * Authentication helper functions
 */

require_once __DIR__ . '/db.php';

/**
 * Start session if not already started
 */
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    initSession();
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Get current user ID
 */
function getCurrentUserId(): ?int {
    initSession();
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 */
function getCurrentUser(): ?array {
    $userId = getCurrentUserId();
    if (!$userId) {
        return null;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, email, initial_balance, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

/**
 * Require authentication - redirect to login if not authenticated
 */
function requireAuth(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Require authentication for API - return 401 if not authenticated
 */
function requireApiAuth(): void {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

/**
 * Register a new user
 */
function registerUser(string $email, string $password): array {
    $email = trim(strtolower($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email address'];
    }

    if (strlen($password) < 8) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters'];
    }

    $db = getDB();

    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Email already registered'];
    }

    // Create user
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
    $stmt->execute([$email, $passwordHash]);

    $userId = (int)$db->lastInsertId();

    // Log user in
    initSession();
    $_SESSION['user_id'] = $userId;

    return ['success' => true, 'user_id' => $userId];
}

/**
 * Authenticate user login
 */
function loginUser(string $email, string $password): array {
    $email = trim(strtolower($email));

    $db = getDB();
    $stmt = $db->prepare("SELECT id, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid email or password'];
    }

    // Log user in
    initSession();
    $_SESSION['user_id'] = (int)$user['id'];

    return ['success' => true, 'user_id' => (int)$user['id']];
}

/**
 * Log out current user
 */
function logoutUser(): void {
    initSession();
    $_SESSION = [];
    session_destroy();
}

/**
 * Update user's initial balance
 */
function updateInitialBalance(int $userId, float $balance): bool {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET initial_balance = ? WHERE id = ?");
    return $stmt->execute([$balance, $userId]);
}
