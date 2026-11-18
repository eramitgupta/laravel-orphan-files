# Changelog

Notable changes to `erag/laravel-orphan-storage`.

## v1.0.0 - Unreleased

First release. Needs Laravel 12 or 13 and PHP 8.2 or newer (8.3 on Laravel 13). The only runtime dependencies are the `illuminate/*` components.

### Added

- `orphan:install` creates `config/orphan-storage.php`, asks before overwriting an existing one (`--force` skips the question) and prints the next steps.
- `orphan:scan` reports how many files are referenced, how many are orphans and how much space they use. `--json` and `--export=csv` give you the data.
- `orphan:clean` trashes or deletes orphans. It's a dry run until you pass `--force`. Also takes `--mode`, `--older-than`, `--limit` and `--force-ratio`.
- `orphan:restore` moves trashed files back to where they were.
- `orphan:purge-trash` deletes trash older than `trash.retention_days`.
- `orphan:explain {path}` lists the rows that reference a file, or says why it counts as an orphan.
- Source types `path`, `json`, `html` and `url`. Custom types implement `ReferenceExtractor` and can return file, directory or whole-row references.
- `Storage::url()` links, CDN prefixes, S3 and MinIO endpoints and signed URLs are mapped back to disk paths.
- The `HasStorageFiles` trait declares file columns on a model, and those models are discovered automatically. It doesn't listen to model events.
- Storage listings are streamed and tables are read in chunks from the primary connection. Past 250,000 references the index moves to a temporary table, which works on MySQL, MariaDB, PostgreSQL, SQLite and SQL Server. If temporary tables aren't allowed, `auto` mode stays in memory.
- Dry run by default, trash mode, a 24 hour grace period counted from when the database was read, exclusions, and a 90% orphan ratio limit.
- A Laravel Boost guideline and an `orphan-storage-setup` skill. `laravel/boost` isn't required.
- Events `OrphanScanCompleted`, `OrphanStorageTrashed` and `OrphanStorageDeleted`, and the queued jobs `RunScanJob` and `CleanOrphansJob`.

### Changed during development

- The namespace is `Erag\OrphanStorage`. The manager, facade and service provider are named `OrphanStorage`, the config file and publish tag use `orphan-storage`, and the commands use the shorter `orphan:` prefix (`orphan:scan`, `orphan:clean` and so on). The batch events became `OrphanStorageTrashed` and `OrphanStorageDeleted`.
- Dropped Laravel 10 and 11.
- The service provider extends `Illuminate\Support\ServiceProvider` directly instead of using the package-tools library.
- Removed the media library source type.
