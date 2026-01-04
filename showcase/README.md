# PHP Array Shapes Showcase

Real-world framework demos comparing **standard PHP 8.5.1** vs **patched PHP 8.5.1** with typed arrays and array shapes.

## Structure

```
showcase/
├── laravel/
│   ├── standard/          # Laravel on vanilla PHP 8.5.1
│   └── patched/           # Laravel on PHP with array shapes
├── symfony/
│   ├── standard/          # Symfony on vanilla PHP 8.5.1
│   └── patched/           # Symfony on PHP with array shapes
├── Dockerfile.standard    # PHP 8.5.1 vanilla (Nginx + FPM + PostgreSQL)
├── Dockerfile.patched     # PHP 8.5.1 with array shapes
├── docker-compose.yml     # All services
└── src/demo.php           # Feature demo script
```

## Quick Start

### Build All Images

```bash
cd showcase
docker compose build
```

### Start Services

```bash
# Start all
docker compose up -d

# Or start specific framework
docker compose up -d laravel-standard laravel-patched
docker compose up -d symfony-standard symfony-patched
```

### Access URLs

| Framework | Variant | URL | PostgreSQL |
|-----------|---------|-----|------------|
| Laravel | Standard | http://localhost:8180 | localhost:5532 |
| Laravel | Patched | http://localhost:8181 | localhost:5533 |
| Symfony | Standard | http://localhost:8280 | localhost:5632 |
| Symfony | Patched | http://localhost:8281 | localhost:5633 |

## Create Framework Projects

### Laravel

```bash
# Standard PHP (array shapes will fail)
docker compose exec laravel-standard bash -c "cd /app && laravel new . --no-interaction"

# Patched PHP (array shapes work!)
docker compose exec laravel-patched bash -c "cd /app && laravel new . --no-interaction"
```

### Symfony

```bash
# Standard PHP
docker compose exec symfony-standard bash -c "cd /app && composer create-project symfony/skeleton . --no-interaction"

# Patched PHP
docker compose exec symfony-patched bash -c "cd /app && composer create-project symfony/skeleton . --no-interaction"
```

## Run Demo Script

```bash
# Test on standard PHP (fails with syntax error)
docker compose exec laravel-standard php /demo/demo.php

# Test on patched PHP (works!)
docker compose exec laravel-patched php /demo/demo.php
```

## What's Different?

| Feature | Standard PHP | Patched PHP |
|---------|-------------|-------------|
| `array<int>` | ❌ Syntax Error | ✅ Runtime validated |
| `array<string, User>` | ❌ Syntax Error | ✅ Key + value types |
| `array{id: int, name: string}` | ❌ Syntax Error | ✅ Shape validated |
| `array{...}!` (closed) | ❌ Syntax Error | ✅ Extra keys rejected |
| `shape User = array{...}` | ❌ Syntax Error | ✅ Type alias |

## Container Details

Each container includes:
- **PHP 8.5.1** (CLI + FPM on Unix socket)
- **Nginx** (port 80)
- **PostgreSQL 15** (port 5432)
- **Composer** + framework installer

## Commands Reference

```bash
# Build
docker compose build

# Start all
docker compose up -d

# Start Laravel only
docker compose up -d laravel-standard laravel-patched

# Shell access
docker compose exec laravel-patched bash
docker compose exec symfony-patched bash

# View logs
docker compose logs -f laravel-patched

# Stop all
docker compose down

# Remove everything
docker compose down -v --rmi all
```

## Database Credentials

Each container has its own PostgreSQL with pre-configured database:

| Framework | Database | User | Password |
|-----------|----------|------|----------|
| Laravel | laravel | laravel | laravel |
| Symfony | symfony | symfony | symfony |
