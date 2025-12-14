<?php

namespace Bangkah\Starter\Console;

use Bangkah\Starter\Services\DependencyInstaller;
use Bangkah\Starter\Services\DockerService;
use Bangkah\Starter\Services\EnvironmentService;
use Bangkah\Starter\Services\TemplateService;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;
use Exception;

class StarterCreateCommand extends Command
{
    protected $signature = 'bangkah:create
        {--docker : Aktifkan Docker}
        {--nginx : Gunakan Nginx (hanya jika --docker)}
        {--type= : Tipe project: web|api}
        {--auth : Sertakan auth scaffolding}
        {--db= : Tipe database: mysql|postgres}
        {--frontend= : Frontend: tailwind|bootstrap|none}
        {--yes : Auto-konfirmasi semua pilihan (non-interaktif)}';

    protected $description = 'Bangkah Interactive Starter Kit - Scaffold current Laravel project';

    private array $logContext = [];
    private bool $hasTty;

    public function handle(TemplateService $templates, DockerService $docker, DependencyInstaller $deps, EnvironmentService $env)
    {
        try {
            $this->hasTty = $this->detectTty();
            $this->logInfo('Starting Bangkah scaffolding', ['tty' => $this->hasTty]);

            $fs = new Filesystem();

            // Always scaffold current project
            $targetPath = base_path();
            $projectName = basename($targetPath);
            
            $this->logContext = ['project' => $projectName, 'path' => $targetPath];
            $this->info("🚀 Scaffolding project: {$projectName}");

            // Validate Laravel project
            if (!$this->validateLaravelProject($targetPath)) {
                $this->logError('Invalid Laravel project structure');
                return self::FAILURE;
            }

            // Validate and normalize options
            $options = $this->validateAndNormalizeOptions();
            if ($options === null) {
                return self::FAILURE;
            }

            $nonInteractive = (bool) $this->option('yes')
                || $this->option('docker') || $this->option('type') || $this->option('db') || $this->option('frontend') || $this->option('auth');

            $useDocker = $nonInteractive
                ? (bool) $this->option('docker')
                : $this->confirm('Gunakan Docker?', false);

            $useNginx = $useDocker
                ? ($nonInteractive ? (bool) $this->option('nginx') : $this->confirm('Gunakan Nginx?', true))
                : false;

            $projectType = $this->option('type')
                ? (strtolower($this->option('type')) === 'api' ? 'API' : 'Web')
                : $this->choice('Tipe project?', ['Web', 'API'], 0);

            $includeAuth = $this->option('auth') ? true : ($nonInteractive ? false : $this->confirm('Include auth scaffolding?', false));

            $dbType = $this->option('db')
                ? (strtolower($this->option('db')) === 'postgres' || strtolower($this->option('db')) === 'pgsql' || strtolower($this->option('db')) === 'postgresql' ? 'PostgreSQL' : 'MySQL')
                : $this->choice('Tipe database?', ['MySQL', 'PostgreSQL'], 0);

            $frontend = $this->option('frontend')
                ? (match (strtolower($this->option('frontend'))) { 'none' => 'None', 'bootstrap' => 'Bootstrap', default => 'Tailwind' })
                : $this->choice('Frontend?', ['Tailwind', 'Bootstrap', 'None'], 0);

            // Apply templates based on project type
            $this->logInfo('Applying templates', ['type' => $projectType]);
            try {
                if ($projectType === 'Web') {
                    $templates->applyWeb($targetPath);
                } else {
                    $templates->applyApi($targetPath);
                }
            } catch (Exception $e) {
                $this->handleError('Failed to apply templates', $e);
                return self::FAILURE;
            }

            $this->logInfo('Configuring environment');
            try {
                $env->setAppName($targetPath, $projectName);
                $env->configureDatabase($targetPath, $dbType, $useDocker);
            } catch (Exception $e) {
                $this->handleError('Failed to configure environment', $e);
                return self::FAILURE;
            }

            // Clean composer.json from local repositories before Docker
            if ($useDocker) {
                $this->logInfo('Cleaning local repositories for Docker');
                try {
                    $this->cleanLocalRepositories($targetPath);
                } catch (Exception $e) {
                    $this->handleError('Failed to clean local repositories', $e);
                    $this->warn('Continuing with Docker setup...');
                }
            }

            if ($useDocker) {
                $this->logInfo('Setting up Docker environment');
                try {
                    $docker->generateCompose($targetPath, [
                        'nginx' => $useNginx,
                        'db' => strtolower($dbType) === 'postgresql' || strtolower($dbType) === 'pgsql' || strtolower($dbType) === 'postgres' ? 'postgres' : 'mysql',
                        'frontend' => $frontend,
                    ]);
                    $this->info('🐳 Membangun dan menjalankan container Docker...');
                    $exit = $this->runProcess(['docker', 'compose', 'up', '-d', '--build'], $targetPath);
                    if ($exit !== 0) {
                        $this->logInfo('Trying docker-compose fallback');
                        $exit = $this->runProcess(['docker-compose', 'up', '-d', '--build'], $targetPath);
                        if ($exit !== 0) {
                            throw new Exception('Docker container startup failed with exit code: ' . $exit);
                        }
                    }
                    $this->logInfo('Docker containers started successfully');
                } catch (Exception $e) {
                    $this->handleError('Docker setup failed', $e, [
                        'suggestion' => 'Make sure Docker is installed and running. Check: docker --version',
                        'docs' => 'https://docs.docker.com/get-docker/'
                    ]);
                    return self::FAILURE;
                }
            } else {
                $this->info('⚙️  Menjalankan setup lokal (composer/npm)...');
                $this->logInfo('Installing dependencies locally');
                try {
                    $deps->composerInstall($targetPath);
                    $this->logInfo('Composer install completed');
                    
                    if (strtolower($frontend) !== 'none') {
                        $deps->npmInstall($targetPath, $frontend, !$this->hasTty);
                        $this->logInfo('NPM install completed', ['frontend' => $frontend]);
                    }
                } catch (Exception $e) {
                    $this->handleError('Dependency installation failed', $e, [
                        'suggestion' => 'Check your composer and npm installation',
                        'commands' => 'composer --version && npm --version'
                    ]);
                    return self::FAILURE;
                }
            }

            if ($includeAuth) {
                $this->info('🔐 Menginstall auth scaffolding...');
                $this->logInfo('Installing authentication');
                try {
                    $deps->installAuth($targetPath, $frontend);
                    $this->logInfo('Auth scaffolding completed');
                } catch (Exception $e) {
                    $this->handleError('Auth scaffolding failed', $e);
                    $this->warn('Continuing without authentication...');
                }
            }

            $url = $this->determineUrl($useDocker, $useNginx, $projectType);
            $this->newLine();
            $this->components->info('✅ Starter project berhasil dibuat!');
            $this->line('📦 Nama: '.$projectName);
            $this->line('🎯 Jenis: '.$projectType);
            $this->line('🐳 Docker: '.($useDocker ? 'Ya' : 'Tidak').($useDocker && $useNginx ? ' + Nginx' : ''));
            $this->line('💾 Database: '.$dbType);
            $this->line('🎨 Frontend: '.$frontend);
            if ($includeAuth) {
                $this->line('🔐 Auth: Included');
            }
            $this->newLine();
            $this->components->twoColumnDetail('🌐 Buka project di', $url);
            $this->newLine();
            
            if (!$useDocker) {
                $this->info('💡 Next steps:');
                $this->line('   php artisan migrate');
                $this->line('   php artisan serve');
            }

            $this->logInfo('Scaffolding completed successfully', [
                'project_type' => $projectType,
                'docker' => $useDocker,
                'database' => $dbType,
                'frontend' => $frontend
            ]);

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->handleError('Unexpected error during scaffolding', $e);
            return self::FAILURE;
        }
    }

    private function detectTty(): bool
    {
        if (function_exists('posix_isatty')) {
            return @posix_isatty(STDOUT);
        }
        
        // Fallback detection
        if (getenv('CI') === 'true') {
            return false;
        }
        
        return stream_isatty(STDOUT);
    }

    private function validateLaravelProject(string $path): bool
    {
        $requiredFiles = ['artisan', 'composer.json'];
        $requiredDirs = ['app', 'bootstrap', 'config'];
        
        foreach ($requiredFiles as $file) {
            if (!file_exists($path . '/' . $file)) {
                $this->error(\"\u274c Invalid Laravel project: Missing {$file}\");\n                $this->line('Make sure you are in a Laravel project directory.');\n                return false;\n            }\n        }\n        \n        foreach ($requiredDirs as $dir) {
            if (!is_dir($path . '/' . $dir)) {\n                $this->error(\"\u274c Invalid Laravel project: Missing {$dir}/ directory\");\n                return false;\n            }\n        }\n        \n        return true;\n    }

    private function validateAndNormalizeOptions(): ?array\n    {\n        $options = [];\n        \n        // Validate type option\n        if ($type = $this->option('type')) {\n            $validTypes = ['web', 'api'];\n            if (!in_array(strtolower($type), $validTypes)) {\n                $this->error(\"\u274c Invalid --type option: '{$type}'\");\n                $this->line('Valid options: ' . implode(', ', $validTypes));\n                return null;\n            }\n            $options['type'] = strtolower($type);\n        }\n        \n        // Validate db option\n        if ($db = $this->option('db')) {\n            $validDbs = ['mysql', 'postgres', 'postgresql', 'pgsql'];\n            if (!in_array(strtolower($db), $validDbs)) {\n                $this->error(\"\u274c Invalid --db option: '{$db}'\");\n                $this->line('Valid options: mysql, postgres');\n                return null;\n            }\n            $options['db'] = strtolower($db);\n        }\n        \n        // Validate frontend option\n        if ($frontend = $this->option('frontend')) {\n            $validFrontends = ['tailwind', 'bootstrap', 'none'];\n            if (!in_array(strtolower($frontend), $validFrontends)) {\n                $this->error(\"\u274c Invalid --frontend option: '{$frontend}'\");\n                $this->line('Valid options: ' . implode(', ', $validFrontends));\n                return null;\n            }\n            $options['frontend'] = strtolower($frontend);\n        }\n        \n        // Validate nginx option (only valid with docker)\n        if ($this->option('nginx') && !$this->option('docker')) {\n            $this->error(\"\u274c --nginx option requires --docker\");\n            $this->line('Use both flags: --docker --nginx');\n            return null;\n        }\n        \n        return $options;\n    }

    private function logInfo(string $message, array $context = []): void\n    {\n        $fullContext = array_merge($this->logContext, $context);\n        Log::info('[Bangkah] ' . $message, $fullContext);\n    }

    private function logError(string $message, array $context = []): void\n    {\n        $fullContext = array_merge($this->logContext, $context);\n        Log::error('[Bangkah] ' . $message, $fullContext);\n    }

    private function handleError(string $message, Exception $e, array $suggestions = []): void\n    {\n        $this->newLine();\n        $this->error(\"\u274c {$message}\");\n        $this->error('Error: ' . $e->getMessage());\n        \n        if (!empty($suggestions)) {\n            $this->newLine();\n            $this->warn('\ud83d\udca1 Suggestions:');\n            foreach ($suggestions as $key => $value) {\n                if (is_numeric($key)) {\n                    $this->line('   \u2022 ' . $value);\n                } else {\n                    $this->line('   ' . ucfirst($key) . ': ' . $value);\n                }\n            }\n        }\n        \n        $this->newLine();\n        $this->line('For more help, visit: https://github.com/Bangkah/bangkah-launcher/issues');\n        \n        $this->logError($message, [\n            'exception' => get_class($e),\n            'message' => $e->getMessage(),\n            'file' => $e->getFile(),\n            'line' => $e->getLine(),\n            'trace' => $e->getTraceAsString()\n        ]);\n    }

    private function runProcess(array $cmd, string $cwd): int
    {
        $this->logInfo('Running process', ['command' => implode(' ', $cmd)]);\n        $process = new Process($cmd, $cwd, null, null, 1800);\n        $process->run(function ($type, $buffer) {\n            echo $buffer;\n        });\n        $exitCode = $process->getExitCode() ?? 0;\n        \n        if ($exitCode !== 0) {\n            $this->logError('Process failed', [\n                'command' => implode(' ', $cmd),\n                'exit_code' => $exitCode,\n                'output' => $process->getOutput(),\n                'error' => $process->getErrorOutput()\n            ]);\n        }\n        \n        return $exitCode;\n    }

    private function determineUrl(bool $docker, bool $nginx, string $type): string
    {
        if ($docker) {
            if ($nginx) {
                return 'http://localhost';
            }
            return 'http://localhost:8000';
        }
        return $type === 'Web' ? 'http://localhost:8000' : 'http://localhost:8000/api/health';
    }

    private function cleanLocalRepositories(string $targetPath): void
    {
        $composerFile = $targetPath . '/composer.json';
        
        if (! file_exists($composerFile)) {
            return;
        }
        
        $composer = json_decode(file_get_contents($composerFile), true);
        
        if (! $composer) {
            return;
        }
        
        $modified = false;
        
        // Remove repositories with type "path"
        if (isset($composer['repositories'])) {
            foreach ($composer['repositories'] as $key => $repo) {
                if (is_array($repo) && isset($repo['type']) && $repo['type'] === 'path') {
                    unset($composer['repositories'][$key]);
                    $modified = true;
                }
            }
            
            // If repositories is empty, remove it entirely
            if (empty($composer['repositories'])) {
                unset($composer['repositories']);
            } else {
                // Re-index array to fix JSON structure
                $composer['repositories'] = array_values($composer['repositories']);
            }
        }
        
        // Remove bangkah/bangkah from require if it exists (it's just a scaffolding tool)
        if (isset($composer['require']['bangkah/bangkah'])) {
            unset($composer['require']['bangkah/bangkah']);
            $modified = true;
        }
        
        if ($modified) {
            file_put_contents($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            
            // Delete composer.lock to force fresh install
            if (file_exists($targetPath . '/composer.lock')) {
                unlink($targetPath . '/composer.lock');
            }
            
            // Remove vendor directory
            if (is_dir($targetPath . '/vendor')) {
                $fs = new Filesystem();
                $fs->deleteDirectory($targetPath . '/vendor');
            }
        }
    }
}
