<?php

use Erag\OrphanStorage\Facades\OrphanStorage as OrphanStorageFacade;
use Erag\OrphanStorage\OrphanStorage;
use Erag\OrphanStorage\OrphanStorageServiceProvider;
use Illuminate\Support\ServiceProvider;

it('merges the package defaults into the config', function () {
    expect(config('orphan-storage.mode'))->toBe('trash')
        ->and(config('orphan-storage.grace_period_hours'))->toBe(24);
});

it('publishes the config file under the orphan-storage-config tag', function () {
    $paths = ServiceProvider::pathsToPublish(OrphanStorageServiceProvider::class, 'orphan-storage-config');

    expect(array_values($paths))->toBe([config_path('orphan-storage.php')])
        ->and(array_keys($paths)[0])->toBeFile();
});

it('resolves the facade to the shared manager', function () {
    expect(OrphanStorageFacade::getFacadeRoot())->toBe(app(OrphanStorage::class));
});
