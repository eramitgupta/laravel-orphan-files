<?php

namespace Erag\OrphanFiles\Support;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use RuntimeException;

/**
 * Moves a file within a disk (a native rename or server-side copy) or
 * streams it between disks.
 */
class FileMover
{
    public function __construct(protected FilesystemFactory $filesystems) {}

    /**
     * @throws RuntimeException
     */
    public function move(string $fromDisk, string $from, string $toDisk, string $to): void
    {
        $source = $this->filesystems->disk($fromDisk);

        if ($fromDisk === $toDisk) {
            if (! $source->move($from, $to)) {
                throw new RuntimeException("Unable to move [{$from}] to [{$to}] on disk [{$fromDisk}].");
            }

            return;
        }

        $stream = $source->readStream($from);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read [{$from}] on disk [{$fromDisk}].");
        }

        try {
            if (! $this->filesystems->disk($toDisk)->writeStream($to, $stream)) {
                throw new RuntimeException("Unable to write [{$to}] on disk [{$toDisk}].");
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $source->delete($from)) {
            throw new RuntimeException("Copied [{$from}] to disk [{$toDisk}] but could not remove the original.");
        }
    }
}
