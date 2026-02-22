<?php
/**
 * Admin - Plugin Management
 */

requirePermission('plugin_management');

$page['site_name']    = getenv('SITE_NAME') ?: 'Snow Framework';
$page['current_year'] = date('Y');
$page['current_user'] = getCurrentUser();
$page['navigation']   = getNavigationMenu();
$page['title']        = 'Plugins';
$page['breadcrumbs']  = [
    ['title' => 'Home',    'url' => '/'],
    ['title' => 'Admin',   'url' => '/admin'],
    ['title' => 'Plugins', 'url' => '', 'current' => true],
];

$message = '';
$error   = '';

// ── Handle POST ───────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    $pluginId   = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($postAction === 'activate' && $pluginId) {
        dbUpdate('plugins', ['status' => 'active', 'install_date' => date('Y-m-d H:i:s')], 'id = ?', [$pluginId]);
        header('Location: /admin/plugins?msg=activated');
        exit;

    } elseif ($postAction === 'deactivate' && $pluginId) {
        dbUpdate('plugins', ['status' => 'inactive', 'uninstall_date' => date('Y-m-d H:i:s')], 'id = ?', [$pluginId]);
        header('Location: /admin/plugins?msg=deactivated');
        exit;

    } elseif ($postAction === 'add') {
        $name        = trim($_POST['name'] ?? '');
        $version     = trim($_POST['version'] ?? '1.0.0');
        $description = trim($_POST['description'] ?? '');
        $author      = trim($_POST['author'] ?? '');

        if (!$name) {
            $error = 'Plugin name is required.';
        } elseif (!$version) {
            $error = 'Version is required.';
        } else {
            $existing = dbGetRow("SELECT id FROM plugins WHERE name = ?", [$name]);
            if ($existing) {
                $error = 'A plugin with that name already exists.';
            } else {
                dbInsert('plugins', [
                    'name'        => $name,
                    'version'     => $version,
                    'description' => $description,
                    'author'      => $author,
                    'status'      => 'inactive',
                ]);
                header('Location: /admin/plugins?msg=added');
                exit;
            }
        }
        $_GET['action'] = 'add';

    } elseif ($postAction === 'delete' && $pluginId) {
        dbQuery("DELETE FROM plugin_files WHERE plugin_id = ?", [$pluginId]);
        dbQuery("DELETE FROM plugins WHERE id = ?", [$pluginId]);
        header('Location: /admin/plugins?msg=deleted');
        exit;
    }
}

// ── Flash messages ────────────────────────────────────────────────────────────

if (isset($_GET['msg'])) {
    $msgs    = [
        'activated'   => 'Plugin activated.',
        'deactivated' => 'Plugin deactivated.',
        'added'       => 'Plugin registered.',
        'deleted'     => 'Plugin deleted.',
    ];
    $message = $msgs[$_GET['msg']] ?? '';
}

// ── Data ──────────────────────────────────────────────────────────────────────

$plugins = dbGetRows("SELECT * FROM plugins ORDER BY name", []);

// ── Build content ─────────────────────────────────────────────────────────────

ob_start();

if (($_GET['action'] ?? '') === 'add'): ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <a href="/admin/plugins" class="btn btn-secondary btn-sm mb-3">&larr; Back to Plugins</a>
    <form method="post" action="/admin/plugins">
        <input type="hidden" name="action" value="add">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Plugin Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Version <span class="text-danger">*</span></label>
                <input type="text" name="version" class="form-control" value="<?= htmlspecialchars($_POST['version'] ?? '1.0.0') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Author</label>
                <input type="text" name="author" class="form-control" value="<?= htmlspecialchars($_POST['author'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-success">Register Plugin</button>
            <a href="/admin/plugins" class="btn btn-secondary ms-2">Cancel</a>
        </div>
    </form>
<?php else: ?>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span><?= count($plugins) ?> plugin<?= count($plugins) !== 1 ? 's' : '' ?></span>
        <a href="/admin/plugins?action=add" class="btn btn-success btn-sm">+ Register Plugin</a>
    </div>
    <?php if (empty($plugins)): ?>
    <div class="alert alert-info">No plugins registered.</div>
    <?php else: ?>
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr><th>Name</th><th>Version</th><th>Author</th><th>Status</th><th>Installed</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($plugins as $p): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($p['name']) ?></strong>
                    <?php if ($p['description']): ?><br><small class="text-muted"><?= htmlspecialchars($p['description']) ?></small><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($p['version']) ?></td>
                <td><?= htmlspecialchars($p['author'] ?? '') ?></td>
                <td>
                    <?php $badgeClass = match($p['status']) { 'active' => 'success', 'error' => 'danger', default => 'secondary' }; ?>
                    <span class="badge bg-<?= $badgeClass ?>"><?= htmlspecialchars($p['status']) ?></span>
                </td>
                <td><?= $p['install_date'] ? htmlspecialchars($p['install_date']) : '<span class="text-muted">Never</span>' ?></td>
                <td>
                    <?php if ($p['status'] !== 'active'): ?>
                    <form method="post" action="/admin/plugins" class="d-inline">
                        <input type="hidden" name="action" value="activate">
                        <input type="hidden" name="id"     value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="btn btn-success btn-sm">Activate</button>
                    </form>
                    <?php else: ?>
                    <form method="post" action="/admin/plugins" class="d-inline">
                        <input type="hidden" name="action" value="deactivate">
                        <input type="hidden" name="id"     value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="btn btn-warning btn-sm">Deactivate</button>
                    </form>
                    <?php endif; ?>
                    <form method="post" action="/admin/plugins" class="d-inline"
                          onsubmit="return confirm('Delete plugin &quot;<?= htmlspecialchars(addslashes($p['name'])) ?>&quot;?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id"     value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
<?php endif; ?>
<?php
$page['content'] = ob_get_clean();
?>
