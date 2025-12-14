<?php

namespace Bangkah\Starter\Services;

use Symfony\Component\Process\Process;

class DependencyInstaller
{
    public function composerInstall(string $cwd): int
    {
        return $this->run(['composer', 'install', '--no-interaction'], $cwd);
    }

    public function npmInstall(string $cwd, string $frontend): int
    {
        $code = $this->run(['npm', 'install'], $cwd);
        if ($code !== 0) {
            return $code;
        }

        $frontend = strtolower($frontend);
        if ($frontend === 'tailwind') {
            $this->run(['npm', 'install', '-D', 'tailwindcss', 'postcss', 'autoprefixer'], $cwd);
            $this->run(['npx', 'tailwindcss', 'init', '-p'], $cwd);
        } elseif ($frontend === 'bootstrap') {
            $this->run(['npm', 'install', 'bootstrap', '@popperjs/core'], $cwd);
        }
        
        // Build frontend assets
        echo "\nBuilding frontend assets...\n";
        $this->run(['npm', 'run', 'build'], $cwd);
        
        return 0;
    }

    public function installAuth(string $cwd, string $frontend): void
    {
        $frontend = strtolower($frontend);
        if ($frontend === 'tailwind') {
            $this->run(['composer', 'require', 'laravel/breeze', '--dev'], $cwd);
            $this->run(['php', 'artisan', 'breeze:install', 'blade'], $cwd);
            // Build assets after Breeze installation
            echo "\nBuilding authentication assets...\n";
            $this->run(['npm', 'run', 'build'], $cwd);
        } elseif ($frontend === 'bootstrap') {
            $this->run(['composer', 'require', 'laravel/ui', '--dev'], $cwd);
            $this->run(['php', 'artisan', 'ui', 'bootstrap', '--auth'], $cwd);
            echo "\nBuilding authentication assets...\n";
            $this->run(['npm', 'run', 'build'], $cwd);
        }
    }

    private function run(array $cmd, string $cwd): int
    {
        $process = new Process($cmd, $cwd, null, null, 1800);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
        return $process->getExitCode() ?? 0;
    }
}
