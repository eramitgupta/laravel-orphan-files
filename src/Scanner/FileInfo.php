<?php

namespace Erag\OrphanFiles\Scanner;

final class FileInfo
{
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
        public readonly ?int $size = null,
        public readonly ?int $lastModified = null,
    ) {}

    public function basename(): string
    {
        return basename($this->path);
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
    }

    /**
     * @return array{disk: string, path: string, size: int|null, last_modified: int|null}
     */
    public function toArray(): array
    {
        return [
            'disk' => $this->disk,
            'path' => $this->path,
            'size' => $this->size,
            'last_modified' => $this->lastModified,
        ];
    }
}
