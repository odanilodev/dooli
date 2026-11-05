#!/bin/sh
set -e

mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

# php-fpm must start as root so its master can bind and drop workers to www-data.
# Every other command (artisan, queue, scheduler) runs as www-data.
if [ "$1" = "php-fpm" ]; then
    exec "$@"
fi

if [ "$(id -u)" = "0" ]; then
    exec setpriv --reuid=www-data --regid=www-data --init-groups "$@"
fi

exec "$@"
