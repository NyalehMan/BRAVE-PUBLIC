# syntax=docker/dockerfile:1.7

FROM node:22-bookworm-slim AS frontend

WORKDIR /workspace/frontend

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./

ENV VITE_PUBLIC_DEPLOY=false
ENV VITE_BASE_PATH=/
ENV NODE_OPTIONS=--max-old-space-size=4096

RUN --mount=type=secret,id=maptiler_key \
    test -s /run/secrets/maptiler_key && \
    export VITE_MAPTILER_API_KEY="$(cat /run/secrets/maptiler_key)" && \
    npm run build -- --outDir /build/public --emptyOutDir

FROM composer:2 AS php_vendor

WORKDIR /app

COPY backend/ ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --classmap-authoritative

FROM php:8.4-apache-bookworm AS runtime

ENV TESSERACT_PATH=/usr/bin/tesseract

LABEL org.opencontainers.image.source="https://github.com/NyalehMan/BRAVE-PUBLIC"
LABEL org.opencontainers.image.description="BRAVE Laravel API and Vue application"

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libwebp-dev \
        libzip-dev \
        tesseract-ocr \
        tesseract-ocr-eng \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        mbstring \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        zip \
    && docker-php-ext-enable opcache \
    && a2enmod rewrite headers expires \
    && sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && rm -rf /var/lib/apt/lists/*

COPY backend/docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY backend/docker/php-production.ini /usr/local/etc/php/conf.d/zz-brave-production.ini
COPY backend/docker/entrypoint.sh /usr/local/bin/brave-entrypoint

COPY --from=php_vendor --chown=www-data:www-data /app /var/www/html
COPY --from=frontend --chown=www-data:www-data /build/public /var/www/html/public

RUN chmod +x /usr/local/bin/brave-entrypoint \
    && mkdir -p \
        /var/www/html/storage/app/private/public_reports \
        /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
    && chown -R www-data:www-data \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache

WORKDIR /var/www/html

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl --fail --silent http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["brave-entrypoint"]
CMD ["apache2-foreground"]
