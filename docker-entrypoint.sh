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

# ─── Migraciones (siempre — Laravel rastrea las ejecutadas) ──────────────
echo ">> Ejecutando migraciones..."
php artisan migrate --force || true

# ─── Seeders (solo si la DB está vacía — tabla roles sin datos) ──────────
# Consulta directa vía PDO para no depender de archivos centinela.
# Si la tabla roles tiene 0 filas → primer arranque → ejecutar seeders.
ROLES_COUNT=$(php -r "
try {
    \$host = getenv('DB_HOST')     ?: 'localhost';
    \$port = getenv('DB_PORT')     ?: '5432';
    \$db   = getenv('DB_DATABASE') ?: 'banco_oferentes_db';
    \$user = getenv('DB_USERNAME') ?: 'unidoc';
    \$pass = getenv('DB_PASSWORD') ?: 'unidoc';
    \$pdo  = new PDO(\"pgsql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass);
    echo \$pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
} catch (Exception \$e) { echo 0; }
" 2>/dev/null || echo "0")

if [ -z "$ROLES_COUNT" ] || [ "$ROLES_COUNT" -eq 0 ] 2>/dev/null; then
    echo ">> Base de datos vacía. Ejecutando seeders..."
    php artisan db:seed --force \
        && echo ">> Seeders completados. Base de datos lista." \
        || echo ">> Advertencia: los seeders fallaron. Revise los logs."
else
    echo ">> Base de datos ya inicializada ($ROLES_COUNT roles). Saltando seeders."
fi

# Cachés de Laravel (no requieren DB, mejoran el tiempo de arranque en frío)
php artisan config:cache  || true
php artisan route:cache   || true
php artisan view:cache    || true

exec apache2-foreground
