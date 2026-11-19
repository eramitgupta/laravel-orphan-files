<?php

use Erag\OrphanFiles\References\Extractors\HtmlExtractor;
use Erag\OrphanFiles\References\Extractors\JsonExtractor;
use Erag\OrphanFiles\References\Extractors\PathExtractor;
use Erag\OrphanFiles\References\Extractors\UrlExtractor;

return [

    /*
    |--------------------------------------------------------------------------
    | Disks & Directories
    |--------------------------------------------------------------------------
    |
    | Which disks (from config/filesystems.php) and directories to scan.
    | Nothing outside these is ever touched. An empty array scans the whole
    | disk.
    |
    | 'public' => ['avatars/', 'comments/'],
    | 's3' => ['uploads/'],
    |
    */

    'disks' => [
        // 'public' => ['avatars/', 'comments/'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reference Sources
    |--------------------------------------------------------------------------
    |
    | Every column that can point to a stored file. Each source needs a
    | `table` or `model`, a `column` and a `type`:
    |
    | - path: the column holds a single disk-relative path.
    | - json: the column holds JSON. Use `key` (e.g. 'images.*.path') to pick
    |         values, or leave it out to collect every string in the document.
    | - html: rich text. Paths are read from src, href, srcset, poster and
    |         CSS url() values.
    | - url:  a full URL (Storage::url(), CDN or signed URL).
    |
    | With `disk`, a source only keeps files on that disk. Without it, matching
    | paths are kept on every scanned disk.
    |
    */

    'sources' => [
        // ['table' => 'users', 'column' => 'avatar', 'type' => 'path', 'disk' => 'public'],
        // ['model' => App\Models\Comment::class, 'column' => 'images', 'type' => 'json', 'key' => '*.path'],
        // ['table' => 'posts', 'column' => 'body', 'type' => 'html'],
        // ['table' => 'products', 'column' => 'image_url', 'type' => 'url', 'disk' => 's3'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Discovery
    |--------------------------------------------------------------------------
    |
    | Models in these paths that use the HasStorageFiles trait become sources
    | automatically. Models that live elsewhere can be listed in `models`.
    |
    */

    'discovery' => [
        'enabled' => true,
        'paths' => [
            app_path('Models'),
        ],
        'models' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Extractors
    |--------------------------------------------------------------------------
    |
    | Which class reads each source `type`. Add your own types here; the class
    | has to implement ReferenceExtractor.
    |
    */

    'extractors' => [
        'path' => PathExtractor::class,
        'json' => JsonExtractor::class,
        'html' => HtmlExtractor::class,
        'url' => UrlExtractor::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | URL Prefixes
    |--------------------------------------------------------------------------
    |
    | Extra base URLs, such as a CDN or custom domain, that serve a disk. You
    | don't need to list the disk's own Storage::url(), its `url` option or
    | its S3 endpoints.
    |
    | 's3' => ['https://cdn.example.com/', 'https://d111111abcdef8.cloudfront.net/'],
    |
    */

    'url_prefixes' => [],

    /*
    |--------------------------------------------------------------------------
    | Unknown URL Hosts
    |--------------------------------------------------------------------------
    |
    | When a URL doesn't start with a known base URL, keep any file whose path
    | matches the end of the URL anyway. That way a CDN you forgot to list
    | above can't get files deleted. Set to false to ignore such URLs.
    |
    */

    'match_unknown_hosts' => true,

    /*
    |--------------------------------------------------------------------------
    | Normalizers
    |--------------------------------------------------------------------------
    |
    | Classes implementing PathNormalizer, or invokables that receive
    | `string $path, string $disk`. Each can return another reading of a
    | stored value. They only ever add paths to keep, so a mistake here can't
    | cause a deletion.
    |
    */

    'normalizers' => [],

    /*
    |--------------------------------------------------------------------------
    | Case Sensitivity
    |--------------------------------------------------------------------------
    |
    | Set to false if your storage or database ignores case, e.g. macOS
    | volumes or a case-insensitive MySQL collation.
    |
    */

    'case_sensitive' => true,

    /*
    |--------------------------------------------------------------------------
    | Exclusions
    |--------------------------------------------------------------------------
    |
    | Files matching these paths or glob patterns are skipped entirely: the
    | scan doesn't count them and a clean never touches them. An entry
    | without a slash matches that file name in any directory.
    |
    */

    'exclude_paths' => [
        '.gitignore',
        '.gitkeep',
    ],

    'exclude_patterns' => [
        // 'defaults/*',
        // '*.pdf',
    ],

    /*
    |--------------------------------------------------------------------------
    | File Rules
    |--------------------------------------------------------------------------
    |
    | Only clean files within these sizes (in bytes) or with these extensions.
    |
    */

    'min_size' => null,

    'max_size' => null,

    'only_extensions' => [],

    'except_extensions' => [],

    /*
    |--------------------------------------------------------------------------
    | Custom Filters
    |--------------------------------------------------------------------------
    |
    | Classes implementing FileFilter. A filter returns a reason to keep an
    | unreferenced file, or null if it may be cleaned.
    |
    */

    'filters' => [],

    /*
    |--------------------------------------------------------------------------
    | Grace Period
    |--------------------------------------------------------------------------
    |
    | Files modified less than this many hours before the database was read
    | are never cleaned. It covers uploads whose row isn't saved yet.
    |
    */

    'grace_period_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | Cleanup Mode
    |--------------------------------------------------------------------------
    |
    | "trash" moves orphans to the trash folder, where you can restore them.
    | "delete" removes them for good.
    |
    */

    'mode' => 'trash',

    'trash' => [
        'disk' => null,
        'directory' => '.orphan-trash',
        'retention_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety Threshold
    |--------------------------------------------------------------------------
    |
    | A clean stops if more than this percentage of a disk's files would go.
    | A ratio that high usually means a source is missing from this file.
    | Use --force-ratio to skip the check once.
    |
    */

    'max_orphan_ratio' => 90,

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    |
    | `chunk_size` sets the size of database chunks, storage batches and
    | delete batches. `driver` is "auto", "memory" or "database". In "auto"
    | mode references stay in memory up to `memory_limit`, then move to a
    | temporary table on `connection` (null means the default connection).
    | If that table can't be created, "auto" stays in memory and logs a
    | warning, and "database" stops with an error.
    |
    */

    'chunk_size' => 1000,

    'diff' => [
        'driver' => 'auto',
        'memory_limit' => 250000,
        'connection' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Where jobs go when a command runs with --queue.
    |
    */

    'queue' => [
        'connection' => null,
        'name' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Directory
    |--------------------------------------------------------------------------
    |
    | Where `orphan:scan --export=csv` writes its file by default.
    |
    */

    'export_path' => storage_path('orphan-files'),

];
