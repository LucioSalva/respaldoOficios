FROM php:8.2-apache

# Instalar dependencias del sistema
# curl: necesario para el healthcheck del contenedor (docker-compose)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    opcache

# Habilitar mod_rewrite para URLs limpias
RUN a2enmod rewrite

# Configurar PHP para producción
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Ajustes PHP personalizados
RUN echo "upload_max_filesize = 12M" >> "$PHP_INI_DIR/php.ini" \
 && echo "post_max_size = 13M" >> "$PHP_INI_DIR/php.ini" \
 && echo "max_execution_time = 60" >> "$PHP_INI_DIR/php.ini" \
 && echo "memory_limit = 128M" >> "$PHP_INI_DIR/php.ini" \
 && echo "session.cookie_httponly = 1" >> "$PHP_INI_DIR/php.ini" \
 && echo "session.cookie_samesite = Strict" >> "$PHP_INI_DIR/php.ini" \
 && echo "session.use_strict_mode = 1" >> "$PHP_INI_DIR/php.ini" \
 && echo "expose_php = Off" >> "$PHP_INI_DIR/php.ini"

# Configurar Apache para el directorio public
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' \
    /etc/apache2/sites-available/000-default.conf

# Agregar configuración AllowOverride para mod_rewrite
RUN echo '<Directory /var/www/html/public>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/sites-available/000-default.conf

# Headers de seguridad en Apache
RUN a2enmod headers && \
    echo 'Header always set X-Frame-Options "SAMEORIGIN"' >> /etc/apache2/conf-available/security.conf && \
    echo 'Header always set X-Content-Type-Options "nosniff"' >> /etc/apache2/conf-available/security.conf && \
    echo 'Header always set X-XSS-Protection "1; mode=block"' >> /etc/apache2/conf-available/security.conf && \
    echo 'Header always set Referrer-Policy "strict-origin-when-cross-origin"' >> /etc/apache2/conf-available/security.conf && \
    a2enconf security

# Crear directorio de uploads con permisos
RUN mkdir -p /var/www/uploads && \
    chown www-data:www-data /var/www/uploads && \
    chmod 750 /var/www/uploads

# Copiar código fuente
COPY src/ /var/www/html/

# Permisos correctos
RUN chown -R www-data:www-data /var/www/html && \
    find /var/www/html -type f -exec chmod 644 {} \; && \
    find /var/www/html -type d -exec chmod 755 {} \;

EXPOSE 80
