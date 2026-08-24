# syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1-php8.5 AS base

RUN install-php-extensions pdo_pgsql opcache intl zip pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app


# Build stage: needs Node for Vite AND PHP for Wayfinder, which generates
# resources/js/routes + resources/js/actions by running artisan during the build.
FROM base AS build

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .

# Dummy key so artisan can boot for package:discover and wayfinder:generate;
# the real APP_KEY comes from Railway's environment at runtime.
ENV APP_ENV=production \
    APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=

RUN composer dump-autoload --optimize --no-dev

RUN npm ci \
    && npm run build \
    && rm -rf node_modules


# Runtime stage
FROM base AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'memory_limit = 256M'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.validate_timestamps = 0'; \
        echo 'opcache.memory_consumption = 192'; \
    } > "$PHP_INI_DIR/conf.d/app.ini"

COPY --from=build /app /app

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s \
    CMD curl -fsS "http://localhost:${PORT:-8080}/up" || exit 1

ENTRYPOINT ["docker/entrypoint.sh"]
