<?php

namespace Erag\OrphanFiles\Support;

use Erag\OrphanFiles\Scanner\StorageScanner;
use Erag\OrphanFiles\Support\Data\TrashedFile;
use Generator;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\Date;

/**
 * The trash layout: "{directory}/{source disk}/{bucket}/{original path}".
 *
 * A bucket is named after the day it was created ("2026-11-15"). When a
 * file is trashed again on a day whose bucket already holds that path, a
 * second bucket ("2026-11-15_101500") is used, so trashed copies are never
 * overwritten. The bucket date drives retention (moving a local file keeps
 * its old modification time), so no database is needed.
 */
class Trash
{
    protected const BUCKET_PATTERN = '/^(\d{4})-(\d{2})-(\d{2})(?:_\d{6}(?:_\d+)?)?$/';

    public function __construct(
        protected Repository $config,
        protected FilesystemFactory $filesystems,
        protected StorageScanner $scanner,
    ) {}

    /**
     * The disk that holds trashed files from the given disk.
     */
    public function diskFor(string $sourceDisk): string
    {
        $disk = $this->config->get('orphan-files.trash.disk');

        return is_string($disk) && $disk !== '' ? $disk : $sourceDisk;
    }

    public function directory(): string
    {
        $directory = trim((string) $this->config->get('orphan-files.trash.directory', '.orphan-trash'), '/');

        return $directory === '' ? '.orphan-trash' : $directory;
    }

    /**
     * The folder holding every trashed file of a disk.
     */
    public function root(string $sourceDisk): string
    {
        return $this->directory().'/'.$sourceDisk;
    }

    public function path(string $sourceDisk, string $bucket, string $path): string
    {
        return $this->root($sourceDisk).'/'.$bucket.'/'.ltrim($path, '/');
    }

    /**
     * The prefix scans must skip on a disk, or null when the disk holds no trash.
     */
    public function excludedPrefixOn(string $disk): ?string
    {
        $trashDisk = $this->config->get('orphan-files.trash.disk');

        if (is_string($trashDisk) && $trashDisk !== '' && $trashDisk !== $disk) {
            return null;
        }

        return $this->directory().'/';
    }

    public function isBucket(string $name): bool
    {
        return preg_match(self::BUCKET_PATTERN, $name, $matches) === 1
            && checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    /**
     * The day a bucket was created ("Y-m-d").
     */
    public function bucketDate(string $bucket): string
    {
        return substr($bucket, 0, 10);
    }

    public function retentionDays(): int
    {
        return max(0, (int) $this->config->get('orphan-files.trash.retention_days', 30));
    }

    /**
     * The bucket a new trash run should use: today's, and whether it already existed.
     *
     * @return array{name: string, existed: bool}
     */
    public function todaysBucket(string $sourceDisk): array
    {
        $name = Date::now()->format('Y-m-d');

        return [
            'name' => $name,
            'existed' => $this->filesystems->disk($this->diskFor($sourceDisk))->exists($this->root($sourceDisk).'/'.$name),
        ];
    }

    /**
     * A bucket for today that does not exist yet, e.g. "2026-11-15_101500" or "2026-11-15_101500_2".
     */
    public function newBucket(string $sourceDisk): string
    {
        $filesystem = $this->filesystems->disk($this->diskFor($sourceDisk));
        $base = Date::now()->format('Y-m-d_His');
        $name = $base;

        for ($attempt = 2; $filesystem->exists($this->root($sourceDisk).'/'.$name); $attempt++) {
            $name = $base.'_'.$attempt;
        }

        return $name;
    }

    /**
     * Whether a trashed copy already exists at the given trash path.
     */
    public function holds(string $sourceDisk, string $trashPath): bool
    {
        return $this->scanner->fileExists($this->filesystems->disk($this->diskFor($sourceDisk)), $trashPath);
    }

    /**
     * Source disks that may have trashed files.
     *
     * @return list<string>
     */
    public function sourceDisks(): array
    {
        $disks = [];

        foreach ((array) $this->config->get('orphan-files.disks', []) as $disk => $directories) {
            $disks[] = is_int($disk) && is_string($directories) ? $directories : (string) $disk;
        }

        $trashDisk = $this->config->get('orphan-files.trash.disk');

        if (is_string($trashDisk) && $trashDisk !== '') {
            foreach ($this->filesystems->disk($trashDisk)->directories($this->directory()) as $directory) {
                $disks[] = basename($directory);
            }
        }

        return array_values(array_unique(array_map('strval', $disks)));
    }

    /**
     * Trash buckets of a disk, newest first.
     *
     * @param  string|null  $date  Only buckets created on this day ("Y-m-d").
     * @return list<string>
     */
    public function buckets(string $sourceDisk, ?string $date = null): array
    {
        $buckets = array_filter(
            array_map('basename', $this->filesystems->disk($this->diskFor($sourceDisk))->directories($this->root($sourceDisk))),
            fn (string $bucket): bool => $this->isBucket($bucket) && ($date === null || $this->bucketDate($bucket) === $date),
        );

        rsort($buckets);

        return $buckets;
    }

    /**
     * Find the newest trashed copy of a file.
     */
    public function find(string $sourceDisk, string $path, ?string $date = null): ?TrashedFile
    {
        $trashDisk = $this->diskFor($sourceDisk);
        $filesystem = $this->filesystems->disk($trashDisk);

        foreach ($this->buckets($sourceDisk, $date) as $bucket) {
            $trashPath = $this->path($sourceDisk, $bucket, $path);

            if ($this->scanner->fileExists($filesystem, $trashPath)) {
                return new TrashedFile($sourceDisk, $bucket, $path, $trashDisk, $trashPath, $filesystem->size($trashPath));
            }
        }

        return null;
    }

    /**
     * Trashed files of a disk, newest bucket first.
     *
     * @param  string|null  $date  Only buckets created on this day ("Y-m-d").
     * @param  string  $prefix  Only files below this directory.
     * @return Generator<int, TrashedFile>
     */
    public function entries(string $sourceDisk, ?string $date = null, string $prefix = ''): Generator
    {
        foreach ($this->buckets($sourceDisk, $date) as $bucket) {
            yield from $this->bucketEntries($sourceDisk, $bucket, $prefix);
        }
    }

    /**
     * Trashed files in one bucket.
     *
     * @return Generator<int, TrashedFile>
     */
    public function bucketEntries(string $sourceDisk, string $bucket, string $prefix = ''): Generator
    {
        $trashDisk = $this->diskFor($sourceDisk);
        $base = $this->root($sourceDisk).'/'.$bucket.'/';

        foreach ($this->scanner->list($trashDisk, $base.trim($prefix, '/')) as $file) {
            yield new TrashedFile(
                $sourceDisk,
                $bucket,
                substr($file->path, strlen($base)),
                $trashDisk,
                $file->path,
                $file->size,
            );
        }
    }
}
