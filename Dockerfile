# Use the official PHP image with Apache
FROM php:8.2-apache

# 1. Install necessary PHP extensions for your database
RUN docker-php-ext-install pdo pdo_mysql mysqli

# 2. Enable Apache mod_rewrite (important for routing)
RUN a2enmod rewrite

# 3. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. COPY ALL FILES INTO THE CONTAINER FIRST
# (Everything below this can now see your composer.json and folders)
COPY . /var/www/html/

# 5. Install dependencies now that the files are present
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader; fi

# 6. Change the Apache document root to point to your 'public' folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 7. THE ROUTES FIX: Map the /routes URL to the /routes folder
RUN echo "Alias /routes /var/www/html/routes" >> /etc/apache2/apache2.conf
RUN echo "<Directory /var/www/html/routes>\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>" >> /etc/apache2/apache2.conf

# 8. Give Apache permissions to read your files
RUN chown -R www-data:www-data /var/www/html/