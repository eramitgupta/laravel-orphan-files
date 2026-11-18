<?php

namespace Erag\OrphanFiles\Events;

use Erag\OrphanFiles\Support\Data\ScanReport;

class OrphanScanCompleted
{
    public function __construct(public readonly ScanReport $report) {}
}
