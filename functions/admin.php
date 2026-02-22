<?php
/**
 * Admin dashboard page custom script
 */

// Require admin access
requirePermission('admin_access');

// Handle admin page rendering
$page['site_name'] = getenv('SITE_NAME') ?: 'Snow Framework';
$page['title'] = 'Admin Dashboard';
$page['current_year'] = date('Y');
$page['current_user'] = getCurrentUser();
$page['navigation'] = getNavigationMenu();
$page['breadcrumbs'] = [
    ['title' => 'Home', 'url' => '/'],
    ['title' => 'Admin Dashboard', 'url' => '/admin', 'current' => true]
];

// Fetch live counts
$statUsers   = dbGetRow("SELECT COUNT(*) AS n FROM users WHERE status = 'active'", [])['n'] ?? 0;
$statPages   = dbGetRow("SELECT COUNT(*) AS n FROM pages WHERE status = 'active'", [])['n'] ?? 0;
$statReports = dbGetRow("SELECT COUNT(*) AS n FROM report_templates", [])['n'] ?? 0;
$statGroups  = dbGetRow("SELECT COUNT(*) AS n FROM user_groups_list WHERE status = 'active'", [])['n'] ?? 0;

ob_start();
?>
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Users</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= (int)$statUsers ?></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Pages</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= (int)$statPages ?></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Reports</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= (int)$statReports ?></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Groups</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= (int)$statGroups ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-6">
                        <a href="/admin/pages?action=add" class="btn btn-primary btn-sm w-100">Add Page</a>
                    </div>
                    <div class="col-sm-6">
                        <a href="/admin/users?action=add" class="btn btn-success btn-sm w-100">Add User</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$page['content'] = ob_get_clean();

// Add helper function for permission checking
$page['hasPermission'] = function($permission) {
    return hasPermission($permission);
};
?>