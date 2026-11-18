<?php

namespace Erag\OrphanFiles\Support;

use InvalidArgumentException;

final class Duration
{
    /**
     * Parse "90m", "48h", "7d" or "2w" (plain numbers are hours) into minutes.
     *
     * @throws InvalidArgumentException
     */
    public static function toMinutes(string $value): int
    {
        if (preg_match('/^\s*(\d+)\s*(m|min|h|d|w)?\s*$/i', $value, $matches) !== 1) {
            throw new InvalidArgumentException("Invalid duration [{$value}]. Use e.g. 90m, 48h, 7d or 2w.");
        }

        $amount = (int) $matches[1];

        return match (strtolower($matches[2] ?? 'h')) {
            'm', 'min' => $amount,
            'd' => $amount * 1440,
            'w' => $amount * 10080,
            default => $amount * 60,
        };
    }
}
