# Use the official PHP-Apache image
FROM php:8.2-apache

# Install necessary PHP extensions (optional, if you need mysqli or pdo)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite (very important for PHP websites)
RUN a2enmod rewrite

# Copy website files to Apache's public directory
COPY . /var/www/html/

# Set permissions (Optional, useful if you have uploads folder)
RUN chown -R www-data:www-data /var/www/html/ \
    && chmod -R 755 /var/www/html/

# Expose port 80
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]
