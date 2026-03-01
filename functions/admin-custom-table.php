<?php
/**
 * Generic admin handler for custom tables.
 * Derives the table name from the last segment of $page['path'].
 * e.g. path "admin/data/my_table" → table "my_table"
 */

require_once __DIR__ . '/acl.php';

requirePermission('table_data_access');

// Derive table name from URL path
$pathParts = explode('/', $page['path'] ?? '');
$tableName = end($pathParts);

$tableDef = dbGetRow(
    "SELECT * FROM custom_tables WHERE table_name = ? AND status = 'active'",
    [$tableName]
);

if (!$tableDef) {
    $page['title']   = 'Table Not Found';
    $page['content'] = '<div class="alert alert-danger">Custom table <code>'
        . htmlspecialchars($tableName) . '</code> not found or inactive.</div>';
    return;
}

$fields      = dbGetRows(
    "SELECT * FROM custom_table_fields WHERE table_name = ? AND status = 'active' ORDER BY display_order, field_name",
    [$tableName]
);
$displayName = $tableDef['display_name'];

$page['site_name']    = getenv('SITE_NAME') ?: 'Snow Framework';
$page['current_year'] = date('Y');
$page['current_user'] = getCurrentUser();
$page['navigation']   = getNavigationMenu();

$action   = $_GET['action'] ?? 'list';
$recordId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$message  = '';
$error    = '';

// Flash messages
if (isset($_GET['msg'])) {
    $msgs = [
        'created' => 'Record created.',
        'updated' => 'Record updated.',
        'deleted' => 'Record deleted.',
    ];
    $message = $msgs[$_GET['msg']] ?? '';
}

// ── Handle POST ───────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? $action;

    if ($postAction === 'add') {
        // Only honour ACL group selections submitted by users with table_management
        if (hasPermission('table_management')) {
            $viewGroupIds = array_filter(array_map('intval', (array)($_POST['view_groups'] ?? [])));
            $editGroupIds = array_filter(array_map('intval', (array)($_POST['edit_groups'] ?? [])));
        } else {
            $viewGroupIds = null;  // sentinel: do not write these columns
            $editGroupIds = null;
        }

        $rowData  = [];
        $hasError = false;
        foreach ($fields as $f) {
            $val = trim($_POST[$f['field_name']] ?? '');
            if ($f['is_required'] && $val === '') {
                $error    = htmlspecialchars($f['display_label']) . ' is required.';
                $hasError = true;
                break;
            }
            $rowData[$f['field_name']] = ($val !== '') ? $val : null;
        }
        if (!$hasError) {
            if ($viewGroupIds !== null) {
                $rowData['view_groups'] = !empty($viewGroupIds) ? implode(',', $viewGroupIds) : null;
            }
            if ($editGroupIds !== null) {
                $rowData['edit_groups'] = !empty($editGroupIds) ? implode(',', $editGroupIds) : null;
            }
            // Standard field: status
            if (isset($_POST['status'])) {
                $rowData['status'] = in_array($_POST['status'], ['active', 'inactive']) ? $_POST['status'] : 'active';
            }
            dbInsert($tableName, $rowData);
            header('Location: /' . $page['path'] . '?msg=created');
            exit;
        }
        $action = 'add';

    } elseif ($postAction === 'edit' && $recordId) {
        // Row-level edit permission check
        $existingForAcl = dbGetRow("SELECT * FROM `{$tableName}` WHERE id = ?", [$recordId]);
        if (!$existingForAcl || !canEditRow($existingForAcl)) {
            http_response_code(403);
            $error = 'You do not have permission to edit this record.';
            $hasError = true;
        }

        // Only honour ACL group selections submitted by users with table_management
        if (hasPermission('table_management')) {
            $viewGroupIds = array_filter(array_map('intval', (array)($_POST['view_groups'] ?? [])));
            $editGroupIds = array_filter(array_map('intval', (array)($_POST['edit_groups'] ?? [])));
        } else {
            $viewGroupIds = null;  // sentinel: do not write these columns
            $editGroupIds = null;
        }

        $rowData  = [];
        if (!isset($hasError)) { $hasError = false; }
        foreach ($fields as $f) {
            if ($hasError) break;
            $val = trim($_POST[$f['field_name']] ?? '');
            if ($f['is_required'] && $val === '') {
                $error    = htmlspecialchars($f['display_label']) . ' is required.';
                $hasError = true;
                break;
            }
            $rowData[$f['field_name']] = ($val !== '') ? $val : null;
        }
        if (!$hasError) {
            if ($viewGroupIds !== null) {
                $rowData['view_groups'] = !empty($viewGroupIds) ? implode(',', $viewGroupIds) : null;
            }
            if ($editGroupIds !== null) {
                $rowData['edit_groups'] = !empty($editGroupIds) ? implode(',', $editGroupIds) : null;
            }
            // Standard field: status
            if (isset($_POST['status'])) {
                $rowData['status'] = in_array($_POST['status'], ['active', 'inactive']) ? $_POST['status'] : 'active';
            }
            dbUpdate($tableName, $rowData, 'id = ?', [$recordId]);
            header('Location: /' . $page['path'] . '?msg=updated');
            exit;
        }
        $action = 'edit';

    } elseif ($postAction === 'delete' && $recordId) {
        dbQuery("DELETE FROM `{$tableName}` WHERE id = ?", [$recordId]);
        header('Location: /' . $page['path'] . '?msg=deleted');
        exit;
    }
}

// ── Field input renderer ──────────────────────────────────────────────────────

function renderCustomFieldInput(array $f, string $value = ''): string {
    $name = htmlspecialchars($f['field_name']);
    $val  = htmlspecialchars($value);
    $req  = $f['is_required'] ? 'required' : '';
    if (in_array($f['field_type'], ['text', 'longtext'])) {
        return "<textarea name=\"{$name}\" class=\"form-control\" rows=\"3\" {$req}>{$val}</textarea>";
    }
    $typeMap = [
        'date'     => 'date',
        'datetime' => 'datetime-local',
        'int'      => 'number',
        'tinyint'  => 'number',
        'decimal'  => 'number',
    ];
    $inputType = $typeMap[$f['field_type']] ?? 'text';
    return "<input type=\"{$inputType}\" name=\"{$name}\" class=\"form-control\" value=\"{$val}\" {$req}>";
}

// ── Build content ─────────────────────────────────────────────────────────────

ob_start();

if ($action === 'add') {
    $page['title'] = 'Add ' . $displayName;
    $page['breadcrumbs'] = [
        ['title' => 'Home',       'url' => '/'],
        ['title' => 'Admin',      'url' => '/admin'],
        ['title' => $displayName, 'url' => '/' . $page['path']],
        ['title' => 'Add',        'url' => '', 'current' => true],
    ];
    // Load all active groups for the ACL selector widget
    $allGroups = dbGetRows("SELECT id, name FROM user_groups_list WHERE status = 'active' ORDER BY name", []);
    $currentRecord = [];
    $currentViewGroups = array_filter(array_map('intval',
        (array)($_POST['view_groups'] ?? [])));
    $currentEditGroups = array_filter(array_map('intval',
        (array)($_POST['edit_groups'] ?? [])));
    ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <a href="/<?= htmlspecialchars($page['path']) ?>" class="btn btn-secondary btn-sm mb-3">&larr; Back to <?= htmlspecialchars($displayName) ?></a>
    <form method="post" action="/<?= htmlspecialchars($page['path']) ?>?action=add">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="row g-3">
            <?php foreach ($fields as $f): ?>
            <div class="col-md-6">
                <label class="form-label">
                    <?= htmlspecialchars($f['display_label']) ?>
                    <?= $f['is_required'] ? '<span class="text-danger">*</span>' : '' ?>
                </label>
                <?= renderCustomFieldInput($f, $_POST[$f['field_name']] ?? $f['default_value'] ?? '') ?>
            </div>
            <?php endforeach; ?>
            <?php if (!$fields): ?>
            <div class="col-12">
                <p class="text-muted">No fields defined yet. <a href="/admin/tables?action=fields&id=<?= (int)$tableDef['id'] ?>">Add fields</a> to this table first.</p>
            </div>
            <?php endif; ?>
            <!-- Status field -->
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active"   <?= (($_POST['status'] ?? 'active') === 'active')   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= (($_POST['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <?php if (hasPermission('table_management')): ?>
            <!-- View Groups -->
            <div class="col-12">
                <label class="form-label fw-semibold">View Groups <small class="text-muted fw-normal">(leave unchecked for all users)</small></label>
                <?php if ($allGroups): ?>
                <div class="row g-2">
                    <?php foreach ($allGroups as $g): ?>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="checkbox" name="view_groups[]" class="form-check-input"
                                id="vg_<?= (int)$g['id'] ?>" value="<?= (int)$g['id'] ?>"
                                <?= in_array((int)$g['id'], $currentViewGroups) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="vg_<?= (int)$g['id'] ?>">
                                <?= htmlspecialchars($g['name']) ?>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted small">No groups defined. <a href="/admin/groups">Add groups</a> first.</p>
                <?php endif; ?>
            </div>
            <!-- Edit Groups -->
            <div class="col-12">
                <label class="form-label fw-semibold">Edit Groups <small class="text-muted fw-normal">(leave unchecked for all users who can view)</small></label>
                <?php if ($allGroups): ?>
                <div class="row g-2">
                    <?php foreach ($allGroups as $g): ?>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="checkbox" name="edit_groups[]" class="form-check-input"
                                id="eg_<?= (int)$g['id'] ?>" value="<?= (int)$g['id'] ?>"
                                <?= in_array((int)$g['id'], $currentEditGroups) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="eg_<?= (int)$g['id'] ?>">
                                <?= htmlspecialchars($g['name']) ?>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted small">No groups defined.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-success">Create</button>
            <a href="/<?= htmlspecialchars($page['path']) ?>" class="btn btn-secondary ms-2">Cancel</a>
        </div>
    </form>
    <?php

} elseif ($action === 'edit' && $recordId) {
    $record = dbGetRow("SELECT * FROM `{$tableName}` WHERE id = ?", [$recordId]);
    if (!$record) {
        echo '<div class="alert alert-danger">Record not found.</div>';
    } elseif (!canViewRow($record)) {
        // Row-level view check
        http_response_code(403);
        echo '<div class="alert alert-danger">You do not have permission to view this record.</div>';
    } else {
        $canEdit = canEditRow($record);
        $page['title'] = 'Edit ' . $displayName;
        $page['breadcrumbs'] = [
            ['title' => 'Home',             'url' => '/'],
            ['title' => 'Admin',            'url' => '/admin'],
            ['title' => $displayName,       'url' => '/' . $page['path']],
            ['title' => 'Edit #' . $recordId, 'url' => '', 'current' => true],
        ];
        // Load all active groups for the ACL selector widget
        $allGroups = dbGetRows("SELECT id, name FROM user_groups_list WHERE status = 'active' ORDER BY name", []);
        $currentRecord = $record;
        // On POST repopulation: use submitted array. On fresh GET: parse DB comma-separated string.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $currentViewGroups = array_filter(array_map('intval',
                (array)($_POST['view_groups'] ?? [])));
            $currentEditGroups = array_filter(array_map('intval',
                (array)($_POST['edit_groups'] ?? [])));
        } else {
            $currentViewGroups = array_filter(array_map('intval',
                explode(',', $currentRecord['view_groups'] ?? '')));
            $currentEditGroups = array_filter(array_map('intval',
                explode(',', $currentRecord['edit_groups'] ?? '')));
        }
        ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        <a href="/<?= htmlspecialchars($page['path']) ?>" class="btn btn-secondary btn-sm mb-3">&larr; Back to <?= htmlspecialchars($displayName) ?></a>
        <form method="post" action="/<?= htmlspecialchars($page['path']) ?>?action=edit&id=<?= $recordId ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <?php if (!$canEdit): ?><fieldset disabled><?php endif; ?>
            <div class="row g-3">
                <?php foreach ($fields as $f): ?>
                <div class="col-md-6">
                    <label class="form-label">
                        <?= htmlspecialchars($f['display_label']) ?>
                        <?= $f['is_required'] ? '<span class="text-danger">*</span>' : '' ?>
                    </label>
                    <?= renderCustomFieldInput($f, $_POST[$f['field_name']] ?? $record[$f['field_name']] ?? '') ?>
                </div>
                <?php endforeach; ?>
                <!-- Status field -->
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active"   <?= (($_POST['status'] ?? $currentRecord['status'] ?? 'active') === 'active')   ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($_POST['status'] ?? $currentRecord['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <?php if (hasPermission('table_management')): ?>
                <!-- View Groups -->
                <div class="col-12">
                    <label class="form-label fw-semibold">View Groups <small class="text-muted fw-normal">(leave unchecked for all users)</small></label>
                    <?php if ($allGroups): ?>
                    <div class="row g-2">
                        <?php foreach ($allGroups as $g): ?>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" name="view_groups[]" class="form-check-input"
                                    id="vg_<?= (int)$g['id'] ?>" value="<?= (int)$g['id'] ?>"
                                    <?= in_array((int)$g['id'], $currentViewGroups) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="vg_<?= (int)$g['id'] ?>">
                                    <?= htmlspecialchars($g['name']) ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted small">No groups defined. <a href="/admin/groups">Add groups</a> first.</p>
                    <?php endif; ?>
                </div>
                <!-- Edit Groups -->
                <div class="col-12">
                    <label class="form-label fw-semibold">Edit Groups <small class="text-muted fw-normal">(leave unchecked for all users who can view)</small></label>
                    <?php if ($allGroups): ?>
                    <div class="row g-2">
                        <?php foreach ($allGroups as $g): ?>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" name="edit_groups[]" class="form-check-input"
                                    id="eg_<?= (int)$g['id'] ?>" value="<?= (int)$g['id'] ?>"
                                    <?= in_array((int)$g['id'], $currentEditGroups) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="eg_<?= (int)$g['id'] ?>">
                                    <?= htmlspecialchars($g['name']) ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted small">No groups defined.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!$canEdit): ?>
            </fieldset>
            <div class="alert alert-warning mt-3">You have view-only access to this record.</div>
            <?php else: ?>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/<?= htmlspecialchars($page['path']) ?>" class="btn btn-secondary ms-2">Cancel</a>
            </div>
            <?php endif; ?>
        </form>
        <?php if ($canEdit): ?>
        <form method="post" action="/<?= htmlspecialchars($page['path']) ?>?action=edit&id=<?= $recordId ?>"
              class="mt-2" onsubmit="return confirm('Delete this record?')">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-danger btn-sm">Delete Record</button>
        </form>
        <?php endif; ?>
        <?php
    }

} else {
    // List view
    $page['title'] = $displayName;
    $page['breadcrumbs'] = [
        ['title' => 'Home',       'url' => '/'],
        ['title' => 'Admin',      'url' => '/admin'],
        ['title' => $displayName, 'url' => '', 'current' => true],
    ];

    // Fetch all rows and apply ACL filter
    $allRows      = dbGetRows("SELECT * FROM `{$tableName}` ORDER BY id DESC", []);
    $rows         = filterRowsByViewAccess($allRows);
    $visibleCount = count($rows);
    ?>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span><?= (int)$visibleCount ?> record<?= $visibleCount !== 1 ? 's' : '' ?></span>
        <a href="/<?= htmlspecialchars($page['path']) ?>?action=add" class="btn btn-success btn-sm">+ Add <?= htmlspecialchars($displayName) ?></a>
    </div>
    <?php if ($rows): ?>
    <div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <?php foreach ($fields as $f): ?>
                    <?php if ($f['is_visible']): ?><th><?= htmlspecialchars($f['display_label']) ?></th><?php endif; ?>
                <?php endforeach; ?>
                <th>View Groups</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= (int)$row['id'] ?></td>
                <?php foreach ($fields as $f): ?>
                    <?php if ($f['is_visible']): ?>
                    <td><?= htmlspecialchars((string)($row[$f['field_name']] ?? '')) ?></td>
                    <?php endif; ?>
                <?php endforeach; ?>
                <td><?= formatGroupIds($row['view_groups'] ?? null) ?></td>
                <td><?= htmlspecialchars($row['status'] ?? 'active') ?></td>
                <td><a href="/<?= htmlspecialchars($page['path']) ?>?action=edit&id=<?= (int)$row['id'] ?>" class="btn btn-primary btn-sm">Edit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
    <div class="alert alert-info">No records found<?= $allRows ? ' that you have permission to view' : '' ?>.</div>
    <?php endif; ?>
    <?php
}

$page['content'] = ob_get_clean();
?>
