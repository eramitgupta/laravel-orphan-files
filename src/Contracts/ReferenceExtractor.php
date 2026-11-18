<?php

namespace Erag\OrphanFiles\Contracts;

use Erag\OrphanFiles\References\Reference;
use Erag\OrphanFiles\References\Source;

interface ReferenceExtractor
{
    /**
     * Extract every storage reference from a database value.
     *
     * Yield plain strings (paths or URLs, which the package normalises) or
     * Reference objects to pin a reference to a disk or protect a directory.
     * Row-level sources (column "*") receive the whole row as an array.
     *
     * @return iterable<int, string|Reference>
     */
    public function extract(mixed $value, Source $source): iterable;
}
