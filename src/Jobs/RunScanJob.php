<?php

namespace Erag\OrphanFiles\Jobs;

use Erag\OrphanFiles\OrphanFiles;
use Erag\OrphanFiles\Support\Data\ScanOptions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Runs a scan in the background. Listen for OrphanScanCompleted to use the report.
 */
class RunScanJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public ScanOptions $options = new ScanOptions) {}

    public function handle(OrphanFiles $orphanFiles): void
    {
        $orphanFiles->scan($this->options);
    }
}
