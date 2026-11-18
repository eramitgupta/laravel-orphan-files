<?php

namespace Erag\OrphanFiles\Actions;

use Erag\OrphanFiles\Support\Data\ActionResult;
use Erag\OrphanFiles\Support\Data\TrashedFile;
use Erag\OrphanFiles\Support\FileMover;
use Erag\OrphanFiles\Support\Trash;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Throwable;

/**
 * Moves trashed files back to their original paths. When a path was
 * trashed more than once, only the newest copy is considered.
 */
class RestoreFiles
{
    public function __construct(
        protected Trash $trash,
        protected FileMover $mover,
        protected FilesystemFactory $filesystems,
    ) {}

    /**
     * Restore files, or every file below a directory.
     *
     * @param  list<string>  $paths
     * @param  string|null  $date  Only restore from buckets created on this day ("Y-m-d").
     */
    public function handle(string $disk, array $paths, ?string $date = null, bool $overwrite = false): ActionResult
    {
        $result = new ActionResult('restore', keepPaths: true);
        $handled = [];

        foreach ($paths as $path) {
            $path = trim(str_replace('\\', '/', $path), '/');
            $file = $path === '' ? null : $this->trash->find($disk, $path, $date);

            if ($file !== null) {
                $this->restore($file, $overwrite, $result, $handled);

                continue;
            }

            $found = false;

            foreach ($this->trash->entries($disk, $date, $path) as $entry) {
                $found = true;

                $this->restore($entry, $overwrite, $result, $handled);
            }

            if (! $found) {
                $result->failed($disk, $path, 'Not found in the trash.');
            }
        }

        return $result;
    }

    /**
     * Restore everything trashed from a disk (optionally from one day).
     */
    public function all(string $disk, ?string $date = null, bool $overwrite = false): ActionResult
    {
        $result = new ActionResult('restore', keepPaths: true);
        $handled = [];

        foreach ($this->trash->entries($disk, $date) as $entry) {
            $this->restore($entry, $overwrite, $result, $handled);
        }

        return $result;
    }

    /**
     * Entries arrive newest bucket first. A path is attempted once: if its
     * newest copy fails, older copies are not restored in its place.
     *
     * @param  array<string, true>  $handled  Binary hashes of paths already attempted.
     */
    protected function restore(TrashedFile $file, bool $overwrite, ActionResult $result, array &$handled): void
    {
        $key = hash('xxh128', $file->path, true);

        if (isset($handled[$key])) {
            return;
        }

        $handled[$key] = true;

        if (! $overwrite && $this->filesystems->disk($file->disk)->exists($file->path)) {
            $result->failed($file->disk, $file->path, 'A file already exists at the original path. Use --overwrite to replace it.');

            return;
        }

        try {
            $this->mover->move($file->trashDisk, $file->trashPath, $file->disk, $file->path);
        } catch (Throwable $exception) {
            $result->failed($file->disk, $file->path, $exception->getMessage());

            return;
        }

        $result->succeeded($file->disk, $file->path, $file->size);
    }
}
