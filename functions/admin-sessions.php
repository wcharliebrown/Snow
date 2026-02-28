<?php
/**
 * Admin Sessions Page — View active sessions and force-logout (SEC-02)
 * Included by renderPage() via pages table custom_script field.
 * CSRF already validated by requireCsrf() in pages.php.
 */

require_once __DIR__ . '/session-handler.php';

// Handle POST: force-logout a session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'force_logout') {
    requirePermission('admin_access');

    $sessionRowId = (int)($_POST['session_id'] ?? 0);
    if ($sessionRowId > 0) {
        // Look up the actual session_id string by numeric row id
        $row = dbGetRow("SELECT session_id FROM sessions WHERE id = ?", [$sessionRowId]);
        if ($row) {
            forceLogoutSession($row['session_id']);
            $_SESSION['flash_success'] = 'Session terminated.';
        }
    }

    header('Location: /admin/sessions');
    exit;
}

// Handle POST: force-logout all sessions for a user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'force_logout_user') {
    requirePermission('admin_access');

    $targetUserId = (int)($_POST['user_id'] ?? 0);
    if ($targetUserId > 0) {
        forceLogoutUser($targetUserId);
        $_SESSION['flash_success'] = 'All sessions for user terminated.';
    }

    header('Location: /admin/sessions');
    exit;
}

// GET: render the active sessions list
requirePermission('admin_access');
$sessions = getActiveSessions();
$currentUserId = getCurrentUserId();

?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Active Sessions</h1>
        <span class="badge bg-secondary"><?= count($sessions) ?> active</span>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (empty($sessions)): ?>
        <div class="alert alert-info">No active sessions found.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-sm">
            <thead class="table-light">
                <tr>
                    <th>User</th>
                    <th>IP Address</th>
                    <th>Browser</th>
                    <th>Started</th>
                    <th>Expires</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $session): ?>
                <tr<?= ($session['user_id'] == $currentUserId) ? ' class="table-warning"' : '' ?>>
                    <td>
                        <?php if ($session['user_name'] && trim($session['user_name']) !== ' '): ?>
                            <strong><?= htmlspecialchars(trim($session['user_name'])) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($session['user_email'] ?? '') ?></small>
                        <?php else: ?>
                            <span class="text-muted">Guest / Unauthenticated</span>
                        <?php endif; ?>
                        <?php if ($session['user_id'] == $currentUserId): ?>
                            <span class="badge bg-warning text-dark ms-1">You</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($session['ip_address'] ?? '—') ?></td>
                    <td>
                        <span title="<?= htmlspecialchars($session['user_agent'] ?? '') ?>">
                            <?= htmlspecialchars(substr($session['user_agent'] ?? '—', 0, 50)) ?>
                            <?= strlen($session['user_agent'] ?? '') > 50 ? '…' : '' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($session['created_at']) ?></td>
                    <td><?= htmlspecialchars($session['expires_at']) ?></td>
                    <td>
                        <?php if ($session['user_id'] != $currentUserId): ?>
                        <form method="post" action="/admin/sessions" class="d-inline"
                              onsubmit="return confirm('Terminate this session?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="force_logout">
                            <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Logout</button>
                        </form>
                        <?php else: ?>
                            <span class="text-muted small">Current session</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
