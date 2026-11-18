<?php

namespace Erag\OrphanFiles\Support\Data;

use Erag\OrphanFiles\Scanner\FileInfo;

final class DiskReport
{
    public int $files = 0;

    public int $referenced = 0;

    public int $orphans = 0;

    public int $protected = 0;

    public int $cleanable = 0;

    public int $totalBytes = 0;

    public int $orphanBytes = 0;

    public int $reclaimableBytes = 0;

    /**
     * Protected orphans per filter, e.g. ['grace_period' => 3].
     *
     * @var array<string, int>
     */
    public array $protectedBy = [];

    /**
     * @param  list<string>  $directories
     */
    public function __construct(
        public readonly string $disk,
        public readonly array $directories,
    ) {}

    /**
     * @param  string|null  $keptBy  The filter that protects an unreferenced file.
     */
    public function record(FileInfo $file, bool $referenced, ?string $keptBy = null): void
    {
        $size = (int) $file->size;

        $this->files++;
        $this->totalBytes += $size;

        if ($referenced) {
            $this->referenced++;

            return;
        }

        $this->orphans++;
        $this->orphanBytes += $size;

        if ($keptBy !== null) {
            $this->protected++;
            $this->protectedBy[$keptBy] = ($this->protectedBy[$keptBy] ?? 0) + 1;

            return;
        }

        $this->cleanable++;
        $this->reclaimableBytes += $size;
    }

    /**
     * Cleanable files as a percentage of all scanned files.
     */
    public function orphanRatio(): float
    {
        return $this->files === 0 ? 0.0 : round($this->cleanable / $this->files * 100, 2);
    }

    /**
     * @return array{disk: string, directories: list<string>, files: int, referenced: int, orphans: int, protected: int, cleanable: int, total_bytes: int, orphan_bytes: int, reclaimable_bytes: int, orphan_ratio: float, protected_by: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'disk' => $this->disk,
            'directories' => $this->directories,
            'files' => $this->files,
            'referenced' => $this->referenced,
            'orphans' => $this->orphans,
            'protected' => $this->protected,
            'cleanable' => $this->cleanable,
            'total_bytes' => $this->totalBytes,
            'orphan_bytes' => $this->orphanBytes,
            'reclaimable_bytes' => $this->reclaimableBytes,
            'orphan_ratio' => $this->orphanRatio(),
            'protected_by' => $this->protectedBy,
        ];
    }
}
