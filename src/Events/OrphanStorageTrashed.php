<?php

namespace Erag\OrphanStorage\Events;

/**
 * Dispatched once per batch (`chunk_size` files) moved to the trash.
 */
class OrphanStorageTrashed
{
    /**
     * @param  list<string>  $paths  Original paths on the source disk.
     * @param  string  $trashDirectory  The dated trash folder, e.g. ".orphan-trash/public/2026-11-15".
     */
    public function __construct(
        public readonly string $disk,
        public readonly array $paths,
        public readonly int $bytes,
        public readonly string $trashDisk,
        public readonly string $trashDirectory,
    ) {}
}
