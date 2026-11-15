<?php

namespace Erag\OrphanStorage\Support\Filters;

use Erag\OrphanStorage\Exceptions\SafetyThresholdExceeded;
use Erag\OrphanStorage\Support\Data\ScanReport;

/**
 * Stops a clean when a disk would lose a suspicious share of its files,
 * which usually means a source is missing from the config.
 */
class SafetyRatioGuard
{
    public function __construct(protected ?float $maxRatio) {}

    /**
     * Orphan ratios (percent) of the disks above the threshold.
     *
     * @return array<string, float>
     */
    public function violations(ScanReport $report): array
    {
        if ($this->maxRatio === null || $this->maxRatio >= 100) {
            return [];
        }

        $violations = [];

        foreach ($report->disks as $disk => $diskReport) {
            if ($diskReport->cleanable > 0 && $diskReport->orphanRatio() > $this->maxRatio) {
                $violations[$disk] = $diskReport->orphanRatio();
            }
        }

        return $violations;
    }

    /**
     * @throws SafetyThresholdExceeded
     */
    public function check(ScanReport $report): void
    {
        $violations = $this->violations($report);

        if ($violations !== []) {
            throw new SafetyThresholdExceeded($violations, (float) $this->maxRatio);
        }
    }

    public function threshold(): ?float
    {
        return $this->maxRatio;
    }
}
