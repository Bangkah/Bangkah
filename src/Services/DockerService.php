<?php

namespace Bangkah\Starter\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Exception;

class DockerService
{
    public function __construct(private Filesystem $files = new Filesystem)
    {
    }

    public function generateCompose(string $targetPath, array $opts): void
    {
        $useNginx = (bool)($opts['nginx'] ?? false);
        $db = ($opts['db'] ?? 'mysql');
        $frontend = strtolower((string)($opts['frontend'] ?? 'none'));

        Log::info('[Bangkah] Generating Docker configuration', [
            'nginx' => $useNginx,
            'database' => $db,
            'frontend' => $frontend
        ]);

        $services = [];
        $this->generateDockerfile($targetPath);

        if ($useNginx) {
            $services['app'] = $this->phpFpmService();
            $services['nginx'] = $this->nginxService();
        } else {
            $services['app'] = $this->phpCliService();
        }

        if ($db === 'mysql') {
            $services['db'] = $this->mysqlService();
        } else {
            $services['db'] = $this->postgresService();
        }

        if ($frontend !== 'none') {
            $services['node'] = $this->nodeService();
        }

        $compose = $this->renderCompose($services);

        $this->files->put($targetPath.'/docker-compose.yml', $compose);
        Log::info('[Bangkah] Created docker-compose.yml');

        if ($useNginx) {
            $this->copyNginxConfig($targetPath);
        }
    }

    private function copyNginxConfig(string $targetPath): void
    {
        $this->ensureDir($targetPath.'/docker/nginx');
        
        $stubPath = $this->stubsPath('nginx/nginx.conf.stub');
        
        // Check if stub exists
        if (!$this->files->exists($stubPath)) {
            Log::warning('[Bangkah] Nginx stub not found, creating default config');
            // Create default nginx config if stub doesn't exist
            $defaultConfig = $this->getDefaultNginxConfig();
            $this->files->put($targetPath.'/docker/nginx/nginx.conf', $defaultConfig);
        } else {
            $this->files->copy($stubPath, $targetPath.'/docker/nginx/nginx.conf');
        }
        
        Log::info('[Bangkah] Nginx configuration created');
    }

    private function getDefaultNginxConfig(): string
    {
        return <<<'NGINX'
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINX;
    }

    public function generateDockerfile(string $targetPath): void
    {
        $dockerfile = $targetPath.'/Dockerfile';
        if ($this->files->exists($dockerfile)) {
            Log::info('[Bangkah] Dockerfile already exists, skipping');
            return;
        }

        // Enhanced Dockerfile with better error handling and build caching
        $content = <<<'DOCKER'
# syntax=docker/dockerfile:1

FROM composer:2 AS composer

FROM php:8.4-fpm AS php-base
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libonig-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --from=composer /usr/bin/composer /usr/bin/composer

FROM php:8.4-cli AS php-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libonig-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./

# Install dependencies (will be cached if composer files haven't changed)
RUN composer install --no-interaction --prefer-dist --no-progress --no-dev --no-scripts --no-autoloader

# Copy application code
COPY . .

# Complete the composer installation with autoloader
RUN composer install --no-interaction --prefer-dist --no-progress --no-dev --optimize-autoloader \
    && php artisan config:cache || true \
    && php artisan route:cache || true

FROM php-base AS php-fpm

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-interaction --prefer-dist --no-progress --no-dev --no-scripts --no-autoloader

# Copy application code
COPY . .

# Complete installation and set permissions
RUN composer install --no-interaction --prefer-dist --no-progress --no-dev --optimize-autoloader \
    && mkdir -p storage/framework/{sessions,views,cache} \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && php artisan config:cache || true \
    && php artisan route:cache || true

CMD ["php-fpm"]
DOCKER;

        $this->files->put($dockerfile, $content);
        Log::info('[Bangkah] Dockerfile generated successfully');
    }

    private function phpCliService(): array
    {
        return [
            'build' => [
                'context' => '.',
                'dockerfile' => 'Dockerfile',
                'target' => 'php-cli',
            ],
            'working_dir' => '/var/www/html',
            'ports' => ['8000:8000'],
            'volumes' => ['.:/var/www/html'],
            'command' => 'sh -lc "php artisan serve --host=0.0.0.0 --port=8000"',
            'depends_on' => ['db'],
        ];
    }

    private function phpFpmService(): array
    {
        return [
            'build' => [
                'context' => '.',
                'dockerfile' => 'Dockerfile',
                'target' => 'php-fpm',
            ],
            'working_dir' => '/var/www/html',
            'volumes' => ['.:/var/www/html'],
            'expose' => ['9000'],
            'depends_on' => ['db'],
        ];
    }

    private function nginxService(): array
    {
        return [
            'image' => 'nginx:alpine',
            'ports' => ['80:80'],
            'volumes' => [
                '.:/var/www/html',
                './docker/nginx/nginx.conf:/etc/nginx/conf.d/default.conf',
            ],
            'depends_on' => ['app'],
        ];
    }

    private function mysqlService(): array
    {
        return [
            'image' => 'mysql:8.0',
            'ports' => ['3306:3306'],
            'environment' => [
                'MYSQL_DATABASE=laravel',
                'MYSQL_ROOT_PASSWORD=secret',
                'MYSQL_USER=laravel',
                'MYSQL_PASSWORD=secret',
            ],
            'volumes' => ['dbdata:/var/lib/mysql'],
        ];
    }

    private function postgresService(): array
    {
        return [
            'image' => 'postgres:15-alpine',
            'ports' => ['5432:5432'],
            'environment' => [
                'POSTGRES_DB=laravel',
                'POSTGRES_PASSWORD=secret',
                'POSTGRES_USER=laravel',
            ],
            'volumes' => ['pgdata:/var/lib/postgresql/data'],
        ];
    }

    private function nodeService(): array
    {
        return [
            'image' => 'node:20-alpine',
            'working_dir' => '/var/www/html',
            'volumes' => ['.:/var/www/html'],
            'command' => 'sh -lc "npm install && npm run dev"',
            'ports' => ['5173:5173'],
        ];
    }

    private function renderCompose(array $services): string
    {
        $yaml = "services:\n";
        foreach ($services as $name => $cfg) {
            $yaml .= '  '.$name.":\n";
            foreach ($cfg as $k => $v) {
                $yaml .= $this->yamlLine($k, $v, 2);
            }
        }
        if (isset($services['db']) || isset($services['node'])) {
            $yaml .= "volumes:\n";
            if (isset($services['db'])) {
                if (($services['db']['image'] ?? '') === 'mysql:8.0') {
                    $yaml .= "  dbdata:\n";
                } else {
                    $yaml .= "  pgdata:\n";
                }
            }
        }
        return $yaml;
    }

    private function yamlLine(string $key, $value, int $indent = 0): string
    {
        $pad = str_repeat('  ', $indent);
        if (is_array($value)) {
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);
            if ($isAssoc) {
                $out = $pad.$key.":\n";
                foreach ($value as $k => $v) {
                    $out .= $this->yamlLine((string)$k, $v, $indent + 1);
                }
                return $out;
            } else {
                $out = $pad.$key.":\n";
                foreach ($value as $item) {
                    $out .= $pad.'  - '.(is_string($item) ? $item : json_encode($item))."\n";
                }
                return $out;
            }
        }
        return $pad.$key.': '.(is_string($value) ? $value : json_encode($value))."\n";
    }

    private function ensureDir(string $path): void
    {
        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }

    private function stubsPath(string $suffix = ''): string
    {
        $base = __DIR__.'/../../stubs';
        return rtrim($base.'/'.ltrim($suffix, '/'), '/');
    }
}
