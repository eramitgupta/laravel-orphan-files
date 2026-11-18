<?php

namespace Erag\OrphanFiles\Support\Filters;

use Erag\OrphanFiles\Contracts\FileFilter;
use Erag\OrphanFiles\Scanner\FileInfo;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Keeps recently modified files, which may belong to a database row that
 * has not been saved yet.
 */
class GracePeriodFilter implements FileFilter
{
    /**
     * @param  int|null  $referenceTime  When the database was read (unix time). Ages are measured from
     *                                   this moment, so a file uploaded during a long run is never
     *                                   cleaned just because its row was saved after its table was read.
     */
    public function __construct(
        protected int $minutes,
        protected ?int $referenceTime = null,
    ) {}

    public function reasonToKeep(FileInfo $file): ?string
    {
        if ($this->minutes <= 0 && $this->referenceTime === null) {
            return null;
        }

        if ($file->lastModified === null) {
            return 'its modification time is unknown';
        }

        $referenceTime = $this->referenceTime ?? Date::now()->getTimestamp();

        if ($file->lastModified > $referenceTime) {
            return 'it was modified after the database was read';
        }

        if ($referenceTime - $file->lastModified < $this->minutes * 60) {
            return sprintf('it was modified within the grace period (%s)', $this->describe($this->minutes));
        }

        return null;
    }

    protected function describe(int $minutes): string
    {
        return match (true) {
            $minutes >= 2880 && $minutes % 1440 === 0 => ($minutes / 1440).' days',
            $minutes % 60 === 0 => ($minutes / 60).' '.Str::plural('hour', intdiv($minutes, 60)),
            default => $minutes.' '.Str::plural('minute', $minutes),
        };
    }
}
