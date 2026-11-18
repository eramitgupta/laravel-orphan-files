<?php

use Erag\OrphanFiles\Contracts\ReferenceExtractor;
use Erag\OrphanFiles\Diff\TempTableDiffer;
use Erag\OrphanFiles\References\ReferenceCollector;
use Erag\OrphanFiles\References\Source;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses()->group('database');

afterEach(fn () => Str::createRandomStringsNormally());

/**
 * Two referenced avatars and one orphan, read through the avatar column.
 */
function seedAvatarReferences(): void
{
    config()->set('orphan-files.sources', [['table' => 'users', 'column' => 'avatar', 'type' => 'path']]);
    DB::table('users')->insert([['avatar' => 'avatars/jane.jpg'], ['avatar' => 'avatars/john.jpg']]);

    storeFile('public', 'avatars/jane.jpg');
    storeFile('public', 'avatars/john.jpg');
    storeFile('public', 'avatars/orphan.jpg');
}

it('drops the temporary table after a scan', function () {
    Storage::fake('public');
    $table = fixReferenceTableName();
    config()->set('orphan-files.diff.driver', 'database');
    seedAvatarReferences();

    $totals = scanReport()['totals'];

    expect([$totals['referenced'], $totals['cleanable']])->toBe([2, 1])
        ->and(referenceTableExists($table))->toBeFalse();
});

it('drops the temporary table after a forced clean', function () {
    Storage::fake('public');
    $table = fixReferenceTableName();
    config()->set('orphan-files.diff.driver', 'database');
    seedAvatarReferences();

    $this->artisan('orphan:clean', ['--force' => true, '--mode' => 'delete'])->assertSuccessful();

    Storage::disk('public')->assertMissing('avatars/orphan.jpg');
    Storage::disk('public')->assertExists(['avatars/jane.jpg', 'avatars/john.jpg']);
    expect(referenceTableExists($table))->toBeFalse();
});

it('drops the temporary table when indexing fails', function () {
    Storage::fake('public');
    $table = fixReferenceTableName();
    config()->set('orphan-files.diff.driver', 'database');
    config()->set('orphan-files.chunk_size', 1);
    config()->set('orphan-files.sources', [['table' => 'posts', 'column' => 'body', 'type' => 'failing']]);
    DB::table('posts')->insert([['body' => 'posts/a.jpg'], ['body' => 'posts/b.jpg']]);
    app(ReferenceCollector::class)->extend('failing', new class implements ReferenceExtractor
    {
        public function extract(mixed $value, Source $source): iterable
        {
            if ($value === 'posts/b.jpg') {
                throw new RuntimeException('Extractor failed.');
            }

            return [$value];
        }
    });

    $this->artisan('orphan:scan')
        ->expectsOutputToContain('Extractor failed.')
        ->assertFailed();

    expect(referenceTableExists($table))->toBeFalse();
});

it('leaves no table behind when moving references to the database fails', function () {
    Storage::fake('public');
    $table = fixReferenceTableName();
    config()->set('orphan-files.diff.memory_limit', 1);
    config()->set('orphan-files.chunk_size', 1);
    seedAvatarReferences();
    $this->app->bind(TempTableDiffer::class, fn ($app, array $parameters) => new class($parameters['connection']) extends TempTableDiffer
    {
        protected function insertMissing(array $hashes): int
        {
            throw new RuntimeException('Connection lost while inserting.');
        }
    });

    $this->artisan('orphan:scan')
        ->expectsOutputToContain('Connection lost while inserting.')
        ->assertFailed();

    expect(referenceTableExists($table))->toBeFalse();
});

it('keeps references in memory and logs a warning when auto mode cannot create the table', function () {
    Storage::fake('public');
    Log::spy();
    config()->set('orphan-files.diff.connection', useUnavailableConnection());
    config()->set('orphan-files.diff.memory_limit', 1);
    config()->set('orphan-files.chunk_size', 1);
    seedAvatarReferences();

    $totals = scanReport()['totals'];

    expect([$totals['referenced'], $totals['cleanable']])->toBe([2, 1]);
    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message): bool => str_contains($message, 'keeps references in memory')
            && str_contains($message, 'database connection [unavailable]'),
    );
});

it('shows a clear error when the database driver cannot create its table', function () {
    Storage::fake('public');
    config()->set('orphan-files.diff.driver', 'database');
    config()->set('orphan-files.diff.connection', useUnavailableConnection());
    seedAvatarReferences();

    $this->artisan('orphan:clean', ['--force' => true])
        ->expectsOutputToContain('database connection [unavailable]')
        ->assertFailed();

    Storage::disk('public')->assertExists('avatars/orphan.jpg');
});

it('rejects an unknown diff driver', function () {
    Storage::fake('public');
    config()->set('orphan-files.diff.driver', 'databse');

    $this->artisan('orphan:scan')
        ->expectsOutputToContain('Invalid "orphan-files.diff.driver" [databse]')
        ->assertFailed();
});
