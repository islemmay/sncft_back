FROM php:8.2-cli

# Install dependencies
RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libonig-dev libzip-dev zip \
    && docker-php-ext-install intl pdo pdo_mysql zip

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working dir
WORKDIR /app

# Copy project
COPY . .

# Install Symfony deps
RUN composer install --no-dev --optimize-autoloader

# Expose port
EXPOSE 10000

# Run server
CMD php -S 0.0.0.0:10000 -t public
