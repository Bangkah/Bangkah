<?php

namespace Bangkah\Starter\Services;

use Illuminate\Filesystem\Filesystem;

class EnvironmentService
{
    public function __construct(private Filesystem $files = new Filesystem)
    {
    }

    public function setAppName(string $targetPath, string $name): void
    {
        $this->updateEnv($targetPath, 'APP_NAME', '"'.$name.'"');
        $this->updateEnvExample($targetPath, 'APP_NAME', '"'.$name.'"');
    }

    public function configureDatabase(string $targetPath, string $dbType, bool $docker = false): void
    {
        $dbType = strtolower($dbType);
        $host = $docker ? 'db' : '127.0.0.1';
        if ($dbType === 'postgresql' || $dbType === 'postgres' || $dbType === 'pgsql') {
            $this->updateEnv($targetPath, 'DB_CONNECTION', 'pgsql');
            $this->updateEnv($targetPath, 'DB_HOST', $host);
            $this->updateEnv($targetPath, 'DB_PORT', '5432');
            $this->updateEnv($targetPath, 'DB_DATABASE', 'laravel');
            $this->updateEnv($targetPath, 'DB_USERNAME', 'root');
            $this->updateEnv($targetPath, 'DB_PASSWORD', '');
        } else {
            $this->updateEnv($targetPath, 'DB_CONNECTION', 'mysql');
            $this->updateEnv($targetPath, 'DB_HOST', $host);
            $this->updateEnv($targetPath, 'DB_PORT', '3306');
            $this->updateEnv($targetPath, 'DB_DATABASE', 'laravel');
            $this->updateEnv($targetPath, 'DB_USERNAME', 'root');
            $this->updateEnv($targetPath, 'DB_PASSWORD', '');
        }

        $this->updateEnvExample($targetPath, 'DB_CONNECTION', $dbType === 'postgresql' || $dbType === 'postgres' || $dbType === 'pgsql' ? 'pgsql' : 'mysql');
        $this->updateEnvExample($targetPath, 'DB_HOST', $host);
        $this->updateEnvExample($targetPath, 'DB_PORT', $dbType === 'postgresql' || $dbType === 'postgres' || $dbType === 'pgsql' ? '5432' : '3306');
        $this->updateEnvExample($targetPath, 'DB_DATABASE', 'laravel');
        $this->updateEnvExample($targetPath, 'DB_USERNAME', 'laravel');
        $this->updateEnvExample($targetPath, 'DB_PASSWORD', 'secret');
    }

    private function updateEnv(string $targetPath, string $key, string $value): void
    {
        $envPath = $targetPath.'/.env';
        if (! $this->files->exists($envPath)) {
            return;
        }
        $content = $this->files->get($envPath);
        $pattern = "/^".preg_quote($key, '/')."=.*/m";
        $line = $key.'='.$value;
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $line, $content);
        } else {
            $content .= "\n".$line."\n";
        }
        $this->files->put($envPath, $content);
    }

    private function updateEnvExample(string $targetPath, string $key, string $value): void
    {
        $envPath = $targetPath.'/.env.example';
        if (! $this->files->exists($envPath)) {
            return;
        }
        $content = $this->files->get($envPath);
        $pattern = "/^".preg_quote($key, '/')."=.*/m";
        $line = $key.'='.$value;
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $line, $content);
        } else {
            $content .= "\n".$line."\n";
        }
        $this->files->put($envPath, $content);
    }
}
