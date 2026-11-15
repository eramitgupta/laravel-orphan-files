<?php

namespace Erag\OrphanStorage\Support;

final class Bytes
{
    /**
     * Format a byte count, e.g. 1536 becomes "1.5 KB".
     */
    public static function format(int|float|null $bytes): string
    {
        $bytes = max(0, (float) $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $unit = 0;

        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return $unit === 0
            ? sprintf('%d %s', $bytes, $units[$unit])
            : sprintf('%s %s', rtrim(rtrim(number_format($bytes, 1, '.', ''), '0'), '.'), $units[$unit]);
    }
}
