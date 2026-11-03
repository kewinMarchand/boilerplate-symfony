FROM dunglas/frankenphp:1-php8.5-bookworm AS base
WORKDIR /app
RUN install-php-extensions intl opcache zip
COPY --from=composer/composer:2-bin /composer /usr/bin/composer
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
ENV COMPOSER_HOME=/tmp/composer \
    XDG_CONFIG_HOME=/tmp/caddy/config \
    XDG_DATA_HOME=/tmp/caddy/data
EXPOSE 8080
HEALTHCHECK --interval=5s --timeout=3s --retries=20 CMD curl -fsS -o /dev/null http://${SERVER_ADDRESS:-localhost:8080}/robots.txt || exit 1
CMD ["sh", "-c", "php docker/startup.php; exec frankenphp run --config /etc/frankenphp/Caddyfile"]

FROM base AS dev
ENV APP_ENV=dev
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

FROM base AS vendor
ENV APP_ENV=prod
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-progress --prefer-dist

FROM base AS prod
ENV APP_ENV=prod APP_DEBUG=0
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php-prod.ini "$PHP_INI_DIR/conf.d/zz-prod.ini"
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && composer dump-env prod \
    && composer run-script --no-dev post-install-cmd \
    && bin/console asset-map:compile \
    && mkdir -p /tmp/caddy \
    && chown -R www-data:www-data var /tmp/caddy
USER www-data
