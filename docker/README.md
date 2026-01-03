# PHP 8.5.1 with Array Shapes - Docker

Docker image containing PHP 8.5.1 with native array shapes support.

## Quick Start

### Build the image

```bash
./build.sh
```

This builds `php-array-shapes:8.5` with both CLI and FPM.

### Run PHP CLI

```bash
# Run a script
docker run --rm -v $(pwd):/app -w /app php-array-shapes:8.5 php your-script.php

# Interactive mode
docker run --rm -it php-array-shapes:8.5 php -a

# Check version
docker run --rm php-array-shapes:8.5 php -v
```

### Run PHP-FPM with Nginx

```bash
# Start services
docker-compose up -d

# Visit http://localhost:8080/

# Stop services
docker-compose down
```

### Use as base image

```dockerfile
FROM php-array-shapes:8.5

COPY . /var/www/html
WORKDIR /var/www/html

CMD ["php-fpm", "-F"]
```

## Features Included

- **PHP 8.5.1** with array shapes patch
- **PHP-FPM** for web serving
- **PHP CLI** for command line
- **Extensions**: opcache, mbstring, curl, openssl, pdo_mysql, pdo_sqlite, mysqli, zip, gd, sodium, intl, bcmath, pcntl, sockets

## Array Shapes Syntax

```php
<?php
declare(strict_arrays=1);

// Typed arrays
function getIds(): array<int> {
    return [1, 2, 3];
}

// Array of objects
function getUsers(): array<User> {
    return [new User(1), new User(2)];
}

// Array shapes
function getConfig(): array{host: string, port: int, ssl?: bool} {
    return ['host' => 'localhost', 'port' => 3306];
}

// Shape aliases
shape UserData = array{id: int, name: string, email: string};

function fetchUser(int $id): UserData {
    return ['id' => $id, 'name' => 'Alice', 'email' => 'alice@example.com'];
}
```

## File Structure

```
docker/
├── Dockerfile          # Multi-stage build for PHP
├── docker-compose.yml  # FPM + Nginx setup
├── nginx.conf          # Nginx configuration
├── build.sh            # Build script
├── app/                # Sample application
│   └── index.php       # Demo page
└── README.md           # This file
```
