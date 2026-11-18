<?php

namespace Erag\OrphanFiles\Exceptions;

use Erag\OrphanFiles\References\Source;
use InvalidArgumentException;

class InvalidSourceException extends InvalidArgumentException
{
    public static function invalidDefinition(int|string $index, string $problem): self
    {
        return new self("Source [{$index}] in config/orphan-files.php is invalid: {$problem}");
    }

    public static function tableNotFound(Source $source): self
    {
        return new self(sprintf(
            'Source [%s]: table [%s] doesn\'t exist on connection [%s]. Fix or remove the source in config/orphan-files.php.',
            $source->label(),
            $source->table,
            $source->connection ?? 'default',
        ));
    }

    public static function columnNotFound(Source $source, string $column): self
    {
        return new self(sprintf(
            'Source [%s]: column [%s] doesn\'t exist on table [%s]. Fix or remove the source in config/orphan-files.php.',
            $source->label(),
            $column,
            $source->table,
        ));
    }

    public static function extractionFailed(Source $source, string $reason): self
    {
        return new self(sprintf(
            'Source [%s]: could not work out its file paths, so nothing was cleaned. %s',
            $source->label(),
            $reason,
        ));
    }

    public static function unknownType(Source $source): self
    {
        return new self(sprintf(
            'Source [%s] has unknown type [%s]. Add an extractor for it under "extractors" in config/orphan-files.php.',
            $source->label(),
            $source->type,
        ));
    }
}
