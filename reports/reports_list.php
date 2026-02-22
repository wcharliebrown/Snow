<?php
class ReportsListReport {
    public function name() {
        return 'reports_list';
    }
    public function description() {
        return 'All reports â€” matches the admin/reports list view';
    }
    public function sql_table() {
        return 'report_templates';
    }
    public function sql_fields() {
        return 'id,
    name,
    CASE WHEN description IS NOT NULL AND description != \'\'
        THEN CONCAT(\'<strong>\', name, \'</strong><br><small class="text-muted">\', description, \'</small>\')
        ELSE CONCAT(\'<strong>\', name, \'</strong>\')
    END AS name_display,
    CONCAT(\'<code>\', REPLACE(REPLACE(sql_table, \'<\', \'&lt;\'), \'>\', \'&gt;\'), \'</code>\') AS table_display,
    UPPER(output_format) AS format_display,
    CASE status WHEN \'active\' THEN \'<span class="badge bg-success">active</span>\' ELSE \'<span class="badge bg-secondary">inactive</span>\' END AS status_badge';
    }
    public function sql_where() {
        return 'status != \'deleted\'';
    }
    public function sql_order() {
        return 'name';
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
<tr><th>Name</th><th>Table</th><th>Format</th><th>Status</th><th>Actions</th></tr>
</thead>
<tbody>';
    }
    public function html_row_template() {
        return '<tr><td>{{name_display}}</td><td>{{table_display}}</td><td>{{format_display}}</td><td>{{status_badge}}</td><td><a href="/admin/reports?action=run&amp;id={{id}}" class="btn btn-info btn-sm">Run</a> <a href="/admin/reports?action=edit&amp;id={{id}}" class="btn btn-primary btn-sm">Edit</a></td></tr>';
    }
    public function html_footer() {
        return '</tbody></table>';
    }
}
