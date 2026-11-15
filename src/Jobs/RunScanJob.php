<?php

namespace Erag\OrphanStorage\Jobs;

use Erag\OrphanStorage\OrphanStorage;
use Erag\OrphanStorage\Support\Data\ScanOptions;
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

    public function handle(OrphanStorage $orphanStorage): void
    {
        $orphanStorage->scan($this->options);
    }
}
