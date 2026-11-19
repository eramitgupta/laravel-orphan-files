<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('restores a cleaned file to its original path', function () {
    $this->travelTo('2026-11-15 10:00:00');
    Storage::fake('public');
    config()->set('orphan-files.sources', [['table' => 'users', 'column' => 'avatar', 'type' => 'path']]);
    DB::table('users')->insert(['avatar' => 'avatars/jane.jpg']);
    storeFile('public', 'avatars/jane.jpg');
    storeFile('public', 'avatars/orphan.jpg', 'orphan');
    $this->artisan('orphan:clean', ['--force' => true, '--force-ratio' => true])->assertSuccessful();

    $this->artisan('orphan:restore', ['paths' => ['avatars/orphan.jpg']])
        ->expectsOutputToContain('Restored 1 file (')
        ->assertSuccessful();

    expect(Storage::disk('public')->get('avatars/orphan.jpg'))->toBe('orphan');
    Storage::disk('public')->assertMissing('.orphan-trash/public/2026-11-15/avatars/orphan.jpg');
});

it('restores every file below a trashed directory', function () {
    Storage::fake('public');
    storeTrashedFile('public', '2026-11-01', 'comments/1/a.jpg');
    storeTrashedFile('public', '2026-11-01', 'comments/1/b.jpg');
    storeTrashedFile('public', '2026-11-01', 'comments/2/c.jpg');

    $this->artisan('orphan:restore', ['paths' => ['comments/1']])->assertSuccessful();

    Storage::disk('public')->assertExists(['comments/1/a.jpg', 'comments/1/b.jpg']);
    Storage::disk('public')->assertMissing('comments/2/c.jpg');
});

it('restores the newest copy when a path was trashed on several dates', function () {
    Storage::fake('public');
    storeTrashedFile('public', '2026-10-01', 'avatars/jane.jpg', 'older');
    storeTrashedFile('public', '2026-11-01', 'avatars/jane.jpg', 'newer');

    $this->artisan('orphan:restore', ['--all' => true])->assertSuccessful();

    expect(Storage::disk('public')->get('avatars/jane.jpg'))->toBe('newer');
});

it('restores from a specific date', function () {
    Storage::fake('public');
    storeTrashedFile('public', '2026-10-01', 'avatars/jane.jpg', 'older');
    storeTrashedFile('public', '2026-11-01', 'avatars/jane.jpg', 'newer');

    $this->artisan('orphan:restore', ['paths' => ['avatars/jane.jpg'], '--date' => '2026-10-01'])->assertSuccessful();

    expect(Storage::disk('public')->get('avatars/jane.jpg'))->toBe('older');
});

it('keeps an existing file unless --overwrite is given', function () {
    Storage::fake('public');
    Storage::disk('public')->put('avatars/jane.jpg', 'current');
    storeTrashedFile('public', '2026-11-01', 'avatars/jane.jpg', 'trashed');

    $this->artisan('orphan:restore', ['paths' => ['avatars/jane.jpg']])
        ->expectsOutputToContain('A file already exists at the original path')
        ->assertFailed();

    expect(Storage::disk('public')->get('avatars/jane.jpg'))->toBe('current');

    $this->artisan('orphan:restore', ['paths' => ['avatars/jane.jpg'], '--overwrite' => true])->assertSuccessful();

    expect(Storage::disk('public')->get('avatars/jane.jpg'))->toBe('trashed');
});

it('reports paths that are not in the trash', function () {
    Storage::fake('public');

    $this->artisan('orphan:restore', ['paths' => ['avatars/missing.jpg']])
        ->expectsOutputToContain('Not found in the trash.')
        ->assertFailed();
});

it('lists trashed files', function () {
    Storage::fake('public');
    storeTrashedFile('public', '2026-11-01', 'avatars/jane.jpg');

    $this->artisan('orphan:restore', ['--list' => true])
        ->expectsOutputToContain('avatars/jane.jpg')
        ->assertSuccessful();

    Storage::disk('public')->assertMissing('avatars/jane.jpg');
});

it('moves files through a dedicated trash disk', function () {
    $this->travelTo('2026-11-15 10:00:00');
    Storage::fake('public');
    Storage::fake('local');
    config()->set('orphan-files.trash.disk', 'local');
    storeFile('public', 'avatars/orphan.jpg', 'orphan');

    $this->artisan('orphan:clean', ['--force' => true, '--force-ratio' => true])->assertSuccessful();

    Storage::disk('public')->assertMissing('avatars/orphan.jpg');
    Storage::disk('local')->assertExists('.orphan-trash/public/2026-11-15/avatars/orphan.jpg');

    $this->artisan('orphan:restore', ['paths' => ['avatars/orphan.jpg'], '--disk' => 'public'])->assertSuccessful();

    expect(Storage::disk('public')->get('avatars/orphan.jpg'))->toBe('orphan');
    Storage::disk('local')->assertMissing('.orphan-trash/public/2026-11-15/avatars/orphan.jpg');
});

it('purges trash older than the retention period', function () {
    $this->travelTo('2026-11-15 10:00:00');
    Storage::fake('public');
    config()->set('orphan-files.trash.retention_days', 30);
    storeTrashedFile('public', '2026-10-15', 'avatars/old.jpg');
    storeTrashedFile('public', '2026-10-16', 'avatars/recent.jpg');

    $this->artisan('orphan:purge-trash')
        ->expectsOutputToContain('Purged 1 trashed file (')
        ->assertSuccessful();

    Storage::disk('public')->assertMissing('.orphan-trash/public/2026-10-15');
    Storage::disk('public')->assertExists('.orphan-trash/public/2026-10-16/avatars/recent.jpg');
});

it('purges every date with --all', function () {
    $this->travelTo('2026-11-15 10:00:00');
    Storage::fake('public');
    storeTrashedFile('public', '2026-11-15', 'avatars/today.jpg');

    $this->artisan('orphan:purge-trash', ['--all' => true])->assertSuccessful();

    Storage::disk('public')->assertMissing('.orphan-trash/public/2026-11-15');
});

it('only reports what would be purged with --dry-run', function () {
    $this->travelTo('2026-11-15 10:00:00');
    Storage::fake('public');
    storeTrashedFile('public', '2026-01-01', 'avatars/old.jpg');

    $this->artisan('orphan:purge-trash', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: 1 trashed file (')
        ->assertSuccessful();

    Storage::disk('public')->assertExists('.orphan-trash/public/2026-01-01/avatars/old.jpg');
});

it('leaves folders that are not trash dates alone', function () {
    $this->travelTo('2026-11-15 10:00:00');
    Storage::fake('public');
    Storage::disk('public')->put('.orphan-trash/public/notes/readme.txt', 'keep');

    $this->artisan('orphan:purge-trash', ['--all' => true])->assertSuccessful();

    Storage::disk('public')->assertExists('.orphan-trash/public/notes/readme.txt');
});

it('keeps both copies when a path is trashed twice on the same day', function () {
    $this->travelTo('2026-11-15 10:00:00');
    Storage::fake('public');
    storeFile('public', 'avatars/orphan.jpg', 'first');
    $this->artisan('orphan:clean', ['--force' => true, '--force-ratio' => true])->assertSuccessful();
    storeFile('public', 'avatars/orphan.jpg', 'second');

    $this->artisan('orphan:clean', ['--force' => true, '--force-ratio' => true])->assertSuccessful();

    expect(Storage::disk('public')->get('.orphan-trash/public/2026-11-15/avatars/orphan.jpg'))->toBe('first')
        ->and(Storage::disk('public')->get('.orphan-trash/public/2026-11-15_100000/avatars/orphan.jpg'))->toBe('second');
});

it('restores the newest copy when a path was trashed twice on the same day', function () {
    Storage::fake('public');
    storeTrashedFile('public', '2026-11-15', 'avatars/jane.jpg', 'morning');
    storeTrashedFile('public', '2026-11-15_150000', 'avatars/jane.jpg', 'afternoon');

    $this->artisan('orphan:restore', ['paths' => ['avatars/jane.jpg'], '--date' => '2026-11-15'])->assertSuccessful();

    expect(Storage::disk('public')->get('avatars/jane.jpg'))->toBe('afternoon');
});

it('purges today\'s trash with --days=0', function () {
    $this->travelTo('2026-11-15 10:00:00');
    Storage::fake('public');
    storeTrashedFile('public', '2026-11-15', 'avatars/today.jpg');

    $this->artisan('orphan:purge-trash', ['--days' => 0])
        ->expectsOutputToContain('Purged 1 trashed file (')
        ->assertSuccessful();

    Storage::disk('public')->assertMissing('.orphan-trash/public/2026-11-15/avatars/today.jpg');
});

it('lists and restores the trash of every configured disk when --disk is not given', function () {
    Storage::fake('public');
    Storage::fake('s3');
    config()->set('orphan-files.disks', ['public' => ['avatars/'], 's3' => ['uploads/']]);
    storeTrashedFile('public', '2026-11-15', 'avatars/a.jpg', 'a');
    storeTrashedFile('s3', '2026-11-15', 'uploads/b.jpg', 'b');

    $this->artisan('orphan:restore', ['--list' => true])
        ->expectsOutputToContain('avatars/a.jpg')
        ->expectsOutputToContain('uploads/b.jpg')
        ->assertSuccessful();

    $this->artisan('orphan:restore', ['--all' => true])
        ->expectsOutputToContain('Restored 2 files')
        ->assertSuccessful();

    Storage::disk('public')->assertExists('avatars/a.jpg');
    Storage::disk('s3')->assertExists('uploads/b.jpg');

    $this->artisan('orphan:restore', ['--list' => true])
        ->expectsOutputToContain('The trash is empty on public and s3.')
        ->assertSuccessful();
});

it('finds a path in the trash of any configured disk when --disk is not given', function () {
    Storage::fake('public');
    Storage::fake('s3');
    config()->set('orphan-files.disks', ['public' => ['avatars/'], 's3' => ['uploads/']]);
    storeTrashedFile('s3', '2026-11-15', 'uploads/b.jpg', 'b');

    $this->artisan('orphan:restore', ['paths' => ['uploads/b.jpg']])
        ->expectsOutputToContain('Restored 1 file (')
        ->assertSuccessful();

    Storage::disk('s3')->assertExists('uploads/b.jpg');
});

it('reports a path that is in no trash once, naming the disks it checked', function () {
    Storage::fake('public');
    Storage::fake('s3');
    config()->set('orphan-files.disks', ['public' => ['avatars/'], 's3' => ['uploads/']]);

    $this->artisan('orphan:restore', ['paths' => ['uploads/missing.jpg']])
        ->expectsOutputToContain('Not found in the trash of public or s3.')
        ->assertFailed();
});

it('warns instead of reporting success when a restore is refused', function () {
    Storage::fake('public');
    storeTrashedFile('public', '2026-11-15', 'avatars/a.jpg', 'old');
    Storage::disk('public')->put('avatars/a.jpg', 'new');

    $this->artisan('orphan:restore', ['paths' => ['avatars/a.jpg'], '--disk' => 'public'])
        ->expectsOutputToContain('A file already exists at the original path. Use --overwrite to replace it.')
        ->expectsOutputToContain('1 file could not be restored, see above.')
        ->doesntExpectOutputToContain('Restored 0 files')
        ->assertFailed();

    expect(Storage::disk('public')->get('avatars/a.jpg'))->toBe('new');
});
