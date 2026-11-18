<?php

namespace Erag\OrphanFiles\Support\Normalizers;

use Closure;
use Erag\OrphanFiles\Contracts\PathNormalizer;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Normalizer;

/**
 * Turns stored references into disk paths and comparison keys.
 *
 * An ambiguous reference resolves to every plausible path (encoded and
 * decoded, with and without a query string, each URL base that matches).
 * Extra interpretations can only protect more files, never fewer.
 */
class NormalizerPipeline
{
    /**
     * @var list<PathNormalizer|callable(string, string): ?string>
     */
    protected array $extensions = [];

    /**
     * @var list<PathNormalizer|callable(string, string): ?string>|null
     */
    protected ?array $resolved = null;

    public function __construct(
        protected DefaultPathNormalizer $paths,
        protected UrlToPathNormalizer $urls,
        protected Repository $config,
        protected Container $container,
    ) {}

    /**
     * Register an extra normalizer at runtime.
     *
     * @param  PathNormalizer|callable(string, string): ?string  $normalizer
     */
    public function extend(PathNormalizer|callable $normalizer): void
    {
        $this->extensions[] = $normalizer;
        $this->resolved = null;
    }

    /**
     * Every disk-relative path a stored value may point at. The most likely path comes first.
     *
     * @return list<string>
     */
    public function paths(string $value, string $disk): array
    {
        $value = trim($value);

        if ($value === '' || preg_match('/^(?:data|mailto|tel|javascript|blob|about):/i', $value) === 1) {
            return [];
        }

        $candidates = $this->urls->isAbsoluteUrl($value)
            ? $this->urlCandidates($value, $disk)
            : $this->pathCandidates($value, $disk);

        foreach ($this->customNormalizers() as $normalizer) {
            foreach ($candidates as $candidate) {
                $result = $normalizer instanceof PathNormalizer
                    ? $normalizer->normalize($candidate, $disk)
                    : $normalizer($candidate, $disk);

                if (is_string($result) && $result !== '') {
                    $candidates[] = $result;
                }
            }
        }

        $paths = [];

        foreach ($candidates as $candidate) {
            $path = $this->paths->normalize($candidate, $disk);

            if ($path !== null) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * The one path a value points at, or null when it cannot be resolved with confidence.
     *
     * Used before removing a file, so URLs on unknown hosts never resolve.
     */
    public function primaryPath(string $value, string $disk): ?string
    {
        $value = trim($value);

        if ($this->urls->isAbsoluteUrl($value) && $this->urls->matches($value, $disk) === []) {
            return null;
        }

        return $this->paths($value, $disk)[0] ?? null;
    }

    /**
     * Clean a disk path without adding interpretations.
     */
    public function clean(string $path, string $disk): ?string
    {
        return $this->paths->normalize($path, $disk);
    }

    /**
     * The canonical comparison key of a clean path.
     */
    public function key(string $path): string
    {
        if (preg_match('/[^\x00-\x7F]/', $path) === 1 && class_exists(Normalizer::class)) {
            $path = Normalizer::normalize($path, Normalizer::FORM_C) ?: $path;
        }

        return $this->config->get('orphan-files.case_sensitive', true) ? $path : Str::lower($path);
    }

    /**
     * Forget cached state such as resolved base URLs.
     */
    public function flush(): void
    {
        $this->urls->flush();
        $this->resolved = null;
    }

    public function urls(): UrlToPathNormalizer
    {
        return $this->urls;
    }

    public function hasCustomNormalizers(): bool
    {
        return $this->customNormalizers() !== [];
    }

    /**
     * @return list<string>
     */
    protected function urlCandidates(string $value, string $disk): array
    {
        $matches = $this->urls->matches($value, $disk);

        if ($matches === []) {
            if (! $this->config->get('orphan-files.match_unknown_hosts', true)) {
                return [];
            }

            $matches = $this->suffixes($this->urls->pathOf($value));
        }

        $candidates = [];

        foreach ($matches as $match) {
            $decoded = rawurldecode($match);

            array_push($candidates, $decoded, $match);
        }

        // "//avatars/a.jpg" may be a path with a doubled slash rather than a protocol-relative URL.
        if (str_starts_with($value, '//')) {
            array_push($candidates, ...$this->pathCandidates(substr($value, 1), $disk));
        }

        return $candidates;
    }

    /**
     * @return list<string>
     */
    protected function pathCandidates(string $value, string $disk): array
    {
        $candidates = [];

        if (str_starts_with(str_replace('\\', '/', $value), '/')) {
            $local = $this->urls->fromLocalRoot($value, $disk);

            if ($local !== null) {
                $candidates[] = $local;
            }

            array_push($candidates, ...$this->urls->matches($value, $disk));
        }

        $candidates[] = $value;

        // "storage/avatars/a.jpg" is a Storage::url() path without its leading slash.
        if (! str_starts_with(str_replace('\\', '/', $value), '/')) {
            array_push($candidates, ...$this->urls->matches('/'.$value, $disk));
        }

        $withoutQuery = $this->urls->stripQuery($value);

        if ($withoutQuery !== $value && $withoutQuery !== '') {
            $candidates[] = $withoutQuery;
        }

        foreach ($candidates as $candidate) {
            $decoded = rawurldecode($candidate);

            if ($decoded !== $candidate) {
                $candidates[] = $decoded;
            }
        }

        return $candidates;
    }

    /**
     * "a/b/c.jpg" becomes ["a/b/c.jpg", "b/c.jpg", "c.jpg"].
     *
     * @return list<string>
     */
    protected function suffixes(string $path): array
    {
        $segments = array_values(array_filter(explode('/', $path), fn (string $segment): bool => $segment !== ''));
        $suffixes = [];

        foreach (array_keys($segments) as $offset) {
            $suffixes[] = implode('/', array_slice($segments, $offset));
        }

        return $suffixes;
    }

    /**
     * @return list<PathNormalizer|callable(string, string): ?string>
     */
    protected function customNormalizers(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $normalizers = [];

        foreach ([...(array) $this->config->get('orphan-files.normalizers', []), ...$this->extensions] as $normalizer) {
            if (is_string($normalizer) && class_exists($normalizer)) {
                $normalizer = $this->container->make($normalizer);
            }

            if (! $normalizer instanceof PathNormalizer && ! is_callable($normalizer)) {
                throw new InvalidArgumentException('Every entry in orphan-files.normalizers has to implement PathNormalizer or be callable.');
            }

            $normalizers[] = $normalizer instanceof Closure || $normalizer instanceof PathNormalizer
                ? $normalizer
                : Closure::fromCallable($normalizer);
        }

        return $this->resolved = $normalizers;
    }
}
