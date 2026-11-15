<?php

namespace Erag\OrphanStorage\Diff;

use Generator;

class MemoryReferenceIndex implements ReferenceIndex
{
    /**
     * @var array<string, true>
     */
    protected array $hashes = [];

    public function add(iterable $hashes): void
    {
        foreach ($hashes as $hash) {
            $this->hashes[$hash] = true;
        }
    }

    public function existing(array $hashes): array
    {
        $found = [];

        foreach ($hashes as $hash) {
            if (isset($this->hashes[$hash])) {
                $found[$hash] = true;
            }
        }

        return $found;
    }

    public function count(): int
    {
        return count($this->hashes);
    }

    /**
     * @return Generator<int, string>
     */
    public function hashes(): Generator
    {
        foreach (array_keys($this->hashes) as $hash) {
            yield (string) $hash;
        }
    }

    public function release(): void
    {
        $this->hashes = [];
    }
}
