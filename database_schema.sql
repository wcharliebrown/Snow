-- Snow Framework Database Schema
-- This file creates all the necessary tables for the framework

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login DATETIME NULL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    reset_token VARCHAR(255) NULL,
    reset_token_expiry DATETIME NULL,
    email_subscriptions TINYINT(1) DEFAULT 1,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_created_date (created_date)
);

-- User Groups table
CREATE TABLE user_groups_list (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_status (status)
);

-- User Groups junction table
CREATE TABLE user_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    group_id INT NOT NULL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES user_groups_list(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_group (user_id, group_id),
    INDEX idx_user_id (user_id),
    INDEX idx_group_id (group_id)
);

-- Permissions table
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
);

-- Group Permissions junction table
CREATE TABLE group_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES user_groups_list(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_permission (group_id, permission_id),
    INDEX idx_group_id (group_id),
    INDEX idx_permission_id (permission_id)
);

-- Pages table
CREATE TABLE pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    path VARCHAR(255) NOT NULL UNIQUE,
    content LONGTEXT NULL,
    meta_description VARCHAR(500) NULL,
    meta_keywords VARCHAR(500) NULL,
    template_file VARCHAR(255) NOT NULL DEFAULT 'default_page_template.html',
    custom_script VARCHAR(255) NULL,
    require_auth TINYINT(1) DEFAULT 0,
    required_permission VARCHAR(100) NULL,
    status ENUM('active', 'inactive', 'deleted') DEFAULT 'active',
    parent_id INT NULL,
    sort_order INT DEFAULT 0,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    modified_by INT NULL,
    FOREIGN KEY (parent_id) REFERENCES pages(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (modified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_path (path),
    INDEX idx_status (status),
    INDEX idx_parent_id (parent_id),
    INDEX idx_sort_order (sort_order),
    INDEX idx_created_date (created_date)
);

-- Page Templates table
CREATE TABLE page_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    filename VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    status ENUM('active', 'inactive', 'deleted') DEFAULT 'active',
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_filename (filename),
    INDEX idx_status (status)
);

-- Report Templates table
CREATE TABLE report_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    sql_table TEXT NOT NULL,
    sql_fields TEXT DEFAULT NULL,
    sql_where TEXT NULL,
    sql_order VARCHAR(500) NULL,
    rows_per_page INT DEFAULT 20,
    output_format ENUM('html', 'csv') DEFAULT 'html',
    html_header TEXT NULL,
    html_row_template TEXT NOT NULL,
    html_footer TEXT NULL,
    status ENUM('active', 'inactive', 'deleted') DEFAULT 'active',
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    modified_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (modified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_name (name),
    INDEX idx_status (status),
    INDEX idx_created_date (created_date)
);

-- Email Templates table
CREATE TABLE email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    subject VARCHAR(255) NOT NULL,
    from_address VARCHAR(255) NULL,
    to_address VARCHAR(255) NULL,
    bcc VARCHAR(500) NULL,
    body TEXT NOT NULL,
    allow_unsubscribe TINYINT(1) DEFAULT 0,
    status ENUM('active', 'inactive', 'deleted') DEFAULT 'active',
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_status (status)
);

-- Unsubscribe Tokens table
CREATE TABLE unsubscribe_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    user_id INT NULL,
    template_name VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    status ENUM('active', 'unsubscribed', 'expired') DEFAULT 'active',
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATETIME NOT NULL,
    unsubscribed_date DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_status (status),
    INDEX idx_expiry_date (expiry_date)
);

-- Navigation table
CREATE TABLE navigation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_name VARCHAR(100) NOT NULL DEFAULT 'main',
    title VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL,
    parent_id INT NULL,
    sort_order INT DEFAULT 0,
    target VARCHAR(50) DEFAULT '_self',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES navigation(id) ON DELETE CASCADE,
    INDEX idx_menu_name (menu_name),
    INDEX idx_parent_id (parent_id),
    INDEX idx_sort_order (sort_order),
    INDEX idx_status (status)
);

-- Custom Tables registry
CREATE TABLE custom_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(255) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    custom_script_before_edit VARCHAR(255) NULL,
    custom_script_after_edit VARCHAR(255) NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_table_name (table_name),
    INDEX idx_status (status)
);

-- Custom Table Fields
CREATE TABLE custom_table_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(255) NOT NULL,
    field_name VARCHAR(255) NOT NULL,
    field_type VARCHAR(100) NOT NULL,
    display_label VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    is_visible TINYINT(1) DEFAULT 1,
    is_required TINYINT(1) DEFAULT 0,
    is_unique TINYINT(1) DEFAULT 0,
    select_options_sql TEXT NULL,
    validation_rules TEXT NULL,
    default_value VARCHAR(500) NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (table_name) REFERENCES custom_tables(table_name) ON DELETE CASCADE,
    INDEX idx_table_name (table_name),
    INDEX idx_field_name (field_name),
    INDEX idx_display_order (display_order),
    INDEX idx_status (status)
);

-- Encryption Keys
CREATE TABLE encryption_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    status ENUM('active', 'deleted') DEFAULT 'active',
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_status (status)
);

-- Table Encryption registry
CREATE TABLE table_encryption (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(255) NOT NULL,
    field_name VARCHAR(255) NOT NULL,
    key_name VARCHAR(255) NOT NULL,
    status ENUM('active', 'deleted') DEFAULT 'active',
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (key_name) REFERENCES encryption_keys(name) ON DELETE CASCADE,
    INDEX idx_table_name (table_name),
    INDEX idx_field_name (field_name),
    INDEX idx_key_name (key_name),
    INDEX idx_status (status)
);

-- Plugins table
CREATE TABLE plugins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    version VARCHAR(50) NOT NULL,
    description TEXT NULL,
    author VARCHAR(255) NULL,
    status ENUM('active', 'inactive', 'error') DEFAULT 'inactive',
    install_date DATETIME NULL,
    uninstall_date DATETIME NULL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_status (status),
    INDEX idx_install_date (install_date)
);

-- Plugin Files
CREATE TABLE plugin_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plugin_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type ENUM('php', 'html', 'css', 'js', 'sql', 'other') NOT NULL,
    original_content LONGTEXT NULL,
    status ENUM('active', 'deleted') DEFAULT 'active',
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plugin_id) REFERENCES plugins(id) ON DELETE CASCADE,
    INDEX idx_plugin_id (plugin_id),
    INDEX idx_file_path (file_path),
    INDEX idx_status (status)
);

-- Snapshots table
CREATE TABLE snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(255) NOT NULL,
    snapshot_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    snapshot_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    row_count INT DEFAULT 0,
    file_path VARCHAR(500) NULL,
    status ENUM('active', 'deleted') DEFAULT 'active',
    created_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_table_name (table_name),
    INDEX idx_snapshot_date (snapshot_date),
    INDEX idx_status (status)
);

-- Insert default permissions
INSERT INTO permissions (name, description) VALUES
('admin_access', 'Access to admin area'),
('user_management', 'Manage users'),
('group_management', 'Manage groups'),
('page_management', 'Manage pages'),
('template_management', 'Manage page templates'),
('report_management', 'Manage reports'),
('email_management', 'Manage email templates'),
('table_management', 'Manage custom tables'),
('plugin_management', 'Manage plugins'),
('snapshot_management', 'Manage database snapshots'),
('log_viewing', 'View system logs'),
('encryption_management', 'Manage encryption keys');

-- Insert default admin group
INSERT INTO user_groups_list (name, description) VALUES
('Administrators', 'Full system access'),
('Editors', 'Can manage pages and content'),
('Users', 'Basic authenticated access');

-- Assign all permissions to Administrators group
INSERT INTO group_permissions (group_id, permission_id)
SELECT g.id, p.id 
FROM user_groups_list g, permissions p 
WHERE g.name = 'Administrators';

-- Assign page management permissions to Editors group
INSERT INTO group_permissions (group_id, permission_id)
SELECT g.id, p.id 
FROM user_groups_list g, permissions p 
WHERE g.name = 'Editors' 
AND p.name IN ('page_management', 'template_management', 'report_management');

-- Assign basic user permissions to Users group
INSERT INTO group_permissions (group_id, permission_id)
SELECT g.id, p.id 
FROM user_groups_list g, permissions p 
WHERE g.name = 'Users' 
AND p.name IN ('log_viewing');

-- Insert default page templates
INSERT INTO page_templates (name, filename, description) VALUES
('Default Page', 'default_page_template.html', 'Standard page template with header and footer'),
('Admin Page', 'admin_page_template.html', 'Admin area page template'),
('Error Page', 'error_page_template.html', 'Error page template'),
('Login Page', 'login_page_template.html', 'Login page template');

-- Insert default email templates
INSERT INTO email_templates (name, description, subject, body, allow_unsubscribe) VALUES
('password_reset', 'Password reset email', 'Password Reset Request', 
'<h2>Password Reset</h2><p>Hello {{first_name}},</p><p>You requested a password reset. Click the link below to reset your password:</p><p><a href="{{reset_link}}">Reset Password</a></p><p>This link will expire in 24 hours.</p><p>If you did not request this reset, please ignore this email.</p>', 0),
('welcome_email', 'Welcome email for new users', 'Welcome to {{site_name}}', 
'<h2>Welcome, {{first_name}}!</h2><p>Thank you for registering at {{site_name}}. Your account has been created successfully.</p><p>You can now log in using your email address and the password you created.</p><p>If you have any questions, please contact our support team.</p>', 1);

-- Create default admin user (password: admin123)
INSERT INTO users (email, password_hash, first_name, last_name, status) VALUES
('admin@example.com', '$2y$12$Ol5DjRXtxza.HhOZw2T.0OJMd0EJ4gunioBkWSMJZkfIz2VBRFjqy', 'Admin', 'User', 'active');

-- Add admin user to Administrators group
INSERT INTO user_groups (user_id, group_id) 
SELECT u.id, g.id 
FROM users u, user_groups_list g 
WHERE u.email = 'admin@example.com' AND g.name = 'Administrators';

-- Insert default pages
INSERT INTO pages (title, path, content, meta_description, template_file, custom_script, require_auth, required_permission, status) VALUES
('Home',             'home',             '<h1>Welcome to Snow Framework</h1><p>This is the home page of your Snow-powered website.</p>', 'Welcome to Snow Framework', 'default_page_template.html', NULL,                  0, NULL,                'active'),
('Login',            'login',            '',                                                                                              'Login to Snow Framework',  'login_page_template.html',  'login.php',           0, NULL,                'active'),
('Profile',          'profile',          '',                                                                                              'User Profile',             'default_page_template.html', 'profile.php',         1, NULL,                'active'),
('Admin Dashboard',  'admin',            '',                                                                                              'Admin Dashboard',          'admin_page_template.html',  'admin.php',           1, NULL,                'active'),
('Logout',           'logout',           '',                                                                                              'Logout',                   'default_page_template.html', 'logout.php',          0, NULL,                'active'),
('User Management',  'admin/users',      '',                                                                                              'User Management',          'admin_page_template.html',  'admin-users.php',     1, 'user_management',   'active'),
('Page Management',  'admin/pages',      '',                                                                                              'Page Management',          'admin_page_template.html',  'admin-pages.php',     1, 'page_management',   'active'),
('Group Management', 'admin/groups',     '',                                                                                              'Group Management',         'admin_page_template.html',  'admin-groups.php',    1, 'group_management',  'active'),
('Reports',          'admin/reports',    '',                                                                                              'Reports',                  'admin_page_template.html',  'admin-reports.php',   1, 'report_management', 'active'),
('Custom Tables',    'admin/tables',     '',                                                                                              'Custom Tables',            'admin_page_template.html',  'admin-tables.php',    1, 'table_management',  'active'),
('Email Templates',  'admin/emails',     '',                                                                                              'Email Templates',          'admin_page_template.html',  'admin-emails.php',    1, 'email_management',  'active'),
('Plugins',          'admin/plugins',    '',                                                                                              'Plugins',                  'admin_page_template.html',  'admin-plugins.php',   1, 'plugin_management', 'active'),
('Snapshots',        'admin/snapshots',  '',                                                                                              'Snapshots',                'admin_page_template.html',  'admin-snapshots.php', 1, 'snapshot_management','active'),
('Logs',             'admin/logs',       '',                                                                                              'Logs',                     'admin_page_template.html',  'admin-logs.php',      1, 'log_viewing',       'active');

-- Insert default navigation
INSERT INTO navigation (menu_name, title, url, sort_order, status) VALUES
('main', 'Home', '/', 1, 'active'),
('main', 'Login', '/login', 10, 'active'),
('main', 'Profile', '/profile', 20, 'active'),
('main', 'Admin', '/admin', 30, 'active');

-- Create default encryption key
INSERT INTO encryption_keys (name, description) VALUES
('default', 'Default encryption key for general use');

-- Insert built-in report: pages list (used by admin/pages list view)
INSERT INTO report_templates (name, description, sql_table, sql_fields, sql_where, sql_order, rows_per_page, output_format, html_header, html_row_template, html_footer, status) VALUES
(
    'pages_list',
    'All pages — matches the admin/pages list view',
    'pages p LEFT JOIN page_templates t ON p.template_file = t.filename',
    'p.id,
    p.title,
    p.path,
    COALESCE(t.name, p.template_file) AS template_name,
    CASE WHEN p.require_auth = 1 THEN ''<span class="badge bg-warning">Yes</span>'' ELSE ''<span class="badge bg-secondary">No</span>'' END AS auth_badge,
    CASE p.status WHEN ''active'' THEN ''<span class="badge bg-success">active</span>'' WHEN ''inactive'' THEN ''<span class="badge bg-secondary">inactive</span>'' ELSE ''<span class="badge bg-secondary">other</span>'' END AS status_badge',
    'p.status != ''deleted''',
    'p.sort_order, p.title',
    50,
    'html',
    '<table class="table table-striped table-hover">
<thead class="table-dark">
<tr><th>ID</th><th>Title</th><th>Path</th><th>Template</th><th>Auth</th><th>Status</th><th>Actions</th></tr>
</thead>
<tbody>',
    '<tr><td>{{id}}</td><td>{{title}}</td><td><code>{{path}}</code></td><td>{{template_name}}</td><td>{{auth_badge}}</td><td>{{status_badge}}</td><td><a href="/admin/pages?action=edit&amp;id={{id}}" class="btn btn-primary btn-sm">Edit</a></td></tr>',
    '</tbody></table>',
    'active'
);

-- Insert built-in report: users list (used by admin/users list view)
INSERT INTO report_templates (name, description, sql_table, sql_fields, sql_where, sql_order, rows_per_page, output_format, html_header, html_row_template, html_footer, status) VALUES
(
    'users_list',
    'All users — matches the admin/users list view',
    'users',
    'id,
    CONCAT(first_name, '' '', last_name) AS full_name,
    email,
    CASE status WHEN ''active'' THEN ''<span class="badge bg-success">active</span>'' WHEN ''suspended'' THEN ''<span class="badge bg-warning">suspended</span>'' ELSE CONCAT(''<span class="badge bg-secondary">'', status, ''</span>'') END AS status_badge,
    IFNULL(last_login, ''<span class="text-muted">Never</span>'') AS last_login_display',
    NULL,
    'created_date DESC',
    50,
    'html',
    '<table class="table table-striped table-hover">
<thead class="table-dark">
<tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Last Login</th><th>Actions</th></tr>
</thead>
<tbody>',
    '<tr><td>{{id}}</td><td>{{full_name}}</td><td>{{email}}</td><td>{{status_badge}}</td><td>{{last_login_display}}</td><td><a href="/admin/users?action=edit&amp;id={{id}}" class="btn btn-primary btn-sm">Edit</a></td></tr>',
    '</tbody></table>',
    'active'
);

-- Insert built-in report: groups list (used by admin/groups list view)
INSERT INTO report_templates (name, description, sql_table, sql_fields, sql_where, sql_order, rows_per_page, output_format, html_header, html_row_template, html_footer, status) VALUES
(
    'groups_list',
    'All groups — matches the admin/groups list view',
    '(SELECT g.id, g.name, g.description, g.status,
        COUNT(DISTINCT ug.user_id)       AS member_count,
        COUNT(DISTINCT gp.permission_id) AS permission_count
      FROM user_groups_list g
      LEFT JOIN user_groups ug       ON g.id = ug.group_id
      LEFT JOIN group_permissions gp ON g.id = gp.group_id
      GROUP BY g.id, g.name, g.description, g.status) AS grp',
    'id,
    name,
    COALESCE(description, '''') AS description,
    CONCAT(''<span class="badge bg-info text-dark">'', member_count, ''</span>'') AS member_badge,
    CONCAT(''<span class="badge bg-secondary">'', permission_count, ''</span>'') AS permission_badge,
    CASE status WHEN ''active'' THEN ''<span class="badge bg-success">active</span>'' ELSE ''<span class="badge bg-secondary">inactive</span>'' END AS status_badge',
    NULL,
    'name',
    50,
    'html',
    '<table class="table table-striped table-hover">
<thead class="table-dark">
<tr><th>ID</th><th>Name</th><th>Description</th><th>Members</th><th>Permissions</th><th>Status</th><th>Actions</th></tr>
</thead>
<tbody>',
    '<tr><td>{{id}}</td><td>{{name}}</td><td>{{description}}</td><td>{{member_badge}}</td><td>{{permission_badge}}</td><td>{{status_badge}}</td><td><a href="/admin/groups?action=edit&amp;id={{id}}" class="btn btn-primary btn-sm">Edit</a></td></tr>',
    '</tbody></table>',
    'active'
);

-- Insert built-in report: reports list (used by admin/reports list view)
INSERT INTO report_templates (name, description, sql_table, sql_fields, sql_where, sql_order, rows_per_page, output_format, html_header, html_row_template, html_footer, status) VALUES
(
    'reports_list',
    'All reports — matches the admin/reports list view',
    'report_templates',
    'id,
    name,
    CASE WHEN description IS NOT NULL AND description != ''''
        THEN CONCAT(''<strong>'', name, ''</strong><br><small class="text-muted">'', description, ''</small>'')
        ELSE CONCAT(''<strong>'', name, ''</strong>'')
    END AS name_display,
    CONCAT(''<code>'', REPLACE(REPLACE(sql_table, ''<'', ''&lt;''), ''>'', ''&gt;''), ''</code>'') AS table_display,
    UPPER(output_format) AS format_display,
    CASE status WHEN ''active'' THEN ''<span class="badge bg-success">active</span>'' ELSE ''<span class="badge bg-secondary">inactive</span>'' END AS status_badge',
    'status != ''deleted''',
    'name',
    50,
    'html',
    '<table class="table table-striped table-hover">
<thead class="table-dark">
<tr><th>Name</th><th>Table</th><th>Format</th><th>Status</th><th>Actions</th></tr>
</thead>
<tbody>',
    '<tr><td>{{name_display}}</td><td>{{table_display}}</td><td>{{format_display}}</td><td>{{status_badge}}</td><td><a href="/admin/reports?action=run&amp;id={{id}}" class="btn btn-info btn-sm">Run</a> <a href="/admin/reports?action=edit&amp;id={{id}}" class="btn btn-primary btn-sm">Edit</a></td></tr>',
    '</tbody></table>',
    'active'
);

-- Insert built-in report: emails list (used by admin/emails list view)
INSERT INTO report_templates (name, description, sql_table, sql_fields, sql_where, sql_order, rows_per_page, output_format, html_header, html_row_template, html_footer, status) VALUES
(
    'emails_list',
    'All email templates — matches the admin/emails list view',
    'email_templates',
    'id,
    CASE WHEN description IS NOT NULL AND description != ''''
        THEN CONCAT(''<strong>'', name, ''</strong><br><small class="text-muted">'', description, ''</small>'')
        ELSE CONCAT(''<strong>'', name, ''</strong>'')
    END AS name_display,
    subject,
    COALESCE(from_address, '''') AS from_address,
    CASE allow_unsubscribe WHEN 1 THEN ''<span class="badge bg-info text-dark">Yes</span>'' ELSE ''<span class="badge bg-secondary">No</span>'' END AS unsub_badge,
    CASE status WHEN ''active'' THEN ''<span class="badge bg-success">active</span>'' ELSE ''<span class="badge bg-secondary">inactive</span>'' END AS status_badge',
    'status != ''deleted''',
    'name',
    50,
    'html',
    '<table class="table table-striped table-hover">
<thead class="table-dark">
<tr><th>Name</th><th>Subject</th><th>From</th><th>Unsubscribe</th><th>Status</th><th>Actions</th></tr>
</thead>
<tbody>',
    '<tr><td>{{name_display}}</td><td>{{subject}}</td><td>{{from_address}}</td><td>{{unsub_badge}}</td><td>{{status_badge}}</td><td><a href="/admin/emails?action=edit&amp;id={{id}}" class="btn btn-primary btn-sm">Edit</a></td></tr>',
    '</tbody></table>',
    'active'
);

-- Insert built-in report: plugins list (used by admin/plugins list view)
INSERT INTO report_templates (name, description, sql_table, sql_fields, sql_where, sql_order, rows_per_page, output_format, html_header, html_row_template, html_footer, status) VALUES
(
    'plugins_list',
    'All plugins — matches the admin/plugins list view',
    'plugins',
    'id,
    CASE WHEN description IS NOT NULL AND description != ''''
        THEN CONCAT(''<strong>'', name, ''</strong><br><small class="text-muted">'', description, ''</small>'')
        ELSE CONCAT(''<strong>'', name, ''</strong>'')
    END AS name_display,
    version,
    COALESCE(author, '''') AS author,
    CASE status WHEN ''active'' THEN ''<span class="badge bg-success">active</span>'' WHEN ''error'' THEN ''<span class="badge bg-danger">error</span>'' ELSE ''<span class="badge bg-secondary">inactive</span>'' END AS status_badge,
    IFNULL(install_date, ''<span class="text-muted">Never</span>'') AS install_date_display',
    NULL,
    'name',
    50,
    'html',
    '<table class="table table-striped table-hover">
<thead class="table-dark">
<tr><th>Name</th><th>Version</th><th>Author</th><th>Status</th><th>Installed</th></tr>
</thead>
<tbody>',
    '<tr><td>{{name_display}}</td><td>{{version}}</td><td>{{author}}</td><td>{{status_badge}}</td><td>{{install_date_display}}</td></tr>',
    '</tbody></table>',
    'active'
);

-- Insert built-in report: snapshots list (used by admin/snapshots list view)
INSERT INTO report_templates (name, description, sql_table, sql_fields, sql_where, sql_order, rows_per_page, output_format, html_header, html_row_template, html_footer, status) VALUES
(
    'snapshots_list',
    'All snapshots — matches the admin/snapshots list view',
    'snapshots s LEFT JOIN users u ON s.created_by = u.id',
    's.id,
    CONCAT(''<code>'', s.table_name, ''</code>'') AS table_display,
    s.snapshot_name,
    COALESCE(s.description, '''') AS description,
    s.snapshot_date,
    CONCAT(''<span class="badge bg-secondary">'', FORMAT(s.row_count, 0), ''</span>'') AS row_badge,
    CASE WHEN u.first_name IS NOT NULL
        THEN CONCAT(u.first_name, '' '', u.last_name)
        ELSE ''<span class="text-muted">&mdash;</span>''
    END AS created_by_display',
    's.status = ''active''',
    's.snapshot_date DESC',
    50,
    'html',
    '<table class="table table-striped table-hover">
<thead class="table-dark">
<tr><th>Table</th><th>Snapshot Name</th><th>Description</th><th>Date</th><th>Rows</th><th>By</th></tr>
</thead>
<tbody>',
    '<tr><td>{{table_display}}</td><td>{{snapshot_name}}</td><td>{{description}}</td><td>{{snapshot_date}}</td><td>{{row_badge}}</td><td>{{created_by_display}}</td></tr>',
    '</tbody></table>',
    'active'
);

-- Insert built-in report: custom tables list (used by admin/tables list view)
INSERT INTO report_templates (name, description, sql_table, sql_fields, sql_where, sql_order, rows_per_page, output_format, html_header, html_row_template, html_footer, status) VALUES
(
    'tables_list',
    'All custom table definitions — matches the admin/tables list view',
    '(SELECT ct.id, ct.table_name, ct.display_name, ct.description, ct.status,
        COUNT(ctf.id) AS field_count
      FROM custom_tables ct
      LEFT JOIN custom_table_fields ctf ON ct.table_name = ctf.table_name AND ctf.status = ''active''
      GROUP BY ct.id, ct.table_name, ct.display_name, ct.description, ct.status) AS ct',
    'id,
    CONCAT(''<code>'', table_name, ''</code>'') AS table_display,
    display_name,
    COALESCE(description, '''') AS description,
    CONCAT(''<span class="badge bg-info text-dark">'', field_count, ''</span>'') AS field_badge,
    CASE status WHEN ''active'' THEN ''<span class="badge bg-success">active</span>'' ELSE ''<span class="badge bg-secondary">inactive</span>'' END AS status_badge',
    NULL,
    'display_name',
    50,
    'html',
    '<table class="table table-striped table-hover">
<thead class="table-dark">
<tr><th>Table Name</th><th>Display Name</th><th>Description</th><th>Fields</th><th>Status</th><th>Actions</th></tr>
</thead>
<tbody>',
    '<tr><td>{{table_display}}</td><td>{{display_name}}</td><td>{{description}}</td><td>{{field_badge}}</td><td>{{status_badge}}</td><td><a href="/admin/tables?action=fields&amp;id={{id}}" class="btn btn-info btn-sm">Fields</a> <a href="/admin/tables?action=edit&amp;id={{id}}" class="btn btn-primary btn-sm">Edit</a></td></tr>',
    '</tbody></table>',
    'active'
);

-- =============================================================================
-- Phase 1 Security Foundations — migration (2026-02-28)
-- =============================================================================

-- Sessions table — DB-backed session storage (SEC-02)
CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    user_id INT NULL,
    data LONGTEXT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log table — structured DB logging (SEC-04)
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level VARCHAR(20) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    user_id INT NULL,
    session_id VARCHAR(128) NULL,
    ip_address VARCHAR(45) NULL,
    context JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_level (level),
    INDEX idx_event_type (event_type),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password policy config table — one-row config (SEC-03)
CREATE TABLE IF NOT EXISTS password_policy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    min_length INT NOT NULL DEFAULT 8,
    max_age_days INT NOT NULL DEFAULT 0,
    prevent_reuse_count INT NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default policy row so getPasswordPolicy() always has data
INSERT INTO password_policy (min_length, max_age_days, prevent_reuse_count)
SELECT 8, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM password_policy LIMIT 1);

-- Password history table — previous hashes for reuse prevention (SEC-03)
CREATE TABLE IF NOT EXISTS password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add password_changed_at to users for password age tracking (SEC-03)
-- Conditional guard: only adds column if it does not already exist (MySQL 8.0 compatible)
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'password_changed_at'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL AFTER last_login',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================================================
-- Phase 2: Access Control — Standard Custom Table Columns
-- =============================================================================
-- Every custom table provisioned from Phase 2 onward includes these columns.
-- Tables provisioned before Phase 2 receive these columns via
-- migrateExistingCustomTables() in functions/admin-tables.php.
--
-- Standard column definitions (applied by provisionCustomTable()):
--
--   `status`      VARCHAR(20)  NOT NULL DEFAULT 'active'
--       Row lifecycle: 'active', 'inactive'. Enforced by application logic.
--
--   `view_groups` VARCHAR(500) DEFAULT NULL
--       Comma-separated group IDs (e.g. '1,3,7'). NULL = open to all users
--       with table_management permission. ACL check performed in PHP via
--       canViewRow() in functions/acl.php using array_intersect().
--
--   `edit_groups` VARCHAR(500) DEFAULT NULL
--       Comma-separated group IDs. NULL = any user who can view can also edit.
--       ACL check performed in PHP via canEditRow() in functions/acl.php.
--
--   `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
--       Row creation timestamp. Set automatically by MySQL on INSERT.
--
--   `modified_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
--       Row last-modified timestamp. Updated automatically by MySQL on any UPDATE.
--       NOTE: Tables provisioned before Phase 2 may also have an `updated_at`
--       column (the prior standard name). Both columns may coexist on legacy
--       tables; `updated_at` is left in place to avoid breaking existing reports.
--
-- Full CREATE TABLE template for new custom tables:
--
-- CREATE TABLE IF NOT EXISTS `{table_name}` (
--     `id`          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
--     `status`      VARCHAR(20)  NOT NULL DEFAULT 'active',
--     `view_groups` VARCHAR(500) DEFAULT NULL,
--     `edit_groups` VARCHAR(500) DEFAULT NULL,
--     `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
--     `modified_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- =============================================================================

-- =============================================================================
-- Phase 2 Gap Closure: table_data_access permission (2026-03-01)
-- =============================================================================
-- Separates "can access custom table data pages" from "can manage table definitions".
-- Non-admin users assigned table_data_access can reach admin/data/* and have row-level
-- ACL applied. table_management remains required for admin/tables (schema management)
-- and for the View Groups / Edit Groups selectors within each data form.

-- Add permission if not already present (idempotent)
INSERT IGNORE INTO permissions (name, description)
VALUES ('table_data_access', 'Access custom table data pages (row-level ACL applied)');

-- Grant table_data_access to any group that already holds table_management (idempotent)
INSERT IGNORE INTO group_permissions (group_id, permission_id)
SELECT gp.group_id, p.id
FROM group_permissions gp
JOIN permissions existing ON gp.permission_id = existing.id AND existing.name = 'table_management'
JOIN permissions p ON p.name = 'table_data_access';

-- Migrate already-provisioned admin/data/* pages from table_management to table_data_access
UPDATE pages
SET required_permission = 'table_data_access'
WHERE path LIKE 'admin/data/%'
  AND required_permission = 'table_management';
-- =============================================================================

-- =============================================================================
-- Phase 3: Data Integrity — Migration
-- =============================================================================

-- VER-01: Row version history table
-- Stores a full JSON before-image of each row before every edit.
-- Only edits (UPDATEs) create entries — inserts and deletes do not.
CREATE TABLE IF NOT EXISTS row_versions (
    id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    table_name   VARCHAR(255) NOT NULL,
    row_id       INT          NOT NULL,
    changed_by   INT          NULL,
    changed_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    row_snapshot JSON         NOT NULL,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_table_row   (table_name, row_id),
    INDEX idx_changed_at  (changed_at),
    INDEX idx_changed_by  (changed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- VER-03/VER-05: Add physical snapshot table name column to snapshots metadata.
-- Stores the actual MySQL table name (e.g. snapshot_products_20260305143022)
-- so restore can look it up unambiguously without string reconstruction.
-- Uses INFORMATION_SCHEMA guard for idempotency (MySQL 8.0 compatible).
SET @dbname = DATABASE();
SET @tblname = 'snapshots';
SET @colname = 'snapshot_table';
SET @preparedStatement = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE snapshots ADD COLUMN snapshot_table VARCHAR(255) NULL AFTER snapshot_name',
        'SELECT ''Column snapshot_table already exists — skipping'''
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME   = @tblname
      AND COLUMN_NAME  = @colname
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================================================
-- Phase 3 Plan 06 — snapshot restore (2026-03-06)
-- =============================================================================
-- VER-05: Expand snapshots.status enum to include 'restored'.
-- Needed so the restore POST handler can mark a used snapshot without deleting it.
-- Uses COLUMN_TYPE check in INFORMATION_SCHEMA to guard against re-running.
SET @dbname = DATABASE();
SET @preparedStatement = (
    SELECT IF(
        COLUMN_TYPE NOT LIKE '%restored%',
        'ALTER TABLE snapshots MODIFY COLUMN status ENUM(''active'',''deleted'',''restored'') DEFAULT ''active''',
        'SELECT ''snapshots.status already has restored value — skipping'''
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME   = 'snapshots'
      AND COLUMN_NAME  = 'status'
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
-- =============================================================================
-- =============================================================================
-- Phase 4: Extensibility — migration (2026-03-06)
-- =============================================================================

-- EXT-01: Add pre_edit_php_filename to custom_tables (hook: fires before row edit is saved)
SET @preparedStatement = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE custom_tables ADD COLUMN pre_edit_php_filename VARCHAR(255) NULL AFTER description',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'custom_tables'
      AND COLUMN_NAME  = 'pre_edit_php_filename'
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- EXT-01: Add post_edit_php_filename to custom_tables (hook: fires after row edit is saved)
SET @preparedStatement = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE custom_tables ADD COLUMN post_edit_php_filename VARCHAR(255) NULL AFTER pre_edit_php_filename',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'custom_tables'
      AND COLUMN_NAME  = 'post_edit_php_filename'
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- SEC-05: Add require_2fa to users (flag: 1 = user must complete OTP challenge at login)
SET @preparedStatement = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE users ADD COLUMN require_2fa TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'users'
      AND COLUMN_NAME  = 'require_2fa'
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- SEC-05: OTP challenge table — holds pending login verification codes
CREATE TABLE IF NOT EXISTS login_otp (
    id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    code       VARCHAR(6)   NOT NULL,
    expires_at DATETIME     NOT NULL,
    attempts   TINYINT      NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_login_otp_user    (user_id),
    INDEX idx_login_otp_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SEC-05: Seed login_otp email template
INSERT INTO email_templates (name, subject, body, status)
VALUES (
    'login_otp',
    'Your login verification code',
    'Hi {{first_name}},\n\nYour login verification code is:\n\n{{otp_code}}\n\nThis code expires in 15 minutes.\n\nIf you did not request this, please contact your administrator.',
    'active'
)
ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body);

-- SEC-05: Register login-otp page for OTP entry
INSERT INTO pages (title, path, custom_script, require_auth, status)
VALUES ('Login Verification', 'login-otp', 'login-otp.php', 0, 'active')
ON DUPLICATE KEY UPDATE custom_script = VALUES(custom_script), title = VALUES(title);

-- =============================================================================

-- =============================================================================
-- Phase 5: User Experience — migration (2026-03-17)
-- =============================================================================

-- DATA-03: Add col_width to custom_table_fields
-- col_width controls edit form field width: 'half' = col-md-6, 'full' = col-12
-- DEFAULT 'half' preserves current behavior for all existing fields
SET @preparedStatement = (
    SELECT IF(
        COUNT(*) = 0,
        "ALTER TABLE custom_table_fields ADD COLUMN col_width VARCHAR(10) NOT NULL DEFAULT 'half' AFTER display_order",
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'custom_table_fields'
      AND COLUMN_NAME  = 'col_width'
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- EXT-04: Schedule columns (activate_at, deactivate_at, delete_at) are added to
-- each individual custom table via migrateExistingCustomTables() and
-- provisionCustomTable() — no central framework table changes required here.

-- =============================================================================
