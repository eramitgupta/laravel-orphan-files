<?php

namespace Erag\OrphanStorage\Support;

use Generator;

final class Chunks
{
    /**
     * Split any iterable into lists of at most $size items, lazily.
     *
     * @template TValue
     *
     * @param  iterable<mixed, TValue>  $items
     * @return Generator<int, list<TValue>>
     */
    public static function of(iterable $items, int $size): Generator
    {
        $size = max(1, $size);
        $chunk = [];

        foreach ($items as $item) {
            $chunk[] = $item;

            if (count($chunk) >= $size) {
                yield $chunk;

                $chunk = [];
            }
        }

        if ($chunk !== []) {
            yield $chunk;
        }
    }
}
