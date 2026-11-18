<?php

namespace Erag\OrphanFiles\Diff;

/**
 * A set of hashed reference keys.
 */
interface ReferenceIndex
{
    /**
     * @param  iterable<int, string>  $hashes
     */
    public function add(iterable $hashes): void;

    /**
     * The given hashes that are present in the index.
     *
     * @param  list<string>  $hashes
     * @return array<string, true>
     */
    public function existing(array $hashes): array;

    public function count(): int;

    /**
     * Free memory or drop temporary storage.
     */
    public function release(): void;
}
