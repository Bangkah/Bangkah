# Changelog

All notable changes to the Bangkah Laravel Starter Kit will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-12-15

### Added
- ✨ **TTY Detection**: Automatic detection of terminal capabilities for non-interactive environments
- 📊 **Structured Logging**: Comprehensive logging with context for debugging failed installations
- ✅ **Input Validation**: Better validation of command-line options with helpful error messages
- 🎯 **Laravel Project Validation**: Verify project structure before scaffolding
- 📝 **Enhanced Error Messages**: Descriptive errors with suggestions for common issues
- 🔍 **Better Process Logging**: Detailed logging of all external commands and their results
- 🎨 **Improved UX**: Better emoji icons and formatting for clearer output

### Fixed
- 🐛 **Docker Build Path Issue**: Resolved "Source path not found" errors with local packages
  - Improved Dockerfile with better layer caching
  - Added multi-stage build optimization
  - Fixed path resolution for stubs and templates
- 🐛 **TTY Warning Handling**: npm/vite processes now work correctly in non-TTY environments
  - Automatic `--no-progress` flag for CI/CD environments
  - Proper TTY mode detection and handling
- 🐛 **Nginx Configuration**: Fallback to default config if stub is missing

### Changed
- 🔄 **Composer Install**: Enhanced Docker build process with better caching
- 🔄 **NPM Install**: Use `npm ci` when package-lock.json exists for faster, more reliable installs
- 🔄 **Build Commands**: Add `--no-progress` flag automatically in non-TTY environments
- 🔄 **Error Handling**: All major operations now wrapped in try-catch with detailed error reporting

### Improved
- 📈 **Dockerfile**: Multi-stage build with better caching and optimization
  - Separate layer for composer dependencies
  - Optimized autoloader generation
  - Config and route caching in container
- 📈 **Docker Compose**: Better service configuration
- 📈 **Code Quality**: Enhanced type hints and documentation
- 📈 **Logging**: Structured logs with context throughout the scaffolding process

### Developer Experience
- 🛠️ **Better Debugging**: Detailed logs in Laravel log file for troubleshooting
- 🛠️ **Helpful Suggestions**: Error messages now include suggestions and relevant documentation links
- 🛠️ **Validation**: Input validation prevents common mistakes early
- 🛠️ **Progress Indicators**: Clear status messages with emojis for better visibility

## [1.0.0] - 2025-12-14

### Added
- 🎉 Initial public release
- ⚡ Interactive CLI for Laravel project scaffolding
- 🐳 Docker support with optional Nginx
- 🎨 Frontend options: Tailwind CSS, Bootstrap, or None
- 🔐 Optional authentication scaffolding (Breeze, UI)
- 💾 Database support: MySQL and PostgreSQL
- 📦 Project types: Web and API
- 🚀 One-command setup for new Laravel projects
- 📝 Comprehensive documentation
- ✅ Non-interactive mode with `--yes` flag

### Features
- Auto-detection of project structure
- Automatic environment configuration
- Docker Compose generation
- Nginx configuration templates
- Frontend asset building
- Database configuration
- Service provider auto-discovery
- Global and local installation support

---

## Upgrade Guide

### From 1.0.x to 1.1.0

No breaking changes! This is a backward-compatible release with improvements and bug fixes.

To upgrade:

```bash
composer update bangkah/bangkah
```

Or for global installations:

```bash
composer global update bangkah/bangkah
```

**What's Changed:**
- Better error handling and validation
- Improved Docker support
- TTY detection for CI/CD environments
- Enhanced logging for debugging

**No action required** - all changes are internal improvements.

---

## Links

- [GitHub Repository](https://github.com/Bangkah/bangkah-launcher)
- [Packagist](https://packagist.org/packages/bangkah/bangkah)
- [Documentation](https://github.com/Bangkah/bangkah-launcher#readme)
- [Issue Tracker](https://github.com/Bangkah/bangkah-launcher/issues)

[1.1.0]: https://github.com/Bangkah/bangkah-launcher/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/Bangkah/bangkah-launcher/releases/tag/v1.0.0
