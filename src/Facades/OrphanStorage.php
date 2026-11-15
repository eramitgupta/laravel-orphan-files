<?php

namespace Erag\OrphanStorage\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Erag\OrphanStorage\Support\Data\ScanReport scan(?\Erag\OrphanStorage\Support\Data\ScanOptions $options = null, ?callable $onOrphan = null)
 * @method static \Erag\OrphanStorage\Support\Data\CleanResult clean(?\Erag\OrphanStorage\Support\Data\CleanOptions $options = null, ?callable $onOrphan = null)
 * @method static \Erag\OrphanStorage\Support\Data\Explanation explain(string $path, ?string $disk = null)
 * @method static bool isReferenced(string $disk, string $path)
 * @method static \Erag\OrphanStorage\Support\Data\ActionResult restore(string $disk, array $paths, ?string $date = null, bool $overwrite = false)
 * @method static \Erag\OrphanStorage\Support\Data\ActionResult restoreAll(string $disk, ?string $date = null, bool $overwrite = false)
 * @method static \Generator trashed(string $disk, ?string $date = null, string $prefix = '')
 * @method static \Erag\OrphanStorage\Support\Data\ActionResult purgeTrash(?string $disk = null, ?int $retentionDays = null, bool $all = false, bool $dryRun = false)
 * @method static void queueScan(?\Erag\OrphanStorage\Support\Data\ScanOptions $options = null)
 * @method static void queueClean(?\Erag\OrphanStorage\Support\Data\CleanOptions $options = null)
 * @method static string defaultDisk()
 * @method static array sources()
 * @method static \Erag\OrphanStorage\OrphanStorage addSource(\Erag\OrphanStorage\References\Source|array $source)
 * @method static \Erag\OrphanStorage\OrphanStorage extend(string $type, \Erag\OrphanStorage\Contracts\ReferenceExtractor|string $extractor)
 * @method static \Erag\OrphanStorage\OrphanStorage normalizeUsing(\Erag\OrphanStorage\Contracts\PathNormalizer|callable $normalizer)
 * @method static \Erag\OrphanStorage\OrphanStorage filterUsing(\Erag\OrphanStorage\Contracts\FileFilter $filter)
 *
 * @see \Erag\OrphanStorage\OrphanStorage
 */
class OrphanStorage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Erag\OrphanStorage\OrphanStorage::class;
    }
}
