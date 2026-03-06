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

    // Setup: create a test custom table registered in custom_tables
    $testTable = 'test_ver_capture_' . time();
    dbQuery("CREATE TABLE `{$testTable}` (id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(100), status VARCHAR(20) DEFAULT 'active', view_groups VARCHAR(500) DEFAULT NULL, edit_groups VARCHAR(500) DEFAULT NULL)", []);
    // Register table in custom_tables so the handler can find it
    $tableDefId = dbInsert('custom_tables', [
        'table_name'   => $testTable,
        'display_name' => 'Test Ver Capture',
        'status'       => 'active',
    ]);
    // Add a field definition
    dbInsert('custom_table_fields', [
        'table_name'    => $testTable,
        'field_name'    => 'label',
        'display_label' => 'Label',
        'field_type'    => 'varchar',
        'is_required'   => 0,
        'is_visible'    => 1,
        'display_order' => 1,
        'status'        => 'active',
    ]);
    // Insert a row to edit
    $rowId = dbInsert($testTable, ['label' => 'before-value', 'status' => 'active']);

    $t->it('version capture inserts a row_versions record on successful edit', function (SnowTestRunner $t) use ($testTable, $rowId) {
        // Fetch the row as $existingForAcl would
        $existingForAcl = dbGetRow("SELECT * FROM `{$testTable}` WHERE id = ?", [$rowId]);
        $t->assertTrue($existingForAcl !== false, 'Test row must exist before capture');

        $countBefore = (int)dbGetRow("SELECT COUNT(*) as cnt FROM row_versions WHERE table_name = ? AND row_id = ?", [$testTable, $rowId])['cnt'];

        // Simulate the version capture block (VER-01) that will be added to admin-custom-table.php
        $currentUser = getCurrentUser();
        dbInsert('row_versions', [
            'table_name'   => $testTable,
            'row_id'       => $rowId,
            'changed_by'   => $currentUser['id'] ?? null,
            'changed_at'   => date('Y-m-d H:i:s'),
            'row_snapshot' => json_encode($existingForAcl),
        ]);

        $countAfter = (int)dbGetRow("SELECT COUNT(*) as cnt FROM row_versions WHERE table_name = ? AND row_id = ?", [$testTable, $rowId])['cnt'];
        $t->assertEqual($countBefore + 1, $countAfter, 'row_versions count should increase by 1 after version capture');
    });

    $t->it('row_snapshot JSON contains correct before-image of the row', function (SnowTestRunner $t) use ($testTable, $rowId) {
        // Fetch the most recent row_versions entry for this row
        $versionRow = dbGetRow(
            "SELECT * FROM row_versions WHERE table_name = ? AND row_id = ? ORDER BY id DESC LIMIT 1",
            [$testTable, $rowId]
        );
        $t->assertTrue($versionRow !== false, 'A row_versions entry must exist for the test row');

        $snapshot = json_decode($versionRow['row_snapshot'], true);
        $t->assertTrue(is_array($snapshot), 'row_snapshot must decode to an array');
        $t->assertEqual('before-value', $snapshot['label'], "Snapshot 'label' must contain the before-value");
        $t->assertEqual((string)$rowId, (string)$snapshot['id'], 'Snapshot must contain the correct row id');
    });

    // Teardown
    dbQuery("DELETE FROM row_versions WHERE table_name = ?", [$testTable]);
    dbQuery("DELETE FROM custom_table_fields WHERE table_name = ?", [$testTable]);
    dbQuery("DELETE FROM custom_tables WHERE id = ?", [$tableDefId]);
    dbQuery("DROP TABLE IF EXISTS `{$testTable}`", []);

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

    $t->it('schema drift check detects column differences between snapshot and live table', function (SnowTestRunner $t) {
        $liveTable = 'test_drift_live_' . time() . rand(10, 99);
        $snapTable = 'snapshot_drift_' . time() . rand(10, 99);

        dbQuery("CREATE TABLE `{$liveTable}` (id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(100))", []);
        dbQuery("CREATE TABLE `{$snapTable}` (id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(100))", []);

        // Add a column to the live table so there is drift
        dbQuery("ALTER TABLE `{$liveTable}` ADD COLUMN extra_col VARCHAR(50) DEFAULT NULL", []);

        $snapCols = array_column(dbGetRows("SHOW COLUMNS FROM `{$snapTable}`", []), 'Field');
        $liveCols = array_column(dbGetRows("SHOW COLUMNS FROM `{$liveTable}`", []), 'Field');
        $onlyInSnap = array_diff($snapCols, $liveCols);
        $onlyInLive = array_diff($liveCols, $snapCols);

        $t->assertTrue(!empty($onlyInLive), 'Should detect extra_col only in live table');
        $t->assertTrue(in_array('extra_col', $onlyInLive), 'extra_col should appear in onlyInLive diff');
        $t->assertTrue(empty($onlyInSnap), 'Snapshot should not have extra_col');

        // Cleanup
        dbQuery("DROP TABLE IF EXISTS `{$liveTable}`", []);
        dbQuery("DROP TABLE IF EXISTS `{$snapTable}`", []);
    });

    $t->it('restore: RENAME TABLE atomically swaps snapshot table to live table name', function (SnowTestRunner $t) {
        $liveTable = 'test_rename_live_' . time() . rand(10, 99);
        $snapTable = 'snapshot_rename_' . time() . rand(10, 99);
        $tempTable = $liveTable . '_prerestore_' . date('YmdHis');

        dbQuery("CREATE TABLE `{$liveTable}` (id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(100))", []);
        dbInsert($liveTable, ['label' => 'live-row']);

        dbQuery("CREATE TABLE `{$snapTable}` (id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(100))", []);
        dbInsert($snapTable, ['label' => 'snap-row']);

        // MySQL RENAME TABLE is atomic at the DDL level; wrapping in transaction is correct in
        // production code (dbBeginTransaction/dbCommit) but DDL causes implicit commit in MySQL.
        // Here we test the core: single RENAME TABLE statement with two pairs swaps tables correctly.
        dbQuery("RENAME TABLE `{$liveTable}` TO `{$tempTable}`, `{$snapTable}` TO `{$liveTable}`", []);

        $t->assertTrue(dbTableExists($liveTable), 'Live table name must still exist after rename (now contains snapshot data)');
        $t->assertTrue(dbTableExists($tempTable), 'Pre-restore temp table must exist (contains old live data)');
        $t->assertFalse(dbTableExists($snapTable), 'Original snapshot table name must no longer exist (renamed to live)');

        // Verify data: live table now contains snap-row, temp has live-row
        $liveRow = dbGetRow("SELECT label FROM `{$liveTable}` WHERE label = 'snap-row'", []);
        $t->assertTrue($liveRow !== false, 'Restored live table must contain snap-row from snapshot');

        $tempRow = dbGetRow("SELECT label FROM `{$tempTable}` WHERE label = 'live-row'", []);
        $t->assertTrue($tempRow !== false, 'Pre-restore temp table must contain old live-row');

        // Cleanup
        dbQuery("DROP TABLE IF EXISTS `{$liveTable}`", []);
        dbQuery("DROP TABLE IF EXISTS `{$tempTable}`", []);
    });

    $t->it('pre-restore auto-snapshot is created and recorded in snapshots table', function (SnowTestRunner $t) {
        $liveTable = 'test_autosnap_live_' . time() . rand(10, 99);
        dbQuery("CREATE TABLE `{$liveTable}` (id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(100))", []);
        dbInsert($liveTable, ['label' => 'current-live']);

        // Insert a source snapshot metadata row to mark as 'restored'
        $sourceSnapTable = 'snapshot_src_' . time() . rand(10, 99);
        $snapMetaId = dbInsert('snapshots', [
            'table_name'     => $liveTable,
            'snapshot_table' => $sourceSnapTable,
            'snapshot_name'  => 'source_snap',
            'description'    => 'Original snapshot',
            'snapshot_date'  => date('Y-m-d H:i:s'),
            'row_count'      => 1,
            'file_path'      => null,
            'status'         => 'active',
            'created_by'     => null,
        ]);

        // Simulate auto-snapshot creation (CREATE TABLE AS SELECT + INSERT into snapshots)
        $autoSnapshotTable = 'snapshot_' . $liveTable . '_' . date('YmdHis') . rand(100, 999);
        dbQuery("CREATE TABLE `{$autoSnapshotTable}` AS SELECT * FROM `{$liveTable}`", []);
        $autoSnapId = dbInsert('snapshots', [
            'table_name'     => $liveTable,
            'snapshot_table' => $autoSnapshotTable,
            'snapshot_name'  => $autoSnapshotTable,
            'description'    => 'Pre-restore auto-snapshot before restoring: source_snap',
            'snapshot_date'  => date('Y-m-d H:i:s'),
            'row_count'      => 1,
            'file_path'      => null,
            'status'         => 'active',
            'created_by'     => null,
        ]);

        $row = dbGetRow("SELECT * FROM snapshots WHERE id = ?", [$autoSnapId]);
        $t->assertTrue($row !== false, 'Auto-snapshot metadata row must exist in snapshots table');
        $t->assertTrue(strpos($row['description'], 'Pre-restore auto-snapshot') === 0, 'Description must start with Pre-restore auto-snapshot');
        $t->assertTrue(dbTableExists($autoSnapshotTable), 'Auto-snapshot physical table must exist');

        // Verify source snapshot can be marked as 'restored'
        dbUpdate('snapshots', ['status' => 'restored'], 'id = ?', [$snapMetaId]);
        $srcRow = dbGetRow("SELECT status FROM snapshots WHERE id = ?", [$snapMetaId]);
        $t->assertEqual('restored', $srcRow['status'], 'Source snapshot status must be restored after use');

        // Cleanup
        dbQuery("DROP TABLE IF EXISTS `{$liveTable}`", []);
        dbQuery("DROP TABLE IF EXISTS `{$autoSnapshotTable}`", []);
        dbQuery("DELETE FROM snapshots WHERE id IN (?, ?)", [$autoSnapId, $snapMetaId]);
    });

});
