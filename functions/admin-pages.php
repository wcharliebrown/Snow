<?php
/**
 * Admin - Page Management
 */

requirePermission('page_management');

$page['site_name']    = getenv('SITE_NAME') ?: 'Snow Framework';
$page['current_year'] = date('Y');
$page['current_user'] = getCurrentUser();
$page['navigation']   = getNavigationMenu();

$action = $_GET['action'] ?? 'list';
$pageId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$message = '';
$error   = '';

// Paths that cannot be deleted
$corePaths = ['home', 'login', 'logout', 'admin'];

// ── Handle POST ───────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? $action;

    if ($postAction === 'add') {
        $title              = trim($_POST['title'] ?? '');
        $path               = trim($_POST['path'] ?? '');
        $content            = $_POST['content'] ?? '';
        $metaDescription    = trim($_POST['meta_description'] ?? '');
        $metaKeywords       = trim($_POST['meta_keywords'] ?? '');
        $templateFile       = $_POST['template_file'] ?? 'default_page_template.html';
        $customScript       = trim($_POST['custom_script'] ?? '') ?: null;
        $requireAuth        = isset($_POST['require_auth']) ? 1 : 0;
        $requiredPermission = trim($_POST['required_permission'] ?? '') ?: null;
        $status             = $_POST['status'] ?? 'active';
        $sortOrder          = (int)($_POST['sort_order'] ?? 0);

        if (!$title) {
            $error = 'Title is required.';
        } elseif (!$path) {
            $error = 'Path is required.';
        } elseif (!preg_match('/^[a-z0-9][a-z0-9\-\/]*$/', $path)) {
            $error = 'Path must start with a letter or digit and contain only lowercase letters, digits, hyphens, and slashes.';
        } else {
            $existing = dbGetRow("SELECT id FROM pages WHERE path = ? AND status != 'deleted'", [$path]);
            if ($existing) {
                $error = 'A page with that path already exists.';
            } else {
                $currentUser = getCurrentUser();
                savePage([
                    'title'               => $title,
                    'path'                => $path,
                    'content'             => $content,
                    'meta_description'    => $metaDescription,
                    'meta_keywords'       => $metaKeywords,
                    'template_file'       => $templateFile,
                    'custom_script'       => $customScript,
                    'require_auth'        => $requireAuth,
                    'required_permission' => $requiredPermission,
                    'status'              => $status,
                    'sort_order'          => $sortOrder,
                    'created_by'          => $currentUser['id'] ?? null,
                ]);
                header('Location: /admin/pages?msg=created');
                exit;
            }
        }
        $action = 'add';

    } elseif ($postAction === 'edit' && $pageId) {
        $title              = trim($_POST['title'] ?? '');
        $path               = trim($_POST['path'] ?? '');
        $content            = $_POST['content'] ?? '';
        $metaDescription    = trim($_POST['meta_description'] ?? '');
        $metaKeywords       = trim($_POST['meta_keywords'] ?? '');
        $templateFile       = $_POST['template_file'] ?? 'default_page_template.html';
        $customScript       = trim($_POST['custom_script'] ?? '') ?: null;
        $requireAuth        = isset($_POST['require_auth']) ? 1 : 0;
        $requiredPermission = trim($_POST['required_permission'] ?? '') ?: null;
        $status             = $_POST['status'] ?? 'active';
        $sortOrder          = (int)($_POST['sort_order'] ?? 0);

        if (!$title) {
            $error = 'Title is required.';
        } elseif (!$path) {
            $error = 'Path is required.';
        } elseif (!preg_match('/^[a-z0-9][a-z0-9\-\/]*$/', $path)) {
            $error = 'Path must start with a letter or digit and contain only lowercase letters, digits, hyphens, and slashes.';
        } else {
            $dup = dbGetRow("SELECT id FROM pages WHERE path = ? AND id != ? AND status != 'deleted'", [$path, $pageId]);
            if ($dup) {
                $error = 'That path is already used by another page.';
            } else {
                $currentUser = getCurrentUser();
                savePage([
                    'id'                  => $pageId,
                    'title'               => $title,
                    'path'                => $path,
                    'content'             => $content,
                    'meta_description'    => $metaDescription,
                    'meta_keywords'       => $metaKeywords,
                    'template_file'       => $templateFile,
                    'custom_script'       => $customScript,
                    'require_auth'        => $requireAuth,
                    'required_permission' => $requiredPermission,
                    'status'              => $status,
                    'sort_order'          => $sortOrder,
                    'modified_by'         => $currentUser['id'] ?? null,
                ]);
                header('Location: /admin/pages?msg=updated');
                exit;
            }
        }
        $action = 'edit';

    } elseif ($postAction === 'delete' && $pageId) {
        $targetPage = getPageById($pageId);
        if ($targetPage && in_array($targetPage['path'], $corePaths)) {
            $error  = 'Core pages cannot be deleted.';
            $action = 'list';
        } else {
            deletePage($pageId);
            header('Location: /admin/pages?msg=deleted');
            exit;
        }
    }
}

// ── Flash messages ────────────────────────────────────────────────────────────

if (isset($_GET['msg'])) {
    $msgs    = ['created' => 'Page created.', 'updated' => 'Page updated.', 'deleted' => 'Page deleted.'];
    $message = $msgs[$_GET['msg']] ?? '';
}

// ── Templates dropdown ────────────────────────────────────────────────────────

$templates = getPageTemplates();

// ── Build content ─────────────────────────────────────────────────────────────

ob_start();

if ($action === 'add') {
    $page['title'] = 'Add Page';
    $page['breadcrumbs'] = [
        ['title' => 'Home',     'url' => '/'],
        ['title' => 'Admin',    'url' => '/admin'],
        ['title' => 'Pages',    'url' => '/admin/pages'],
        ['title' => 'Add Page', 'url' => '', 'current' => true],
    ];
    ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <a href="/admin/pages" class="btn btn-secondary btn-sm mb-3">&larr; Back to Pages</a>
    <form method="post" action="/admin/pages?action=add">
        <input type="hidden" name="action" value="add">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Path <span class="text-danger">*</span></label>
                <input type="text" name="path" class="form-control" value="<?= htmlspecialchars($_POST['path'] ?? '') ?>" placeholder="e.g. about or blog/post" required>
                <div class="form-text">Lowercase letters, digits, hyphens, and slashes only.</div>
            </div>
            <div class="col-12">
                <label class="form-label">Content</label>
                <textarea name="content" class="form-control" rows="8"><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Meta Description</label>
                <input type="text" name="meta_description" class="form-control" value="<?= htmlspecialchars($_POST['meta_description'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Meta Keywords</label>
                <input type="text" name="meta_keywords" class="form-control" value="<?= htmlspecialchars($_POST['meta_keywords'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Template</label>
                <select name="template_file" class="form-select">
                    <?php foreach ($templates as $tpl): ?>
                    <option value="<?= htmlspecialchars($tpl['filename']) ?>"
                        <?= (($_POST['template_file'] ?? 'default_page_template.html') === $tpl['filename']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tpl['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active"   <?= (($_POST['status'] ?? 'active') === 'active')   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= (($_POST['status'] ?? 'active') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="<?= (int)($_POST['sort_order'] ?? 0) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Custom Script</label>
                <input type="text" name="custom_script" class="form-control" value="<?= htmlspecialchars($_POST['custom_script'] ?? '') ?>" placeholder="e.g. mypage.php">
                <div class="form-text">PHP file in functions/ folder (optional).</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Required Permission</label>
                <input type="text" name="required_permission" class="form-control" value="<?= htmlspecialchars($_POST['required_permission'] ?? '') ?>" placeholder="e.g. page_management">
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input type="checkbox" name="require_auth" class="form-check-input" id="requireAuth" value="1"
                        <?= !empty($_POST['require_auth']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="requireAuth">Require Authentication</label>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-success">Create Page</button>
            <a href="/admin/pages" class="btn btn-secondary ms-2">Cancel</a>
        </div>
    </form>
    <?php

} elseif ($action === 'edit' && $pageId) {
    $editPage = getPageById($pageId);
    if (!$editPage) {
        echo '<div class="alert alert-danger">Page not found.</div>';
    } else {
        $page['title'] = 'Edit Page';
        $page['breadcrumbs'] = [
            ['title' => 'Home',      'url' => '/'],
            ['title' => 'Admin',     'url' => '/admin'],
            ['title' => 'Pages',     'url' => '/admin/pages'],
            ['title' => 'Edit Page', 'url' => '', 'current' => true],
        ];
        $isCorePage = in_array($editPage['path'], $corePaths);
        ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($isCorePage): ?><div class="alert alert-warning">This is a core page. The path cannot be changed.</div><?php endif; ?>
        <a href="/admin/pages" class="btn btn-secondary btn-sm mb-3">&larr; Back to Pages</a>
        <form method="post" action="/admin/pages?action=edit&id=<?= $pageId ?>">
            <input type="hidden" name="action" value="edit">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($_POST['title'] ?? $editPage['title']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Path <span class="text-danger">*</span></label>
                    <input type="text" name="path" class="form-control"
                        value="<?= htmlspecialchars($_POST['path'] ?? $editPage['path']) ?>"
                        <?= $isCorePage ? 'readonly' : '' ?> required>
                    <?php if (!$isCorePage): ?>
                    <div class="form-text">Lowercase letters, digits, hyphens, and slashes only.</div>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label">Content</label>
                    <textarea name="content" class="form-control" rows="8"><?= htmlspecialchars($_POST['content'] ?? $editPage['content'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Meta Description</label>
                    <input type="text" name="meta_description" class="form-control" value="<?= htmlspecialchars($_POST['meta_description'] ?? $editPage['meta_description'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Meta Keywords</label>
                    <input type="text" name="meta_keywords" class="form-control" value="<?= htmlspecialchars($_POST['meta_keywords'] ?? $editPage['meta_keywords'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Template</label>
                    <select name="template_file" class="form-select">
                        <?php foreach ($templates as $tpl): ?>
                        <option value="<?= htmlspecialchars($tpl['filename']) ?>"
                            <?= (($_POST['template_file'] ?? $editPage['template_file']) === $tpl['filename']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tpl['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active"   <?= (($_POST['status'] ?? $editPage['status']) === 'active')   ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($_POST['status'] ?? $editPage['status']) === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= (int)($_POST['sort_order'] ?? $editPage['sort_order'] ?? 0) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Custom Script</label>
                    <input type="text" name="custom_script" class="form-control" value="<?= htmlspecialchars($_POST['custom_script'] ?? $editPage['custom_script'] ?? '') ?>" placeholder="e.g. mypage.php">
                    <div class="form-text">PHP file in functions/ folder (optional).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Required Permission</label>
                    <input type="text" name="required_permission" class="form-control" value="<?= htmlspecialchars($_POST['required_permission'] ?? $editPage['required_permission'] ?? '') ?>" placeholder="e.g. page_management">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="require_auth" class="form-check-input" id="requireAuth" value="1"
                            <?= !empty($_POST['require_auth'] ?? $editPage['require_auth']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="requireAuth">Require Authentication</label>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/admin/pages" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
        <?php
    }

} else {
    // List view
    $page['title'] = 'Page Management';
    $page['breadcrumbs'] = [
        ['title' => 'Home',  'url' => '/'],
        ['title' => 'Admin', 'url' => '/admin'],
        ['title' => 'Pages', 'url' => '', 'current' => true],
    ];
    $pageCount  = dbGetRow("SELECT COUNT(*) AS n FROM pages WHERE status != 'deleted'", [])['n'] ?? 0;
    $listReport = getReportByName('pages_list');
    ?>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span><?= (int)$pageCount ?> page<?= $pageCount !== 1 ? 's' : '' ?></span>
        <a href="/admin/pages?action=add" class="btn btn-success btn-sm">+ Add Page</a>
    </div>
    <?php if ($listReport): ?>
        <?= renderReport($listReport) ?>
    <?php else: ?>
        <div class="alert alert-warning">Report <code>pages_list</code> not found. Create it via <a href="/admin/reports">Admin &rsaquo; Reports</a>.</div>
    <?php endif; ?>
    <?php
}

$page['content'] = ob_get_clean();
?>
