FROM php:8.3-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev \
    zip unzip libzip-dev libicu-dev \
    libfreetype6-dev libjpeg62-turbo-dev \
    libpq-dev rsync \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP extensions layer
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd zip intl

# Node.js separate layer (needed if asset compilation or vite build is required)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install composer dependencies
COPY composer.json composer.lock ./
RUN composer install --no-interaction --optimize-autoloader --no-scripts --no-dev

# Install npm dependencies if package.json exists
COPY package.json package-lock.json* ./
RUN if [ -f package.json ]; then npm ci || npm install; fi

# Copy application source code
COPY . .

# Run assets build if script exists & optimize autoloader
RUN if [ -f package.json ]; then npm run build --if-present; fi \
    && composer dump-autoload --optimize \
    && chown -R www-data:www-data /app \
    && chmod -R 775 /app/storage /app/bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
