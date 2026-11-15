<?php

use Erag\OrphanStorage\OrphanStorageServiceProvider;
use Orchestra\Testbench\Foundation\Application;
use OrphanStorageDemo\DemoServiceProvider;

/*
|--------------------------------------------------------------------------
| Demo Application
|--------------------------------------------------------------------------
|
| A throwaway Laravel app (Orchestra Testbench) running the real package.
| Used only to record the README demo: vhs art/demo.tape
|
*/

require __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/DemoServiceProvider.php';

return Application::create(options: [
    'extra' => [
        'providers' => [
            OrphanStorageServiceProvider::class,
            DemoServiceProvider::class,
        ],
        'dont-discover' => ['*'],
    ],
]);
