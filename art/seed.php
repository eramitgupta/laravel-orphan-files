<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Demo Data
|--------------------------------------------------------------------------
|
| Resets the demo database and disk: users with avatars, comments with
| image galleries (JSON), plus 20 orphaned images left behind by replaced
| avatars and deleted comments. Every file is a few days old, so the
| 24 hour grace period does not hide them.
|
*/

$app = require __DIR__.'/bootstrap.php';

(new Filesystem)->deleteDirectory(__DIR__.'/storage');
(new Filesystem)->put(__DIR__.'/database.sqlite', '');

DB::purge('demo');

Schema::create('users', function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->string('avatar')->nullable();
});

Schema::create('comments', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id');
    $table->text('body');
    $table->json('images')->nullable();
});

mt_srand(2026);

$disk = Storage::disk('public');
$store = function (string $path) use ($disk): void {
    $disk->put($path, str_repeat('0', mt_rand(48_000, 720_000)));
    touch($disk->path($path), time() - mt_rand(3, 60) * 86400);
};

$names = ['Aarav Shah', 'Maya Patel', 'Liam Chen', 'Sofia Rossi', 'Noah Kim', 'Zara Ahmed', 'Ethan Brooks', 'Isha Verma'];

foreach ($names as $name) {
    $slug = Str::slug($name);
    $store("avatars/{$slug}.jpg");

    $userId = DB::table('users')->insertGetId(['name' => $name, 'avatar' => "avatars/{$slug}.jpg"]);

    $images = [];

    foreach (range(1, mt_rand(1, 3)) as $ignored) {
        $path = sprintf('comments/2026/11/IMG_%04d.jpg', mt_rand(1000, 9999));
        $store($path);
        $images[] = ['path' => $path, 'alt' => 'Customer photo'];
    }

    DB::table('comments')->insert(['user_id' => $userId, 'body' => 'Looks great!', 'images' => json_encode($images)]);
}

// Orphans: avatars that were replaced, and galleries of deleted comments.
foreach (array_slice($names, 0, 5) as $name) {
    $store('avatars/'.Str::slug($name).'-old.jpg');
}

foreach (range(1, 15) as $ignored) {
    $store(sprintf('comments/2026/09/IMG_%04d.jpg', mt_rand(1000, 9999)));
}
