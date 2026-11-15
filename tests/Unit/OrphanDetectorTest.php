<?php

use Erag\OrphanStorage\Contracts\ReferenceExtractor;
use Erag\OrphanStorage\Diff\OrphanDetector;
use Erag\OrphanStorage\References\Reference;
use Erag\OrphanStorage\References\ReferenceCollector;
use Erag\OrphanStorage\References\Source;
use Erag\OrphanStorage\Scanner\StorageScanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses()->group('database');
use Tests\Fixtures\Models\Document;

/**
 * @return array<string, bool> Referenced flag per path.
 */
function classifiedPaths(OrphanDetector $detector, string $disk): array
{
    $result = [];

    foreach ($detector->classify(app(StorageScanner::class)->files($disk)) as $item) {
        $result[$item['file']->path] = $item['referenced'];
    }

    ksort($result);

    return $result;
}

it('marks files referenced by a source', function () {
    Storage::fake('public');
    storeFile('public', 'avatars/kept.jpg');
    storeFile('public', 'avatars/orphan.jpg');
    DB::table('users')->insert(['avatar' => 'avatars/kept.jpg']);
    $detector = app(OrphanDetector::class);

    $detector->index([new Source('users', 'avatar')], ['public']);

    expect(classifiedPaths($detector, 'public'))->toBe([
        'avatars/kept.jpg' => true,
        'avatars/orphan.jpg' => false,
    ]);
});

it('only matches references on the source disk', function () {
    Storage::fake('public');
    Storage::fake('s3');
    storeFile('public', 'avatars/jane.jpg');
    storeFile('s3', 'avatars/jane.jpg');
    DB::table('users')->insert(['avatar' => 'avatars/jane.jpg']);
    $detector = app(OrphanDetector::class);

    $detector->index([new Source('users', 'avatar', disk: 's3')], ['public', 's3']);

    expect(classifiedPaths($detector, 'public'))->toBe(['avatars/jane.jpg' => false])
        ->and(classifiedPaths($detector, 's3'))->toBe(['avatars/jane.jpg' => true]);
});

it('treats files below a referenced directory as referenced', function () {
    Storage::fake('public');
    storeFile('public', 'galleries/7/photo.jpg');
    storeFile('public', 'galleries/7/thumbs/photo.jpg');
    storeFile('public', 'galleries/8/photo.jpg');
    DB::table('posts')->insert(['body' => 'galleries/7']);
    app(ReferenceCollector::class)->extend('gallery', new class implements ReferenceExtractor
    {
        public function extract(mixed $value, Source $source): iterable
        {
            return [Reference::directory((string) $value)];
        }
    });
    $detector = app(OrphanDetector::class);

    $detector->index([new Source('posts', 'body', 'gallery')], ['public']);

    expect(classifiedPaths($detector, 'public'))->toBe([
        'galleries/7/photo.jpg' => true,
        'galleries/7/thumbs/photo.jpg' => true,
        'galleries/8/photo.jpg' => false,
    ]);
});

it('gives the same result with the temporary table driver', function () {
    config()->set('orphan-storage.diff.driver', 'database');
    Storage::fake('public');
    storeFile('public', 'avatars/kept.jpg');
    storeFile('public', 'avatars/orphan.jpg');
    DB::table('users')->insert(['avatar' => 'avatars/kept.jpg']);
    $detector = app(OrphanDetector::class);

    $detector->index([new Source('users', 'avatar')], ['public']);

    expect($detector->usesDatabase())->toBeTrue()
        ->and(classifiedPaths($detector, 'public'))->toBe([
            'avatars/kept.jpg' => true,
            'avatars/orphan.jpg' => false,
        ]);
});

it('moves references to a temporary table past the memory limit', function () {
    config()->set('orphan-storage.diff.memory_limit', 2);
    config()->set('orphan-storage.chunk_size', 1);
    Storage::fake('public');
    storeFile('public', 'avatars/c.jpg');
    storeFile('public', 'avatars/orphan.jpg');
    DB::table('users')->insert([['avatar' => 'avatars/a.jpg'], ['avatar' => 'avatars/b.jpg'], ['avatar' => 'avatars/c.jpg']]);
    $detector = app(OrphanDetector::class);

    $count = $detector->index([new Source('users', 'avatar')], ['public']);

    expect($detector->usesDatabase())->toBeTrue()
        ->and($count)->toBe(3)
        ->and(classifiedPaths($detector, 'public'))->toBe([
            'avatars/c.jpg' => true,
            'avatars/orphan.jpg' => false,
        ]);
});

it('counts soft-deleted rows as references', function () {
    Storage::fake('public');
    storeFile('public', 'documents/contract.pdf');
    DB::table('documents')->insert(['file' => 'documents/contract.pdf', 'deleted_at' => now()]);
    $detector = app(OrphanDetector::class);

    $detector->index([new Source('documents', 'file', model: Document::class)], ['public']);

    expect(classifiedPaths($detector, 'public'))->toBe(['documents/contract.pdf' => true]);
});
