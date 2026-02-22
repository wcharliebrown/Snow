<?php
class PagesListReport {
    public function name() {
        return 'pages_list';
    }
    public function description() {
        return 'All pages — matches the admin/pages list view';
    }
    public function sql_table() {
        return 'pages p LEFT JOIN page_templates t ON p.template_file = t.filename';
    }
    public function sql_fields() {
        return 'p.id,
    p.title,
    p.path,
    COALESCE(t.name, p.template_file) AS template_name,
    CASE WHEN p.require_auth = 1 THEN \'<span class="badge bg-warning">Yes</span>\' ELSE \'<span class="badge bg-secondary">No</span>\' END AS auth_badge,
    CASE p.status WHEN \'active\' THEN \'<span class="badge bg-success">active</span>\' WHEN \'inactive\' THEN \'<span class="badge bg-secondary">inactive</span>\' ELSE \'<span class="badge bg-secondary">other</span>\' END AS status_badge';
    }
    public function sql_where() {
        return 'p.status \\!= \'deleted\'';
    }
    public function sql_order() {
        return 'p.sort_order, p.title';
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
<tr><th>ID</th><th>Title</th><th>Path</th><th>Template</th><th>Auth</th><th>Status</th><th>Actions</th></tr>
</thead>
<tbody>';
    }
    public function html_row_template() {
        return '<tr><td>{{id}}</td><td>{{title}}</td><td><code>{{path}}</code></td><td>{{template_name}}</td><td>{{auth_badge}}</td><td>{{status_badge}}</td><td><a href="/admin/pages?action=edit&amp;id={{id}}" class="btn btn-primary btn-sm">Edit</a></td></tr>';
    }
    public function html_footer() {
        return '</tbody></table>';
    }
}
