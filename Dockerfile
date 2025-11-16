# Dockerfile para Laravel (PHP 8.2 + Apache) con poppler (pdftotext)
FROM php:8.2-apache

# Evitar preguntas interactiva
ARG DEBIAN_FRONTEND=noninteractive

# Instalar utilidades de sistema y dependencias PHP necesarias
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    pkg-config \
    locales \
    poppler-utils \    
    ca-certificates \
 && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP requeridas por Laravel
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd intl zip

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Copiar composer desde la imagen oficial de composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiar el código de la aplicación
COPY . /var/www/html

# Instalar dependencias PHP (producción)
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader || composer install --no-interaction

# Permisos para storage y cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

# Si usas assets (Vite) puedes construirlos aquí — opción segura:
# Nota: instalar node/npm en la imagen apt puede tener versiones viejas; si necesitas builds más complejos
# podemos usar una multi-stage build con node:18-alpine. Por ahora intento un build simple si existe package.json.
RUN if [ -f package.json ]; then \
      apt-get update && apt-get install -y nodejs npm && \
      npm ci && npm run build || true; \
    fi

EXPOSE 80

# Comando por defecto para Apache en foreground
CMD ["apache2-foreground"]
