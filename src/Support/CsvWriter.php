<?php

namespace Erag\OrphanStorage\Support;

use Erag\OrphanStorage\Scanner\FileInfo;
use RuntimeException;

/**
 * Streams orphans to a CSV file, one row at a time.
 */
class CsvWriter
{
    /**
     * @var resource
     */
    protected $handle;

    public int $rows = 0;

    public function __construct(public readonly string $path)
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create the export directory [{$directory}].");
        }

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException("Unable to write the export file [{$path}].");
        }

        $this->handle = $handle;

        fputcsv($this->handle, ['disk', 'path', 'size', 'last_modified', 'status', 'reason'], escape: '\\');
    }

    public function write(FileInfo $file, ?string $reasonToKeep): void
    {
        fputcsv($this->handle, [
            $file->disk,
            $file->path,
            $file->size,
            $file->lastModified !== null ? gmdate('Y-m-d\TH:i:s\Z', $file->lastModified) : '',
            $reasonToKeep === null ? 'cleanable' : 'protected',
            $reasonToKeep ?? '',
        ], escape: '\\');

        $this->rows++;
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}
