<?php
/**
 * Admin Password Policy Page — Configure password policy (SEC-03)
 * Included by renderPage() via pages table custom_script field.
 * CSRF already validated by requireCsrf() in pages.php.
 */

require_once __DIR__ . '/password-policy.php';

$errors  = [];
$success = false;

// Handle POST: save policy
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('user_management');

    $minLength         = (int)($_POST['min_length'] ?? 8);
    $maxAgeDays        = (int)($_POST['max_age_days'] ?? 0);
    $preventReuseCount = (int)($_POST['prevent_reuse_count'] ?? 0);

    // Validate input ranges
    if ($minLength < 1 || $minLength > 128) {
        $errors[] = 'Minimum length must be between 1 and 128.';
    }
    if ($maxAgeDays < 0) {
        $errors[] = 'Maximum age must be 0 (disabled) or a positive number of days.';
    }
    if ($preventReuseCount < 0 || $preventReuseCount > 50) {
        $errors[] = 'Reuse prevention count must be between 0 and 50.';
    }

    if (empty($errors)) {
        $saved = savePasswordPolicy($minLength, $maxAgeDays, $preventReuseCount, getCurrentUserId());
        if ($saved) {
            $success = true;
            logInfo("Password policy updated by user " . getCurrentUserId()
                    . ": min_length={$minLength}, max_age_days={$maxAgeDays}, prevent_reuse_count={$preventReuseCount}");
        } else {
            $errors[] = 'Failed to save policy. Please try again.';
        }
    }
}

// GET (or after POST): load current policy
$policy = getPasswordPolicy();

ob_start();
?>
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <h1 class="h3 mb-4">Password Policy</h1>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    Password policy saved successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="post" action="/admin/password-policy">
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="min_length" class="form-label fw-bold">Minimum Password Length</label>
                            <input type="number" class="form-control" id="min_length" name="min_length"
                                   value="<?= (int)$policy['min_length'] ?>"
                                   min="1" max="128" required>
                            <div class="form-text">Characters required. Currently: <?= (int)$policy['min_length'] ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="max_age_days" class="form-label fw-bold">Maximum Password Age (days)</label>
                            <input type="number" class="form-control" id="max_age_days" name="max_age_days"
                                   value="<?= (int)$policy['max_age_days'] ?>"
                                   min="0" max="3650">
                            <div class="form-text">Days before users must change password. Set to <strong>0</strong> to disable expiry.</div>
                        </div>

                        <div class="mb-3">
                            <label for="prevent_reuse_count" class="form-label fw-bold">Prevent Password Reuse</label>
                            <input type="number" class="form-control" id="prevent_reuse_count" name="prevent_reuse_count"
                                   value="<?= (int)$policy['prevent_reuse_count'] ?>"
                                   min="0" max="50">
                            <div class="form-text">Number of previous passwords that cannot be reused. Set to <strong>0</strong> to disable.</div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <button type="submit" class="btn btn-primary">Save Policy</button>
                            <?php if ($policy['updated_at'] ?? null): ?>
                                <small class="text-muted">Last updated: <?= htmlspecialchars($policy['updated_at']) ?></small>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $page['content'] = ob_get_clean(); ?>
