<?php

namespace TarunKorat\AssetCleaner\Commands;

use Illuminate\Console\Command;
use TarunKorat\AssetCleaner\Services\AssetScanner;
use TarunKorat\AssetCleaner\Services\AssetDeleter;

class DeleteUnusedAssetsCommand extends Command
{
    protected $signature = 'assets:delete
                          {--type=* : Specific asset types to delete (js, css, img, fonts, video, audio)}
                          {--dry-run : Show what would be deleted without actually deleting}
                          {--no-backup : Skip creating backup}
                          {--force : Skip confirmation prompt}';

    protected $description = 'Delete unused asset files';

    public function handle(AssetScanner $scanner, AssetDeleter $deleter)
    {
        $types = $this->option('type');

        if (empty($types)) {
            $configTypes = config('asset-cleaner.clean_types');
            if ($configTypes !== 'all' && is_array($configTypes)) {
                $types = $configTypes;
            }
        }

        $this->warn('⚠️  This will delete unused assets from your project.');

        if (!empty($types)) {
            $this->comment('Asset types: ' . implode(', ', $types));
        } else {
            $this->comment('Asset types: all');
        }

        $this->newLine();

        $results = $scanner->scan($types);

        if (empty($results['unused'])) {
            $this->info('✅ No unused assets found!');
            return 0;
        }

        // Group by category for better display
        $byCategory = [];
        foreach ($results['unused'] as $asset) {
            $byCategory[$asset['category']][] = $asset;
        }

        foreach ($byCategory as $category => $assets) {
            $this->line("<fg=cyan>📦 {$category}</>");
            $this->table(
                ['File', 'Size', 'Extension'],
                array_map(fn($a) => [
                    $a['path'],
                    $this->formatBytes($a['size']),
                    $a['type']
                ], $assets)
            );
        }

        $totalSize = array_sum(array_column($results['unused'], 'size'));
        $this->info('Total size to be freed: ' . $this->formatBytes($totalSize));
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->info('🔍 Dry run - no files were deleted.');
            return 0;
        }

        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to proceed with deletion?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $createBackup = !$this->option('no-backup') && config('asset-cleaner.create_backup', true);
        $deleted = $deleter->delete($results['unused'], $createBackup);

        $this->newLine();
        $this->info("✅ Successfully deleted {$deleted} asset(s).");

        if ($createBackup) {
            $backupPath = config('asset-cleaner.backup_directory');
            $this->comment("Backup created at: {$backupPath}");
        }

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
