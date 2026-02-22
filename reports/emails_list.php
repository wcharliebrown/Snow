<?php
class EmailsListReport {
    public function name() {
        return 'emails_list';
    }
    public function description() {
        return 'All email templates â€” matches the admin/emails list view';
    }
    public function sql_table() {
        return 'email_templates';
    }
    public function sql_fields() {
        return 'id,
    CASE WHEN description IS NOT NULL AND description != \'\'
        THEN CONCAT(\'<strong>\', name, \'</strong><br><small class="text-muted">\', description, \'</small>\')
        ELSE CONCAT(\'<strong>\', name, \'</strong>\')
    END AS name_display,
    subject,
    COALESCE(from_address, \'\') AS from_address,
    CASE allow_unsubscribe WHEN 1 THEN \'<span class="badge bg-info text-dark">Yes</span>\' ELSE \'<span class="badge bg-secondary">No</span>\' END AS unsub_badge,
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
<tr><th>Name</th><th>Subject</th><th>From</th><th>Unsubscribe</th><th>Status</th><th>Actions</th></tr>
</thead>
<tbody>';
    }
    public function html_row_template() {
        return '<tr><td>{{name_display}}</td><td>{{subject}}</td><td>{{from_address}}</td><td>{{unsub_badge}}</td><td>{{status_badge}}</td><td><a href="/admin/emails?action=edit&amp;id={{id}}" class="btn btn-primary btn-sm">Edit</a></td></tr>';
    }
    public function html_footer() {
        return '</tbody></table>';
    }
}
