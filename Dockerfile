FROM php:8.3-apache

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    supervisor \
    && docker-php-ext-install zip pdo pdo_mysql \
    mbstring \
    exif \
    pcntl\
    bcmath \
    gd

RUN pecl install redis \
    && docker-php-ext-enable redis


COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY Docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80