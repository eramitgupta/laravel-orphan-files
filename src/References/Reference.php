<?php

namespace Erag\OrphanFiles\References;

final class Reference
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $disk = null,
        public readonly bool $isDirectory = false,
    ) {}

    /**
     * Reference a single file.
     */
    public static function file(string $path, ?string $disk = null): self
    {
        return new self($path, $disk);
    }

    /**
     * Reference every file below a directory.
     */
    public static function directory(string $path, ?string $disk = null): self
    {
        return new self($path, $disk, true);
    }
}
