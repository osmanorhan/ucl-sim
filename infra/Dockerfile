# syntax=docker/dockerfile:1

# 1. Build the SPA. VITE_API_BASE_URL is empty so the client calls the API
#    same-origin (/api/*) — this image serves both, so CORS never enters the picture.
FROM node:24-alpine AS web
RUN corepack enable
WORKDIR /web
COPY apps/web/package.json apps/web/pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile
COPY apps/web/ ./
ENV VITE_API_BASE_URL=""
RUN pnpm build

# 2. Resolve PHP dependencies without dev tooling.
FROM composer:2 AS vendor
WORKDIR /api
COPY apps/api/composer.json apps/api/composer.lock ./
RUN composer install \
      --no-dev --no-scripts --no-interaction --no-progress \
      --prefer-dist --ignore-platform-reqs

# 3. Runtime: FrankenPHP serves Laravel and the built SPA from one process.
FROM dunglas/frankenphp:1-php8.4 AS runtime
RUN install-php-extensions pdo_sqlite opcache
WORKDIR /app

COPY apps/api/ /app/
COPY --from=vendor /api/vendor /app/vendor
COPY --from=web /web/dist/ /app/public/
COPY infra/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
 && php artisan package:discover --ansi \
 && chown -R www-data:www-data storage bootstrap/cache

ENV SERVER_NAME=:8080

EXPOSE 8080
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=5 \
  CMD curl -fsS http://localhost:8080/up || exit 1
ENTRYPOINT ["entrypoint"]
