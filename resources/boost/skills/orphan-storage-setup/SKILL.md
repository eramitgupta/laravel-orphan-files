---
name: orphan-storage-setup
description: "Set up erag/laravel-orphan-storage in a Laravel app. Use when installing the package, publishing config/orphan-storage.php, registering the disks to scan and every column that stores file references (path, json, html or url columns, or the HasStorageFiles trait), running the first orphan-storage scan, checking results with orphan:explain, or scheduling scans and cleanups safely."
---

# Orphan Storage Setup

Use this skill when a task involves adding `erag/laravel-orphan-storage` to a Laravel application, or fixing its configuration.

## Safety rules

- Never run `orphan:clean --force` without the user's approval. Always show a scan or a dry run first.
- Never use `--mode=delete`, `--force-ratio` or `orphan:purge-trash` without the user's explicit approval.
- Never lower `grace_period_hours` or pass `--older-than` without asking the user.

## Steps

### 1. Install and publish the config

```bash
composer require erag/laravel-orphan-storage
php artisan vendor:publish --tag=orphan-storage-config
```

S3, MinIO and other S3-compatible disks also need `league/flysystem-aws-s3-v3`.

### 2. List where uploads are stored

- Read `config/filesystems.php`.
- Search the code for `->store(`, `->storeAs(`, `Storage::put` and `Storage::disk(` to find each disk and directory that receives uploads.
- Add them under `disks`, using the narrowest directories that hold uploads:

```php
'disks' => [
    'public' => ['avatars/', 'posts/'],
    's3' => ['uploads/'],
],
```

Never list a disk root that holds files the app did not upload, such as logs, exports or cached files.

### 3. Register every column that references a file

Inspect the migrations, models and database schema. Find every column that stores a path, a URL, JSON containing paths, or rich text with images or links.

| Stored value | Source |
| --- | --- |
| `avatars/jane.jpg` | `['table' => 'users', 'column' => 'avatar', 'type' => 'path']` |
| `[{"path": "comments/1.jpg"}]` | `['table' => 'comments', 'column' => 'images', 'type' => 'json', 'key' => '*.path']` |
| `<img src="/storage/posts/a.png">` | `['table' => 'posts', 'column' => 'body', 'type' => 'html']` |
| `https://cdn.example.com/p/a.jpg` | `['table' => 'products', 'column' => 'image_url', 'type' => 'url']` |

- Add `'disk' => 's3'` when a column only refers to one disk.
- Add CDN or custom domains under `url_prefixes`, e.g. `'s3' => ['https://cdn.example.com/']`.
- Every source needs a unique `primary_key` (default `id`).
- A missing source makes its files look orphaned, so be thorough.

You can also declare the columns on the model instead. Models in `app/Models` are discovered automatically:

```php
use Erag\OrphanStorage\Concerns\HasStorageFiles;

class User extends Authenticatable
{
    use HasStorageFiles;

    protected array $storageFiles = ['avatar' => 'path'];

    protected ?string $storageDisk = 'public';
}
```

### 4. Exclude files that must always stay

Add placeholders and fixed assets to `exclude_paths` or `exclude_patterns`, e.g. `'defaults/*'`.

### 5. Run the first scan and verify it

```bash
php artisan orphan:scan
php artisan orphan:scan --export=csv
```

- Pick a few reported orphans and a few files the app uses, and check them:

```bash
php artisan orphan:explain avatars/jane.jpg
```

- If a file in use is reported as an orphan, add the missing source and scan again.
- A high orphan ratio almost always means a missing source, not real orphans.
- Show the summary to the user.

### 6. Preview the clean, then ask

```bash
php artisan orphan:clean
```

This is a dry run. Only after the user approves, run it with `--force`. That moves the files to the trash, from where they can be restored:

```bash
php artisan orphan:clean --force
php artisan orphan:restore avatars/jane.jpg
```

### 7. Schedule it (with the user's agreement)

In `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('orphan:scan --json')->weekly();
Schedule::command('orphan:purge-trash')->daily();
```

Add `Schedule::command('orphan:clean --force')->weekly();` only when the user asks for automatic cleanup.
