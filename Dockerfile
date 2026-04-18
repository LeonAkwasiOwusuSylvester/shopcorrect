# Use the official PHP image with Apache
FROM php:8.2-apache

# 1. Install system dependencies (git, unzip, and zip are required by Composer)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli zip

# 2. Enable Apache mod_rewrite
RUN a2enmod rewrite

# 3. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Copy all files into the container
COPY . /var/www/html/

# 5. Install dependencies
# We add --ignore-platform-reqs just in case there's a mismatch with the PHP version
RUN if [ -f "composer.json" ]; then \
    composer install --no-dev --optimize-autoloader --ignore-platform-reqs; \
    fi

# 6. Change the Apache document root to point to your 'public' folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 7. THE ROUTES FIX: Map the /routes URL to the /routes folder
RUN echo "Alias /routes /var/www/html/routes" >> /etc/apache2/apache2.conf
RUN echo "<Directory /var/www/html/routes>\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>" >> /etc/apache2/apache2.conf

# 8. Set permissions
RUN chown -R www-data:www-data /var/www/html/