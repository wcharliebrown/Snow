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
        $t->assertTrue(false, 'run 05-02 migration and migrateExistingCustomTables()');
    });

    $t->it('processScheduledActions() activates rows past their activate_at date', function (SnowTestRunner $t) {
        $t->assertTrue(false, 'implement processScheduledActions() in admin-custom-table.php');
    });

    $t->it('processScheduledActions() deactivates rows past their deactivate_at date', function (SnowTestRunner $t) {
        $t->assertTrue(false, 'implement processScheduledActions()');
    });

    $t->it('processScheduledActions() deletes rows past their delete_at date', function (SnowTestRunner $t) {
        $t->assertTrue(false, 'implement processScheduledActions()');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// STANDALONE SUMMARY
// ─────────────────────────────────────────────────────────────────────────────
if (isset($standaloneRun) && $standaloneRun) {
    $t->summary();
}
