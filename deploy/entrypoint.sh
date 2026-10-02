#!/bin/sh

set -e

cd /var/www/html

attempt=1
until php artisan migrate --force --no-interaction; do
    if [ "$attempt" -ge 5 ]; then
        echo "Database migration failed after ${attempt} attempts."
        exit 1
    fi

    attempt=$((attempt + 1))
    echo "Database not ready, retrying (${attempt}/5)..."
    sleep 5
done

php artisan storage:link || true
php artisan staging:admin
php artisan config:cache

exec apache2-foreground
