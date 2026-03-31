<?php

namespace Bangkah\Starter\Services;

use Illuminate\Filesystem\Filesystem;

class TemplateService
{
    public function __construct(private Filesystem $files = new Filesystem)
    {
    }

    public function applyWeb(string $targetPath): void
    {
        $this->ensureDirs($targetPath, [
            'routes',
            'resources/views',
            'app/Http/Controllers',
        ]);

        $webRoutes = $targetPath.'/routes/web.php';
        if ($this->files->exists($webRoutes)) {
            $this->files->move($webRoutes, $webRoutes.'.backup-'.date('YmdHis'));
        }
        $this->files->copy($this->stubsPath('web/routes/web.stub.php'), $webRoutes);
        $this->files->copy($this->stubsPath('web/views/home.blade.php'), $targetPath.'/resources/views/home.blade.php');
        
        $controllerTarget = $targetPath.'/app/Http/Controllers/HomeController.php';
        if (! $this->files->exists($controllerTarget)) {
            $this->files->copy($this->stubsPath('web/controllers/HomeController.stub.php'), $controllerTarget);
        }
    }

    public function applyApi(string $targetPath): void
    {
        $this->ensureDirs($targetPath, [
            'routes',
            'app/Http/Controllers/Api',
        ]);

        $apiRoutes = $targetPath.'/routes/api.php';
        if ($this->files->exists($apiRoutes)) {
            $this->files->move($apiRoutes, $apiRoutes.'.backup-'.date('YmdHis'));
        }
        $this->files->copy($this->stubsPath('api/routes/api.stub.php'), $apiRoutes);

        $controllerTarget = $targetPath.'/app/Http/Controllers/Api/HealthController.php';
        if (! $this->files->exists($controllerTarget)) {
            $this->files->copy($this->stubsPath('api/controllers/HealthController.stub.php'), $controllerTarget);
        }

        // Enable API routes in bootstrap/app.php for Laravel 12
        $bootstrapApp = $targetPath.'/bootstrap/app.php';
        if ($this->files->exists($bootstrapApp)) {
            $this->files->copy($this->stubsPath('api/bootstrap/app.stub.php'), $bootstrapApp);
        }
    }

    public function stubsPath(string $suffix = ''): string
    {
        $base = __DIR__.'/../../stubs';
        return rtrim($base.'/'.ltrim($suffix, '/'), '/');
    }

    private function ensureDirs(string $base, array $relPaths): void
    {
        foreach ($relPaths as $rel) {
            $dir = rtrim($base.'/'.trim($rel, '/'), '/');
            if (! $this->files->isDirectory($dir)) {
                $this->files->makeDirectory($dir, 0755, true);
            }
        }
    }
}
