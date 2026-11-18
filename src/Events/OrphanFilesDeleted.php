<?php

namespace Erag\OrphanFiles\Events;

/**
 * Dispatched once per batch (`chunk_size` files) deleted permanently, by
 * a clean in delete mode or when the trash is purged.
 */
class OrphanFilesDeleted
{
    /**
     * @param  list<string>  $paths
     */
    public function __construct(
        public readonly string $disk,
        public readonly array $paths,
        public readonly int $bytes,
    ) {}
}
