<?php
/**
 * Login page custom script
 */

// Set page data first
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
            // Redirect to intended page or admin dashboard
            $redirect = $_SESSION['redirect_after_login'] ?? '/admin';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        } else {
            $page['error'] = 'Invalid email or password.';
        }
    }
}
?>