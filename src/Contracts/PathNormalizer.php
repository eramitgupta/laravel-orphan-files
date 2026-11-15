<?php

namespace Erag\OrphanStorage\Contracts;

interface PathNormalizer
{
    /**
     * Turn a stored reference into a path relative to the given disk.
     *
     * Return null when the value has no meaning on that disk.
     */
    public function normalize(string $path, string $disk): ?string;
}
