<?php

namespace Bangkah\Starter\Services;

use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

class DependencyInstaller
{
    private bool $noTty = false;

    public function setNoTty(bool $noTty): void
    {
        $this->noTty = $noTty;
    }

    public function composerInstall(string $cwd): int
    {
        Log::info('[Bangkah] Running composer install');
        return $this->run(['composer', 'install', '--no-interaction'], $cwd);
    }

    public function npmInstall(string $cwd, string $frontend, bool $noTty = false): int
    {
        $this->noTty = $noTty;
        
        Log::info('[Bangkah] Running npm install', ['frontend' => $frontend, 'noTty' => $noTty]);
        
        // Use npm ci for faster, more reliable installs if package-lock.json exists
        $npmCommand = file_exists($cwd . '/package-lock.json') ? 'ci' : 'install';
        $code = $this->run(['npm', $npmCommand], $cwd);
        if ($code !== 0) {
            Log::error('[Bangkah] npm install failed', ['exit_code' => $code]);
            return $code;
        }

        $frontend = strtolower($frontend);
        if ($frontend === 'tailwind') {
            Log::info('[Bangkah] Installing Tailwind CSS');
            $this->run(['npm', 'install', '-D', 'tailwindcss', 'postcss', 'autoprefixer'], $cwd);
            $this->run(['npx', 'tailwindcss', 'init', '-p'], $cwd);
        } elseif ($frontend === 'bootstrap') {
            Log::info('[Bangkah] Installing Bootstrap');
            $this->run(['npm', 'install', 'bootstrap', '@popperjs/core'], $cwd);
        }
        
        // Build frontend assets
        echo "\n\ud83c\udfed Building frontend assets...\n";
        Log::info('[Bangkah] Building frontend assets');
        
        // Use different build command for non-TTY environments
        $buildCmd = $this->noTty 
            ? ['npm', 'run', 'build', '--', '--no-progress']
            : ['npm', 'run', 'build'];
        
        $exitCode = $this->run($buildCmd, $cwd);
        
        if ($exitCode !== 0) {
            Log::warning('[Bangkah] Frontend build had warnings or errors', ['exit_code' => $exitCode]);
        }
        
        return 0;
    }

    public function installAuth(string $cwd, string $frontend): void
    {
        $frontend = strtolower($frontend);
        Log::info('[Bangkah] Installing authentication', ['frontend' => $frontend]);
        
        if ($frontend === 'tailwind') {
            Log::info('[Bangkah] Installing Laravel Breeze');
            $this->run(['composer', 'require', 'laravel/breeze', '--dev', '--no-interaction'], $cwd);
            $this->run(['php', 'artisan', 'breeze:install', 'blade', '--no-interaction'], $cwd);
            
            // Build assets after Breeze installation
            echo "\n\ud83c\udfed Building authentication assets...\n";
            Log::info('[Bangkah] Building Breeze assets');
            
            $buildCmd = $this->noTty 
                ? ['npm', 'run', 'build', '--', '--no-progress']
                : ['npm', 'run', 'build'];
            
            $this->run($buildCmd, $cwd);
        } elseif ($frontend === 'bootstrap') {
            Log::info('[Bangkah] Installing Laravel UI');
            $this->run(['composer', 'require', 'laravel/ui', '--dev', '--no-interaction'], $cwd);
            $this->run(['php', 'artisan', 'ui', 'bootstrap', '--auth'], $cwd);
            
            echo "\n\ud83c\udfed Building authentication assets...\n";
            Log::info('[Bangkah] Building UI assets');
            
            $buildCmd = $this->noTty 
                ? ['npm', 'run', 'build', '--', '--no-progress']
                : ['npm', 'run', 'build'];
            
            $this->run($buildCmd, $cwd);
        }
    }

    private function run(array $cmd, string $cwd): int
    {
        $commandStr = implode(' ', $cmd);
        Log::debug('[Bangkah] Executing command', ['command' => $commandStr, 'cwd' => $cwd]);
        
        $process = new Process($cmd, $cwd, null, null, 1800);
        
        // Set TTY mode based on environment
        if ($this->noTty) {
            $process->setTty(false);
        }
        
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
        
        $exitCode = $process->getExitCode() ?? 0;
        
        if ($exitCode !== 0) {
            Log::warning('[Bangkah] Command failed', [
                'command' => $commandStr,
                'exit_code' => $exitCode,
                'error' => $process->getErrorOutput()
            ]);
        }
        
        return $exitCode;
    }
}
