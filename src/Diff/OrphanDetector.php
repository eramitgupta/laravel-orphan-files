<?php

namespace Erag\OrphanStorage\Diff;

use Erag\OrphanStorage\Exceptions\TemporaryTableUnavailable;
use Erag\OrphanStorage\References\ReferenceCollector;
use Erag\OrphanStorage\References\Source;
use Erag\OrphanStorage\Scanner\FileInfo;
use Erag\OrphanStorage\Support\Chunks;
use Erag\OrphanStorage\Support\Normalizers\NormalizerPipeline;
use Generator;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Computes "storage minus references".
 *
 * References are indexed first (in memory, moving to a temporary table
 * past `diff.memory_limit`), then storage files are streamed and checked
 * in chunks. A file is referenced when its own key or one of its parent
 * directories (directory references) is in the index.
 */
class OrphanDetector
{
    public const DRIVERS = ['auto', 'memory', 'database'];

    protected ?ReferenceIndex $index = null;

    protected bool $hasDirectories = false;

    /**
     * Set when auto mode could not create the temporary table and stays in memory.
     */
    protected bool $databaseUnavailable = false;

    public function __construct(
        protected ReferenceCollector $collector,
        protected NormalizerPipeline $paths,
        protected Repository $config,
        protected DatabaseManager $db,
        protected Container $container,
        protected LoggerInterface $logger,
    ) {}

    /**
     * Index every reference the sources hold for the given disks.
     *
     * @param  list<Source>  $sources
     * @param  list<string>  $disks
     * @return int The number of unique reference keys.
     *
     * @throws InvalidArgumentException When `diff.driver` is not auto, memory or database.
     * @throws TemporaryTableUnavailable When the database driver cannot create its table.
     */
    public function index(array $sources, array $disks): int
    {
        $this->release();

        $driver = $this->driver();
        $this->index = $driver === 'database' ? $this->databaseIndex() : new MemoryReferenceIndex;

        $references = (function () use ($sources, $disks): Generator {
            foreach ($this->collector->collect($sources, $disks) as $reference) {
                $this->hasDirectories = $this->hasDirectories || $reference->isDirectory;

                yield $this->hash((string) $reference->disk, $reference->path);
            }
        })();

        foreach (Chunks::of($references, $this->chunkSize()) as $hashes) {
            $this->index->add($hashes);

            if ($driver === 'auto'
                && ! $this->databaseUnavailable
                && $this->index instanceof MemoryReferenceIndex
                && $this->index->count() > $this->memoryLimit()) {
                $this->spillToDatabase($this->index);
            }
        }

        return $this->index->count();
    }

    /**
     * Mark each storage file as referenced or not.
     *
     * @param  iterable<int, FileInfo>  $files
     * @return Generator<int, array{file: FileInfo, referenced: bool}>
     */
    public function classify(iterable $files): Generator
    {
        if ($this->index === null) {
            throw new LogicException('Build the reference index with index() before classifying files.');
        }

        foreach (Chunks::of($files, $this->chunkSize()) as $chunk) {
            $lookups = array_map(fn (FileInfo $file): array => $this->lookupHashes($file), $chunk);
            $found = $this->index->existing(array_values(array_unique(array_merge(...$lookups))));

            foreach ($chunk as $position => $file) {
                $referenced = false;

                foreach ($lookups[$position] as $hash) {
                    if (isset($found[$hash])) {
                        $referenced = true;

                        break;
                    }
                }

                yield ['file' => $file, 'referenced' => $referenced];
            }
        }
    }

    public function usesDatabase(): bool
    {
        return $this->index instanceof TempTableDiffer;
    }

    public function release(): void
    {
        $this->index?->release();
        $this->index = null;
        $this->hasDirectories = false;
        $this->databaseUnavailable = false;
    }

    /**
     * The hashed index key of a disk path.
     */
    public function hash(string $disk, string $path): string
    {
        return hash('xxh128', $disk."\0".$this->paths->key($path));
    }

    /**
     * @return list<string>
     */
    protected function lookupHashes(FileInfo $file): array
    {
        $path = $this->paths->clean($file->path, $file->disk) ?? $file->path;
        $hashes = [$this->hash($file->disk, $path)];

        if ($this->hasDirectories) {
            foreach ($this->collector->ancestors($path) as $directory) {
                $hashes[] = $this->hash($file->disk, $directory);
            }
        }

        return $hashes;
    }

    /**
     * Move the in-memory references to a temporary table.
     *
     * If the table cannot be created, the references stay in memory and a
     * warning is logged. Any other failure releases the new table before the
     * exception is rethrown, so nothing is left behind.
     */
    protected function spillToDatabase(MemoryReferenceIndex $memory): void
    {
        $database = $this->databaseIndex();

        try {
            $database->add($memory->hashes());
        } catch (TemporaryTableUnavailable $exception) {
            $database->release();
            $this->databaseUnavailable = true;

            $this->logger->warning('Orphan storage keeps references in memory: '.$exception->getMessage(), [
                'references' => $memory->count(),
            ]);

            return;
        } catch (Throwable $exception) {
            $database->release();

            throw $exception;
        }

        $memory->release();

        $this->index = $database;
    }

    protected function databaseIndex(): TempTableDiffer
    {
        /** @var Connection $connection */
        $connection = $this->db->connection($this->config->get('orphan-storage.diff.connection'));

        return $this->container->make(TempTableDiffer::class, ['connection' => $connection]);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function driver(): string
    {
        $driver = $this->config->get('orphan-storage.diff.driver', 'auto');

        if (! is_string($driver) || ! in_array($driver, self::DRIVERS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid "orphan-storage.diff.driver" [%s]. Use "auto" (memory, moving to a temporary table past diff.memory_limit), "memory" or "database".',
                is_scalar($driver) ? (string) $driver : get_debug_type($driver),
            ));
        }

        return $driver;
    }

    protected function chunkSize(): int
    {
        return max(1, (int) $this->config->get('orphan-storage.chunk_size', 1000));
    }

    protected function memoryLimit(): int
    {
        return max(1, (int) $this->config->get('orphan-storage.diff.memory_limit', 250000));
    }
}
