<?php

namespace Erag\OrphanFiles\Actions;

use Erag\OrphanFiles\Scanner\FileInfo;
use Erag\OrphanFiles\Scanner\StorageScanner;
use Erag\OrphanFiles\Support\Data\ActionResult;
use Erag\OrphanFiles\Support\Data\TrashedFile;
use Erag\OrphanFiles\Support\Trash;
use Generator;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\Date;

/**
 * Permanently deletes trash buckets older than the retention period.
 *
 * Only the files that were listed are deleted. The bucket folder itself
 * is removed afterwards only when every delete succeeded and nothing new
 * arrived in it meanwhile.
 */
class PurgeTrash
{
    public function __construct(
        protected Trash $trash,
        protected DeleteFiles $delete,
        protected FilesystemFactory $filesystems,
        protected StorageScanner $scanner,
    ) {}

    /**
     * @param  int|null  $retentionDays  Null uses `trash.retention_days`.
     * @param  bool  $all  Purge every bucket, including today's.
     * @param  bool  $dryRun  Count what would be purged without deleting.
     */
    public function handle(?string $disk = null, ?int $retentionDays = null, bool $all = false, bool $dryRun = false): ActionResult
    {
        $result = new ActionResult('purge');
        $retention = max(0, $retentionDays ?? $this->trash->retentionDays());
        $cutoff = Date::today()->subDays($retention)->format('Y-m-d');

        foreach ($disk !== null ? [$disk] : $this->trash->sourceDisks() as $sourceDisk) {
            foreach ($this->trash->buckets($sourceDisk) as $bucket) {
                // A retention of 0 days keeps nothing, including today's trash.
                if (! $all && $retention > 0 && $this->trash->bucketDate($bucket) >= $cutoff) {
                    continue;
                }

                if ($dryRun) {
                    foreach ($this->trash->bucketEntries($sourceDisk, $bucket) as $entry) {
                        $result->succeeded($entry->trashDisk, $entry->trashPath, $entry->size);
                    }

                    continue;
                }

                $purged = $this->delete->handle($this->files($this->trash->bucketEntries($sourceDisk, $bucket)));
                $result->merge($purged);

                if ($purged->failed === 0) {
                    $this->removeIfEmpty($sourceDisk, $bucket);
                }
            }
        }

        return $result;
    }

    /**
     * Remove the (now empty) bucket folder, e.g. leftover local directories.
     */
    protected function removeIfEmpty(string $sourceDisk, string $bucket): void
    {
        $trashDisk = $this->trash->diskFor($sourceDisk);
        $folder = $this->trash->root($sourceDisk).'/'.$bucket;

        foreach ($this->scanner->list($trashDisk, $folder) as $file) {
            return;
        }

        $this->filesystems->disk($trashDisk)->deleteDirectory($folder);
    }

    /**
     * @param  iterable<int, TrashedFile>  $entries
     * @return Generator<int, FileInfo>
     */
    protected function files(iterable $entries): Generator
    {
        foreach ($entries as $entry) {
            yield new FileInfo($entry->trashDisk, $entry->trashPath, $entry->size);
        }
    }
}
