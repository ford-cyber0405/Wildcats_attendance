FROM php:8.2-apache

# Install system packages and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libpng-dev \
    zip \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql zip mbstring xml \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
# Ensure only one Apache MPM is enabled (prefork, required for mod_php)
RUN a2dismod -f mpm_event mpm_worker mpm_prefork || true
RUN rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* /etc/apache2/mods-enabled/mpm_prefork.*
RUN a2enmod mpm_prefork rewrite
# Fail the build if more than one MPM is loaded
RUN apache2ctl -M 2>/dev/null | grep -c 'mpm_' | grep -qx 1

# Make Apache use port 10000 (Render default)
RUN sed -i 's/Listen 80/Listen 10000/g' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:10000>/g' /etc/apache2/sites-available/000-default.conf

# Set Laravel public as document root
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/apache2.conf

# Allow .htaccess for Laravel
RUN printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy Laravel app
COPY . .

# Install startup script that guarantees a single MPM at runtime
COPY start-apache.sh /usr/local/bin/start-apache.sh
RUN chmod +x /usr/local/bin/start-apache.sh

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install frontend dependencies and build assets
RUN npm install && npm run build

RUN php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear

# Create storage symlink
RUN php artisan storage:link || true

# Fix permissions
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache public/uploads \
    && chown -R www-data:www-data storage bootstrap/cache public/uploads \
    && chmod -R 775 storage bootstrap/cache public/uploads

# (Optional) Run migrations


# Expose port
EXPOSE 10000

# Start Apache
CMD ["/usr/local/bin/start-apache.sh"]
