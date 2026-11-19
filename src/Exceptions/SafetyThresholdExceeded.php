<?php

namespace Erag\OrphanFiles\Exceptions;

use RuntimeException;

class SafetyThresholdExceeded extends RuntimeException
{
    /**
     * @param  array<string, float>  $ratios  Orphan ratio (percent) per offending disk.
     */
    public function __construct(public readonly array $ratios, public readonly float $threshold)
    {
        $details = collect($ratios)
            ->map(fn (float $ratio, string $disk): string => sprintf('%s: %s%%', $disk, rtrim(rtrim(number_format($ratio, 2, '.', ''), '0'), '.')))
            ->implode(', ');

        parent::__construct(sprintf(
            'Too many files would be cleaned (%s, limit %s%%), so a forced clean won\'t run. A source is probably missing from config/orphan-files.php; check a few files with orphan:explain, or pass --force-ratio if the number is right.',
            $details,
            rtrim(rtrim(number_format($threshold, 1, '.', ''), '0'), '.'),
        ));
    }
}
