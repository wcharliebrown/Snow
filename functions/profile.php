<?php
/**
 * Profile page custom script
 */

// Require login
requireLogin();

// Get current user
$user = getCurrentUser();
if (!$user) {
    header('Location: /login');
    exit;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($email)) {
        $errors[] = 'First name, last name, and email are required.';
    }
    
    // Check if email is being changed and if it's already taken
    if ($email !== $user['email']) {
        $existing = dbGetRow("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user['id']]);
        if ($existing) {
            $errors[] = 'Email address is already in use.';
        }
    }
    
    // Handle password change
    if (!empty($newPassword)) {
        if (empty($currentPassword)) {
            $errors[] = 'Current password is required to change password.';
        } elseif (!password_verify($currentPassword, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        }
    }
    
    if (empty($errors)) {
        // Update user data
        $updateData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'modified_date' => date('Y-m-d H:i:s')
        ];
        
        // Update password if provided
        if (!empty($newPassword)) {
            $updateData['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        
        $success = dbUpdate('users', $updateData, 'id = ?', [$user['id']]);
        
        if ($success) {
            $page['success'] = 'Profile updated successfully!';
            
            // Refresh user data
            $user = getCurrentUser();
        } else {
            $page['error'] = 'Failed to update profile. Please try again.';
        }
    } else {
        $page['error'] = implode('<br>', $errors);
    }
}

// Set page data
$page['site_name'] = getenv('SITE_NAME') ?: 'Snow Framework';
$page['title'] = 'User Profile';
$page['current_user'] = $user;

// Build profile form content
ob_start();
?>
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Profile Information</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?= htmlspecialchars($user['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?= htmlspecialchars($user['last_name']) ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    
                    <hr>
                    <h6>Change Password</h6>
                    <p class="text-muted">Leave blank if you don't want to change your password</p>
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Account Details</h5>
            </div>
            <div class="card-body">
                <p><strong>User ID:</strong> <?= $user['id'] ?></p>
                <p><strong>Status:</strong> <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($user['status']) ?></span></p>
                <p><strong>Member Since:</strong> <?= date('M j, Y', strtotime($user['created_date'])) ?></p>
                <p><strong>Last Login:</strong> <?= $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never' ?></p>
            </div>
        </div>
        
        <?php if (isAdmin()): ?>
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Admin Actions</h5>
            </div>
            <div class="card-body">
                <a href="/admin" class="btn btn-outline-primary btn-sm w-100 mb-2">Admin Dashboard</a>
                <a href="/admin/users/<?= $user['id'] ?>/edit" class="btn btn-outline-secondary btn-sm w-100">Edit User (Admin)</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
$page['content'] = ob_get_clean();
?>