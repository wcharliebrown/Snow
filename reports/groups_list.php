<?php
class GroupsListReport {
    public function name() {
        return 'groups_list';
    }
    public function description() {
        return 'All groups â€” matches the admin/groups list view';
    }
    public function sql_table() {
        return '(SELECT g.id, g.name, g.description, g.status,
        COUNT(DISTINCT ug.user_id)       AS member_count,
        COUNT(DISTINCT gp.permission_id) AS permission_count
      FROM user_groups_list g
      LEFT JOIN user_groups ug       ON g.id = ug.group_id
      LEFT JOIN group_permissions gp ON g.id = gp.group_id
      GROUP BY g.id, g.name, g.description, g.status) AS grp';
    }
    public function sql_fields() {
        return 'id,
    name,
    COALESCE(description, \'\') AS description,
    CONCAT(\'<span class="badge bg-info text-dark">\', member_count, \'</span>\') AS member_badge,
    CONCAT(\'<span class="badge bg-secondary">\', permission_count, \'</span>\') AS permission_badge,
    CASE status WHEN \'active\' THEN \'<span class="badge bg-success">active</span>\' ELSE \'<span class="badge bg-secondary">inactive</span>\' END AS status_badge';
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
<tr><th>ID</th><th>Name</th><th>Description</th><th>Members</th><th>Permissions</th><th>Status</th><th>Actions</th></tr>
</thead>
<tbody>';
    }
    public function html_row_template() {
        return '<tr><td>{{id}}</td><td>{{name}}</td><td>{{description}}</td><td>{{member_badge}}</td><td>{{permission_badge}}</td><td>{{status_badge}}</td><td><a href="/admin/groups?action=edit&amp;id={{id}}" class="btn btn-primary btn-sm">Edit</a></td></tr>';
    }
    public function html_footer() {
        return '</tbody></table>';
    }
}
