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

$snapshotCount = dbGetRow("SELECT COUNT(*) AS n FROM snapshots WHERE status = 'active'", [])['n'] ?? 0;
$listReport    = getReportByName('snapshots_list');

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
    <span><?= (int)$snapshotCount ?> snapshot<?= $snapshotCount !== 1 ? 's' : '' ?></span>
</div>
<?php if ($listReport): ?>
    <?= renderReport($listReport) ?>
<?php else: ?>
    <div class="alert alert-warning">Report <code>snapshots_list</code> not found. <a href="/admin/reports">Recreate it in Reports</a>.</div>
<?php endif; ?>
<?php
$page['content'] = ob_get_clean();
?>
