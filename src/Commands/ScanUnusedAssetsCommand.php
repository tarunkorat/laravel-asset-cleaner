<?php

namespace TarunKorat\AssetCleaner\Commands;

use Illuminate\Console\Command;
use TarunKorat\AssetCleaner\Services\AssetScanner;

class ScanUnusedAssetsCommand extends Command
{
    protected $signature = 'assets:scan
                          {--type=* : Specific asset types to scan (js, css, img, fonts, video, audio)}
                          {--details : Show detailed information about each unused asset}
                          {--json : Output results as JSON}';

    protected $description = 'Scan for unused CSS, JS, and other asset files';

    public function handle(AssetScanner $scanner)
    {
        $types = $this->option('type');

        // Show available types if requested
        if (empty($types)) {
            $configTypes = config('asset-cleaner.clean_types');
            if ($configTypes !== 'all') {
                if (is_string($configTypes)) {
                    $types = [$configTypes];
                } elseif (is_array($configTypes)) {
                    $types = $configTypes;
                }
            }
        }

        $this->info('🔍 Scanning for unused assets...');

        if (!empty($types)) {
            $this->comment('Asset types: ' . implode(', ', $types));
        } else {
            $this->comment('Asset types: all');
        }

        $this->newLine();

        $results = $scanner->scan($types);

        // Debug information
        if ($results['total_assets'] === 0) {
            $this->warn('⚠️  No asset files found in configured directories.');
            $this->newLine();
            $this->comment('Scanned types: ' . implode(', ', $results['scanned_types']));
            $this->newLine();
            $this->comment('Configured directories:');

            $assetTypes = config('asset-cleaner.asset_types', []);
            foreach ($results['scanned_types'] as $type) {
                if (isset($assetTypes[$type])) {
                    $this->line("  {$type}:");
                    foreach ($assetTypes[$type]['directories'] as $dir) {
                        $exists = is_dir(base_path($dir)) ? '✓' : '✗';
                        $this->line("    {$exists} {$dir}");
                    }
                }
            }

            return 0;
        }

        if (empty($results['unused'])) {
            $this->info('✅ No unused assets found!');
            $this->comment("Total assets scanned: {$results['total_assets']}");
            return 0;
        }

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT));
            return 0;
        }

        // Group by type
        $byType = [];
        foreach ($results['unused'] as $asset) {
            $byType[$asset['category']][] = $asset;
        }

        $this->warn('Found ' . count($results['unused']) . ' unused asset(s):');
        $this->newLine();

        foreach ($byType as $type => $assets) {
            // ensure type is a string label
            $label = is_array($type) ? json_encode($type) : (string) $type;
            $fileCount = count($assets);

            $this->line("  📦 {$label} ({$fileCount} files)");

            foreach ($assets as $asset) {
                $size = $this->formatBytes($asset['size']);
                $this->line("     📄 {$asset['path']} ({$size})");

                if ($this->option('details')) {
                    $this->line("        Extension: {$asset['type']}");
                    $this->line("        Last modified: {$asset['modified']}");
                    $this->newLine();
                }
            }
            $this->newLine();
        }


        $totalSize = array_sum(array_column($results['unused'], 'size'));
        $this->info('Total size: ' . $this->formatBytes($totalSize));
        $this->newLine();
        $this->comment('Run "php artisan assets:delete" to remove these files.');

        return 0;
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
