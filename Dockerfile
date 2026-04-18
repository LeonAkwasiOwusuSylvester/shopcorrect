# Use the official PHP image with Apache
FROM php:8.2-apache

# Install necessary PHP extensions for your database
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache mod_rewrite (important for routing)
RUN a2enmod rewrite

# Change the Apache document root to point to your 'public' folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy all your project files into the Docker container
COPY . /var/www/html/

# Give Apache permissions to read your files
RUN chown -R www-data:www-data /var/www/html/