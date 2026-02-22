<?php
/**
 * Admin - User Management
 */

requirePermission('user_management');

$page['site_name']    = getenv('SITE_NAME') ?: 'Snow Framework';
$page['current_year'] = date('Y');
$page['current_user'] = getCurrentUser();
$page['navigation']   = getNavigationMenu();

$action  = $_GET['action'] ?? 'list';
$userId  = isset($_GET['id']) ? (int)$_GET['id'] : null;
$message = '';
$error   = '';

// ── Handle POST ──────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? $action;

    if ($postAction === 'add') {
        $email     = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $password  = $_POST['password'] ?? '';
        $status    = $_POST['status'] ?? 'active';

        if (!$email || !$firstName || !$lastName || !$password) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $existing = dbGetRow("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existing) {
                $error = 'A user with that email already exists.';
            } else {
                dbInsert('users', [
                    'email'         => $email,
                    'first_name'    => $firstName,
                    'last_name'     => $lastName,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'status'        => $status,
                    'created_date'  => date('Y-m-d H:i:s'),
                ]);
                header('Location: /admin/users?msg=created');
                exit;
            }
        }
        $action = 'add';

    } elseif ($postAction === 'edit' && $userId) {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $status    = $_POST['status'] ?? 'active';
        $newPass   = $_POST['password'] ?? '';

        if (!$email || !$firstName || !$lastName) {
            $error = 'First name, last name, and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            $dup = dbGetRow("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
            if ($dup) {
                $error = 'That email is already used by another user.';
            } else {
                $fields = [
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'email'      => $email,
                    'status'     => $status,
                ];
                if ($newPass !== '') {
                    if (strlen($newPass) < 8) {
                        $error = 'New password must be at least 8 characters.';
                    } else {
                        $fields['password_hash'] = password_hash($newPass, PASSWORD_DEFAULT);
                    }
                }
                if (!$error) {
                    dbUpdate('users', $fields, 'id = ?', [$userId]);
                    header('Location: /admin/users?msg=updated');
                    exit;
                }
            }
        }
        $action = 'edit';

    } elseif ($postAction === 'delete' && $userId) {
        // Prevent deleting yourself
        if ($userId === (int)($page['current_user']['id'] ?? 0)) {
            $error  = 'You cannot delete your own account.';
            $action = 'list';
        } else {
            dbUpdate('users', ['status' => 'inactive'], 'id = ?', [$userId]);
            header('Location: /admin/users?msg=deleted');
            exit;
        }
    }
}

// ── Flash messages ───────────────────────────────────────────────────────────

if (isset($_GET['msg'])) {
    $msgs = ['created' => 'User created.', 'updated' => 'User updated.', 'deleted' => 'User deactivated.'];
    $message = $msgs[$_GET['msg']] ?? '';
}

// ── Build content ────────────────────────────────────────────────────────────

ob_start();

if ($action === 'add') {
    $page['title'] = 'Add User';
    $page['breadcrumbs'] = [
        ['title' => 'Home',            'url' => '/'],
        ['title' => 'Admin',           'url' => '/admin'],
        ['title' => 'Users',           'url' => '/admin/users'],
        ['title' => 'Add User',        'url' => '',  'current' => true],
    ];
    ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <a href="/admin/users" class="btn btn-secondary btn-sm mb-3">&larr; Back to Users</a>
    <form method="post" action="/admin/users?action=add">
        <input type="hidden" name="action" value="add">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" minlength="8" required>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-success">Create User</button>
            <a href="/admin/users" class="btn btn-secondary ms-2">Cancel</a>
        </div>
    </form>
    <?php

} elseif ($action === 'edit' && $userId) {
    $editUser = dbGetRow("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$editUser) {
        echo '<div class="alert alert-danger">User not found.</div>';
    } else {
        $page['title'] = 'Edit User';
        $page['breadcrumbs'] = [
            ['title' => 'Home',    'url' => '/'],
            ['title' => 'Admin',   'url' => '/admin'],
            ['title' => 'Users',   'url' => '/admin/users'],
            ['title' => 'Edit',    'url' => '', 'current' => true],
        ];
        ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <a href="/admin/users" class="btn btn-secondary btn-sm mb-3">&larr; Back to Users</a>
        <form method="post" action="/admin/users?action=edit&id=<?= $userId ?>">
            <input type="hidden" name="action" value="edit">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($_POST['first_name'] ?? $editUser['first_name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($_POST['last_name'] ?? $editUser['last_name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? $editUser['email']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active"   <?= ($editUser['status'] === 'active')   ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($editUser['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended"<?= ($editUser['status'] === 'suspended')? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
                    <input type="password" name="password" class="form-control" minlength="8">
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/admin/users" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
        <?php
    }

} else {
    // List view
    $page['title'] = 'User Management';
    $page['breadcrumbs'] = [
        ['title' => 'Home',  'url' => '/'],
        ['title' => 'Admin', 'url' => '/admin'],
        ['title' => 'Users', 'url' => '', 'current' => true],
    ];
    $userCount  = dbGetRow("SELECT COUNT(*) AS n FROM users", [])['n'] ?? 0;
    $listReport = getReportByName('users_list');
    ?>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span><?= (int)$userCount ?> user<?= $userCount !== 1 ? 's' : '' ?></span>
        <a href="/admin/users?action=add" class="btn btn-success btn-sm">+ Add User</a>
    </div>
    <?php if ($listReport): ?>
        <?= renderReport($listReport) ?>
    <?php else: ?>
        <div class="alert alert-warning">Report <code>users_list</code> not found. <a href="/admin/reports">Recreate it in Reports</a>.</div>
    <?php endif; ?>
    <?php
}

$page['content'] = ob_get_clean();
?>
