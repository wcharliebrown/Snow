# Snow Framework

A comprehensive PHP web framework for building dynamic websites with MySQL backend, designed for Linux servers running Apache2 and PHP 8.4.

## Features

- **No External Dependencies**: Pure PHP with no composer or node dependencies
- **Dynamic Page Generation**: All pages generated on-the-fly from MySQL database
- **Admin Interface**: Self-generating admin pages for site management
- **Authentication & Permissions**: Group-based user authentication and permissions
- **Template System**: Token-based template system similar to Twig/Mustache
- **Custom Tables**: Admin users can create and manage custom database tables
- **Report System**: Flexible reporting with HTML and CSV output
- **Email Templates**: Template-based email system with unsubscribe functionality
- **Plugin System**: Import/export plugins for site customization
- **Logging System**: Comprehensive logging for errors, traffic, and debugging
- **Encryption**: Field-level encryption with secure key management
- **Snapshots**: Database backup and restore functionality
- **Dev-Prod Sync**: Tools for synchronizing development and production sites

## Requirements

- Linux Server (Ubuntu 24+ recommended)
- Apache2 Web Server
- PHP 8.4+
- MySQL 8.0+
- mod_rewrite enabled

## Installation

### 1. Clone/Download the Framework

```bash
# Copy the framework to your web directory
cp -r snow /var/www/html/
cd /var/www/html/snow
```

### 2. Set Permissions

```bash
# Set proper ownership and permissions
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 770 logs keys
```

### 3. Configure Environment

Edit the `.env` file with your database and site settings:

```bash
# Database Settings
DB_HOST=localhost
DB_NAME=snow
DB_USER=your_db_user
DB_PASS=your_secure_password

# Site Settings
SITE_NAME=Your Website
SITE_URL=https://yourdomain.com
ADMIN_EMAIL=admin@yourdomain.com

# Security Settings
SESSION_TIMEOUT=3600
PASSWORD_MIN_LENGTH=8
ENCRYPTION_KEY=your_32_character_encryption_key_here
```

### 4. Create Database

```sql
CREATE DATABASE snow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'snow_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON snow.* TO 'snow_user'@'localhost';
FLUSH PRIVILEGES;
```

### 5. Import Database Schema

```bash
mysql -u snow_user -p snow < database_schema.sql
```

### 6. Configure Apache

Create a virtual host configuration:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/html/snow/public_html
    
    <Directory /var/www/html/snow/public_html>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/snow_error.log
    CustomLog ${APACHE_LOG_DIR}/snow_access.log combined
</VirtualHost>
```

Enable the site and rewrite module:

```bash
sudo a2ensite yourdomain.com.conf
sudo a2enmod rewrite
sudo systemctl restart apache2
```

## Directory Structure

```
snow/
├── .env                    # Configuration file
├── database_schema.sql     # Database schema
├── public_html/            # Web root
│   ├── index.php          # Main bootstrap file
│   └── .htaccess          # Apache configuration
├── templates/             # Page templates
├── functions/             # PHP function files
├── logs/                  # Log files
├── keys/                  # Encryption keys
├── plugins/               # Plugin files
└── snapshots/             # Database snapshots
```

## Default Login

After installation, you can log in with:

- **Email**: admin@example.com
- **Password**: admin123

**Important**: Change the default password immediately after first login.

## Core Functions

### Authentication
- `loginUser($email, $password)` - Authenticate user
- `logoutUser()` - Logout current user
- `hasPermission($permission)` - Check user permission
- `requireLogin()` - Require authentication
- `requirePermission($permission)` - Require specific permission

### Database
- `dbQuery($sql, $params)` - Execute SQL query
- `dbGetRow($sql, $params)` - Get single row
- `dbGetRows($sql, $params)` - Get multiple rows
- `dbInsert($table, $data)` - Insert record
- `dbUpdate($table, $data, $where, $params)` - Update record
- `dbDelete($table, $where, $params)` - Delete record

### Pages
- `renderPage($path)` - Render a page
- `savePage($data)` - Create/update page
- `getPageByPath($path)` - Get page by URL path

### Templates
- `renderTemplate($templateFile, $data)` - Render template
- `processTokens($content, $data)` - Process template tokens

### Reports
- `renderReport($report, $params)` - Generate report
- `exportReportCSV($report, $params)` - Export as CSV

### Email
- `sendEmailTemplate($templateName, $to, $data)` - Send templated email
- `sendEmail($to, $subject, $body)` - Send email directly

### Encryption
- `encryptString($data, $keyName)` - Encrypt data
- `decryptString($encryptedData, $keyName)` - Decrypt data
- `createEncryptionKey($keyName, $description)` - Create encryption key

### Logging
- `logError($message)` - Log error
- `logInfo($message)` - Log info
- `logTraffic($path)` - Log traffic
- `logEmail($to, $subject, $template)` - Log email sent

## Template System

Templates use token replacement similar to Twig/Mustache:

```html
<!-- Variables -->
<h1>{{title}}</h1>
<p>{{content}}</p>

<!-- Object properties -->
<p>{{user.first_name}} {{user.last_name}}</p>

<!-- Conditionals -->
{{#if user.is_admin}}
<p>Welcome admin!</p>
{{/if}}

<!-- Loops -->
<ul>
{{#each items}}
    <li>{{title}} - {{price}}</li>
{{/each}}
</ul>

<!-- Embedded reports -->
<div class="report">
    {{user_list_report}}
</div>
```

## Custom Tables

Admin users can create custom database tables with:

- Custom field definitions
- Field validation rules
- Select options from SQL queries
- Custom PHP scripts for before/after edit
- Field visibility controls
- Developer mode for advanced editing

## Security Features

- Password hashing with bcrypt
- Session timeout management
- CSRF protection
- SQL injection prevention
- XSS protection
- Field-level encryption
- Secure key storage
- Access logging

## Development

### Adding New Functions

1. Create a new PHP file in `functions/` directory
2. Name the file after the function (e.g., `custom.php`)
3. Functions are automatically loaded via autoloader

### Creating Custom Pages

1. Add page to database via admin interface
2. Create optional custom script in `functions/` (e.g., `about-us.php`)
3. Select template for the page

### Plugin Development

Plugins can include:
- PHP function files
- HTML templates
- Database schema changes
- Configuration files

## Support

For issues and questions:
1. Check the logs in `logs/` directory
2. Review the error messages
3. Test with `DEV_MODE=1` in `.env` for detailed errors

## License

This framework is provided as-is for educational and development purposes. Please review the code and customize according to your specific needs before using in production.