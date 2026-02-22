FROM php:8.4-fpm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libssl-dev \
    curl \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql zip xml mbstring exif bcmath \
    && rm -rf /var/lib/apt/lists/*

# Create a user with uid=501 to match the macOS host user (cb)
# This ensures PHP-FPM can write to bind-mounted logs/ and keys/ directories
RUN groupadd -g 501 hostgroup 2>/dev/null || true \
    && useradd -u 501 -g 501 -m hostuser 2>/dev/null || true

# Run PHP-FPM workers as the host-uid user
RUN sed -i \
    -e 's/^user = www-data/user = hostuser/' \
    -e 's/^group = www-data/group = hostgroup/' \
    /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

EXPOSE 9000
