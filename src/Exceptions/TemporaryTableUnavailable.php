<?php

namespace Erag\OrphanFiles\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The temporary reference table could not be created, e.g. because the
 * database user may not create temporary tables.
 */
class TemporaryTableUnavailable extends RuntimeException
{
    public static function onConnection(string $connection, Throwable $previous): self
    {
        return new self(sprintf(
            'Could not create the temporary reference table on database connection [%s]: %s Grant the database user permission to create temporary tables, or set "orphan-files.diff.driver" to "memory".',
            $connection,
            rtrim($previous->getMessage(), '.').'.',
        ), previous: $previous);
    }
}
