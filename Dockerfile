FROM php:8.3-fpm

# System deps + poppler-utils (per pdftotext in PdfImportService)
RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip zip \
        libzip-dev libpng-dev libwebp-dev libjpeg62-turbo-dev \
        libonig-dev libxml2-dev libssl-dev libicu-dev \
        poppler-utils \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo pdo_mysql mbstring zip exif pcntl xml bcmath gd intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
