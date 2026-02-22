<?php
/**
 * Admin - Log Viewer
 */

requirePermission('log_viewing');

$page['site_name']    = getenv('SITE_NAME') ?: 'Snow Framework';
$page['current_year'] = date('Y');
$page['current_user'] = getCurrentUser();
$page['navigation']   = getNavigationMenu();
$page['title']        = 'Logs';
$page['breadcrumbs']  = [
    ['title' => 'Home',  'url' => '/'],
    ['title' => 'Admin', 'url' => '/admin'],
    ['title' => 'Logs',  'url' => '', 'current' => true],
];

$message = '';
$error   = '';

// ── Handle POST (clear action) ────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear') {
    $clearLevel = $_POST['level'] ?? null;
    // Validate level to prevent path traversal
    $validLevels = ['error', 'info', 'traffic', 'email', null];
    if (in_array($clearLevel, $validLevels, true)) {
        clearLogs($clearLevel ?: null);
        $message = $clearLevel ? ucfirst($clearLevel) . ' log cleared.' : 'All logs cleared.';
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────

$filterLevel  = $_GET['level'] ?? null;
$searchTerm   = trim($_GET['q'] ?? '');
$validLevels  = ['ERROR', 'INFO', 'TRAFFIC', 'EMAIL'];
if ($filterLevel && !in_array(strtoupper($filterLevel), $validLevels)) {
    $filterLevel = null;
}

// ── Fetch entries ─────────────────────────────────────────────────────────────

$stats = getLogStats();

if ($searchTerm) {
    $entries = searchLogEntries($searchTerm, $filterLevel ? strtoupper($filterLevel) : null, 200);
} else {
    $entries = getLogEntries($filterLevel ? strtoupper($filterLevel) : null, 200);
}

// ── Build content ─────────────────────────────────────────────────────────────

ob_start();
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Stats row -->
<div class="row mb-4">
    <div class="col-sm-3">
        <div class="card text-center border-danger">
            <div class="card-body py-2">
                <div class="h5 mb-0"><?= (int)$stats['error_count'] ?></div>
                <div class="small text-danger">Errors</div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card text-center border-info">
            <div class="card-body py-2">
                <div class="h5 mb-0"><?= (int)$stats['info_count'] ?></div>
                <div class="small text-info">Info</div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card text-center border-secondary">
            <div class="card-body py-2">
                <div class="h5 mb-0"><?= (int)$stats['traffic_count'] ?></div>
                <div class="small text-secondary">Traffic</div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card text-center border-warning">
            <div class="card-body py-2">
                <div class="h5 mb-0"><?= (int)$stats['email_count'] ?></div>
                <div class="small text-warning">Email</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter bar -->
<form method="get" action="/admin/logs" class="row g-2 mb-3">
    <div class="col-sm-4">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search messages…" value="<?= htmlspecialchars($searchTerm) ?>">
    </div>
    <div class="col-sm-3">
        <select name="level" class="form-select form-select-sm">
            <option value="">All levels</option>
            <?php foreach ($validLevels as $lvl): ?>
            <option value="<?= strtolower($lvl) ?>" <?= (strtoupper($filterLevel ?? '') === $lvl) ? 'selected' : '' ?>><?= $lvl ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-sm-auto">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="/admin/logs" class="btn btn-secondary btn-sm">Reset</a>
    </div>
</form>

<!-- Clear buttons -->
<div class="d-flex gap-2 mb-3">
    <?php foreach (['error','info','traffic','email'] as $lvl): ?>
    <form method="post" action="/admin/logs" class="d-inline"
          onsubmit="return confirm('Clear <?= $lvl ?> log?')">
        <input type="hidden" name="action" value="clear">
        <input type="hidden" name="level"  value="<?= $lvl ?>">
        <button type="submit" class="btn btn-outline-secondary btn-sm">Clear <?= ucfirst($lvl) ?></button>
    </form>
    <?php endforeach; ?>
    <form method="post" action="/admin/logs" class="d-inline"
          onsubmit="return confirm('Clear ALL logs?')">
        <input type="hidden" name="action" value="clear">
        <button type="submit" class="btn btn-outline-danger btn-sm">Clear All</button>
    </form>
</div>

<!-- Log table -->
<?php if (empty($entries)): ?>
<div class="alert alert-info">No log entries found.</div>
<?php else: ?>
<p class="text-muted small">Showing <?= count($entries) ?> entries (newest first).</p>
<table class="table table-sm table-striped table-hover font-monospace small">
    <thead class="table-dark">
        <tr><th style="width:160px">Timestamp</th><th style="width:80px">Level</th><th style="width:80px">User</th><th>Message</th></tr>
    </thead>
    <tbody>
    <?php foreach ($entries as $e): ?>
        <?php
        $badgeClass = match(strtoupper($e['level'])) {
            'ERROR'   => 'danger',
            'INFO'    => 'info',
            'TRAFFIC' => 'secondary',
            'EMAIL'   => 'warning',
            default   => 'light',
        };
        ?>
        <tr>
            <td class="text-nowrap"><?= htmlspecialchars($e['timestamp']) ?></td>
            <td><span class="badge bg-<?= $badgeClass ?>"><?= htmlspecialchars($e['level']) ?></span></td>
            <td><?= htmlspecialchars($e['user_id'] ?: '-') ?></td>
            <td style="word-break:break-all"><?= htmlspecialchars($e['message']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php
$page['content'] = ob_get_clean();
?>
