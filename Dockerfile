FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
        git curl libpng-dev libonig-dev libxml2-dev libzip-dev libpq-dev libicu-dev zip unzip \
        nodejs npm \
    && docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd zip intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
