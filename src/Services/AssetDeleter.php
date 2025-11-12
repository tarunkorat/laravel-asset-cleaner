<?php

namespace TarunKorat\AssetCleaner\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class AssetDeleter
{
    private ?string $currentBackupDir = null;

    public function delete(array $assets, bool $createBackup = true): int
    {
        $deleted = 0;
        $failed = [];
        $backupRoot = config('asset-cleaner.backup_directory');

        if ($createBackup) {
            $this->currentBackupDir = $this->ensureBackupDirectory($backupRoot);
        }

        foreach ($assets as $asset) {
            $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $asset['path']);

            // Detect absolute vs relative
            if (preg_match('/^[A-Za-z]:\\\\/', $path) || str_starts_with($path, DIRECTORY_SEPARATOR)) {
                $filePath = $path;
            } else {
                $filePath = base_path($path);
            }

            // Canonicalize and normalize
            $filePath = realpath($filePath) ?: $filePath;
            $filePath = preg_replace('/[\/\\\\]+/', DIRECTORY_SEPARATOR, $filePath);

            if (!File::exists($filePath)) {
                $this->line("⚠️  Not found: {$filePath}");
                $failed[] = "{$filePath} (not found)";
                continue;
            }

            // Ensure writable
            @chmod($filePath, 0666);
            if (!is_writable($filePath)) {
                $this->line("⚠️  Not writable: {$filePath}");
                $failed[] = "{$filePath} (not writable)";
                continue;
            }

            // Backup before deletion
            if ($createBackup) {
                try {
                    $this->backupFile($filePath, $asset['path']);
                    $this->line("💾 Backed up: {$asset['path']}");
                } catch (\Throwable $e) {
                    $failed[] = "{$filePath} (backup failed: {$e->getMessage()})";
                    continue;
                }
            }

            // Try delete
            try {
                $this->line("Deleting: {$filePath}");

                // Windows-safe long path
                $windowsSafe = (str_starts_with($filePath, '\\\\?\\') ? $filePath : '\\\\?\\' . $filePath);

                $success = false;

                // Prefer unlink first
                if (file_exists($filePath)) {
                    $success = @unlink($windowsSafe);
                }

                // Fallback to File::delete()
                if (!$success && file_exists($filePath)) {
                    $success = File::delete($filePath);
                }

                clearstatcache();

                if ($success && !file_exists($filePath)) {
                    $deleted++;
                    $this->line("🗑️  Deleted: {$asset['path']}");
                    $this->cleanEmptyDirectories(dirname($filePath));
                } else {
                    $failed[] = "{$filePath} (delete failed)";
                    $this->line("❌ Delete failed: {$asset['path']}");
                }
            } catch (\Throwable $e) {
                $failed[] = "{$filePath} (exception: {$e->getMessage()})";
                $this->line("❌ Exception deleting {$asset['path']}: " . $e->getMessage());
            }
        }

        if ($failed) {
            Log::warning('Asset Cleaner: Some files failed to delete', [
                'failed' => $failed,
                'deleted_count' => $deleted,
            ]);
        }

        return $deleted;
    }


    private function ensureBackupDirectory(string $backupRoot): string
    {
        if (!File::isDirectory($backupRoot)) {
            File::makeDirectory($backupRoot, 0755, true);
        }

        $timestamped = $backupRoot . DIRECTORY_SEPARATOR . date('Y-m-d_His');
        if (!File::isDirectory($timestamped)) {
            File::makeDirectory($timestamped, 0755, true);
        }

        return $timestamped;
    }

    private function backupFile(string $filePath, string $relativePath): void
    {
        if (!$this->currentBackupDir) {
            throw new \Exception('Backup directory not initialized');
        }

        // 🧩 Normalize: if absolute Windows path, strip base_path() and drive letter
        if (preg_match('/^[A-Za-z]:\\\\/', $relativePath)) {
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $relativePath);
        }

        // Normalize directory separators
        $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);

        // Construct proper backup path
        $backupPath = $this->currentBackupDir . DIRECTORY_SEPARATOR . $relativePath;
        $backupDirPath = dirname($backupPath);

        // Ensure directory exists
        if (!File::isDirectory($backupDirPath)) {
            File::makeDirectory($backupDirPath, 0755, true);
        }

        // Copy file
        if (!File::copy($filePath, $backupPath)) {
            throw new \Exception('Failed to copy file to backup location');
        }
    }

    private function cleanEmptyDirectories(string $directory): void
    {
        $base = base_path();
        $protected = [$base, "{$base}/resources", "{$base}/public", "{$base}/app"];

        if (in_array($directory, $protected, true)) {
            return;
        }

        if (!File::isDirectory($directory)) {
            return;
        }

        $files = File::files($directory);
        $dirs = File::directories($directory);

        if (!$files && !$dirs) {
            try {
                File::deleteDirectory($directory);
                $parent = dirname($directory);
                if ($parent !== $directory) {
                    $this->cleanEmptyDirectories($parent);
                }
            } catch (\Throwable $e) {
                Log::debug("Could not delete empty directory: {$directory}");
            }
        }
    }

    private function line(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message . PHP_EOL;
        }
    }
}
