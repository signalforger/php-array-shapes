#===============================================================================
# PHP 8.5 with Array Shapes Support
# Multi-stage build for smaller final image
#===============================================================================

FROM debian:bookworm-slim AS builder

# Build dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    autoconf \
    automake \
    libtool \
    bison \
    re2c \
    pkg-config \
    git \
    curl \
    ca-certificates \
    libxml2-dev \
    libsqlite3-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    libreadline-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libssl-dev \
    libsodium-dev \
    libargon2-dev \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /build

# Clone PHP 8.5.1
ARG PHP_VERSION=php-8.5.1
RUN git clone --depth 1 --branch ${PHP_VERSION} https://github.com/php/php-src.git php-src

# Copy and apply array shapes patch
COPY patches/array-shapes.patch /build/
RUN cd php-src && git apply --verbose /build/array-shapes.patch || \
    (echo "Patch failed, trying with --3way" && git apply --3way /build/array-shapes.patch)

# Build PHP
WORKDIR /build/php-src

RUN ./buildconf --force

RUN ./configure \
    --prefix=/usr/local/php \
    --with-config-file-path=/usr/local/php/etc \
    --with-config-file-scan-dir=/usr/local/php/etc/conf.d \
    --enable-fpm \
    --with-fpm-user=www-data \
    --with-fpm-group=www-data \
    --enable-cli \
    --enable-mbstring \
    --enable-opcache \
    --enable-bcmath \
    --enable-pcntl \
    --enable-sockets \
    --enable-intl \
    --with-curl \
    --with-openssl \
    --with-readline \
    --with-zlib \
    --with-zip \
    --with-pdo-mysql \
    --with-pdo-sqlite \
    --with-mysqli \
    --with-sodium \
    --with-password-argon2 \
    --with-freetype \
    --with-jpeg \
    --disable-cgi \
    --disable-phpdbg

RUN make -j$(nproc)
RUN make install

# Create php.ini
RUN cp php.ini-development /usr/local/php/etc/php.ini
RUN mkdir -p /usr/local/php/etc/conf.d

# Create php-fpm.conf
RUN cp /usr/local/php/etc/php-fpm.conf.default /usr/local/php/etc/php-fpm.conf
RUN cp /usr/local/php/etc/php-fpm.d/www.conf.default /usr/local/php/etc/php-fpm.d/www.conf

# Adjust php-fpm to listen on port
RUN sed -i 's/listen = 127.0.0.1:9000/listen = 9000/' /usr/local/php/etc/php-fpm.d/www.conf
RUN sed -i 's/;listen.allowed_clients/listen.allowed_clients/' /usr/local/php/etc/php-fpm.d/www.conf

#===============================================================================
# Final runtime image
#===============================================================================

FROM debian:bookworm-slim AS runtime

# Runtime dependencies only
RUN apt-get update && apt-get install -y --no-install-recommends \
    libxml2 \
    libsqlite3-0 \
    libcurl4 \
    libonig5 \
    libreadline8 \
    libzip4 \
    libpng16-16 \
    libjpeg62-turbo \
    libfreetype6 \
    libssl3 \
    libsodium23 \
    libargon2-1 \
    libicu72 \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && useradd -r -s /bin/false www-data 2>/dev/null || true

# Copy PHP from builder
COPY --from=builder /usr/local/php /usr/local/php

# Create symlinks
RUN ln -s /usr/local/php/bin/php /usr/local/bin/php \
    && ln -s /usr/local/php/bin/phpize /usr/local/bin/phpize \
    && ln -s /usr/local/php/bin/php-config /usr/local/bin/php-config \
    && ln -s /usr/local/php/sbin/php-fpm /usr/local/bin/php-fpm

# Create directories
RUN mkdir -p /var/www/html /var/log/php-fpm /run/php-fpm

# Set permissions
RUN chown -R www-data:www-data /var/www/html /var/log/php-fpm /run/php-fpm

WORKDIR /var/www/html

# Verify array shapes work
RUN php -r 'declare(strict_arrays=1); function test(): array<int> { return [1,2,3]; } var_dump(test());'

EXPOSE 9000

# Default to CLI, override with php-fpm for FPM
CMD ["php", "-v"]
