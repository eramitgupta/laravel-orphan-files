<?php

namespace Erag\OrphanFiles\Scanner;

use Generator;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\FileAttributes;
use Throwable;

/**
 * Streams the files of a disk.
 *
 * Flysystem listings are lazy and paginated (S3 returns 1,000 keys per
 * request, with size and modification time), so even buckets with
 * millions of objects are walked without loading them into memory.
 */
class StorageScanner
{
    public function __construct(protected FilesystemFactory $filesystems) {}

    /**
     * Every file below the directories.
     *
     * @param  list<string>  $directories  Use [''] for the whole disk.
     * @param  string|null  $skip  A path prefix to leave out, such as the trash folder.
     * @return Generator<int, FileInfo>
     */
    public function files(string $disk, array $directories = [''], ?string $skip = null): Generator
    {
        foreach ($directories as $directory) {
            foreach ($this->list($disk, $directory) as $file) {
                if ($skip !== null && str_starts_with($file->path, $skip)) {
                    continue;
                }

                yield $file;
            }
        }
    }

    /**
     * Every file below a directory.
     *
     * @return Generator<int, FileInfo>
     */
    public function list(string $disk, string $directory = ''): Generator
    {
        $filesystem = $this->filesystems->disk($disk);
        $directory = trim($directory, '/');

        if ($filesystem instanceof FilesystemAdapter) {
            foreach ($filesystem->getDriver()->listContents($directory, true) as $item) {
                if (! $item instanceof FileAttributes) {
                    continue;
                }

                yield new FileInfo(
                    $disk,
                    $item->path(),
                    $item->fileSize() ?? $this->attempt(fn (): int => $filesystem->size($item->path())),
                    $item->lastModified() ?? $this->attempt(fn (): int => $filesystem->lastModified($item->path())),
                );
            }

            return;
        }

        foreach ($filesystem->allFiles($directory) as $path) {
            yield $this->describe($filesystem, $disk, $path);
        }
    }

    /**
     * Describe a single file, or return null when it does not exist.
     */
    public function file(string $disk, string $path): ?FileInfo
    {
        $filesystem = $this->filesystems->disk($disk);

        if (! $this->fileExists($filesystem, $path)) {
            return null;
        }

        return $this->describe($filesystem, $disk, $path);
    }

    /**
     * Whether a file (not a directory) exists at the path.
     */
    public function fileExists(Filesystem $filesystem, string $path): bool
    {
        return $filesystem instanceof FilesystemAdapter
            ? $filesystem->fileExists($path)
            : $filesystem->exists($path);
    }

    protected function describe(Filesystem $filesystem, string $disk, string $path): FileInfo
    {
        return new FileInfo(
            $disk,
            $path,
            $this->attempt(fn (): int => $filesystem->size($path)),
            $this->attempt(fn (): int => $filesystem->lastModified($path)),
        );
    }

    /**
     * @param  callable(): int  $callback
     */
    protected function attempt(callable $callback): ?int
    {
        try {
            return $callback();
        } catch (Throwable) {
            return null;
        }
    }
}
