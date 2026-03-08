FROM php:8.2-apache

# ─── Dependencias del sistema ─────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libmagickwand-dev \
    imagemagick \
    ghostscript \
    && rm -rf /var/lib/apt/lists/*

# Permitir que ImageMagick procese PDFs (necesario para spatie/pdf-to-image)
RUN sed -i 's/<policy domain="coder" rights="none" pattern="PDF" \/>/<policy domain="coder" rights="read|write" pattern="PDF" \/>/' /etc/ImageMagick-6/policy.xml || true

# ─── Extensiones PHP ──────────────────────────────────────────────────────
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        xml \
        dom \
        fileinfo \
        opcache

# imagick via PECL (requerido por spatie/pdf-to-image ^1.2)
RUN pecl install imagick && docker-php-ext-enable imagick

# ─── OPcache (producción) ─────────────────────────────────────────────────
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=60'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache.ini

# ─── Apache: habilitar mod_rewrite ───────────────────────────────────────
RUN a2enmod rewrite

# ─── Composer ────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ─── Código de la aplicación ─────────────────────────────────────────────
WORKDIR /var/www/html

# Copiar manifiestos primero para aprovechar la caché de capas
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copiar el resto del código fuente
COPY . .

# Ejecutar scripts post-instalación de Composer
RUN composer run-script post-autoload-dump

# ─── Permisos ────────────────────────────────────────────────────────────
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# ─── Apache: apuntar DocumentRoot a /public ──────────────────────────────
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf

COPY docker-entrypoint.sh /docker-entrypoint.sh
# Eliminar CRLF (Windows) para que el script funcione en Linux
RUN sed -i 's/\r$//' /docker-entrypoint.sh && chmod +x /docker-entrypoint.sh

# Render usa 10000 por defecto; se sobreescribe con la env var PORT en runtime
EXPOSE 10000
ENTRYPOINT ["/docker-entrypoint.sh"]
