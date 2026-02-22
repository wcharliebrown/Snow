<?php
/**
 * Admin - Custom Table Management
 */

requirePermission('table_management');

$page['site_name']    = getenv('SITE_NAME') ?: 'Snow Framework';
$page['current_year'] = date('Y');
$page['current_user'] = getCurrentUser();
$page['navigation']   = getNavigationMenu();

$action  = $_GET['action'] ?? 'list';
$tableId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$message = '';
$error   = '';

function getCustomTableById(int $id): array|false {
    return dbGetRow("SELECT * FROM custom_tables WHERE id = ?", [$id]);
}

function getCustomTableFields(string $tableName): array {
    return dbGetRows(
        "SELECT * FROM custom_table_fields WHERE table_name = ? AND status = 'active' ORDER BY display_order, field_name",
        [$tableName]
    );
}

// ── Handle POST ───────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? $action;

    if ($postAction === 'add') {
        $tableName   = trim(strtolower(preg_replace('/[^a-z0-9_]/i', '_', $_POST['table_name'] ?? '')));
        $displayName = trim($_POST['display_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status      = $_POST['status'] ?? 'active';
        $currentUser = getCurrentUser();

        if (!$tableName) {
            $error = 'Table name is required.';
        } elseif (!$displayName) {
            $error = 'Display name is required.';
        } elseif (!preg_match('/^[a-z][a-z0-9_]*$/', $tableName)) {
            $error = 'Table name must start with a letter and contain only letters, digits, and underscores.';
        } else {
            $existing = dbGetRow("SELECT id FROM custom_tables WHERE table_name = ?", [$tableName]);
            if ($existing) {
                $error = 'A custom table with that name already exists.';
            } else {
                dbInsert('custom_tables', [
                    'table_name'   => $tableName,
                    'display_name' => $displayName,
                    'description'  => $description,
                    'status'       => $status,
                    'created_by'   => $currentUser['id'] ?? null,
                ]);
                header('Location: /admin/tables?msg=created');
                exit;
            }
        }
        $action = 'add';

    } elseif ($postAction === 'edit' && $tableId) {
        $displayName = trim($_POST['display_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status      = $_POST['status'] ?? 'active';

        if (!$displayName) {
            $error = 'Display name is required.';
        } else {
            dbUpdate('custom_tables', [
                'display_name' => $displayName,
                'description'  => $description,
                'status'       => $status,
            ], 'id = ?', [$tableId]);
            header('Location: /admin/tables?msg=updated');
            exit;
        }
        $action = 'edit';

    } elseif ($postAction === 'delete' && $tableId) {
        $ct = getCustomTableById($tableId);
        if ($ct) {
            dbQuery("DELETE FROM custom_table_fields WHERE table_name = ?", [$ct['table_name']]);
            dbQuery("DELETE FROM custom_tables WHERE id = ?", [$tableId]);
        }
        header('Location: /admin/tables?msg=deleted');
        exit;

    } elseif ($postAction === 'add_field' && $tableId) {
        $ct        = getCustomTableById($tableId);
        $fieldName = trim(strtolower(preg_replace('/[^a-z0-9_]/i', '_', $_POST['field_name'] ?? '')));
        $fieldType = trim($_POST['field_type'] ?? '');
        $label     = trim($_POST['display_label'] ?? '');
        $order     = (int)($_POST['display_order'] ?? 0);
        $isVisible  = isset($_POST['is_visible'])  ? 1 : 0;
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $isUnique   = isset($_POST['is_unique'])   ? 1 : 0;
        $defaultVal = trim($_POST['default_value'] ?? '');

        if (!$ct) {
            $error = 'Table not found.';
        } elseif (!$fieldName || !$fieldType || !$label) {
            $error = 'Field name, type, and label are required.';
        } else {
            $dupField = dbGetRow(
                "SELECT id FROM custom_table_fields WHERE table_name = ? AND field_name = ? AND status = 'active'",
                [$ct['table_name'], $fieldName]
            );
            if ($dupField) {
                $error = 'A field with that name already exists in this table.';
            } else {
                dbInsert('custom_table_fields', [
                    'table_name'    => $ct['table_name'],
                    'field_name'    => $fieldName,
                    'field_type'    => $fieldType,
                    'display_label' => $label,
                    'display_order' => $order,
                    'is_visible'    => $isVisible,
                    'is_required'   => $isRequired,
                    'is_unique'     => $isUnique,
                    'default_value' => $defaultVal,
                    'status'        => 'active',
                ]);
                header("Location: /admin/tables?action=fields&id=$tableId&msg=field_added");
                exit;
            }
        }
        $action = 'fields';

    } elseif ($postAction === 'delete_field') {
        $fieldId = isset($_POST['field_id']) ? (int)$_POST['field_id'] : 0;
        if ($fieldId) {
            dbUpdate('custom_table_fields', ['status' => 'inactive'], 'id = ?', [$fieldId]);
        }
        header("Location: /admin/tables?action=fields&id=$tableId&msg=field_deleted");
        exit;
    }
}

// ── Flash messages ────────────────────────────────────────────────────────────

if (isset($_GET['msg'])) {
    $msgs    = [
        'created'      => 'Table definition created.',
        'updated'      => 'Table definition updated.',
        'deleted'      => 'Table definition deleted.',
        'field_added'  => 'Field added.',
        'field_deleted'=> 'Field removed.',
    ];
    $message = $msgs[$_GET['msg']] ?? '';
}

// ── Build content ─────────────────────────────────────────────────────────────

ob_start();

if ($action === 'add') {
    $page['title'] = 'Add Custom Table';
    $page['breadcrumbs'] = [
        ['title' => 'Home',         'url' => '/'],
        ['title' => 'Admin',        'url' => '/admin'],
        ['title' => 'Custom Tables','url' => '/admin/tables'],
        ['title' => 'Add Table',    'url' => '', 'current' => true],
    ];
    ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <a href="/admin/tables" class="btn btn-secondary btn-sm mb-3">&larr; Back to Custom Tables</a>
    <form method="post" action="/admin/tables?action=add">
        <input type="hidden" name="action" value="add">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Table Name <span class="text-danger">*</span></label>
                <input type="text" name="table_name" class="form-control" value="<?= htmlspecialchars($_POST['table_name'] ?? '') ?>" placeholder="e.g. my_data" required>
                <div class="form-text">Lowercase letters, digits, underscores only.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Display Name <span class="text-danger">*</span></label>
                <input type="text" name="display_name" class="form-control" value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-9">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active"   <?= (($_POST['status'] ?? 'active') === 'active')   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= (($_POST['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-success">Create Table Definition</button>
            <a href="/admin/tables" class="btn btn-secondary ms-2">Cancel</a>
        </div>
    </form>
    <?php

} elseif ($action === 'edit' && $tableId) {
    $editTable = getCustomTableById($tableId);
    if (!$editTable) {
        echo '<div class="alert alert-danger">Table definition not found.</div>';
    } else {
        $page['title'] = 'Edit Table Definition';
        $page['breadcrumbs'] = [
            ['title' => 'Home',          'url' => '/'],
            ['title' => 'Admin',         'url' => '/admin'],
            ['title' => 'Custom Tables', 'url' => '/admin/tables'],
            ['title' => 'Edit Table',    'url' => '', 'current' => true],
        ];
        ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <a href="/admin/tables" class="btn btn-secondary btn-sm mb-3">&larr; Back to Custom Tables</a>
        <form method="post" action="/admin/tables?action=edit&id=<?= $tableId ?>">
            <input type="hidden" name="action" value="edit">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Table Name</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($editTable['table_name']) ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Display Name <span class="text-danger">*</span></label>
                    <input type="text" name="display_name" class="form-control"
                        value="<?= htmlspecialchars($_POST['display_name'] ?? $editTable['display_name']) ?>" required>
                </div>
                <div class="col-md-9">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($_POST['description'] ?? $editTable['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active"   <?= (($_POST['status'] ?? $editTable['status']) === 'active')   ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($_POST['status'] ?? $editTable['status']) === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/admin/tables" class="btn btn-secondary ms-2">Cancel</a>
                <a href="/admin/tables?action=fields&id=<?= $tableId ?>" class="btn btn-info ms-2">Manage Fields</a>
            </div>
        </form>
        <?php
    }

} elseif ($action === 'fields' && $tableId) {
    $ct = getCustomTableById($tableId);
    if (!$ct) {
        echo '<div class="alert alert-danger">Table definition not found.</div>';
    } else {
        $page['title'] = 'Fields: ' . htmlspecialchars($ct['display_name']);
        $page['breadcrumbs'] = [
            ['title' => 'Home',          'url' => '/'],
            ['title' => 'Admin',         'url' => '/admin'],
            ['title' => 'Custom Tables', 'url' => '/admin/tables'],
            ['title' => htmlspecialchars($ct['display_name']), 'url' => '', 'current' => true],
        ];
        $fields    = getCustomTableFields($ct['table_name']);
        $fieldTypes = ['varchar','text','int','decimal','date','datetime','tinyint','longtext'];
        ?>
        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <a href="/admin/tables" class="btn btn-secondary btn-sm mb-3">&larr; Back to Custom Tables</a>

        <!-- Existing fields -->
        <?php if ($fields): ?>
        <table class="table table-sm table-striped mb-4">
            <thead class="table-dark">
                <tr><th>Field</th><th>Type</th><th>Label</th><th>Order</th><th>Vis</th><th>Req</th><th>Uniq</th><th>Default</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($fields as $f): ?>
                <tr>
                    <td><code><?= htmlspecialchars($f['field_name']) ?></code></td>
                    <td><?= htmlspecialchars($f['field_type']) ?></td>
                    <td><?= htmlspecialchars($f['display_label']) ?></td>
                    <td><?= (int)$f['display_order'] ?></td>
                    <td><?= $f['is_visible']  ? '✓' : '' ?></td>
                    <td><?= $f['is_required'] ? '✓' : '' ?></td>
                    <td><?= $f['is_unique']   ? '✓' : '' ?></td>
                    <td><?= htmlspecialchars($f['default_value'] ?? '') ?></td>
                    <td>
                        <form method="post" action="/admin/tables?action=fields&id=<?= $tableId ?>" class="d-inline"
                              onsubmit="return confirm('Remove field &quot;<?= htmlspecialchars(addslashes($f['field_name'])) ?>&quot;?')">
                            <input type="hidden" name="action"   value="delete_field">
                            <input type="hidden" name="field_id" value="<?= (int)$f['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted">No fields defined yet.</p>
        <?php endif; ?>

        <!-- Add field form -->
        <div class="card">
            <div class="card-header py-2"><strong>Add Field</strong></div>
            <div class="card-body">
                <form method="post" action="/admin/tables?action=fields&id=<?= $tableId ?>">
                    <input type="hidden" name="action" value="add_field">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Field Name <span class="text-danger">*</span></label>
                            <input type="text" name="field_name" class="form-control form-control-sm" placeholder="e.g. first_name" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm">Type <span class="text-danger">*</span></label>
                            <select name="field_type" class="form-select form-select-sm" required>
                                <?php foreach ($fieldTypes as $ft): ?>
                                <option value="<?= $ft ?>"><?= $ft ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Display Label <span class="text-danger">*</span></label>
                            <input type="text" name="display_label" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label form-label-sm">Order</label>
                            <input type="number" name="display_order" class="form-control form-control-sm" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Default Value</label>
                            <input type="text" name="default_value" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 d-flex gap-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_visible"  class="form-check-input" id="fv" value="1" checked>
                                <label class="form-check-label" for="fv">Visible</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="is_required" class="form-check-input" id="fr" value="1">
                                <label class="form-check-label" for="fr">Required</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="is_unique"   class="form-check-input" id="fu" value="1">
                                <label class="form-check-label" for="fu">Unique</label>
                            </div>
                            <button type="submit" class="btn btn-success btn-sm ms-auto">Add Field</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

} else {
    // List view
    $page['title'] = 'Custom Tables';
    $page['breadcrumbs'] = [
        ['title' => 'Home',          'url' => '/'],
        ['title' => 'Admin',         'url' => '/admin'],
        ['title' => 'Custom Tables', 'url' => '', 'current' => true],
    ];
    $customTables = dbGetRows(
        "SELECT ct.*, COUNT(ctf.id) AS field_count
         FROM custom_tables ct
         LEFT JOIN custom_table_fields ctf ON ct.table_name = ctf.table_name AND ctf.status = 'active'
         GROUP BY ct.id
         ORDER BY ct.display_name",
        []
    );
    ?>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span><?= count($customTables) ?> table<?= count($customTables) !== 1 ? 's' : '' ?></span>
        <a href="/admin/tables?action=add" class="btn btn-success btn-sm">+ Add Table</a>
    </div>
    <?php if (empty($customTables)): ?>
    <div class="alert alert-info">No custom table definitions yet.</div>
    <?php else: ?>
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr><th>Table Name</th><th>Display Name</th><th>Description</th><th>Fields</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($customTables as $ct): ?>
            <tr>
                <td><code><?= htmlspecialchars($ct['table_name']) ?></code></td>
                <td><?= htmlspecialchars($ct['display_name']) ?></td>
                <td><?= htmlspecialchars($ct['description'] ?? '') ?></td>
                <td><span class="badge bg-info text-dark"><?= (int)$ct['field_count'] ?></span></td>
                <td>
                    <span class="badge bg-<?= $ct['status'] === 'active' ? 'success' : 'secondary' ?>">
                        <?= htmlspecialchars($ct['status']) ?>
                    </span>
                </td>
                <td>
                    <a href="/admin/tables?action=fields&id=<?= (int)$ct['id'] ?>" class="btn btn-info btn-sm">Fields</a>
                    <a href="/admin/tables?action=edit&id=<?= (int)$ct['id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                    <form method="post" action="/admin/tables?action=delete&id=<?= (int)$ct['id'] ?>" class="d-inline"
                          onsubmit="return confirm('Delete table definition &quot;<?= htmlspecialchars(addslashes($ct['display_name'])) ?>&quot;?')">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php
}

$page['content'] = ob_get_clean();
?>
