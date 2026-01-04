#!/bin/bash
set -e

# Get framework name from environment (default to laravel)
FRAMEWORK=${FRAMEWORK:-laravel}
DB_NAME=${DB_DATABASE:-$FRAMEWORK}
DB_USER=${DB_USERNAME:-$FRAMEWORK}
DB_PASS=${DB_PASSWORD:-$FRAMEWORK}

# Create log directories
mkdir -p /var/log/supervisor /var/log/php /var/log/nginx /var/log/postgresql
chown -R postgres:postgres /var/log/postgresql

# Ensure PHP-FPM socket directory exists
mkdir -p /var/run/php
chown www-data:www-data /var/run/php

# Ensure PostgreSQL directories exist and have correct permissions
mkdir -p /var/run/postgresql
chown -R postgres:postgres /var/run/postgresql

# Initialize PostgreSQL if not already initialized
if [ ! -f /var/lib/postgresql/15/main/PG_VERSION ]; then
    echo "Initializing PostgreSQL database..."

    # Create the data directory
    mkdir -p /var/lib/postgresql/15/main
    chown -R postgres:postgres /var/lib/postgresql/15
    chmod 700 /var/lib/postgresql/15/main

    # Initialize the database
    sudo -u postgres /usr/lib/postgresql/15/bin/initdb -D /var/lib/postgresql/15/main

    # Configure PostgreSQL to listen on localhost
    echo "listen_addresses = 'localhost'" >> /var/lib/postgresql/15/main/postgresql.conf
    echo "port = 5432" >> /var/lib/postgresql/15/main/postgresql.conf

    # Configure authentication
    echo "local all all trust" > /var/lib/postgresql/15/main/pg_hba.conf
    echo "host all all 127.0.0.1/32 trust" >> /var/lib/postgresql/15/main/pg_hba.conf
    echo "host all all ::1/128 trust" >> /var/lib/postgresql/15/main/pg_hba.conf

    echo "PostgreSQL initialized successfully."
fi

# Create database user and database
create_database() {
    sleep 3  # Wait for PostgreSQL to start
    sudo -u postgres psql -c "CREATE USER ${DB_USER} WITH PASSWORD '${DB_PASS}';" 2>/dev/null || true
    sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};" 2>/dev/null || true
    sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};" 2>/dev/null || true
    echo "Database '${DB_NAME}' created for user '${DB_USER}'."
}

# Run database creation in background after services start
create_database &

# Set proper permissions for app directory
chown -R www-data:www-data /app

# Display info
echo "=============================================="
echo "Framework: ${FRAMEWORK} (${PHP_VARIANT:-unknown})"
echo "PHP Version:"
php -v | head -1
echo "=============================================="
echo "Nginx + PHP-FPM (Unix Socket) + PostgreSQL"
echo "Database: ${DB_NAME}"
echo "=============================================="

# Execute the command passed to docker run
exec "$@"
