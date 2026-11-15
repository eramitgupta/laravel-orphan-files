<?php

namespace Erag\OrphanStorage\Support\Data;

final class TrashedFile
{
    /**
     * @param  string  $bucket  The trash bucket, e.g. "2026-11-15" or "2026-11-15_101500".
     */
    public function __construct(
        public readonly string $disk,
        public readonly string $bucket,
        public readonly string $path,
        public readonly string $trashDisk,
        public readonly string $trashPath,
        public readonly ?int $size = null,
    ) {}
}
