<?php
/**
 * Admin - User Management
 */

require_once __DIR__ . '/password-policy.php';

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
        } else {
            $strengthErrors = validatePasswordStrength($password);
            if (!empty($strengthErrors)) {
                $errors = $strengthErrors;
                $error = implode(' ', $errors);
            }
        }
        if (!$error) {
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
                    'first_name'  => $firstName,
                    'last_name'   => $lastName,
                    'email'       => $email,
                    'status'      => $status,
                    'require_2fa' => isset($_POST['require_2fa']) ? 1 : 0,
                ];
                if ($newPass !== '') {
                    $strengthErrors = validatePasswordStrength($newPass);
                    if (!empty($strengthErrors)) {
                        $error = implode(' ', $strengthErrors);
                    } else {
                        $fields['password_hash'] = password_hash($newPass, PASSWORD_DEFAULT);
                    }
                }
                if (!$error) {
                    dbUpdate('users', $fields, 'id = ?', [$userId]);
                    // Save group memberships: delete all existing, insert new selections
                    $newGroupIds = array_filter(array_map('intval', (array)($_POST['groups'] ?? [])));
                    dbQuery("DELETE FROM user_groups WHERE user_id = ?", [$userId]);
                    foreach ($newGroupIds as $gid) {
                        dbInsert('user_groups', [
                            'user_id'      => $userId,
                            'group_id'     => $gid,
                            'created_date' => date('Y-m-d H:i:s'),
                        ]);
                    }
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
        <?= csrfField() ?>
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
                <input type="password" name="password" class="form-control" minlength="<?= (int)getPasswordPolicy()['min_length'] ?>" required>
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
        // Load group membership data for the widget
        $allGroups       = dbGetRows("SELECT id, name FROM user_groups_list WHERE status = 'active' ORDER BY name", []);
        $userGroups      = getUserGroups($userId);
        $currentGroupIds = array_map('intval', array_column($userGroups, 'id'));
        // On POST validation failure, use submitted values instead
        $selectedGroupIds = isset($_POST['groups'])
            ? array_map('intval', (array)$_POST['groups'])
            : $currentGroupIds;
        ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <a href="/admin/users" class="btn btn-secondary btn-sm mb-3">&larr; Back to Users</a>
        <form method="post" action="/admin/users?action=edit&id=<?= $userId ?>">
            <?= csrfField() ?>
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
                    <input type="password" name="password" class="form-control" minlength="<?= (int)getPasswordPolicy()['min_length'] ?>">
                </div>
                <div class="col-12">
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="require_2fa" id="require_2fa" value="1"
                            <?= (($_POST['require_2fa'] ?? $editUser['require_2fa'] ?? 0) == 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="require_2fa">Require email OTP on login (2FA)</label>
                    </div>
                </div>
            </div>

            <?php if ($allGroups): ?>
            <div class="mt-4">
                <h6 class="mb-2">Group Membership</h6>
                <div class="row g-2">
                    <?php foreach ($allGroups as $g): ?>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="checkbox" name="groups[]" class="form-check-input"
                                id="grp_<?= (int)$g['id'] ?>" value="<?= (int)$g['id'] ?>"
                                <?= in_array((int)$g['id'], $selectedGroupIds) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="grp_<?= (int)$g['id'] ?>">
                                <?= htmlspecialchars($g['name']) ?>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="mt-4">
                <p class="text-muted small">No groups defined. <a href="/admin/groups">Create groups</a> to assign group membership.</p>
            </div>
            <?php endif; ?>

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
