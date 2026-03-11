#!/bin/bash
set -e

echo "[entrypoint] Starting application..."

# Run migrations (non-blocking — server starts even if DB is temporarily unavailable)
echo "[entrypoint] Running migrations..."
php artisan migrate --force 2>&1 || echo "[entrypoint] WARNING: Migration failed, continuing anyway"

# Clear and rebuild caches
echo "[entrypoint] Caching config..."
php artisan config:clear 2>/dev/null
php artisan config:cache 2>&1 || echo "[entrypoint] WARNING: Config cache failed"
php artisan route:cache 2>&1 || echo "[entrypoint] WARNING: Route cache failed"
php artisan view:cache 2>&1 || echo "[entrypoint] WARNING: View cache failed"

# Create storage link if not exists
php artisan storage:link 2>/dev/null || true

# Start the server — use PORT from Railway, default 8080
PORT="${PORT:-8080}"
echo "[entrypoint] Starting server on port $PORT"
exec php artisan serve --host=0.0.0.0 --port="$PORT"
