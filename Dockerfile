FROM php:8.2-apache

# 1. Install system tools + GD extension (for images/QR codes)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mysqli zip gd

# 2. Enable Apache rewrite
RUN a2enmod rewrite

# 3. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Copy files
COPY . /var/www/html/

# 5. THE CLEAN SWEEP: Remove the Windows vendor folder and install fresh
RUN if [ -d "vendor" ]; then rm -rf vendor; fi
RUN if [ -f "composer.json" ]; then \
    composer install --no-dev --optimize-autoloader --ignore-platform-reqs; \
    fi

# 6. Configure Apache Root
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 7. Routes Alias
RUN echo "Alias /routes /var/www/html/routes" >> /etc/apache2/apache2.conf
RUN echo "<Directory /var/www/html/routes>\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>" >> /etc/apache2/apache2.conf

# 8. Permissions
RUN chown -R www-data:www-data /var/www/html/