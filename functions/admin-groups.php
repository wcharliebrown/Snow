<?php
/**
 * Admin - Group Management
 */

requirePermission('group_management');

$page['site_name']    = getenv('SITE_NAME') ?: 'Snow Framework';
$page['current_year'] = date('Y');
$page['current_user'] = getCurrentUser();
$page['navigation']   = getNavigationMenu();

$action  = $_GET['action'] ?? 'list';
$groupId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$message = '';
$error   = '';

// Core groups that cannot be deactivated or deleted
$coreGroups = ['Administrators'];

// All available permissions for the checkboxes
$allPermissions = dbGetRows("SELECT * FROM permissions ORDER BY name", []);

// ── Helpers ───────────────────────────────────────────────────────────────────

function getGroupById(int $id): array|false {
    return dbGetRow("SELECT * FROM user_groups_list WHERE id = ?", [$id]);
}

function getGroupPermissionIds(int $groupId): array {
    $rows = dbGetRows("SELECT permission_id FROM group_permissions WHERE group_id = ?", [$groupId]);
    return array_column($rows, 'permission_id');
}

function saveGroupPermissions(int $groupId, array $permissionIds): void {
    dbQuery("DELETE FROM group_permissions WHERE group_id = ?", [$groupId]);
    foreach ($permissionIds as $permId) {
        dbInsert('group_permissions', [
            'group_id'      => $groupId,
            'permission_id' => (int)$permId,
        ]);
    }
}

// ── Handle POST ───────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? $action;

    if ($postAction === 'add') {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status      = $_POST['status'] ?? 'active';
        $permIds     = array_map('intval', (array)($_POST['permissions'] ?? []));

        if (!$name) {
            $error = 'Group name is required.';
        } else {
            $existing = dbGetRow("SELECT id FROM user_groups_list WHERE name = ?", [$name]);
            if ($existing) {
                $error = 'A group with that name already exists.';
            } else {
                $newId = dbInsert('user_groups_list', [
                    'name'        => $name,
                    'description' => $description,
                    'status'      => $status,
                ]);
                saveGroupPermissions((int)$newId, $permIds);
                header('Location: /admin/groups?msg=created');
                exit;
            }
        }
        $action = 'add';

    } elseif ($postAction === 'edit' && $groupId) {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status      = $_POST['status'] ?? 'active';
        $permIds     = array_map('intval', (array)($_POST['permissions'] ?? []));

        $targetGroup = getGroupById($groupId);
        if (!$name) {
            $error = 'Group name is required.';
        } elseif ($targetGroup && in_array($targetGroup['name'], $coreGroups) && $status !== 'active') {
            $error = 'Core groups cannot be deactivated.';
        } else {
            $dup = dbGetRow("SELECT id FROM user_groups_list WHERE name = ? AND id != ?", [$name, $groupId]);
            if ($dup) {
                $error = 'That name is already used by another group.';
            } else {
                dbUpdate('user_groups_list', [
                    'name'        => $name,
                    'description' => $description,
                    'status'      => $status,
                ], 'id = ?', [$groupId]);
                saveGroupPermissions($groupId, $permIds);
                header('Location: /admin/groups?msg=updated');
                exit;
            }
        }
        $action = 'edit';

    } elseif ($postAction === 'delete' && $groupId) {
        $targetGroup = getGroupById($groupId);
        if ($targetGroup && in_array($targetGroup['name'], $coreGroups)) {
            $error  = 'Core groups cannot be deleted.';
            $action = 'list';
        } else {
            // Remove permissions and memberships, then hard-delete
            dbQuery("DELETE FROM group_permissions WHERE group_id = ?", [$groupId]);
            dbQuery("DELETE FROM user_groups WHERE group_id = ?", [$groupId]);
            dbQuery("DELETE FROM user_groups_list WHERE id = ?", [$groupId]);
            header('Location: /admin/groups?msg=deleted');
            exit;
        }
    }
}

// ── Flash messages ────────────────────────────────────────────────────────────

if (isset($_GET['msg'])) {
    $msgs    = ['created' => 'Group created.', 'updated' => 'Group updated.', 'deleted' => 'Group deleted.'];
    $message = $msgs[$_GET['msg']] ?? '';
}

// ── Build content ─────────────────────────────────────────────────────────────

ob_start();

if ($action === 'add') {
    $page['title'] = 'Add Group';
    $page['breadcrumbs'] = [
        ['title' => 'Home',      'url' => '/'],
        ['title' => 'Admin',     'url' => '/admin'],
        ['title' => 'Groups',    'url' => '/admin/groups'],
        ['title' => 'Add Group', 'url' => '', 'current' => true],
    ];
    $selectedPerms = array_map('intval', (array)($_POST['permissions'] ?? []));
    ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <a href="/admin/groups" class="btn btn-secondary btn-sm mb-3">&larr; Back to Groups</a>
    <form method="post" action="/admin/groups?action=add">
        <input type="hidden" name="action" value="add">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Group Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active"   <?= (($_POST['status'] ?? 'active') === 'active')   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= (($_POST['status'] ?? 'active') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label">Permissions</label>
                <div class="row">
                    <?php foreach ($allPermissions as $perm): ?>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="checkbox" name="permissions[]" class="form-check-input"
                                id="perm_<?= (int)$perm['id'] ?>" value="<?= (int)$perm['id'] ?>"
                                <?= in_array((int)$perm['id'], $selectedPerms) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="perm_<?= (int)$perm['id'] ?>">
                                <strong><?= htmlspecialchars($perm['name']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($perm['description'] ?? '') ?></small>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-success">Create Group</button>
            <a href="/admin/groups" class="btn btn-secondary ms-2">Cancel</a>
        </div>
    </form>
    <?php

} elseif ($action === 'edit' && $groupId) {
    $editGroup = getGroupById($groupId);
    if (!$editGroup) {
        echo '<div class="alert alert-danger">Group not found.</div>';
    } else {
        $page['title'] = 'Edit Group';
        $page['breadcrumbs'] = [
            ['title' => 'Home',       'url' => '/'],
            ['title' => 'Admin',      'url' => '/admin'],
            ['title' => 'Groups',     'url' => '/admin/groups'],
            ['title' => 'Edit Group', 'url' => '', 'current' => true],
        ];
        $isCoreGroup   = in_array($editGroup['name'], $coreGroups);
        $currentPerms  = getGroupPermissionIds($groupId);
        // POST overrides current DB values if there was a validation error
        $selectedPerms = isset($_POST['permissions'])
            ? array_map('intval', (array)$_POST['permissions'])
            : $currentPerms;
        ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($isCoreGroup): ?><div class="alert alert-warning">This is a core group and cannot be deactivated.</div><?php endif; ?>
        <a href="/admin/groups" class="btn btn-secondary btn-sm mb-3">&larr; Back to Groups</a>
        <form method="post" action="/admin/groups?action=edit&id=<?= $groupId ?>">
            <input type="hidden" name="action" value="edit">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Group Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                        value="<?= htmlspecialchars($_POST['name'] ?? $editGroup['name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" <?= $isCoreGroup ? 'disabled' : '' ?>>
                        <option value="active"   <?= (($_POST['status'] ?? $editGroup['status']) === 'active')   ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($_POST['status'] ?? $editGroup['status']) === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <?php if ($isCoreGroup): ?>
                    <input type="hidden" name="status" value="active">
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? $editGroup['description'] ?? '') ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Permissions</label>
                    <div class="row">
                        <?php foreach ($allPermissions as $perm): ?>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" name="permissions[]" class="form-check-input"
                                    id="perm_<?= (int)$perm['id'] ?>" value="<?= (int)$perm['id'] ?>"
                                    <?= in_array((int)$perm['id'], $selectedPerms) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="perm_<?= (int)$perm['id'] ?>">
                                    <strong><?= htmlspecialchars($perm['name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($perm['description'] ?? '') ?></small>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/admin/groups" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
        <?php
    }

} else {
    // List view
    $page['title'] = 'Group Management';
    $page['breadcrumbs'] = [
        ['title' => 'Home',   'url' => '/'],
        ['title' => 'Admin',  'url' => '/admin'],
        ['title' => 'Groups', 'url' => '', 'current' => true],
    ];
    $groupCount  = dbGetRow("SELECT COUNT(*) AS n FROM user_groups_list", [])['n'] ?? 0;
    $listReport  = getReportByName('groups_list');
    ?>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span><?= (int)$groupCount ?> group<?= $groupCount !== 1 ? 's' : '' ?></span>
        <a href="/admin/groups?action=add" class="btn btn-success btn-sm">+ Add Group</a>
    </div>
    <?php if ($listReport): ?>
        <?= renderReport($listReport) ?>
    <?php else: ?>
        <div class="alert alert-warning">Report <code>groups_list</code> not found. <a href="/admin/reports">Recreate it in Reports</a>.</div>
    <?php endif; ?>
    <?php
}

$page['content'] = ob_get_clean();
?>
