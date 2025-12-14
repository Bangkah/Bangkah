<?php

namespace Bangkah\Starter\Services;

use Illuminate\Filesystem\Filesystem;

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

        if ($useNginx) {
            $this->ensureDir($targetPath.'/docker/nginx');
            $this->files->copy($this->stubsPath('nginx/nginx.conf.stub'), $targetPath.'/docker/nginx/nginx.conf');
        }
    }

    public function generateDockerfile(string $targetPath): void
    {
        $dockerfile = $targetPath.'/Dockerfile';
        if ($this->files->exists($dockerfile)) {
            return;
        }

        $content = <<<'DOCKER'
# syntax=docker/dockerfile:1

FROM composer:2 AS composer

FROM php:8.4-fpm AS php-base
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --from=composer /usr/bin/composer /usr/bin/composer

FROM php:8.4-cli AS php-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY . .
RUN composer install --no-interaction --prefer-dist --no-progress

FROM php-base AS php-fpm
COPY . .
RUN composer install --no-interaction --prefer-dist --no-progress \
    && chown -R www-data:www-data storage bootstrap/cache
CMD ["php-fpm"]
DOCKER;

        $this->files->put($dockerfile, $content);
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
