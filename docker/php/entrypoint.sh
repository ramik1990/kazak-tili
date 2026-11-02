#!/bin/bash
set -e

mkdir -p public/build && cp -r /opt/build/. public/build/
mkdir -p storage/app/public/audio

if [ "$1" = "php-fpm" ]; then
    if ! grep -qE '^APP_KEY=.+' .env; then
        php artisan key:generate --force
    fi

    until php artisan migrate --force >/dev/null 2>&1; do
        echo "waiting for database..."
        sleep 3
    done

    php artisan db:seed --force >/dev/null 2>&1 || true
    php artisan storage:link >/dev/null 2>&1 || true
    php artisan config:cache
    php artisan route:cache
fi

exec "$@"
