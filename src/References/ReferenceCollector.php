<?php

namespace Erag\OrphanStorage\References;

use Erag\OrphanStorage\Contracts\ReferenceExtractor;
use Erag\OrphanStorage\Exceptions\InvalidSourceException;
use Erag\OrphanStorage\Support\Normalizers\NormalizerPipeline;
use Generator;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\LazyCollection;

/**
 * Streams sources row by row and turns their values into disk references.
 */
class ReferenceCollector
{
    /**
     * Column types whose stored value always contains the file name literally.
     */
    protected const SEARCHABLE_TYPES = ['path', 'url', 'json', 'html'];

    /**
     * @var array<string, ReferenceExtractor|class-string<ReferenceExtractor>>
     */
    protected array $extensions = [];

    /**
     * @var array<string, ReferenceExtractor>
     */
    protected array $resolved = [];

    public function __construct(
        protected NormalizerPipeline $paths,
        protected DatabaseManager $db,
        protected Repository $config,
        protected Container $container,
    ) {}

    /**
     * Register an extractor for a source type at runtime.
     *
     * @param  ReferenceExtractor|class-string<ReferenceExtractor>  $extractor
     */
    public function extend(string $type, ReferenceExtractor|string $extractor): void
    {
        $this->extensions[$type] = $extractor;

        unset($this->resolved[$type]);
    }

    /**
     * @throws InvalidSourceException
     */
    public function extractorFor(Source $source): ReferenceExtractor
    {
        if (isset($this->resolved[$source->type])) {
            return $this->resolved[$source->type];
        }

        $configured = (array) $this->config->get('orphan-storage.extractors', []);
        $extractor = $this->extensions[$source->type] ?? $configured[$source->type] ?? $source->type;

        if (is_string($extractor) && class_exists($extractor)) {
            $extractor = $this->container->make($extractor);
        }

        if (! $extractor instanceof ReferenceExtractor) {
            throw InvalidSourceException::unknownType($source);
        }

        return $this->resolved[$source->type] = $extractor;
    }

    /**
     * Stream every reference held by the sources, resolved for the given disks.
     *
     * @param  list<Source>  $sources
     * @param  list<string>  $disks
     * @return Generator<int, Reference>
     */
    public function collect(array $sources, array $disks): Generator
    {
        foreach ($sources as $source) {
            $extractor = $this->extractorFor($source);

            foreach ($this->rows($source) as $row) {
                yield from $this->resolve($extractor, $source, $this->valueOf($row, $source), $disks);
            }
        }
    }

    /**
     * Find the rows that reference a file.
     *
     * @param  list<Source>  $sources
     * @return Generator<int, array{source: Source, key: mixed, value: mixed, reference: Reference}>
     */
    public function rowsReferencing(array $sources, string $disk, string $path): Generator
    {
        $target = $this->paths->key($path);
        $ancestors = array_flip(array_map(fn (string $directory): string => $this->paths->key($directory), $this->ancestors($path)));

        foreach ($sources as $source) {
            $extractor = $this->extractorFor($source);

            foreach ($this->searchRows($source, $this->needles($source, $path)) as $row) {
                $value = $this->valueOf($row, $source);

                foreach ($this->resolve($extractor, $source, $value, [$disk]) as $reference) {
                    $key = $this->paths->key($reference->path);

                    if ($reference->isDirectory ? isset($ancestors[$key]) : $key === $target) {
                        yield [
                            'source' => $source,
                            'key' => $source->primaryKey !== null ? ($row->{$source->primaryKey} ?? null) : null,
                            'value' => $value,
                            'reference' => $reference,
                        ];

                        break;
                    }
                }
            }
        }
    }

    /**
     * Resolve one value into disk references.
     *
     * @param  list<string>  $disks
     * @return Generator<int, Reference>
     */
    public function resolve(ReferenceExtractor $extractor, Source $source, mixed $value, array $disks): Generator
    {
        if ($value === null) {
            return;
        }

        foreach ($extractor->extract($value, $source) as $item) {
            $reference = match (true) {
                $item instanceof Reference => $item,
                is_string($item) => Reference::file($item),
                default => null,
            };

            if ($reference === null) {
                continue;
            }

            $disk = $reference->disk ?? $source->disk;
            $targets = $disk === null ? $disks : (in_array($disk, $disks, true) ? [$disk] : []);

            foreach ($targets as $target) {
                foreach ($this->paths->paths($reference->path, $target) as $path) {
                    yield new Reference($reference->isDirectory ? $path.'/' : $path, $target, $reference->isDirectory);
                }
            }
        }
    }

    /**
     * Read a source in chunks, never loading the whole table.
     *
     * @param  list<string>  $needles  Only rows containing one of these strings.
     * @return LazyCollection<int, object>
     */
    public function rows(Source $source, array $needles = []): LazyCollection
    {
        /** @var Builder $query */
        // Read from the primary: a lagging replica could miss a new reference and make a used file look orphaned.
        $query = $this->db->connection($source->connection)->table($source->table)->useWritePdo();
        $chunkSize = max(1, (int) $this->config->get('orphan-storage.chunk_size', 1000));

        if ($source->column !== null) {
            $query->select(array_values(array_unique(array_filter([$source->primaryKey, $source->column]))))
                ->whereNotNull($source->column);
        }

        if ($needles !== [] && $source->column !== null) {
            $column = $query->getGrammar()->wrap($source->column);

            $query->where(function (Builder $query) use ($needles, $column): void {
                foreach ($needles as $needle) {
                    $query->orWhereRaw("{$column} like ? escape '!'", ['%'.$this->escapeLike($needle).'%']);
                }
            });
        }

        if ($source->primaryKey === null) {
            throw InvalidSourceException::invalidDefinition($source->label(), 'a unique "primary_key" is required to read the table in chunks.');
        }

        return $query->lazyById($chunkSize, $source->primaryKey);
    }

    /**
     * Rows narrowed down with LIKE when the database supports it on the column.
     *
     * @param  list<string>  $needles
     * @return Generator<int, object>
     */
    protected function searchRows(Source $source, array $needles): Generator
    {
        if ($needles !== []) {
            try {
                foreach ($this->rows($source, $needles) as $row) {
                    yield $row;
                }

                return;
            } catch (QueryException) {
                // e.g. LIKE on a jsonb column; fall back to reading every row.
            }
        }

        yield from $this->rows($source);
    }

    /**
     * A string that must appear in any column value referencing the path.
     *
     * The longest run of URL-unreserved characters (A-Z a-z 0-9 . _ ~ -) in
     * the file name survives URL encoding, HTML entities and JSON escaping
     * unchanged, so it is safe to search for literally.
     *
     * @return list<string>
     */
    protected function needles(Source $source, string $path): array
    {
        if ($source->column === null
            || ! in_array($source->type, self::SEARCHABLE_TYPES, true)
            || $this->paths->hasCustomNormalizers()
            || ! $this->config->get('orphan-storage.case_sensitive', true)) {
            return [];
        }

        preg_match_all('/[A-Za-z0-9._~-]+/', basename($path), $matches);

        $needle = '';

        foreach ($matches[0] as $run) {
            if (strlen($run) > strlen($needle)) {
                $needle = $run;
            }
        }

        return $needle === '' ? [] : [$needle];
    }

    /**
     * "a/b/c.jpg" becomes ["a/", "a/b/"].
     *
     * @return list<string>
     */
    public function ancestors(string $path): array
    {
        $segments = explode('/', $path);
        $ancestors = [];

        for ($length = 1; $length < count($segments); $length++) {
            $ancestors[] = implode('/', array_slice($segments, 0, $length)).'/';
        }

        return $ancestors;
    }

    protected function valueOf(object $row, Source $source): mixed
    {
        return $source->column === null ? get_object_vars($row) : ($row->{$source->column} ?? null);
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
