<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('lists the rows that reference a file', function () {
    Storage::fake('public');
    config()->set('orphan-files.sources', [['table' => 'users', 'column' => 'avatar', 'type' => 'path']]);
    DB::table('users')->insert(['avatar' => '/avatars/jane.jpg']);
    storeFile('public', 'avatars/jane.jpg');

    $this->artisan('orphan:explain', ['path' => 'avatars/jane.jpg'])
        ->expectsOutputToContain('users.avatar')
        ->expectsOutputToContain('Kept: referenced by 1 row(s).')
        ->assertSuccessful();
});

it('finds references in rich text and URLs', function () {
    Storage::fake('public');
    config()->set('orphan-files.sources', [['table' => 'posts', 'column' => 'body', 'type' => 'html']]);
    DB::table('posts')->insert(['body' => '<p>Intro</p><img src="https://app.test/storage/posts/my%20chart.png">']);
    storeFile('public', 'posts/my chart.png');

    $this->artisan('orphan:explain', ['path' => 'https://app.test/storage/posts/my%20chart.png'])
        ->expectsOutputToContain('posts.body')
        ->expectsOutputToContain('Kept: referenced by 1 row(s).')
        ->assertSuccessful();
});

it('explains why an unreferenced file is an orphan', function () {
    Storage::fake('public');
    config()->set('orphan-files.sources', [['table' => 'users', 'column' => 'avatar', 'type' => 'path']]);
    storeFile('public', 'avatars/orphan.jpg');

    $this->artisan('orphan:explain', ['path' => 'avatars/orphan.jpg'])
        ->expectsOutputToContain('Orphan: none of the 1 sources reference it. orphan:clean --force would trash it.')
        ->assertSuccessful();
});

it('explains why an unreferenced file is kept', function () {
    Storage::fake('public');
    storeFile('public', 'avatars/fresh.jpg', hoursOld: 1);

    $this->artisan('orphan:explain', ['path' => 'avatars/fresh.jpg'])
        ->expectsOutputToContain('Not referenced, but kept: it was modified within the grace period (24 hours).')
        ->assertSuccessful();
});

it('reports a reference to a missing file', function () {
    Storage::fake('public');
    config()->set('orphan-files.sources', [['table' => 'users', 'column' => 'avatar', 'type' => 'path']]);
    DB::table('users')->insert(['avatar' => 'avatars/gone.jpg']);

    $this->artisan('orphan:explain', ['path' => 'avatars/gone.jpg'])
        ->expectsOutputToContain('the file is missing from the disk (a broken reference)')
        ->assertSuccessful();
});

it('finds references whose file name is partly URL-encoded', function () {
    Storage::fake('public');
    config()->set('orphan-files.sources', [['table' => 'posts', 'column' => 'body', 'type' => 'html']]);
    DB::table('posts')->insert(['body' => '<img src="/storage/avatars/photo%20(1).jpg">']);
    storeFile('public', 'avatars/photo (1).jpg');

    $this->artisan('orphan:explain', ['path' => 'avatars/photo (1).jpg'])
        ->expectsOutputToContain('Kept: referenced by 1 row(s).')
        ->assertSuccessful();
});
