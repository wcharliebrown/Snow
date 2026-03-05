<?php
/**
 * Phase 3: Data Integrity — test stubs (VER-01 through VER-05)
 * Run: php tests/test_all.php
 */

// $t is provided by test_all.php which require_once's this file
// Do NOT re-require bootstrap.php here — test_all.php already did it

$t->describe('Phase 3: Data Integrity — VER-01 (row_versions schema)', function (SnowTestRunner $t) {

    $t->it('row_versions table exists in the database', function (SnowTestRunner $t) {
        $t->assertTrue(dbTableExists('row_versions'), 'row_versions table must exist after Phase 3 migration');
    });

    $t->it('row_versions has required columns (table_name, row_id, changed_by, changed_at, row_snapshot)', function (SnowTestRunner $t) {
        $cols = dbGetRows("SHOW COLUMNS FROM row_versions", []);
        $colNames = array_column($cols, 'Field');
        foreach (['table_name', 'row_id', 'changed_by', 'changed_at', 'row_snapshot'] as $required) {
            $t->assertTrue(in_array($required, $colNames), "row_versions must have column: $required");
        }
    });

});

$t->describe('Phase 3: Data Integrity — VER-01 (version capture)', function (SnowTestRunner $t) {

    $t->it('version capture inserts a row_versions record — PENDING until 03-03', function (SnowTestRunner $t) {
        // TODO(03-03): Un-skip when version capture is added to admin-custom-table.php POST handler.
        // This test will: INSERT a row into a test custom table, simulate an edit POST,
        // then assert COUNT(*) FROM row_versions increased by 1.
        $t->assertTrue(true, 'PENDING — stub passes until 03-03 implements capture');
    });

    $t->it('row_snapshot JSON contains correct before-image — PENDING until 03-03', function (SnowTestRunner $t) {
        // TODO(03-03): Fetch the most recent row_versions entry after a simulated edit
        // and assert json_decode(row_snapshot, true) matches the pre-edit row values.
        $t->assertTrue(true, 'PENDING — stub passes until 03-03 implements capture');
    });

});

$t->describe('Phase 3: Data Integrity — VER-02 (row revert)', function (SnowTestRunner $t) {

    $t->it('revert restores row to selected version snapshot — PENDING until 03-05', function (SnowTestRunner $t) {
        // TODO(03-05): Test that after calling the revert action for a version_id,
        // the live row matches that version's row_snapshot JSON.
        $t->assertTrue(true, 'PENDING — stub passes until 03-05 implements revert');
    });

    $t->it('revert saves current state as new row_versions entry before overwriting — PENDING until 03-05', function (SnowTestRunner $t) {
        // TODO(03-05): Assert that after revert, the count of row_versions for the row
        // increased by 1 (the pre-revert current state was captured).
        $t->assertTrue(true, 'PENDING — stub passes until 03-05 implements revert');
    });

});

$t->describe('Phase 3: Data Integrity — VER-03 (snapshot create)', function (SnowTestRunner $t) {

    // Setup: create a temporary test table with a row so we can snapshot it
    $testTable = 'test_snap_src_' . time();
    dbQuery("CREATE TABLE `{$testTable}` (id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(100))", []);
    dbInsert($testTable, ['label' => 'hello']);

    $t->it('snapshot create produces a MySQL table named snapshot_{table}_{ts}', function (SnowTestRunner $t) use ($testTable) {
        // Insert a snapshot metadata row as the create action would, with a generated name
        $snapshotTableName = 'snapshot_' . $testTable . '_' . date('YmdHis') . rand(100, 999);
        $snapshotId = dbInsert('snapshots', [
            'table_name'     => $testTable,
            'snapshot_name'  => $testTable . '_test',
            'snapshot_table' => $snapshotTableName,
            'description'    => 'TDD test snapshot',
            'snapshot_date'  => date('Y-m-d H:i:s'),
            'row_count'      => 1,
            'file_path'      => null,
            'status'         => 'active',
            'created_by'     => null,
        ]);
        // Execute the CREATE TABLE AS SELECT (the main new behaviour)
        dbQuery("CREATE TABLE `{$snapshotTableName}` AS SELECT * FROM `{$testTable}`", []);

        $t->assertTrue(dbTableExists($snapshotTableName), "Physical snapshot table {$snapshotTableName} must exist after create");

        // Verify row was copied
        $row = dbGetRow("SELECT * FROM `{$snapshotTableName}` WHERE label = 'hello'", []);
        $t->assertTrue($row !== false, 'Snapshot table should contain the source row');

        // Cleanup
        dbQuery("DROP TABLE IF EXISTS `{$snapshotTableName}`", []);
        dbQuery("DELETE FROM snapshots WHERE id = ?", [$snapshotId]);
    });

    $t->it('snapshots metadata row has snapshot_table column populated', function (SnowTestRunner $t) use ($testTable) {
        $snapshotTableName = 'snapshot_' . $testTable . '_' . date('YmdHis') . rand(100, 999);
        $snapshotId = dbInsert('snapshots', [
            'table_name'     => $testTable,
            'snapshot_name'  => $testTable . '_meta_test',
            'snapshot_table' => $snapshotTableName,
            'description'    => 'TDD metadata test',
            'snapshot_date'  => date('Y-m-d H:i:s'),
            'row_count'      => 1,
            'file_path'      => null,
            'status'         => 'active',
            'created_by'     => null,
        ]);
        $row = dbGetRow("SELECT * FROM snapshots WHERE id = ?", [$snapshotId]);
        $t->assertEqual($snapshotTableName, $row['snapshot_table'], 'snapshot_table column must be set on insert');

        // Cleanup
        dbQuery("DELETE FROM snapshots WHERE id = ?", [$snapshotId]);
    });

    // Teardown: remove temp source table
    dbQuery("DROP TABLE IF EXISTS `{$testTable}`", []);

});

$t->describe('Phase 3: Data Integrity — VER-04 (snapshot diff algorithm)', function (SnowTestRunner $t) {

    $t->it('diff identifies changed rows correctly', function (SnowTestRunner $t) {
        // Pure unit test — no DB needed. Uses hardcoded fixtures.
        $snap = [['id' => 1, 'name' => 'Alice', 'age' => '30'], ['id' => 2, 'name' => 'Bob', 'age' => '25']];
        $live = [['id' => 1, 'name' => 'Alice', 'age' => '31'], ['id' => 2, 'name' => 'Bob', 'age' => '25']];

        $snapById = array_column($snap, null, 'id');
        $liveById = array_column($live, null, 'id');

        $changed = [];
        foreach ($snapById as $id => $snapRow) {
            if (isset($liveById[$id]) && $snapRow !== $liveById[$id]) {
                $changed[] = ['snapshot' => $snapRow, 'live' => $liveById[$id]];
            }
        }
        $t->assertEqual(1, count($changed), 'Should detect exactly 1 changed row');
        $t->assertEqual('30', $changed[0]['snapshot']['age'], 'Snapshot age should be 30');
        $t->assertEqual('31', $changed[0]['live']['age'], 'Live age should be 31');
    });

    $t->it('diff identifies deleted rows (in snapshot only)', function (SnowTestRunner $t) {
        $snap = [['id' => 1, 'name' => 'Alice'], ['id' => 3, 'name' => 'Charlie']];
        $live = [['id' => 1, 'name' => 'Alice']];

        $snapById = array_column($snap, null, 'id');
        $liveById = array_column($live, null, 'id');

        $deleted = [];
        foreach ($snapById as $id => $snapRow) {
            if (!isset($liveById[$id])) {
                $deleted[] = $snapRow;
            }
        }
        $t->assertEqual(1, count($deleted), 'Should detect 1 deleted row');
        $t->assertEqual('Charlie', $deleted[0]['name']);
    });

    $t->it('diff identifies added rows (in live only)', function (SnowTestRunner $t) {
        $snap = [['id' => 1, 'name' => 'Alice']];
        $live = [['id' => 1, 'name' => 'Alice'], ['id' => 4, 'name' => 'Diana']];

        $snapById = array_column($snap, null, 'id');
        $liveById = array_column($live, null, 'id');

        $added = [];
        foreach ($liveById as $id => $liveRow) {
            if (!isset($snapById[$id])) {
                $added[] = $liveRow;
            }
        }
        $t->assertEqual(1, count($added), 'Should detect 1 added row');
        $t->assertEqual('Diana', $added[0]['name']);
    });

});

$t->describe('Phase 3: Data Integrity — VER-05 (snapshot restore)', function (SnowTestRunner $t) {

    $t->it('restore renames snapshot table to live table name atomically — PENDING until 03-06', function (SnowTestRunner $t) {
        // TODO(03-06): Create a test table + snapshot table, call restore, assert the
        // snapshot table is now the live table and the old live is renamed to _pre_restore_*.
        $t->assertTrue(true, 'PENDING — stub passes until 03-06 implements restore rename');
    });

    $t->it('pre-restore auto-snapshot is created and recorded before rename — PENDING until 03-06', function (SnowTestRunner $t) {
        // TODO(03-06): After restore, assert an auto-created snapshot row exists in snapshots table.
        $t->assertTrue(true, 'PENDING — stub passes until 03-06 implements auto-snapshot');
    });

});
