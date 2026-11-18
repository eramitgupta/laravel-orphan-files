<?php

namespace Erag\OrphanFiles\Jobs;

use Erag\OrphanFiles\OrphanFiles;
use Erag\OrphanFiles\Support\Data\CleanOptions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Runs a clean in the background. Listen for OrphanFilesTrashed or
 * OrphanFilesDeleted to follow progress.
 */
class CleanOrphansJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public CleanOptions $options = new CleanOptions) {}

    public function handle(OrphanFiles $orphanFiles): void
    {
        $orphanFiles->clean($this->options);
    }
}
