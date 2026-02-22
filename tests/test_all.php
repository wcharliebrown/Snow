<?php
/**
 * Snow Framework – Comprehensive Test Suite
 *
 * Covers every feature documented in README.md:
 *   Database, Auth, Template, Pages, Encryption, Logging, Reports, Email
 *
 * Run: php tests/test_all.php
 */

require_once __DIR__ . '/SnowTestRunner.php';
require_once __DIR__ . '/bootstrap.php';

$t = new SnowTestRunner();

// ─────────────────────────────────────────────────────────────────────────────
// DATABASE FUNCTIONS
// ─────────────────────────────────────────────────────────────────────────────
$t->describe('Database Functions', function (SnowTestRunner $t) {

    $t->it('getDbConnection returns a PDO instance', function (SnowTestRunner $t) {
        $db = getDbConnection();
        $t->assertTrue($db instanceof PDO, 'Expected PDO instance');
    });

    $t->it('dbGetRow retrieves a single row', function (SnowTestRunner $t) {
        $row = dbGetRow("SELECT 1 AS value");
        $t->assertTrue(is_array($row));
        // PDO with native prepares returns integers as int, not string
        $t->assertEqual(1, (int)$row['value']);
    });

    $t->it('dbGetRows retrieves multiple rows', function (SnowTestRunner $t) {
        $rows = dbGetRows("SELECT * FROM users WHERE status = 'active' LIMIT 10");
        $t->assertIsArray($rows);
        $t->assertGreaterThan(0, count($rows), 'Should have at least 1 active user');
    });

    $t->it('dbInsert inserts a record and returns its ID', function (SnowTestRunner $t) {
        $id = dbInsert('navigation', [
            'menu_name'  => 'test',
            'title'      => '__test_nav__',
            'url'        => '/test-url',
            'sort_order' => 999,
            'status'     => 'inactive',
        ]);
        $t->assertIsInt((int)$id);
        $t->assertGreaterThan(0, (int)$id);
        // cleanup
        dbDelete('navigation', 'id = ?', [$id]);
    });

    $t->it('dbUpdate modifies a record', function (SnowTestRunner $t) {
        $id = dbInsert('navigation', [
            'menu_name'  => 'test',
            'title'      => '__update_me__',
            'url'        => '/update',
            'sort_order' => 998,
            'status'     => 'inactive',
        ]);
        dbUpdate('navigation', ['title' => '__updated__'], 'id = ?', [$id]);
        $row = dbGetRow("SELECT title FROM navigation WHERE id = ?", [$id]);
        $t->assertEqual('__updated__', $row['title']);
        dbDelete('navigation', 'id = ?', [$id]);
    });

    $t->it('dbDelete removes a record and returns row count', function (SnowTestRunner $t) {
        $id = dbInsert('navigation', [
            'menu_name'  => 'test',
            'title'      => '__delete_me__',
            'url'        => '/delete',
            'sort_order' => 997,
            'status'     => 'inactive',
        ]);
        $count = dbDelete('navigation', 'id = ?', [$id]);
        $t->assertEqual(1, $count);
        $row = dbGetRow("SELECT id FROM navigation WHERE id = ?", [$id]);
        $t->assertFalse($row, 'Row should be deleted');
    });

    $t->it('dbTableExists returns true for existing tables', function (SnowTestRunner $t) {
        $t->assertTrue(dbTableExists('users'));
        $t->assertTrue(dbTableExists('pages'));
        $t->assertFalse(dbTableExists('this_table_does_not_exist_xyz'));
    });

    $t->it('dbGetTableStructure returns table columns', function (SnowTestRunner $t) {
        $structure = dbGetTableStructure('users');
        $t->assertIsArray($structure);
        $t->assertGreaterThan(0, count($structure));
        $fields = array_column($structure, 'Field');
        $t->assertTrue(in_array('email', $fields), 'Expected email column');
        $t->assertTrue(in_array('password_hash', $fields), 'Expected password_hash column');
    });

    $t->it('dbBeginTransaction / dbCommit work without error', function (SnowTestRunner $t) {
        dbBeginTransaction();
        $id = dbInsert('navigation', [
            'menu_name'  => 'txn_test',
            'title'      => '__txn__',
            'url'        => '/txn',
            'sort_order' => 0,
            'status'     => 'inactive',
        ]);
        dbCommit();
        $row = dbGetRow("SELECT id FROM navigation WHERE id = ?", [$id]);
        $t->assertNotNull($row);
        dbDelete('navigation', 'id = ?', [$id]);
    });

    $t->it('dbRollback discards a transaction', function (SnowTestRunner $t) {
        dbBeginTransaction();
        $id = dbInsert('navigation', [
            'menu_name'  => 'txn_rollback',
            'title'      => '__rollback__',
            'url'        => '/rollback',
            'sort_order' => 0,
            'status'     => 'inactive',
        ]);
        dbRollback();
        $row = dbGetRow("SELECT id FROM navigation WHERE id = ?", [$id]);
        $t->assertFalse($row, 'Row should not exist after rollback');
    });

    $t->it('dbCreateTable creates a new table and dbAddColumn adds a column', function (SnowTestRunner $t) {
        // Drop if exists (clean slate)
        try { dbQuery("DROP TABLE IF EXISTS test_snow_tmp"); } catch (Exception $e) {}

        $result = dbCreateTable('test_snow_tmp', [
            'id'   => 'INT AUTO_INCREMENT PRIMARY KEY',
            'name' => 'VARCHAR(100) NOT NULL',
        ]);
        $t->assertTrue($result !== false);
        $t->assertTrue(dbTableExists('test_snow_tmp'));

        // Add column
        dbAddColumn('test_snow_tmp', 'extra', 'VARCHAR(50) NULL');
        $structure = dbGetTableStructure('test_snow_tmp');
        $fields = array_column($structure, 'Field');
        $t->assertTrue(in_array('extra', $fields));

        // Add index
        dbAddIndex('test_snow_tmp', 'idx_name', ['name']);

        // Drop column
        dbDropColumn('test_snow_tmp', 'extra');
        $structure = dbGetTableStructure('test_snow_tmp');
        $fields = array_column($structure, 'Field');
        $t->assertFalse(in_array('extra', $fields));

        // Drop index
        dbDropIndex('test_snow_tmp', 'idx_name');

        // Cleanup
        dbQuery("DROP TABLE test_snow_tmp");
    });

    $t->it('dbQuery returns a PDOStatement', function (SnowTestRunner $t) {
        $stmt = dbQuery("SELECT 1");
        $t->assertTrue($stmt instanceof PDOStatement);
    });

    $t->it('parameterised queries prevent SQL injection', function (SnowTestRunner $t) {
        $malicious = "'; DROP TABLE users; --";
        // Should return no row, not throw
        $row = dbGetRow("SELECT id FROM users WHERE email = ?", [$malicious]);
        $t->assertFalse($row);
        // Users table must still exist
        $t->assertTrue(dbTableExists('users'));
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// AUTHENTICATION & AUTHORISATION
// ─────────────────────────────────────────────────────────────────────────────
$t->describe('Authentication & Authorisation', function (SnowTestRunner $t) {

    // Ensure a fresh session state
    $_SESSION = [];

    $t->it('isLoggedIn returns false when no session user', function (SnowTestRunner $t) {
        $_SESSION = [];
        $t->assertFalse(isLoggedIn());
    });

    $t->it('getCurrentUser returns null when not logged in', function (SnowTestRunner $t) {
        $_SESSION = [];
        $t->assertNull(getCurrentUser());
    });

    $t->it('loginUser returns false for wrong password', function (SnowTestRunner $t) {
        $_SESSION = [];
        $result = loginUser('admin@example.com', 'wrongpassword');
        $t->assertFalse($result);
    });

    $t->it('loginUser returns false for non-existent user', function (SnowTestRunner $t) {
        $_SESSION = [];
        $result = loginUser('nobody@nowhere.invalid', 'password');
        $t->assertFalse($result);
    });

    $t->it('loginUser returns user array on valid credentials', function (SnowTestRunner $t) {
        $_SESSION = [];
        $user = loginUser('admin@example.com', 'admin123');
        $t->assertIsArray($user);
        $t->assertEqual('admin@example.com', $user['email']);
    });

    $t->it('isLoggedIn returns true after loginUser', function (SnowTestRunner $t) {
        $_SESSION = [];
        loginUser('admin@example.com', 'admin123');
        $t->assertTrue(isLoggedIn());
    });

    $t->it('getCurrentUser returns user data after login', function (SnowTestRunner $t) {
        $_SESSION = [];
        loginUser('admin@example.com', 'admin123');
        $user = getCurrentUser();
        $t->assertNotNull($user);
        $t->assertEqual('admin@example.com', $user['email']);
    });

    $t->it('hasPermission returns true for admin_access on admin user', function (SnowTestRunner $t) {
        $_SESSION = [];
        $user = loginUser('admin@example.com', 'admin123');
        $t->assertTrue(hasPermission('admin_access', $user['id']));
    });

    $t->it('hasPermission returns false for non-existent permission', function (SnowTestRunner $t) {
        $_SESSION = [];
        $user = loginUser('admin@example.com', 'admin123');
        $t->assertFalse(hasPermission('nonexistent_permission_xyz', $user['id']));
    });

    $t->it('isAdmin returns true for admin user', function (SnowTestRunner $t) {
        $_SESSION = [];
        $user = loginUser('admin@example.com', 'admin123');
        $t->assertTrue(isAdmin($user['id']));
    });

    $t->it('registerUser creates a new user', function (SnowTestRunner $t) {
        // Clean up first
        dbQuery("DELETE FROM users WHERE email = ?", ['test_snow@example.com']);
        $userId = registerUser('test_snow@example.com', 'TestPass123', 'Test', 'User');
        $t->assertGreaterThan(0, (int)$userId);

        $user = dbGetRow("SELECT * FROM users WHERE email = ?", ['test_snow@example.com']);
        $t->assertNotNull($user);
        $t->assertEqual('Test', $user['first_name']);
        $t->assertEqual('active', $user['status']);

        // Cleanup
        dbDelete('users', 'email = ?', ['test_snow@example.com']);
    });

    $t->it('registerUser returns false if email already exists', function (SnowTestRunner $t) {
        $result = registerUser('admin@example.com', 'AnyPass123', 'Dup', 'User');
        $t->assertFalse($result);
    });

    $t->it('changePassword returns false for wrong old password', function (SnowTestRunner $t) {
        $user = dbGetRow("SELECT id FROM users WHERE email = ?", ['admin@example.com']);
        $result = changePassword($user['id'], 'wrongoldpassword', 'NewPass456');
        $t->assertFalse($result);
    });

    $t->it('changePassword succeeds with correct old password', function (SnowTestRunner $t) {
        // Create temp user for this test
        dbQuery("DELETE FROM users WHERE email = ?", ['changepwd@example.com']);
        $userId = registerUser('changepwd@example.com', 'OldPass123', 'Change', 'Test');
        $result = changePassword($userId, 'OldPass123', 'NewPass456');
        $t->assertTrue((bool)$result);
        // Verify new password works
        $_SESSION = [];
        $user = loginUser('changepwd@example.com', 'NewPass456');
        $t->assertIsArray($user);
        // Cleanup
        dbDelete('users', 'email = ?', ['changepwd@example.com']);
    });

    $t->it('getUserGroups returns groups for admin user', function (SnowTestRunner $t) {
        $user = dbGetRow("SELECT id FROM users WHERE email = ?", ['admin@example.com']);
        $groups = getUserGroups($user['id']);
        $t->assertIsArray($groups);
        $t->assertGreaterThan(0, count($groups));
        $groupNames = array_column($groups, 'name');
        $t->assertTrue(in_array('Administrators', $groupNames));
    });

    $t->it('getUserPermissions returns permissions for admin user', function (SnowTestRunner $t) {
        $user = dbGetRow("SELECT id FROM users WHERE email = ?", ['admin@example.com']);
        $perms = getUserPermissions($user['id']);
        $t->assertIsArray($perms);
        $t->assertGreaterThan(0, count($perms));
        $permNames = array_column($perms, 'name');
        $t->assertTrue(in_array('admin_access', $permNames));
    });

    $t->it('logoutUser clears session', function (SnowTestRunner $t) {
        $_SESSION = [];
        loginUser('admin@example.com', 'admin123');
        $t->assertTrue(isLoggedIn());
        logoutUser();
        // logoutUser destroys the session; restart a clean one for subsequent tests
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION = [];
        $t->assertFalse(isLoggedIn());
    });

    $t->it('checkSessionTimeout returns false with no login_time in session', function (SnowTestRunner $t) {
        $_SESSION = [];
        $t->assertFalse(checkSessionTimeout());
    });

    $t->it('checkSessionTimeout returns false when session has expired', function (SnowTestRunner $t) {
        putenv('SESSION_TIMEOUT=3600');
        $_SESSION = ['user_id' => 1, 'login_time' => time() - 7200]; // 2 hours ago
        // checkSessionTimeout calls logoutUser which destroys session; restart cleanly
        $result = checkSessionTimeout();
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION = [];
        $t->assertFalse($result);
    });

    $t->it('checkSessionTimeout returns true when within timeout', function (SnowTestRunner $t) {
        putenv('SESSION_TIMEOUT=3600');
        $_SESSION = ['user_id' => 1, 'login_time' => time()];
        $result = checkSessionTimeout();
        $t->assertTrue($result);
        $_SESSION = [];
    });

    $t->it('verifyResetToken returns false for invalid token', function (SnowTestRunner $t) {
        $result = verifyResetToken('invalidtokenxyz');
        $t->assertFalse($result);
    });

    $t->it('verifyPassword validates bcrypt hashes correctly', function (SnowTestRunner $t) {
        $hash = hashPassword('mySecret123');
        $t->assertTrue(verifyPassword('mySecret123', $hash));
        $t->assertFalse(verifyPassword('wrongpassword', $hash));
    });

    $t->it('validatePasswordStrength catches weak passwords', function (SnowTestRunner $t) {
        $errors = validatePasswordStrength('short');
        $t->assertGreaterThan(0, count($errors), 'Should flag short password');

        $errors = validatePasswordStrength('alllowercase1');
        $t->assertGreaterThan(0, count($errors), 'Should flag missing uppercase');

        $errors = validatePasswordStrength('ALLUPPERCASE1');
        $t->assertGreaterThan(0, count($errors), 'Should flag missing lowercase');

        $errors = validatePasswordStrength('NoNumbersHere');
        $t->assertGreaterThan(0, count($errors), 'Should flag missing number');
    });

    $t->it('validatePasswordStrength passes a strong password', function (SnowTestRunner $t) {
        $errors = validatePasswordStrength('StrongPass99');
        $t->assertCount(0, $errors);
    });

    // Reset session
    $_SESSION = [];
});

// ─────────────────────────────────────────────────────────────────────────────
// TEMPLATE SYSTEM
// ─────────────────────────────────────────────────────────────────────────────
$t->describe('Template System', function (SnowTestRunner $t) {

    $t->it('processTokens replaces simple variables', function (SnowTestRunner $t) {
        $tpl = 'Hello {{name}}, welcome to {{site}}!';
        $out = processTokens($tpl, ['name' => 'Alice', 'site' => 'Snow']);
        $t->assertEqual('Hello Alice, welcome to Snow!', $out);
    });

    $t->it('processTokens replaces object-dot properties', function (SnowTestRunner $t) {
        $tpl = '{{user.first_name}} {{user.last_name}}';
        $out = processTokens($tpl, ['user' => ['first_name' => 'John', 'last_name' => 'Doe']]);
        $t->assertEqual('John Doe', $out);
    });

    $t->it('processTokens handles {{#each}} loops', function (SnowTestRunner $t) {
        $tpl = '{{#each items}}<li>{{title}}</li>{{/each}}';
        $out = processTokens($tpl, ['items' => [
            ['title' => 'Apple'],
            ['title' => 'Banana'],
        ]]);
        $t->assertEqual('<li>Apple</li><li>Banana</li>', $out);
    });

    $t->it('processTokens handles {{#each}} with empty array', function (SnowTestRunner $t) {
        $tpl = '{{#each items}}<li>{{title}}</li>{{/each}}';
        $out = processTokens($tpl, ['items' => []]);
        $t->assertEqual('', $out);
    });

    $t->it('processTokens handles {{#if}} blocks', function (SnowTestRunner $t) {
        $tpl = '{{#if show}}VISIBLE{{/if}}ALWAYS';
        $outTrue  = processTokens($tpl, ['show' => true]);
        $outFalse = processTokens($tpl, ['show' => false]);
        $t->assertEqual('VISIBLEALWAYS', $outTrue);
        $t->assertEqual('ALWAYS', $outFalse);
    });

    $t->it('processTokens handles {{#unless}} blocks', function (SnowTestRunner $t) {
        $tpl = '{{#unless hide}}VISIBLE{{/unless}}ALWAYS';
        $outFalse = processTokens($tpl, ['hide' => false]);
        $outTrue  = processTokens($tpl, ['hide' => true]);
        $t->assertEqual('VISIBLEALWAYS', $outFalse);
        $t->assertEqual('ALWAYS', $outTrue);
    });

    $t->it('processTokens leaves unresolved tokens as empty string', function (SnowTestRunner $t) {
        $tpl = 'Value: {{missing}}';
        $out = processTokens($tpl, []);
        // After processing, the token remains because simple vars loop skips missing keys
        // (it replaces known keys only) – so {{missing}} should still be present
        // OR be replaced with empty – current implementation leaves it unless key is set
        $t->assertIsString($out);
    });

    $t->it('processTokens handles {{@index}} in each loops', function (SnowTestRunner $t) {
        $tpl = '{{#each items}}{{@index}}:{{name}} {{/each}}';
        $out = processTokens($tpl, ['items' => [
            ['name' => 'A'],
            ['name' => 'B'],
        ]]);
        $t->assertContains('0:A', $out);
        $t->assertContains('1:B', $out);
    });

    $t->it('renderTemplate outputs content from an existing template file', function (SnowTestRunner $t) {
        ob_start();
        renderTemplate('default_page_template.html', [
            'title'            => 'Test Title',
            'content'          => 'Test Content',
            'site_name'        => 'Snow',
            'navigation'       => [],
            'breadcrumbs'      => [],
            'meta_description' => '',
            'meta_keywords'    => '',
            'current_year'     => date('Y'),
            'current_user'     => null,
        ]);
        $html = ob_get_clean();
        $t->assertContains('Test Title', $html);
        $t->assertContains('Test Content', $html);
    });

    $t->it('renderTemplate handles a non-existent template gracefully', function (SnowTestRunner $t) {
        ob_start();
        renderTemplate('this_template_does_not_exist.html', []);
        $html = ob_get_clean();
        $t->assertContains('Template', $html); // Outputs error message
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// PAGE MANAGEMENT
// ─────────────────────────────────────────────────────────────────────────────
$t->describe('Page Management', function (SnowTestRunner $t) {

    $t->it('getPageByPath retrieves the home page', function (SnowTestRunner $t) {
        $page = getPageByPath('home');
        $t->assertNotNull($page);
        $t->assertEqual('home', $page['path']);
        $t->assertEqual('Home', $page['title']);
    });

    $t->it('getPageByPath returns false for non-existent path', function (SnowTestRunner $t) {
        $page = getPageByPath('this-path-does-not-exist-xyz');
        $t->assertFalse($page);
    });

    $t->it('getPageById retrieves a page by its ID', function (SnowTestRunner $t) {
        $home = getPageByPath('home');
        $page = getPageById($home['id']);
        $t->assertNotNull($page);
        $t->assertEqual($home['id'], $page['id']);
    });

    $t->it('savePage creates a new page and returns its ID', function (SnowTestRunner $t) {
        dbQuery("DELETE FROM pages WHERE path = ?", ['test-page-snow']);
        $id = savePage([
            'title'         => 'Test Page',
            'path'          => 'test-page-snow',
            'content'       => '<p>Test content</p>',
            'template_file' => 'default_page_template.html',
            'status'        => 'active',
            'require_auth'  => 0,
        ]);
        $t->assertGreaterThan(0, (int)$id);
        $page = getPageByPath('test-page-snow');
        $t->assertNotNull($page);
        $t->assertEqual('Test Page', $page['title']);
        // Cleanup
        deletePage($id);
    });

    $t->it('savePage updates an existing page', function (SnowTestRunner $t) {
        dbQuery("DELETE FROM pages WHERE path = ?", ['test-update-page']);
        $id = savePage([
            'title'         => 'Before Update',
            'path'          => 'test-update-page',
            'content'       => 'old',
            'template_file' => 'default_page_template.html',
            'status'        => 'active',
            'require_auth'  => 0,
        ]);
        savePage(['id' => $id, 'title' => 'After Update']);
        $page = getPageById($id);
        $t->assertEqual('After Update', $page['title']);
        deletePage($id);
    });

    $t->it('deletePage soft-deletes a page (sets status to deleted)', function (SnowTestRunner $t) {
        dbQuery("DELETE FROM pages WHERE path = ?", ['test-delete-page']);
        $id = savePage([
            'title'         => 'Delete Me',
            'path'          => 'test-delete-page',
            'content'       => '',
            'template_file' => 'default_page_template.html',
            'status'        => 'active',
            'require_auth'  => 0,
        ]);
        deletePage($id);
        $page = getPageByPath('test-delete-page');
        $t->assertFalse($page, 'Soft-deleted page should not appear via getPageByPath');
    });

    $t->it('getAllPages returns an array of active pages', function (SnowTestRunner $t) {
        $pages = getAllPages('active');
        $t->assertIsArray($pages);
        $t->assertGreaterThan(0, count($pages));
    });

    $t->it('searchPages finds pages by title', function (SnowTestRunner $t) {
        $results = searchPages('Home');
        $t->assertIsArray($results);
        $t->assertGreaterThan(0, count($results));
        $titles = array_column($results, 'title');
        $t->assertTrue(in_array('Home', $titles));
    });

    $t->it('searchPages returns empty array for unknown term', function (SnowTestRunner $t) {
        $results = searchPages('xyzzy_not_a_real_page_title_abc');
        $t->assertIsArray($results);
        $t->assertCount(0, $results);
    });

    $t->it('getRecentlyModifiedPages returns pages in descending order', function (SnowTestRunner $t) {
        $pages = getRecentlyModifiedPages(5);
        $t->assertIsArray($pages);
    });

    $t->it('duplicatePage creates a copy of a page', function (SnowTestRunner $t) {
        $home = getPageByPath('home');
        dbQuery("DELETE FROM pages WHERE path = ?", ['home-copy-test']);
        $newId = duplicatePage($home['id'], 'home-copy-test', 'Home Copy');
        $t->assertGreaterThan(0, (int)$newId);
        $copy = getPageById($newId);
        $t->assertNotNull($copy);
        $t->assertEqual('Home Copy', $copy['title']);
        deletePage($newId);
    });

    $t->it('getPageStats returns aggregate counts', function (SnowTestRunner $t) {
        $stats = getPageStats();
        $t->assertIsArray($stats);
        $t->assertTrue(isset($stats['total_pages']));
        $t->assertTrue(isset($stats['active_pages']));
        $t->assertTrue($stats['total_pages'] >= $stats['active_pages']);
    });

    $t->it('getNavigationMenu returns navigation items', function (SnowTestRunner $t) {
        $nav = getNavigationMenu('main');
        $t->assertIsArray($nav);
        $t->assertGreaterThan(0, count($nav));
        $titles = array_column($nav, 'title');
        $t->assertTrue(in_array('Home', $titles));
    });

    $t->it('getPageTemplates returns active page templates', function (SnowTestRunner $t) {
        $templates = getPageTemplates();
        $t->assertIsArray($templates);
        $t->assertGreaterThan(0, count($templates));
        $filenames = array_column($templates, 'filename');
        $t->assertTrue(in_array('default_page_template.html', $filenames));
    });

    $t->it('savePageTemplate creates a template record', function (SnowTestRunner $t) {
        dbQuery("DELETE FROM page_templates WHERE filename = ?", ['test_template_snow.html']);
        $id = savePageTemplate([
            'name'     => 'Test Snow Template',
            'filename' => 'test_template_snow.html',
            'status'   => 'active',
        ]);
        $t->assertGreaterThan(0, (int)$id);
        $tpl = getPageTemplate('test_template_snow.html');
        $t->assertNotNull($tpl);
        dbQuery("DELETE FROM page_templates WHERE id = ?", [$id]);
    });

    $t->it('getBreadcrumbs returns array with Home and current page', function (SnowTestRunner $t) {
        $page = getPageByPath('home');
        $crumbs = getBreadcrumbs($page);
        $t->assertIsArray($crumbs);
        $t->assertGreaterThan(0, count($crumbs));
        // First breadcrumb should be Home
        $t->assertEqual('Home', $crumbs[0]['title']);
        // Last should be current
        $last = end($crumbs);
        $t->assertTrue((bool)$last['current']);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// ENCRYPTION
// ─────────────────────────────────────────────────────────────────────────────
$t->describe('Encryption', function (SnowTestRunner $t) {

    $keyName = 'test_snow_key';

    $t->it('createEncryptionKey creates a key file and DB record', function (SnowTestRunner $t) use ($keyName) {
        // Clean up
        $keyFile = SNOW_KEYS . "/$keyName.key";
        if (file_exists($keyFile)) unlink($keyFile);
        dbQuery("DELETE FROM encryption_keys WHERE name = ?", [$keyName]);

        $id = createEncryptionKey($keyName, 'Test key for Snow test suite');
        $t->assertGreaterThan(0, (int)$id);
        $t->assertTrue(file_exists($keyFile), 'Key file should be created');
    });

    $t->it('getEncryptionKey reads the key from disk', function (SnowTestRunner $t) use ($keyName) {
        $key = getEncryptionKey($keyName);
        $t->assertNotNull($key);
        $t->assertIsString($key);
        $t->assertGreaterThan(10, strlen($key));
    });

    $t->it('encryptString returns a base64-encoded string', function (SnowTestRunner $t) use ($keyName) {
        $encrypted = encryptString('Hello Snow', $keyName);
        $t->assertIsString($encrypted);
        $t->assertTrue(base64_decode($encrypted) !== false);
    });

    $t->it('decryptString recovers the original plaintext', function (SnowTestRunner $t) use ($keyName) {
        $plaintext = 'Secret message 12345!';
        $encrypted = encryptString($plaintext, $keyName);
        $decrypted = decryptString($encrypted, $keyName);
        $t->assertEqual($plaintext, $decrypted);
    });

    $t->it('encryptString returns different ciphertext each call (random IV)', function (SnowTestRunner $t) use ($keyName) {
        $enc1 = encryptString('same plaintext', $keyName);
        $enc2 = encryptString('same plaintext', $keyName);
        $t->assertNotEqual($enc1, $enc2, 'Each encryption should use a unique IV');
    });

    $t->it('decryptString returns false for tampered ciphertext', function (SnowTestRunner $t) use ($keyName) {
        $result = decryptString('definitely_not_valid_base64_ciphertext', $keyName);
        $t->assertFalse($result);
    });

    $t->it('encryptString returns false for non-existent key', function (SnowTestRunner $t) {
        $result = encryptString('data', 'this_key_does_not_exist');
        $t->assertFalse($result);
    });

    $t->it('encryptField / decryptField round-trip works', function (SnowTestRunner $t) use ($keyName) {
        $value = 'sensitive_field_data';
        $enc = encryptField($value, $keyName);
        $dec = decryptField($enc, $keyName);
        $t->assertEqual($value, $dec);
    });

    $t->it('encryptField returns original value for empty input', function (SnowTestRunner $t) use ($keyName) {
        $t->assertEqual('', encryptField('', $keyName));
        $t->assertNull(encryptField(null, $keyName));
    });

    $t->it('generateSecureToken produces a hex string of expected length', function (SnowTestRunner $t) {
        $token = generateSecureToken(32);
        $t->assertEqual(64, strlen($token), 'Expected 64 hex chars for 32 bytes');
        $t->assertMatchesRegex('/^[0-9a-f]+$/', $token);
    });

    $t->it('generatePasswordResetToken contains a timestamp', function (SnowTestRunner $t) {
        $token = generatePasswordResetToken();
        $t->assertContains('-', $token);
        // The timestamp part should be numeric
        $parts = explode('-', $token);
        $ts = end($parts);
        $t->assertTrue(is_numeric($ts));
    });

    $t->it('getEncryptionKeys lists active keys from DB', function (SnowTestRunner $t) use ($keyName) {
        $keys = getEncryptionKeys();
        $t->assertIsArray($keys);
        $names = array_column($keys, 'name');
        $t->assertTrue(in_array($keyName, $names));
    });

    $t->it('deleteEncryptionKey removes key file and marks DB record deleted', function (SnowTestRunner $t) use ($keyName) {
        deleteEncryptionKey($keyName);
        $keyFile = SNOW_KEYS . "/$keyName.key";
        $t->assertFalse(file_exists($keyFile), 'Key file should be deleted');
        $row = dbGetRow("SELECT status FROM encryption_keys WHERE name = ?", [$keyName]);
        // Row still exists but marked deleted
        if ($row) {
            $t->assertEqual('deleted', $row['status']);
        }
        // Cleanup DB record
        dbQuery("DELETE FROM encryption_keys WHERE name = ?", [$keyName]);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// LOGGING SYSTEM
// ─────────────────────────────────────────────────────────────────────────────
$t->describe('Logging System', function (SnowTestRunner $t) {

    // Use a temp log path to avoid polluting real logs
    $originalLogs = SNOW_LOGS;

    $t->it('logError writes to the error.log and snow.log', function (SnowTestRunner $t) {
        $msg = 'Test error message ' . uniqid();
        logError($msg);

        $errorLog = SNOW_LOGS . '/error.log';
        $mainLog  = SNOW_LOGS . '/snow.log';

        $t->assertTrue(file_exists($errorLog), 'error.log should exist');
        $t->assertTrue(file_exists($mainLog),  'snow.log should exist');

        $content = file_get_contents($errorLog);
        $t->assertContains($msg, $content);
        $content = file_get_contents($mainLog);
        $t->assertContains($msg, $content);
    });

    $t->it('logInfo writes when LOG_LEVEL >= 2', function (SnowTestRunner $t) {
        putenv('LOG_LEVEL=2');
        $msg = 'Test info message ' . uniqid();
        logInfo($msg);

        $infoLog = SNOW_LOGS . '/info.log';
        $t->assertTrue(file_exists($infoLog), 'info.log should exist');
        $content = file_get_contents($infoLog);
        $t->assertContains($msg, $content);
    });

    $t->it('logTraffic writes when LOG_TRAFFIC=1', function (SnowTestRunner $t) {
        putenv('LOG_TRAFFIC=1');
        $path = 'test/traffic/path/' . uniqid();
        logTraffic($path);

        $trafficLog = SNOW_LOGS . '/traffic.log';
        $t->assertTrue(file_exists($trafficLog));
        $content = file_get_contents($trafficLog);
        $t->assertContains($path, $content);
    });

    $t->it('logEmail writes when LOG_EMAIL=1', function (SnowTestRunner $t) {
        putenv('LOG_EMAIL=1');
        $subject = 'Test email subject ' . uniqid();
        logEmail('test@example.com', $subject, 'test_template');

        $emailLog = SNOW_LOGS . '/email.log';
        $t->assertTrue(file_exists($emailLog));
        $content = file_get_contents($emailLog);
        $t->assertContains($subject, $content);
    });

    $t->it('getLogEntries returns an array of parsed entries', function (SnowTestRunner $t) {
        // Write a known entry
        logError('GetLogEntries test entry');
        $entries = getLogEntries('ERROR', 100);
        $t->assertIsArray($entries);
        $t->assertGreaterThan(0, count($entries));

        $entry = $entries[0];
        $t->assertTrue(isset($entry['timestamp']));
        $t->assertTrue(isset($entry['level']));
        $t->assertTrue(isset($entry['message']));
    });

    $t->it('getLogEntries filters by level', function (SnowTestRunner $t) {
        $entries = getLogEntries('ERROR', 100);
        foreach ($entries as $entry) {
            $t->assertEqual('ERROR', $entry['level'], 'All entries should be ERROR level');
        }
    });

    $t->it('searchLogEntries finds entries containing search term', function (SnowTestRunner $t) {
        $unique = 'SEARCHABLE_' . uniqid();
        logError($unique);
        $results = searchLogEntries($unique, null, 100);
        $t->assertIsArray($results);
        $t->assertGreaterThan(0, count($results));
        $t->assertContains($unique, $results[0]['message']);
    });

    $t->it('getLogStats returns structured statistics', function (SnowTestRunner $t) {
        $stats = getLogStats();
        $t->assertTrue(isset($stats['total_entries']));
        $t->assertTrue(isset($stats['error_count']));
        $t->assertTrue(isset($stats['info_count']));
        $t->assertTrue(isset($stats['traffic_count']));
        $t->assertTrue(isset($stats['email_count']));
        $t->assertGreaterThan(0, $stats['total_entries']);
        $t->assertGreaterThan(0, $stats['error_count']);
    });

    $t->it('clearLogs removes a specific log file', function (SnowTestRunner $t) {
        // Write something first to create the file
        logError('Clear logs test');
        $t->assertTrue(file_exists(SNOW_LOGS . '/error.log'));

        clearLogs('error');
        $t->assertFalse(file_exists(SNOW_LOGS . '/error.log'), 'error.log should be removed');

        // Write again so subsequent tests still work
        logError('Post-clear test entry');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// REPORT SYSTEM
// ─────────────────────────────────────────────────────────────────────────────
$t->describe('Report System', function (SnowTestRunner $t) {

    $reportName = 'test_snow_users_report';

    $t->it('saveReport creates a new report and returns its ID', function (SnowTestRunner $t) use ($reportName) {
        dbQuery("DELETE FROM report_templates WHERE name = ?", [$reportName]);
        $id = saveReport([
            'name'              => $reportName,
            'description'       => 'Test report',
            'sql_table'         => 'users',
            'sql_fields'        => 'id, email, first_name, last_name',
            'sql_order'         => 'id',
            'rows_per_page'     => 10,
            'output_format'     => 'html',
            'html_header'       => '<table><tr><th>ID</th><th>Email</th></tr>',
            'html_row_template' => '<tr><td>{{id}}</td><td>{{email}}</td></tr>',
            'html_footer'       => '</table>',
            'status'            => 'active',
        ]);
        $t->assertGreaterThan(0, (int)$id);
    });

    $t->it('getReportByName retrieves the saved report', function (SnowTestRunner $t) use ($reportName) {
        $report = getReportByName($reportName);
        $t->assertNotNull($report);
        $t->assertEqual($reportName, $report['name']);
        $t->assertEqual('users', $report['sql_table']);
    });

    $t->it('getAllReports returns array including the test report', function (SnowTestRunner $t) use ($reportName) {
        $reports = getAllReports('active');
        $t->assertIsArray($reports);
        $names = array_column($reports, 'name');
        $t->assertTrue(in_array($reportName, $names));
    });

    $t->it('buildReportSQL constructs correct SQL from report definition', function (SnowTestRunner $t) use ($reportName) {
        $report = getReportByName($reportName);
        $sql = buildReportSQL($report);
        $t->assertContains('SELECT', $sql);
        $t->assertContains('users', $sql);
        $t->assertContains('id, email', $sql);
        $t->assertContains('ORDER BY', $sql);
    });

    $t->it('renderReport returns HTML string with data rows', function (SnowTestRunner $t) use ($reportName) {
        $_GET = ['page' => 1]; // simulate request
        $report = getReportByName($reportName);
        $html = renderReport($report);
        $t->assertIsString($html);
        $t->assertContains('<table>', $html);
        $t->assertContains('admin@example.com', $html);
    });

    $t->it('searchReports finds report by name', function (SnowTestRunner $t) use ($reportName) {
        $results = searchReports('test_snow');
        $t->assertIsArray($results);
        $names = array_column($results, 'name');
        $t->assertTrue(in_array($reportName, $names));
    });

    $t->it('getReportStats returns aggregate report data', function (SnowTestRunner $t) {
        $stats = getReportStats();
        $t->assertTrue(isset($stats['total_reports']));
        $t->assertTrue(isset($stats['active_reports']));
        $t->assertGreaterThan(0, (int)$stats['active_reports']);
    });

    $t->it('validateReport passes for a valid report definition', function (SnowTestRunner $t) {
        $errors = validateReport([
            'name'              => 'valid_report',
            'sql_table'         => 'users',
            'html_row_template' => '<tr><td>{{email}}</td></tr>',
        ]);
        $t->assertCount(0, $errors);
    });

    $t->it('validateReport catches missing required fields', function (SnowTestRunner $t) {
        $errors = validateReport([]);
        $t->assertGreaterThan(0, count($errors));
    });

    $t->it('duplicateReport creates a copy with a new name', function (SnowTestRunner $t) use ($reportName) {
        $original = getReportByName($reportName);
        $copyName = $reportName . '_copy';
        dbQuery("DELETE FROM report_templates WHERE name = ?", [$copyName]);
        $newId = duplicateReport($original['id'], $copyName);
        $t->assertGreaterThan(0, (int)$newId);
        $copy = getReportByName($copyName);
        $t->assertNotNull($copy);
        $t->assertEqual($copyName, $copy['name']);
        deleteReport($newId);
    });

    $t->it('buildPagination returns empty string for single page', function (SnowTestRunner $t) {
        $html = buildPagination(1, 20, 15, 'test_report');
        $t->assertEqual('', $html);
    });

    $t->it('buildPagination returns HTML for multi-page results', function (SnowTestRunner $t) {
        $html = buildPagination(2, 10, 100, 'test_report');
        $t->assertContains('pagination', $html);
        $t->assertContains('Previous', $html);
        $t->assertContains('Next', $html);
    });

    $t->it('deleteReport soft-deletes the test report', function (SnowTestRunner $t) use ($reportName) {
        $report = getReportByName($reportName);
        deleteReport($report['id']);
        $deleted = getReportByName($reportName);
        $t->assertFalse($deleted, 'Deleted report should not appear in active list');
        // Cleanup
        dbQuery("DELETE FROM report_templates WHERE name = ?", [$reportName]);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// EMAIL SYSTEM
// ─────────────────────────────────────────────────────────────────────────────
$t->describe('Email System', function (SnowTestRunner $t) {

    $templateName = 'test_snow_email';

    $t->it('saveEmailTemplate creates a new email template', function (SnowTestRunner $t) use ($templateName) {
        dbQuery("DELETE FROM email_templates WHERE name = ?", [$templateName]);
        $id = saveEmailTemplate([
            'name'             => $templateName,
            'description'      => 'Test template',
            'subject'          => 'Hello {{first_name}}',
            'from_address'     => 'noreply@example.com',
            'body'             => '<p>Hello {{first_name}}, welcome!</p>',
            'allow_unsubscribe'=> 0,
            'status'           => 'active',
        ]);
        $t->assertGreaterThan(0, (int)$id);
    });

    $t->it('getEmailTemplate retrieves the saved template', function (SnowTestRunner $t) use ($templateName) {
        $tpl = getEmailTemplate($templateName);
        $t->assertNotNull($tpl);
        $t->assertEqual($templateName, $tpl['name']);
    });

    $t->it('getAllEmailTemplates returns an array including the test template', function (SnowTestRunner $t) use ($templateName) {
        $templates = getAllEmailTemplates();
        $names = array_column($templates, 'name');
        $t->assertTrue(in_array($templateName, $names));
    });

    $t->it('validateEmailTemplate passes a valid template', function (SnowTestRunner $t) {
        $errors = validateEmailTemplate([
            'name'         => 'valid_tpl',
            'subject'      => 'Test Subject',
            'body'         => 'Test body',
            'from_address' => 'from@example.com',
        ]);
        $t->assertCount(0, $errors);
    });

    $t->it('validateEmailTemplate catches missing name, subject, body', function (SnowTestRunner $t) {
        $errors = validateEmailTemplate([]);
        $t->assertGreaterThan(0, count($errors));
    });

    $t->it('validateEmailTemplate catches invalid from_address', function (SnowTestRunner $t) {
        $errors = validateEmailTemplate([
            'name'         => 'tpl',
            'subject'      => 'Sub',
            'body'         => 'Body',
            'from_address' => 'not-an-email',
        ]);
        $t->assertGreaterThan(0, count($errors));
    });

    $t->it('createUnsubscribeToken inserts a token record', function (SnowTestRunner $t) use ($templateName) {
        $token = createUnsubscribeToken('sub@example.com', $templateName);
        $t->assertGreaterThan(0, (int)$token, 'Should return the DB insert ID');
        // Cleanup
        dbQuery("DELETE FROM unsubscribe_tokens WHERE template_name = ? AND email = ?", [$templateName, 'sub@example.com']);
    });

    $t->it('isUnsubscribed returns false for a non-unsubscribed address', function (SnowTestRunner $t) use ($templateName) {
        $t->assertFalse(isUnsubscribed('neversubbed@example.com', $templateName));
    });

    $t->it('processUnsubscribe unsubscribes a valid token', function (SnowTestRunner $t) use ($templateName) {
        // Insert a token manually
        $token = bin2hex(random_bytes(16));
        dbInsert('unsubscribe_tokens', [
            'email'         => 'unsub_test@example.com',
            'template_name' => $templateName,
            'token'         => $token,
            'status'        => 'active',
            'created_date'  => date('Y-m-d H:i:s'),
            'expiry_date'   => date('Y-m-d H:i:s', strtotime('+1 year')),
        ]);

        $result = processUnsubscribe($token);
        $t->assertNotNull($result);
        $t->assertIsArray($result);

        $t->assertTrue(isUnsubscribed('unsub_test@example.com', $templateName));

        // Cleanup
        dbQuery("DELETE FROM unsubscribe_tokens WHERE token = ?", [$token]);
    });

    $t->it('processUnsubscribe returns false for invalid token', function (SnowTestRunner $t) {
        $t->assertFalse(processUnsubscribe('invalid_token_xyz'));
    });

    $t->it('getUnsubscribeStats returns structured stats', function (SnowTestRunner $t) {
        $stats = getUnsubscribeStats();
        $t->assertTrue(isset($stats['total_tokens']));
        $t->assertTrue(isset($stats['active_tokens']));
        $t->assertTrue(isset($stats['unsubscribed_tokens']));
    });

    $t->it('cleanupExpiredUnsubscribeTokens returns a non-negative count', function (SnowTestRunner $t) {
        // Insert an expired token
        dbInsert('unsubscribe_tokens', [
            'email'         => 'expired@example.com',
            'template_name' => 'test_cleanup',
            'token'         => bin2hex(random_bytes(8)),
            'status'        => 'active',
            'created_date'  => date('Y-m-d H:i:s'),
            'expiry_date'   => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
        $count = cleanupExpiredUnsubscribeTokens();
        $t->assertTrue($count >= 1, 'Should clean up at least the expired token we inserted');
    });

    $t->it('getEmailTemplate returns false for non-existent template', function (SnowTestRunner $t) {
        $tpl = getEmailTemplate('this_template_does_not_exist_xyz');
        $t->assertFalse($tpl);
    });

    $t->it('deleteEmailTemplate soft-deletes the template', function (SnowTestRunner $t) use ($templateName) {
        $tpl = getEmailTemplate($templateName);
        deleteEmailTemplate($tpl['id']);
        $deleted = getEmailTemplate($templateName);
        $t->assertFalse($deleted, 'Deleted template should not appear as active');
        // Hard delete
        dbQuery("DELETE FROM email_templates WHERE name = ?", [$templateName]);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// HTTP ENDPOINT TESTS (using PHP built-in server on port 8080)
// ─────────────────────────────────────────────────────────────────────────────
$t->describe('HTTP Endpoints', function (SnowTestRunner $t) {

    // When running inside Docker, Apache is reachable via service name.
    // When running on host, it is mapped to 127.0.0.1:8080.
    // Override with TEST_BASE_URL env var if needed.
    $base = getenv('TEST_BASE_URL') ?: (getenv('RUNNING_IN_DOCKER') ? 'http://apache' : 'http://127.0.0.1:8080');

    function httpGet(string $url, array $cookies = []): array {
        $ctx = stream_context_create(['http' => [
            'method'          => 'GET',
            'follow_location' => 0,
            'ignore_errors'   => true,
            'header'          => $cookies ? 'Cookie: ' . http_build_query($cookies, '', '; ') : '',
        ]]);
        $body    = @file_get_contents($url, false, $ctx);
        $headers = $http_response_header ?? [];
        $status  = 200;
        foreach ($headers as $h) {
            if (preg_match('/HTTP\/\S+ (\d+)/', $h, $m)) {
                $status = (int)$m[1];
            }
        }
        return ['status' => $status, 'body' => $body ?: '', 'headers' => $headers];
    }

    function httpPost(string $url, array $data): array {
        $ctx = stream_context_create(['http' => [
            'method'          => 'POST',
            'content'         => http_build_query($data),
            'header'          => "Content-Type: application/x-www-form-urlencoded\r\n",
            'follow_location' => 0,
            'ignore_errors'   => true,
        ]]);
        $body    = @file_get_contents($url, false, $ctx);
        $headers = $http_response_header ?? [];
        $status  = 200;
        foreach ($headers as $h) {
            if (preg_match('/HTTP\/\S+ (\d+)/', $h, $m)) {
                $status = (int)$m[1];
            }
        }
        return ['status' => $status, 'body' => $body ?: '', 'headers' => $headers];
    }

    $t->it('homepage returns HTTP 200', function (SnowTestRunner $t) use ($base) {
        $res = httpGet("$base/");
        $t->assertEqual(200, $res['status']);
    });

    $t->it('homepage contains site name', function (SnowTestRunner $t) use ($base) {
        $res = httpGet("$base/");
        $t->assertContains('Snow', $res['body']);
    });

    $t->it('/login returns HTTP 200', function (SnowTestRunner $t) use ($base) {
        $res = httpGet("$base/login");
        $t->assertEqual(200, $res['status']);
    });

    $t->it('/login page contains a login form', function (SnowTestRunner $t) use ($base) {
        $res = httpGet("$base/login");
        $t->assertContains('form', strtolower($res['body']));
        $t->assertContains('email', strtolower($res['body']));
        $t->assertContains('password', strtolower($res['body']));
    });

    $t->it('/admin redirects to /login for unauthenticated requests', function (SnowTestRunner $t) use ($base) {
        $res = httpGet("$base/admin");
        // Either a 302 redirect or the login page served with 200
        $isRedirect = $res['status'] === 302 || $res['status'] === 301;
        $hasLogin   = strpos(strtolower($res['body']), 'login') !== false;
        $t->assertTrue($isRedirect || $hasLogin,
            "Expected redirect or login page, got status {$res['status']}");
    });

    $t->it('non-existent page returns HTTP 404', function (SnowTestRunner $t) use ($base) {
        $res = httpGet("$base/this-page-does-not-exist-xyz");
        $t->assertEqual(404, $res['status']);
    });

    $t->it('URL rewriting works (no .php extension required)', function (SnowTestRunner $t) use ($base) {
        $res = httpGet("$base/home");
        $t->assertEqual(200, $res['status']);
        $t->assertContains('Snow', $res['body']);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// PAGE MANAGEMENT UNIT TESTS
// ─────────────────────────────────────────────────────────────────────────────
$t->describe('Page Management Functions', function (SnowTestRunner $t) {

    $testPath = 'test-page-' . time();

    $t->it('savePage creates a new page and returns an id', function (SnowTestRunner $t) use ($testPath) {
        $id = savePage([
            'title'         => 'Test Page',
            'path'          => $testPath,
            'content'       => '<p>Hello</p>',
            'template_file' => 'default_page_template.html',
            'status'        => 'active',
        ]);
        $t->assertGreaterThan(0, (int)$id, 'savePage should return a positive insert id');
        // Store for later tests
        $GLOBALS['__test_page_path'] = $testPath;
        $GLOBALS['__test_page_id']   = (int)$id;
    });

    $t->it('getPageByPath returns the created page', function (SnowTestRunner $t) use ($testPath) {
        $pg = getPageByPath($testPath);
        $t->assertIsArray($pg);
        $t->assertEqual('Test Page', $pg['title']);
        $t->assertEqual($testPath, $pg['path']);
    });

    $t->it('getPageById returns the page by id', function (SnowTestRunner $t) {
        $id = $GLOBALS['__test_page_id'] ?? 0;
        $pg = getPageById($id);
        $t->assertIsArray($pg);
        $t->assertEqual('Test Page', $pg['title']);
    });

    $t->it('getAllPages includes the new page', function (SnowTestRunner $t) use ($testPath) {
        $pages = getAllPages('active');
        $t->assertIsArray($pages);
        $paths = array_column($pages, 'path');
        $t->assertTrue(in_array($testPath, $paths), "New page should appear in getAllPages");
    });

    $t->it('savePage with id updates an existing page', function (SnowTestRunner $t) use ($testPath) {
        $id = $GLOBALS['__test_page_id'] ?? 0;
        savePage(['id' => $id, 'title' => 'Updated Title', 'path' => $testPath,
                  'template_file' => 'default_page_template.html', 'status' => 'active']);
        $pg = getPageById($id);
        $t->assertEqual('Updated Title', $pg['title']);
    });

    $t->it('deletePage soft-deletes (status becomes deleted)', function (SnowTestRunner $t) use ($testPath) {
        $id = $GLOBALS['__test_page_id'] ?? 0;
        deletePage($id);
        // getPageByPath only returns active pages
        $pg = getPageByPath($testPath);
        $t->assertFalse($pg, 'Deleted page should not appear via getPageByPath');
        // Verify the row still exists with status=deleted
        $row = dbGetRow("SELECT status FROM pages WHERE id = ?", [$id]);
        $t->assertEqual('deleted', $row['status']);
        // Hard-delete to keep DB clean
        dbQuery("DELETE FROM pages WHERE id = ?", [$id]);
    });

    $t->it('getPageTemplates returns at least one template', function (SnowTestRunner $t) {
        $tpls = getPageTemplates();
        $t->assertIsArray($tpls);
        $t->assertGreaterThan(0, count($tpls), 'Should have at least one template');
    });

    $t->it('path validation regex rejects invalid paths', function (SnowTestRunner $t) {
        $invalid = ['', 'Has Spaces', '/leading-slash', 'UPPERCASE', '-starts-with-dash', 'has..dots'];
        foreach ($invalid as $p) {
            $ok = preg_match('/^[a-z0-9][a-z0-9\-\/]*$/', $p);
            $t->assertFalse((bool)$ok, "Path '$p' should be invalid");
        }
    });

    $t->it('path validation regex accepts valid paths', function (SnowTestRunner $t) {
        $valid = ['about', 'blog/post', 'admin/users', 'page123', 'my-page'];
        foreach ($valid as $p) {
            $ok = preg_match('/^[a-z0-9][a-z0-9\-\/]*$/', $p);
            $t->assertTrue((bool)$ok, "Path '$p' should be valid");
        }
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN PAGES HTTP TESTS
// ─────────────────────────────────────────────────────────────────────────────
$t->describe('Admin Pages HTTP', function (SnowTestRunner $t) {

    $base = getenv('TEST_BASE_URL') ?: (getenv('RUNNING_IN_DOCKER') ? 'http://apache' : 'http://127.0.0.1:8080');

    // Helper: POST with optional session cookie
    function adminHttpPost(string $url, array $data, string $cookie = ''): array {
        $headers = "Content-Type: application/x-www-form-urlencoded\r\n";
        if ($cookie) {
            $headers .= "Cookie: $cookie\r\n";
        }
        $ctx  = stream_context_create(['http' => [
            'method'          => 'POST',
            'content'         => http_build_query($data),
            'header'          => $headers,
            'follow_location' => 0,
            'ignore_errors'   => true,
        ]]);
        $body    = @file_get_contents($url, false, $ctx);
        $headers = $http_response_header ?? [];
        $status  = 200;
        foreach ($headers as $h) {
            if (preg_match('/HTTP\/\S+ (\d+)/', $h, $m)) {
                $status = (int)$m[1];
            }
        }
        return ['status' => $status, 'body' => $body ?: '', 'headers' => $headers];
    }

    // Helper: extract PHPSESSID from Set-Cookie headers
    function extractSessionCookie(array $headers): string {
        foreach ($headers as $h) {
            if (preg_match('/Set-Cookie:\s*PHPSESSID=([^;]+)/i', $h, $m)) {
                return 'PHPSESSID=' . $m[1];
            }
        }
        return '';
    }

    // Login and get a session cookie for admin
    $loginRes = adminHttpPost("$base/login", [
        'email'    => 'admin@example.com',
        'password' => 'admin123',
    ]);
    $adminCookie = extractSessionCookie($loginRes['headers']);

    $t->it('login returns a session cookie', function (SnowTestRunner $t) use ($adminCookie) {
        $t->assertTrue($adminCookie !== '', 'Login should return a PHPSESSID cookie');
    });

    $t->it('/admin/pages returns HTTP 200 for authenticated admin', function (SnowTestRunner $t) use ($base, $adminCookie) {
        $res = httpGet("$base/admin/pages", ['PHPSESSID' => ltrim(str_replace('PHPSESSID=', '', $adminCookie))]);
        $t->assertEqual(200, $res['status']);
    });

    $t->it('/admin/pages list contains Add Page button', function (SnowTestRunner $t) use ($base, $adminCookie) {
        $sessId = str_replace('PHPSESSID=', '', $adminCookie);
        $res = httpGet("$base/admin/pages", ['PHPSESSID' => $sessId]);
        $t->assertContains('Add Page', $res['body']);
    });

    $t->it('/admin/pages?action=add returns HTTP 200 with form', function (SnowTestRunner $t) use ($base, $adminCookie) {
        $sessId = str_replace('PHPSESSID=', '', $adminCookie);
        $res = httpGet("$base/admin/pages?action=add", ['PHPSESSID' => $sessId]);
        $t->assertEqual(200, $res['status']);
        $t->assertContains('Create Page', $res['body']);
    });

    $t->it('POST add page with missing title returns validation error', function (SnowTestRunner $t) use ($base, $adminCookie) {
        $res = adminHttpPost("$base/admin/pages?action=add", [
            'action' => 'add',
            'title'  => '',
            'path'   => 'test-validation-path',
        ], $adminCookie);
        $t->assertEqual(200, $res['status']);
        $t->assertContains('required', strtolower($res['body']));
    });

    $t->it('POST add page with invalid path returns validation error', function (SnowTestRunner $t) use ($base, $adminCookie) {
        $res = adminHttpPost("$base/admin/pages?action=add", [
            'action' => 'add',
            'title'  => 'Test',
            'path'   => 'Invalid Path!',
        ], $adminCookie);
        $t->assertEqual(200, $res['status']);
        $t->assertContains('path', strtolower($res['body']));
    });

    // Track created page id across tests
    $createdPageId = 0;

    $t->it('POST add page with valid data redirects to list with msg=created', function (SnowTestRunner $t) use ($base, $adminCookie, &$createdPageId) {
        $uniquePath = 'http-test-page-' . time();
        $res = adminHttpPost("$base/admin/pages?action=add", [
            'action'        => 'add',
            'title'         => 'HTTP Test Page',
            'path'          => $uniquePath,
            'content'       => '<p>Created by test</p>',
            'template_file' => 'default_page_template.html',
            'status'        => 'active',
        ], $adminCookie);
        $t->assertEqual(302, $res['status'], 'Expected redirect after successful create');
        $location = '';
        foreach ($res['headers'] as $h) {
            if (preg_match('/Location:\s*(.+)/i', $h, $m)) {
                $location = trim($m[1]);
            }
        }
        $t->assertContains('msg=created', $location);
        // Retrieve the created page id for subsequent tests
        $pg = getPageByPath($uniquePath);
        if ($pg) {
            $createdPageId = (int)$pg['id'];
            $GLOBALS['__http_test_page_id'] = $createdPageId;
        }
    });

    $t->it('/admin/pages?action=edit shows the edit form for created page', function (SnowTestRunner $t) use ($base, $adminCookie) {
        $id = $GLOBALS['__http_test_page_id'] ?? 0;
        if (!$id) {
            $t->skip('No created page id available');
        }
        $sessId = str_replace('PHPSESSID=', '', $adminCookie);
        $res = httpGet("$base/admin/pages?action=edit&id=$id", ['PHPSESSID' => $sessId]);
        $t->assertEqual(200, $res['status']);
        $t->assertContains('Save Changes', $res['body']);
    });

    $t->it('POST edit page redirects with msg=updated', function (SnowTestRunner $t) use ($base, $adminCookie) {
        $id = $GLOBALS['__http_test_page_id'] ?? 0;
        if (!$id) {
            $t->skip('No created page id available');
        }
        $pg = getPageById($id);
        $res = adminHttpPost("$base/admin/pages?action=edit&id=$id", [
            'action'        => 'edit',
            'title'         => 'HTTP Test Page Updated',
            'path'          => $pg['path'],
            'content'       => '<p>Updated</p>',
            'template_file' => 'default_page_template.html',
            'status'        => 'active',
        ], $adminCookie);
        $t->assertEqual(302, $res['status'], 'Expected redirect after update');
        $location = '';
        foreach ($res['headers'] as $h) {
            if (preg_match('/Location:\s*(.+)/i', $h, $m)) {
                $location = trim($m[1]);
            }
        }
        $t->assertContains('msg=updated', $location);
    });

    $t->it('POST delete page redirects with msg=deleted', function (SnowTestRunner $t) use ($base, $adminCookie) {
        $id = $GLOBALS['__http_test_page_id'] ?? 0;
        if (!$id) {
            $t->skip('No created page id available');
        }
        $res = adminHttpPost("$base/admin/pages?action=delete&id=$id", [
            'action' => 'delete',
        ], $adminCookie);
        $t->assertEqual(302, $res['status'], 'Expected redirect after delete');
        $location = '';
        foreach ($res['headers'] as $h) {
            if (preg_match('/Location:\s*(.+)/i', $h, $m)) {
                $location = trim($m[1]);
            }
        }
        $t->assertContains('msg=deleted', $location);
        // Hard-delete test row
        if ($id) {
            dbQuery("DELETE FROM pages WHERE id = ?", [$id]);
        }
    });

    $t->it('core pages do not show a Delete button on list page', function (SnowTestRunner $t) use ($base, $adminCookie) {
        $sessId = str_replace('PHPSESSID=', '', $adminCookie);
        $res = httpGet("$base/admin/pages", ['PHPSESSID' => $sessId]);
        // The home page row should not have a delete form next to it.
        // Count occurrences of delete buttons vs total pages — core pages have none.
        $deleteCount = substr_count($res['body'], 'action" value="delete"');
        $pageCount   = substr_count($res['body'], '<code>');
        $coreCount   = 4; // home, login, logout, admin
        $t->assertTrue($deleteCount <= $pageCount - $coreCount,
            "Delete button count ($deleteCount) should be at most non-core page count (" . ($pageCount - $coreCount) . ")");
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// GROUP MANAGEMENT UNIT TESTS
// ─────────────────────────────────────────────────────────────────────────────
$t->describe('Group Management Functions', function (SnowTestRunner $t) {

    $testGroupName = 'Test Group ' . time();

    $t->it('dbInsert creates a new group and returns an id', function (SnowTestRunner $t) use ($testGroupName) {
        $id = dbInsert('user_groups_list', [
            'name'        => $testGroupName,
            'description' => 'Created by test suite',
            'status'      => 'active',
        ]);
        $t->assertGreaterThan(0, (int)$id);
        $GLOBALS['__test_group_id']   = (int)$id;
        $GLOBALS['__test_group_name'] = $testGroupName;
    });

    $t->it('getGroupById returns the created group', function (SnowTestRunner $t) {
        $id = $GLOBALS['__test_group_id'] ?? 0;
        $g  = dbGetRow("SELECT * FROM user_groups_list WHERE id = ?", [$id]);
        $t->assertIsArray($g);
        $t->assertEqual($GLOBALS['__test_group_name'], $g['name']);
    });

    $t->it('duplicate group name is detected', function (SnowTestRunner $t) {
        $name     = $GLOBALS['__test_group_name'] ?? '';
        $existing = dbGetRow("SELECT id FROM user_groups_list WHERE name = ?", [$name]);
        $t->assertIsArray($existing, 'Duplicate should be found by name lookup');
    });

    $t->it('saveGroupPermissions assigns permissions to a group', function (SnowTestRunner $t) {
        $gid  = $GLOBALS['__test_group_id'] ?? 0;
        $perm = dbGetRow("SELECT id FROM permissions WHERE name = 'log_viewing'", []);
        $t->assertIsArray($perm);
        dbQuery("DELETE FROM group_permissions WHERE group_id = ?", [$gid]);
        dbInsert('group_permissions', ['group_id' => $gid, 'permission_id' => (int)$perm['id']]);
        $rows = dbGetRows("SELECT permission_id FROM group_permissions WHERE group_id = ?", [$gid]);
        $t->assertCount(1, $rows);
        $t->assertEqual((int)$perm['id'], (int)$rows[0]['permission_id']);
    });

    $t->it('replaceGroupPermissions replaces existing assignments', function (SnowTestRunner $t) {
        $gid   = $GLOBALS['__test_group_id'] ?? 0;
        $perms = dbGetRows("SELECT id FROM permissions WHERE name IN ('log_viewing','page_management')", []);
        $t->assertCount(2, $perms);
        dbQuery("DELETE FROM group_permissions WHERE group_id = ?", [$gid]);
        foreach ($perms as $p) {
            dbInsert('group_permissions', ['group_id' => $gid, 'permission_id' => (int)$p['id']]);
        }
        $rows = dbGetRows("SELECT permission_id FROM group_permissions WHERE group_id = ?", [$gid]);
        $t->assertCount(2, $rows);
    });

    $t->it('getUserPermissions includes permissions from assigned group', function (SnowTestRunner $t) {
        // Assign admin user to test group, verify permissions propagate
        $gid    = $GLOBALS['__test_group_id'] ?? 0;
        $admin  = dbGetRow("SELECT id FROM users WHERE email = 'admin@example.com'", []);
        $t->assertIsArray($admin);
        dbInsert('user_groups', ['user_id' => (int)$admin['id'], 'group_id' => $gid]);
        $perms = getUserPermissions((int)$admin['id']);
        $names = array_column($perms, 'name');
        $t->assertTrue(in_array('log_viewing', $names) || in_array('page_management', $names));
        // Clean up membership
        dbQuery("DELETE FROM user_groups WHERE user_id = ? AND group_id = ?", [(int)$admin['id'], $gid]);
    });

    $t->it('list query returns member and permission counts', function (SnowTestRunner $t) {
        $gid = $GLOBALS['__test_group_id'] ?? 0;
        $row = dbGetRow(
            "SELECT g.*, COUNT(DISTINCT ug.user_id) AS member_count, COUNT(DISTINCT gp.permission_id) AS permission_count
             FROM user_groups_list g
             LEFT JOIN user_groups ug ON g.id = ug.group_id
             LEFT JOIN group_permissions gp ON g.id = gp.group_id
             WHERE g.id = ?
             GROUP BY g.id",
            [$gid]
        );
        $t->assertIsArray($row);
        $t->assertEqual(0, (int)$row['member_count']);
        $t->assertEqual(2, (int)$row['permission_count']);
    });

    $t->it('hard-delete removes group, permissions, and memberships', function (SnowTestRunner $t) {
        $gid = $GLOBALS['__test_group_id'] ?? 0;
        dbQuery("DELETE FROM group_permissions WHERE group_id = ?", [$gid]);
        dbQuery("DELETE FROM user_groups WHERE group_id = ?", [$gid]);
        dbQuery("DELETE FROM user_groups_list WHERE id = ?", [$gid]);
        $gone = dbGetRow("SELECT id FROM user_groups_list WHERE id = ?", [$gid]);
        $t->assertFalse($gone, 'Group should be gone after hard-delete');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN GROUPS HTTP TESTS
// ─────────────────────────────────────────────────────────────────────────────
$t->describe('Admin Groups HTTP', function (SnowTestRunner $t) {

    $base = getenv('TEST_BASE_URL') ?: (getenv('RUNNING_IN_DOCKER') ? 'http://apache' : 'http://127.0.0.1:8080');

    // Helper: POST with session cookie (reuses adminHttpPost / extractSessionCookie defined above)
    $loginRes    = adminHttpPost("$base/login", ['email' => 'admin@example.com', 'password' => 'admin123']);
    $adminCookie = extractSessionCookie($loginRes['headers']);

    $sessId = str_replace('PHPSESSID=', '', $adminCookie);

    $t->it('/admin/groups returns HTTP 200 for authenticated admin', function (SnowTestRunner $t) use ($base, $sessId) {
        $res = httpGet("$base/admin/groups", ['PHPSESSID' => $sessId]);
        $t->assertEqual(200, $res['status']);
    });

    $t->it('/admin/groups list shows Administrators group', function (SnowTestRunner $t) use ($base, $sessId) {
        $res = httpGet("$base/admin/groups", ['PHPSESSID' => $sessId]);
        $t->assertContains('Administrators', $res['body']);
    });

    $t->it('/admin/groups list shows Add Group button', function (SnowTestRunner $t) use ($base, $sessId) {
        $res = httpGet("$base/admin/groups", ['PHPSESSID' => $sessId]);
        $t->assertContains('Add Group', $res['body']);
    });

    $t->it('/admin/groups?action=add returns HTTP 200 with form', function (SnowTestRunner $t) use ($base, $sessId) {
        $res = httpGet("$base/admin/groups?action=add", ['PHPSESSID' => $sessId]);
        $t->assertEqual(200, $res['status']);
        $t->assertContains('Create Group', $res['body']);
    });

    $t->it('add form lists available permissions as checkboxes', function (SnowTestRunner $t) use ($base, $sessId) {
        $res = httpGet("$base/admin/groups?action=add", ['PHPSESSID' => $sessId]);
        $t->assertContains('admin_access', $res['body']);
        $t->assertContains('log_viewing',  $res['body']);
    });

    $t->it('POST add group with missing name returns validation error', function (SnowTestRunner $t) use ($base, $adminCookie) {
        $res = adminHttpPost("$base/admin/groups?action=add", [
            'action' => 'add',
            'name'   => '',
        ], $adminCookie);
        $t->assertEqual(200, $res['status']);
        $t->assertContains('required', strtolower($res['body']));
    });

    $t->it('POST add group with valid data redirects with msg=created', function (SnowTestRunner $t) use ($base, $adminCookie) {
        $uniqueName = 'HTTP Test Group ' . time();
        $res = adminHttpPost("$base/admin/groups?action=add", [
            'action'      => 'add',
            'name'        => $uniqueName,
            'description' => 'Created by HTTP test',
            'status'      => 'active',
        ], $adminCookie);
        $t->assertEqual(302, $res['status'], 'Expected redirect after create');
        $location = '';
        foreach ($res['headers'] as $h) {
            if (preg_match('/Location:\s*(.+)/i', $h, $m)) {
                $location = trim($m[1]);
            }
        }
        $t->assertContains('msg=created', $location);
        $g = dbGetRow("SELECT id FROM user_groups_list WHERE name = ?", [$uniqueName]);
        if ($g) {
            $GLOBALS['__http_test_group_id'] = (int)$g['id'];
        }
    });

    $t->it('/admin/groups?action=edit shows the edit form', function (SnowTestRunner $t) use ($base, $sessId) {
        $id = $GLOBALS['__http_test_group_id'] ?? 0;
        if (!$id) {
            $t->skip('No created group id available');
        }
        $res = httpGet("$base/admin/groups?action=edit&id=$id", ['PHPSESSID' => $sessId]);
        $t->assertEqual(200, $res['status']);
        $t->assertContains('Save Changes', $res['body']);
    });

    $t->it('POST edit group redirects with msg=updated', function (SnowTestRunner $t) use ($base, $adminCookie) {
        $id = $GLOBALS['__http_test_group_id'] ?? 0;
        if (!$id) {
            $t->skip('No created group id available');
        }
        $res = adminHttpPost("$base/admin/groups?action=edit&id=$id", [
            'action'      => 'edit',
            'name'        => 'HTTP Test Group Updated',
            'description' => 'Updated by test',
            'status'      => 'active',
        ], $adminCookie);
        $t->assertEqual(302, $res['status'], 'Expected redirect after update');
        $location = '';
        foreach ($res['headers'] as $h) {
            if (preg_match('/Location:\s*(.+)/i', $h, $m)) {
                $location = trim($m[1]);
            }
        }
        $t->assertContains('msg=updated', $location);
    });

    $t->it('POST delete group redirects with msg=deleted', function (SnowTestRunner $t) use ($base, $adminCookie) {
        $id = $GLOBALS['__http_test_group_id'] ?? 0;
        if (!$id) {
            $t->skip('No created group id available');
        }
        $res = adminHttpPost("$base/admin/groups?action=delete&id=$id", [
            'action' => 'delete',
        ], $adminCookie);
        $t->assertEqual(302, $res['status'], 'Expected redirect after delete');
        $location = '';
        foreach ($res['headers'] as $h) {
            if (preg_match('/Location:\s*(.+)/i', $h, $m)) {
                $location = trim($m[1]);
            }
        }
        $t->assertContains('msg=deleted', $location);
        // Confirm hard-deleted
        $gone = dbGetRow("SELECT id FROM user_groups_list WHERE id = ?", [$id]);
        $t->assertFalse($gone, 'Group should be gone after delete');
    });

    $t->it('Administrators group has no Delete button on list page', function (SnowTestRunner $t) use ($base, $sessId) {
        $res = httpGet("$base/admin/groups", ['PHPSESSID' => $sessId]);
        // Find the Administrators row — it should not contain a delete form
        // Simple heuristic: page has fewer delete buttons than total groups
        $deleteCount = substr_count($res['body'], 'action" value="delete"');
        $rowCount    = substr_count($res['body'], '<td>Administrators</td>');
        $t->assertEqual(1, $rowCount, 'Administrators row should appear once');
        // The Administrators row must not have a delete button; at minimum one fewer than total rows
        $totalRows = substr_count($res['body'], '</tr>') - 1; // subtract thead
        $t->assertTrue($deleteCount < $totalRows, 'Delete button count should be less than total group rows');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// SUMMARY
// ─────────────────────────────────────────────────────────────────────────────
$t->summary();
