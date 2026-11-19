# Changelog

Notable changes to `erag/laravel-orphan-files`, newest version first.

## v0.0.2 - Unreleased

### Changed

- Files matching `exclude_paths` or `exclude_patterns` are skipped before anything is counted. They no longer show up as files, orphans or "Protected" in a scan, and they don't count toward the orphan ratio. `orphan:explain` says "Excluded" for them.
- `orphan:restore` without `--disk` now works on every configured disk, the same way `orphan:purge-trash` already did. `--list` adds a Disk column when it shows more than one disk.
- `orphan:explain` asks for the path when you run it without one. With `--no-interaction` it still stops with a "missing path" error.
- Custom extractors have to return strings or `Reference` objects, as `ReferenceExtractor::extract()` documents. Any other value now throws a `TypeError` instead of being skipped without a word.

### Fixed

- `orphan:restore --all` and `--list` without `--disk` only looked at the first configured disk, so trash on any other disk was left behind without a word.
- `orphan:restore {path}` without `--disk` looked only in the first disk's trash. It now finds the path in the trash of any configured disk, and a path that is in no trash is reported once: "Not found in the trash of s3 or public."
- A refused restore (a file already at the original path) ended with "INFO Restored 0 files", which read like success. It now ends with a warning such as "1 file could not be restored, see above." The exit code stays 1.
- `orphan:purge-trash --days=0` kept today's trash. A retention of 0 days now keeps nothing.
- Messages said "1 files", "1 row(s)" and "none of the 1 sources". They now use the right singular and plural, for example "Restored 1 file" and "the only source does not reference it".
- The safety limit message printed ratios as "100.0%" while the scan table printed "100%". Both now use the same format.

### Development

- Static analysis with PHPStan (Larastan) at level 8: `composer test:types`, also part of `composer test` and the CI lint job.
- `league/flysystem-aws-s3-v3` is a dev dependency, so the S3 batch delete is type-checked. For your app it stays a suggestion.

## v0.0.1 - 2026-11-18

First release. Needs Laravel 12 or 13 and PHP 8.2 or newer (8.3 on Laravel 13). The only runtime dependencies are the `illuminate/*` components.

### Added

- `orphan:install` creates `config/orphan-files.php`, asks before overwriting an existing one (`--force` skips the question) and prints the next steps.
- `orphan:scan` reports how many files are referenced, how many are orphans and how much space they use. `--json` and `--export=csv` give you the data.
- `orphan:clean` trashes or deletes orphans. It's a dry run until you pass `--force`. Also takes `--mode`, `--older-than`, `--limit` and `--force-ratio`.
- `orphan:restore` moves trashed files back to where they were.
- `orphan:purge-trash` deletes trash older than `trash.retention_days`.
- `orphan:explain {path}` lists the rows that reference a file, or says why it counts as an orphan.
- Source types `path`, `json`, `html` and `url`. Custom types implement `ReferenceExtractor` and can return file, directory or whole-row references.
- `Storage::url()` links, CDN prefixes, S3 endpoints (including MinIO and RustFS) and signed URLs are mapped back to disk paths.
- The `HasStorageFiles` trait declares file columns on a model, and those models are discovered automatically. It doesn't listen to model events.
- Storage listings are streamed and tables are read in chunks from the primary connection. Past 250,000 references the index moves to a temporary table, which works on MySQL, MariaDB, PostgreSQL, SQLite and SQL Server. If temporary tables aren't allowed, `auto` mode stays in memory.
- Dry run by default, trash mode, a 24 hour grace period counted from when the database was read, exclusions, and a 90% orphan ratio limit.
- A Laravel Boost guideline and an `orphan-files-setup` skill. `laravel/boost` isn't required.
- Events `OrphanScanCompleted`, `OrphanFilesTrashed` and `OrphanFilesDeleted`, and the queued jobs `RunScanJob` and `CleanOrphansJob`.

### Changed before the first release

- The package was renamed from `erag/laravel-orphan-storage` to `erag/laravel-orphan-files`. The namespace is `Erag\OrphanFiles`; the manager, facade and service provider are named `OrphanFiles`; the config file and publish tag use `orphan-files`; and the commands use the shorter `orphan:` prefix (`orphan:scan`, `orphan:clean` and so on). The batch events became `OrphanFilesTrashed` and `OrphanFilesDeleted`.
- Dropped Laravel 10 and 11.
- The service provider extends `Illuminate\Support\ServiceProvider` directly instead of using the package-tools library.
- Removed the media library source type.
