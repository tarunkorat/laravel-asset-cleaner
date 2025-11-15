<h1 align="center">Laravel Asset Cleaner</h1>

<p align="center">
    <img src="https://img.shields.io/packagist/v/tarunkorat/laravel-asset-cleaner" alt="Latest Version">
    <img src="https://img.shields.io/packagist/dt/tarunkorat/laravel-asset-cleaner" alt="Total Downloads">
    <img src="https://img.shields.io/packagist/l/tarunkorat/laravel-asset-cleaner" alt="License">
    <img src="https://img.shields.io/github/stars/tarunkorat/laravel-asset-cleaner" alt="Stars">
</p>

<p align="center">
Safely detect and remove unused CSS, JS, SCSS, images, and other assets from your Laravel applications. Works seamlessly with Laravel, Laravel Vue, Laravel React, Inertia.js, and Livewire projects.
</p>

---

## ✨ Features

- 🔍 **Smart Detection** - Scans your entire Laravel project for unused assets
- 🛡️ **Safe Deletion** - Creates backups before removing any files
- 🎯 **Selective Cleaning** - Choose specific asset types (JS, CSS, images, fonts, etc.)
- 📊 **Detailed Reports** - See exactly what will be deleted and why
- 🔎 **Debug Mode** - Investigate why files are marked as used or unused
- ⚡ **Fast Scanning** - Efficiently processes large projects
- 🎨 **Framework Agnostic** - Works with Mix, Vite, plain webpack, and more
- 🔒 **Protected Files** - Never accidentally delete important files
- 📝 **Strict Matching** - Avoids false positives with intelligent pattern matching
- 🌟 Wildcard Support (New in v1.0.1) - Use wildcards in directory patterns

## 📋 Requirements

- PHP 8.2 or higher
- Laravel 9.x, 10.x, 11.x, or 12.x
- Composer

## 🚀 Installation
```bash
composer require tarunkorat/laravel-asset-cleaner
```

### Publish Configuration
```bash
php artisan vendor:publish --tag=asset-cleaner-config
```

This creates `config/asset-cleaner.php` where you can customize settings.

## 📖 Usage

### Basic Commands

#### Scan for Unused Assets
```bash
# Scan all asset types
php artisan assets:scan

# Scan specific types
php artisan assets:scan --type=js --type=css
php artisan assets:scan --type=img

# Show detailed information
php artisan assets:scan --details

# Output as JSON
php artisan assets:scan --json
```

#### Delete Unused Assets
```bash
# Dry run (preview what will be deleted)
php artisan assets:delete --dry-run

# Delete with confirmation
php artisan assets:delete

# Delete without confirmation
php artisan assets:delete --force

# Delete without backup
php artisan assets:delete --no-backup --force

# Delete specific types
php artisan assets:delete --type=js --type=css
```

#### Debug Mode
```bash
# Debug specific file
php artisan assets:debug resources/images/logo.png

# Show all found assets
php artisan assets:debug --show-all

# Show all reference patterns
php artisan assets:debug --show-refs

# Debug specific type
php artisan assets:debug --type=js --show-all
```

### Example Workflow
```bash
# Step 1: Scan for unused assets
php artisan assets:scan

# Output:
# Found 15 unused asset(s):
#   📦 js (5 files)
#      📄 resources/js/old-component.js (2.5 KB)
#      📄 public/js/legacy-script.js (8.2 KB)
#   📦 css (4 files)
#      📄 resources/css/unused-styles.css (3.1 KB)
#   📦 img (6 files)
#      📄 public/images/old-logo.png (45 KB)

# Step 2: Preview deletion
php artisan assets:delete --dry-run

# Step 3: Delete unused assets
php artisan assets:delete

# Step 4: Verify backup created
# Backup location: storage/asset-cleaner-backup/2024-11-12_153045/
```

## ⚙️ Configuration

Edit `config/asset-cleaner.php`:
```php
return [
    // Specify which asset types to clean by default
    'clean_types' => 'all', // or ['js', 'css', 'img']
    
    // Enable strict matching (recommended)
    'strict_matching' => true,
    
    // Define asset types and their locations
    'asset_types' => [
        'js' => [
            'directories' => [
                'resources/js', 
                'public/js',
                'public/js/*',  // ✨ NEW: Wildcard support
            ],
            'extensions' => ['js', 'jsx', 'ts', 'tsx', 'vue', 'mjs'],
        ],
        'css' => [
            'directories' => [
                'resources/css', 
                'resources/sass', 
                'public/css',
                'public/css/*',  // ✨ NEW: Scans css/vendor, css/admin, etc.'
            ],
            'extensions' => ['css', 'scss', 'sass', 'less'],
        ],
        'img' => [
            'directories' => [
                'resources/images', 
                'public/images',
                'public/assets/**',  // ✨ NEW: Recursive wildcard
            ],
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico'],
        ],
        // ... more types
    ],
    
    // Directories to scan for asset references
    'scan_directories' => ['app', 'resources/views', 'resources/js', 'routes'],
    
    // Files that should never be deleted
    'protected_files' => [
        'resources/js/app.js',
        'resources/css/app.css',
    ],
    
    // Backup settings
    'backup_directory' => storage_path('asset-cleaner-backup'),
    'create_backup' => true,
];
```

## 🔍 How It Works

### Detection Process

1. **Scans Asset Directories** - Finds all assets in configured directories
2. **Searches for References** - Looks for asset usage in:
   - Blade templates (`asset()`, `<img src>`, `<script>`, `<link>`)
   - JavaScript files (imports, requires, dynamic imports)
   - CSS files (`url()`, `@import`, background images)
   - PHP controllers (`Image::make()`, `public_path()`, `Storage::url()`)
   - Vue/React components (import statements, src attributes)
   - Build configs (webpack.mix.js, vite.config.js, package.json)
3. **Strict Matching** - Only matches complete filenames with extensions
4. **Safe Deletion** - Creates timestamped backups before removal

### Wildcard Directory Patterns (New in v1.0.1)
You can now use wildcard patterns in your directory configuration for more flexible asset scanning.

**Single Level Wildcard (*)**
Single Level Wildcard (*)
```
'directories' => [
    'public/css/*',  // Scans: public/css/vendor, public/css/admin, public/css/themes
]
```
**Example structure:**
```
public/css/
  ├── app.css           ✅ Scanned
  ├── vendor/
  │   └── bootstrap.css ✅ Scanned
  └── admin/
      └── style.css     ✅ Scanned
```

**Recursive Wildcard **(**)
Scans the directory and ALL subdirectories recursively:
```
'directories' => [
    'public/assets/**',  // Scans everything under public/assets
]
```
**Example structure:**
```
public/assets/
  ├── css/
  │   ├── app.css              ✅ Scanned
  │   └── vendor/
  │       └── bootstrap.css    ✅ Scanned
  ├── js/
  │   └── app.js               ✅ Scanned
  └── images/
      └── logo.png             ✅ Scanned
```      

### What Gets Detected

✅ **These patterns are detected:**
```php
// Blade templates
<img src="{{ asset('images/logo.png') }}">
<script src="{{ mix('js/app.js') }}"></script>

// JavaScript
import Logo from './images/logo.png';
require('./components/Header.vue');

// CSS
background-image: url('../images/banner.jpg');
@import 'components/button.css';

// PHP Controllers
$image = Image::make(public_path('images/product.jpg'));
return asset('images/logo.png');
```

❌ **False positives avoided:**
- File named `error.svg` won't match word "error" in code
- `test.js` won't match variable named "test"
- Strict boundary checking prevents partial matches

## 🛡️ Safety Features

- ✅ **Automatic Backups** - All deleted files are backed up with timestamps
- ✅ **Protected Files** - Important files (app.js, app.css) are never deleted
- ✅ **Dry Run Mode** - Preview changes before applying them
- ✅ **Confirmation Prompts** - Asks for confirmation before deletion
- ✅ **Verification** - Checks if files were actually deleted
- ✅ **Error Logging** - Failed deletions are logged for review

## 🎯 Use Cases

### Clean Up After Refactoring
```bash
# After removing old components
php artisan assets:scan --type=js
php artisan assets:delete --type=js --dry-run
```

### Optimize Images
```bash
# Find unused images
php artisan assets:scan --type=img
php artisan assets:delete --type=img
```

### CI/CD Integration
```bash
# In your deployment script
php artisan assets:scan --json > unused-assets-report.json
```

### Before Production Deploy
```bash
# Clean up everything
php artisan assets:scan
php artisan assets:delete --force
```

## 🐛 Troubleshooting

### Files Not Being Detected as Unused
```bash
# Debug specific file
php artisan assets:debug resources/js/MyComponent.vue

# This will show:
# - Where the file is located
# - What references it (if any)
# - Why it's marked as used/unused
```

### Files Won't Delete

Common causes:
- File permissions (run as administrator on Windows)
- File is open in an editor
- Antivirus blocking deletion

Solution:
```bash
# Check what failed
# Check Laravel logs: storage/logs/laravel.log

# Try with elevated permissions (Windows)
# Run PowerShell as Administrator
php artisan assets:delete --force
```

### False Positives

If files are incorrectly marked as used:
```php
// config/asset-cleaner.php
'strict_matching' => true, // Ensure this is enabled
```

### Restore from Backup
```bash
# Backups are in storage/asset-cleaner-backup/
# Each run creates a timestamped folder

# To restore:
cp -r storage/asset-cleaner-backup/2024-11-12_153045/* ./
```

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### Development Setup
```bash
# Clone the repository
git clone https://github.com/tarunkorat/laravel-asset-cleaner.git

# Install dependencies
composer install

## 📝 Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for recent changes.

## 🔒 Security

If you discover any security issues, please email tarunkorat336@gmail.com instead of using the issue tracker.

## 📄 License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## 💖 Support

If this package helped you, please consider:
- ⭐ Starring the repository
- 🐛 Reporting bugs
- 💡 Suggesting new features
- 📖 Improving documentation

## 🙏 Credits

- [Tarun Korat](https://github.com/tarunkorat)
<!-- - [All Contributors](../../contributors) -->

## 🔗 Links

- [Documentation](https://github.com/tarunkorat/laravel-asset-cleaner#readme)
- [Packagist](https://packagist.org/packages/tarunkorat/laravel-asset-cleaner)
- [GitHub](https://github.com/tarunkorat/laravel-asset-cleaner)
- [Issues](https://github.com/tarunkorat/laravel-asset-cleaner/issues)

---

<p align="center">Made with ❤️ by <a href="https://github.com/tarunkorat">Tarun Korat</a></p>
