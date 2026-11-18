<?php

namespace Erag\OrphanFiles\Support\Data;

final class CleanResult
{
    /**
     * @param  array<string, float>  $ratioViolations  Disks above the safety threshold (dry runs only).
     */
    public function __construct(
        public readonly ScanReport $report,
        public readonly string $mode,
        public readonly bool $dryRun,
        public readonly ?ActionResult $action = null,
        public readonly array $ratioViolations = [],
        public readonly ?int $limit = null,
    ) {}

    /**
     * The number of files a forced run would clean.
     */
    public function candidates(): int
    {
        $cleanable = $this->report->totals()['cleanable'];

        return $this->limit === null ? $cleanable : min($cleanable, $this->limit);
    }
}
