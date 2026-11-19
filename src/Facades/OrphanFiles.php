<?php

namespace Erag\OrphanFiles\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Erag\OrphanFiles\Support\Data\ScanReport scan(?\Erag\OrphanFiles\Support\Data\ScanOptions $options = null, ?callable $onOrphan = null)
 * @method static \Erag\OrphanFiles\Support\Data\CleanResult clean(?\Erag\OrphanFiles\Support\Data\CleanOptions $options = null, ?callable $onOrphan = null)
 * @method static \Erag\OrphanFiles\Support\Data\Explanation explain(string $path, ?string $disk = null)
 * @method static bool isReferenced(string $disk, string $path)
 * @method static \Erag\OrphanFiles\Support\Data\ActionResult restore(string $disk, list<string> $paths, ?string $date = null, bool $overwrite = false)
 * @method static \Erag\OrphanFiles\Support\Data\ActionResult restoreAll(string $disk, ?string $date = null, bool $overwrite = false)
 * @method static \Generator<int, \Erag\OrphanFiles\Support\Data\TrashedFile> trashed(string $disk, ?string $date = null, string $prefix = '')
 * @method static list<string> trashDisks()
 * @method static bool inTrash(string $disk, string $path, ?string $date = null)
 * @method static \Erag\OrphanFiles\Support\Data\ActionResult purgeTrash(?string $disk = null, ?int $retentionDays = null, bool $all = false, bool $dryRun = false)
 * @method static void queueScan(?\Erag\OrphanFiles\Support\Data\ScanOptions $options = null)
 * @method static void queueClean(?\Erag\OrphanFiles\Support\Data\CleanOptions $options = null)
 * @method static string defaultDisk()
 * @method static list<\Erag\OrphanFiles\References\Source> sources()
 * @method static \Erag\OrphanFiles\OrphanFiles addSource(\Erag\OrphanFiles\References\Source|array<string, mixed> $source)
 * @method static \Erag\OrphanFiles\OrphanFiles extend(string $type, \Erag\OrphanFiles\Contracts\ReferenceExtractor|string $extractor)
 * @method static \Erag\OrphanFiles\OrphanFiles normalizeUsing(\Erag\OrphanFiles\Contracts\PathNormalizer|callable $normalizer)
 * @method static \Erag\OrphanFiles\OrphanFiles filterUsing(\Erag\OrphanFiles\Contracts\FileFilter $filter)
 *
 * @see \Erag\OrphanFiles\OrphanFiles
 */
class OrphanFiles extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Erag\OrphanFiles\OrphanFiles::class;
    }
}
