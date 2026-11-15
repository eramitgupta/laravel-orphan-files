<?php

use Erag\OrphanStorage\OrphanStorage;
use Illuminate\Support\Facades\DB;

it('reports a file as referenced when a source points at it', function () {
    config()->set('orphan-storage.sources', [['table' => 'users', 'column' => 'avatar', 'type' => 'path']]);
    DB::table('users')->insert(['avatar' => 'avatars/jane.jpg']);

    expect(app(OrphanStorage::class)->isReferenced('public', 'avatars/jane.jpg'))->toBeTrue();
});

it('reports a file as unreferenced when no source points at it', function () {
    config()->set('orphan-storage.sources', [['table' => 'users', 'column' => 'avatar', 'type' => 'path']]);
    DB::table('users')->insert(['avatar' => 'avatars/jane.jpg']);

    expect(app(OrphanStorage::class)->isReferenced('public', 'avatars/john.jpg'))->toBeFalse();
});
