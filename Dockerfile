# FrankenPHP bundles a modern PHP runtime and web server in one image,
# which keeps the application container small and the setup reproducible.
FROM dunglas/frankenphp:1-php8.4

RUN install-php-extensions \
    pdo_pgsql \
    intl \
    opcache \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Application code (vendor/ and var/ are excluded via .dockerignore).
COPY . .

# Install from the committed composer.lock for a reproducible build. Dev
# dependencies are included so the test suite can run inside the container.
RUN composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader \
    && mkdir -p var \
    && chmod -R 777 var

COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
