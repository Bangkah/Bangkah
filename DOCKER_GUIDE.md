# Docker Implementation Guide - Bangkah Laravel Starter

## 📖 Overview

Bangkah generates production-ready Docker configurations following best practices for Laravel applications. This guide explains the Docker implementation, optimizations, and how to use it effectively.

## 🏗️ Architecture

### Multi-Stage Build Strategy

The generated Dockerfile uses multi-stage builds to create optimized images:

```
composer:2 (base)
    ↓
php:8.4-fpm (php-base) ──→ php-fpm (production)
    ↓
php:8.4-cli (php-cli) ──→ app (development/cli)
```

### Services

1. **app** - PHP application (FPM or CLI mode)
2. **nginx** - Web server (optional, when using FPM)
3. **db** - Database (MySQL or PostgreSQL)
4. **node** - Frontend build tools (optional)

## 🚀 Quick Start

### Generate Project with Docker

```bash
# API with Nginx
php artisan bangkah:create --type=api --docker --nginx

# Web app with frontend
php artisan bangkah:create --type=web --docker --nginx --frontend=vue

# Simple CLI mode (no Nginx)
php artisan bangkah:create --docker
```

### Start Services

```bash
# Build and start all services
docker compose up -d

# View logs
docker compose logs -f

# Stop services
docker compose down
```

## 📁 Generated Files

### Dockerfile

Multi-stage Dockerfile optimized for:
- **Fast builds** via layer caching
- **Small images** with `--no-dev` flag
- **Security** with minimal dependencies

### docker-compose.yml

Defines services with:
- Environment variables
- Volume mounts
- Network configuration
- Port mappings

### docker/nginx/nginx.conf

Nginx configuration for Laravel with:
- PHP-FPM proxy
- Static asset serving
- Proper security headers

## 🔧 Dockerfile Optimization

### Layer Caching Strategy

The Dockerfile is structured to maximize Docker's layer caching:

```dockerfile
# Stage 1: Dependencies (cached unless composer files change)
COPY composer.json ./
COPY composer.lock* ./
RUN composer install --no-dev --no-scripts

# Stage 2: Application code (cached unless source changes)
COPY . .
RUN composer dump-autoload --optimize
```

**Benefits:**
- Dependencies only reinstall when `composer.json` changes
- Application changes don't invalidate dependency cache
- Faster development iterations

### Production Optimizations

```dockerfile
RUN composer install \
    --no-interaction \      # Non-interactive mode
    --prefer-dist \         # Download archives, not git repos
    --no-progress \         # No progress display (cleaner logs)
    --no-dev \             # Skip dev dependencies
    --no-scripts           # Skip post-install scripts
```

Then separately:
```dockerfile
RUN composer dump-autoload --optimize  # Optimized autoloader
```

### Handling Missing composer.lock

```dockerfile
COPY composer.lock* ./
```

The `*` wildcard makes the copy optional:
- If `composer.lock` exists → copies it
- If missing → silently skips (no error)

This handles fresh Laravel installations that may not have a lock file yet.

## 🐘 PHP Configuration

### FPM Mode (with Nginx)

```yaml
app:
  build:
    target: php-fpm
  expose:
    - "9000"
  environment:
    - APP_ENV=local
    - SESSION_DRIVER=file
```

**Use when:**
- Building web applications
- Need Nginx for static assets
- Production deployments
- High-traffic sites

### CLI Mode (standalone)

```yaml
app:
  build:
    target: php-cli
  ports:
    - "8000:8000"
  command: php artisan serve --host=0.0.0.0
```

**Use when:**
- Development/testing
- API-only applications
- Simple deployments
- Queue workers

## 🌐 Networking

### Port Mappings

| Service | Internal Port | Host Port | Purpose |
|---------|---------------|-----------|---------|
| Nginx | 80 | 8080 | HTTP traffic |
| App (CLI) | 8000 | 8000 | Dev server |
| Node | 5173 | 5173 | Vite HMR |
| MySQL | 3306 | - | Database (internal only) |
| PostgreSQL | 5432 | 5432 | Database |

### Internal DNS

Containers communicate via service names:
```env
DB_HOST=db          # Not 'localhost' or '127.0.0.1'
REDIS_HOST=redis
```

## 📦 Volumes

### Application Code

```yaml
volumes:
  - .:/var/www/html
```

**Purpose:** Live code reloading during development
**Note:** Remove in production, use only the built image

### Database Persistence

```yaml
volumes:
  - dbdata:/var/lib/mysql    # MySQL
  - pgdata:/var/lib/postgresql/data  # PostgreSQL
```

**Purpose:** Persist database data across container restarts

### Nginx Configuration

```yaml
volumes:
  - ./docker/nginx/nginx.conf:/etc/nginx/conf.d/default.conf
```

**Purpose:** Custom Nginx configuration

## 🔐 Environment Variables

### Auto-Generated Variables

The Docker services include these environment variables by default:

```yaml
environment:
  - APP_ENV=local
  - APP_DEBUG=true
  - APP_URL=http://localhost:8080
  - SESSION_DRIVER=file        # No DB needed for sessions
  - DB_CONNECTION=mysql
  - DB_HOST=db                 # Service name
  - DB_PORT=3306
  - DB_DATABASE=laravel
  - DB_USERNAME=root
  - DB_PASSWORD=               # Empty in dev
```

### Startup Bootstrap

The app container runs initialization on startup:

```bash
# Copy .env if it doesn't exist
if [ ! -f .env ]; then cp .env.example .env; fi

# Generate app key
php artisan key:generate --force

# Fix permissions
chown -R www-data:www-data storage bootstrap/cache

# Start service
php-fpm  # or: php artisan serve
```

## 🚀 Production Deployment

### Build Production Image

```bash
# Build optimized image
docker build --target php-fpm -t myapp:latest .

# Or with compose
docker compose -f docker-compose.prod.yml build
```

### Production Checklist

- [ ] Remove volume mounts (use built image)
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Use strong `APP_KEY`
- [ ] Configure proper database credentials
- [ ] Set up Redis for cache/session
- [ ] Configure queue workers
- [ ] Set up health checks
- [ ] Configure logging to stdout
- [ ] Use secrets management (not plain env vars)

### Example Production docker-compose.yml

```yaml
services:
  app:
    image: myapp:latest
    restart: always
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - APP_KEY=${APP_KEY}
    secrets:
      - db_password
    healthcheck:
      test: ["CMD", "php", "artisan", "health:check"]
      interval: 30s
      timeout: 10s
      retries: 3
```

## 🐛 Troubleshooting

### "COPY failed: composer.lock not found"

**Solution:** Update to Bangkah v1.0.3+ which uses `COPY composer.lock* ./`

### "bangkah/bangkah" removed during build

**Solution:** Update to v1.0.3+ which properly separates dependency installation from code copying

### Permission denied in storage/

**Solution:** The startup script runs `chown -R www-data:www-data storage bootstrap/cache`

If still failing:
```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### Database connection refused

**Check:**
1. `DB_HOST=db` (service name, not localhost)
2. Database service is running: `docker compose ps`
3. Wait for DB to be ready (add healthcheck or sleep)

```yaml
app:
  depends_on:
    db:
      condition: service_healthy
```

### Slow builds

**Solutions:**
1. Use `.dockerignore` to exclude unnecessary files:
   ```
   node_modules/
   vendor/
   .git/
   storage/logs/*
   ```

2. Enable BuildKit:
   ```bash
   DOCKER_BUILDKIT=1 docker build .
   ```

3. Use layer caching (v1.0.3+ does this automatically)

## 📈 Performance Tips

### 1. Multi-Stage Builds

Use different targets for different environments:
```bash
# Development
docker build --target php-cli -t myapp:dev .

# Production
docker build --target php-fpm -t myapp:prod .
```

### 2. Build Cache

```bash
# Use remote cache
docker buildx build --cache-from=type=registry,ref=myapp:cache .

# Save cache
docker buildx build --cache-to=type=registry,ref=myapp:cache .
```

### 3. Parallel Builds

```yaml
# docker-compose.yml
x-build-args: &build-args
  BUILDKIT_INLINE_CACHE: 1

services:
  app:
    build:
      args: *build-args
```

## 🔄 Development Workflow

### 1. Initial Setup

```bash
# Create project
php artisan bangkah:create --docker --nginx

# Start services
cd myproject
docker compose up -d

# Check status
docker compose ps
```

### 2. Daily Development

```bash
# View logs
docker compose logs -f app

# Run artisan commands
docker compose exec app php artisan migrate

# Run composer
docker compose exec app composer install

# Run tests
docker compose exec app php artisan test

# Access shell
docker compose exec app bash
```

### 3. Database Management

```bash
# Run migrations
docker compose exec app php artisan migrate

# Seed database
docker compose exec app php artisan db:seed

# Access MySQL
docker compose exec db mysql -u root

# Backup database
docker compose exec db mysqldump -u root laravel > backup.sql
```

## 🔗 Related Documentation

- [Bangkah README](README.md) - Main documentation
- [CHANGELOG](CHANGELOG.md) - Version history
- [v1.0.3 Release Notes](V1.0.3_RELEASE_NOTES.md) - Docker improvements

## 💡 Best Practices

1. **Always use `.dockerignore`** - Exclude vendor/, node_modules/, .git/
2. **Pin versions** - Use specific tags like `php:8.4-fpm`, not `php:latest`
3. **One process per container** - Don't run multiple services in one container
4. **Use health checks** - Monitor container health
5. **Log to stdout** - Docker can capture and manage logs
6. **Use secrets** - Don't put credentials in Dockerfile or env vars
7. **Multi-stage builds** - Keep final images small
8. **Layer caching** - Order Dockerfile commands from least to most frequently changing

## 📞 Support

- **Issues**: https://github.com/Bangkah/bangkah/issues
- **Discussions**: https://github.com/Bangkah/bangkah/discussions
- **Documentation**: https://github.com/Bangkah/bangkah

---

Generated by Bangkah Laravel Starter v1.0.3
