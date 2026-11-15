<div align="center">

# Laravel Orphan Storage

Find and safely delete unused files in Laravel storage: old uploads that no database row points to any more.

[![Packagist License](https://img.shields.io/badge/License-MIT-blue)](https://github.com/eramitgupta/laravel-orphan-storage/blob/main/LICENSE.md)
[![Latest Stable Version](https://img.shields.io/packagist/v/erag/laravel-orphan-storage?label=Stable)](https://packagist.org/packages/erag/laravel-orphan-storage)
[![Total Downloads](https://img.shields.io/packagist/dt/erag/laravel-orphan-storage.svg?label=Downloads)](https://packagist.org/packages/erag/laravel-orphan-storage)

[Documentation](https://erag.in/laravel-orphan-storage/) · [Packagist](https://packagist.org/packages/erag/laravel-orphan-storage)

</div>

<p align="center">
  <img src="https://raw.githubusercontent.com/eramitgupta/laravel-orphan-storage/main/art/demo.gif" alt="Terminal demo of Laravel Orphan Storage: php artisan orphan:scan finds 20 unused images (6.8 MB) out of 43 files, orphan:clean lists them in a dry run, orphan:clean --force moves them to the trash, and a second scan shows 0 orphans" width="900">
</p>

## What is Laravel Orphan Storage?

Every Laravel app that accepts uploads collects dead files. A user changes their avatar, an admin deletes a product, a comment with images is removed, and the old files stay on the disk. On S3 or MinIO you keep paying for them, and on a local disk they slowly fill the server.

Laravel Orphan Storage finds those files for you. It lists the files on your disks, reads every database column that stores a file (a path, a JSON list, rich text or a full URL) and reports the files that nothing references any more. These are your orphaned files.

Then it cleans them up carefully. A clean is a dry run until you add `--force`, cleaned files go to a trash folder you can restore from, recent uploads are skipped, and the clean stops if it would remove a suspicious share of a disk.

It's a set of Artisan commands for Laravel 12 and Laravel 13, with no migrations, no tables and no UI. It works on `local` and `public` disks, Amazon S3, MinIO and other S3-compatible storage.

In the demo above, 20 of 43 images turn out to be unused, and cleaning them frees 6.8 MB.

## Installation

Install the package with Composer, then publish the config file:

```bash
composer require erag/laravel-orphan-storage
php artisan vendor:publish --tag=orphan-storage-config
```

Laravel registers the package automatically. The second command creates `config/orphan-storage.php`, where you list your disks and the columns that point to files, as shown in the [quick start](#quick-start) below.

## Features

- Works on any Laravel disk: `local`, `public`, Amazon S3, MinIO and other S3-compatible storage.
- Streams storage listings, so a bucket with millions of objects doesn't have to fit in memory.
- Reads file references from path columns, JSON columns, rich text (HTML or Markdown) and full URLs.
- Maps `Storage::url()` links, CDN URLs, S3 and MinIO endpoints and signed URLs back to disk paths.
- Lets you list columns in the config file, or declare them on a model with the `HasStorageFiles` trait.
- Keeps soft-deleted rows' files, because global scopes are ignored.
- Dry run by default: nothing changes until you add `--force`.
- Moves unused files to a trash folder with a 30 day retention, or deletes them (1,000 per request on S3).
- Restores trashed files to their original paths with one command.
- Skips recent uploads, excluded paths and anything outside your size or extension rules.
- Exports every orphan to CSV or prints the summary as JSON for monitoring.
- `orphan:explain` tells you why a single file is kept, or which column is missing.
- No migrations, no tables and no UI: just Artisan commands, queued jobs and events.

[See all features in the docs](https://erag.in/laravel-orphan-storage/introduction.html)

## How it works

1. It lists the files in the disks and folders you configure, for example `avatars/` on the public disk.
2. It reads every column that can point to a file and compares those paths with the files on disk. Anything nobody references is an orphan.
3. It moves the orphans to a trash folder that keeps their original paths, so restoring a file is a single command. You can purge the trash later, or delete files directly if you prefer.

[Read the full walkthrough](https://erag.in/laravel-orphan-storage/introduction.html)

## What it can read

- A path column holds one file, like `users.avatar` storing `avatars/jane.jpg`.
- A JSON column holds several files, like a comment's image gallery. You tell it which key holds the path.
- An HTML column is rich text, like a blog post body. Images and links are read from `src`, `href`, `srcset`, CSS `url()` and Markdown.
- A URL column stores a full link, like a product image served from a CDN or a signed S3 URL. The URL is turned back into the file's path on the disk.

You can add your own column types for editors like Editor.js. [See sources in the docs](https://erag.in/laravel-orphan-storage/configuration/sources.html)

## Requirements

| Laravel | PHP |
| --- | --- |
| 12.x | 8.2, 8.3, 8.4 |
| 13.x | 8.3, 8.4 |

## Quick start

After installing, open `config/orphan-storage.php` and tell it where files live and which columns point to them:

```php
'disks' => [
    'public' => ['avatars/'],
],

'sources' => [
    ['table' => 'users', 'column' => 'avatar', 'type' => 'path', 'disk' => 'public'],
],
```

Then scan, preview and clean:

```bash
php artisan orphan:scan            # how many orphans, and how much space they use
php artisan orphan:clean           # dry run: lists what would be trashed
php artisan orphan:clean --force   # moves the orphans to the trash
```

[Installation guide](https://erag.in/laravel-orphan-storage/installation.html) · [Quick start](https://erag.in/laravel-orphan-storage/quick-start.html)

## Commands

| Command | What it does |
| --- | --- |
| `orphan:scan` | Counts files, referenced files and orphans, and shows how much space you'd get back. Can export CSV or print JSON. |
| `orphan:clean` | Shows what would be cleaned. With `--force` it moves orphans to the trash, or deletes them with `--mode=delete`. |
| `orphan:restore` | Moves trashed files back to where they were, one file, one folder or everything. |
| `orphan:purge-trash` | Permanently deletes trash older than the retention period (30 days by default). |
| `orphan:explain` | Shows which rows reference a file, or why it counts as an orphan. |

[All command options and sample output](https://erag.in/laravel-orphan-storage/commands/scan.html)

## Delete unused images in Laravel safely

Deleting files is easy to get wrong, so the package is careful by default:

- `orphan:clean` is a dry run until you add `--force`.
- Cleaned files go to a trash folder first. Permanent deletion is opt-in with `--mode=delete` or `orphan:purge-trash`.
- Files modified in the 24 hours before the database was read are skipped, so uploads whose row isn't saved yet stay put.
- If more than 90% of a disk would be cleaned, the clean stops. That ratio usually means a column is missing from the config.
- If a configured table or column doesn't exist, the command stops before it reads or touches anything.
- Only the folders you list are ever scanned.

[Running it safely](https://erag.in/laravel-orphan-storage/guides/safety.html)

## Clean up orphaned files on S3 and MinIO

S3 and MinIO disks work the same way as local ones. Listings are paginated, so large buckets are fine, and deletes go out in batches of 1,000 keys. URLs stored in your database, including CDN domains and signed URLs, are matched back to the right object. [URLs and CDNs in the docs](https://erag.in/laravel-orphan-storage/configuration/urls.html)

## FAQ

**How do I find unused files in Laravel storage?**
List your upload folders and the columns that store paths in `config/orphan-storage.php`, then run `php artisan orphan:scan`.

**Will it delete files that are still in use?**
Not unless a column that references them is missing from your config. Run a scan first and check a few files with `orphan:explain`. Cleaned files go to a restorable trash by default.

**Can I undo a clean?**
Yes. `php artisan orphan:restore path/to/file` moves a trashed file back, and `--all` restores everything from a disk.

**Does it work with Livewire or Inertia file uploads?**
Yes. It doesn't care how a file was uploaded. It only compares what's on the disk with the paths your database stores, and if you store them in an unusual format you can add a [custom extractor](https://erag.in/laravel-orphan-storage/extending/extractors.html).

**Does it slow down my app?**
No. It only runs when you call a command or schedule one. It doesn't listen to model events and adds nothing to your requests.

**Can I run it on a schedule?**
Yes. Most people schedule a weekly scan first, then a weekly clean once the reports look right. [Scheduling](https://erag.in/laravel-orphan-storage/guides/scheduling.html)

[More questions](https://erag.in/laravel-orphan-storage/faq.html)

## Documentation

Full documentation, configuration options and examples: [Read the docs](https://erag.in/laravel-orphan-storage/)

- [Installation](https://erag.in/laravel-orphan-storage/installation.html)
- [Configuration](https://erag.in/laravel-orphan-storage/configuration/disks.html)
- [Commands](https://erag.in/laravel-orphan-storage/commands/scan.html)
- [Running it safely](https://erag.in/laravel-orphan-storage/guides/safety.html)
- [Using it from code](https://erag.in/laravel-orphan-storage/guides/code.html)
- [FAQ](https://erag.in/laravel-orphan-storage/faq.html)

## Changelog and contributing

See the [changelog](CHANGELOG.md) for what changed. To help out, read [CONTRIBUTING.md](CONTRIBUTING.md) and use the [issues](https://github.com/eramitgupta/laravel-orphan-storage/issues), [pull requests](https://github.com/eramitgupta/laravel-orphan-storage/pulls) and [discussions](https://github.com/eramitgupta/laravel-orphan-storage/discussions) on GitHub.

## Credits

Built and maintained by [Er Amit Gupta](https://github.com/eramitgupta).

## License

MIT. See [LICENSE.md](LICENSE.md).
