<?php
/**
 * Admin - Snapshot Management
 */

requirePermission('snapshot_management');

$page['site_name']    = getenv('SITE_NAME') ?: 'Snow Framework';
$page['current_year'] = date('Y');
$page['current_user'] = getCurrentUser();
$page['navigation']   = getNavigationMenu();
$page['title']        = 'Snapshots';
$page['breadcrumbs']  = [
    ['title' => 'Home',      'url' => '/'],
    ['title' => 'Admin',     'url' => '/admin'],
    ['title' => 'Snapshots', 'url' => '', 'current' => true],
];

$message = '';
$error   = '';

// ── Handle POST ───────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction  = $_POST['action'] ?? '';
    $snapshotId  = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($postAction === 'create') {
        $tableName      = trim($_POST['table_name'] ?? '');
        $snapshotName   = trim($_POST['snapshot_name'] ?? '') ?: $tableName . '_' . date('Ymd_His');
        $description    = trim($_POST['description'] ?? '');
        $currentUser    = getCurrentUser();

        if (!$tableName) {
            $error = 'Table name is required.';
        } elseif (!dbTableExists($tableName)) {
            $error = "Table '$tableName' does not exist.";
        } else {
            $rowCount = dbGetRow("SELECT COUNT(*) AS n FROM `$tableName`", [])['n'] ?? 0;
            dbInsert('snapshots', [
                'table_name'    => $tableName,
                'snapshot_name' => $snapshotName,
                'description'   => $description,
                'snapshot_date' => date('Y-m-d H:i:s'),
                'row_count'     => (int)$rowCount,
                'file_path'     => null,
                'status'        => 'active',
                'created_by'    => $currentUser['id'] ?? null,
            ]);
            header('Location: /admin/snapshots?msg=created');
            exit;
        }

    } elseif ($postAction === 'delete' && $snapshotId) {
        dbUpdate('snapshots', ['status' => 'deleted'], 'id = ?', [$snapshotId]);
        header('Location: /admin/snapshots?msg=deleted');
        exit;
    }
}

// ── Flash messages ────────────────────────────────────────────────────────────

if (isset($_GET['msg'])) {
    $msgs    = ['created' => 'Snapshot recorded.', 'deleted' => 'Snapshot deleted.'];
    $message = $msgs[$_GET['msg']] ?? '';
}

// ── Data ──────────────────────────────────────────────────────────────────────

$snapshots = dbGetRows(
    "SELECT s.*, u.first_name, u.last_name
     FROM snapshots s
     LEFT JOIN users u ON s.created_by = u.id
     WHERE s.status = 'active'
     ORDER BY s.snapshot_date DESC",
    []
);

// Tables list for the create form
$tables = dbGetRows("SHOW TABLES", []);
$tableNames = array_map(fn($r) => reset($r), $tables);

// ── Build content ─────────────────────────────────────────────────────────────

ob_start();
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Create snapshot form -->
<div class="card mb-4">
    <div class="card-header py-2"><strong>Record Snapshot</strong></div>
    <div class="card-body">
        <form method="post" action="/admin/snapshots" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="create">
            <div class="col-md-3">
                <label class="form-label form-label-sm">Table</label>
                <select name="table_name" class="form-select form-select-sm" required>
                    <option value="">— select —</option>
                    <?php foreach ($tableNames as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm">Snapshot Name</label>
                <input type="text" name="snapshot_name" class="form-control form-control-sm" placeholder="Auto-generated if blank">
            </div>
            <div class="col-md-4">
                <label class="form-label form-label-sm">Description</label>
                <input type="text" name="description" class="form-control form-control-sm" placeholder="Optional note">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success btn-sm w-100">Record</button>
            </div>
        </form>
    </div>
</div>

<!-- Snapshot list -->
<div class="d-flex justify-content-between align-items-center mb-2">
    <span><?= count($snapshots) ?> snapshot<?= count($snapshots) !== 1 ? 's' : '' ?></span>
</div>
<?php if (empty($snapshots)): ?>
<div class="alert alert-info">No snapshots recorded yet.</div>
<?php else: ?>
<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr><th>Table</th><th>Snapshot Name</th><th>Description</th><th>Date</th><th>Rows</th><th>By</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($snapshots as $s): ?>
        <tr>
            <td><code><?= htmlspecialchars($s['table_name']) ?></code></td>
            <td><?= htmlspecialchars($s['snapshot_name']) ?></td>
            <td><?= htmlspecialchars($s['description'] ?? '') ?></td>
            <td><?= htmlspecialchars($s['snapshot_date']) ?></td>
            <td><span class="badge bg-secondary"><?= number_format((int)$s['row_count']) ?></span></td>
            <td><?= $s['first_name'] ? htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) : '<span class="text-muted">—</span>' ?></td>
            <td>
                <form method="post" action="/admin/snapshots" class="d-inline"
                      onsubmit="return confirm('Delete snapshot &quot;<?= htmlspecialchars(addslashes($s['snapshot_name'])) ?>&quot;?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id"     value="<?= (int)$s['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php
$page['content'] = ob_get_clean();
?>
