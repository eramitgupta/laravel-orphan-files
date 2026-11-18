## Orphan Storage (erag/laravel-orphan-storage)

This package finds files on Laravel disks (local, public, S3, MinIO) that no database row references. A clean moves them to a trash folder by default, where they can be restored. It only has Artisan commands, and no tables or migrations of its own.

### Installing

- Install with `composer require erag/laravel-orphan-storage`, then run `php artisan orphan:install` to create `config/orphan-storage.php`.
- `orphan:install` asks before overwriting an existing config. Only pass `--force` when the user wants their config replaced.

### Registering sources

- In `config/orphan-storage.php`, list the disks and directories to scan under `disks`. Only these locations are ever scanned or cleaned.
- List every column that stores a file reference under `sources`, with a `type`:
  - `path`: a disk path.
  - `json`: use `key` (e.g. `*.path`) to pick values.
  - `html`: rich text.
  - `url`: a full URL, CDN or signed URL.
- Or declare the columns on the model with the `HasStorageFiles` trait. Models in `app/Models` are discovered automatically.
- A missing source makes its files look orphaned. Check every column that stores uploads, including rich text and JSON.

@verbatim
<code-snippet name="Register disks and sources in config/orphan-storage.php" lang="php">
'disks' => [
    'public' => ['avatars/', 'posts/'],
],

'sources' => [
    ['table' => 'users', 'column' => 'avatar', 'type' => 'path', 'disk' => 'public'],
    ['model' => App\Models\Comment::class, 'column' => 'images', 'type' => 'json', 'key' => '*.path'],
    ['table' => 'posts', 'column' => 'body', 'type' => 'html'],
    ['table' => 'products', 'column' => 'image_url', 'type' => 'url'],
],
</code-snippet>

<code-snippet name="Declare storage columns on a model" lang="php">
use Erag\OrphanStorage\Concerns\HasStorageFiles;

class Post extends Model
{
    use HasStorageFiles;

    protected array $storageFiles = [
        'cover' => 'path',
        'gallery' => ['type' => 'json', 'key' => '*.path'],
        'body' => 'html',
    ];

    protected ?string $storageDisk = 'public';
}
</code-snippet>
@endverbatim

### Commands

- `php artisan orphan:scan`: reports files, references, orphans and reclaimable size (`--json`, `--export=csv`).
- `php artisan orphan:clean`: a dry run by default. It changes nothing until `--force` is added.
- `php artisan orphan:restore {path}`: moves trashed files back (`--list` shows the trash).
- `php artisan orphan:purge-trash`: permanently deletes trash older than `trash.retention_days`.
- `php artisan orphan:explain {path}`: shows which rows reference a file, or why it counts as an orphan.

### Safety rules for agents

- Always run `orphan:scan`, or `orphan:clean` without `--force`, first. Show the result to the user before anything is changed.
- Never run `orphan:clean --force` without the user's approval.
- Never use `--mode=delete`, `--force-ratio` or `orphan:purge-trash` without the user's explicit approval. They delete files permanently or bypass a safety check.
- Never lower `grace_period_hours` or pass `--older-than` without asking the user first.
- When a file the user needs is reported as an orphan, run `orphan:explain` and add the missing source. Do not exclude files one by one.
