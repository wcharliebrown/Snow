<?php
/**
 * Page Management Functions for Snow Framework
 */

/**
 * Render a page
 */
function renderPage($path) {
    // Check session timeout
    checkSessionTimeout();
    
    // Get page from database
    $page = getPageByPath($path);
    
    if (!$page) {
        // Try to find a page with similar path
        $page = getPageByPath(rtrim($path, '/'));
        if (!$page) {
            http_response_code(404);
            renderErrorPage(404, 'Page not found');
            return;
        }
    }
    
    // Check if page requires authentication
    if ($page['require_auth'] && !isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /login');
        return;
    }
    
    // Check if page requires specific permission
    if ($page['required_permission'] && !hasPermission($page['required_permission'])) {
        http_response_code(403);
        renderErrorPage(403, 'Access denied');
        return;
    }
    
    // Execute custom page script if exists
    if ($page['custom_script']) {
        $scriptFile = SNOW_FUNCTIONS . '/' . $page['custom_script'];
        if (file_exists($scriptFile)) {
            include $scriptFile;
        }
    }
    
    // Prepare template data
    $templateData = $page;
    $templateData['site_name'] = getenv('SITE_NAME') ?: 'Snow Framework';
    $templateData['current_year'] = date('Y');
    $templateData['current_user'] = getCurrentUser();
    $templateData['navigation'] = getNavigationMenu();
    $templateData['breadcrumbs'] = getBreadcrumbs($page);
    
    // Pre-compute permission flags used in templates
    $userId = $templateData['current_user']['id'] ?? null;
    $templateData['is_admin']               = hasPermission('admin_access',       $userId);
    $templateData['perm_page_management']   = hasPermission('page_management',    $userId);
    $templateData['perm_user_management']   = hasPermission('user_management',    $userId);
    $templateData['perm_group_management']  = hasPermission('group_management',   $userId);
    $templateData['perm_report_management'] = hasPermission('report_management',  $userId);
    $templateData['perm_table_management']  = hasPermission('table_management',   $userId);
    $templateData['perm_email_management']  = hasPermission('email_management',   $userId);
    $templateData['perm_plugin_management'] = hasPermission('plugin_management',  $userId);
    $templateData['perm_snapshot_management'] = hasPermission('snapshot_management', $userId);
    $templateData['perm_log_viewing']       = hasPermission('log_viewing',        $userId);
    
    // Render the page using template
    renderTemplate($page['template_file'], $templateData);
}

/**
 * Get page by path
 */
function getPageByPath($path) {
    $sql = "SELECT * FROM pages WHERE path = ? AND status = 'active'";
    return dbGetRow($sql, [$path]);
}

/**
 * Get all pages
 */
function getAllPages($status = 'active') {
    $sql = "SELECT p.*, t.name as template_name 
            FROM pages p 
            LEFT JOIN page_templates t ON p.template_file = t.filename 
            WHERE p.status = ? 
            ORDER BY p.title";
    return dbGetRows($sql, [$status]);
}

/**
 * Create or update a page
 */
function savePage($data) {
    if (isset($data['id']) && $data['id']) {
        // Update existing page
        return dbUpdate('pages', $data, 'id = ?', [$data['id']]);
    } else {
        // Create new page
        $data['created_date'] = date('Y-m-d H:i:s');
        $data['modified_date'] = date('Y-m-d H:i:s');
        return dbInsert('pages', $data);
    }
}

/**
 * Delete a page
 */
function deletePage($pageId) {
    return dbUpdate('pages', ['status' => 'deleted'], 'id = ?', [$pageId]);
}

/**
 * Get page templates
 */
function getPageTemplates() {
    $sql = "SELECT * FROM page_templates WHERE status = 'active' ORDER BY name";
    return dbGetRows($sql);
}

/**
 * Get page template by filename
 */
function getPageTemplate($filename) {
    $sql = "SELECT * FROM page_templates WHERE filename = ? AND status = 'active'";
    return dbGetRow($sql, [$filename]);
}

/**
 * Save page template
 */
function savePageTemplate($data) {
    if (isset($data['id']) && $data['id']) {
        return dbUpdate('page_templates', $data, 'id = ?', [$data['id']]);
    } else {
        $data['created_date'] = date('Y-m-d H:i:s');
        return dbInsert('page_templates', $data);
    }
}

/**
 * Render error page
 */
function renderErrorPage($code, $message) {
    $errorPage = [
        'title' => "Error $code",
        'content' => "<h1>Error $code</h1><p>$message</p>",
        'meta_description' => "Error $code - $message",
        'template_file' => 'error_page_template.html'
    ];
    
    renderTemplate($errorPage['template_file'], $errorPage);
}

/**
 * Get navigation menu
 */
function getNavigationMenu($menuName = 'main') {
    try {
        $sql = "SELECT * FROM navigation WHERE menu_name = ? AND status = 'active' ORDER BY sort_order";
        return dbGetRows($sql, [$menuName]);
    } catch (Exception $e) {
        // Return default navigation if table doesn't exist or query fails
        return [
            ['title' => 'Home', 'url' => '/'],
            ['title' => 'Login', 'url' => '/login']
        ];
    }
}

/**
 * Get breadcrumbs for current page
 */
function getBreadcrumbs($currentPage) {
    $breadcrumbs = [];
    
    // Add home
    $breadcrumbs[] = [
        'title' => 'Home',
        'url' => '/',
        'current' => false
    ];
    
    // Add parent pages if any
    if ($currentPage['parent_id']) {
        $parent = getPageById($currentPage['parent_id']);
        if ($parent) {
            $breadcrumbs[] = [
                'title' => $parent['title'],
                'url' => '/' . ltrim($parent['path'], '/'),
                'current' => false
            ];
        }
    }
    
    // Add current page
    $breadcrumbs[] = [
        'title' => $currentPage['title'],
        'url' => '',
        'current' => true
    ];
    
    return $breadcrumbs;
}

/**
 * Get page by ID
 */
function getPageById($pageId) {
    $sql = "SELECT * FROM pages WHERE id = ? AND status = 'active'";
    return dbGetRow($sql, [$pageId]);
}

/**
 * Search pages
 */
function searchPages($searchTerm, $limit = 50) {
    $sql = "SELECT * FROM pages 
            WHERE (title LIKE ? OR content LIKE ? OR meta_description LIKE ?) 
            AND status = 'active' 
            ORDER BY title 
            LIMIT ?";
    
    $searchParam = "%$searchTerm%";
    return dbGetRows($sql, [$searchParam, $searchParam, $searchParam, $limit]);
}

/**
 * Get recently modified pages
 */
function getRecentlyModifiedPages($limit = 10) {
    $sql = "SELECT * FROM pages 
            WHERE status = 'active' 
            ORDER BY modified_date DESC 
            LIMIT ?";
    return dbGetRows($sql, [$limit]);
}

/**
 * Duplicate a page
 */
function duplicatePage($pageId, $newPath, $newTitle) {
    $original = getPageById($pageId);
    if (!$original) {
        return false;
    }
    
    $newPage = $original;
    unset($newPage['id']);
    $newPage['path'] = $newPath;
    $newPage['title'] = $newTitle;
    $newPage['created_date'] = date('Y-m-d H:i:s');
    $newPage['modified_date'] = date('Y-m-d H:i:s');
    
    return dbInsert('pages', $newPage);
}

/**
 * Get page statistics
 */
function getPageStats() {
    $stats = [
        'total_pages' => 0,
        'active_pages' => 0,
        'private_pages' => 0,
        'public_pages' => 0
    ];
    
    $sql = "SELECT 
            COUNT(*) as total_pages,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_pages,
            SUM(CASE WHEN require_auth = 1 THEN 1 ELSE 0 END) as private_pages,
            SUM(CASE WHEN require_auth = 0 THEN 1 ELSE 0 END) as public_pages
            FROM pages";
    
    $result = dbGetRow($sql);
    if ($result) {
        $stats = array_merge($stats, $result);
    }
    
    return $stats;
}
?>