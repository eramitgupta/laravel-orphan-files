<?php

namespace Erag\OrphanStorage\Support\Data;

use Erag\OrphanStorage\Scanner\FileInfo;

final class Explanation
{
    public const REFERENCED = 'referenced';

    public const PROTECTED = 'protected';

    public const ORPHAN = 'orphan';

    public const MISSING = 'missing';

    /**
     * @param  list<array{source: string, type: string, key: mixed, value: string}>  $references
     * @param  list<string>  $directories  The configured scan directories of the disk.
     */
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
        public readonly ?FileInfo $file,
        public readonly array $references,
        public readonly ?string $reasonToKeep,
        public readonly bool $inScope,
        public readonly array $directories,
        public readonly int $sourcesChecked,
    ) {}

    public function verdict(): string
    {
        return match (true) {
            $this->references !== [] => self::REFERENCED,
            $this->file === null => self::MISSING,
            $this->reasonToKeep !== null || ! $this->inScope => self::PROTECTED,
            default => self::ORPHAN,
        };
    }
}
