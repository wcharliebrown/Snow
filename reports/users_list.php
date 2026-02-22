<?php
class UsersListReport {
    public function name() {
        return 'users_list';
    }
    public function description() {
        return 'All users â€” matches the admin/users list view';
    }
    public function sql_table() {
        return 'users';
    }
    public function sql_fields() {
        return 'id,
    CONCAT(first_name, \' \', last_name) AS full_name,
    email,
    CASE status WHEN \'active\' THEN \'<span class="badge bg-success">active</span>\' WHEN \'suspended\' THEN \'<span class="badge bg-warning">suspended</span>\' ELSE CONCAT(\'<span class="badge bg-secondary">\', status, \'</span>\') END AS status_badge,
    IFNULL(last_login, \'<span class="text-muted">Never</span>\') AS last_login_display';
    }
    public function sql_where() {
        return NULL;
    }
    public function sql_order() {
        return 'created_date DESC';
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
<tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Last Login</th><th>Actions</th></tr>
</thead>
<tbody>';
    }
    public function html_row_template() {
        return '<tr><td>{{id}}</td><td>{{full_name}}</td><td>{{email}}</td><td>{{status_badge}}</td><td>{{last_login_display}}</td><td><a href="/admin/users?action=edit&amp;id={{id}}" class="btn btn-primary btn-sm">Edit</a></td></tr>';
    }
    public function html_footer() {
        return '</tbody></table>';
    }
}
