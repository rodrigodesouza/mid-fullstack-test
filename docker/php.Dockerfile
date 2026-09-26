FROM php:8.4-cli

# Instala dependências do sistema e bibliotecas para extensões PHP
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    zip \
    unzip

# Instala as extensões PHP (incluindo intl e zip)
RUN docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd intl zip
RUN pecl install redis && docker-php-ext-enable redis
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Instala o Composer oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
