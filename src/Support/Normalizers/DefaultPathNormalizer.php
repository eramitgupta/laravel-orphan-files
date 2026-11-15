<?php

namespace Erag\OrphanStorage\Support\Normalizers;

use Erag\OrphanStorage\Contracts\PathNormalizer;

class DefaultPathNormalizer implements PathNormalizer
{
    /**
     * Clean a path: forward slashes, no leading or duplicate slashes and
     * no "." or ".." segments.
     */
    public function normalize(string $path, string $disk): ?string
    {
        $segments = [];

        foreach (explode('/', str_replace('\\', '/', trim($path))) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return $segments === [] ? null : implode('/', $segments);
    }
}
