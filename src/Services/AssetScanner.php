<?php

namespace TarunKorat\AssetCleaner\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class AssetScanner
{
    private $assetFiles = [];
    private $references = [];
    private $assetTypes = [];

    public function scan(array $types = []): array
    {
        $this->assetTypes = $this->getAssetTypesToScan($types);

        $this->findAssetFiles();
        $this->findReferences();

        return [
            'unused' => $this->getUnusedAssets(),
            'total_assets' => count($this->assetFiles),
            'total_unused' => count($this->getUnusedAssets()),
            'scanned_types' => array_keys($this->assetTypes),
        ];
    }

    private function getAssetTypesToScan(array $requestedTypes): array
    {
        $allTypes = config('asset-cleaner.asset_types', []);
        $configCleanTypes = config('asset-cleaner.clean_types', 'all');

        // If specific types requested via command
        if (!empty($requestedTypes)) {
            $result = [];
            foreach ($requestedTypes as $type) {
                if (isset($allTypes[$type])) {
                    $result[$type] = $allTypes[$type];
                }
            }
            return $result;
        }

        // If config specifies types as string (single type)
        if ($configCleanTypes !== 'all' && is_string($configCleanTypes)) {
            $result = [];
            if (isset($allTypes[$configCleanTypes])) {
                $result[$configCleanTypes] = $allTypes[$configCleanTypes];
            }
            return $result;
        }

        // If config specifies types as array
        if ($configCleanTypes !== 'all' && is_array($configCleanTypes)) {
            $result = [];
            foreach ($configCleanTypes as $type) {
                if (isset($allTypes[$type])) {
                    $result[$type] = $allTypes[$type];
                }
            }
            return $result;
        }

        // Return all types
        return $allTypes;
    }

    private function findAssetFiles(): void
    {
        $basePath = base_path();

        foreach ($this->assetTypes as $category => $config) {
            $directories = $config['directories'];
            $extensions = $config['extensions'];

            foreach ($directories as $dir) {
                $fullPath = $basePath . '/' . $dir;

                if (!File::isDirectory($fullPath)) {
                    continue;
                }

                $finder = new Finder();
                $finder->files()
                       ->in($fullPath)
                       ->name('/\.(' . implode('|', $extensions) . ')$/');

                foreach ($finder as $file) {
                    $relativePath = str_replace($basePath . '/', '', $file->getRealPath());

                    if ($this->shouldExclude($relativePath)) {
                        continue;
                    }

                    $this->assetFiles[$relativePath] = [
                        'path' => $relativePath,
                        'name' => $file->getFilename(),
                        'basename' => $file->getBasename('.' . $file->getExtension()),
                        'size' => $file->getSize(),
                        'type' => $file->getExtension(),
                        'category' => $category,
                        'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                    ];
                }
            }
        }
    }

    private function findReferences(): void
    {
        $directories = config('asset-cleaner.scan_directories');
        $basePath = base_path();

        foreach ($directories as $dir) {
            $fullPath = $basePath . '/' . $dir;

            if (!File::isDirectory($fullPath)) {
                continue;
            }

            $finder = new Finder();
            $finder->files()->in($fullPath);

            foreach ($finder as $file) {
                $extension = $file->getExtension();

                // Check if this file type should be scanned
                $refExtensions = config('asset-cleaner.reference_extensions');
                $shouldScan = false;

                foreach ($refExtensions as $refExt) {
                    if (str_ends_with($file->getFilename(), $refExt)) {
                        $shouldScan = true;
                        break;
                    }
                }

                if (!$shouldScan) {
                    continue;
                }

                $content = file_get_contents($file->getRealPath());
                $this->extractReferences($content);
            }
        }

        // Also check webpack.mix.js, vite.config.js, package.json, tailwind.config.js
        $this->checkBuildConfigs();

        // Check CSS/SCSS files for background images and other asset references
        $this->checkStylesheets();
    }

    private function checkStylesheets(): void
    {
        $basePath = base_path();
        $styleDirs = ['resources/css', 'resources/sass', 'resources/scss', 'public/css'];

        foreach ($styleDirs as $dir) {
            $fullPath = $basePath . '/' . $dir;

            if (!File::isDirectory($fullPath)) {
                continue;
            }

            $finder = new Finder();
            $finder->files()
                ->in($fullPath)
                ->name(['*.css', '*.scss', '*.sass', '*.less']);

            foreach ($finder as $file) {
                $content = file_get_contents($file->getRealPath());
                $this->extractReferences($content);
            }
        }
    }

    private function extractReferences(string $content): void
    {
        // Match various import/require patterns
        $patterns = [
           // JS/TS imports
            "/import\s+.*?from\s+['\"]([^'\"]+)['\"]/",
            "/require\s*\(['\"]([^'\"]+)['\"]\)/",
            // CSS imports
            "/@import\s+['\"]([^'\"]+)['\"]/",
            // Asset references
            "/asset\s*\(['\"]([^'\"]+)['\"]\)/",
            "/url\s*\(['\"]?([^'\")\s]+)['\"]?\)/",
            // Script/link tags
            "/<script[^>]+src=['\"]([^'\"]+)['\"]/",
            "/<link[^>]+href=['\"]([^'\"]+)['\"]/",
            // Vue/React dynamic imports
            "/import\s*\(['\"]([^'\"]+)['\"]\)/",
            // Image sources in HTML/Blade/JSX
            "/<img[^>]+src=['\"]([^'\"]+)['\"]/",
            "/src=['\"]([^'\"]+\.(jpg|jpeg|png|gif|svg|webp|ico))['\"]/i",
            // Background images in CSS/inline styles
            "/background(?:-image)?\s*:\s*url\s*\(['\"]?([^'\")\s]+)['\"]?\)/i",
            // CSS content property
            "/content\s*:\s*url\s*\(['\"]?([^'\")\s]+)['\"]?\)/i",
            // Srcset for responsive images
            "/srcset=['\"]([^'\"]+)['\"]/",
            // Video/audio sources
            "/<(?:video|audio|source)[^>]+src=['\"]([^'\"]+)['\"]/",
            // React/Vue image imports
            "/from\s+['\"]([^'\"]+\.(jpg|jpeg|png|gif|svg|webp))['\"]/i",
            // Public path helpers
            "/public_path\s*\(['\"]([^'\"]+)['\"]\)/",
            "/Storage::(?:url|path)\s*\(['\"]([^'\"]+)['\"]\)/",
            // Font face declarations
            "/@font-face[^}]*src:\s*url\s*\(['\"]?([^'\")\s]+)['\"]?\)/i",
            // Laravel Mix/Vite references
            "/mix\s*\(['\"]([^'\"]+)['\"]\)/",
            "/vite\s*\(['\"]([^'\"]+)['\"]\)/",
            "/@vite\s*\(['\"]([^'\"]+)['\"]\)/",
            // PHP file includes
            "/include\s*\(['\"]([^'\"]+)['\"]\)/",
            "/require\s*\(['\"]([^'\"]+)['\"]\)/",
            "/require_once\s*\(['\"]([^'\"]+)['\"]\)/",
            "/include_once\s*\(['\"]([^'\"]+)['\"]\)/",
            // Image manipulation in controllers
            "/Image::make\s*\(['\"]([^'\"]+)['\"]\)/",
            "/\\\File::(?:get|exists)\s*\(['\"]([^'\"]+)['\"]\)/",
            // Relative paths in CSS
            "/url\s*\(\s*['\"]?\.\.?\/([^'\")\s]+)['\"]?\s*\)/",
            // Data attributes
            "/data-[a-z-]*(?:image|src|url|bg|background)[a-z-]*=['\"]([^'\"]+)['\"]/i",
            // Style bindings in Vue/React
            "/:style=['\"][^'\"]*url\(['\"]?([^'\")\s]+)['\"]?\)[^'\"]*['\"]/",
            "/style={{[^}]*url\(['\"]?([^'\")\s]+)['\"]?\)[^}]*}}/",
            // Webpack/Vite dynamic requires
            "/require\s*\([`'\"]([^`'\"]+\.(jpg|jpeg|png|gif|svg|webp|css|js))[`'\"]\)/i",
            // Template literals with file extensions
            "/[`'\"]([^`'\"]*\/[^`'\"\/]+\.(jpg|jpeg|png|gif|svg|webp|css|js|woff|woff2|ttf|eot|otf|mp3|mp4|wav))[`'\"]/i",
            // JSON references
            "/fetch\s*\(['\"]([^'\"]+\.json)['\"]\)/i",
            "/import\s+.*?from\s+['\"]([^'\"]+\.json)['\"]/i",
            "/url\s*\(['\"]?([^'\")]+\.json)['\"]?\)/i",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $ref) {
                    // Ignore NPM or external package imports (no ./ or /)
                    if (!preg_match('/^(?:\.{0,2}\/|\/)/', $ref)) {
                        continue;
                    }

                    $this->addReference($ref);
                }
            }
        }

        // Extract filenames without extensions
        foreach ($this->assetFiles as $asset) {
            $filename = $asset['name']; // e.g., "error.svg"
            $filenameEscaped = preg_quote($filename, '/');

            // Only match if the full filename appears with proper boundaries
            // This prevents "error" from matching "error.svg"
            $strictPattern = '/[\s\'"()\[\]{}\/\\\\,;]' . $filenameEscaped . '[\s\'"()\[\]{}\/\\\\,;]|^' . $filenameEscaped . '[\s\'"()\[\]{}\/\\\\,;]|[\s\'"()\[\]{}\/\\\\,;]' . $filenameEscaped . '$/';

            if (preg_match($strictPattern, $content)) {
                $this->addReference($asset['path']);
            }
        }
    }

    private function addReference(string $ref): void
    {
        $ref = trim($ref);

        // Clean up common prefixes and URL encodings
        $ref = str_replace(['%20', '%2F'], [' ', '/'], $ref);
        $ref = preg_replace('/^[.\/]+/', '', $ref); // Remove leading ./ or ../
        $ref = preg_replace('/^(public\/|resources\/)/', '', $ref); // Remove common prefixes

        // Add the reference
        $this->references[$ref] = true;

        // Also add without extension
        $withoutExt = preg_replace('/\.[^.]+$/', '', $ref);
        $this->references[$withoutExt] = true;

        // Add just the filename
        $filename = basename($ref);
        $this->references[$filename] = true;

        // Add filename without extension
        $filenameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $this->references[$filenameWithoutExt] = true;

        // For paths with directories, add each segment
        $parts = explode('/', $ref);
        if (count($parts) > 1) {
            // Add last two segments (common pattern: images/logo.png)
            $lastTwo = implode('/', array_slice($parts, -2));
            $this->references[$lastTwo] = true;
        }
    }

    private function checkBuildConfigs(): void
    {
        $configFiles = [
            'webpack.mix.js',
            'vite.config.js',
            'vite.config.ts',
            'package.json',
            'tailwind.config.js',
            'tailwind.config.ts',
        ];

        foreach ($configFiles as $file) {
            $path = base_path($file);
            if (File::exists($path)) {
                $content = file_get_contents($path);
                $this->extractReferences($content);
            }
        }

        // Also check all CSS files in public directory
        $this->scanPublicAssets();
    }

    private function scanPublicAssets(): void
    {
        $publicPath = public_path();

        if (!File::isDirectory($publicPath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()
            ->in($publicPath)
            ->name(['*.css', '*.js'])
            ->notPath('vendor'); // Skip vendor directory

        foreach ($finder as $file) {
            // Only scan compiled CSS/JS files for references
            if ($file->getSize() < 5000000) { // Skip files larger than 5MB
                $content = file_get_contents($file->getRealPath());
                $this->extractReferences($content);
            }
        }
    }

    private function getUnusedAssets(): array
    {
        $unused = [];
        $protectedFiles = config('asset-cleaner.protected_files', []);

        foreach ($this->assetFiles as $path => $asset) {
            if (in_array($path, $protectedFiles)) {
                continue;
            }

            if (!$this->isReferenced($asset)) {
                $unused[] = $asset;
            }
        }

        return $unused;
    }

    private function isReferenced(array $asset): bool
    {
        $checks = [
            $asset['path'],
            $asset['name'],
            $asset['basename'],
            str_replace('resources/', '', $asset['path']),
            str_replace('public/', '', $asset['path']),
            basename($asset['path']),
            pathinfo($asset['name'], PATHINFO_FILENAME),
        ];

        // For images and other media, add more variations
        if (in_array($asset['category'], ['img', 'video', 'audio', 'fonts'])) {
            // Add path without first directory
            $pathParts = explode('/', $asset['path']);
            if (count($pathParts) > 1) {
                $checks[] = implode('/', array_slice($pathParts, 1));
                $checks[] = implode('/', array_slice($pathParts, -2)); // Last two parts
            }

            // Add common public path variations
            $checks[] = str_replace(['resources/images/', 'resources/img/', 'resources/'], ['images/', 'img/', ''], $asset['path']);
            $checks[] = str_replace(['public/images/', 'public/img/', 'public/'], ['images/', 'img/', ''], $asset['path']);
        }

        // Direct reference check
        foreach ($checks as $check) {
            if (isset($this->references[$check])) {
                return true;
            }
        }

        // Partial match check - look for the filename in any reference
        $filename = $asset['name'];
        $filenameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

        foreach (array_keys($this->references) as $ref) {
            // Check if reference contains our filename
            if (stripos($ref, $filename) !== false) {
                return true;
            }

            // Check if reference contains filename without extension
            if (stripos($ref, $filenameWithoutExt) !== false) {
                return true;
            }

            // Check reverse - if our path contains the reference
            if (stripos($asset['path'], $ref) !== false && strlen($ref) > 5) {
                return true;
            }
        }

        return false;
    }

    private function shouldExclude(string $path): bool
    {
        $patterns = config('asset-cleaner.exclude_patterns', []);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
