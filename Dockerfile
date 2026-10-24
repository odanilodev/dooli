FROM php:8.3.36-cli

LABEL maintainer="cursos-api"

ENV DEBIAN_FRONTEND=noninteractive \
    TZ=America/Sao_Paulo

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        zip \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        libicu-dev \
        default-mysql-client \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        gd \
        zip \
        intl \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN printf "date.timezone=America/Sao_Paulo\n" > /usr/local/etc/php/conf.d/zz-timezone.ini \
    && printf "memory_limit=512M\n" > /usr/local/etc/php/conf.d/zz-memory.ini \
    && printf "opcache.enable_cli=1\nopcache.validate_timestamps=1\nopcache.revalidate_freq=2\n" > /usr/local/etc/php/conf.d/zz-opcache.ini \
    && printf "upload_max_filesize=24M\npost_max_size=25M\n" > /usr/local/etc/php/conf.d/zz-upload.ini

WORKDIR /var/www/html

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
