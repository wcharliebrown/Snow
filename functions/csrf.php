<?php
/**
 * CSRF Protection Functions for Snow Framework (SEC-01)
 *
 * Per-session CSRF token pattern:
 *  - One token per session (not per-request — avoids back-button and multi-tab breakage)
 *  - Stored in $_SESSION['csrf_token']
 *  - Generated with random_bytes(32) — cryptographically secure
 *  - Compared with hash_equals() — timing-safe
 *
 * Usage:
 *  1. requireCsrf() is called centrally in renderPage() before any POST handler runs
 *  2. <?= csrfField() ?> is added inside every <form method="post">
 */

/**
 * Get or generate the per-session CSRF token.
 * Generates a new token if none exists in the session.
 *
 * @return string  64-character hex token
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token against the session token.
 * Uses hash_equals() to prevent timing-oracle attacks.
 *
 * @param  string $submittedToken  Value from $_POST['csrf_token']
 * @return bool   true = valid, false = invalid or missing
 */
function validateCsrfToken(string $submittedToken): bool {
    $stored = $_SESSION['csrf_token'] ?? '';
    if (empty($stored) || empty($submittedToken)) {
        return false;
    }
    return hash_equals($stored, $submittedToken);
}

/**
 * Render a hidden CSRF token input field for use inside HTML forms.
 * Call inside every <form method="post">:  <?= csrfField() ?>
 *
 * @return string  HTML input element string
 */
function csrfField(): string {
    $token = htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Central CSRF enforcement gate.
 * Call before processing any POST request. Aborts with HTTP 403 if token is invalid.
 * GET, HEAD, OPTIONS requests pass through immediately.
 *
 * Called once in renderPage() before the custom_script include.
 */
function requireCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;  // Only enforce on POST
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        http_response_code(403);
        // Output minimal error page — avoid calling full template renderer which may loop
        echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body>';
        echo '<h1>403 Forbidden</h1>';
        echo '<p>Invalid or missing security token. Please go back and try again.</p>';
        echo '</body></html>';
        exit;
    }
}
