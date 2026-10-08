FROM mcr.microsoft.com/devcontainers/php:4-8.4-trixie

USER root

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        unzip \
        zip \
        sqlite3 \
        libsqlite3-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        zip \
        bcmath \
        intl \
        pdo_mysql \
        pdo_sqlite \
        gd \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

USER vscode
