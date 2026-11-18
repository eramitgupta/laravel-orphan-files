<?php

namespace Erag\OrphanStorage;

use Erag\OrphanStorage\Commands\CleanCommand;
use Erag\OrphanStorage\Commands\ExplainCommand;
use Erag\OrphanStorage\Commands\InstallCommand;
use Erag\OrphanStorage\Commands\PurgeTrashCommand;
use Erag\OrphanStorage\Commands\RestoreCommand;
use Erag\OrphanStorage\Commands\ScanCommand;
use Erag\OrphanStorage\References\ReferenceCollector;
use Erag\OrphanStorage\References\SourceRegistry;
use Erag\OrphanStorage\Support\Normalizers\NormalizerPipeline;
use Illuminate\Support\ServiceProvider;

class OrphanStorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/orphan-storage.php', 'orphan-storage');

        $this->app->singleton(NormalizerPipeline::class);
        $this->app->singleton(ReferenceCollector::class);
        $this->app->singleton(SourceRegistry::class);
        $this->app->singleton(OrphanStorage::class);
        $this->app->alias(OrphanStorage::class, 'orphan-storage');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/orphan-storage.php' => config_path('orphan-storage.php'),
        ], 'orphan-storage-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                ScanCommand::class,
                CleanCommand::class,
                RestoreCommand::class,
                PurgeTrashCommand::class,
                ExplainCommand::class,
            ]);
        }
    }
}
