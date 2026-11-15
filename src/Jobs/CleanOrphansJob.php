<?php

namespace Erag\OrphanStorage\Jobs;

use Erag\OrphanStorage\OrphanStorage;
use Erag\OrphanStorage\Support\Data\CleanOptions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Runs a clean in the background. Listen for OrphanStorageTrashed or
 * OrphanStorageDeleted to follow progress.
 */
class CleanOrphansJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public CleanOptions $options = new CleanOptions) {}

    public function handle(OrphanStorage $orphanStorage): void
    {
        $orphanStorage->clean($this->options);
    }
}
