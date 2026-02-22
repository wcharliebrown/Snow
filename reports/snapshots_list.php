<?php
class SnapshotsListReport {
    public function name() {
        return 'snapshots_list';
    }
    public function description() {
        return 'All snapshots â€” matches the admin/snapshots list view';
    }
    public function sql_table() {
        return 'snapshots s LEFT JOIN users u ON s.created_by = u.id';
    }
    public function sql_fields() {
        return 's.id,
    CONCAT(\'<code>\', s.table_name, \'</code>\') AS table_display,
    s.snapshot_name,
    COALESCE(s.description, \'\') AS description,
    s.snapshot_date,
    CONCAT(\'<span class="badge bg-secondary">\', FORMAT(s.row_count, 0), \'</span>\') AS row_badge,
    CASE WHEN u.first_name IS NOT NULL
        THEN CONCAT(u.first_name, \' \', u.last_name)
        ELSE \'<span class="text-muted">&mdash;</span>\'
    END AS created_by_display';
    }
    public function sql_where() {
        return 's.status = \'active\'';
    }
    public function sql_order() {
        return 's.snapshot_date DESC';
    }
    public function rows_per_page() {
        return 50;
    }
    public function output_format() {
        return 'html';
    }
    public function html_header() {
        return '<table class="table table-striped table-hover">
<thead class="table-dark">
<tr><th>Table</th><th>Snapshot Name</th><th>Description</th><th>Date</th><th>Rows</th><th>By</th></tr>
</thead>
<tbody>';
    }
    public function html_row_template() {
        return '<tr><td>{{table_display}}</td><td>{{snapshot_name}}</td><td>{{description}}</td><td>{{snapshot_date}}</td><td>{{row_badge}}</td><td>{{created_by_display}}</td></tr>';
    }
    public function html_footer() {
        return '</tbody></table>';
    }
}
