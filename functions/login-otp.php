<?php
/**
 * Login OTP verification page custom script
 * SEC-05: Email OTP second factor
 */

// If no pending OTP session, redirect back to login immediately
if (empty($_SESSION['otp_pending_user_id'])) {
    header('Location: /login');
    exit;
}

// Set page data
$page['csrf_field']   = csrfField();
$page['site_name']    = getenv('SITE_NAME') ?: 'Snow Framework';
$page['title']        = 'Verify Login';
$page['content']      = '';
$page['current_year'] = date('Y');
$page['current_user'] = null; // Not yet logged in
$page['navigation']   = getNavigationMenu();
$page['breadcrumbs']  = [];
$page['hasPermission'] = function($permission) {
    return hasPermission($permission);
};

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pendingUserId = $_SESSION['otp_pending_user_id'] ?? null;

    if (!$pendingUserId) {
        header('Location: /login');
        exit;
    }

    $submittedCode = trim($_POST['otp_code'] ?? '');
    $otpRow = dbGetRow(
        "SELECT * FROM login_otp WHERE user_id = ? AND expires_at > NOW() AND attempts < 3",
        [$pendingUserId]
    );

    if (!$otpRow || $otpRow['code'] !== $submittedCode) {
        if ($otpRow) {
            dbQuery("UPDATE login_otp SET attempts = attempts + 1 WHERE id = ?", [$otpRow['id']]);
            if ($otpRow['attempts'] + 1 >= 3) {
                dbQuery("DELETE FROM login_otp WHERE id = ?", [$otpRow['id']]);
                unset($_SESSION['otp_pending_user_id']);
                $error = 'Too many failed attempts. Please log in again.';
            } else {
                $remaining = 2 - $otpRow['attempts'];
                $error = 'Invalid code. ' . $remaining . ' attempt(s) remaining.';
            }
        } else {
            $error = 'Code expired or invalid. Please log in again.';
            unset($_SESSION['otp_pending_user_id']);
        }
    } else {
        // Success — complete the login
        dbQuery("DELETE FROM login_otp WHERE id = ?", [$otpRow['id']]);
        $user = dbGetRow("SELECT * FROM users WHERE id = ?", [$pendingUserId]);
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['login_time'] = time();
        unset($_SESSION['otp_pending_user_id']);
        $sessionId = session_id();
        if ($sessionId) {
            dbQuery("UPDATE sessions SET user_id = ? WHERE session_id = ?", [$user['id'], $sessionId]);
        }
        logInfo("User completed 2FA login: {$user['id']} ({$user['email']})");
        $redirect = $_SESSION['redirect_after_login'] ?? '/admin';
        unset($_SESSION['redirect_after_login']);
        header('Location: ' . $redirect);
        exit;
    }
}

ob_start();
?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php if (empty($_SESSION['otp_pending_user_id'])): ?>
<p><a href="/login" class="btn btn-secondary btn-sm">Back to Login</a></p>
<?php endif; ?>
<?php endif; ?>

<?php if (!empty($_SESSION['otp_pending_user_id'])): ?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h5 class="card-title mb-3">Two-Factor Authentication</h5>
                <p class="text-muted small mb-3">Enter the 6-digit code sent to your email address.</p>
                <form method="post" action="/login-otp">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label for="otp_code" class="form-label">Verification Code</label>
                        <input type="text" id="otp_code" name="otp_code" class="form-control form-control-lg"
                               maxlength="6" pattern="[0-9]{6}" placeholder="000000"
                               autocomplete="one-time-code" autofocus required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Verify Code</button>
                    </div>
                </form>
                <div class="mt-3 text-center">
                    <a href="/login" class="text-muted small">Cancel and return to login</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php
$page['content'] = ob_get_clean();
?>
