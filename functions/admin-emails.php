<?php
/**
 * Admin - Email Template Management
 */

requirePermission('email_management');

$page['site_name']    = getenv('SITE_NAME') ?: 'Snow Framework';
$page['current_year'] = date('Y');
$page['current_user'] = getCurrentUser();
$page['navigation']   = getNavigationMenu();

$action     = $_GET['action'] ?? 'list';
$templateId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$message    = '';
$error      = '';

function getEmailTemplateById(int $id): array|false {
    return dbGetRow("SELECT * FROM email_templates WHERE id = ?", [$id]);
}

// ── Handle POST ───────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? $action;

    if ($postAction === 'add') {
        $name             = trim($_POST['name'] ?? '');
        $description      = trim($_POST['description'] ?? '');
        $subject          = trim($_POST['subject'] ?? '');
        $fromAddress      = trim($_POST['from_address'] ?? '');
        $toAddress        = trim($_POST['to_address'] ?? '');
        $bcc              = trim($_POST['bcc'] ?? '');
        $body             = $_POST['body'] ?? '';
        $allowUnsubscribe = isset($_POST['allow_unsubscribe']) ? 1 : 0;
        $status           = $_POST['status'] ?? 'active';

        $errors = validateEmailTemplate(['name' => $name, 'subject' => $subject, 'body' => $body, 'from_address' => $fromAddress]);
        if ($errors) {
            $error = implode(' ', $errors);
        } else {
            $existing = dbGetRow("SELECT id FROM email_templates WHERE name = ? AND status != 'deleted'", [$name]);
            if ($existing) {
                $error = 'A template with that name already exists.';
            } else {
                saveEmailTemplate([
                    'name'             => $name,
                    'description'      => $description,
                    'subject'          => $subject,
                    'from_address'     => $fromAddress,
                    'to_address'       => $toAddress,
                    'bcc'              => $bcc,
                    'body'             => $body,
                    'allow_unsubscribe'=> $allowUnsubscribe,
                    'status'           => $status,
                ]);
                header('Location: /admin/emails?msg=created');
                exit;
            }
        }
        $action = 'add';

    } elseif ($postAction === 'edit' && $templateId) {
        $name             = trim($_POST['name'] ?? '');
        $description      = trim($_POST['description'] ?? '');
        $subject          = trim($_POST['subject'] ?? '');
        $fromAddress      = trim($_POST['from_address'] ?? '');
        $toAddress        = trim($_POST['to_address'] ?? '');
        $bcc              = trim($_POST['bcc'] ?? '');
        $body             = $_POST['body'] ?? '';
        $allowUnsubscribe = isset($_POST['allow_unsubscribe']) ? 1 : 0;
        $status           = $_POST['status'] ?? 'active';

        $errors = validateEmailTemplate(['name' => $name, 'subject' => $subject, 'body' => $body, 'from_address' => $fromAddress]);
        if ($errors) {
            $error = implode(' ', $errors);
        } else {
            $dup = dbGetRow("SELECT id FROM email_templates WHERE name = ? AND id != ? AND status != 'deleted'", [$name, $templateId]);
            if ($dup) {
                $error = 'That name is already used by another template.';
            } else {
                saveEmailTemplate([
                    'id'               => $templateId,
                    'name'             => $name,
                    'description'      => $description,
                    'subject'          => $subject,
                    'from_address'     => $fromAddress,
                    'to_address'       => $toAddress,
                    'bcc'              => $bcc,
                    'body'             => $body,
                    'allow_unsubscribe'=> $allowUnsubscribe,
                    'status'           => $status,
                ]);
                header('Location: /admin/emails?msg=updated');
                exit;
            }
        }
        $action = 'edit';

    } elseif ($postAction === 'delete' && $templateId) {
        deleteEmailTemplate($templateId);
        header('Location: /admin/emails?msg=deleted');
        exit;
    }
}

// ── Flash messages ────────────────────────────────────────────────────────────

if (isset($_GET['msg'])) {
    $msgs    = ['created' => 'Template created.', 'updated' => 'Template updated.', 'deleted' => 'Template deleted.'];
    $message = $msgs[$_GET['msg']] ?? '';
}

// ── Build content ─────────────────────────────────────────────────────────────

ob_start();

if ($action === 'add') {
    $page['title'] = 'Add Email Template';
    $page['breadcrumbs'] = [
        ['title' => 'Home',               'url' => '/'],
        ['title' => 'Admin',              'url' => '/admin'],
        ['title' => 'Email Templates',    'url' => '/admin/emails'],
        ['title' => 'Add Template',       'url' => '', 'current' => true],
    ];
    ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <a href="/admin/emails" class="btn btn-secondary btn-sm mb-3">&larr; Back to Email Templates</a>
    <form method="post" action="/admin/emails?action=add">
        <input type="hidden" name="action" value="add">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Template Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                <div class="form-text">Internal identifier (e.g. welcome_email).</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active"   <?= (($_POST['status'] ?? 'active') === 'active')   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= (($_POST['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label">Subject <span class="text-danger">*</span></label>
                <input type="text" name="subject" class="form-control" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">From Address</label>
                <input type="email" name="from_address" class="form-control" value="<?= htmlspecialchars($_POST['from_address'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">To Address</label>
                <input type="text" name="to_address" class="form-control" value="<?= htmlspecialchars($_POST['to_address'] ?? '') ?>" placeholder="Leave blank to use recipient">
            </div>
            <div class="col-md-4">
                <label class="form-label">BCC</label>
                <input type="text" name="bcc" class="form-control" value="<?= htmlspecialchars($_POST['bcc'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Body <span class="text-danger">*</span></label>
                <div class="form-text mb-1">HTML allowed. Use <code>{{variable}}</code> tokens for dynamic content.</div>
                <textarea name="body" class="form-control font-monospace" rows="12" required><?= htmlspecialchars($_POST['body'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input type="checkbox" name="allow_unsubscribe" class="form-check-input" id="allowUnsub" value="1"
                        <?= !empty($_POST['allow_unsubscribe']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="allowUnsub">Allow Unsubscribe</label>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-success">Create Template</button>
            <a href="/admin/emails" class="btn btn-secondary ms-2">Cancel</a>
        </div>
    </form>
    <?php

} elseif ($action === 'edit' && $templateId) {
    $editTpl = getEmailTemplateById($templateId);
    if (!$editTpl) {
        echo '<div class="alert alert-danger">Template not found.</div>';
    } else {
        $page['title'] = 'Edit Email Template';
        $page['breadcrumbs'] = [
            ['title' => 'Home',            'url' => '/'],
            ['title' => 'Admin',           'url' => '/admin'],
            ['title' => 'Email Templates', 'url' => '/admin/emails'],
            ['title' => 'Edit Template',   'url' => '', 'current' => true],
        ];
        $v = fn($k) => htmlspecialchars($_POST[$k] ?? $editTpl[$k] ?? '');
        ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <a href="/admin/emails" class="btn btn-secondary btn-sm mb-3">&larr; Back to Email Templates</a>
        <form method="post" action="/admin/emails?action=edit&id=<?= $templateId ?>">
            <input type="hidden" name="action" value="edit">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Template Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= $v('name') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active"   <?= (($_POST['status'] ?? $editTpl['status']) === 'active')   ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($_POST['status'] ?? $editTpl['status']) === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"><?= $v('description') ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Subject <span class="text-danger">*</span></label>
                    <input type="text" name="subject" class="form-control" value="<?= $v('subject') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">From Address</label>
                    <input type="email" name="from_address" class="form-control" value="<?= $v('from_address') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">To Address</label>
                    <input type="text" name="to_address" class="form-control" value="<?= $v('to_address') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">BCC</label>
                    <input type="text" name="bcc" class="form-control" value="<?= $v('bcc') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Body <span class="text-danger">*</span></label>
                    <textarea name="body" class="form-control font-monospace" rows="12" required><?= $v('body') ?></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="allow_unsubscribe" class="form-check-input" id="allowUnsub" value="1"
                            <?= !empty($_POST['allow_unsubscribe'] ?? $editTpl['allow_unsubscribe']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="allowUnsub">Allow Unsubscribe</label>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/admin/emails" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
        <?php
    }

} else {
    // List view
    $page['title'] = 'Email Templates';
    $page['breadcrumbs'] = [
        ['title' => 'Home',            'url' => '/'],
        ['title' => 'Admin',           'url' => '/admin'],
        ['title' => 'Email Templates', 'url' => '', 'current' => true],
    ];
    $templateCount = dbGetRow("SELECT COUNT(*) AS n FROM email_templates WHERE status != 'deleted'", [])['n'] ?? 0;
    $listReport    = getReportByName('emails_list');
    ?>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span><?= (int)$templateCount ?> template<?= $templateCount !== 1 ? 's' : '' ?></span>
        <a href="/admin/emails?action=add" class="btn btn-success btn-sm">+ Add Template</a>
    </div>
    <?php if ($listReport): ?>
        <?= renderReport($listReport) ?>
    <?php else: ?>
        <div class="alert alert-warning">Report <code>emails_list</code> not found. <a href="/admin/reports">Recreate it in Reports</a>.</div>
    <?php endif; ?>
    <?php
}

$page['content'] = ob_get_clean();
?>
