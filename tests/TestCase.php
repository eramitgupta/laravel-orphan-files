<?php

namespace Tests;

use Erag\OrphanStorage\OrphanStorageServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            OrphanStorageServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.url', 'https://app.test');

        // SQLite in memory by default; CI runs the "database" group on MySQL, MariaDB, PostgreSQL and SQL Server.
        $app['config']->set('database.default', env('DB_CONNECTION', 'testing'));
        $app['config']->set('database.connections.sqlsrv.trust_server_certificate', true);

        $app['config']->set('filesystems.disks.public.url', 'https://app.test/storage');
        $app['config']->set('filesystems.disks.s3', [
            'driver' => 's3',
            'bucket' => 'orphan-bucket',
            'region' => 'eu-west-1',
            'root' => 'tenant-1',
        ]);
        $app['config']->set('orphan-storage.discovery.paths', []);
        $app['config']->set('orphan-storage.disks', ['public' => ['']]);
    }

    /**
     * Create the fixture tables directly; the package ships no migrations.
     * Dropping first keeps persistent databases (MySQL, PostgreSQL) clean between tests.
     */
    protected function defineDatabaseMigrations(): void
    {
        $migration = require __DIR__.'/Fixtures/migrations/2026_01_01_000000_create_fixture_tables.php';

        $migration->down();
        $migration->up();
    }
}
