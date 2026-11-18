<?php

namespace Erag\OrphanFiles\Support\Normalizers;

use Erag\OrphanFiles\Contracts\PathNormalizer;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Throwable;

class UrlToPathNormalizer implements PathNormalizer
{
    /**
     * Known base URLs per disk, split into host ("//cdn.example.com" or "") and path ("/uploads/").
     *
     * @var array<string, list<array{host: string, path: string}>>
     */
    protected array $bases = [];

    public function __construct(
        protected Repository $config,
        protected FilesystemFactory $filesystems,
    ) {}

    /**
     * Resolve a URL to a path on the disk, or null when no known base URL matches.
     */
    public function normalize(string $path, string $disk): ?string
    {
        return $this->matches($path, $disk)[0] ?? null;
    }

    /**
     * Every disk-relative path the value resolves to, most specific base URL first.
     *
     * @return list<string>
     */
    public function matches(string $value, string $disk): array
    {
        $parts = $this->split($this->stripQuery($value));

        if ($parts === null) {
            return [];
        }

        $matches = [];

        foreach ($this->basesFor($disk) as $base) {
            if ($parts['host'] !== '' && $parts['host'] !== $base['host']) {
                continue;
            }

            if (str_starts_with($parts['path'], $base['path'])) {
                $matches[] = substr($parts['path'], strlen($base['path']));
            }
        }

        return array_values(array_unique(array_filter($matches, fn (string $match): bool => $match !== '')));
    }

    /**
     * Resolve an absolute filesystem path (e.g. "/var/www/storage/app/public/a.jpg") on a local disk.
     */
    public function fromLocalRoot(string $value, string $disk): ?string
    {
        $config = $this->diskConfig($disk);

        if (($config['driver'] ?? null) !== 'local' || ! is_string($config['root'] ?? null) || $config['root'] === '') {
            return null;
        }

        $root = rtrim(str_replace('\\', '/', $config['root']), '/').'/';
        $value = str_replace('\\', '/', $value);

        return str_starts_with($value, $root) ? substr($value, strlen($root)) : null;
    }

    public function isAbsoluteUrl(string $value): bool
    {
        return str_starts_with($value, '//') || preg_match('#^[a-z][a-z0-9+.\-]*://#i', $value) === 1;
    }

    /**
     * The path component of a URL, without the leading slash.
     */
    public function pathOf(string $url): string
    {
        return ltrim($this->split($this->stripQuery($url))['path'] ?? '', '/');
    }

    /**
     * Remove the query string and fragment (signed URL parameters, cache busters).
     */
    public function stripQuery(string $value): string
    {
        return (string) preg_replace('/[?#].*$/s', '', $value);
    }

    /**
     * Forget cached base URLs, e.g. after the disk configuration changed.
     */
    public function flush(): void
    {
        $this->bases = [];
    }

    /**
     * @return list<array{host: string, path: string}>
     */
    protected function basesFor(string $disk): array
    {
        if (isset($this->bases[$disk])) {
            return $this->bases[$disk];
        }

        $config = $this->diskConfig($disk);
        $keyPrefix = $this->keyPrefix($config);
        $urls = [];

        try {
            $urls[] = $this->filesystems->disk($disk)->url('');
        } catch (Throwable) {
            // The driver cannot build URLs; rely on the configured ones.
        }

        if (is_string($config['url'] ?? null) && $config['url'] !== '') {
            $urls[] = $config['url'];
        }

        if (($config['driver'] ?? null) === 's3') {
            $urls = [...$urls, ...$this->s3Endpoints($config, $keyPrefix)];
        }

        foreach ((array) $this->config->get("orphan-files.url_prefixes.{$disk}", []) as $prefix) {
            if (! is_string($prefix) || $prefix === '') {
                continue;
            }

            $urls[] = $prefix;

            if ($keyPrefix !== '') {
                $urls[] = rtrim($prefix, '/').'/'.$keyPrefix;
            }
        }

        $appHost = $this->split((string) $this->config->get('app.url', ''))['host'] ?? '';
        $bases = [];

        foreach ($urls as $url) {
            $parts = $this->split($url);

            if ($parts === null) {
                continue;
            }

            $path = rtrim($parts['path'], '/').'/';
            $bases[$parts['host'].$path] = ['host' => $parts['host'], 'path' => $path];

            // A path-only base (e.g. "/storage/") is served from the application's own host.
            if ($parts['host'] === '' && $appHost !== '') {
                $bases[$appHost.$path] = ['host' => $appHost, 'path' => $path];
            }
        }

        $bases = array_values($bases);

        usort($bases, fn (array $a, array $b): int => strlen($b['path']) <=> strlen($a['path']));

        return $this->bases[$disk] = $bases;
    }

    /**
     * Public endpoints of an S3 or S3-compatible bucket.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function s3Endpoints(array $config, string $keyPrefix): array
    {
        $bucket = $config['bucket'] ?? null;

        if (! is_string($bucket) || $bucket === '') {
            return [];
        }

        $region = $config['region'] ?? null;
        $endpoints = [
            "https://{$bucket}.s3.amazonaws.com",
            "https://s3.amazonaws.com/{$bucket}",
        ];

        if (is_string($region) && $region !== '') {
            array_push(
                $endpoints,
                "https://{$bucket}.s3.{$region}.amazonaws.com",
                "https://{$bucket}.s3-{$region}.amazonaws.com",
                "https://s3.{$region}.amazonaws.com/{$bucket}",
                "https://s3-{$region}.amazonaws.com/{$bucket}",
            );
        }

        $endpoint = $config['endpoint'] ?? null;

        if (is_string($endpoint) && $endpoint !== '') {
            $endpoint = rtrim($endpoint, '/');
            $endpoints[] = "{$endpoint}/{$bucket}";

            if (preg_match('#^(https?://)(.+)$#i', $endpoint, $matches) === 1) {
                $endpoints[] = "{$matches[1]}{$bucket}.{$matches[2]}";
            }
        }

        return array_map(
            fn (string $endpoint): string => $keyPrefix === '' ? $endpoint : $endpoint.'/'.$keyPrefix,
            $endpoints,
        );
    }

    /**
     * The key prefix ("root" and "prefix" options) that remote drivers add to every object key.
     *
     * @param  array<string, mixed>  $config
     */
    protected function keyPrefix(array $config): string
    {
        $segments = [];

        if (($config['driver'] ?? null) !== 'local' && is_string($config['root'] ?? null)) {
            $segments[] = trim($config['root'], '/');
        }

        if (is_string($config['prefix'] ?? null)) {
            $segments[] = trim($config['prefix'], '/');
        }

        return implode('/', array_filter($segments, fn (string $segment): bool => $segment !== ''));
    }

    /**
     * Split a URL into a lower-cased "//host[:port]" part and its path.
     *
     * @return array{host: string, path: string}|null
     */
    protected function split(string $url): ?array
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (preg_match('#^(?:[a-z][a-z0-9+.\-]*:)?//([^/]+)(.*)$#i', $url, $matches) === 1) {
            $host = strtolower(preg_replace('/^[^@]*@/', '', $matches[1]) ?? $matches[1]);

            return ['host' => '//'.$host, 'path' => '/'.ltrim($matches[2], '/')];
        }

        if (str_starts_with($url, '/')) {
            return ['host' => '', 'path' => $url];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function diskConfig(string $disk): array
    {
        return (array) $this->config->get("filesystems.disks.{$disk}", []);
    }
}
