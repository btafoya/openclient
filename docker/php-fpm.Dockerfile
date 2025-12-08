FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \    libpq-dev git unzip \ && docker-php-ext-install pdo pdo_pgsql

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

CMD ["php-fpm"]
