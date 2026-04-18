FROM php:8.2-apache

# 1. Install system tools + GD extension
RUN apt-get update && apt-get install -y \
    git unzip zip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mysqli zip gd

# 2. Enable Apache mod_rewrite (Crucial for your .htaccess to work!)
RUN a2enmod rewrite

# 3. Allow .htaccess to override Apache settings
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# 4. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Copy everything to the standard root
COPY . /var/www/html/

# 6. Install dependencies
RUN if [ -f "composer.json" ]; then \
    composer install --no-dev --optimize-autoloader --ignore-platform-reqs; \
    fi

# 7. Permissions
RUN chown -R www-data:www-data /var/www/html/