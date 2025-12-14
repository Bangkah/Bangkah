<?php

namespace Bangkah\Starter\Console;

use Bangkah\Starter\Services\DependencyInstaller;
use Bangkah\Starter\Services\DockerService;
use Bangkah\Starter\Services\EnvironmentService;
use Bangkah\Starter\Services\TemplateService;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

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

    public function handle(TemplateService $templates, DockerService $docker, DependencyInstaller $deps, EnvironmentService $env)
    {
        $fs = new Filesystem();

        // Always scaffold current project
        $targetPath = base_path();
        $projectName = basename($targetPath);
        
        $this->info("Scaffolding project: {$projectName}");

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
        if ($projectType === 'Web') {
            $templates->applyWeb($targetPath);
        } else {
            $templates->applyApi($targetPath);
        }

        $env->setAppName($targetPath, $projectName);
        $env->configureDatabase($targetPath, $dbType, $useDocker);

        // Clean composer.json from local repositories before Docker
        if ($useDocker) {
            $this->cleanLocalRepositories($targetPath);
        }

        if ($useDocker) {
            $docker->generateCompose($targetPath, [
                'nginx' => $useNginx,
                'db' => strtolower($dbType) === 'postgresql' || strtolower($dbType) === 'pgsql' || strtolower($dbType) === 'postgres' ? 'postgres' : 'mysql',
                'frontend' => $frontend,
            ]);
            $this->info('Membangun dan menjalankan container Docker...');
            $exit = $this->runProcess(['docker', 'compose', 'up', '-d', '--build'], $targetPath);
            if ($exit !== 0) {
                $this->runProcess(['docker-compose', 'up', '-d', '--build'], $targetPath);
            }
        } else {
            $this->info('Menjalankan setup lokal (composer/npm)...');
            $deps->composerInstall($targetPath);
            if (strtolower($frontend) !== 'none') {
                $deps->npmInstall($targetPath, $frontend);
            }
        }

        if ($includeAuth) {
            $this->info('Menginstall auth scaffolding...');
            $deps->installAuth($targetPath, $frontend);
        }

        $url = $this->determineUrl($useDocker, $useNginx, $projectType);
        $this->newLine();
        $this->components->info('Starter project berhasil dibuat!');
        $this->line('Nama: '.$projectName);
        $this->line('Jenis: '.$projectType);
        $this->line('Docker: '.($useDocker ? 'Ya' : 'Tidak').($useDocker && $useNginx ? ' + Nginx' : ''));
        $this->line('Database: '.$dbType);
        $this->line('Frontend: '.$frontend);
        $this->newLine();
        $this->components->twoColumnDetail('Buka project di', $url);

        return self::SUCCESS;
    }

    private function runProcess(array $cmd, string $cwd): int
    {
        $process = new Process($cmd, $cwd, null, null, 1800);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
        return $process->getExitCode() ?? 0;
    }

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
