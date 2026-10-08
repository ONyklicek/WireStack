FROM mcr.microsoft.com/devcontainers/php:1-8.4-bookworm

ARG NODE_VERSION=22

ENV DEBIAN_FRONTEND=noninteractive

# ---------------------------------------------------------
# System dependencies
# ---------------------------------------------------------

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        zip \
        sqlite3 \
        libsqlite3-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libpq-dev \
        libcurl4-openssl-dev \
        libssl-dev \
        imagemagick \
        ghostscript \
        jq \
        ripgrep \
        fd-find \
        bat \
        tree \
    && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------
# PHP extensions
# ---------------------------------------------------------

RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        zip

# ---------------------------------------------------------
# Redis
# ---------------------------------------------------------

RUN pecl install redis \
    && docker-php-ext-enable redis

# ---------------------------------------------------------
# Xdebug
# ---------------------------------------------------------

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# ---------------------------------------------------------
# Composer
# ---------------------------------------------------------

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# ---------------------------------------------------------
# Node.js
# ---------------------------------------------------------

RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x \
    | bash - \
    && apt-get install -y nodejs \
    && npm install -g \
        npm@latest \
        pnpm \
    && npm cache clean --force

# ---------------------------------------------------------
# Laravel CLI
# ---------------------------------------------------------

RUN curl -sLS https://php.new/install/linux/8.4 \
    | bash -s -- --install=global

# ---------------------------------------------------------
# PHP configuration
# ---------------------------------------------------------

COPY php.ini /usr/local/etc/php/conf.d/99-laravel.ini

# ---------------------------------------------------------
# Workspace
# ---------------------------------------------------------

WORKDIR /workspaces/${localWorkspaceFolderBasename}

# Ensure vscode owns the workspace
RUN chown -R vscode:vscode /workspaces
