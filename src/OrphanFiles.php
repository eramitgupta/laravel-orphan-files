<?php

namespace Erag\OrphanFiles;

use Closure;
use Erag\OrphanFiles\Actions\DeleteFiles;
use Erag\OrphanFiles\Actions\PurgeTrash;
use Erag\OrphanFiles\Actions\RestoreFiles;
use Erag\OrphanFiles\Actions\TrashFiles;
use Erag\OrphanFiles\Contracts\FileFilter;
use Erag\OrphanFiles\Contracts\PathNormalizer;
use Erag\OrphanFiles\Contracts\ReferenceExtractor;
use Erag\OrphanFiles\Diff\OrphanDetector;
use Erag\OrphanFiles\Events\OrphanScanCompleted;
use Erag\OrphanFiles\Exceptions\InvalidSourceException;
use Erag\OrphanFiles\Exceptions\SafetyThresholdExceeded;
use Erag\OrphanFiles\Jobs\CleanOrphansJob;
use Erag\OrphanFiles\Jobs\RunScanJob;
use Erag\OrphanFiles\References\ReferenceCollector;
use Erag\OrphanFiles\References\Source;
use Erag\OrphanFiles\References\SourceRegistry;
use Erag\OrphanFiles\Scanner\FileInfo;
use Erag\OrphanFiles\Scanner\StorageScanner;
use Erag\OrphanFiles\Support\Data\ActionResult;
use Erag\OrphanFiles\Support\Data\CleanOptions;
use Erag\OrphanFiles\Support\Data\CleanResult;
use Erag\OrphanFiles\Support\Data\Explanation;
use Erag\OrphanFiles\Support\Data\ScanOptions;
use Erag\OrphanFiles\Support\Data\ScanReport;
use Erag\OrphanFiles\Support\Data\TrashedFile;
use Erag\OrphanFiles\Support\Filters\ExcludePathFilter;
use Erag\OrphanFiles\Support\Filters\FileRuleFilter;
use Erag\OrphanFiles\Support\Filters\GracePeriodFilter;
use Erag\OrphanFiles\Support\Filters\SafetyRatioGuard;
use Erag\OrphanFiles\Support\Normalizers\NormalizerPipeline;
use Erag\OrphanFiles\Support\Trash;
use Generator;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The orphan files pipeline: validate sources, index references, stream
 * storage, filter and act. Commands and jobs only call into this class.
 */
class OrphanFiles
{
    public const MODES = ['trash', 'delete'];

    /**
     * @var list<FileFilter>
     */
    protected array $filters = [];

    public function __construct(
        protected Container $container,
        protected Repository $config,
        protected SourceRegistry $sources,
        protected ReferenceCollector $collector,
        protected NormalizerPipeline $paths,
        protected StorageScanner $scanner,
        protected Trash $trash,
        protected Dispatcher $events,
        protected BusDispatcher $bus,
    ) {}

    /**
     * Report storage files that no database row references.
     *
     * @param  (callable(FileInfo, string|null): void)|null  $onOrphan  Called for every unreferenced file with the reason it is kept (null when cleanable).
     *
     * @throws InvalidSourceException
     */
    public function scan(?ScanOptions $options = null, ?callable $onOrphan = null): ScanReport
    {
        $options ??= new ScanOptions;
        $targets = $this->targets($options);
        $this->filters($options->minimumAgeMinutes); // Fail on invalid custom filters before reading anything.

        return $this->withIndex($targets, function (OrphanDetector $detector, int $references, int $indexedAt) use ($options, $targets, $onOrphan): ScanReport {
            $filters = $this->filters($options->minimumAgeMinutes, $indexedAt);
            $report = $this->classify($detector, $targets, $filters, $onOrphan);
            $report->references = $references;

            $this->events->dispatch(new OrphanScanCompleted($report));

            return $report;
        });
    }

    /**
     * Trash or delete orphans. Runs as a dry run unless `dryRun` is false.
     *
     * @param  (callable(FileInfo, string|null): void)|null  $onOrphan
     *
     * @throws InvalidSourceException
     * @throws SafetyThresholdExceeded
     */
    public function clean(?CleanOptions $options = null, ?callable $onOrphan = null): CleanResult
    {
        $options ??= new CleanOptions;
        $mode = $this->mode($options->mode);
        $targets = $this->targets($options->scan);
        $this->filters($options->scan->minimumAgeMinutes); // Fail on invalid custom filters before reading anything.
        $guard = $this->guard();

        return $this->withIndex($targets, function (OrphanDetector $detector, int $references, int $indexedAt) use ($options, $mode, $targets, $guard, $onOrphan): CleanResult {
            // Ages are measured from when the database was read, not from "now", so files uploaded during a long run stay safe.
            $filters = $this->filters($options->scan->minimumAgeMinutes, $indexedAt);
            $report = $this->classify($detector, $targets, $filters, $onOrphan);
            $report->references = $references;

            $this->events->dispatch(new OrphanScanCompleted($report));

            if ($options->dryRun) {
                $violations = $options->forceRatio ? [] : $guard->violations($report);

                return new CleanResult($report, $mode, true, null, $violations, $options->limit);
            }

            if (! $options->forceRatio) {
                $guard->check($report);
            }

            $candidates = $this->cleanable($detector, $targets, $filters, $options->limit);

            $action = $mode === 'delete'
                ? $this->container->make(DeleteFiles::class)->handle($candidates)
                : $this->container->make(TrashFiles::class)->handle($candidates);

            return new CleanResult($report, $mode, false, $action, [], $options->limit);
        });
    }

    /**
     * Explain whether a file is referenced, and where, or why it counts as an orphan.
     *
     * @param  string  $path  A disk path or a URL.
     *
     * @throws InvalidSourceException
     */
    public function explain(string $path, ?string $disk = null): Explanation
    {
        $disk ??= $this->defaultDisk();

        $this->paths->flush();

        $clean = $this->paths->primaryPath($path, $disk) ?? $this->paths->clean($path, $disk);

        if ($clean === null) {
            throw new InvalidArgumentException('Pass the path or URL of the file you want explained.');
        }

        $sources = $this->sources->all();
        $this->sources->validate($sources);

        $file = $this->scanner->file($disk, $clean);
        $references = [];

        foreach ($this->collector->rowsReferencing($sources, $disk, $clean) as $match) {
            $references[] = [
                'source' => $match['source']->label(),
                'type' => $match['source']->type,
                'key' => $match['key'],
                'value' => $this->excerpt($match['value'], $clean),
            ];

            if (count($references) >= 25) {
                break;
            }
        }

        $directories = $this->configuredDisks()[$disk] ?? [];
        $trashPrefix = $this->trash->excludedPrefixOn($disk);
        $inScope = ($trashPrefix === null || ! str_starts_with($clean, $trashPrefix))
            && $this->withinDirectories($clean, $directories);

        $reasonToKeep = $file !== null && $references === []
            ? $this->reasonToKeep($file, $this->filters(null))[1]
            : null;

        return new Explanation($disk, $clean, $file, $references, $reasonToKeep, $inScope, $directories, count($sources));
    }

    /**
     * Whether any source references the file. Read-only: nothing is changed.
     */
    public function isReferenced(string $disk, string $path): bool
    {
        foreach ($this->collector->rowsReferencing($this->sources->all(), $disk, $path) as $match) {
            return true;
        }

        return false;
    }

    /**
     * Restore trashed files (or directories) to their original paths.
     *
     * @param  list<string>  $paths
     */
    public function restore(string $disk, array $paths, ?string $date = null, bool $overwrite = false): ActionResult
    {
        return $this->container->make(RestoreFiles::class)->handle($disk, $paths, $date, $overwrite);
    }

    /**
     * Restore everything trashed from a disk.
     */
    public function restoreAll(string $disk, ?string $date = null, bool $overwrite = false): ActionResult
    {
        return $this->container->make(RestoreFiles::class)->all($disk, $date, $overwrite);
    }

    /**
     * @return Generator<int, TrashedFile>
     */
    public function trashed(string $disk, ?string $date = null, string $prefix = ''): Generator
    {
        return $this->trash->entries($disk, $date, $prefix);
    }

    /**
     * Permanently delete trash older than the retention period.
     */
    public function purgeTrash(?string $disk = null, ?int $retentionDays = null, bool $all = false, bool $dryRun = false): ActionResult
    {
        return $this->container->make(PurgeTrash::class)->handle($disk, $retentionDays, $all, $dryRun);
    }

    public function queueScan(?ScanOptions $options = null): void
    {
        $this->bus->dispatch($this->onQueue(new RunScanJob($options ?? new ScanOptions)));
    }

    public function queueClean(?CleanOptions $options = null): void
    {
        $this->bus->dispatch($this->onQueue(new CleanOrphansJob($options ?? new CleanOptions)));
    }

    /**
     * Every configured, discovered and registered source.
     *
     * @return list<Source>
     */
    public function sources(): array
    {
        return $this->sources->all();
    }

    /**
     * @param  Source|array<string, mixed>  $source
     */
    public function addSource(Source|array $source): static
    {
        $this->sources->add($source);

        return $this;
    }

    /**
     * Register an extractor for a custom source type.
     *
     * @param  ReferenceExtractor|class-string<ReferenceExtractor>  $extractor
     */
    public function extend(string $type, ReferenceExtractor|string $extractor): static
    {
        $this->collector->extend($type, $extractor);

        return $this;
    }

    /**
     * Register an extra path normalizer.
     *
     * @param  PathNormalizer|callable(string, string): ?string  $normalizer
     */
    public function normalizeUsing(PathNormalizer|callable $normalizer): static
    {
        $this->paths->extend($normalizer);

        return $this;
    }

    /**
     * Register an extra filter that can keep unreferenced files.
     */
    public function filterUsing(FileFilter $filter): static
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * The disks and directories to scan.
     *
     * @return array<string, list<string>>
     *
     * @throws InvalidArgumentException
     */
    public function targets(ScanOptions $options): array
    {
        $configured = $this->configuredDisks();
        $disks = $options->disks !== null && $options->disks !== [] ? $options->disks : array_keys($configured);

        if ($disks === []) {
            throw new InvalidArgumentException('No disks to scan. Add them under "disks" in config/orphan-files.php.');
        }

        $targets = [];

        foreach ($disks as $disk) {
            $disk = (string) $disk;

            if (! is_array($this->config->get("filesystems.disks.{$disk}"))) {
                throw new InvalidArgumentException("Disk [{$disk}] doesn't exist in config/filesystems.php.");
            }

            if (! isset($configured[$disk])) {
                throw new InvalidArgumentException("Disk [{$disk}] isn't listed under \"disks\" in config/orphan-files.php. Add it there before scanning it.");
            }

            $directories = $options->directories !== null && $options->directories !== []
                ? $this->narrow($configured[$disk], $this->requestedDirectories($options->directories))
                : $configured[$disk];

            if ($directories !== []) {
                $targets[$disk] = $directories;
            }
        }

        if ($targets === []) {
            throw new InvalidArgumentException(sprintf(
                'The directories [%s] are outside the configured directories of every selected disk.',
                implode(', ', (array) $options->directories),
            ));
        }

        return $targets;
    }

    /**
     * The first configured disk, or the application's default disk.
     */
    public function defaultDisk(): string
    {
        return (string) (array_key_first($this->configuredDisks()) ?? $this->config->get('filesystems.default', 'local'));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function mode(?string $mode): string
    {
        $mode ??= (string) $this->config->get('orphan-files.mode', 'trash');

        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException("Invalid mode [{$mode}]. Use \"trash\" or \"delete\".");
        }

        return $mode;
    }

    public function guard(): SafetyRatioGuard
    {
        $ratio = $this->config->get('orphan-files.max_orphan_ratio', 90);

        return new SafetyRatioGuard(is_numeric($ratio) ? (float) $ratio : null);
    }

    /**
     * @param  array<string, list<string>>  $targets
     * @param  Closure(OrphanDetector, int, int): mixed  $callback  Receives the detector, the reference count and when the database read started.
     */
    protected function withIndex(array $targets, Closure $callback): mixed
    {
        $sources = $this->sources->all();
        $this->sources->validate($sources);
        $this->paths->flush();

        $detector = $this->container->make(OrphanDetector::class);
        $indexedAt = Date::now()->getTimestamp();

        try {
            return $callback($detector, $detector->index($sources, array_map('strval', array_keys($targets))), $indexedAt);
        } finally {
            $detector->release();
        }
    }

    /**
     * @param  array<string, list<string>>  $targets
     * @param  list<FileFilter>  $filters
     * @param  (callable(FileInfo, string|null): void)|null  $onOrphan
     */
    protected function classify(OrphanDetector $detector, array $targets, array $filters, ?callable $onOrphan): ScanReport
    {
        $report = new ScanReport(Date::now());

        foreach ($targets as $disk => $directories) {
            $diskReport = $report->disk((string) $disk, $directories);

            foreach ($detector->classify($this->files((string) $disk, $directories)) as ['file' => $file, 'referenced' => $referenced]) {
                [$keptBy, $reason] = $referenced ? [null, null] : $this->reasonToKeep($file, $filters);

                $diskReport->record($file, $referenced, $keptBy);

                if (! $referenced && $onOrphan !== null) {
                    $onOrphan($file, $reason);
                }
            }
        }

        return $report->finish();
    }

    /**
     * Second pass of a clean: stream the cleanable files again.
     *
     * @param  array<string, list<string>>  $targets
     * @param  list<FileFilter>  $filters
     * @return Generator<int, FileInfo>
     */
    protected function cleanable(OrphanDetector $detector, array $targets, array $filters, ?int $limit): Generator
    {
        if ($limit !== null && $limit <= 0) {
            return;
        }

        $count = 0;

        foreach ($targets as $disk => $directories) {
            foreach ($detector->classify($this->files((string) $disk, $directories)) as ['file' => $file, 'referenced' => $referenced]) {
                if ($referenced || $this->reasonToKeep($file, $filters)[0] !== null) {
                    continue;
                }

                yield $file;

                if ($limit !== null && ++$count >= $limit) {
                    return;
                }
            }
        }
    }

    /**
     * @param  list<string>  $directories
     * @return Generator<int, FileInfo>
     */
    protected function files(string $disk, array $directories): Generator
    {
        return $this->scanner->files($disk, $directories, $this->trash->excludedPrefixOn($disk));
    }

    /**
     * The first filter that keeps the file, as [filter name, reason].
     *
     * @param  iterable<int, FileFilter>  $filters
     * @return array{0: string|null, 1: string|null}
     */
    protected function reasonToKeep(FileInfo $file, iterable $filters): array
    {
        foreach ($filters as $filter) {
            $reason = $filter->reasonToKeep($file);

            if ($reason !== null) {
                $name = class_basename($filter);

                return [str_contains($name, '@') ? 'custom' : Str::snake(Str::beforeLast($name, 'Filter')), $reason];
            }
        }

        return [null, null];
    }

    /**
     * @return list<FileFilter>
     */
    protected function filters(?int $minimumAgeMinutes, ?int $referenceTime = null): array
    {
        $graceMinutes = $minimumAgeMinutes ?? (int) round((float) $this->config->get('orphan-files.grace_period_hours', 24) * 60);
        $custom = [];

        foreach ((array) $this->config->get('orphan-files.filters', []) as $filter) {
            $filter = is_string($filter) ? $this->container->make($filter) : $filter;

            if (! $filter instanceof FileFilter) {
                throw new InvalidArgumentException('Every class in orphan-files.filters has to implement FileFilter.');
            }

            $custom[] = $filter;
        }

        $minSize = $this->config->get('orphan-files.min_size');
        $maxSize = $this->config->get('orphan-files.max_size');

        return [
            new ExcludePathFilter(
                array_values(array_filter((array) $this->config->get('orphan-files.exclude_paths', []), 'is_string')),
                array_values(array_filter((array) $this->config->get('orphan-files.exclude_patterns', []), 'is_string')),
                (bool) $this->config->get('orphan-files.case_sensitive', true),
            ),
            new GracePeriodFilter($graceMinutes, $referenceTime),
            new FileRuleFilter(
                is_numeric($minSize) ? (int) $minSize : null,
                is_numeric($maxSize) ? (int) $maxSize : null,
                array_values(array_filter((array) $this->config->get('orphan-files.only_extensions', []), 'is_string')),
                array_values(array_filter((array) $this->config->get('orphan-files.except_extensions', []), 'is_string')),
            ),
            ...$custom,
            ...$this->filters,
        ];
    }

    /**
     * Configured disks with their cleaned directory lists.
     *
     * @return array<string, list<string>>
     */
    protected function configuredDisks(): array
    {
        $disks = [];

        foreach ((array) $this->config->get('orphan-files.disks', []) as $disk => $directories) {
            if (is_int($disk) && is_string($directories)) {
                $disks[$directories] = [''];

                continue;
            }

            $disks[(string) $disk] = $this->directories((array) $directories);
        }

        return $disks;
    }

    /**
     * Directories passed with --dir. Unlike config entries, "*" or "/" here do not widen the scan.
     *
     * @param  list<string>  $directories
     * @return list<string>
     */
    protected function requestedDirectories(array $directories): array
    {
        $requested = [];

        foreach ($directories as $directory) {
            $directory = trim(str_replace('\\', '/', trim($directory)), '/');
            $requested[] = $directory === '' || $directory === '*' ? '' : $directory.'/';
        }

        return array_values(array_unique($requested));
    }

    /**
     * The part of the requested directories that lies inside the configured ones.
     *
     * @param  list<string>  $configured
     * @param  list<string>  $requested
     * @return list<string>
     */
    protected function narrow(array $configured, array $requested): array
    {
        $directories = [];

        foreach ($requested as $directory) {
            foreach ($configured as $allowed) {
                if ($allowed === '' || ($directory !== '' && str_starts_with($directory, $allowed))) {
                    $directories[] = $directory;
                } elseif ($directory === '' || str_starts_with($allowed, $directory)) {
                    $directories[] = $allowed;
                }
            }
        }

        return $directories === [] ? [] : $this->directories($directories);
    }

    /**
     * Clean directory names and drop directories nested in another one.
     *
     * @param  array<int|string, mixed>  $directories
     * @return list<string> [''] means the whole disk.
     */
    protected function directories(array $directories): array
    {
        $clean = [];

        foreach ($directories as $directory) {
            if (! is_string($directory)) {
                continue;
            }

            $directory = trim(str_replace('\\', '/', trim($directory)), '/');

            if ($directory === '' || $directory === '*') {
                return [''];
            }

            $clean[] = $directory.'/';
        }

        if ($clean === []) {
            return [''];
        }

        $clean = array_values(array_unique($clean));
        sort($clean);

        $result = [];

        foreach ($clean as $directory) {
            foreach ($result as $parent) {
                if (str_starts_with($directory, $parent)) {
                    continue 2;
                }
            }

            $result[] = $directory;
        }

        return $result;
    }

    /**
     * @param  list<string>  $directories
     */
    protected function withinDirectories(string $path, array $directories): bool
    {
        foreach ($directories as $directory) {
            if ($directory === '' || str_starts_with($path, $directory)) {
                return true;
            }
        }

        return false;
    }

    protected function excerpt(mixed $value, string $path): string
    {
        $text = is_string($value) ? $value : (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $excerpt = Str::excerpt($text, basename($path), ['radius' => 60]);

        return trim($excerpt ?? Str::limit($text, 120));
    }

    /**
     * @template TJob of RunScanJob|CleanOrphansJob
     *
     * @param  TJob  $job
     * @return TJob
     */
    protected function onQueue(RunScanJob|CleanOrphansJob $job): RunScanJob|CleanOrphansJob
    {
        $connection = $this->config->get('orphan-files.queue.connection');
        $queue = $this->config->get('orphan-files.queue.name');

        if (is_string($connection) && $connection !== '') {
            $job->onConnection($connection);
        }

        if (is_string($queue) && $queue !== '') {
            $job->onQueue($queue);
        }

        return $job;
    }
}
