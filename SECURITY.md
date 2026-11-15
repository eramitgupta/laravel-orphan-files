# Security Policy

## Supported versions

| Package version | Laravel | PHP | Supported |
| --- | --- | --- | --- |
| 1.x | 12.x, 13.x | 8.2+ (8.3+ on Laravel 13) | Yes |
| < 1.0 | | | No |

## What counts as a security issue

This package deletes files, so a bug that loses data counts as a security issue even if no attacker is involved. Please report these privately:

- anything that trashes or deletes a file that is still referenced, for example a path that isn't normalised the same way on both sides, a disk mix-up or a column type that's missed
- a way around the dry run, the trash, the grace period, the exclusions or the orphan ratio limit
- a way to delete files outside the configured `disks` directories, or to lose data through `restore` or `purge-trash`

Classic vulnerabilities belong here too, of course.

## Reporting a vulnerability

Don't open a public issue, discussion or pull request for a security problem. Use GitHub's [private vulnerability reporting](https://github.com/eramitgupta/laravel-orphan-storage/security/advisories/new), or email Er Amit Gupta at [info.eramitgupta@gmail.com](mailto:info.eramitgupta@gmail.com).

It helps a lot if you include:

- the package, Laravel and PHP versions
- the disk driver (local, S3, MinIO, ...) and the `disks` and `sources` parts of `config/orphan-storage.php`
- the steps to reproduce, with the output of `php artisan orphan:clean` without `--force`
- what was affected, and whether the files could be restored from the trash

## What happens next

You'll get a reply within 3 business days and an assessment within 7 days. Data-loss bugs are fixed first. If you'd like credit, you'll be named in the release notes.

Please keep the details private until a fixed version is out. Thanks for reporting it.
