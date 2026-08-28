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

# ─── Worker de colas ────────────────────────────────────────────────────
# La carga masiva del SNIES no importa nada durante la petición: SniesImportacionController
# guarda el .xlsx, despacha ImportarSniesJob y responde de inmediato. QUEUE_CONNECTION no está
# definido en .env, así que cae al valor por defecto `database` y el trabajo se inserta como una
# fila en la tabla `jobs`. Sin nadie consumiéndola, ahí se queda para siempre y la importación
# nunca sale de "pendiente": no falla, no avisa, simplemente no pasa nada.
#
# El worker va dentro de esta imagen para que viaje con ella. El docker-compose.yml que levanta
# el servicio `queue-worker` vive en la raíz del monorepo, que no está versionada: quien clone
# solo este repositorio obtiene el Dockerfile y este entrypoint, pero ninguna cola procesándose.
# Ese es exactamente el caso en el que la importación "no sirve" sin dar ningún error.
#
# RUN_QUEUE_WORKER=false lo apaga. Lo usa el monorepo en su servicio `backend`, que ya tiene un
# contenedor `queue-worker` dedicado. Dos workers sobre la misma cola no corrompen nada —Laravel
# reserva cada trabajo de forma atómica— pero duplican memoria sin ganar nada.
if [ "${RUN_QUEUE_WORKER:-true}" = "true" ]; then
    echo ">> Iniciando worker de colas en segundo plano"

    # El bucle es la supervisión. `--max-time=3600` hace que el worker termine solo cada hora
    # para soltar la memoria que PHP no devuelve en procesos largos, y el bucle lo vuelve a
    # levantar; lo mismo si algo lo mata. Sin él, el contenedor seguiría reportándose sano
    # —Apache vivo— con la cola parada, que es la peor forma de fallar: en silencio.
    #
    # Corre como www-data, el mismo usuario de Apache. Como root escribiría storage/logs y
    # bootstrap/cache con propietario root, y a partir de ahí Apache ya no podría escribir ahí.
    su -s /bin/sh www-data -c '
        while true; do
            php /var/www/html/artisan queue:work \
                --tries=3 --timeout=1800 --sleep=3 --max-time=3600
            echo ">> El worker de colas terminó; reiniciando en 5 s"
            sleep 5
        done
    ' &
fi

exec apache2-foreground
