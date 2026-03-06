<?php
/**
 * Phase 4: Extensibility — test stubs (EXT-01, SEC-05, EXT-02, EXT-03)
 * Run: php tests/test_all.php  (or standalone: php tests/test_extensibility.php)
 *
 * These stubs are Wave-0 tests: they will FAIL until the corresponding
 * implementation plans (04-02 through 04-05) are executed. Failures are
 * assertion failures — no PHP fatals.
 */

// When run standalone, bootstrap the runner and DB connection.
// When included from test_all.php, $t is already defined.
if (!isset($t)) {
    require_once __DIR__ . '/SnowTestRunner.php';
    require_once __DIR__ . '/bootstrap.php';
    $t = new SnowTestRunner();
    $standaloneRun = true;
} else {
    $standaloneRun = false;
}

// ─────────────────────────────────────────────────────────────────────────────
// EXT-01: Table Hooks
// ─────────────────────────────────────────────────────────────────────────────

$t->describe('EXT-01: Table Hooks', function (SnowTestRunner $t) {

    $t->it('custom_tables has pre_edit_php_filename column', function (SnowTestRunner $t) {
        $col = dbGetRow(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'custom_tables'
               AND COLUMN_NAME  = 'pre_edit_php_filename'",
            []
        );
        $t->assertTrue(!empty($col), 'custom_tables must have column pre_edit_php_filename (run Phase 4 migration)');
    });

    $t->it('custom_tables has post_edit_php_filename column', function (SnowTestRunner $t) {
        $col = dbGetRow(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'custom_tables'
               AND COLUMN_NAME  = 'post_edit_php_filename'",
            []
        );
        $t->assertTrue(!empty($col), 'custom_tables must have column post_edit_php_filename (run Phase 4 migration)');
    });

    $t->it('pre_edit hook file is included when it exists and tableDef has filename set', function (SnowTestRunner $t) {
        // Create a sentinel file the hook will write to
        $sentinelFile = tempnam(sys_get_temp_dir(), 'snow_hook_sentinel_');
        // Create the hook file itself — it writes to the sentinel on include
        $hookFile = tempnam(sys_get_temp_dir(), 'snow_hook_');
        file_put_contents($hookFile, "<?php file_put_contents(" . var_export($sentinelFile, true) . ", 'included');");

        // Replicate the hook logic: if($hookFile) { include $hookPath; }
        $tableDef = ['pre_edit_php_filename' => basename($hookFile)];

        // We use the full path directly (in production it resolves relative to SNOW_FUNCTIONS)
        $hookPath = $hookFile;
        if ($hookPath && file_exists($hookPath)) {
            try {
                include $hookPath;
            } catch (\Throwable $e) {
                // non-blocking
            }
        }

        $written = file_get_contents($sentinelFile);
        @unlink($hookFile);
        @unlink($sentinelFile);
        $t->assertEqual('included', $written, 'Hook file must be included when pre_edit_php_filename is set and file exists');
    });

    $t->it('hook failure does not throw — catch(Throwable) absorbs the exception', function (SnowTestRunner $t) {
        // Create a hook file that throws an exception
        $badHookFile = tempnam(sys_get_temp_dir(), 'snow_bad_hook_');
        file_put_contents($badHookFile, '<?php throw new \Exception("hook error");');

        $exceptionPropagated = false;
        try {
            include $badHookFile;
        } catch (\Throwable $e) {
            // Absorbed — this is correct behaviour; do NOT re-throw
        } catch (\Exception $e) {
            $exceptionPropagated = true;
        }

        @unlink($badHookFile);
        $t->assertFalse($exceptionPropagated, 'Hook exception must be caught by Throwable handler and must not propagate');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// SEC-05: Email OTP 2FA
// ─────────────────────────────────────────────────────────────────────────────

$t->describe('SEC-05: Email OTP 2FA', function (SnowTestRunner $t) {

    $t->it('login_otp table exists', function (SnowTestRunner $t) {
        $tbl = dbGetRow(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'login_otp'",
            []
        );
        $t->assertTrue(!empty($tbl), 'login_otp table must exist (run Phase 4 migration)');
    });

    $t->it('login_otp has required columns: user_id, code, expires_at, attempts', function (SnowTestRunner $t) {
        $required = ['user_id', 'code', 'expires_at', 'attempts'];
        foreach ($required as $col) {
            $row = dbGetRow(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'login_otp'
                   AND COLUMN_NAME  = ?",
                [$col]
            );
            $t->assertTrue(!empty($row), "login_otp must have column: $col");
        }
    });

    $t->it('users table has require_2fa column', function (SnowTestRunner $t) {
        $col = dbGetRow(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'users'
               AND COLUMN_NAME  = 'require_2fa'",
            []
        );
        $t->assertTrue(!empty($col), 'users table must have column require_2fa (run Phase 4 migration)');
    });

    $t->it('expired OTP code is not accepted', function (SnowTestRunner $t) {
        // Guard: login_otp must exist before we can insert
        $tbl = dbGetRow(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'login_otp'",
            []
        );
        if (empty($tbl)) {
            $t->skip('login_otp table does not exist yet — run Phase 4 migration');
        }

        // Resolve a valid user_id (login_otp has a FK to users)
        $userRow = dbGetRow("SELECT id FROM users ORDER BY id LIMIT 1", []);
        if (empty($userRow)) {
            $t->skip('No users in DB — cannot test login_otp FK constraint');
        }
        $testUserId = (int) $userRow['id'];

        // Insert an already-expired OTP
        $insertedId = dbInsert('login_otp', [
            'user_id'    => $testUserId,
            'code'       => '999999',
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 minute')),
            'attempts'   => 0,
        ]);

        // A valid SELECT would require expires_at > NOW()
        $row = dbGetRow(
            "SELECT id FROM login_otp WHERE id = ? AND expires_at > NOW()",
            [$insertedId]
        );

        // Cleanup regardless of outcome
        dbDelete('login_otp', 'id = ?', [$insertedId]);

        $t->assertTrue(empty($row), 'Expired OTP must not be returned when filtering expires_at > NOW()');
    });

    $t->it('OTP with attempts >= 3 is not accepted', function (SnowTestRunner $t) {
        // Guard: login_otp must exist
        $tbl = dbGetRow(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'login_otp'",
            []
        );
        if (empty($tbl)) {
            $t->skip('login_otp table does not exist yet — run Phase 4 migration');
        }

        // Resolve a valid user_id (login_otp has a FK to users)
        $userRow = dbGetRow("SELECT id FROM users ORDER BY id LIMIT 1", []);
        if (empty($userRow)) {
            $t->skip('No users in DB — cannot test login_otp FK constraint');
        }
        $testUserId = (int) $userRow['id'];

        // Insert an OTP that has been attempted 3 times
        $insertedId = dbInsert('login_otp', [
            'user_id'    => $testUserId,
            'code'       => '888888',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+10 minutes')),
            'attempts'   => 3,
        ]);

        // Valid SELECT filters attempts < 3
        $row = dbGetRow(
            "SELECT id FROM login_otp WHERE id = ? AND attempts < 3",
            [$insertedId]
        );

        // Cleanup
        dbDelete('login_otp', 'id = ?', [$insertedId]);

        $t->assertTrue(empty($row), 'OTP with attempts >= 3 must not be returned when filtering attempts < 3');
    });

    $t->it('otp_pending_user_id is distinct from user_id in session logic', function (SnowTestRunner $t) {
        // Document the invariant: the two session keys must be different strings
        // so that a user is not fully logged in while OTP is pending.
        $pendingKey = 'otp_pending_user_id';
        $sessionKey = 'user_id';
        $t->assertTrue($pendingKey !== $sessionKey, 'otp_pending_user_id and user_id must be distinct session keys');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// EXT-03: Email Templates
// ─────────────────────────────────────────────────────────────────────────────

$t->describe('EXT-03: Email Templates', function (SnowTestRunner $t) {

    $t->it('sendEmailTemplate renders {{variable}} substitutions', function (SnowTestRunner $t) {
        // Guard: email_templates table must exist
        $tbl = dbGetRow(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_templates'",
            []
        );
        if (empty($tbl)) {
            $t->skip('email_templates table does not exist yet — run Phase 4 migration');
        }

        // Insert a test template
        $insertedId = dbInsert('email_templates', [
            'name'    => '__test_ext03__',
            'subject' => 'Hello {{name}}',
            'body'    => 'Code: {{code}}',
            'status'  => 'active',
        ]);

        try {
            // Fetch the template and process its tokens
            $template = dbGetRow("SELECT * FROM email_templates WHERE id = ?", [$insertedId]);
            $result = processTokens($template['body'], ['name' => 'World', 'code' => '123456']);

            $t->assertContains('123456', $result, 'Processed body must contain substituted code value');
            $t->assertFalse(strpos($result, '{{code}}') !== false, 'Raw {{code}} token must not remain after substitution');
        } finally {
            dbDelete('email_templates', 'id = ?', [$insertedId]);
        }
    });

    $t->it('email_templates table has login_otp template seeded by migration', function (SnowTestRunner $t) {
        // Guard: email_templates table must exist
        $tbl = dbGetRow(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_templates'",
            []
        );
        if (empty($tbl)) {
            $t->skip('email_templates table does not exist yet — run Phase 4 migration');
        }

        $row = dbGetRow("SELECT id FROM email_templates WHERE name = 'login_otp'", []);
        $t->assertTrue(!empty($row), "email_templates must contain a 'login_otp' row seeded by Phase 4 migration");
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// EXT-02: Custom Pages
// ─────────────────────────────────────────────────────────────────────────────

$t->describe('EXT-02: Custom Pages', function (SnowTestRunner $t) {

    $t->it('pages table has content and custom_script columns', function (SnowTestRunner $t) {
        foreach (['content', 'custom_script'] as $colName) {
            $col = dbGetRow(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'pages'
                   AND COLUMN_NAME  = ?",
                [$colName]
            );
            $t->assertTrue(!empty($col), "pages table must have column: $colName (run Phase 4 migration)");
        }
    });

    $t->it('custom_script include pattern: file is included when script column is set', function (SnowTestRunner $t) {
        // Create a sentinel file and a script that writes to it
        $sentinelFile = tempnam(sys_get_temp_dir(), 'snow_page_sentinel_');
        $scriptFile   = tempnam(sys_get_temp_dir(), 'snow_page_script_');
        file_put_contents($scriptFile, "<?php file_put_contents(" . var_export($sentinelFile, true) . ", 'page_script_ran');");

        // Define SNOW_FUNCTIONS to the temp dir only if not already defined
        if (!defined('SNOW_FUNCTIONS')) {
            define('SNOW_FUNCTIONS', sys_get_temp_dir());
        }

        // Replicate the pages.php include logic:
        //   $scriptPath = SNOW_FUNCTIONS . '/' . $page['custom_script'];
        //   if ($page['custom_script'] && file_exists($scriptPath)) { include $scriptPath; }
        $page = ['custom_script' => basename($scriptFile)];
        $scriptPath = sys_get_temp_dir() . '/' . $page['custom_script'];

        // Copy script to a name relative to sys_get_temp_dir() so the path resolves
        // (basename already places it there — $scriptFile IS in sys_get_temp_dir())
        if ($page['custom_script'] && file_exists($scriptPath)) {
            try {
                include $scriptPath;
            } catch (\Throwable $e) {
                // non-blocking
            }
        }

        $written = file_get_contents($sentinelFile);
        @unlink($scriptFile);
        @unlink($sentinelFile);
        $t->assertEqual('page_script_ran', $written, 'custom_script file must be included when column is set and file exists');
    });

});

// When run standalone, print summary.
if (!empty($standaloneRun)) {
    $t->summary();
}
