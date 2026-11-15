<?php

namespace Erag\OrphanStorage\Events;

use Erag\OrphanStorage\Support\Data\ScanReport;

class OrphanScanCompleted
{
    public function __construct(public readonly ScanReport $report) {}
}
