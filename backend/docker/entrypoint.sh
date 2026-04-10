#!/bin/sh
set -e
if [ ! -f .env ]; then
  cp .env.example .env
fi
if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  php artisan key:generate --force
fi
php artisan config:clear || true
php artisan migrate --force
php artisan storage:link || true
php artisan queue:work redis --queue=scans,fits,vton-gpu,assets,default --sleep=1 --tries=3 --timeout=180 &
exec php artisan serve --host=0.0.0.0 --port=8000
