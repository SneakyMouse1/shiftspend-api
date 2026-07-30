FROM php:8.4-fpm

# Set Composer environment variables
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_MEMORY_LIMIT=-1

# System dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev \
    zip unzip libzip-dev libicu-dev \
    libfreetype6-dev libjpeg62-turbo-dev \
    libpq-dev libgmp-dev rsync \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP extensions layer
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd zip intl gmp

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install composer dependencies
COPY composer.json composer.lock ./
RUN composer install --no-interaction --optimize-autoloader --no-scripts --no-dev --ignore-platform-reqs

# Copy application source code
COPY . .

RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data /app \
    && chmod -R 775 /app/storage /app/bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
