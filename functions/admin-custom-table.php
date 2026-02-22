<?php
/**
 * Generic admin handler for custom tables.
 * Derives the table name from the last segment of $page['path'].
 * e.g. path "admin/data/my_table" → table "my_table"
 */

requirePermission('table_management');

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
            dbInsert($tableName, $rowData);
            header('Location: /' . $page['path'] . '?msg=created');
            exit;
        }
        $action = 'add';

    } elseif ($postAction === 'edit' && $recordId) {
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
    ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <a href="/<?= htmlspecialchars($page['path']) ?>" class="btn btn-secondary btn-sm mb-3">&larr; Back to <?= htmlspecialchars($displayName) ?></a>
    <form method="post" action="/<?= htmlspecialchars($page['path']) ?>?action=add">
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
    } else {
        $page['title'] = 'Edit ' . $displayName;
        $page['breadcrumbs'] = [
            ['title' => 'Home',             'url' => '/'],
            ['title' => 'Admin',            'url' => '/admin'],
            ['title' => $displayName,       'url' => '/' . $page['path']],
            ['title' => 'Edit #' . $recordId, 'url' => '', 'current' => true],
        ];
        ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        <a href="/<?= htmlspecialchars($page['path']) ?>" class="btn btn-secondary btn-sm mb-3">&larr; Back to <?= htmlspecialchars($displayName) ?></a>
        <form method="post" action="/<?= htmlspecialchars($page['path']) ?>?action=edit&id=<?= $recordId ?>">
            <input type="hidden" name="action" value="edit">
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
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/<?= htmlspecialchars($page['path']) ?>" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
        <form method="post" action="/<?= htmlspecialchars($page['path']) ?>?action=edit&id=<?= $recordId ?>"
              class="mt-2" onsubmit="return confirm('Delete this record?')">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-danger btn-sm">Delete Record</button>
        </form>
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
    $totalCount = dbGetRow("SELECT COUNT(*) AS n FROM `{$tableName}`")['n'] ?? 0;
    $listReport = getReportByName($tableName . '_list');
    ?>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span><?= (int)$totalCount ?> record<?= $totalCount !== 1 ? 's' : '' ?></span>
        <a href="/<?= htmlspecialchars($page['path']) ?>?action=add" class="btn btn-success btn-sm">+ Add <?= htmlspecialchars($displayName) ?></a>
    </div>
    <?php if ($listReport): ?>
        <?= renderReport($listReport) ?>
    <?php else: ?>
        <div class="alert alert-warning">Report <code><?= htmlspecialchars($tableName . '_list') ?></code> not found.</div>
    <?php endif; ?>
    <?php
}

$page['content'] = ob_get_clean();
?>
