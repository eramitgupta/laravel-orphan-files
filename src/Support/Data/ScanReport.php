<?php

namespace Erag\OrphanFiles\Support\Data;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

final class ScanReport
{
    /**
     * @var array<string, DiskReport>
     */
    public array $disks = [];

    /**
     * Unique reference keys found in the database.
     */
    public int $references = 0;

    public ?CarbonInterface $finishedAt = null;

    public function __construct(public readonly CarbonInterface $startedAt) {}

    /**
     * @param  list<string>  $directories
     */
    public function disk(string $disk, array $directories): DiskReport
    {
        return $this->disks[$disk] ??= new DiskReport($disk, $directories);
    }

    public function finish(): self
    {
        $this->finishedAt = Date::now();

        return $this;
    }

    /**
     * @return array{files: int, referenced: int, orphans: int, protected: int, cleanable: int, total_bytes: int, orphan_bytes: int, reclaimable_bytes: int}
     */
    public function totals(): array
    {
        $totals = [
            'files' => 0,
            'referenced' => 0,
            'orphans' => 0,
            'protected' => 0,
            'cleanable' => 0,
            'total_bytes' => 0,
            'orphan_bytes' => 0,
            'reclaimable_bytes' => 0,
        ];

        foreach ($this->disks as $disk) {
            $totals['files'] += $disk->files;
            $totals['referenced'] += $disk->referenced;
            $totals['orphans'] += $disk->orphans;
            $totals['protected'] += $disk->protected;
            $totals['cleanable'] += $disk->cleanable;
            $totals['total_bytes'] += $disk->totalBytes;
            $totals['orphan_bytes'] += $disk->orphanBytes;
            $totals['reclaimable_bytes'] += $disk->reclaimableBytes;
        }

        return $totals;
    }

    /**
     * @return array{started_at: string, finished_at: string|null, references: int, totals: array<string, int>, disks: array<string, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'started_at' => $this->startedAt->toIso8601String(),
            'finished_at' => $this->finishedAt?->toIso8601String(),
            'references' => $this->references,
            'totals' => $this->totals(),
            'disks' => array_map(fn (DiskReport $disk): array => $disk->toArray(), $this->disks),
        ];
    }
}
