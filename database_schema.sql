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
    sql_table VARCHAR(255) NOT NULL,
    sql_fields VARCHAR(500) DEFAULT '*',
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
INSERT INTO pages (title, path, content, meta_description, template_file, require_auth, status) VALUES
('Home', 'home', '<h1>Welcome to Snow Framework</h1><p>This is the home page of your Snow-powered website.</p>', 'Welcome to Snow Framework', 'default_page_template.html', 0, 'active'),
('Login', 'login', '<h1>Login</h1><p>Please enter your credentials to access the system.</p>', 'Login to Snow Framework', 'login_page_template.html', 0, 'active'),
('Profile', 'profile', '<h1>User Profile</h1><p>Manage your account settings here.</p>', 'User Profile', 'default_page_template.html', 1, 'active'),
('Admin Dashboard', 'admin', '<h1>Admin Dashboard</h1><p>Welcome to the admin area.</p>', 'Admin Dashboard', 'admin_page_template.html', 1, 'active');

-- Insert default navigation
INSERT INTO navigation (menu_name, title, url, sort_order, status) VALUES
('main', 'Home', '/', 1, 'active'),
('main', 'Login', '/login', 10, 'active'),
('main', 'Profile', '/profile', 20, 'active'),
('main', 'Admin', '/admin', 30, 'active');

-- Create default encryption key
INSERT INTO encryption_keys (name, description) VALUES
('default', 'Default encryption key for general use');