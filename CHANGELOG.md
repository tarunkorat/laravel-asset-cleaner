# Changelog

All notable changes to `laravel-asset-cleaner` will be documented in this file.

## [1.0.0] - 2025-11-12

### Added
- Initial release
- Asset scanning for JS, CSS, images, fonts, video, and audio files
- Smart reference detection in Blade templates, PHP files, JavaScript, and CSS
- Strict matching mode to avoid false positives
- Automatic backup creation before deletion
- Dry run mode for safe previewing
- Debug command to investigate specific files
- Protected files configuration
- Support for Laravel 9.x, 10.x, 11.x, and 12.x
- Support for Laravel Mix, Vite, and plain webpack
- Works with Vue, React, Inertia.js, and Livewire

### Features
- `assets:scan` - Scan for unused assets
- `assets:delete` - Delete unused assets with backup
- `assets:debug` - Debug why files are marked as used/unused

### Security
- Strict filename matching prevents false positives
- Backup system ensures safe deletion
- Protected files list prevents accidental deletion of critical files

[1.0.0]: https://github.com/tarunkorat/laravel-asset-cleaner/releases/tag/v1.0.0
