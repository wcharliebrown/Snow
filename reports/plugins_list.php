<?php
class PluginsListReport {
    public function name() {
        return 'plugins_list';
    }
    public function description() {
        return 'All plugins â€” matches the admin/plugins list view';
    }
    public function sql_table() {
        return 'plugins';
    }
    public function sql_fields() {
        return 'id,
    CASE WHEN description IS NOT NULL AND description != \'\'
        THEN CONCAT(\'<strong>\', name, \'</strong><br><small class="text-muted">\', description, \'</small>\')
        ELSE CONCAT(\'<strong>\', name, \'</strong>\')
    END AS name_display,
    version,
    COALESCE(author, \'\') AS author,
    CASE status WHEN \'active\' THEN \'<span class="badge bg-success">active</span>\' WHEN \'error\' THEN \'<span class="badge bg-danger">error</span>\' ELSE \'<span class="badge bg-secondary">inactive</span>\' END AS status_badge,
    IFNULL(install_date, \'<span class="text-muted">Never</span>\') AS install_date_display';
    }
    public function sql_where() {
        return NULL;
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
<tr><th>Name</th><th>Version</th><th>Author</th><th>Status</th><th>Installed</th></tr>
</thead>
<tbody>';
    }
    public function html_row_template() {
        return '<tr><td>{{name_display}}</td><td>{{version}}</td><td>{{author}}</td><td>{{status_badge}}</td><td>{{install_date_display}}</td></tr>';
    }
    public function html_footer() {
        return '</tbody></table>';
    }
}
