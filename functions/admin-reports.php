<?php
/**
 * Admin - Report Management
 */

requirePermission('report_management');

$page['site_name']    = getenv('SITE_NAME') ?: 'Snow Framework';
$page['current_year'] = date('Y');
$page['current_user'] = getCurrentUser();
$page['navigation']   = getNavigationMenu();

$action   = $_GET['action'] ?? 'list';
$reportId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$message  = '';
$error    = '';

function getReportById(int $id): array|false {
    return dbGetRow("SELECT * FROM report_templates WHERE id = ?", [$id]);
}

// ── Handle POST ───────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? $action;

    if ($postAction === 'add') {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sqlTable    = trim($_POST['sql_table'] ?? '');
        $sqlFields   = trim($_POST['sql_fields'] ?? '*') ?: '*';
        $sqlWhere    = trim($_POST['sql_where'] ?? '');
        $sqlOrder    = trim($_POST['sql_order'] ?? '');
        $rowsPerPage = max(1, (int)($_POST['rows_per_page'] ?? 20));
        $outputFmt   = in_array($_POST['output_format'] ?? '', ['html','csv']) ? $_POST['output_format'] : 'html';
        $htmlHeader  = $_POST['html_header'] ?? '';
        $htmlRow     = trim($_POST['html_row_template'] ?? '');
        $htmlFooter  = $_POST['html_footer'] ?? '';
        $status      = $_POST['status'] ?? 'active';

        if (!$name) {
            $error = 'Report name is required.';
        } elseif (!$sqlTable) {
            $error = 'Source table is required.';
        } elseif (!$htmlRow) {
            $error = 'Row template is required.';
        } else {
            $existing = dbGetRow("SELECT id FROM report_templates WHERE name = ? AND status != 'deleted'", [$name]);
            if ($existing) {
                $error = 'A report with that name already exists.';
            } else {
                $currentUser = getCurrentUser();
                saveReport([
                    'name'              => $name,
                    'description'       => $description,
                    'sql_table'         => $sqlTable,
                    'sql_fields'        => $sqlFields,
                    'sql_where'         => $sqlWhere,
                    'sql_order'         => $sqlOrder,
                    'rows_per_page'     => $rowsPerPage,
                    'output_format'     => $outputFmt,
                    'html_header'       => $htmlHeader,
                    'html_row_template' => $htmlRow,
                    'html_footer'       => $htmlFooter,
                    'status'            => $status,
                    'created_by'        => $currentUser['id'] ?? null,
                ]);
                header('Location: /admin/reports?msg=created');
                exit;
            }
        }
        $action = 'add';

    } elseif ($postAction === 'edit' && $reportId) {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sqlTable    = trim($_POST['sql_table'] ?? '');
        $sqlFields   = trim($_POST['sql_fields'] ?? '*') ?: '*';
        $sqlWhere    = trim($_POST['sql_where'] ?? '');
        $sqlOrder    = trim($_POST['sql_order'] ?? '');
        $rowsPerPage = max(1, (int)($_POST['rows_per_page'] ?? 20));
        $outputFmt   = in_array($_POST['output_format'] ?? '', ['html','csv']) ? $_POST['output_format'] : 'html';
        $htmlHeader  = $_POST['html_header'] ?? '';
        $htmlRow     = trim($_POST['html_row_template'] ?? '');
        $htmlFooter  = $_POST['html_footer'] ?? '';
        $status      = $_POST['status'] ?? 'active';

        if (!$name) {
            $error = 'Report name is required.';
        } elseif (!$sqlTable) {
            $error = 'Source table is required.';
        } elseif (!$htmlRow) {
            $error = 'Row template is required.';
        } else {
            $dup = dbGetRow("SELECT id FROM report_templates WHERE name = ? AND id != ? AND status != 'deleted'", [$name, $reportId]);
            if ($dup) {
                $error = 'That name is already used by another report.';
            } else {
                $currentUser = getCurrentUser();
                saveReport([
                    'id'                => $reportId,
                    'name'              => $name,
                    'description'       => $description,
                    'sql_table'         => $sqlTable,
                    'sql_fields'        => $sqlFields,
                    'sql_where'         => $sqlWhere,
                    'sql_order'         => $sqlOrder,
                    'rows_per_page'     => $rowsPerPage,
                    'output_format'     => $outputFmt,
                    'html_header'       => $htmlHeader,
                    'html_row_template' => $htmlRow,
                    'html_footer'       => $htmlFooter,
                    'status'            => $status,
                    'modified_by'       => $currentUser['id'] ?? null,
                ]);
                header('Location: /admin/reports?msg=updated');
                exit;
            }
        }
        $action = 'edit';

    } elseif ($postAction === 'delete' && $reportId) {
        deleteReport($reportId);
        header('Location: /admin/reports?msg=deleted');
        exit;

    } elseif ($postAction === 'duplicate' && $reportId) {
        $src = getReportById($reportId);
        if ($src) {
            $newName = $src['name'] . ' (copy)';
            duplicateReport($reportId, $newName);
        }
        header('Location: /admin/reports?msg=duplicated');
        exit;
    }
}

// ── Flash messages ────────────────────────────────────────────────────────────

if (isset($_GET['msg'])) {
    $msgs    = ['created' => 'Report created.', 'updated' => 'Report updated.',
                'deleted' => 'Report deleted.', 'duplicated' => 'Report duplicated.'];
    $message = $msgs[$_GET['msg']] ?? '';
}

// ── Build content ─────────────────────────────────────────────────────────────

ob_start();

if ($action === 'add') {
    $page['title'] = 'Add Report';
    $page['breadcrumbs'] = [
        ['title' => 'Home',       'url' => '/'],
        ['title' => 'Admin',      'url' => '/admin'],
        ['title' => 'Reports',    'url' => '/admin/reports'],
        ['title' => 'Add Report', 'url' => '', 'current' => true],
    ];
    ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <a href="/admin/reports" class="btn btn-secondary btn-sm mb-3">&larr; Back to Reports</a>
    <form method="post" action="/admin/reports?action=add">
        <input type="hidden" name="action" value="add">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Report Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Output Format</label>
                <select name="output_format" class="form-select">
                    <option value="html" <?= (($_POST['output_format'] ?? 'html') === 'html') ? 'selected' : '' ?>>HTML</option>
                    <option value="csv"  <?= (($_POST['output_format'] ?? '') === 'csv')  ? 'selected' : '' ?>>CSV</option>
                </select>
            </div>
            <div class="col-md-3">
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
                <label class="form-label">Source Table <span class="text-danger">*</span></label>
                <textarea name="sql_table" class="form-control font-monospace" rows="3" placeholder="e.g. users&#10;or: pages p LEFT JOIN page_templates t ON p.template_file = t.filename" required><?= htmlspecialchars($_POST['sql_table'] ?? '') ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Fields</label>
                <input type="text" name="sql_fields" class="form-control" value="<?= htmlspecialchars($_POST['sql_fields'] ?? '*') ?>" placeholder="* or field1, field2">
            </div>
            <div class="col-md-4">
                <label class="form-label">Rows Per Page</label>
                <input type="number" name="rows_per_page" class="form-control" value="<?= (int)($_POST['rows_per_page'] ?? 20) ?>" min="1" max="500">
            </div>
            <div class="col-md-6">
                <label class="form-label">WHERE Clause</label>
                <input type="text" name="sql_where" class="form-control" value="<?= htmlspecialchars($_POST['sql_where'] ?? '') ?>" placeholder="status = 'active'">
            </div>
            <div class="col-md-6">
                <label class="form-label">ORDER BY</label>
                <input type="text" name="sql_order" class="form-control" value="<?= htmlspecialchars($_POST['sql_order'] ?? '') ?>" placeholder="created_date DESC">
            </div>
            <div class="col-12">
                <label class="form-label">HTML Header</label>
                <textarea name="html_header" class="form-control font-monospace" rows="3"><?= htmlspecialchars($_POST['html_header'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label">Row Template <span class="text-danger">*</span></label>
                <div class="form-text mb-1">Use <code>{{fieldname}}</code> tokens. e.g. <code>&lt;tr&gt;&lt;td&gt;{{id}}&lt;/td&gt;&lt;td&gt;{{name}}&lt;/td&gt;&lt;/tr&gt;</code></div>
                <textarea name="html_row_template" class="form-control font-monospace" rows="3" required><?= htmlspecialchars($_POST['html_row_template'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label">HTML Footer</label>
                <textarea name="html_footer" class="form-control font-monospace" rows="3"><?= htmlspecialchars($_POST['html_footer'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-success">Create Report</button>
            <a href="/admin/reports" class="btn btn-secondary ms-2">Cancel</a>
        </div>
    </form>
    <?php

} elseif ($action === 'edit' && $reportId) {
    $editReport = getReportById($reportId);
    if (!$editReport) {
        echo '<div class="alert alert-danger">Report not found.</div>';
    } else {
        $page['title'] = 'Edit Report';
        $page['breadcrumbs'] = [
            ['title' => 'Home',        'url' => '/'],
            ['title' => 'Admin',       'url' => '/admin'],
            ['title' => 'Reports',     'url' => '/admin/reports'],
            ['title' => 'Edit Report', 'url' => '', 'current' => true],
        ];
        $v = fn($k) => htmlspecialchars($_POST[$k] ?? $editReport[$k] ?? '');
        ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <a href="/admin/reports" class="btn btn-secondary btn-sm mb-3">&larr; Back to Reports</a>
        <form method="post" action="/admin/reports?action=edit&id=<?= $reportId ?>">
            <input type="hidden" name="action" value="edit">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Report Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= $v('name') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Output Format</label>
                    <select name="output_format" class="form-select">
                        <option value="html" <?= (($_POST['output_format'] ?? $editReport['output_format']) === 'html') ? 'selected' : '' ?>>HTML</option>
                        <option value="csv"  <?= (($_POST['output_format'] ?? $editReport['output_format']) === 'csv')  ? 'selected' : '' ?>>CSV</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active"   <?= (($_POST['status'] ?? $editReport['status']) === 'active')   ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($_POST['status'] ?? $editReport['status']) === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"><?= $v('description') ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Source Table <span class="text-danger">*</span></label>
                    <textarea name="sql_table" class="form-control font-monospace" rows="3" required><?= $v('sql_table') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fields</label>
                    <input type="text" name="sql_fields" class="form-control" value="<?= $v('sql_fields') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Rows Per Page</label>
                    <input type="number" name="rows_per_page" class="form-control" value="<?= (int)($_POST['rows_per_page'] ?? $editReport['rows_per_page'] ?? 20) ?>" min="1" max="500">
                </div>
                <div class="col-md-6">
                    <label class="form-label">WHERE Clause</label>
                    <input type="text" name="sql_where" class="form-control" value="<?= $v('sql_where') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">ORDER BY</label>
                    <input type="text" name="sql_order" class="form-control" value="<?= $v('sql_order') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">HTML Header</label>
                    <textarea name="html_header" class="form-control font-monospace" rows="3"><?= $v('html_header') ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Row Template <span class="text-danger">*</span></label>
                    <textarea name="html_row_template" class="form-control font-monospace" rows="3" required><?= $v('html_row_template') ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">HTML Footer</label>
                    <textarea name="html_footer" class="form-control font-monospace" rows="3"><?= $v('html_footer') ?></textarea>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/admin/reports" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
        <?php
    }

} elseif ($action === 'run' && $reportId) {
    $report = getReportById($reportId);
    if (!$report) {
        echo '<div class="alert alert-danger">Report not found.</div>';
    } else {
        $page['title'] = 'Run Report: ' . htmlspecialchars($report['name']);
        $page['breadcrumbs'] = [
            ['title' => 'Home',    'url' => '/'],
            ['title' => 'Admin',   'url' => '/admin'],
            ['title' => 'Reports', 'url' => '/admin/reports'],
            ['title' => htmlspecialchars($report['name']), 'url' => '', 'current' => true],
        ];
        ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="/admin/reports" class="btn btn-secondary btn-sm">&larr; Back to Reports</a>
            <div>
                <a href="/admin/reports?action=edit&id=<?= $reportId ?>" class="btn btn-primary btn-sm">Edit</a>
                <form method="post" action="/admin/reports?action=duplicate&id=<?= $reportId ?>" class="d-inline">
                    <input type="hidden" name="action" value="duplicate">
                    <button type="submit" class="btn btn-secondary btn-sm">Duplicate</button>
                </form>
            </div>
        </div>
        <?php if ($report['description']): ?>
        <p class="text-muted"><?= htmlspecialchars($report['description']) ?></p>
        <?php endif; ?>
        <div class="report-output">
            <?php
            try {
                echo renderReport($report);
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Error running report: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>
        <?php
    }

} else {
    // List view
    $page['title'] = 'Reports';
    $page['breadcrumbs'] = [
        ['title' => 'Home',    'url' => '/'],
        ['title' => 'Admin',   'url' => '/admin'],
        ['title' => 'Reports', 'url' => '', 'current' => true],
    ];
    $reportCount = dbGetRow("SELECT COUNT(*) AS n FROM report_templates WHERE status != 'deleted'", [])['n'] ?? 0;
    $listReport  = getReportByName('reports_list');
    ?>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span><?= (int)$reportCount ?> report<?= $reportCount !== 1 ? 's' : '' ?></span>
        <a href="/admin/reports?action=add" class="btn btn-success btn-sm">+ Add Report</a>
    </div>
    <?php if ($listReport): ?>
        <?= renderReport($listReport) ?>
    <?php else: ?>
        <div class="alert alert-warning">Report <code>reports_list</code> not found. <a href="/admin/reports?action=add">Recreate it</a>.</div>
    <?php endif; ?>
    <?php
}

$page['content'] = ob_get_clean();
?>
