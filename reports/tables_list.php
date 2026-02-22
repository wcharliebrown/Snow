<?php
class TablesListReport {
    public function name() {
        return 'tables_list';
    }
    public function description() {
        return 'All custom table definitions â€” matches the admin/tables list view';
    }
    public function sql_table() {
        return '(SELECT ct.id, ct.table_name, ct.display_name, ct.description, ct.status,
        COUNT(ctf.id) AS field_count
      FROM custom_tables ct
      LEFT JOIN custom_table_fields ctf ON ct.table_name = ctf.table_name AND ctf.status = \'active\'
      GROUP BY ct.id, ct.table_name, ct.display_name, ct.description, ct.status) AS ct';
    }
    public function sql_fields() {
        return 'id,
    CONCAT(\'<code>\', table_name, \'</code>\') AS table_display,
    display_name,
    COALESCE(description, \'\') AS description,
    CONCAT(\'<span class="badge bg-info text-dark">\', field_count, \'</span>\') AS field_badge,
    CASE status WHEN \'active\' THEN \'<span class="badge bg-success">active</span>\' ELSE \'<span class="badge bg-secondary">inactive</span>\' END AS status_badge';
    }
    public function sql_where() {
        return NULL;
    }
    public function sql_order() {
        return 'display_name';
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
<tr><th>Table Name</th><th>Display Name</th><th>Description</th><th>Fields</th><th>Status</th><th>Actions</th></tr>
</thead>
<tbody>';
    }
    public function html_row_template() {
        return '<tr><td>{{table_display}}</td><td>{{display_name}}</td><td>{{description}}</td><td>{{field_badge}}</td><td>{{status_badge}}</td><td><a href="/admin/tables?action=fields&amp;id={{id}}" class="btn btn-info btn-sm">Fields</a> <a href="/admin/tables?action=edit&amp;id={{id}}" class="btn btn-primary btn-sm">Edit</a></td></tr>';
    }
    public function html_footer() {
        return '</tbody></table>';
    }
}
