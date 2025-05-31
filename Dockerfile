# Use official PHP 8.1 CLI image
FROM php:8.1-cli

# Install system dependencies needed for Guzzle (like unzip and zip)
RUN apt-get update && apt-get install -y \
    libzip-dev unzip \
    && docker-php-ext-install zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory inside container
WORKDIR /app

# Copy all project files into container
COPY . /app

# Install PHP dependencies with Composer
RUN composer install --no-dev --optimize-autoloader

# Expose port 8080 for the web server
EXPOSE 8080

# Start PHP built-in web server to serve your webhook.php
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app"]
