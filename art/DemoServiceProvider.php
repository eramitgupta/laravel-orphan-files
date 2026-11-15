<?php

namespace OrphanStorageDemo;

use Illuminate\Support\ServiceProvider;

/**
 * Points the demo app at a throwaway SQLite database and a local "public"
 * disk inside art/, and registers the comment and avatar sources.
 */
class DemoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->app['config'];

        $config->set('database.connections.demo', [
            'driver' => 'sqlite',
            'database' => __DIR__.'/database.sqlite',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $config->set('database.default', 'demo');

        $config->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => __DIR__.'/storage/public',
            'url' => 'https://shop.test/storage',
            'visibility' => 'public',
            'throw' => false,
        ]);

        $config->set('orphan-storage.disks', ['public' => ['avatars/', 'comments/']]);
        $config->set('orphan-storage.sources', [
            ['table' => 'users', 'column' => 'avatar', 'type' => 'path', 'disk' => 'public'],
            ['table' => 'comments', 'column' => 'images', 'type' => 'json', 'key' => '*.path', 'disk' => 'public'],
        ]);
        $config->set('orphan-storage.discovery.enabled', false);
    }
}
