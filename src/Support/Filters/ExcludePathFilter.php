<?php

namespace Erag\OrphanFiles\Support\Filters;

use Erag\OrphanFiles\Contracts\FileFilter;
use Erag\OrphanFiles\Scanner\FileInfo;
use Illuminate\Support\Str;

/**
 * Keeps files listed in `exclude_paths` or matching `exclude_patterns`.
 *
 * Entries without a slash also match the file name in any directory, so
 * ".gitignore" protects "avatars/.gitignore". Entries ending in a slash
 * protect a whole directory.
 */
class ExcludePathFilter implements FileFilter
{
    /**
     * @param  list<string>  $paths
     * @param  list<string>  $patterns
     */
    public function __construct(
        protected array $paths = [],
        protected array $patterns = [],
        protected bool $caseSensitive = true,
    ) {}

    public function reasonToKeep(FileInfo $file): ?string
    {
        $path = $this->prepare($file->path);
        $basename = basename($path);

        foreach ($this->paths as $excluded) {
            $excluded = $this->prepare($excluded);

            if ($excluded === '') {
                continue;
            }

            $matches = $path === $excluded
                || (str_ends_with($excluded, '/') && str_starts_with($path, $excluded))
                || (! str_contains($excluded, '/') && $basename === $excluded);

            if ($matches) {
                return "it is excluded by path [{$excluded}]";
            }
        }

        foreach ($this->patterns as $pattern) {
            $pattern = $this->prepare($pattern);

            if ($pattern === '') {
                continue;
            }

            if (Str::is($pattern, $path) || (! str_contains($pattern, '/') && Str::is($pattern, $basename))) {
                return "it matches the excluded pattern [{$pattern}]";
            }
        }

        return null;
    }

    protected function prepare(string $value): string
    {
        $value = ltrim(str_replace('\\', '/', trim($value)), '/');

        return $this->caseSensitive ? $value : Str::lower($value);
    }
}
