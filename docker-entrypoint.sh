#!/bin/sh
set -e

# Railway asigna un puerto dinámico vía $PORT; Apache debe escuchar en él.
PORT="${PORT:-80}"

echo ">> Configurando Apache para escuchar en el puerto $PORT"

# Ajustar la directiva Listen en ports.conf
sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf

# Ajustar el VirtualHost en todos los sites habilitados
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/*.conf

# Cachés de Laravel (no requieren DB, mejoran el tiempo de arranque en frío)
php artisan config:cache  || true
php artisan route:cache   || true
php artisan view:cache    || true

exec apache2-foreground
