<?php

use Erag\OrphanFiles\Facades\OrphanFiles as OrphanFilesFacade;
use Erag\OrphanFiles\OrphanFiles;
use Erag\OrphanFiles\OrphanFilesServiceProvider;
use Illuminate\Support\ServiceProvider;

it('merges the package defaults into the config', function () {
    expect(config('orphan-files.mode'))->toBe('trash')
        ->and(config('orphan-files.grace_period_hours'))->toBe(24);
});

it('publishes the config file under the orphan-files-config tag', function () {
    $paths = ServiceProvider::pathsToPublish(OrphanFilesServiceProvider::class, 'orphan-files-config');

    expect(array_values($paths))->toBe([config_path('orphan-files.php')])
        ->and(array_keys($paths)[0])->toBeFile();
});

it('resolves the facade to the shared manager', function () {
    expect(OrphanFilesFacade::getFacadeRoot())->toBe(app(OrphanFiles::class));
});
