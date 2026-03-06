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
            // Generate physical snapshot table name with random suffix to prevent collision (Pitfall 1)
            $snapshotTableName = 'snapshot_' . $tableName . '_' . date('YmdHis') . rand(100, 999);
            $snapshotId = dbInsert('snapshots', [
                'table_name'     => $tableName,
                'snapshot_name'  => $snapshotName,
                'snapshot_table' => $snapshotTableName,
                'description'    => $description,
                'snapshot_date'  => date('Y-m-d H:i:s'),
                'row_count'      => (int)$rowCount,
                'file_path'      => null,
                'status'         => 'active',
                'created_by'     => $currentUser['id'] ?? null,
            ]);
            // VER-03: Copy all rows into a new MySQL table (CREATE TABLE AS SELECT does not copy indexes or FKs — correct for snapshots)
            dbQuery("CREATE TABLE `{$snapshotTableName}` AS SELECT * FROM `{$tableName}`", []);
            header('Location: /admin/snapshots?msg=created');
            exit;
        }

    } elseif ($postAction === 'delete' && $snapshotId) {
        dbUpdate('snapshots', ['status' => 'deleted'], 'id = ?', [$snapshotId]);
        header('Location: /admin/snapshots?msg=deleted');
        exit;

    } elseif ($postAction === 'restore' && $snapshotId) {
        $restoreSnapshot = dbGetRow("SELECT * FROM snapshots WHERE id = ? AND status = 'active'", [$snapshotId]);

        if (!$restoreSnapshot) {
            $error = 'Snapshot not found or already used.';
        } else {
            $snapshotTable = $restoreSnapshot['snapshot_table'] ?? '';
            $liveTable     = $restoreSnapshot['table_name'];
            $currentUser   = getCurrentUser();

            if (!$snapshotTable || !dbTableExists($snapshotTable)) {
                $error = "Snapshot table no longer exists — cannot restore.";
            } elseif (!dbTableExists($liveTable)) {
                $error = "Live table '{$liveTable}' does not exist — cannot restore.";
            } else {
                // Step 1: Auto-snapshot the current live table (safety net — admin always has a way back)
                $liveRowCount      = (int)(dbGetRow("SELECT COUNT(*) AS n FROM `{$liveTable}`", [])['n'] ?? 0);
                $autoSnapshotTable = 'snapshot_' . $liveTable . '_' . date('YmdHis') . rand(100, 999);
                $tempTable         = $liveTable . '_prerestore_' . date('YmdHis');

                dbQuery("CREATE TABLE `{$autoSnapshotTable}` AS SELECT * FROM `{$liveTable}`", []);
                dbInsert('snapshots', [
                    'table_name'     => $liveTable,
                    'snapshot_table' => $autoSnapshotTable,
                    'snapshot_name'  => $autoSnapshotTable,
                    'description'    => 'Pre-restore auto-snapshot before restoring: ' . $restoreSnapshot['snapshot_name'],
                    'snapshot_date'  => date('Y-m-d H:i:s'),
                    'row_count'      => $liveRowCount,
                    'file_path'      => null,
                    'status'         => 'active',
                    'created_by'     => $currentUser['id'] ?? null,
                ]);

                // Step 2: Atomic rename — live → temp, snapshot → live
                // Single RENAME TABLE statement: both renames are atomic (MySQL guarantee)
                // DDL is auto-committed in MySQL; dbBeginTransaction wraps for rollback on exception only
                dbBeginTransaction();
                try {
                    dbQuery(
                        "RENAME TABLE `{$liveTable}` TO `{$tempTable}`,
                                      `{$snapshotTable}` TO `{$liveTable}`",
                        []
                    );
                    dbCommit();

                    // Record the temp (pre-restore) table as a snapshot so admin can see and manage it
                    $tempRowCount = (int)(dbGetRow("SELECT COUNT(*) AS n FROM `{$tempTable}`", [])['n'] ?? 0);
                    dbInsert('snapshots', [
                        'table_name'     => $liveTable,
                        'snapshot_table' => $tempTable,
                        'snapshot_name'  => $tempTable,
                        'description'    => 'Pre-restore live data (rename artifact) from restore of: ' . $restoreSnapshot['snapshot_name'],
                        'snapshot_date'  => date('Y-m-d H:i:s'),
                        'row_count'      => $tempRowCount,
                        'file_path'      => null,
                        'status'         => 'active',
                        'created_by'     => $currentUser['id'] ?? null,
                    ]);

                    // Step 3: Mark the used snapshot as 'restored' so it no longer appears as available
                    dbUpdate('snapshots', ['status' => 'restored'], 'id = ?', [$snapshotId]);

                    header('Location: /admin/snapshots?msg=restored');
                    exit;

                } catch (Exception $e) {
                    dbRollback();
                    $error = 'Restore failed: ' . $e->getMessage() .
                             ' The auto-safety-snapshot was created but the rename did not complete.';
                }
            }
        }
    }
}

// ── Diff view ────────────────────────────────────────────────────────────────

if (isset($_GET['action']) && $_GET['action'] === 'diff' && isset($_GET['id'])) {
    $diffSnapshotId  = (int)$_GET['id'];
    $diffSnapshot    = dbGetRow("SELECT * FROM snapshots WHERE id = ? AND status = 'active'", [$diffSnapshotId]);

    if (!$diffSnapshot) {
        $error = 'Snapshot not found.';
    } else {
        $snapshotTable = $diffSnapshot['snapshot_table'] ?? '';
        $liveTable     = $diffSnapshot['table_name'];

        if (!$snapshotTable || !dbTableExists($snapshotTable)) {
            $error = "Snapshot table " . htmlspecialchars($snapshotTable ?: '(unknown)') . " no longer exists in the database.";
        } else {
            // Guard: refuse to diff oversized tables (Pitfall 7)
            $snapCount = (int)(dbGetRow("SELECT COUNT(*) AS n FROM `{$snapshotTable}`", [])['n'] ?? 0);
            $liveCount = (int)(dbGetRow("SELECT COUNT(*) AS n FROM `{$liveTable}`", [])['n'] ?? 0);
            if ($snapCount > 10000 || $liveCount > 10000) {
                $error = "Table is too large to diff in-browser (limit: 10,000 rows). snapshot={$snapCount} rows, live={$liveCount} rows.";
            } else {
                $snapshotRows = dbGetRows("SELECT * FROM `{$snapshotTable}`", []);
                $liveRows     = dbGetRows("SELECT * FROM `{$liveTable}`", []);

                $snapById = array_column($snapshotRows, null, 'id');
                $liveById = array_column($liveRows,     null, 'id');

                $diffChanged = [];
                $diffDeleted = [];
                $diffAdded   = [];

                foreach ($snapById as $id => $snapRow) {
                    if (!isset($liveById[$id])) {
                        $diffDeleted[] = $snapRow;
                    } elseif ($snapRow !== $liveById[$id]) {
                        $diffChanged[] = ['snapshot' => $snapRow, 'live' => $liveById[$id]];
                    }
                }
                foreach ($liveById as $id => $liveRow) {
                    if (!isset($snapById[$id])) {
                        $diffAdded[] = $liveRow;
                    }
                }

                // Render diff page inline (before main content build)
                $page['title'] = 'Snapshot Diff — ' . htmlspecialchars($diffSnapshot['snapshot_name']);
                $page['breadcrumbs'][] = ['title' => 'Diff', 'url' => '', 'current' => true];

                ob_start();
                ?>
                <h5>Diff: <em><?= htmlspecialchars($diffSnapshot['snapshot_name']) ?></em> vs. live <em><?= htmlspecialchars($liveTable) ?></em></h5>
                <p class="text-muted small">Snapshot taken <?= htmlspecialchars($diffSnapshot['snapshot_date']) ?> &mdash; <?= count($diffChanged) ?> changed, <?= count($diffDeleted) ?> deleted, <?= count($diffAdded) ?> added</p>

                <?php if (empty($diffChanged) && empty($diffDeleted) && empty($diffAdded)): ?>
                    <div class="alert alert-success">No differences — live table matches snapshot exactly.</div>
                <?php else: ?>

                <?php if (!empty($diffChanged)): ?>
                <h6 class="mt-3">Changed Rows (<?= count($diffChanged) ?>)</h6>
                <div class="table-responsive">
                <table class="table table-bordered table-sm small">
                    <thead class="table-light">
                        <tr><th>Source</th><?php foreach (array_keys($diffChanged[0]['snapshot']) as $col): ?><th><?= htmlspecialchars($col) ?></th><?php endforeach; ?></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($diffChanged as $pair):
                        $snapVals = $pair['snapshot'];
                        $liveVals = $pair['live'];
                        $cols = array_keys($snapVals);
                    ?>
                        <tr class="table-warning">
                            <td class="fw-semibold text-nowrap">Snapshot</td>
                            <?php foreach ($cols as $col): ?>
                            <td<?= ($snapVals[$col] !== $liveVals[$col]) ? ' style="background-color:#fff3cd"' : '' ?>><?= htmlspecialchars((string)($snapVals[$col] ?? '')) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-nowrap">Live</td>
                            <?php foreach ($cols as $col): ?>
                            <td<?= ($snapVals[$col] !== $liveVals[$col]) ? ' style="background-color:#fff3cd"' : '' ?>><?= htmlspecialchars((string)($liveVals[$col] ?? '')) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>

                <?php if (!empty($diffDeleted)): ?>
                <h6 class="mt-3">Deleted Since Snapshot (<?= count($diffDeleted) ?>)</h6>
                <div class="table-responsive">
                <table class="table table-bordered table-sm small table-danger">
                    <thead class="table-light"><tr><?php foreach (array_keys($diffDeleted[0]) as $col): ?><th><?= htmlspecialchars($col) ?></th><?php endforeach; ?></tr></thead>
                    <tbody><?php foreach ($diffDeleted as $row): ?><tr><?php foreach ($row as $val): ?><td><?= htmlspecialchars((string)($val ?? '')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
                </table>
                </div>
                <?php endif; ?>

                <?php if (!empty($diffAdded)): ?>
                <h6 class="mt-3">Added Since Snapshot (<?= count($diffAdded) ?>)</h6>
                <div class="table-responsive">
                <table class="table table-bordered table-sm small table-success">
                    <thead class="table-light"><tr><?php foreach (array_keys($diffAdded[0]) as $col): ?><th><?= htmlspecialchars($col) ?></th><?php endforeach; ?></tr></thead>
                    <tbody><?php foreach ($diffAdded as $row): ?><tr><?php foreach ($row as $val): ?><td><?= htmlspecialchars((string)($val ?? '')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
                </table>
                </div>
                <?php endif; ?>

                <?php endif; // end if differences exist ?>

                <div class="mt-3">
                    <a href="/admin/snapshots" class="btn btn-secondary btn-sm">Back to Snapshots</a>
                    <a href="/admin/snapshots?action=restore&id=<?= $diffSnapshotId ?>" class="btn btn-warning btn-sm ms-2">Restore this Snapshot</a>
                </div>
                <?php
                $page['content'] = ob_get_clean();
                return;
            }
        }
    }
}

// ── Restore confirmation view ─────────────────────────────────────────────────

if (isset($_GET['action']) && $_GET['action'] === 'restore' && isset($_GET['id'])) {
    $restoreSnapshotId = (int)$_GET['id'];
    $restoreSnapshot   = dbGetRow("SELECT * FROM snapshots WHERE id = ? AND status = 'active'", [$restoreSnapshotId]);

    if (!$restoreSnapshot) {
        $error = 'Snapshot not found.';
    } else {
        $snapshotTable = $restoreSnapshot['snapshot_table'] ?? '';
        $liveTable     = $restoreSnapshot['table_name'];

        if (!$snapshotTable || !dbTableExists($snapshotTable)) {
            $error = "Snapshot table " . htmlspecialchars($snapshotTable ?: '(unknown)') . " no longer exists.";
        } else {
            // Schema drift check: compare column sets
            $snapCols = array_column(dbGetRows("SHOW COLUMNS FROM `{$snapshotTable}`", []), 'Field');
            $liveCols = array_column(dbGetRows("SHOW COLUMNS FROM `{$liveTable}`", []), 'Field');
            $onlyInSnap = array_diff($snapCols, $liveCols);
            $onlyInLive = array_diff($liveCols, $snapCols);
            $hasSchemaDrift = !empty($onlyInSnap) || !empty($onlyInLive);

            $page['title'] = 'Confirm Restore — ' . htmlspecialchars($restoreSnapshot['snapshot_name']);
            $page['breadcrumbs'][] = ['title' => 'Restore', 'url' => '', 'current' => true];

            ob_start();
            ?>
            <h5>Restore Snapshot: <em><?= htmlspecialchars($restoreSnapshot['snapshot_name']) ?></em></h5>

            <?php if ($hasSchemaDrift): ?>
            <div class="alert alert-warning">
                <strong>Column mismatch detected.</strong> The snapshot and live table have different columns.
                <?php if (!empty($onlyInSnap)): ?>
                <br>Columns only in snapshot (will be restored): <code><?= htmlspecialchars(implode(', ', $onlyInSnap)) ?></code>
                <?php endif; ?>
                <?php if (!empty($onlyInLive)): ?>
                <br>Columns only in live table (will be lost after restore): <code><?= htmlspecialchars(implode(', ', $onlyInLive)) ?></code>
                <?php endif; ?>
                <br><strong>Verify the restored table is correct after restore completes.</strong>
            </div>
            <?php endif; ?>

            <div class="card mb-3">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Table</dt><dd class="col-sm-9"><?= htmlspecialchars($liveTable) ?></dd>
                        <dt class="col-sm-3">Snapshot Name</dt><dd class="col-sm-9"><?= htmlspecialchars($restoreSnapshot['snapshot_name']) ?></dd>
                        <dt class="col-sm-3">Snapshot Date</dt><dd class="col-sm-9"><?= htmlspecialchars($restoreSnapshot['snapshot_date']) ?></dd>
                        <dt class="col-sm-3">Row Count</dt><dd class="col-sm-9"><?= (int)$restoreSnapshot['row_count'] ?></dd>
                    </dl>
                </div>
            </div>

            <div class="alert alert-danger">
                <strong>This will replace all current data in <em><?= htmlspecialchars($liveTable) ?></em> with the snapshot data.</strong>
                A safety snapshot of the current state will be created automatically before restore begins.
            </div>

            <form method="post" action="/admin/snapshots"
                  onsubmit="return confirm('Restore table to snapshot state? This cannot be undone without using the auto-created safety snapshot.')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="restore">
                <input type="hidden" name="id" value="<?= $restoreSnapshotId ?>">
                <button type="submit" class="btn btn-danger">Restore Now</button>
                <a href="/admin/snapshots" class="btn btn-secondary ms-2">Cancel</a>
            </form>
            <?php
            $page['content'] = ob_get_clean();
            return;
        }
    }
}

// ── Flash messages ────────────────────────────────────────────────────────────

if (isset($_GET['msg'])) {
    $msgs    = ['created' => 'Snapshot recorded.', 'deleted' => 'Snapshot deleted.',
                'restored' => 'Table restored to snapshot state. A safety snapshot of the prior state was created.'];
    $message = $msgs[$_GET['msg']] ?? '';
}

// ── Data ──────────────────────────────────────────────────────────────────────

$snapshotCount = dbGetRow("SELECT COUNT(*) AS n FROM snapshots WHERE status = 'active'", [])['n'] ?? 0;
$listReport    = getReportByName('snapshots_list');

// Only show custom-provisioned tables (not snapshot_ tables or system tables) — Pitfall 4
$customTables = dbGetRows("SELECT table_name FROM custom_tables WHERE status = 'active' ORDER BY table_name", []);
$tableNames = array_column($customTables, 'table_name');

// Quick action links for snapshots (diff, restore) — separate from the rendered report
$actionSnapshots = dbGetRows(
    "SELECT s.id, s.table_name, s.snapshot_name, s.snapshot_date, s.snapshot_table
     FROM snapshots s
     WHERE s.status = 'active'
     ORDER BY s.snapshot_date DESC",
    []
);

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
            <?= csrfField() ?>
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

<?php if (!empty($actionSnapshots)): ?>
<div class="card mt-3">
    <div class="card-header py-2"><strong>Snapshot Actions</strong></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Table</th><th>Snapshot</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($actionSnapshots as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['table_name']) ?></td>
                <td><?= htmlspecialchars($s['snapshot_name']) ?></td>
                <td><?= htmlspecialchars($s['snapshot_date']) ?></td>
                <td>
                    <?php if ($s['snapshot_table']): ?>
                    <a href="/admin/snapshots?action=diff&id=<?= (int)$s['id'] ?>" class="btn btn-info btn-sm">Diff</a>
                    <a href="/admin/snapshots?action=restore&id=<?= (int)$s['id'] ?>" class="btn btn-warning btn-sm ms-1">Restore</a>
                    <?php else: ?>
                    <span class="text-muted small">No table data</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php
$page['content'] = ob_get_clean();
?>
