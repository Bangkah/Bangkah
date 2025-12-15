# Changelog

## v1.0.4 (2025-12-15)
- Publish cleanup: add `.gitattributes` to exclude non-essential files from archives.
- Remove dev-only dependency to keep distribution lean.
- No runtime changes.

## v1.0.3 (2025-12-15)
- Docker build optimizations and fixes.
# Changelog

All notable changes to `bangkah/bangkah` will be documented in this file.
## [1.0.3] - 2024-12-15

### Fixed
- Fixed Docker build removing `bangkah/bangkah` package during `composer install`
- Fixed build failure when `composer.lock` file doesn't exist in generated projects
- Fixed inefficient Docker layer caching causing slow rebuilds

### Changed
- Dockerfile now installs dependencies before copying full source for better caching
- Removed hard requirement on `composer.lock` (build works with or without it)
- Split `composer install` into install phase + dump-autoload phase
- Added `--no-dev --no-scripts` flags for production-optimized builds
- Both `php-cli` and `php-fpm` stages now follow same optimized pattern

### Improved
- Docker builds are now faster thanks to proper layer caching
- Smaller production images due to `--no-dev` flag
- More reliable builds that handle edge cases


## [1.0.2] - 2024-12-15

### Fixed
- Fixed vendor directory deletion issue in scaffolding command that broke artisan
- Docker startup environment variables and bootstrapping now properly initialize apps
- Session driver defaults to `file` in generated Docker configs to avoid DB requirement

### Changed
- Updated generated Docker stack to use PHP 8.4 (fpm and cli)
- Nginx service now exposed on host port 8080 by default
- Success URL message updated to reflect http://localhost:8080
- Docker app service now includes env vars (APP_*, SESSION_DRIVER, DB_*)
- Docker startup command now copies .env from .env.example, runs key:generate, and fixes permissions
- Vite dev server in Node service now binds to 0.0.0.0:5173 with strictPort
- Removed external port mapping for MySQL service (internal networking only)

## [1.0.1] - 2024-12-14

### Fixed
- Added `exclude-from-classmap` for `App/` to prevent class ambiguity during installation

## [1.0.0] - 2024-12-13

### Added
- Initial release
- Starter kit scaffolding with Web/API presets
- Docker support with optional Nginx
- Frontend framework integration (Vue, React, Svelte, Alpine)
- Health check endpoints and controllers
- Automatic route generation
- Database configuration (MySQL/PostgreSQL)
