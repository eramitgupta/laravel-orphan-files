<?php

namespace Erag\OrphanFiles\Actions;

use Erag\OrphanFiles\Events\OrphanFilesTrashed;
use Erag\OrphanFiles\Scanner\FileInfo;
use Erag\OrphanFiles\Support\Chunks;
use Erag\OrphanFiles\Support\Data\ActionResult;
use Erag\OrphanFiles\Support\FileMover;
use Erag\OrphanFiles\Support\Trash;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

/**
 * Moves files to "{trash}/{disk}/{bucket}/{original path}" so they can be restored.
 */
class TrashFiles
{
    public function __construct(
        protected Trash $trash,
        protected FileMover $mover,
        protected Dispatcher $events,
        protected Repository $config,
    ) {}

    /**
     * @param  iterable<int, FileInfo>  $files
     */
    public function handle(iterable $files): ActionResult
    {
        $result = new ActionResult('trash');

        /** @var array<string, array{name: string, existed: bool, overflow?: string}> $buckets */
        $buckets = [];

        foreach (Chunks::of($files, (int) $this->config->get('orphan-files.chunk_size', 1000)) as $chunk) {
            /** @var array<string, array<string, array{paths: list<string>, bytes: int}>> $moved */
            $moved = [];

            foreach ($chunk as $file) {
                try {
                    $bucket = $this->bucketFor($file, $buckets);

                    $this->mover->move(
                        $file->disk,
                        $file->path,
                        $this->trash->diskFor($file->disk),
                        $this->trash->path($file->disk, $bucket, $file->path),
                    );
                } catch (Throwable $exception) {
                    $result->failed($file->disk, $file->path, $exception->getMessage());

                    continue;
                }

                $result->succeeded($file->disk, $file->path, $file->size);

                $moved[$file->disk][$bucket] ??= ['paths' => [], 'bytes' => 0];
                $moved[$file->disk][$bucket]['paths'][] = $file->path;
                $moved[$file->disk][$bucket]['bytes'] += (int) $file->size;
            }

            foreach ($moved as $disk => $byBucket) {
                foreach ($byBucket as $bucket => $batch) {
                    $this->events->dispatch(new OrphanFilesTrashed(
                        (string) $disk,
                        $batch['paths'],
                        $batch['bytes'],
                        $this->trash->diskFor((string) $disk),
                        $this->trash->root((string) $disk).'/'.$bucket,
                    ));
                }
            }
        }

        return $result;
    }

    /**
     * Today's bucket, unless it already holds this path from an earlier run:
     * then a fresh bucket, so an earlier trashed copy is never overwritten.
     * A run never lists the same path twice, so a new bucket needs no checks.
     *
     * @param  array<string, array{name: string, existed: bool, overflow?: string}>  $buckets
     */
    protected function bucketFor(FileInfo $file, array &$buckets): string
    {
        $buckets[$file->disk] ??= $this->trash->todaysBucket($file->disk);
        $bucket = $buckets[$file->disk];

        if (! $bucket['existed'] || ! $this->trash->holds($file->disk, $this->trash->path($file->disk, $bucket['name'], $file->path))) {
            return $bucket['name'];
        }

        return $buckets[$file->disk]['overflow'] ??= $this->trash->newBucket($file->disk);
    }
}
