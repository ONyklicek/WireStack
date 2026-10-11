FROM mcr.microsoft.com/devcontainers/php:4-8.4-trixie

USER root

# PHP extensions CI runs with (.github/workflows/ci.yml, PHP_EXTENSIONS plus the
# database legs' drivers), and gd for the media module. pdo_sqlite and sqlite3
# are compiled into the base image already — reinstalling them only produces a
# "module already loaded" warning on every php call.
#
# Chromium is for the CDP drivers (`npm run verify:drivers`); they read
# CHROME_BIN, set in docker-compose.yml.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        chromium \
        fonts-liberation \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libpq-dev \
        libsqlite3-dev \
        libzip-dev \
        mariadb-client \
        postgresql-client \
        sqlite3 \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        zip \
    && yes '' | pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Loaded after the base image's own php.ini and xdebug.ini (which carries the
# zend_extension line, so it is overridden here rather than replaced).
COPY .devcontainer/php.ini /usr/local/etc/php/conf.d/zz-wirestack.ini
COPY .devcontainer/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

# Named volumes mount as root unless the directory already exists with the
# right owner.
RUN mkdir -p /home/vscode/.composer/cache /home/vscode/.npm \
    && chown -R vscode:vscode /home/vscode/.composer /home/vscode/.npm

USER vscode
