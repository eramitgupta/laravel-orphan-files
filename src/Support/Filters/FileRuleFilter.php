<?php

namespace Erag\OrphanFiles\Support\Filters;

use Erag\OrphanFiles\Contracts\FileFilter;
use Erag\OrphanFiles\Scanner\FileInfo;
use Erag\OrphanFiles\Support\Bytes;

/**
 * Limits cleanup by file size and extension.
 */
class FileRuleFilter implements FileFilter
{
    /**
     * @var list<string>
     */
    protected array $onlyExtensions;

    /**
     * @var list<string>
     */
    protected array $exceptExtensions;

    /**
     * @param  list<string>  $onlyExtensions
     * @param  list<string>  $exceptExtensions
     */
    public function __construct(
        protected ?int $minSize = null,
        protected ?int $maxSize = null,
        array $onlyExtensions = [],
        array $exceptExtensions = [],
    ) {
        $this->onlyExtensions = $this->extensions($onlyExtensions);
        $this->exceptExtensions = $this->extensions($exceptExtensions);
    }

    public function reasonToKeep(FileInfo $file): ?string
    {
        $extension = $file->extension();

        if ($this->onlyExtensions !== [] && ! in_array($extension, $this->onlyExtensions, true)) {
            return "its extension [{$extension}] is not in only_extensions";
        }

        if (in_array($extension, $this->exceptExtensions, true)) {
            return "its extension [{$extension}] is in except_extensions";
        }

        if (($this->minSize !== null || $this->maxSize !== null) && $file->size === null) {
            return 'its size is unknown';
        }

        if ($this->minSize !== null && $file->size < $this->minSize) {
            return 'it is smaller than min_size ('.Bytes::format($this->minSize).')';
        }

        if ($this->maxSize !== null && $file->size > $this->maxSize) {
            return 'it is larger than max_size ('.Bytes::format($this->maxSize).')';
        }

        return null;
    }

    /**
     * @param  list<string>  $extensions
     * @return list<string>
     */
    protected function extensions(array $extensions): array
    {
        return array_values(array_filter(array_map(
            fn (string $extension): string => strtolower(ltrim(trim($extension), '.')),
            $extensions,
        )));
    }
}
