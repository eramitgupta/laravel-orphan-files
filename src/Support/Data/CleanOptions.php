<?php

namespace Erag\OrphanStorage\Support\Data;

final class CleanOptions
{
    /**
     * @param  string|null  $mode  "trash" or "delete"; null uses the configured mode.
     * @param  int|null  $limit  The maximum number of files to clean.
     * @param  bool  $dryRun  Report what would happen without touching storage.
     * @param  bool  $forceRatio  Skip the safety ratio check.
     */
    public function __construct(
        public readonly ScanOptions $scan = new ScanOptions,
        public readonly ?string $mode = null,
        public readonly ?int $limit = null,
        public readonly bool $dryRun = true,
        public readonly bool $forceRatio = false,
    ) {}
}
