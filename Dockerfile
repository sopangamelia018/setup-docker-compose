FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libssl-dev unzip git \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql sockets bcmath \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && a2enmod rewrite headers \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# vendor ditaruh di /var/www/vendor supaya tidak tertimpa bind-mount ./php/src -> /var/www/html
WORKDIR /var/www
COPY composer.json ./
RUN composer install --no-interaction --no-progress --prefer-dist

WORKDIR /var/www/html
