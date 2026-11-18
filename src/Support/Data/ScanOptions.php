<?php

namespace Erag\OrphanFiles\Support\Data;

final class ScanOptions
{
    /**
     * @param  list<string>|null  $disks  Null scans every configured disk.
     * @param  list<string>|null  $directories  Null uses each disk's configured directories.
     * @param  int|null  $minimumAgeMinutes  Replaces the configured grace period when set.
     */
    public function __construct(
        public readonly ?array $disks = null,
        public readonly ?array $directories = null,
        public readonly ?int $minimumAgeMinutes = null,
    ) {}
}
