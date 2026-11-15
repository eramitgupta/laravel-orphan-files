<?php

use Erag\OrphanStorage\Events\OrphanStorageDeleted;
use Erag\OrphanStorage\Events\OrphanStorageTrashed;
use Erag\OrphanStorage\Jobs\CleanOrphansJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Two referenced avatars and one orphan, so the orphan ratio stays at 33%.
 */
function seedAvatars(): void
{
    config()->set('orphan-storage.sources', [['table' => 'users', 'column' => 'avatar', 'type' => 'path']]);
    DB::table('users')->insert([['avatar' => 'avatars/jane.jpg'], ['avatar' => 'avatars/john.jpg']]);

    storeFile('public', 'avatars/jane.jpg');
    storeFile('public', 'avatars/john.jpg');
    storeFile('public', 'avatars/orphan.jpg', 'orphan');
}

it('changes nothing without --force', function () {
    Storage::fake('public');
    seedAvatars();

    $this->artisan('orphan:clean')
        ->expectsOutputToContain('public:avatars/orphan.jpg')
        ->expectsOutputToContain('Dry run: nothing was changed. Re-run with --force to trash 1 files.')
        ->assertSuccessful();

    Storage::disk('public')->assertExists('avatars/orphan.jpg');
    Storage::disk('public')->assertMissing('.orphan-trash');
});

it('moves orphans to the dated trash with --force', function () {
    $this->travelTo('2026-11-15 10:00:00');
    Storage::fake('public');
    seedAvatars();

    $this->artisan('orphan:clean', ['--force' => true])->assertSuccessful();

    Storage::disk('public')->assertMissing('avatars/orphan.jpg');
    Storage::disk('public')->assertExists(['avatars/jane.jpg', 'avatars/john.jpg']);
    expect(Storage::disk('public')->get('.orphan-trash/public/2026-11-15/avatars/orphan.jpg'))->toBe('orphan');
});

it('deletes orphans permanently in delete mode', function () {
    Storage::fake('public');
    seedAvatars();

    $this->artisan('orphan:clean', ['--force' => true, '--mode' => 'delete'])->assertSuccessful();

    Storage::disk('public')->assertMissing(['avatars/orphan.jpg', '.orphan-trash']);
});

it('rejects an unknown mode', function () {
    $this->artisan('orphan:clean', ['--mode' => 'shred'])
        ->expectsOutputToContain('Invalid mode [shred]')
        ->assertFailed();
});

it('protects references from every column type', function () {
    Storage::fake('public');
    config()->set('orphan-storage.sources', [
        ['table' => 'users', 'column' => 'avatar', 'type' => 'path'],
        ['table' => 'comments', 'column' => 'images', 'type' => 'json', 'key' => '*.path'],
        ['table' => 'posts', 'column' => 'body', 'type' => 'html'],
        ['table' => 'products', 'column' => 'image_url', 'type' => 'url'],
    ]);
    DB::table('users')->insert(['avatar' => '/avatars/jane.jpg']);
    DB::table('comments')->insert(['images' => json_encode([['path' => 'comments/1.jpg'], ['path' => 'comments/my%20photo.jpg']])]);
    DB::table('posts')->insert(['body' => '<p><img src="https://app.test/storage/posts/inline.png" alt=""></p>']);
    DB::table('products')->insert(['image_url' => 'https://app.test/storage/products/shoe.jpg?expires=1&signature=abc']);
    $referenced = ['avatars/jane.jpg', 'comments/1.jpg', 'comments/my photo.jpg', 'posts/inline.png', 'products/shoe.jpg'];

    foreach ($referenced as $path) {
        storeFile('public', $path);
    }

    storeFile('public', 'posts/orphan.png');

    $this->artisan('orphan:clean', ['--force' => true, '--mode' => 'delete'])->assertSuccessful();

    Storage::disk('public')->assertExists($referenced);
    Storage::disk('public')->assertMissing('posts/orphan.png');
});

it('keeps files modified within the grace period', function () {
    Storage::fake('public');
    seedAvatars();
    storeFile('public', 'avatars/fresh-upload.jpg', hoursOld: 2);

    $this->artisan('orphan:clean', ['--force' => true, '--mode' => 'delete'])->assertSuccessful();

    Storage::disk('public')->assertExists('avatars/fresh-upload.jpg');
    Storage::disk('public')->assertMissing('avatars/orphan.jpg');
});

it('cleans recent files when --older-than is shorter than the grace period', function () {
    Storage::fake('public');
    seedAvatars();
    storeFile('public', 'avatars/fresh-upload.jpg', hoursOld: 2);

    $this->artisan('orphan:clean', ['--force' => true, '--mode' => 'delete', '--older-than' => '1h'])->assertSuccessful();

    Storage::disk('public')->assertMissing('avatars/fresh-upload.jpg');
});

it('keeps excluded paths and patterns', function () {
    Storage::fake('public');
    config()->set('orphan-storage.exclude_paths', ['.gitignore']);
    config()->set('orphan-storage.exclude_patterns', ['defaults/*']);
    seedAvatars();
    storeFile('public', 'avatars/.gitignore');
    storeFile('public', 'defaults/placeholder.png');

    $this->artisan('orphan:clean', ['--force' => true, '--mode' => 'delete'])->assertSuccessful();

    Storage::disk('public')->assertExists(['avatars/.gitignore', 'defaults/placeholder.png']);
    Storage::disk('public')->assertMissing('avatars/orphan.jpg');
});

it('keeps files that fail the size and extension rules', function () {
    Storage::fake('public');
    config()->set('orphan-storage.min_size', 5);
    config()->set('orphan-storage.except_extensions', ['pdf']);
    seedAvatars();
    storeFile('public', 'docs/report.pdf', 'large enough');
    storeFile('public', 'avatars/tiny.jpg', 'x');

    $this->artisan('orphan:clean', ['--force' => true, '--mode' => 'delete'])->assertSuccessful();

    Storage::disk('public')->assertExists(['docs/report.pdf', 'avatars/tiny.jpg']);
    Storage::disk('public')->assertMissing('avatars/orphan.jpg');
});

it('aborts when the orphan ratio exceeds the threshold', function () {
    Storage::fake('public');
    config()->set('orphan-storage.max_orphan_ratio', 50);
    seedAvatars();
    storeFile('public', 'avatars/orphan-2.jpg');
    storeFile('public', 'avatars/orphan-3.jpg');

    $this->artisan('orphan:clean', ['--force' => true])
        ->expectsOutputToContain('Too many files would be cleaned (public: 60.0%, limit 50%)')
        ->assertFailed();

    Storage::disk('public')->assertExists(['avatars/orphan.jpg', 'avatars/orphan-2.jpg', 'avatars/orphan-3.jpg']);
});

it('cleans past the threshold with --force-ratio', function () {
    Storage::fake('public');
    config()->set('orphan-storage.max_orphan_ratio', 50);
    seedAvatars();
    storeFile('public', 'avatars/orphan-2.jpg');
    storeFile('public', 'avatars/orphan-3.jpg');

    $this->artisan('orphan:clean', ['--force' => true, '--force-ratio' => true, '--mode' => 'delete'])->assertSuccessful();

    Storage::disk('public')->assertMissing(['avatars/orphan.jpg', 'avatars/orphan-2.jpg', 'avatars/orphan-3.jpg']);
});

it('warns about the threshold during a dry run', function () {
    Storage::fake('public');
    config()->set('orphan-storage.max_orphan_ratio', 10);
    seedAvatars();

    $this->artisan('orphan:clean')
        ->expectsOutputToContain('Too many files would be cleaned')
        ->assertSuccessful();
});

it('stops after --limit files', function () {
    Storage::fake('public');
    seedAvatars();
    storeFile('public', 'avatars/orphan-2.jpg');
    storeFile('public', 'avatars/orphan-3.jpg');

    $this->artisan('orphan:clean', ['--force' => true, '--force-ratio' => true, '--mode' => 'delete', '--limit' => 2])->assertSuccessful();

    expect(Storage::disk('public')->files('avatars'))->toHaveCount(3);
});

it('aborts before touching storage when a configured column does not exist', function () {
    Storage::fake('public');
    seedAvatars();
    config()->push('orphan-storage.sources', ['table' => 'users', 'column' => 'photo', 'type' => 'path']);

    $this->artisan('orphan:clean', ['--force' => true])
        ->expectsOutputToContain("column [photo] doesn't exist on table [users]")
        ->assertFailed();

    Storage::disk('public')->assertExists('avatars/orphan.jpg');
});

it('dispatches OrphanStorageTrashed with the trashed paths', function () {
    $this->travelTo('2026-11-15 10:00:00');
    Storage::fake('public');
    seedAvatars();
    Event::fake([OrphanStorageTrashed::class]);

    $this->artisan('orphan:clean', ['--force' => true])->assertSuccessful();

    Event::assertDispatched(OrphanStorageTrashed::class, fn (OrphanStorageTrashed $event): bool => $event->disk === 'public'
        && $event->paths === ['avatars/orphan.jpg']
        && $event->bytes === 6
        && $event->trashDirectory === '.orphan-trash/public/2026-11-15');
});

it('dispatches OrphanStorageDeleted in delete mode', function () {
    Storage::fake('public');
    seedAvatars();
    Event::fake([OrphanStorageDeleted::class]);

    $this->artisan('orphan:clean', ['--force' => true, '--mode' => 'delete'])->assertSuccessful();

    Event::assertDispatched(OrphanStorageDeleted::class, fn (OrphanStorageDeleted $event): bool => $event->paths === ['avatars/orphan.jpg']);
});

it('requires --force to queue a clean', function () {
    Queue::fake([CleanOrphansJob::class]);

    $this->artisan('orphan:clean', ['--queue' => true])
        ->expectsOutputToContain('--queue needs --force')
        ->assertFailed();

    Queue::assertNothingPushed();
});

it('queues a forced clean', function () {
    Queue::fake([CleanOrphansJob::class]);

    $this->artisan('orphan:clean', ['--queue' => true, '--force' => true, '--mode' => 'delete', '--limit' => 10])->assertSuccessful();

    Queue::assertPushed(CleanOrphansJob::class, fn (CleanOrphansJob $job): bool => ! $job->options->dryRun
        && $job->options->mode === 'delete'
        && $job->options->limit === 10);
});

it('cleans orphans when the queued job runs', function () {
    Storage::fake('public');
    seedAvatars();

    $this->artisan('orphan:clean', ['--queue' => true, '--force' => true, '--mode' => 'delete'])->assertSuccessful();

    Storage::disk('public')->assertMissing('avatars/orphan.jpg');
});

it('never widens --dir beyond the configured directories', function () {
    Storage::fake('public');
    Storage::fake('local');
    config()->set('orphan-storage.disks', ['public' => ['avatars/'], 'local' => ['products/']]);
    storeFile('public', 'avatars/orphan.jpg');
    storeFile('local', 'avatars/private.jpg');

    $this->artisan('orphan:clean', ['--force' => true, '--force-ratio' => true, '--mode' => 'delete', '--dir' => ['avatars']])->assertSuccessful();

    Storage::disk('public')->assertMissing('avatars/orphan.jpg');
    Storage::disk('local')->assertExists('avatars/private.jpg');
});

it('rejects --dir outside the configured directories', function () {
    Storage::fake('public');
    config()->set('orphan-storage.disks', ['public' => ['avatars/']]);
    storeFile('public', 'posts/orphan.jpg');

    $this->artisan('orphan:clean', ['--force' => true, '--dir' => ['posts']])
        ->expectsOutputToContain('outside the configured directories')
        ->assertFailed();

    Storage::disk('public')->assertExists('posts/orphan.jpg');
});
