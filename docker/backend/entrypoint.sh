#!/bin/sh
set -e

# vendor/ lives in a named volume, so dependencies are installed inside the container.
composer install --no-interaction --prefer-dist

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
fi

exec "$@"
