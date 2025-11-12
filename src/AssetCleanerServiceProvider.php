<?php

namespace TarunKorat\AssetCleaner;

use Illuminate\Support\ServiceProvider;
use Tarunkorat\AssetCleaner\Commands\DebugScanCommand;
use TarunKorat\AssetCleaner\Commands\ScanUnusedAssetsCommand;
use TarunKorat\AssetCleaner\Commands\DeleteUnusedAssetsCommand;

class AssetCleanerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/asset-cleaner.php', 'asset-cleaner'
        );
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/asset-cleaner.php' => config_path('asset-cleaner.php'),
            ], 'asset-cleaner-config');

            $this->commands([
                ScanUnusedAssetsCommand::class,
                DeleteUnusedAssetsCommand::class,
                DebugScanCommand::class,
            ]);
        }
    }
}
