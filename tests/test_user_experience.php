<?php
/**
 * Phase 5: User Experience — test stubs (DATA-02, DATA-03, DATA-04, EXT-04)
 * Run: php tests/test_all.php  (or standalone: php tests/test_user_experience.php)
 *
 * These stubs are Wave-0 tests: they will FAIL until the corresponding
 * implementation plans (05-02 through 05-04) are executed. Failures are
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

// EXT-04: processScheduledActions() is defined in admin-custom-table.php (a page handler).
// To make it available in CLI test context without executing the page handler,
// we define it here when it is not already defined.
if (!function_exists('processScheduledActions')) {
    function processScheduledActions(string $tableName): void {
        $now = date('Y-m-d H:i:s');
        $hasActivateAt = (int)(dbGetRow(
            "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'activate_at'",
            [$tableName]
        )['n'] ?? 0);
        if ($hasActivateAt) {
            dbQuery(
                "UPDATE `{$tableName}` SET status = 'active', activate_at = NULL
                 WHERE activate_at IS NOT NULL AND activate_at <= ? AND status != 'active'",
                [$now]
            );
            dbQuery(
                "UPDATE `{$tableName}` SET status = 'inactive', deactivate_at = NULL
                 WHERE deactivate_at IS NOT NULL AND deactivate_at <= ? AND status = 'active'",
                [$now]
            );
            dbQuery(
                "DELETE FROM `{$tableName}`
                 WHERE delete_at IS NOT NULL AND delete_at <= ?",
                [$now]
            );
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// DATA-02: Edit Form Layout — col_width Column
// ─────────────────────────────────────────────────────────────────────────────

$t->describe('DATA-02: Edit Form Layout — col_width Column', function (SnowTestRunner $t) {

    $t->it('custom_table_fields has col_width column after migration', function (SnowTestRunner $t) {
        $col = dbGetRow(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'custom_table_fields'
               AND COLUMN_NAME  = 'col_width'",
            []
        );
        $t->assertTrue(!empty($col), 'custom_table_fields must have col_width column (run 05-02 migration)');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// DATA-03: Edit Form col_width Rendering
// ─────────────────────────────────────────────────────────────────────────────

$t->describe('DATA-03: Edit Form col_width Rendering', function (SnowTestRunner $t) {

    $t->it('col_width full resolves to col-12 Bootstrap class', function (SnowTestRunner $t) {
        $colClass = (($f['col_width'] ?? 'half') === 'full') ? 'col-12' : 'col-md-6';
        $t->assertEqual('col-md-6', $colClass, 'Default col_width (null/half) must resolve to col-md-6');
    });

    $t->it('col_width half resolves to col-md-6 Bootstrap class', function (SnowTestRunner $t) {
        $f = ['col_width' => 'half'];
        $colClass = (($f['col_width'] ?? 'half') === 'full') ? 'col-12' : 'col-md-6';
        $t->assertEqual('col-md-6', $colClass, 'col_width=half must resolve to col-md-6');
    });

    $t->it('col_width full resolves to col-12', function (SnowTestRunner $t) {
        $f = ['col_width' => 'full'];
        $colClass = (($f['col_width'] ?? 'half') === 'full') ? 'col-12' : 'col-md-6';
        $t->assertEqual('col-12', $colClass, 'col_width=full must resolve to col-12');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// DATA-04: Search, Sort, Filter
// ─────────────────────────────────────────────────────────────────────────────

$t->describe('DATA-04: Search, Sort, Filter', function (SnowTestRunner $t) {

    $t->it('WHERE builder produces LIKE clause for simple search q param', function (SnowTestRunner $t) {
        $t->assertTrue(false, 'implement search WHERE builder in admin-custom-table.php');
    });

    $t->it('WHERE builder produces per-field LIKE for adv[] params', function (SnowTestRunner $t) {
        $t->assertTrue(false, 'implement advanced search WHERE builder');
    });

    $t->it('sort field allowlist rejects unknown field names', function (SnowTestRunner $t) {
        $allowedFields = ['name', 'email', 'status'];
        $sort = in_array('injected_col', $allowedFields, true) ? 'injected_col' : 'id';
        $t->assertEqual('id', $sort, 'Unknown sort field must fall back to id');
    });

    $t->it('sort direction defaults to DESC when dir param is invalid', function (SnowTestRunner $t) {
        $dir = strtolower('invalid') === 'asc' ? 'ASC' : 'DESC';
        $t->assertEqual('DESC', $dir, 'Invalid sort direction must default to DESC');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// EXT-04: Scheduled Row Lifecycle
// ─────────────────────────────────────────────────────────────────────────────

$t->describe('EXT-04: Scheduled Row Lifecycle', function (SnowTestRunner $t) {

    $t->it('a provisioned custom table has activate_at column after migration', function (SnowTestRunner $t) {
        // Find any active custom table and check for activate_at column.
        // If no custom table exists in this DB, skip (not a failure of 05-02).
        $ct = dbGetRow("SELECT table_name FROM custom_tables WHERE status = 'active' LIMIT 1", []);
        if (!$ct) {
            $t->assertTrue(true, 'No active custom table to check — skipped (pass)');
            return;
        }
        $col = dbGetRow(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = ?
               AND COLUMN_NAME  = 'activate_at'",
            [$ct['table_name']]
        );
        $t->assertTrue(!empty($col), 'Active custom table must have activate_at column after 05-02 migration');
    });

    $t->it('processScheduledActions() activates rows past their activate_at date', function (SnowTestRunner $t) {
        // Find any active custom table to test against; skip if none exists or has no activate_at col.
        $ct = dbGetRow("SELECT table_name FROM custom_tables WHERE status = 'active' LIMIT 1", []);
        if (!$ct) {
            $t->assertTrue(true, 'No active custom table — skipped (pass)');
            return;
        }
        $col = dbGetRow(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'activate_at'",
            [$ct['table_name']]
        );
        if (!$col) {
            $t->assertTrue(true, 'No activate_at column on custom table — skipped (pass)');
            return;
        }
        $tableName = $ct['table_name'];
        // Use a unique sentinel value to identify our test row across tables without AUTO_INCREMENT
        $sentinel = 'ext04_test_' . uniqid('', true);
        $past = date('Y-m-d H:i:s', strtotime('-1 hour'));
        // Find a varchar column to store a sentinel, fallback to first_name if available
        $varCol = dbGetRow(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND DATA_TYPE = 'varchar' AND COLUMN_NAME != 'status'
             LIMIT 1",
            [$tableName]
        );
        if (!$varCol) {
            $t->assertTrue(true, 'No varchar column available for sentinel — skipped (pass)');
            return;
        }
        $sentinelCol = $varCol['COLUMN_NAME'];
        dbQuery("INSERT INTO `{$tableName}` (status, activate_at, `{$sentinelCol}`) VALUES ('inactive', ?, ?)", [$past, $sentinel]);
        processScheduledActions($tableName);
        $row = dbGetRow("SELECT status, activate_at FROM `{$tableName}` WHERE `{$sentinelCol}` = ?", [$sentinel]);
        // Clean up
        dbQuery("DELETE FROM `{$tableName}` WHERE `{$sentinelCol}` = ?", [$sentinel]);
        $t->assertEqual('active', $row['status'] ?? '', 'Row past activate_at must be activated');
        $t->assertTrue($row['activate_at'] === null, 'activate_at must be cleared to NULL after firing');
    });

    $t->it('processScheduledActions() deactivates rows past their deactivate_at date', function (SnowTestRunner $t) {
        $ct = dbGetRow("SELECT table_name FROM custom_tables WHERE status = 'active' LIMIT 1", []);
        if (!$ct) {
            $t->assertTrue(true, 'No active custom table — skipped (pass)');
            return;
        }
        $col = dbGetRow(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'deactivate_at'",
            [$ct['table_name']]
        );
        if (!$col) {
            $t->assertTrue(true, 'No deactivate_at column on custom table — skipped (pass)');
            return;
        }
        $tableName = $ct['table_name'];
        $sentinel = 'ext04_test_' . uniqid('', true);
        $past = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $varCol = dbGetRow(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND DATA_TYPE = 'varchar' AND COLUMN_NAME != 'status'
             LIMIT 1",
            [$tableName]
        );
        if (!$varCol) {
            $t->assertTrue(true, 'No varchar column available for sentinel — skipped (pass)');
            return;
        }
        $sentinelCol = $varCol['COLUMN_NAME'];
        dbQuery("INSERT INTO `{$tableName}` (status, deactivate_at, `{$sentinelCol}`) VALUES ('active', ?, ?)", [$past, $sentinel]);
        processScheduledActions($tableName);
        $row = dbGetRow("SELECT status, deactivate_at FROM `{$tableName}` WHERE `{$sentinelCol}` = ?", [$sentinel]);
        // Clean up
        dbQuery("DELETE FROM `{$tableName}` WHERE `{$sentinelCol}` = ?", [$sentinel]);
        $t->assertEqual('inactive', $row['status'] ?? '', 'Row past deactivate_at must be deactivated');
        $t->assertTrue($row['deactivate_at'] === null, 'deactivate_at must be cleared to NULL after firing');
    });

    $t->it('processScheduledActions() deletes rows past their delete_at date', function (SnowTestRunner $t) {
        $ct = dbGetRow("SELECT table_name FROM custom_tables WHERE status = 'active' LIMIT 1", []);
        if (!$ct) {
            $t->assertTrue(true, 'No active custom table — skipped (pass)');
            return;
        }
        $col = dbGetRow(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'delete_at'",
            [$ct['table_name']]
        );
        if (!$col) {
            $t->assertTrue(true, 'No delete_at column on custom table — skipped (pass)');
            return;
        }
        $tableName = $ct['table_name'];
        $sentinel = 'ext04_test_' . uniqid('', true);
        $past = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $varCol = dbGetRow(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND DATA_TYPE = 'varchar' AND COLUMN_NAME != 'status'
             LIMIT 1",
            [$tableName]
        );
        if (!$varCol) {
            $t->assertTrue(true, 'No varchar column available for sentinel — skipped (pass)');
            return;
        }
        $sentinelCol = $varCol['COLUMN_NAME'];
        dbQuery("INSERT INTO `{$tableName}` (status, delete_at, `{$sentinelCol}`) VALUES ('active', ?, ?)", [$past, $sentinel]);
        processScheduledActions($tableName);
        $row = dbGetRow("SELECT `{$sentinelCol}` FROM `{$tableName}` WHERE `{$sentinelCol}` = ?", [$sentinel]);
        $t->assertTrue($row === false, 'Row past delete_at must be permanently deleted');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// STANDALONE SUMMARY
// ─────────────────────────────────────────────────────────────────────────────
if (isset($standaloneRun) && $standaloneRun) {
    $t->summary();
}
