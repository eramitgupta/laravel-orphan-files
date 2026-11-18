<?php

namespace Erag\OrphanFiles\References\Extractors;

use Erag\OrphanFiles\Contracts\ReferenceExtractor;
use Erag\OrphanFiles\References\Source;

/**
 * A column holding one disk-relative path, or several joined by the
 * optional `separator` source option.
 */
class PathExtractor implements ReferenceExtractor
{
    public function extract(mixed $value, Source $source): iterable
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $separator = $source->options['separator'] ?? null;
        $values = is_string($separator) && $separator !== '' ? explode($separator, $value) : [$value];

        return array_values(array_filter(
            array_map('trim', $values),
            fn (string $path): bool => $path !== '',
        ));
    }
}
