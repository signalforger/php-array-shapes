# PHP 8.5.1 with Typed Arrays & Array Shapes
# Multi-stage build for minimal image size

# =============================================================================
# Stage 1: Builder - Compile PHP from source
# =============================================================================
FROM debian:bookworm-slim AS builder

# Install build dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    autoconf \
    bison \
    build-essential \
    ca-certificates \
    curl \
    git \
    libcurl4-openssl-dev \
    libonig-dev \
    libreadline-dev \
    libsqlite3-dev \
    libssl-dev \
    libxml2-dev \
    libzip-dev \
    pkg-config \
    re2c \
    zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

# Clone PHP source with array shapes feature
ARG PHP_BRANCH=feature/array-shapes
ARG PHP_REPO=https://github.com/signalforger/php-src.git

WORKDIR /usr/src/php
RUN git clone --depth 1 --branch ${PHP_BRANCH} ${PHP_REPO} .

# Generate configure script
RUN ./buildconf --force

# Configure PHP with common extensions
RUN ./configure \
    --prefix=/usr/local/php \
    --with-config-file-path=/usr/local/php/etc \
    --with-config-file-scan-dir=/usr/local/php/etc/conf.d \
    --enable-bcmath \
    --enable-fpm \
    --enable-mbstring \
    --enable-opcache \
    --enable-pcntl \
    --enable-sockets \
    --with-curl \
    --with-openssl \
    --with-pdo-mysql \
    --with-pdo-sqlite \
    --with-readline \
    --with-zip \
    --with-zlib \
    --disable-cgi \
    --disable-phpdbg

# Compile PHP (use all available cores)
RUN make -j$(nproc)

# Install to prefix
RUN make install

# Create config directories
RUN mkdir -p /usr/local/php/etc/conf.d

# Copy default php.ini
RUN cp php.ini-production /usr/local/php/etc/php.ini

# =============================================================================
# Stage 2: Runtime - Minimal image with compiled PHP
# =============================================================================
FROM debian:bookworm-slim AS runtime

# Install runtime dependencies only
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    libcurl4 \
    libonig5 \
    libreadline8 \
    libsqlite3-0 \
    libssl3 \
    libxml2 \
    libzip4 \
    zlib1g \
    && rm -rf /var/lib/apt/lists/* \
    && apt-get clean

# Copy compiled PHP from builder
COPY --from=builder /usr/local/php /usr/local/php

# Add PHP to PATH
ENV PATH="/usr/local/php/bin:/usr/local/php/sbin:${PATH}"

# Create symbolic links for convenience
RUN ln -s /usr/local/php/bin/php /usr/local/bin/php \
    && ln -s /usr/local/php/bin/phpize /usr/local/bin/phpize \
    && ln -s /usr/local/php/bin/php-config /usr/local/bin/php-config \
    && ln -s /usr/local/php/sbin/php-fpm /usr/local/bin/php-fpm

# Create non-root user for running PHP
RUN useradd -r -s /bin/false -d /app php

# Set working directory
WORKDIR /app

# Default command: show PHP version with array shapes info
CMD ["php", "-v"]

# =============================================================================
# Stage 3: CLI variant (default)
# =============================================================================
FROM runtime AS cli

USER php
CMD ["php", "-a"]

# =============================================================================
# Stage 4: FPM variant
# =============================================================================
FROM runtime AS fpm

# Copy FPM configuration
RUN cp /usr/local/php/etc/php-fpm.conf.default /usr/local/php/etc/php-fpm.conf \
    && cp /usr/local/php/etc/php-fpm.d/www.conf.default /usr/local/php/etc/php-fpm.d/www.conf \
    && sed -i 's/user = nobody/user = php/' /usr/local/php/etc/php-fpm.d/www.conf \
    && sed -i 's/group = nobody/group = php/' /usr/local/php/etc/php-fpm.d/www.conf \
    && sed -i 's/listen = 127.0.0.1:9000/listen = 9000/' /usr/local/php/etc/php-fpm.d/www.conf

EXPOSE 9000

CMD ["php-fpm", "-F"]
