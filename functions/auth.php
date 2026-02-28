<?php
/**
 * Authentication and Authorization Functions for Snow Framework
 */

// Load password policy functions if not already loaded (SEC-03)
if (!function_exists('getPasswordPolicy')) {
    require_once __DIR__ . '/password-policy.php';
}

/**
 * Get current user ID from session
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 */
function getCurrentUser() {
    $userId = getCurrentUserId();
    if (!$userId) {
        return null;
    }
    
    $sql = "SELECT * FROM users WHERE id = ? AND status = 'active'";
    return dbGetRow($sql, [$userId]);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return getCurrentUserId() !== null;
}

/**
 * Check if user has specific permission
 */
function hasPermission($permission, $userId = null) {
    if ($userId === null) {
        $userId = getCurrentUserId();
    }
    
    if (!$userId) {
        return false;
    }
    
    // Check if user has the permission through their groups
    $sql = "SELECT COUNT(*) as count
            FROM user_groups ug
            JOIN user_groups_list g ON ug.group_id = g.id
            JOIN group_permissions gp ON g.id = gp.group_id
            JOIN permissions p ON gp.permission_id = p.id
            WHERE ug.user_id = ? AND p.name = ? AND g.status = 'active'";
    
    $result = dbGetRow($sql, [$userId, $permission]);
    return $result['count'] > 0;
}

/**
 * Check if user is admin
 */
function isAdmin($userId = null) {
    return hasPermission('admin_access', $userId);
}

/**
 * Require user to be logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /login');
        exit;
    }
}

/**
 * Require specific permission
 */
function requirePermission($permission) {
    requireLogin();
    
    if (!hasPermission($permission)) {
        http_response_code(403);
        echo '<h1>Access Denied</h1>';
        echo '<p>You do not have permission to access this page.</p>';
        exit;
    }
}

/**
 * Login user
 */
function loginUser($email, $password) {
    $sql = "SELECT * FROM users WHERE email = ? AND status = 'active'";
    $user = dbGetRow($sql, [$email]);
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        logError("Login failed for email: $email");
        return false;
    }
    
    // Update last login
    dbUpdate('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
    
    // Prevent session fixation — regenerate ID before writing session vars
    session_regenerate_id(true);  // true = delete old session row

    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['login_time'] = time();

    // Update session row with authenticated user_id (for admin sessions viewer)
    $sessionId = session_id();
    if ($sessionId) {
        dbQuery(
            "UPDATE sessions SET user_id = ? WHERE session_id = ?",
            [$user['id'], $sessionId]
        );
    }

    // Check password expiry policy (SEC-03)
    if (isPasswordExpired((int)$user['id'])) {
        $_SESSION['require_password_change'] = true;
        // Still return user — caller (login page script) redirects to change-password page
    }

    logInfo("User logged in: {$user['id']} ({$user['email']})");
    return $user;
}

/**
 * Logout user
 */
function logoutUser() {
    $userId = getCurrentUserId();
    if ($userId) {
        logInfo("User logged out: $userId");
    }
    
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
}

/**
 * Register new user
 */
function registerUser($email, $password, $firstName, $lastName, $groups = []) {
    // Check if email already exists
    $existing = dbGetRow("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        return false;
    }
    
    // Create user
    $userData = [
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'first_name' => $firstName,
        'last_name' => $lastName,
        'status' => 'active',
        'created_date' => date('Y-m-d H:i:s')
    ];
    
    $userId = dbInsert('users', $userData);
    
    // Add to groups if specified
    if (!empty($groups)) {
        foreach ($groups as $groupId) {
            dbInsert('user_groups', ['user_id' => $userId, 'group_id' => $groupId]);
        }
    }
    
    logInfo("New user registered: $userId ($email)");
    return $userId;
}

/**
 * Change user password
 *
 * Returns:
 *   false         — old password wrong or user not found
 *   array         — non-empty array of validation error strings (strength or reuse failure)
 *   true          — success
 */
function changePassword($userId, $oldPassword, $newPassword) {
    $sql = "SELECT * FROM users WHERE id = ? AND status = 'active'";
    $user = dbGetRow($sql, [$userId]);

    if (!$user || !password_verify($oldPassword, $user['password_hash'])) {
        return false;
    }

    // Validate strength against policy (SEC-03)
    $errors = validatePasswordStrength($newPassword);
    if (!empty($errors)) {
        return $errors;  // Return error array (not false) so caller can display messages
    }

    // Check reuse (SEC-03)
    if (isPasswordReused((int)$userId, $newPassword)) {
        return ['Password was recently used. Choose a different password.'];
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $success = dbUpdate('users', ['password_hash' => $newHash], 'id = ?', [$userId]);

    if ($success) {
        recordPasswordChange((int)$userId, $newHash);  // Record in history (SEC-03)
    }

    return $success;
}

/**
 * Reset password
 */
function resetPassword($email) {
    $user = dbGetRow("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
    if (!$user) {
        return false;
    }
    
    // Generate reset token
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Save reset token
    dbUpdate('users', [
        'reset_token' => $token,
        'reset_token_expiry' => $expiry
    ], 'id = ?', [$user['id']]);
    
    // Send reset email
    sendEmailTemplate('password_reset', $user['email'], [
        'first_name' => $user['first_name'],
        'reset_link' => getenv('SITE_URL') . '/reset-password?token=' . $token
    ]);
    
    logInfo("Password reset requested for user: {$user['id']} ($email)");
    return $token;
}

/**
 * Verify reset token
 */
function verifyResetToken($token) {
    $sql = "SELECT * FROM users WHERE reset_token = ? AND reset_token_expiry > NOW() AND status = 'active'";
    return dbGetRow($sql, [$token]);
}

/**
 * Complete password reset
 */
function completePasswordReset($token, $newPassword) {
    $user = verifyResetToken($token);
    if (!$user) {
        return false;
    }
    
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $success = dbUpdate('users', [
        'password_hash' => $newHash,
        'reset_token' => null,
        'reset_token_expiry' => null
    ], 'id = ?', [$user['id']]);
    
    if ($success) {
        recordPasswordChange((int)$user['id'], $newHash);  // Record in history (SEC-03)
        logInfo("Password reset completed for user: {$user['id']}");
    }

    return $success;
}

/**
 * Get user groups
 */
function getUserGroups($userId) {
    $sql = "SELECT g.* FROM user_groups_list g
            JOIN user_groups ug ON g.id = ug.group_id
            WHERE ug.user_id = ? AND g.status = 'active'";
    return dbGetRows($sql, [$userId]);
}

/**
 * Get user permissions
 */
function getUserPermissions($userId) {
    $sql = "SELECT DISTINCT p.* FROM permissions p 
            JOIN group_permissions gp ON p.id = gp.permission_id 
            JOIN user_groups ug ON gp.group_id = ug.group_id 
            WHERE ug.user_id = ?";
    return dbGetRows($sql, [$userId]);
}

/**
 * Check session timeout
 */
function checkSessionTimeout() {
    if (!isset($_SESSION['login_time'])) {
        return false;
    }
    
    $timeout = getenv('SESSION_TIMEOUT') ?: 3600;
    if (time() - $_SESSION['login_time'] > $timeout) {
        logoutUser();
        return false;
    }
    
    // Refresh login time
    $_SESSION['login_time'] = time();
    return true;
}
?>