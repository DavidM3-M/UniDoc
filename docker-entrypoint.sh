#!/bin/sh
set -e

# Railway asigna un puerto dinámico vía $PORT; Apache debe escuchar en él.
PORT="${PORT:-80}"

echo ">> Configurando Apache para escuchar en el puerto $PORT"

# Ajustar la directiva Listen en ports.conf
sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf

# Ajustar el VirtualHost en todos los sites habilitados
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/*.conf

# Asegurar que Apache puede seguir symlinks (necesario para /storage/)
sed -i 's/Options -Indexes$/Options -Indexes +FollowSymLinks/' /etc/apache2/conf-enabled/docker-php.conf 2>/dev/null || true
sed -i 's/Options Indexes$/Options Indexes +FollowSymLinks/' /etc/apache2/conf-enabled/docker-php.conf 2>/dev/null || true
# Si no hay línea Options, agregarla después de AllowOverride All
grep -q 'FollowSymLinks' /etc/apache2/conf-enabled/docker-php.conf 2>/dev/null || sed -i 's/AllowOverride All/Options +FollowSymLinks\n        AllowOverride All/' /etc/apache2/conf-enabled/docker-php.conf 2>/dev/null || true

# Asegurar permisos de escritura en storage y cache (por si el log fue creado por root)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Recrear el symlink storage con ruta Linux correcta (el del repo apunta a ruta Windows)
php artisan storage:link --force || true

# Cachés de Laravel (no requieren DB, mejoran el tiempo de arranque en frío)
php artisan config:cache  || true
php artisan route:cache   || true
php artisan view:cache    || true

exec apache2-foreground
