<?php
/**
 * Login page custom script
 */

// Set page data first
$page['csrf_field'] = csrfField();
$page['site_name'] = getenv('SITE_NAME') ?: 'Snow Framework';
$page['title'] = 'Login';
$page['content'] = ''; // Content will be rendered by template
$page['current_year'] = date('Y');
$page['current_user'] = getCurrentUser();
$page['navigation'] = getNavigationMenu();
$page['breadcrumbs'] = [];
$page['hasPermission'] = function($permission) {
    return hasPermission($permission);
};

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($email === '' || $password === '') {
        $page['error'] = 'Please enter both email and password.';
    } else {
        $user = loginUser($email, $password);
        if ($user) {
            if (!empty($user['require_2fa'])) {
                // 2FA path: generate OTP, send email, set pending session key
                $code      = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                // Delete any existing pending code for this user
                dbQuery("DELETE FROM login_otp WHERE user_id = ?", [$user['id']]);

                // Store new code
                dbInsert('login_otp', [
                    'user_id'    => $user['id'],
                    'code'       => $code,
                    'expires_at' => $expiresAt,
                    'attempts'   => 0,
                ]);

                // Store pending state — do NOT keep user_id in session
                // loginUser() already set $_SESSION['user_id']; undo that
                unset($_SESSION['user_id']);
                $_SESSION['otp_pending_user_id'] = $user['id'];

                // Send OTP by email
                sendEmailTemplate('login_otp', $user['email'], [
                    'first_name' => $user['first_name'],
                    'otp_code'   => $code,
                ]);

                logInfo("2FA OTP sent to user: {$user['id']} ({$user['email']})");
                header('Location: /login-otp');
                exit;
            } else {
                // No 2FA — existing flow
                $redirect = $_SESSION['redirect_after_login'] ?? '/admin';
                unset($_SESSION['redirect_after_login']);
                header('Location: ' . $redirect);
                exit;
            }
        } else {
            $page['error'] = 'Invalid email or password.';
        }
    }
}
?>