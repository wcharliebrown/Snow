# Snow Docker Testing

This directory contains Docker configuration for testing the Snow framework.

## Quick Start

1. **Start the test environment:**
```bash
./test-docker.sh
```

2. **Access the framework:**
- URL: http://localhost:8080
- Email: admin@example.com  
- Password: admin123

## Manual Docker Commands

### Build and Start Containers
```bash
docker-compose up --build -d
```

### View Logs
```bash
docker-compose logs -f
```

### Stop Containers
```bash
docker-compose down
```

### Reset Everything (including database)
```bash
docker-compose down -v
```

## Container Details

### MySQL Container
- **Image**: mysql:8.0
- **Port**: 3306 (host)
- **Database**: snow
- **User**: snow_user
- **Password**: snow_password

### PHP-FPM Container
- **Image**: php:8.4-fpm (custom build)
- **Extensions**: PDO, MySQL, GD, Zip, XML, MBString, Exif, BCMath
- **Port**: 9000 (internal)

### Apache Container  
- **Image**: httpd:2.4 (custom build)
- **Port**: 8080 (host)
- **Modules**: rewrite, fcgid, proxy_fcgi, headers, expires

## Development

### Access PHP Container
```bash
docker exec -it snow_php bash
```

### Access MySQL
```bash
docker exec -it snow_mysql mysql -u snow_user -psnow_password snow
```

### View Apache Configuration
```bash
docker exec snow_apache cat /usr/local/apache2/conf-available/snow.conf
```

### View PHP Configuration
```bash
docker exec snow_php php -i | grep -E "(memory_limit|max_execution|upload_max)"
```

## Troubleshooting

### Port Conflicts
If port 8080 is already in use, modify `docker-compose.yml`:
```yaml
ports:
  - "8081:80"  # Change 8080 to 8081
```

### Database Connection Issues
Check if MySQL is ready:
```bash
docker exec snow_mysql mysqladmin ping -h"localhost"
```

### Permission Issues
If you get permission errors, ensure logs/keys directories are writable:
```bash
docker exec snow_php chown -R wwwuser:wwwuser /var/www/html/logs
docker exec snow_php chown -R wwwuser:wwwuser /var/www/html/keys
```

### PHP Errors
View PHP error log:
```bash
docker exec snow_php tail -f /var/www/html/logs/php_errors.log
```

## File Structure

```
├── docker-compose.yml      # Docker Compose configuration
├── Dockerfile.php         # PHP-FPM container definition
├── Dockerfile.apache       # Apache container definition
├── apache.conf           # Apache virtual host configuration
├── php.ini              # Custom PHP configuration
├── test-docker.sh       # Automated test script
└── README-Docker.md     # This file
```

## Environment Variables

The Docker environment uses these database settings (configured in .env):
- DB_HOST=mysql
- DB_USER=snow_user  
- DB_PASS=snow_password
- SITE_URL=http://localhost:8080

These are automatically configured for Docker networking.

## Performance Testing

The test environment includes:
- PHP-FPM for optimal performance
- OPcache enabled by default in PHP 8.4
- Apache with mod_rewrite and proper headers
- MySQL 8.0 with performance optimizations

## Security Testing

The Docker setup includes:
- Secure directory restrictions
- Security headers via Apache
- PHP hardening options
- Isolated network containers