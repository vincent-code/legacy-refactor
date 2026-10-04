FROM php:8.1-apache

# Устанавливаем зависимости для zip и само расширение
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install mysqli zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \

# Включаем короткие теги PHP через кастомный файл конфигурации
RUN echo "short_open_tag = On" > /usr/local/etc/php/conf.d/legacy-short-tags.ini

# Включаем модуль Apache для SSI
RUN a2enmod include

RUN a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer