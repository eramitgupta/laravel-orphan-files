<?php

namespace Erag\OrphanFiles;

use Erag\OrphanFiles\Commands\CleanCommand;
use Erag\OrphanFiles\Commands\ExplainCommand;
use Erag\OrphanFiles\Commands\InstallCommand;
use Erag\OrphanFiles\Commands\PurgeTrashCommand;
use Erag\OrphanFiles\Commands\RestoreCommand;
use Erag\OrphanFiles\Commands\ScanCommand;
use Erag\OrphanFiles\References\ReferenceCollector;
use Erag\OrphanFiles\References\SourceRegistry;
use Erag\OrphanFiles\Support\Normalizers\NormalizerPipeline;
use Illuminate\Support\ServiceProvider;

class OrphanFilesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/orphan-files.php', 'orphan-files');

        $this->app->singleton(NormalizerPipeline::class);
        $this->app->singleton(ReferenceCollector::class);
        $this->app->singleton(SourceRegistry::class);
        $this->app->singleton(OrphanFiles::class);
        $this->app->alias(OrphanFiles::class, 'orphan-files');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/orphan-files.php' => config_path('orphan-files.php'),
        ], 'orphan-files-config');

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
