<?php

namespace Erag\OrphanStorage\References\Extractors;

use Erag\OrphanStorage\Contracts\ReferenceExtractor;
use Erag\OrphanStorage\References\Source;

/**
 * A column holding a full URL: Storage::url(), a CDN URL or a signed URL.
 * The URL is mapped back to a disk path by the UrlToPathNormalizer.
 */
class UrlExtractor implements ReferenceExtractor
{
    public function extract(mixed $value, Source $source): iterable
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return [trim($value)];
    }
}
