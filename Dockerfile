FROM php:8.5-apache

ARG APACHE_DOCUMENT_ROOT=/var/www/html/public

ENV APACHE_DOCUMENT_ROOT=${APACHE_DOCUMENT_ROOT} \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    PATH="/var/www/html/vendor/bin:${PATH}"

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        curl \
        git \
        libicu-dev \
        libonig-dev \
        libsqlite3-dev \
        libzip-dev \
        pkg-config \
        unzip; \
    docker-php-ext-configure intl; \
    docker-php-ext-install -j"$(nproc)" \
        intl \
        mbstring \
        mysqli \
        pdo_mysql \
        pdo_sqlite \
        sqlite3 \
        zip; \
    a2enmod expires headers rewrite; \
    { \
        echo 'ServerTokens Prod'; \
        echo 'ServerSignature Off'; \
        echo 'TraceEnable Off'; \
        echo 'FileETag None'; \
    } > /etc/apache2/conf-available/quenza-security.conf; \
    a2enconf quenza-security; \
    { \
        echo '<VirtualHost *:80>'; \
        echo '    ServerName localhost'; \
        echo "    DocumentRoot ${APACHE_DOCUMENT_ROOT}"; \
        echo ''; \
        echo '    <Directory /var/www/html/public>'; \
        echo '        Options -Indexes +FollowSymLinks'; \
        echo '        AllowOverride All'; \
        echo '        Require all granted'; \
        echo '    </Directory>'; \
        echo ''; \
        echo '    <Directory /var/www/html>'; \
        echo '        Options -Indexes +FollowSymLinks'; \
        echo '        AllowOverride All'; \
        echo '        Require all granted'; \
        echo '    </Directory>'; \
        echo ''; \
        echo '    <Directory /var/www/html/storage/uploads>'; \
        echo '        php_admin_flag engine Off'; \
        echo '        Options -Indexes'; \
        echo '        AllowOverride All'; \
        echo '        Require all granted'; \
        echo '    </Directory>'; \
        echo ''; \
        echo '    Header always unset X-Powered-By'; \
        echo '    Header always set X-Content-Type-Options "nosniff"'; \
        echo '    Header always set X-Frame-Options "SAMEORIGIN"'; \
        echo '    Header always set Referrer-Policy "strict-origin-when-cross-origin"'; \
        echo '    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"'; \
        echo ''; \
        echo '    ErrorLog ${APACHE_LOG_DIR}/error.log'; \
        echo '    CustomLog ${APACHE_LOG_DIR}/access.log combined'; \
        echo '</VirtualHost>'; \
    } > /etc/apache2/sites-available/000-default.conf; \
    { \
        echo 'expose_php=Off'; \
        echo 'display_errors=Off'; \
        echo 'log_errors=On'; \
        echo 'error_log=/proc/self/fd/2'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=60'; \
        echo 'upload_max_filesize=20M'; \
        echo 'post_max_size=24M'; \
        echo 'max_input_vars=2000'; \
        echo 'session.cookie_httponly=1'; \
        echo 'session.cookie_samesite=Lax'; \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'realpath_cache_size=4096K'; \
        echo 'realpath_cache_ttl=600'; \
        echo 'date.timezone=Asia/Jakarta'; \
    } > /usr/local/etc/php/conf.d/quenza.ini; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN set -eux; \
    composer install --prefer-dist --no-interaction --no-progress --optimize-autoloader

COPY . .

RUN set -eux; \
    mkdir -p storage/cache/htmlpurifier storage/database storage/logs storage/uploads; \
    chown -R www-data:www-data /var/www/html; \
    find storage -type d -exec chmod 775 {} \;; \
    find storage -type f -exec chmod 664 {} \;; \
    composer dump-autoload --optimize --no-interaction

EXPOSE 80

CMD ["sh", "-lc", "mkdir -p storage/cache/htmlpurifier storage/database storage/logs storage/uploads && touch storage/database/quenza.db && chown -R www-data:www-data storage bootstrap public quenza_core database config bin && if [ ! -f vendor/autoload.php ]; then composer install --prefer-dist --no-interaction --no-progress --optimize-autoloader; fi && apache2-foreground"]
