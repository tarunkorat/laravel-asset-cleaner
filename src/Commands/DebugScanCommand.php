<?php

namespace Tarunkorat\AssetCleaner\Commands;

use Illuminate\Console\Command;
use TarunKorat\AssetCleaner\Services\AssetScanner;
use Illuminate\Support\Facades\File;

class DebugScanCommand extends Command
{
    protected $signature = 'assets:debug
                          {file? : Specific file to debug}
                          {--type=* : Specific asset types to debug}
                          {--show-refs : Show all reference locations}
                          {--show-all : Show all found assets}';

    protected $description = 'Debug asset scanning to see why files are marked as used or unused';

    public function handle(AssetScanner $scanner)
    {
        $this->info('🔍 Debug Mode: Asset Scanner Analysis');
        $this->newLine();

        $specificFile = $this->argument('file');
        $types = $this->option('type');

        if ($specificFile) {
            $this->debugSpecificFile($specificFile);
        } else {
            $this->debugAllAssets($types);
        }

        return 0;
    }

    private function debugSpecificFile(string $file)
    {
        $this->info("Debugging file: {$file}");
        $this->newLine();

        $filePath = base_path($file);

        if (!File::exists($filePath)) {
            $this->error("❌ File not found: {$file}");
            return;
        }

        $this->line("✓ File exists");
        $this->line("  Path: {$filePath}");
        $this->line("  Size: " . $this->formatBytes(File::size($filePath)));
        $this->newLine();

        // Check if file is in configured directories
        $this->line("📁 Checking if file is in scanned directories...");
        $assetTypes = config('asset-cleaner.asset_types', []);
        $inConfiguredDir = false;

        foreach ($assetTypes as $type => $config) {
            foreach ($config['directories'] as $dir) {
                if (str_starts_with($file, $dir)) {
                    $this->info("  ✓ Found in {$type} directory: {$dir}");
                    $inConfiguredDir = true;
                }
            }
        }

        if (!$inConfiguredDir) {
            $this->warn("  ⚠️  File is NOT in any configured asset directory");
            $this->comment("  Configure directories in config/asset-cleaner.php");
        }

        $this->newLine();

        // Search for references using STRICT matching
        $this->line("🔎 Searching for references to this file (STRICT MODE)...");
        $this->newLine();

        $filename = basename($file);
        $filenameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $references = [];
        $strictMode = config('asset-cleaner.strict_matching', true);

        $scanDirs = config('asset-cleaner.scan_directories', []);

        foreach ($scanDirs as $dir) {
            $fullPath = base_path($dir);

            if (!File::isDirectory($fullPath)) {
                continue;
            }

            $files = File::allFiles($fullPath);

            foreach ($files as $scanFile) {
                $content = file_get_contents($scanFile->getRealPath());
                $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $scanFile->getRealPath());

                // Check for EXACT filename matches (with extension)
                $found = false;
                $matchType = '';

                // Pattern 1: Direct filename match in strings/paths
                $filenameEscaped = preg_quote($filename, '/');
                $strictPattern = '/[\s\'"()\[\]{}\/\\\\,;]' . $filenameEscaped . '[\s\'"()\[\]{}\/\\\\,;]|^' . $filenameEscaped . '[\s\'"()\[\]{}\/\\\\,;]|[\s\'"()\[\]{}\/\\\\,;]' . $filenameEscaped . '$/';

                if (preg_match($strictPattern, $content)) {
                    $found = true;
                    $matchType = 'Exact filename with extension: ' . $filename;
                }

                // Pattern 2: Full path match
                $filePathEscaped = preg_quote($file, '/');
                if (preg_match('/' . $filePathEscaped . '/i', $content)) {
                    $found = true;
                    $matchType = 'Full path: ' . $file;
                }

                // Pattern 3: Common asset patterns
                $assetPatterns = [
                    "/asset\s*\(['\"]([^'\"]*" . preg_quote($filename, '/') . ")['\"]\\)/i",
                    "/url\s*\(['\"]?([^'\")\s]*" . preg_quote($filename, '/') . ")['\"]?\\)/i",
                    "/<img[^>]+src=['\"]([^'\"]*" . preg_quote($filename, '/') . ")['\"]/i",
                    "/from\s+['\"]([^'\"]*" . preg_quote($filename, '/') . ")['\"]/i",
                    "/require\s*\(['\"]([^'\"]*" . preg_quote($filename, '/') . ")['\"]\)/i",
                ];

                foreach ($assetPatterns as $pattern) {
                    if (preg_match($pattern, $content, $matches)) {
                        $found = true;
                        $matchType = 'Asset reference: ' . $matches[0];
                        break;
                    }
                }

                if ($found) {
                    $references[$relativePath] = $matchType;
                }
            }
        }

        if (empty($references)) {
            $this->info("  ✅ No references found - File appears UNUSED");
            if ($strictMode) {
                $this->comment("  (Using strict matching - only exact filename matches)");
            }
        } else {
            $this->error("  ❌ Found " . count($references) . " file(s) with references:");
            foreach ($references as $refFile => $matchType) {
                $this->line("    • {$refFile}");
                $this->comment("      {$matchType}");
            }
        }

        $this->newLine();

        // Show what would have matched in loose mode
        if ($strictMode && empty($references)) {
            $this->line("🔍 Checking what would match in LOOSE mode (filename without extension)...");
            $looseMatches = [];

            foreach ($scanDirs as $dir) {
                $fullPath = base_path($dir);

                if (!File::isDirectory($fullPath)) {
                    continue;
                }

                $files = File::allFiles($fullPath);

                foreach ($files as $scanFile) {
                    $content = file_get_contents($scanFile->getRealPath());

                    // Check for loose matches (just the name without extension)
                    if (stripos($content, $filenameWithoutExt) !== false) {
                        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $scanFile->getRealPath());

                        // Find the context
                        $lines = explode("\n", $content);
                        $context = '';
                        foreach ($lines as $line) {
                            if (stripos($line, $filenameWithoutExt) !== false) {
                                $context = trim($line);
                                if (strlen($context) > 100) {
                                    $context = substr($context, 0, 100) . '...';
                                }
                                break;
                            }
                        }

                        $looseMatches[$relativePath] = $context;
                    }
                }
            }

            if (!empty($looseMatches)) {
                $this->warn("  ⚠️  Would have matched in loose mode (these are FALSE POSITIVES):");
                foreach ($looseMatches as $refFile => $context) {
                    $this->line("    • {$refFile}");
                    $this->comment("      Context: {$context}");
                }
                $this->newLine();
                $this->comment("  These match '{$filenameWithoutExt}' but NOT '{$filename}'");
            } else {
                $this->info("  ✓ No loose matches found either");
            }
        }

        $this->newLine();

        // Check build configs
        $this->line("⚙️  Checking build configuration files...");
        $configFiles = ['webpack.mix.js', 'vite.config.js', 'vite.config.ts', 'package.json'];
        $foundInConfig = false;

        foreach ($configFiles as $configFile) {
            $configPath = base_path($configFile);
            if (File::exists($configPath)) {
                $content = file_get_contents($configPath);

                // Strict matching in config files too
                $filenameEscaped = preg_quote($filename, '/');
                $strictPattern = '/[\s\'"()\[\]{}\/\\\\,;]' . $filenameEscaped . '[\s\'"()\[\]{}\/\\\\,;]|^' . $filenameEscaped . '[\s\'"()\[\]{}\/\\\\,;]|[\s\'"()\[\]{}\/\\\\,;]' . $filenameEscaped . '$/';

                if (preg_match($strictPattern, $content)) {
                    $this->info("  ✓ Referenced in {$configFile}");
                    $foundInConfig = true;
                }
            }
        }

        if (!$foundInConfig) {
            $this->line("  - Not found in build configs");
        }

        $this->newLine();
        $this->comment("💡 Strict matching mode: " . ($strictMode ? 'ENABLED' : 'DISABLED'));
        $this->comment("   Change in config/asset-cleaner.php: 'strict_matching' => true/false");
    }

    private function debugAllAssets(array $types = [])
    {
        $assetTypes = config('asset-cleaner.asset_types', []);

        if (empty($types)) {
            $types = array_keys($assetTypes);
        }

        $this->line("Asset types to debug: " . implode(', ', $types));
        $this->newLine();

        // Show configuration
        $this->info("📋 Current Configuration:");
        foreach ($types as $type) {
            if (!isset($assetTypes[$type])) {
                continue;
            }

            $config = $assetTypes[$type];
            $this->line("  {$type}:");
            $this->line("    Directories:");
            foreach ($config['directories'] as $dir) {
                $fullPath = base_path($dir);
                $exists = File::isDirectory($fullPath);
                $icon = $exists ? '✓' : '✗';
                $this->line("      {$icon} {$dir}" . ($exists ? '' : ' (not found)'));

                if ($exists) {
                    $fileCount = count(File::allFiles($fullPath));
                    $this->comment("         ({$fileCount} files)");
                }
            }
            $this->line("    Extensions: " . implode(', ', $config['extensions']));
        }

        $this->newLine();

        // Show scan directories
        $this->info("📁 Reference Scan Directories:");
        $scanDirs = config('asset-cleaner.scan_directories', []);
        foreach ($scanDirs as $dir) {
            $fullPath = base_path($dir);
            $exists = File::isDirectory($fullPath);
            $icon = $exists ? '✓' : '✗';
            $this->line("  {$icon} {$dir}");
        }

        $this->newLine();

        if ($this->option('show-all')) {
            $this->showAllAssets($types);
        }

        if ($this->option('show-refs')) {
            $this->showAllReferences();
        }

        // Summary
        $this->newLine();
        $this->info("💡 Tips:");
        $this->line("  • Use --show-all to see all found assets");
        $this->line("  • Use --show-refs to see all reference patterns found");
        $this->line("  • Use 'php artisan assets:debug path/to/file.js' to debug specific file");
        $this->line("  • Check if your unused files are in the configured directories");
        $this->line("  • Verify reference patterns are being detected correctly");
    }

    private function showAllAssets(array $types)
    {
        $this->info("📄 All Found Assets:");
        $assetTypes = config('asset-cleaner.asset_types', []);

        foreach ($types as $type) {
            if (!isset($assetTypes[$type])) {
                continue;
            }

            $config = $assetTypes[$type];
            $assets = [];

            foreach ($config['directories'] as $dir) {
                $fullPath = base_path($dir);

                if (!File::isDirectory($fullPath)) {
                    continue;
                }

                $files = File::allFiles($fullPath);

                foreach ($files as $file) {
                    $ext = $file->getExtension();
                    if (in_array($ext, $config['extensions'])) {
                        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
                        $assets[] = [
                            'path' => $relativePath,
                            'size' => $file->getSize(),
                        ];
                    }
                }
            }

            if (!empty($assets)) {
                $this->newLine();
                $this->line("  {$type} ({count($assets)} files):");
                foreach ($assets as $asset) {
                    $this->line("    • {$asset['path']} ({$this->formatBytes($asset['size'])})");
                }
            }
        }
    }

    private function showAllReferences()
    {
        $this->newLine();
        $this->info("🔗 Reference Patterns Found:");

        $scanDirs = config('asset-cleaner.scan_directories', []);
        $patterns = [];

        foreach ($scanDirs as $dir) {
            $fullPath = base_path($dir);

            if (!File::isDirectory($fullPath)) {
                continue;
            }

            $files = File::allFiles($fullPath);

            foreach ($files as $file) {
                $content = file_get_contents($file->getRealPath());

                // Extract imports
                if (preg_match_all("/import\s+.*?from\s+['\"]([^'\"]+)['\"]/", $content, $matches)) {
                    foreach ($matches[1] as $match) {
                        $patterns[$match] = ($patterns[$match] ?? 0) + 1;
                    }
                }

                // Extract requires
                if (preg_match_all("/require\s*\(['\"]([^'\"]+)['\"]\)/", $content, $matches)) {
                    foreach ($matches[1] as $match) {
                        $patterns[$match] = ($patterns[$match] ?? 0) + 1;
                    }
                }

                // Extract asset()
                if (preg_match_all("/asset\s*\(['\"]([^'\"]+)['\"]\)/", $content, $matches)) {
                    foreach ($matches[1] as $match) {
                        $patterns[$match] = ($patterns[$match] ?? 0) + 1;
                    }
                }

                // Extract url()
                if (preg_match_all("/url\s*\(['\"]?([^'\")\s]+)['\"]?\)/", $content, $matches)) {
                    foreach ($matches[1] as $match) {
                        $patterns[$match] = ($patterns[$match] ?? 0) + 1;
                    }
                }
            }
        }

        if (empty($patterns)) {
            $this->warn("  No reference patterns found");
        } else {
            arsort($patterns);
            $count = 0;
            foreach ($patterns as $pattern => $occurrences) {
                if ($count++ < 20) { // Show top 20
                    $this->line("  • {$pattern} ({$occurrences}x)");
                }
            }

            if (count($patterns) > 20) {
                $this->comment("  ... and " . (count($patterns) - 20) . " more");
            }
        }
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
